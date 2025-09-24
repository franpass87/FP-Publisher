<?php
/**
 * Handles scheduling and publishing of social posts.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Scheduler for social posts.
 */
class TTS_Scheduler implements TTS_Scheduler_Interface {

    private $integration_gateway;
    private $telemetry_channel;
    private $channel_queue;
    private $rate_limiter;
    private $error_recovery;
    private $publisher_guard;
    private $notifier;
    private $retry_state_meta_key = '_tts_channel_retry_state';
    private $queued_channels_meta_key = '_tts_queued_channels';
    private $finalized_meta_key = '_tts_publication_finalized';
    private $default_channel_config = array(
        'max_pending' => 10,
        'retry'       => array(
            'strategy'     => 'progressive',
            'global_max'   => 5,
            'jitter'       => 60,
            'delays'       => array(
                'low'      => 5,
                'medium'   => 15,
                'high'     => 30,
                'critical' => 60,
            ),
            'max_attempts' => array(
                'low'      => 5,
                'medium'   => 4,
                'high'     => 3,
                'critical' => 1,
            ),
        ),
        'circuit'     => array(
            'failure_threshold' => 3,
            'cooldowns'         => array(
                'low'      => 300,
                'medium'   => 600,
                'high'     => 900,
                'critical' => 1800,
            ),
        ),
    );

    public function __construct( $integration_gateway = null, $telemetry_channel = null, $channel_queue = null, $rate_limiter = null, $error_recovery = null, $publisher_guard = null ) {
        if ( $integration_gateway instanceof TTS_Integration_Gateway_Interface ) {
            $this->integration_gateway = $integration_gateway;
        } else {
            $this->integration_gateway = null;
        }

        if ( $telemetry_channel instanceof TTS_Observability_Channel_Interface ) {
            $this->telemetry_channel = $telemetry_channel;
        } else {
            $this->telemetry_channel = null;
        }

        if ( $channel_queue instanceof TTS_Channel_Queue ) {
            $this->channel_queue = $channel_queue;
        } elseif ( class_exists( 'TTS_Channel_Queue' ) ) {
            $this->channel_queue = new TTS_Channel_Queue();
        } else {
            $this->channel_queue = null;
        }

        if ( $rate_limiter instanceof TTS_Rate_Limiter ) {
            $this->rate_limiter = $rate_limiter;
        } else {
            $this->rate_limiter = null;
        }

        if ( $error_recovery instanceof TTS_Error_Recovery ) {
            $this->error_recovery = $error_recovery;
        } else {
            $this->error_recovery = class_exists( 'TTS_Error_Recovery' ) ? new TTS_Error_Recovery() : null;
        }

        if ( $publisher_guard instanceof TTS_Publisher_Guard ) {
            $this->publisher_guard = $publisher_guard;
        } elseif ( class_exists( 'TTS_Publisher_Guard' ) ) {
            $this->publisher_guard = new TTS_Publisher_Guard( $this->rate_limiter, $this->error_recovery );
        } else {
            $this->publisher_guard = null;
        }

        $this->notifier = new TTS_Notifier();

        add_action( 'save_post_tts_social_post', array( $this, 'schedule_post' ), 10, 3 );
        add_action( 'tts_publish_social_post', array( $this, 'publish_social_post' ), 10, 2 );

        if ( class_exists( 'TTS_Channel_Queue' ) ) {
            add_action( TTS_Channel_Queue::ACTION_HOOK, array( $this, 'publish_social_post' ), 10, 1 );
        }
    }

