<?php
/**
 * Protective wrapper for publisher operations integrating rate limiting and error recovery.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'TTS_Publisher_Guard' ) ) {
    /**
     * Provides resilience primitives (rate limiting, circuit breaker, alarms).
     */
    class TTS_Publisher_Guard {

        /**
         * Rate limiter instance.
         *
         * @var TTS_Rate_Limiter|null
         */
        private $rate_limiter;

        /**
         * Error recovery orchestrator.
         *
         * @var TTS_Error_Recovery|null
         */
        private $error_recovery;

        /**
         * Notification channel for alarms.
         *
         * @var TTS_Notifier
         */
        private $notifier;

        /**
         * Option key storing circuit breaker state.
         */
        private $circuit_option = 'tts_channel_circuit_breakers';

        /**
         * Constructor.
         *
         * @param TTS_Rate_Limiter|null   $rate_limiter   Rate limiter dependency.
         * @param TTS_Error_Recovery|null $error_recovery Error recovery handler.
         * @param TTS_Notifier|null       $notifier       Optional notifier override.
         */
        public function __construct( $rate_limiter = null, $error_recovery = null, $notifier = null ) {
            if ( $rate_limiter instanceof TTS_Rate_Limiter ) {
                $this->rate_limiter = $rate_limiter;
            }

            if ( $error_recovery instanceof TTS_Error_Recovery ) {
                $this->error_recovery = $error_recovery;
            }

            if ( $notifier instanceof TTS_Notifier ) {
                $this->notifier = $notifier;
            } else {
                $this->notifier = new TTS_Notifier();
            }
        }

        /**
         * Execute an operation within the guard rails.
         *
         * @param string   $channel Channel identifier.
         * @param callable $operation Operation callback.
         * @param array    $context Additional context (post_id, retry_count, etc.).
         *
         * @return array Response payload describing success or failure.
         */
        public function execute( $channel, callable $operation, array $context = array() ) {
            $channel = sanitize_key( $channel );
            $context = $this->prepare_context( $channel, $context );

            if ( empty( $channel ) ) {
                return array(
                    'success' => false,
                    'error'   => new WP_Error( 'invalid_channel', __( 'Canale non valido.', 'fp-publisher' ) ),
                    'severity' => TTS_Error_Recovery::SEVERITY_HIGH,
                );
            }

            $circuit_state = $this->get_circuit_state( $channel );
            $now           = time();

            if ( 'open' === $circuit_state['state'] && $now < $circuit_state['open_until'] ) {
                $error = new WP_Error(
                    'circuit_open',
                    sprintf( __( 'Circuit breaker attivo per il canale %s.', 'fp-publisher' ), $channel ),
                    array(
                        'retry_after' => $circuit_state['open_until'] - $now,
                        'channel'     => $channel,
                    )
                );

                return array(
                    'success'  => false,
                    'severity' => TTS_Error_Recovery::SEVERITY_HIGH,
                    'error'    => $error,
                    'circuit'  => $circuit_state,
                );
            }

            if ( 'open' === $circuit_state['state'] && $now >= $circuit_state['open_until'] ) {
                $circuit_state['state'] = 'half_open';
                $this->store_circuit_state( $channel, $circuit_state );
            }

            $rate_context = null;
            if ( $this->rate_limiter instanceof TTS_Rate_Limiter ) {
                $rate_context = $this->rate_limiter->is_request_allowed( $channel );
                if ( isset( $rate_context['allowed'] ) && false === $rate_context['allowed'] ) {
                    $error = new WP_Error(
                        'rate_limit',
                        $rate_context['reason'] ?? __( 'Limite di velocità raggiunto.', 'fp-publisher' ),
                        $rate_context
                    );

                    $failure = $this->handle_failure( $channel, $error, TTS_Error_Recovery::SEVERITY_MEDIUM, $context );
                    $failure['rate_limit'] = $rate_context;

                    return $failure;
                }
            }

            $start = microtime( true );

            try {
                $result = call_user_func( $operation );
            } catch ( Throwable $throwable ) {
                return $this->handle_failure( $channel, $throwable, null, $context );
            }

            if ( is_wp_error( $result ) ) {
                return $this->handle_failure( $channel, $result, null, $context );
            }

            $duration = microtime( true ) - $start;

            if ( $this->rate_limiter instanceof TTS_Rate_Limiter ) {
                $this->rate_limiter->record_request(
                    $channel,
                    'publish',
                    array(
                        'response_time' => $duration,
                        'status_code'   => 200,
                    )
                );
            }

            $this->reset_circuit( $channel );

            return array(
                'success'    => true,
                'result'     => $result,
                'duration'   => $duration,
                'rate_limit' => $rate_context,
            );
        }

        /**
         * Prepare execution context.
         *
         * @param string $channel Channel identifier.
         * @param array  $context Additional context.
         *
         * @return array
         */
        private function prepare_context( $channel, array $context ) {
            $context['channel'] = $channel;
            $context['retry_count'] = isset( $context['retry_count'] ) ? absint( $context['retry_count'] ) : 0;
            $context['skip_retry_queue'] = isset( $context['skip_retry_queue'] ) ? (bool) $context['skip_retry_queue'] : true;

            return $context;
        }

        /**
         * Handle failure path.
         *
         * @param string         $channel  Channel identifier.
         * @param WP_Error|mixed $error    Error payload.
         * @param int|null       $severity Optional severity override.
         * @param array          $context  Additional context.
         *
         * @return array Failure response payload.
         */
        private function handle_failure( $channel, $error, $severity = null, array $context = array() ) {
            if ( null === $severity && $this->error_recovery instanceof TTS_Error_Recovery ) {
                $severity = $this->error_recovery->assess_error_severity( $error );
            } elseif ( null === $severity ) {
                $severity = TTS_Error_Recovery::SEVERITY_MEDIUM;
            }

            $context['channel'] = $channel;
            $recovery_response  = null;

            if ( $this->error_recovery instanceof TTS_Error_Recovery ) {
                $context['skip_retry_queue'] = $context['skip_retry_queue'] ?? true;
                $recovery_response = $this->error_recovery->handle_error( $error, 'api_publish_post', $context );
            }

            $severity_label = $this->get_severity_label( $severity );
            $circuit_state  = $this->register_failure( $channel, $severity_label, $error, $context );

            if ( $severity >= TTS_Error_Recovery::SEVERITY_HIGH ) {
                $this->send_alarm( $channel, $error, $context, $circuit_state );
            }

            return array(
                'success'        => false,
                'severity'       => $severity,
                'severity_label' => $severity_label,
                'error'          => $error,
                'recovery'       => $recovery_response,
                'circuit'        => $circuit_state,
            );
        }

        /**
         * Get human readable severity label.
         *
         * @param int $severity Severity code.
         *
         * @return string
         */
        private function get_severity_label( $severity ) {
            if ( $this->error_recovery instanceof TTS_Error_Recovery ) {
                return $this->error_recovery->get_severity_label( $severity );
            }

            $map = array(
                TTS_Error_Recovery::SEVERITY_LOW      => 'low',
                TTS_Error_Recovery::SEVERITY_MEDIUM   => 'medium',
                TTS_Error_Recovery::SEVERITY_HIGH     => 'high',
                TTS_Error_Recovery::SEVERITY_CRITICAL => 'critical',
            );

            return $map[ $severity ] ?? 'unknown';
        }

        /**
         * Register a failure and update the circuit breaker state.
         *
         * @param string $channel Channel identifier.
         * @param string $severity_label Severity label.
         * @param mixed  $error   Error object/data.
         * @param array  $context Context information.
         *
         * @return array Updated circuit state.
         */
        private function register_failure( $channel, $severity_label, $error, array $context = array() ) {
            $state   = $this->get_circuit_state( $channel );
            $state['failure_count'] = isset( $state['failure_count'] ) ? absint( $state['failure_count'] ) + 1 : 1;
            $state['last_error']    = $this->normalize_error_payload( $error );
            $state['last_severity'] = $severity_label;
            $state['last_failure']  = time();

            $config = isset( $context['channel_config']['circuit'] ) ? $context['channel_config']['circuit'] : array();
            $threshold = isset( $config['failure_threshold'] ) ? absint( $config['failure_threshold'] ) : 3;
            $cooldowns = isset( $config['cooldowns'] ) && is_array( $config['cooldowns'] ) ? $config['cooldowns'] : array();

            $cooldown = isset( $cooldowns[ $severity_label ] ) ? absint( $cooldowns[ $severity_label ] ) : 600;

            if ( in_array( $severity_label, array( 'high', 'critical' ), true ) || $state['failure_count'] >= $threshold ) {
                $state['state']       = 'open';
                $state['open_until']  = time() + max( 60, $cooldown );
                $state['failure_count'] = $state['failure_count'];
            }

            $this->store_circuit_state( $channel, $state );

            return $state;
        }

        /**
         * Reset the circuit for the channel after a successful execution.
         *
         * @param string $channel Channel identifier.
         */
        private function reset_circuit( $channel ) {
            $state = $this->get_circuit_state( $channel );
            if ( 'closed' === $state['state'] && empty( $state['failure_count'] ) ) {
                return;
            }

            $state['state']         = 'closed';
            $state['failure_count'] = 0;
            $state['open_until']    = 0;
            $state['last_error']    = null;
            $state['last_severity'] = null;

            $this->store_circuit_state( $channel, $state );
        }

        /**
         * Retrieve circuit state for a channel.
         *
         * @param string $channel Channel identifier.
         *
         * @return array
         */
        private function get_circuit_state( $channel ) {
            $store = get_option( $this->circuit_option, array() );

            if ( isset( $store[ $channel ] ) && is_array( $store[ $channel ] ) ) {
                return wp_parse_args(
                    $store[ $channel ],
                    array(
                        'state'         => 'closed',
                        'failure_count' => 0,
                        'open_until'    => 0,
                        'last_error'    => null,
                        'last_severity' => null,
                    )
                );
            }

            return array(
                'state'         => 'closed',
                'failure_count' => 0,
                'open_until'    => 0,
                'last_error'    => null,
                'last_severity' => null,
            );
        }

        /**
         * Persist circuit state.
         *
         * @param string $channel Channel identifier.
         * @param array  $state   Circuit state payload.
         */
        private function store_circuit_state( $channel, array $state ) {
            $store           = get_option( $this->circuit_option, array() );
            $store[ $channel ] = $state;
            update_option( $this->circuit_option, $store, false );
        }

        /**
         * Normalize error payload for circuit storage.
         *
         * @param mixed $error Error payload.
         *
         * @return array|null
         */
        private function normalize_error_payload( $error ) {
            if ( is_wp_error( $error ) ) {
                return array(
                    'code'    => $error->get_error_code(),
                    'message' => $error->get_error_message(),
                    'data'    => $error->get_error_data(),
                );
            }

            if ( $error instanceof Throwable ) {
                return array(
                    'code'    => $error->getCode(),
                    'message' => $error->getMessage(),
                );
            }

            if ( is_array( $error ) ) {
                return $error;
            }

            if ( is_string( $error ) ) {
                return array(
                    'code'    => 'error',
                    'message' => $error,
                );
            }

            return null;
        }

        /**
         * Send alarm notification for severe incidents.
         *
         * @param string $channel Channel identifier.
         * @param mixed  $error   Error payload.
         * @param array  $context Context information.
         * @param array  $state   Circuit state.
         */
        private function send_alarm( $channel, $error, array $context, array $state ) {
            $message_parts = array();
            $message_parts[] = sprintf( 'Channel: %s', $channel );

            if ( is_wp_error( $error ) ) {
                $message_parts[] = sprintf( 'Error: %s (%s)', $error->get_error_message(), $error->get_error_code() );
            } elseif ( $error instanceof Throwable ) {
                $message_parts[] = sprintf( 'Exception: %s', $error->getMessage() );
            }

            if ( isset( $context['post_id'] ) ) {
                $message_parts[] = sprintf( 'Post ID: %d', absint( $context['post_id'] ) );
            }

            if ( isset( $state['open_until'] ) && $state['open_until'] > time() ) {
                $message_parts[] = sprintf( 'Circuit open until: %s', date_i18n( 'c', $state['open_until'] ) );
            }

            $message = implode( ' | ', $message_parts );

            $this->notifier->notify_slack( '[Publisher Guard] ' . $message );
            $this->notifier->notify_email(
                __( 'Blocco pubblicazione canale', 'fp-publisher' ),
                $message
            );
        }
    }
}
