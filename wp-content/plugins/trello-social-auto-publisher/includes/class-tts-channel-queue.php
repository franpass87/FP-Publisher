<?php
/**
 * Asynchronous channel queue built on top of Action Scheduler.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'TTS_Channel_Queue' ) ) {
    /**
     * Coordinates asynchronous channel jobs.
     */
    class TTS_Channel_Queue {

        /**
         * Action Scheduler hook name used for channel jobs.
         */
        const ACTION_HOOK = 'tts_process_channel_job';

        /**
         * Default configuration applied when no custom option is present.
         *
         * @var array
         */
        private $default_config = array(
            'max_pending' => 10,
            'retry'       => array(
                'strategy'      => 'progressive',
                'global_max'    => 5,
                'jitter'        => 60,
                'delays'        => array(
                    'low'      => 5,
                    'medium'   => 15,
                    'high'     => 30,
                    'critical' => 60,
                ),
                'max_attempts'  => array(
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

        /**
         * Enqueue a job for a specific channel.
         *
         * @param array $job       Job payload.
         * @param int   $timestamp Execution timestamp.
         *
         * @return array|WP_Error  Normalized job payload or error.
         */
        public function enqueue_job( array $job, $timestamp ) {
            if ( ! function_exists( 'as_schedule_single_action' ) ) {
                return new WP_Error( 'action_scheduler_missing', __( 'Action Scheduler non è disponibile.', 'fp-publisher' ) );
            }

            $job      = $this->normalize_job( $job, $timestamp );
            $channel  = $job['channel'];
            $group    = $this->get_group_name( $channel );
            $limit_ok = $this->enforce_pending_limit( $channel, $group );

            if ( is_wp_error( $limit_ok ) ) {
                return $limit_ok;
            }

            $args      = array( $this->strip_runtime_keys( $job ) );
            $action_id = as_schedule_single_action( $timestamp, self::ACTION_HOOK, $args, $group );

            $job['action_id']      = $action_id;
            $job['action_group']   = $group;
            $job['scheduled_args'] = $args;

            return $job;
        }

        /**
         * Retrieve configuration for a channel.
         *
         * @param string $channel Channel identifier.
         *
         * @return array
         */
        public function get_channel_config( $channel ) {
            $channel = sanitize_key( $channel );
            $config  = $this->get_raw_config();

            if ( isset( $config[ $channel ] ) && is_array( $config[ $channel ] ) ) {
                return $this->merge_config( $config[ $channel ] );
            }

            return $this->default_config;
        }

        /**
         * Build a readable group name for Action Scheduler.
         *
         * @param string $channel Channel identifier.
         *
         * @return string
         */
        public function get_group_name( $channel ) {
            $channel = sanitize_key( $channel );
            if ( '' === $channel ) {
                $channel = 'generic';
            }

            return 'tts_channel_' . $channel;
        }

        /**
         * Normalize the job payload ensuring required metadata is present.
         *
         * @param array $job       Original job payload.
         * @param int   $timestamp Scheduled timestamp.
         *
         * @return array
         */
        private function normalize_job( array $job, $timestamp ) {
            $job['job_id']        = isset( $job['job_id'] ) ? (string) $job['job_id'] : uniqid( 'tts_job_', true );
            $job['post_id']       = isset( $job['post_id'] ) ? absint( $job['post_id'] ) : 0;
            $job['channel']       = isset( $job['channel'] ) ? sanitize_key( $job['channel'] ) : '';
            $job['client_id']     = isset( $job['client_id'] ) ? absint( $job['client_id'] ) : 0;
            $job['attempt']       = isset( $job['attempt'] ) ? absint( $job['attempt'] ) : 0;
            $job['max_attempts']  = isset( $job['max_attempts'] ) ? absint( $job['max_attempts'] ) : 5;
            $job['scheduled_for'] = isset( $job['scheduled_for'] ) ? (int) $job['scheduled_for'] : (int) $timestamp;
            $job['metadata']      = isset( $job['metadata'] ) && is_array( $job['metadata'] ) ? $job['metadata'] : array();

            $job['metadata']['queued_at']    = isset( $job['metadata']['queued_at'] ) ? (float) $job['metadata']['queued_at'] : microtime( true );
            $job['metadata']['scheduled_for'] = $job['scheduled_for'];

            $config                       = $this->get_channel_config( $job['channel'] );
            $job['retry_blueprint']       = isset( $job['retry_blueprint'] ) && is_array( $job['retry_blueprint'] ) ? $job['retry_blueprint'] : $config['retry'];
            $job['circuit_blueprint']     = isset( $job['circuit_blueprint'] ) && is_array( $job['circuit_blueprint'] ) ? $job['circuit_blueprint'] : $config['circuit'];
            $job['retry_blueprint']['strategy']   = $job['retry_blueprint']['strategy'] ?? $config['retry']['strategy'];
            $job['retry_blueprint']['delays']     = isset( $job['retry_blueprint']['delays'] ) && is_array( $job['retry_blueprint']['delays'] ) ? $job['retry_blueprint']['delays'] : $config['retry']['delays'];
            $job['retry_blueprint']['max_attempts'] = isset( $job['retry_blueprint']['max_attempts'] ) && is_array( $job['retry_blueprint']['max_attempts'] ) ? $job['retry_blueprint']['max_attempts'] : $config['retry']['max_attempts'];
            $job['retry_blueprint']['global_max']  = isset( $job['retry_blueprint']['global_max'] ) ? absint( $job['retry_blueprint']['global_max'] ) : $config['retry']['global_max'];
            $job['retry_blueprint']['jitter']      = isset( $job['retry_blueprint']['jitter'] ) ? absint( $job['retry_blueprint']['jitter'] ) : $config['retry']['jitter'];

            return $job;
        }

        /**
         * Remove runtime-only keys before sending job to Action Scheduler.
         *
         * @param array $job Job payload.
         *
         * @return array
         */
        private function strip_runtime_keys( array $job ) {
            $job = $job;
            unset( $job['scheduled_args'], $job['action_id'], $job['action_group'] );
            return $job;
        }

        /**
         * Ensure channel pending limit is respected.
         *
         * @param string $channel Channel identifier.
         * @param string $group   Action Scheduler group.
         *
         * @return true|WP_Error
         */
        private function enforce_pending_limit( $channel, $group ) {
            $config = $this->get_channel_config( $channel );
            $limit  = isset( $config['max_pending'] ) ? absint( $config['max_pending'] ) : 0;

            if ( $limit <= 0 ) {
                return true;
            }

            if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
                return true;
            }

            $pending_actions = as_get_scheduled_actions(
                array(
                    'hook'          => self::ACTION_HOOK,
                    'status'        => 'pending',
                    'group'         => $group,
                    'return_format' => 'ids',
                    'per_page'      => $limit + 1,
                )
            );

            if ( is_array( $pending_actions ) && count( $pending_actions ) >= $limit ) {
                return new WP_Error(
                    'channel_queue_saturated',
                    sprintf(
                        __( 'Il canale %s ha raggiunto il numero massimo di job in attesa.', 'fp-publisher' ),
                        $channel
                    ),
                    array(
                        'channel' => $channel,
                        'limit'   => $limit,
                    )
                );
            }

            return true;
        }

        /**
         * Merge channel configuration with defaults.
         *
         * @param array $channel_config Channel-specific overrides.
         *
         * @return array
         */
        private function merge_config( array $channel_config ) {
            $config = $this->default_config;

            if ( isset( $channel_config['max_pending'] ) ) {
                $config['max_pending'] = absint( $channel_config['max_pending'] );
            }

            if ( isset( $channel_config['retry'] ) && is_array( $channel_config['retry'] ) ) {
                $config['retry'] = wp_parse_args( $channel_config['retry'], $config['retry'] );

                if ( isset( $channel_config['retry']['delays'] ) && is_array( $channel_config['retry']['delays'] ) ) {
                    $config['retry']['delays'] = wp_parse_args( $channel_config['retry']['delays'], $config['retry']['delays'] );
                }

                if ( isset( $channel_config['retry']['max_attempts'] ) && is_array( $channel_config['retry']['max_attempts'] ) ) {
                    $config['retry']['max_attempts'] = wp_parse_args( $channel_config['retry']['max_attempts'], $config['retry']['max_attempts'] );
                }
            }

            if ( isset( $channel_config['circuit'] ) && is_array( $channel_config['circuit'] ) ) {
                $config['circuit'] = wp_parse_args( $channel_config['circuit'], $config['circuit'] );

                if ( isset( $channel_config['circuit']['cooldowns'] ) && is_array( $channel_config['circuit']['cooldowns'] ) ) {
                    $config['circuit']['cooldowns'] = wp_parse_args( $channel_config['circuit']['cooldowns'], $config['circuit']['cooldowns'] );
                }
            }

            return $config;
        }

        /**
         * Read raw configuration from WordPress options.
         *
         * @return array
         */
        private function get_raw_config() {
            $option = get_option( 'tts_channel_limits', array() );

            if ( ! is_array( $option ) ) {
                return array();
            }

            return $option;
        }
    }
}