    public function schedule_post( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( isset( $_POST['_tts_approved'] ) ) {
            if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-post_' . $post_id ) ) {
                return;
            }

            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }
        }

        $existing_channels = get_post_meta( $post_id, '_tts_social_channel', true );
        $this->release_schedule( new TTS_Schedule_Cancellation( $post_id, $existing_channels ) );

        $approved   = isset( $_POST['_tts_approved'] ) ? (bool) sanitize_text_field( $_POST['_tts_approved'] ) : (bool) get_post_meta( $post_id, '_tts_approved', true );
        $publish_at = isset( $_POST['_tts_publish_at'] ) ? sanitize_text_field( $_POST['_tts_publish_at'] ) : get_post_meta( $post_id, '_tts_publish_at', true );
        $channels   = isset( $_POST['_tts_social_channel'] ) && is_array( $_POST['_tts_social_channel'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['_tts_social_channel'] ) ) : get_post_meta( $post_id, '_tts_social_channel', true );

        if ( is_array( $channels ) ) {
            $channels = array_map( 'sanitize_text_field', $channels );
        } elseif ( ! empty( $channels ) ) {
            $channels = array( sanitize_text_field( $channels ) );
        } else {
            $channels = array();
        }

        $client_id = intval( get_post_meta( $post_id, '_tts_client_id', true ) );
        $metadata  = array(
            'trigger' => 'save_post',
            'update'  => (bool) $update,
        );

        $request = new TTS_Schedule_Request(
            $post_id,
            $client_id,
            $channels,
            $publish_at,
            $approved,
            $metadata
        );

        $this->queue_from_request( $request );
    }

    public function queue_from_request( TTS_Schedule_Request $request ) {
        $post_id = $request->get_post_id();

        if ( ! $post_id ) {
            return;
        }

        $channels = $request->get_channels();
        $channels = $this->resolve_channels_from_trello( $post_id, $request->get_client_id(), $channels );
        $channels = $this->expand_channel_targets( $post_id, $channels );

        $this->unschedule_post_actions( $post_id, $channels );

        if ( ! $request->is_approved() ) {
            $this->maybe_record_event(
                'info',
                __( 'Skipping schedule because approval flag is missing.', 'fp-publisher' ),
                array(
                    'post_id'  => $post_id,
                    'channels' => $channels,
                )
            );
            return;
        }

        $timestamp = $request->get_publish_timestamp();

        if ( ! $timestamp ) {
            $this->maybe_record_event(
                'warning',
                __( 'Cannot schedule publication without a valid timestamp.', 'fp-publisher' ),
                array(
                    'post_id'  => $post_id,
                    'channels' => $channels,
                )
            );
            return;
        }

        if ( empty( $channels ) ) {
            as_schedule_single_action( $timestamp, 'tts_publish_social_post', array( $post_id ) );
            return;
        }

        $this->initialize_queued_channels( $post_id, $channels );

        foreach ( $channels as $channel ) {
            $channel_config = $this->get_channel_config( $channel );
            $job_payload    = $this->build_job_payload( $request, $channel, $timestamp, $channel_config );

            if ( $this->channel_queue instanceof TTS_Channel_Queue ) {
                $enqueued_job = $this->channel_queue->enqueue_job( $job_payload, $timestamp );

                if ( is_wp_error( $enqueued_job ) ) {
                    $this->maybe_record_event(
                        'warning',
                        __( 'Unable to enqueue channel job.', 'fp-publisher' ),
                        array(
                            'post_id'  => $post_id,
                            'channel'  => $channel,
                            'error'    => $enqueued_job->get_error_message(),
                        )
                    );

                    as_schedule_single_action( $timestamp, 'tts_publish_social_post', array( $post_id, $channel ) );
                    continue;
                }

                $this->register_job_state( $post_id, $channel, $enqueued_job, $channel_config );
            } else {
                as_schedule_single_action( $timestamp, 'tts_publish_social_post', array( $post_id, $channel ) );
            }
        }

        $this->maybe_record_event(
            'info',
            __( 'Queued publication request.', 'fp-publisher' ),
            array(
                'post_id'   => $post_id,
                'client_id' => $request->get_client_id(),
                'channels'  => $channels,
            )
        );
    }

    public function release_schedule( TTS_Schedule_Cancellation $cancellation ) {
        $post_id = $cancellation->get_post_id();

        if ( ! $post_id ) {
            return;
        }

        $channels = $cancellation->get_channels();
        $this->unschedule_post_actions( $post_id, $channels );

        $this->maybe_record_event(
            'info',
            __( 'Released scheduled publication.', 'fp-publisher' ),
            array(
                'post_id'  => $post_id,
                'channels' => $channels,
            )
        );
    }

    private function unschedule_post_actions( $post_id, $channels = array() ) {
        $post_id = absint( $post_id );

        if ( ! $post_id ) {
            return;
        }

        as_unschedule_all_actions( 'tts_publish_social_post', array( $post_id ) );
        as_unschedule_all_actions( 'tts_publish_social_post', array( 'post_id' => $post_id ) );

        $state = $this->get_channel_state( $post_id );

        if ( empty( $channels ) ) {
            $channels = array_keys( $state );
        }

        if ( ! is_array( $channels ) ) {
            $channels = array( $channels );
        }

        foreach ( $channels as $channel ) {
            if ( ! is_string( $channel ) || '' === $channel ) {
                continue;
            }

            as_unschedule_all_actions( 'tts_publish_social_post', array( $post_id, $channel ) );
            as_unschedule_all_actions( 'tts_publish_social_post', array( 'post_id' => $post_id, 'channel' => $channel ) );

            if ( $this->channel_queue instanceof TTS_Channel_Queue && isset( $state[ $channel ]['scheduled_args'] ) ) {
                $args  = $state[ $channel ]['scheduled_args'];
                $group = $state[ $channel ]['action_group'] ?? '';
                as_unschedule_all_actions( TTS_Channel_Queue::ACTION_HOOK, $args, $group );
            }

            unset( $state[ $channel ] );
        }

        $this->set_channel_state( $post_id, $state );
    }

    public function publish_social_post( $payload, $channel = '' ) {
        if ( is_array( $payload ) && isset( $payload['post_id'] ) ) {
            $this->process_channel_job( $payload );
            return;
        }

        $post_id = intval( $payload );
        if ( ! $post_id ) {
            return;
        }

        $resolved_channel = is_string( $channel ) ? sanitize_key( $channel ) : '';

        $channels = array();
        if ( '' !== $resolved_channel ) {
            $channels = array( $resolved_channel );
        } else {
            $configured = get_post_meta( $post_id, '_tts_social_channel', true );
            if ( is_array( $configured ) ) {
                $channels = $configured;
            } elseif ( ! empty( $configured ) ) {
                $channels = array( $configured );
            }
        }

        $channels = $this->expand_channel_targets( $post_id, $channels );

        if ( empty( $channels ) ) {
            return;
        }

        foreach ( $channels as $target_channel ) {
            $job = $this->build_ad_hoc_job( $post_id, $target_channel );
            $this->process_channel_job( $job );
        }
    }

    private function process_channel_job( array $job ) {
        $post_id = isset( $job['post_id'] ) ? absint( $job['post_id'] ) : 0;
        $channel = isset( $job['channel'] ) ? sanitize_key( $job['channel'] ) : '';

        if ( ! $post_id || '' === $channel ) {
            return;
        }

        $client_id = isset( $job['client_id'] ) ? absint( $job['client_id'] ) : intval( get_post_meta( $post_id, '_tts_client_id', true ) );
        if ( ! $client_id ) {
            tts_log_event( $post_id, $channel, 'error', __( 'Missing client ID', 'fp-publisher' ), '' );
            return;
        }

        $state          = $this->get_channel_state( $post_id );
        $channel_state  = isset( $state[ $channel ] ) ? $state[ $channel ] : array();
        $channel_config = isset( $channel_state['config'] ) ? $channel_state['config'] : $this->get_channel_config( $channel );

        $attempt_index    = isset( $job['attempt'] ) ? absint( $job['attempt'] ) : ( isset( $channel_state['attempts'] ) ? absint( $channel_state['attempts'] ) : 0 );
        $current_attempt  = max( 1, $attempt_index + 1 );
        $max_attempts     = isset( $channel_state['max_attempts'] ) ? absint( $channel_state['max_attempts'] ) : ( isset( $job['max_attempts'] ) ? absint( $job['max_attempts'] ) : ( $channel_config['retry']['global_max'] ?? 5 ) );
        $channel_state['status']        = 'in_progress';
        $channel_state['last_started']  = microtime( true );
        $channel_state['attempts']      = $current_attempt;
        $channel_state['config']        = $channel_config;
        $channel_state['job_id']        = $job['job_id'] ?? ( $channel_state['job_id'] ?? uniqid( 'tts_job_', true ) );
        $channel_state['scheduled_args'] = $job['scheduled_args'] ?? ( $channel_state['scheduled_args'] ?? array( $job ) );
        $channel_state['action_group']  = $job['action_group'] ?? ( $channel_state['action_group'] ?? '' );
        $channel_state['next_attempt']  = null;
        $channel_state['last_error']    = $channel_state['last_error'] ?? null;
        $channel_state['last_updated']  = time();

        $metadata = isset( $channel_state['metadata'] ) && is_array( $channel_state['metadata'] ) ? $channel_state['metadata'] : array();
        if ( isset( $job['metadata'] ) && is_array( $job['metadata'] ) ) {
            $metadata = array_merge( $metadata, $job['metadata'] );
        }
        $channel_state['metadata'] = $metadata;

        if ( isset( $metadata['queued_at'] ) ) {
            $channel_state['queued_at'] = (float) $metadata['queued_at'];
        } elseif ( ! isset( $channel_state['queued_at'] ) ) {
            $channel_state['queued_at'] = microtime( true );
        }

        $state[ $channel ] = $channel_state;
        $this->set_channel_state( $post_id, $state );

        $profiling_context = array(
            'job_id'          => $channel_state['job_id'],
            'post_id'         => $post_id,
            'client_id'       => $client_id,
            'channel'         => $channel,
            'attempt'         => $current_attempt,
            'max_attempts'    => $max_attempts,
            'queued_by'       => isset( $metadata['queued_by'] ) ? sanitize_key( $metadata['queued_by'] ) : '',
            'queued_at'       => $channel_state['queued_at'] ?? null,
            'start_timestamp' => $channel_state['last_started'],
        );

        if ( isset( $metadata['scheduled_for'] ) ) {
            $profiling_context['scheduled_for'] = $metadata['scheduled_for'];
        }

        do_action( 'tts_scheduler_job_started', $profiling_context );

        tts_log_event( $post_id, $channel, 'start', __( 'Publishing social post', 'fp-publisher' ), '' );
        $this->dispatch_integration_event( $post_id, $client_id, array( $channel ) );

        $tokens       = $this->get_client_tokens( $client_id );
        $options      = get_option( 'tts_settings', array() );
        $attachments  = $this->get_post_attachments( $post_id );
        $binding      = $this->resolve_publisher_binding( $channel );

        if ( is_wp_error( $binding ) ) {
            $this->handle_job_failure( $post_id, $channel, $client_id, $current_attempt, $max_attempts, $channel_config, $binding, $state );
            return;
        }

        if ( ! file_exists( $binding['file'] ) ) {
            $error = new WP_Error( 'missing_publisher', sprintf( __( 'Publisher file missing for %s.', 'fp-publisher' ), $channel ) );
            $this->handle_job_failure( $post_id, $channel, $client_id, $current_attempt, $max_attempts, $channel_config, $error, $state );
            return;
        }

        require_once $binding['file'];

        if ( ! class_exists( $binding['class'] ) ) {
            $error = new WP_Error( 'missing_publisher_class', sprintf( __( 'Publisher class missing for %s.', 'fp-publisher' ), $channel ) );
            $this->handle_job_failure( $post_id, $channel, $client_id, $current_attempt, $max_attempts, $channel_config, $error, $state );
            return;
        }

        $publisher   = new $binding['class']();
        $credentials = isset( $tokens[ $binding['credential_key'] ] ) ? $tokens[ $binding['credential_key'] ] : '';
        $message     = $this->resolve_message( $post_id, $channel, $options );
        $guard_ctx   = array(
            'post_id'        => $post_id,
            'client_id'      => $client_id,
            'retry_count'    => $current_attempt - 1,
            'channel_config' => $channel_config,
            'skip_retry_queue' => true,
        );

        $operation = function () use ( $publisher, $binding, $post_id, $credentials, $message, $attachments, $channel ) {
            if ( 'story' === $binding['mode'] ) {
                $media_url = $this->get_story_media_url( $post_id );
                if ( ! $media_url ) {
                    return new WP_Error( 'missing_story_media', __( 'Missing Story media', 'fp-publisher' ) );
                }

                return $publisher->publish_story( $post_id, $credentials, $media_url );
            }

            if ( $attachments ) {
                $processor   = new TTS_Image_Processor();
                $resized_map = array();
                foreach ( $attachments as $attachment_id ) {
                    $url = $processor->resize_for_channel( $attachment_id, $channel );
                    if ( $url ) {
                        $resized_map[ $attachment_id ] = $url;
                    }
                }

                if ( $resized_map ) {
                    update_post_meta( $post_id, '_tts_resized_' . $channel, $resized_map );
                }
            }

            $publish_result = $publisher->publish( $post_id, $credentials, $message );
            if ( is_wp_error( $publish_result ) ) {
                return $publish_result;
            }

            $payload = array(
                'publish' => $publish_result,
            );

            if ( 'instagram' === $channel && is_array( $publish_result ) && isset( $publish_result['id'] ) ) {
                $first_comment = get_post_meta( $post_id, '_tts_instagram_first_comment', true );
                if ( $first_comment ) {
                    $comment_res = $publisher->post_comment( $publish_result['id'], $first_comment );
                    if ( is_wp_error( $comment_res ) ) {
                        return $comment_res;
                    }
                    $payload['comment'] = $comment_res;
                }
            }

            return $payload;
        };

        $guard_response = $this->execute_guarded_operation( $channel, $operation, $guard_ctx );

        if ( ! empty( $guard_response['success'] ) ) {
            $this->handle_job_success( $post_id, $channel, $client_id, $current_attempt, $state, $guard_response['result'], $channel_config );
            return;
        }

        $this->handle_job_failure(
            $post_id,
            $channel,
            $client_id,
            $current_attempt,
            $max_attempts,
            $channel_config,
            $guard_response['error'] ?? new WP_Error( 'publisher_error', __( 'Unknown publishing error', 'fp-publisher' ) ),
            $state,
            $guard_response
        );
    }

    private function execute_guarded_operation( $channel, callable $operation, array $context ) {
        if ( $this->publisher_guard instanceof TTS_Publisher_Guard ) {
            return $this->publisher_guard->execute( $channel, $operation, $context );
        }

        try {
            $result = call_user_func( $operation );
            if ( is_wp_error( $result ) ) {
                return array(
                    'success'  => false,
                    'error'    => $result,
                    'severity' => $this->error_recovery instanceof TTS_Error_Recovery ? $this->error_recovery->assess_error_severity( $result ) : TTS_Error_Recovery::SEVERITY_MEDIUM,
                );
            }

            return array(
                'success' => true,
                'result'  => $result,
            );
        } catch ( \Throwable $exception ) {
            return array(
                'success'  => false,
                'error'    => $exception,
                'severity' => TTS_Error_Recovery::SEVERITY_HIGH,
            );
        }
    }

    private function handle_job_success( $post_id, $channel, $client_id, $current_attempt, array $state, $result, array $channel_config ) {
        $channel_state = isset( $state[ $channel ] ) ? $state[ $channel ] : array();
        $metadata      = isset( $channel_state['metadata'] ) && is_array( $channel_state['metadata'] ) ? $channel_state['metadata'] : array();
        $finished_at   = microtime( true );
        $duration_ms   = isset( $channel_state['last_started'] ) ? max( 0, ( $finished_at - (float) $channel_state['last_started'] ) * 1000 ) : null;
        $queue_latency_ms = null;

        if ( isset( $channel_state['queued_at'] ) && isset( $channel_state['last_started'] ) ) {
            $queue_latency_ms = max( 0, ( (float) $channel_state['last_started'] - (float) $channel_state['queued_at'] ) * 1000 );
        }

        $profiling_context = array(
            'job_id'          => $channel_state['job_id'] ?? '',
            'post_id'         => $post_id,
            'client_id'       => $client_id,
            'channel'         => $channel,
            'attempt'         => $current_attempt,
            'max_attempts'    => isset( $channel_state['max_attempts'] ) ? absint( $channel_state['max_attempts'] ) : $current_attempt,
            'queued_by'       => isset( $metadata['queued_by'] ) ? sanitize_key( $metadata['queued_by'] ) : '',
            'queued_at'       => $channel_state['queued_at'] ?? null,
            'start_timestamp' => $channel_state['last_started'] ?? $finished_at,
            'end_timestamp'   => $finished_at,
        );

        if ( null !== $duration_ms ) {
            $profiling_context['duration_ms'] = $duration_ms;
        }

        if ( null !== $queue_latency_ms ) {
            $profiling_context['queue_latency_ms'] = $queue_latency_ms;
        }

        if ( isset( $metadata['scheduled_for'] ) ) {
            $profiling_context['scheduled_for'] = $metadata['scheduled_for'];
        }

        do_action( 'tts_scheduler_job_completed', $profiling_context, $result );

        $channel_state['status']         = 'completed';
        $channel_state['attempts']       = $current_attempt;
        $channel_state['last_error']     = null;
        $channel_state['completed_at']   = time();
        $channel_state['next_attempt']   = null;
        $channel_state['scheduled_args'] = null;
        $channel_state['action_group']   = null;
        $channel_state['last_updated']   = time();
        $state[ $channel ]               = $channel_state;
        $this->set_channel_state( $post_id, $state );

        $log = get_post_meta( $post_id, '_tts_publish_log', true );
        if ( ! is_array( $log ) ) {
            $log = array();
        }

        if ( is_array( $result ) && isset( $result['publish'] ) ) {
            $log[ $channel ] = $result['publish'];
            if ( isset( $result['comment'] ) ) {
                $log['instagram_comment'] = $result['comment'];
            }
        } else {
            $log[ $channel ] = $result;
        }

        update_post_meta( $post_id, '_tts_publish_log', $log );
        tts_log_event( $post_id, $channel, 'success', __( 'Channel publish completed', 'fp-publisher' ), $log[ $channel ] );

        $this->maybe_record_event(
            'info',
            __( 'Channel job completed.', 'fp-publisher' ),
            array(
                'post_id'  => $post_id,
                'channel'  => $channel,
                'attempts' => $current_attempt,
            )
        );

        $this->maybe_complete_post( $post_id, $client_id );
    }

    private function handle_job_failure( $post_id, $channel, $client_id, $current_attempt, $max_attempts, array $channel_config, $error, array $state, $guard_response = null ) {
        $channel_state = isset( $state[ $channel ] ) ? $state[ $channel ] : array();
        $metadata      = isset( $channel_state['metadata'] ) && is_array( $channel_state['metadata'] ) ? $channel_state['metadata'] : array();
        $finished_at   = microtime( true );
        $duration_ms   = isset( $channel_state['last_started'] ) ? max( 0, ( $finished_at - (float) $channel_state['last_started'] ) * 1000 ) : null;
        $queue_latency_ms = null;

        if ( isset( $channel_state['queued_at'] ) && isset( $channel_state['last_started'] ) ) {
            $queue_latency_ms = max( 0, ( (float) $channel_state['last_started'] - (float) $channel_state['queued_at'] ) * 1000 );
        }

        $channel_state['status']       = 'retry_pending';
        $channel_state['attempts']     = $current_attempt;
        $channel_state['last_updated'] = time();

        $severity = $guard_response['severity'] ?? ( $this->error_recovery instanceof TTS_Error_Recovery ? $this->error_recovery->assess_error_severity( $error ) : TTS_Error_Recovery::SEVERITY_MEDIUM );
        $severity_label = $guard_response['severity_label'] ?? ( $this->error_recovery instanceof TTS_Error_Recovery ? $this->error_recovery->get_severity_label( $severity ) : 'medium' );

        $message = '';
        if ( is_wp_error( $error ) ) {
            $message = $error->get_error_message();
        } elseif ( $error instanceof \Throwable ) {
            $message = $error->getMessage();
        } elseif ( is_array( $error ) && isset( $error['message'] ) ) {
            $message = $error['message'];
        } else {
            $message = (string) $error;
        }

        $channel_state['last_error'] = array(
            'message'  => $message,
            'severity' => $severity_label,
            'time'     => time(),
        );

        $profiling_context = array(
            'job_id'          => $channel_state['job_id'] ?? '',
            'post_id'         => $post_id,
            'client_id'       => $client_id,
            'channel'         => $channel,
            'attempt'         => $current_attempt,
            'max_attempts'    => $max_attempts,
            'queued_by'       => isset( $metadata['queued_by'] ) ? sanitize_key( $metadata['queued_by'] ) : '',
            'queued_at'       => $channel_state['queued_at'] ?? null,
            'start_timestamp' => $channel_state['last_started'] ?? $finished_at,
            'end_timestamp'   => $finished_at,
            'severity'        => $severity_label,
        );

        if ( null !== $duration_ms ) {
            $profiling_context['duration_ms'] = $duration_ms;
        }

        if ( null !== $queue_latency_ms ) {
            $profiling_context['queue_latency_ms'] = $queue_latency_ms;
        }

        if ( isset( $metadata['scheduled_for'] ) ) {
            $profiling_context['scheduled_for'] = $metadata['scheduled_for'];
        }

        do_action( 'tts_scheduler_job_failed', $profiling_context, $error );

        $state[ $channel ] = $channel_state;
        $this->set_channel_state( $post_id, $state );

        $log = get_post_meta( $post_id, '_tts_publish_log', true );
        if ( ! is_array( $log ) ) {
            $log = array();
        }
        $log[ $channel ] = $message;
        update_post_meta( $post_id, '_tts_publish_log', $log );

        tts_log_event( $post_id, $channel, 'error', $message, '' );

        $allowed_attempts = $this->determine_allowed_attempts( $max_attempts, $severity_label, $channel_config );
        $circuit_state    = $guard_response['circuit'] ?? array();
        $circuit_open     = isset( $circuit_state['state'] ) && 'open' === $circuit_state['state'] && ( $circuit_state['open_until'] ?? 0 ) > time();

        if ( $current_attempt >= $allowed_attempts || $circuit_open ) {
            $channel_state['status'] = $circuit_open ? 'awaiting_manual' : 'failed';
            $state[ $channel ]       = $channel_state;
            $this->set_channel_state( $post_id, $state );

            $this->alert_failure( $post_id, $channel, $client_id, $message, $severity_label );
            update_post_meta( $post_id, '_published_status', 'failed' );

            $this->maybe_record_event(
                'error',
                __( 'Channel publishing failed permanently.', 'fp-publisher' ),
                array(
                    'post_id'  => $post_id,
                    'channel'  => $channel,
                    'attempts' => $current_attempt,
                    'severity' => $severity_label,
                )
            );
            return;
        }

        if ( ! ( $this->channel_queue instanceof TTS_Channel_Queue ) ) {
            $delay_minutes = $this->calculate_backoff_delay( $current_attempt );
            $timestamp     = time() + $delay_minutes * MINUTE_IN_SECONDS;
            as_schedule_single_action( $timestamp, 'tts_publish_social_post', array( $post_id, $channel ) );

            $this->maybe_record_event(
                'retry',
                __( 'Legacy retry scheduled due to missing queue.', 'fp-publisher' ),
                array(
                    'post_id'  => $post_id,
                    'channel'  => $channel,
                    'attempts' => $current_attempt,
                )
            );
            return;
        }

        $delay_seconds = $this->calculate_retry_delay( $severity_label, $current_attempt, $channel_config );
        $next_timestamp = time() + $delay_seconds;

        $retry_job = array(
            'post_id'           => $post_id,
            'client_id'         => $client_id,
            'channel'           => $channel,
            'attempt'           => $current_attempt,
            'max_attempts'      => $max_attempts,
            'metadata'          => array(
                'queued_at'      => microtime( true ),
                'scheduled_for'  => $next_timestamp,
                'queued_by'      => 'scheduler_retry',
                'last_error'     => $message,
            ),
            'retry_blueprint'   => $channel_config['retry'],
            'circuit_blueprint' => $channel_config['circuit'],
        );

        $enqueued_job = $this->channel_queue->enqueue_job( $retry_job, $next_timestamp );

        if ( is_wp_error( $enqueued_job ) ) {
            $channel_state['status'] = 'failed';
            $state[ $channel ]       = $channel_state;
            $this->set_channel_state( $post_id, $state );

            $fallback_message = sprintf( '%s (enqueue error: %s)', $message, $enqueued_job->get_error_message() );
            $this->alert_failure( $post_id, $channel, $client_id, $fallback_message, $severity_label );
            update_post_meta( $post_id, '_published_status', 'failed' );
            return;
        }

        $channel_state['status']        = 'queued_retry';
        $channel_state['scheduled_args'] = $enqueued_job['scheduled_args'] ?? array( $retry_job );
        $channel_state['action_group']  = $enqueued_job['action_group'] ?? '';
        $channel_state['job_id']        = $enqueued_job['job_id'] ?? $channel_state['job_id'];
        $channel_state['next_attempt']  = $next_timestamp;
        $channel_state['last_updated']  = time();
        if ( isset( $enqueued_job['metadata'] ) && is_array( $enqueued_job['metadata'] ) ) {
            $channel_state['metadata'] = isset( $channel_state['metadata'] ) && is_array( $channel_state['metadata'] )
                ? array_merge( $channel_state['metadata'], $enqueued_job['metadata'] )
                : $enqueued_job['metadata'];

            if ( isset( $enqueued_job['metadata']['queued_at'] ) ) {
                $channel_state['queued_at'] = (float) $enqueued_job['metadata']['queued_at'];
            }
        }
        $state[ $channel ]              = $channel_state;
        $this->set_channel_state( $post_id, $state );

        $delay_minutes = ceil( $delay_seconds / MINUTE_IN_SECONDS );
        $this->maybe_record_event(
            'retry',
            __( 'Retry scheduled for channel job.', 'fp-publisher' ),
            array(
                'post_id'  => $post_id,
                'channel'  => $channel,
                'attempts' => $current_attempt,
                'delay'    => $delay_minutes,
                'severity' => $severity_label,
            )
        );

        tts_log_event( $post_id, $channel, 'retry', sprintf( __( 'Retry #%1$d scheduled in %2$d minutes', 'fp-publisher' ), $current_attempt, $delay_minutes ), '' );
    }

    private function finalize_publication( $post_id, $client_id, array $log ) {
        $card_id       = get_post_meta( $post_id, '_trello_card_id', true );
        $trello_key    = get_post_meta( $client_id, '_tts_trello_key', true );
        $trello_token  = get_post_meta( $client_id, '_tts_trello_token', true );
        $published_list = get_post_meta( $client_id, '_tts_trello_published_list', true );

        if ( $card_id && $trello_key && $trello_token && $published_list ) {
            $first_url = '';
            $links     = array();
            foreach ( $log as $channel => $entry ) {
                if ( 'instagram_comment' === $channel ) {
                    continue;
                }

                $link = '';
                if ( is_string( $entry ) && preg_match( '/https?:\/\/[^\s]+/', $entry, $match ) ) {
                    $link = $match[0];
                } elseif ( is_array( $entry ) ) {
                    if ( isset( $entry['url'] ) ) {
                        $link = $entry['url'];
                    } else {
                        foreach ( $entry as $val ) {
                            if ( is_string( $val ) && preg_match( '/https?:\/\/[^\s]+/', $val, $match ) ) {
                                $link = $match[0];
                                break;
                            }
                        }
                    }
                }

                if ( $link ) {
                    if ( empty( $first_url ) ) {
                        $first_url = $link;
                    }
                    $links[] = ucfirst( $channel ) . ': ' . $link;
                }
            }

            $base = 'https://api.trello.com/1/cards/' . rawurlencode( $card_id );
            $move_response = wp_remote_request(
                $base . '?key=' . rawurlencode( $trello_key ) . '&token=' . rawurlencode( $trello_token ),
                array(
                    'method'  => 'PUT',
                    'body'    => array( 'idList' => $published_list ),
                    'timeout' => 20,
                )
            );

            if ( ! is_wp_error( $move_response ) ) {
                $comment_url = sprintf(
                    'https://api.trello.com/1/cards/%s/actions/comments?key=%s&token=%s',
                    rawurlencode( $card_id ),
                    rawurlencode( $trello_key ),
                    rawurlencode( $trello_token )
                );

                if ( $first_url ) {
                    $comment_response = wp_remote_post(
                        $comment_url,
                        array(
                            'body'    => array( 'text' => $first_url ),
                            'timeout' => 20,
                        )
                    );
                    if ( is_wp_error( $comment_response ) ) {
                        tts_log_event( $post_id, 'trello', 'error', $comment_response->get_error_message(), '' );
                    }
                }

                if ( $links ) {
                    $comment_response2 = wp_remote_post(
                        $comment_url,
                        array(
                            'body'    => array( 'text' => implode( "
", $links ) ),
                            'timeout' => 20,
                        )
                    );
                    if ( is_wp_error( $comment_response2 ) ) {
                        tts_log_event( $post_id, 'trello', 'error', $comment_response2->get_error_message(), '' );
                    }
                }
            } else {
                tts_log_event( $post_id, 'trello', 'error', $move_response->get_error_message(), '' );
            }
        }

        tts_log_event( $post_id, 'scheduler', 'complete', __( 'Publish process completed', 'fp-publisher' ), $log );

        $log_url = admin_url( 'admin.php?page=fp-publisher-log&post_id=' . $post_id );
        $message = sprintf( __( 'Publishing completed for post %1$s. Log: %2$s', 'fp-publisher' ), get_the_title( $post_id ), $log_url );
        $this->notifier->notify_slack( $message );
        $this->notifier->notify_email( __( 'Social publishing completed', 'fp-publisher' ), $message );
    }

    private function maybe_complete_post( $post_id, $client_id ) {
        $queued = get_post_meta( $post_id, $this->queued_channels_meta_key, true );
        if ( ! is_array( $queued ) || empty( $queued ) ) {
            return;
        }

        $state = $this->get_channel_state( $post_id );
        foreach ( $queued as $channel ) {
            if ( ! isset( $state[ $channel ] ) || 'completed' !== $state[ $channel ]['status'] ) {
                return;
            }
        }

        if ( 'published' === get_post_meta( $post_id, '_published_status', true ) ) {
            return;
        }

        update_post_meta( $post_id, '_published_status', 'published' );
        update_post_meta( $post_id, $this->finalized_meta_key, time() );

        $log = get_post_meta( $post_id, '_tts_publish_log', true );
        if ( ! is_array( $log ) ) {
            $log = array();
        }

        $this->finalize_publication( $post_id, $client_id, $log );
    }

    private function dispatch_integration_event( $post_id, $client_id, array $channels ) {
        if ( ! $this->integration_gateway instanceof TTS_Integration_Gateway_Interface ) {
            return;
        }

        $message = new TTS_Integration_Message(
            0,
            'publication_sync',
            array(
                'post_id'   => $post_id,
                'client_id' => $client_id,
                'channels'  => $channels,
            ),
            array(
                'post_id'  => $post_id,
                'channels' => $channels,
            )
        );

        $this->integration_gateway->dispatch_message( $message );
    }

    private function get_post_attachments( $post_id ) {
        $attachment_ids = get_post_meta( $post_id, '_tts_attachment_ids', true );
        $attachment_ids = is_array( $attachment_ids ) ? array_map( 'intval', $attachment_ids ) : array();
        $manual_id      = (int) get_post_meta( $post_id, '_tts_manual_media', true );
        if ( $manual_id ) {
            $attachment_ids[] = $manual_id;
        }

        return array_values( array_unique( array_filter( $attachment_ids ) ) );
    }

    private function get_story_media_url( $post_id ) {
        $story_id = (int) get_post_meta( $post_id, '_tts_story_media', true );
        return $story_id ? wp_get_attachment_url( $story_id ) : '';
    }

    private function get_client_tokens( $client_id ) {
        return array(
            'facebook'  => get_post_meta( $client_id, '_tts_fb_token', true ),
            'instagram' => get_post_meta( $client_id, '_tts_ig_token', true ),
            'youtube'   => get_post_meta( $client_id, '_tts_yt_token', true ),
            'tiktok'    => get_post_meta( $client_id, '_tts_tt_token', true ),
            'blog'      => get_post_meta( $client_id, '_tts_blog_settings', true ),
        );
    }

    private function resolve_message( $post_id, $channel, $options ) {
        $custom_message = get_post_meta( $post_id, '_tts_message_' . $channel, true );
        if ( $custom_message ) {
            return $custom_message;
        }

        $template = isset( $options[ $channel . '_template' ] ) ? $options[ $channel . '_template' ] : '';
        return $template ? tts_apply_template( $template, $post_id, $channel ) : '';
    }

    private function resolve_publisher_binding( $channel ) {
        $map = array(
            'facebook' => array(
                'class'          => 'TTS_Publisher_Facebook',
                'file'           => plugin_dir_path( __FILE__ ) . 'publishers/class-tts-publisher-facebook.php',
                'credential_key' => 'facebook',
                'mode'           => 'standard',
            ),
            'facebook_story' => array(
                'class'          => 'TTS_Publisher_Facebook_Story',
                'file'           => plugin_dir_path( __FILE__ ) . 'publishers/class-tts-publisher-facebook-story.php',
                'credential_key' => 'facebook',
                'mode'           => 'story',
            ),
            'instagram' => array(
                'class'          => 'TTS_Publisher_Instagram',
                'file'           => plugin_dir_path( __FILE__ ) . 'publishers/class-tts-publisher-instagram.php',
                'credential_key' => 'instagram',
                'mode'           => 'standard',
            ),
            'instagram_story' => array(
                'class'          => 'TTS_Publisher_Instagram_Story',
                'file'           => plugin_dir_path( __FILE__ ) . 'publishers/class-tts-publisher-instagram-story.php',
                'credential_key' => 'instagram',
                'mode'           => 'story',
            ),
            'youtube' => array(
                'class'          => 'TTS_Publisher_Youtube',
                'file'           => plugin_dir_path( __FILE__ ) . 'publishers/class-tts-publisher-youtube.php',
                'credential_key' => 'youtube',
                'mode'           => 'standard',
            ),
            'tiktok' => array(
                'class'          => 'TTS_Publisher_Tiktok',
                'file'           => plugin_dir_path( __FILE__ ) . 'publishers/class-tts-publisher-tiktok.php',
                'credential_key' => 'tiktok',
                'mode'           => 'standard',
            ),
            'blog' => array(
                'class'          => 'TTS_Publisher_Blog',
                'file'           => plugin_dir_path( __FILE__ ) . 'publishers/class-tts-publisher-blog.php',
                'credential_key' => 'blog',
                'mode'           => 'standard',
            ),
        );

        if ( isset( $map[ $channel ] ) ) {
            return $map[ $channel ];
        }

        return new WP_Error( 'unknown_channel', sprintf( __( 'Unknown channel %s.', 'fp-publisher' ), $channel ) );
    }

    private function expand_channel_targets( $post_id, array $channels ) {
        $channels = array_filter( array_map( 'sanitize_key', $channels ) );
        $channels = array_values( array_unique( $channels ) );

        if ( (bool) get_post_meta( $post_id, '_tts_publish_story', true ) ) {
            $channels[] = 'facebook_story';
            $channels[] = 'instagram_story';
        }

        return array_values( array_unique( $channels ) );
    }

    private function resolve_channels_from_trello( $post_id, $client_id, $channels ) {
        if ( ! empty( $channels ) ) {
            return $channels;
        }

        $id_list = get_post_meta( $post_id, '_trello_idList', true );
        $board_id = get_post_meta( $post_id, '_trello_board_id', true );
        if ( empty( $id_list ) || empty( $board_id ) ) {
            $card_id     = get_post_meta( $post_id, '_trello_card_id', true );
            $trello_key   = get_post_meta( $client_id, '_tts_trello_key', true );
            $trello_token = get_post_meta( $client_id, '_tts_trello_token', true );
            if ( $card_id && $trello_key && $trello_token ) {
                $url      = 'https://api.trello.com/1/cards/' . rawurlencode( $card_id ) . '?fields=idList,idBoard&key=' . rawurlencode( $trello_key ) . '&token=' . rawurlencode( $trello_token );
                $response = wp_remote_get( $url, array( 'timeout' => 20 ) );
                if ( ! is_wp_error( $response ) ) {
                    $body = json_decode( wp_remote_retrieve_body( $response ), true );
                    if ( isset( $body['idList'] ) ) {
                        $id_list = $body['idList'];
                    }
                    if ( isset( $body['idBoard'] ) ) {
                        $board_id = $body['idBoard'];
                    }
                }
            }
        }
        if ( $board_id ) {
            update_post_meta( $post_id, '_trello_board_id', $board_id );
        }
        if ( $id_list ) {
            $mapping = get_post_meta( $client_id, '_tts_trello_map', true );
            if ( is_array( $mapping ) ) {
                foreach ( $mapping as $row ) {
                    if ( isset( $row['idList'], $row['canale_social'] ) && $row['idList'] === $id_list ) {
                        $channels = array( $row['canale_social'] );
                        break;
                    }
                }
            }
        }

        return is_array( $channels ) ? $channels : array();
    }

    private function build_job_payload( TTS_Schedule_Request $request, $channel, $timestamp, array $config ) {
        return array(
            'post_id'           => $request->get_post_id(),
            'client_id'         => $request->get_client_id(),
            'channel'           => $channel,
            'attempt'           => 0,
            'max_attempts'      => $config['retry']['global_max'] ?? 5,
            'scheduled_for'     => $timestamp,
            'metadata'          => array(
                'request'       => $request->to_array(),
                'queued_at'     => microtime( true ),
                'scheduled_for' => $timestamp,
                'queued_by'     => 'scheduler',
            ),
            'retry_blueprint'   => $config['retry'],
            'circuit_blueprint' => $config['circuit'],
        );
    }


    private function build_ad_hoc_job( $post_id, $channel ) {
        $client_id = intval( get_post_meta( $post_id, '_tts_client_id', true ) );
        $config    = $this->get_channel_config( $channel );
        $timestamp = time();

        return array(
            'post_id'           => $post_id,
            'client_id'         => $client_id,
            'channel'           => $channel,
            'attempt'           => 0,
            'max_attempts'      => $config['retry']['global_max'] ?? 5,
            'scheduled_for'     => $timestamp,
            'metadata'          => array(
                'queued_at'     => microtime( true ),
                'scheduled_for' => $timestamp,
                'queued_by'     => 'adhoc',
            ),
            'retry_blueprint'   => $config['retry'],
            'circuit_blueprint' => $config['circuit'],
        );
    }

    private function register_job_state( $post_id, $channel, array $job, array $config ) {
        $state = $this->get_channel_state( $post_id );
        $state[ $channel ] = array(
            'status'         => 'queued',
            'attempts'       => 0,
            'max_attempts'   => $job['max_attempts'] ?? ( $config['retry']['global_max'] ?? 5 ),
            'job_id'         => $job['job_id'] ?? uniqid( 'tts_job_', true ),
            'scheduled_args' => $job['scheduled_args'] ?? array( $job ),
            'action_group'   => $job['action_group'] ?? '',
            'next_attempt'   => $job['scheduled_for'] ?? time(),
            'config'         => $config,
            'last_error'     => null,
            'last_updated'   => time(),
            'queued_at'      => isset( $job['metadata']['queued_at'] ) ? (float) $job['metadata']['queued_at'] : microtime( true ),
            'metadata'       => isset( $job['metadata'] ) && is_array( $job['metadata'] ) ? $job['metadata'] : array(),
        );

        $this->set_channel_state( $post_id, $state );
    }

    private function initialize_queued_channels( $post_id, array $channels ) {
        update_post_meta( $post_id, $this->queued_channels_meta_key, array_values( array_unique( $channels ) ) );
    }

    private function get_channel_state( $post_id ) {
        $state = get_post_meta( $post_id, $this->retry_state_meta_key, true );
        return is_array( $state ) ? $state : array();
    }

    private function set_channel_state( $post_id, array $state ) {
        if ( empty( $state ) ) {
            delete_post_meta( $post_id, $this->retry_state_meta_key );
            return;
        }

        update_post_meta( $post_id, $this->retry_state_meta_key, $state );
    }

    private function get_channel_config( $channel ) {
        if ( $this->channel_queue instanceof TTS_Channel_Queue ) {
            return $this->channel_queue->get_channel_config( $channel );
        }

        return $this->default_channel_config;
    }

    private function determine_allowed_attempts( $max_attempts, $severity_label, array $config ) {
        $global_max = max( 1, absint( $max_attempts ) );
        $severity_limits = isset( $config['retry']['max_attempts'] ) ? $config['retry']['max_attempts'] : array();
        $severity_limit  = isset( $severity_limits[ $severity_label ] ) ? absint( $severity_limits[ $severity_label ] ) : $global_max;

        if ( $severity_limit <= 0 ) {
            $severity_limit = $global_max;
        }

        return min( $global_max, max( 1, $severity_limit ) );
    }

    private function calculate_retry_delay( $severity_label, $attempt, array $config ) {
        $retry = isset( $config['retry'] ) ? $config['retry'] : array();
        $delays = isset( $retry['delays'] ) ? $retry['delays'] : array();
        $base_minutes = isset( $delays[ $severity_label ] ) ? absint( $delays[ $severity_label ] ) : 15;
        $strategy = isset( $retry['strategy'] ) ? $retry['strategy'] : 'progressive';

        switch ( $strategy ) {
            case 'exponential':
                $delay_minutes = $base_minutes * pow( 2, max( 0, $attempt - 1 ) );
                break;
            case 'fixed':
                $delay_minutes = $base_minutes;
                break;
            default:
                $delay_minutes = $base_minutes * max( 1, $attempt );
                break;
        }

        $delay_seconds = $delay_minutes * MINUTE_IN_SECONDS;
        $delay_seconds = min( $delay_seconds, 6 * HOUR_IN_SECONDS );

        $jitter = isset( $retry['jitter'] ) ? absint( $retry['jitter'] ) : 60;
        if ( $jitter > 0 ) {
            $delay_seconds += wp_rand( 0, $jitter );
        }

        return max( 60, $delay_seconds );
    }

    private function alert_failure( $post_id, $channel, $client_id, $message, $severity_label ) {
        $log_url = admin_url( 'admin.php?page=fp-publisher-log&post_id=' . $post_id );
        $title   = get_the_title( $post_id );
        $body    = sprintf(
            __( 'Publishing failed for post %1$s on channel %2$s (severity: %3$s). Log: %4$s', 'fp-publisher' ),
            $title ? $title : $post_id,
            $channel,
            $severity_label,
            $log_url
        );

        $this->notifier->notify_slack( $body );
        $this->notifier->notify_email( __( 'Social publishing failed', 'fp-publisher' ), $body );
    }

    private function maybe_record_event( $level, $message, $context = array() ) {
        if ( ! $this->telemetry_channel instanceof TTS_Observability_Channel_Interface ) {
            return;
        }

        $event = new TTS_Observability_Event( 'scheduler', $level, $message, $context );
        $this->telemetry_channel->record_event( $event );
    }

    private function calculate_backoff_delay( $attempt ) {
        $delays = array( 1, 5, 15, 30, 60 );

        if ( $attempt <= 0 ) {
            return 1;
        }

        return isset( $delays[ $attempt - 1 ] ) ? $delays[ $attempt - 1 ] : end( $delays );
    }

    public static function check_queue() {
        $hooks = array( 'tts_publish_social_post' );
        if ( class_exists( 'TTS_Channel_Queue' ) ) {
            $hooks[] = TTS_Channel_Queue::ACTION_HOOK;
        }

        if ( ! class_exists( 'ActionScheduler' ) && ! class_exists( 'ActionScheduler_Store' ) && ! function_exists( 'as_get_scheduled_actions' ) ) {
            return new WP_Error( 'action_scheduler_missing', __( 'Action Scheduler non è disponibile.', 'fp-publisher' ) );
        }

        $pending_total = 0;
        $failed_total  = 0;
        $messages      = array();

        foreach ( $hooks as $hook ) {
            $status = self::inspect_queue_hook( $hook );
            if ( is_wp_error( $status ) ) {
                $messages[] = $status->get_error_message();
            } else {
                $pending_total += $status['pending'];
                $failed_total  += $status['failed'];
                if ( $status['message'] ) {
                    $messages[] = $status['message'];
                }
            }
        }

        if ( $failed_total > 0 ) {
            return new WP_Error( 'action_scheduler_failed_jobs', sprintf( _n( 'La coda ha %d azione fallita.', 'La coda ha %d azioni fallite.', $failed_total, 'fp-publisher' ), $failed_total ) );
        }

        return implode( ' ', array_filter( $messages ) );
    }

    private static function inspect_queue_hook( $hook ) {
        $pending_status = 'pending';
        $failed_status  = 'failed';
        $pending_count  = 0;
        $failed_count   = 0;

        if ( class_exists( 'ActionScheduler_Store' ) ) {
            if ( defined( 'ActionScheduler_Store::STATUS_PENDING' ) ) {
                $pending_status = ActionScheduler_Store::STATUS_PENDING;
            }
            if ( defined( 'ActionScheduler_Store::STATUS_FAILED' ) ) {
                $failed_status = ActionScheduler_Store::STATUS_FAILED;
            }
        }

        $store = null;
        if ( class_exists( 'ActionScheduler' ) && is_callable( array( 'ActionScheduler', 'store' ) ) ) {
            $store = ActionScheduler::store();
        } elseif ( class_exists( 'ActionScheduler_Store' ) && is_callable( array( 'ActionScheduler_Store', 'instance' ) ) ) {
            $store = ActionScheduler_Store::instance();
        }

        if ( $store && method_exists( $store, 'query_actions' ) ) {
            $pending_count = (int) $store->query_actions(
                array(
                    'hook'   => $hook,
                    'status' => $pending_status,
                ),
                'count'
            );

            $failed_count = (int) $store->query_actions(
                array(
                    'hook'   => $hook,
                    'status' => $failed_status,
                ),
                'count'
            );
        } elseif ( function_exists( 'as_get_scheduled_actions' ) ) {
            $pending_actions = as_get_scheduled_actions(
                array(
                    'hook'          => $hook,
                    'status'        => $pending_status,
                    'return_format' => 'ids',
                    'orderby'       => 'date',
                    'order'         => 'ASC',
                    'per_page'      => 100,
                )
            );

            $failed_actions = as_get_scheduled_actions(
                array(
                    'hook'          => $hook,
                    'status'        => $failed_status,
                    'return_format' => 'ids',
                    'per_page'      => 100,
                )
            );

            if ( is_array( $pending_actions ) ) {
                $pending_count = count( $pending_actions );
            }

            if ( is_array( $failed_actions ) ) {
                $failed_count = count( $failed_actions );
            }
        } else {
            return new WP_Error( 'action_scheduler_unavailable', __( 'Impossibile interrogare Action Scheduler.', 'fp-publisher' ) );
        }

        return array(
            'pending' => $pending_count,
            'failed'  => $failed_count,
            'message' => sprintf(
                __( 'Hook %1$s: %2$d pending, %3$d failed.', 'fp-publisher' ),
                $hook,
                $pending_count,
                $failed_count
            ),
        );
    }
}
