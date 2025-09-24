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

    /**
     * Integration gateway used to notify downstream systems.
     *
     * @var TTS_Integration_Gateway_Interface|null
     */
    private $integration_gateway;

    /**
     * Observability channel for telemetry.
     *
     * @var TTS_Observability_Channel_Interface|null
     */
    private $telemetry_channel;

    /**
     * Constructor.
     *
     * @param TTS_Integration_Gateway_Interface|null   $integration_gateway Integration gateway dependency.
     * @param TTS_Observability_Channel_Interface|null $telemetry_channel   Telemetry channel dependency.
     */
    public function __construct( $integration_gateway = null, $telemetry_channel = null ) {
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

        add_action( 'save_post_tts_social_post', array( $this, 'schedule_post' ), 10, 3 );
        add_action( 'tts_publish_social_post', array( $this, 'publish_social_post' ), 10, 2 );
    }

    /**
     * Schedule post publication via Action Scheduler.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function schedule_post( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Security check: only process if this is a legitimate post save with proper nonce
        if ( isset( $_POST['_tts_approved'] ) ) {
            // Verify nonce if processing form data
            if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-post_' . $post_id ) ) {
                return;
            }
            
            // Check user capabilities for this specific post
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

    /**
     * Queue a publication based on a schedule request.
     *
     * @param TTS_Schedule_Request $request Schedule request payload.
     */
    public function queue_from_request( TTS_Schedule_Request $request ) {
        $post_id = $request->get_post_id();

        if ( ! $post_id ) {
            return;
        }

        $channels = $request->get_channels();
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

        $options = get_option( 'tts_settings', array() );

        if ( empty( $channels ) ) {
            as_schedule_single_action( $timestamp, 'tts_publish_social_post', array( $post_id ) );
        } else {
            foreach ( $channels as $channel ) {
                if ( ! is_string( $channel ) || '' === $channel ) {
                    continue;
                }

                $offset = isset( $options[ $channel . '_offset' ] ) ? intval( $options[ $channel . '_offset' ] ) : 0;
                $when   = $timestamp + $offset * MINUTE_IN_SECONDS;
                as_schedule_single_action( $when, 'tts_publish_social_post', array( $post_id, $channel ) );
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

    /**
     * Release scheduled actions for a post and optional channels.
     *
     * @param TTS_Schedule_Cancellation $cancellation Cancellation payload.
     */
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

    /**
     * Unschedule any pending publish actions for a post.
     *
     * @param int          $post_id  Post ID.
     * @param string|array $channels Optional channel or list of channels.
     */
    private function unschedule_post_actions( $post_id, $channels = array() ) {
        $post_id = absint( $post_id );

        if ( ! $post_id ) {
            return;
        }

        as_unschedule_all_actions( 'tts_publish_social_post', array( $post_id ) );
        as_unschedule_all_actions( 'tts_publish_social_post', array( 'post_id' => $post_id ) );

        if ( empty( $channels ) ) {
            return;
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
        }
    }

    /**
     * Send an observability event when telemetry is available.
     *
     * @param string $level   Severity level.
     * @param string $message Event message.
     * @param array  $context Additional context.
     */
    private function maybe_record_event( $level, $message, $context = array() ) {
        if ( ! $this->telemetry_channel instanceof TTS_Observability_Channel_Interface ) {
            return;
        }

        $event = new TTS_Observability_Event( 'scheduler', $level, $message, $context );
        $this->telemetry_channel->record_event( $event );
    }

    /**
     * Publish the social post to configured networks.
     *
     * @param int|array $post_id Post ID or legacy argument array.
     * @param string    $channel Optional channel override.
     */
    public function publish_social_post( $post_id, $channel = '' ) {
        if ( is_array( $post_id ) ) {
            $args    = $post_id;
            $post_id = isset( $args['post_id'] ) ? intval( $args['post_id'] ) : ( isset( $args[0] ) ? intval( $args[0] ) : 0 );
            $channel = isset( $args['channel'] ) ? $args['channel'] : ( isset( $args[1] ) ? $args[1] : '' );
        }

        $post_id        = intval( $post_id );
        $forced_channel = is_string( $channel ) ? sanitize_text_field( $channel ) : '';
        if ( ! $post_id ) {
            return;
        }

        $notifier = new TTS_Notifier();

        $attempt      = (int) get_post_meta( $post_id, '_tts_retry_count', true );
        $max_attempts = 5;

        $client_id = intval( get_post_meta( $post_id, '_tts_client_id', true ) );
        if ( ! $client_id ) {
            tts_log_event( $post_id, 'scheduler', 'error', __( 'Missing client ID', 'fp-publisher' ), '' );
            return;
        }

        tts_log_event( $post_id, 'scheduler', 'start', __( 'Publishing social post', 'fp-publisher' ), '' );

        $tokens = array(
            'facebook'  => get_post_meta( $client_id, '_tts_fb_token', true ),
            'instagram' => get_post_meta( $client_id, '_tts_ig_token', true ),
            // Added support for YouTube uploads.
            'youtube'   => get_post_meta( $client_id, '_tts_yt_token', true ),
            // Added support for TikTok uploads.
            'tiktok'    => get_post_meta( $client_id, '_tts_tt_token', true ),
            // Added support for Blog publishing.
            'blog'      => get_post_meta( $client_id, '_tts_blog_settings', true ),
        );

        $options  = get_option( 'tts_settings', array() );
        $channels = $forced_channel ? array( $forced_channel ) : get_post_meta( $post_id, '_tts_social_channel', true );

        if ( ! is_array( $channels ) ) {
            $channels = $channels ? array( $channels ) : array();
        }

        $this->maybe_record_event(
            'info',
            __( 'Publishing social post triggered.', 'fp-publisher' ),
            array(
                'post_id'   => $post_id,
                'client_id' => $client_id,
                'channels'  => $channels,
            )
        );

        if ( $this->integration_gateway instanceof TTS_Integration_Gateway_Interface ) {
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

        if ( empty( $channels ) && ! $forced_channel ) {
            $mapped_channel = '';
            $id_list        = get_post_meta( $post_id, '_trello_idList', true );
            $board_id      = get_post_meta( $post_id, '_trello_board_id', true );
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
                            $mapped_channel = $row['canale_social'];
                            break;
                        }
                    }
                }
            }
            if ( $mapped_channel ) {
                $channels = array( $mapped_channel );
            }
        }

        if ( empty( $channels ) ) {
            tts_log_event( $post_id, 'scheduler', 'error', __( 'Missing social channel', 'fp-publisher' ), '' );
            return;
        }

        $channels = is_array( $channels ) ? $channels : array( $channels );
        $log      = array();

        $attachment_ids = get_post_meta( $post_id, '_tts_attachment_ids', true );
        $attachment_ids = is_array( $attachment_ids ) ? array_map( 'intval', $attachment_ids ) : array();
        $manual_id      = (int) get_post_meta( $post_id, '_tts_manual_media', true );
        if ( $manual_id ) {
            $attachment_ids[] = $manual_id;
        }

        $processor = new TTS_Image_Processor();

        $error = false;

        foreach ( $channels as $ch ) {
            if ( $attachment_ids ) {
                $resized = array();
                foreach ( $attachment_ids as $att_id ) {
                    $url = $processor->resize_for_channel( $att_id, $ch );
                    if ( $url ) {
                        $resized[ $att_id ] = $url;
                    }
                }
                if ( $resized ) {
                    update_post_meta( $post_id, '_tts_resized_' . $ch, $resized );
                }
            }
            $class = 'TTS_Publisher_' . ucfirst( $ch );
            $file  = plugin_dir_path( __FILE__ ) . 'publishers/class-tts-publisher-' . $ch . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
                if ( class_exists( $class ) ) {
                    $publisher   = new $class();
                    $credentials = isset( $tokens[ $ch ] ) ? $tokens[ $ch ] : '';
                    $template    = isset( $options[ $ch . '_template' ] ) ? $options[ $ch . '_template' ] : '';
                    $custom_message = get_post_meta( $post_id, '_tts_message_' . $ch, true );
                    if ( $custom_message ) {
                        $message = $custom_message;
                    } else {
                        $message = $template ? tts_apply_template( $template, $post_id, $ch ) : '';
                    }

                    try {
                        $log[ $ch ] = $publisher->publish( $post_id, $credentials, $message );
                        if ( is_wp_error( $log[ $ch ] ) ) {
                            $error = true;
                        } elseif ( 'instagram' === $ch ) {
                            $first_comment = get_post_meta( $post_id, '_tts_instagram_first_comment', true );
                            if ( $first_comment && is_array( $log[ $ch ] ) && isset( $log[ $ch ]['id'] ) ) {
                                $comment_res = $publisher->post_comment( $log[ $ch ]['id'], $first_comment );
                                if ( is_wp_error( $comment_res ) ) {
                                    $error = true;
                                    $log['instagram_comment'] = $comment_res;
                                } else {
                                    $log['instagram_comment'] = $comment_res;
                                }
                            }
                        }
                    } catch ( \Exception $e ) {
                        $error       = true;
                        $log[ $ch ]  = $e->getMessage();
                        tts_log_event( $post_id, $ch, 'error', $e->getMessage(), '' );
                    }
                }
            }
        }

        $publish_story = (bool) get_post_meta( $post_id, '_tts_publish_story', true );
        if ( $publish_story ) {
            $story_id  = (int) get_post_meta( $post_id, '_tts_story_media', true );
            $media_url = $story_id ? wp_get_attachment_url( $story_id ) : '';
            if ( $media_url ) {
                $story_channels = array( 'facebook', 'instagram' );
                foreach ( $story_channels as $story_channel ) {
                    $class = 'TTS_Publisher_' . ucfirst( $story_channel ) . '_Story';
                    $file  = plugin_dir_path( __FILE__ ) . 'publishers/class-tts-publisher-' . $story_channel . '-story.php';
                    if ( file_exists( $file ) ) {
                        require_once $file;
                        if ( class_exists( $class ) ) {
                            $publisher   = new $class();
                            $credentials = isset( $tokens[ $story_channel ] ) ? $tokens[ $story_channel ] : '';
                            try {
                                $key             = $story_channel . '_story';
                                $log[ $key ]     = $publisher->publish_story( $post_id, $credentials, $media_url );
                                if ( is_wp_error( $log[ $key ] ) ) {
                                    $error = true;
                                }
                            } catch ( \Exception $e ) {
                                $error = true;
                                $log[ $story_channel . '_story' ] = $e->getMessage();
                                tts_log_event( $post_id, $story_channel . '_story', 'error', $e->getMessage(), '' );
                            }
                        }
                    }
                }
            } else {
                tts_log_event( $post_id, 'scheduler', 'error', __( 'Missing Story media', 'fp-publisher' ), '' );
                $error = true;
            }
        }

        if ( $error ) {
            if ( $attempt >= $max_attempts ) {
                tts_log_event( $post_id, 'scheduler', 'error', __( 'Maximum retry attempts reached', 'fp-publisher' ), '' );
                $log_url = admin_url( 'admin.php?page=fp-publisher-log&post_id=' . $post_id );
                $message = sprintf( __( 'Publishing failed for post %1$s. Log: %2$s', 'fp-publisher' ), get_the_title( $post_id ), $log_url );
                $notifier->notify_slack( $message );
                $notifier->notify_email( __( 'Social publishing failed', 'fp-publisher' ), $message );
                return;
            }

            $attempt++;
            update_post_meta( $post_id, '_tts_retry_count', $attempt );

            $delay     = $this->calculate_backoff_delay( $attempt );
            $timestamp = time() + $delay * MINUTE_IN_SECONDS;
            as_schedule_single_action( $timestamp, 'tts_publish_social_post', array( $post_id ) );

            tts_log_event( $post_id, 'scheduler', 'retry', sprintf( __( 'Retry #%1$d scheduled in %2$d minutes', 'fp-publisher' ), $attempt, $delay ), '' );
            return;
        }

        delete_post_meta( $post_id, '_tts_retry_count' );
        update_post_meta( $post_id, '_published_status', 'published' );
        update_post_meta( $post_id, '_tts_publish_log', $log );

        $card_id       = get_post_meta( $post_id, '_trello_card_id', true );
        $trello_key    = get_post_meta( $client_id, '_tts_trello_key', true );
        $trello_token  = get_post_meta( $client_id, '_tts_trello_token', true );
        $published_list = get_post_meta( $client_id, '_tts_trello_published_list', true );

        if ( $card_id && $trello_key && $trello_token && $published_list ) {
            $first_url = '';
            $links     = array();
            foreach ( $log as $channel => $entry ) {
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
            if ( is_wp_error( $move_response ) ) {
                tts_log_event( $post_id, 'trello', 'error', $move_response->get_error_message(), '' );
            } else {
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
                            'body'    => array( 'text' => implode( "\n", $links ) ),
                            'timeout' => 20,
                        )
                    );
                    if ( is_wp_error( $comment_response2 ) ) {
                        tts_log_event( $post_id, 'trello', 'error', $comment_response2->get_error_message(), '' );
                    }
                }
            }
        }

        tts_log_event( $post_id, 'scheduler', 'complete', __( 'Publish process completed', 'fp-publisher' ), $log );

        $log_url = admin_url( 'admin.php?page=fp-publisher-log&post_id=' . $post_id );
        $message = sprintf( __( 'Publishing completed for post %1$s. Log: %2$s', 'fp-publisher' ), get_the_title( $post_id ), $log_url );
        $notifier->notify_slack( $message );
        $notifier->notify_email( __( 'Social publishing completed', 'fp-publisher' ), $message );
    }

    /**
     * Check the status of the Action Scheduler queue used for publishing.
     *
     * @return string|WP_Error Human readable status message or error describing issues.
     */
    public static function check_queue() {
        if ( ! class_exists( 'ActionScheduler' ) && ! class_exists( 'ActionScheduler_Store' ) && ! function_exists( 'as_get_scheduled_actions' ) ) {
            return new WP_Error( 'action_scheduler_missing', __( 'Action Scheduler non è disponibile.', 'fp-publisher' ) );
        }

        $hook           = 'tts_publish_social_post';
        $store          = null;
        $pending_status = 'pending';
        $failed_status  = 'failed';

        if ( class_exists( 'ActionScheduler' ) && is_callable( array( 'ActionScheduler', 'store' ) ) ) {
            $store = ActionScheduler::store();
        } elseif ( class_exists( 'ActionScheduler_Store' ) && is_callable( array( 'ActionScheduler_Store', 'instance' ) ) ) {
            $store = ActionScheduler_Store::instance();
        }

        if ( class_exists( 'ActionScheduler_Store' ) ) {
            if ( defined( 'ActionScheduler_Store::STATUS_PENDING' ) ) {
                $pending_status = ActionScheduler_Store::STATUS_PENDING;
            }
            if ( defined( 'ActionScheduler_Store::STATUS_FAILED' ) ) {
                $failed_status = ActionScheduler_Store::STATUS_FAILED;
            }
        }

        $pending_count     = 0;
        $failed_count      = 0;
        $oldest_pending_id = 0;

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

            $oldest_ids = $store->query_actions(
                array(
                    'hook'    => $hook,
                    'status'  => $pending_status,
                    'orderby' => 'date',
                    'order'   => 'ASC',
                    'per_page' => 1,
                )
            );

            if ( is_array( $oldest_ids ) && ! empty( $oldest_ids ) ) {
                $oldest_pending_id = (int) reset( $oldest_ids );
            } elseif ( is_numeric( $oldest_ids ) ) {
                $oldest_pending_id = (int) $oldest_ids;
            }
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
                $first_pending = reset( $pending_actions );
                if ( $first_pending ) {
                    $oldest_pending_id = (int) $first_pending;
                }
            }

            if ( is_array( $failed_actions ) ) {
                $failed_count = count( $failed_actions );
            }
        } else {
            return new WP_Error( 'action_scheduler_unavailable', __( 'Impossibile interrogare Action Scheduler.', 'fp-publisher' ) );
        }

        $oldest_pending_age = 0;

        if ( $oldest_pending_id && $store && method_exists( $store, 'get_date' ) ) {
            try {
                $oldest_date = $store->get_date( $oldest_pending_id );
                if ( $oldest_date instanceof \DateTime ) {
                    $oldest_pending_age = max( 0, time() - $oldest_date->getTimestamp() );
                }
            } catch ( \Exception $e ) {
                // If we cannot retrieve the date we skip the stale queue check.
            }
        }

        $pending_threshold = (int) apply_filters( 'tts_scheduler_pending_threshold', 25 );
        $failed_threshold  = (int) apply_filters( 'tts_scheduler_failed_threshold', 0 );
        $stale_threshold   = (int) apply_filters( 'tts_scheduler_pending_stale_threshold', 15 * MINUTE_IN_SECONDS );

        if ( $failed_count > $failed_threshold ) {
            return new WP_Error(
                'action_scheduler_failed_jobs',
                sprintf(
                    _n( 'La coda ha %d azione fallita.', 'La coda ha %d azioni fallite.', $failed_count, 'fp-publisher' ),
                    $failed_count
                )
            );
        }

        if ( $oldest_pending_age > $stale_threshold && $pending_count > 0 ) {
            $minutes = (int) ceil( $oldest_pending_age / MINUTE_IN_SECONDS );

            return new WP_Error(
                'action_scheduler_queue_stale',
                sprintf(
                    _n(
                        'L\'azione più vecchia è in attesa da %d minuto.',
                        'L\'azione più vecchia è in attesa da %d minuti.',
                        $minutes,
                        'fp-publisher'
                    ),
                    $minutes
                )
            );
        }

        if ( $pending_count > $pending_threshold ) {
            return new WP_Error(
                'action_scheduler_queue_backlog',
                sprintf(
                    _n( 'Ci sono %d azione in attesa.', 'Ci sono %d azioni in attesa.', $pending_count, 'fp-publisher' ),
                    $pending_count
                )
            );
        }

        $message = sprintf(
            __( 'Coda regolare: %1$d in attesa, %2$d fallite.', 'fp-publisher' ),
            $pending_count,
            $failed_count
        );

        if ( function_exists( 'as_next_scheduled_action' ) ) {
            $next_timestamp = as_next_scheduled_action( $hook );

            if ( $next_timestamp ) {
                $now = time();

                if ( $next_timestamp <= $now ) {
                    $message .= ' ' . __( 'Una nuova azione è pronta per l\'esecuzione.', 'fp-publisher' );
                } else {
                    $message .= ' ' . sprintf(
                        __( 'Prossima esecuzione tra %s.', 'fp-publisher' ),
                        human_time_diff( $now, $next_timestamp )
                    );
                }
            } elseif ( 0 === $pending_count ) {
                $message .= ' ' . __( 'Nessuna azione pianificata al momento.', 'fp-publisher' );
            }
        }

        return $message;
    }

    /**
     * Calculate delay for retry attempts in minutes.
     *
     * @param int $attempt Current attempt number.
     * @return int Delay in minutes.
     */
    private function calculate_backoff_delay( $attempt ) {
        $delays = array( 1, 5, 15, 30, 60 );

        if ( $attempt <= 0 ) {
            return 1;
        }

        return isset( $delays[ $attempt - 1 ] ) ? $delays[ $attempt - 1 ] : end( $delays );
    }
}
