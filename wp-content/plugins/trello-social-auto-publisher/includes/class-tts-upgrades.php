<?php
/**
 * Handles plugin upgrade routines, migrations, and cache invalidation.
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! class_exists( 'TTS_Plugin_Upgrades' ) ) {
        /**
         * Coordinates incremental upgrade steps when the plugin version changes.
         */
        class TTS_Plugin_Upgrades {

                /**
                 * Option key used to persist the installed plugin version.
                 */
                const OPTION_KEY = 'tts_plugin_version';

                /**
                 * Cached singleton instance.
                 *
                 * @var self|null
                 */
                private static $instance = null;

                /**
                 * Indicates whether migrations already ran during the current request.
                 *
                 * @var bool
                 */
                private $did_upgrade = false;

                /**
                 * Target plugin version string.
                 *
                 * @var string
                 */
                private $current_version = '1.1.0';

                /**
                 * Retrieve the shared singleton instance.
                 *
                 * @param string|null $version Optional explicit version override.
                 * @return self
                 */
                public static function instance( $version = null ) {
                        if ( null === self::$instance ) {
                                self::$instance = new self();
                        }

                        if ( null !== $version && '' !== $version ) {
                                self::$instance->current_version = (string) $version;
                        } elseif ( defined( 'TSAP_VERSION' ) && '' !== TSAP_VERSION ) {
                                self::$instance->current_version = (string) TSAP_VERSION;
                        }

                        return self::$instance;
                }

                /**
                 * Reset the cached instance. Intended for use in the legacy test harness.
                 *
                 * @return void
                 */
                public static function reset_instance_for_tests() {
                        self::$instance = null;
                }

                /**
                 * Run upgrade routines when the stored version lags behind the codebase.
                 *
                 * @return void
                 */
                public function maybe_upgrade() {
                        if ( $this->did_upgrade ) {
                                return;
                        }

                        $this->did_upgrade = true;

                        $stored_version = $this->get_stored_version();

                        if ( version_compare( $stored_version, $this->current_version, '>=' ) ) {
                                if ( $stored_version !== $this->current_version ) {
                                        $this->persist_version( $this->current_version );
                                }

                                return;
                        }

                        $this->run_migrations( $stored_version );
                        $this->persist_version( $this->current_version );
                        $this->flush_runtime_caches();
                }

                /**
                 * Retrieve the stored plugin version.
                 *
                 * @return string
                 */
                private function get_stored_version() {
                        $default = '0.0.0';

                        if ( function_exists( 'tsap_get_option' ) ) {
                                $stored = tsap_get_option( self::OPTION_KEY, $default );
                        } elseif ( function_exists( 'get_option' ) ) {
                                $stored = get_option( self::OPTION_KEY, $default );
                        } else {
                                $stored = $default;
                        }

                        $stored = is_string( $stored ) ? trim( $stored ) : '';

                        return '' === $stored ? $default : $stored;
                }

                /**
                 * Persist the provided version string to the options table.
                 *
                 * @param string $version Version string to store.
                 * @return void
                 */
                private function persist_version( $version ) {
                        if ( function_exists( 'tsap_update_option' ) ) {
                                tsap_update_option( self::OPTION_KEY, (string) $version );
                        } elseif ( function_exists( 'update_option' ) ) {
                                update_option( self::OPTION_KEY, (string) $version );
                        }
                }

                /**
                 * Execute migrations newer than the provided version.
                 *
                 * @param string $from_version Previously stored version.
                 * @return void
                 */
                private function run_migrations( $from_version ) {
                        foreach ( $this->get_migrations() as $target => $callback ) {
                                if ( version_compare( $from_version, $target, '<' ) && is_callable( array( $this, $callback ) ) ) {
                                        $this->{$callback}( $from_version );
                                }
                        }
                }

                /**
                 * Retrieve the list of incremental migrations.
                 *
                 * @return array<string, string>
                 */
                private function get_migrations() {
                        return array(
                                '1.0.1' => 'migrate_to_101',
                                '1.1.0' => 'migrate_to_110',
                        );
                }

                /**
                 * Ensure critical database tables are provisioned during upgrades to 1.0.1.
                 *
                 * @param string $from_version Original version string.
                 * @return void
                 */
                private function migrate_to_101( $from_version ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                        $this->ensure_database_schema();
                }

                /**
                 * Migrate network-aware options and synchronise caches for 1.1.0.
                 *
                 * @param string $from_version Original version string.
                 * @return void
                 */
                private function migrate_to_110( $from_version ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                        $this->migrate_network_options();
                }

                /**
                 * Ensure the plugin's database schema is provisioned.
                 *
                 * @return void
                 */
                private function ensure_database_schema() {
                        if ( class_exists( 'TTS_Security_Audit' ) && is_callable( array( 'TTS_Security_Audit', 'activate' ) ) ) {
                                TTS_Security_Audit::activate();
                        }

                        if ( class_exists( 'TTS_Workflow_System' ) && is_callable( array( 'TTS_Workflow_System', 'install' ) ) ) {
                                TTS_Workflow_System::install();
                        }

                        if ( class_exists( 'TTS_Integration_Hub' ) && is_callable( array( 'TTS_Integration_Hub', 'install' ) ) ) {
                                TTS_Integration_Hub::install();
                        }
                }

                /**
                 * Migrate plugin options into the network options table when required.
                 *
                 * @return void
                 */
                private function migrate_network_options() {
                        if ( ! function_exists( 'tsap_is_network_mode' ) || ! tsap_is_network_mode() ) {
                                return;
                        }

                        if ( ! function_exists( 'update_site_option' ) || ! function_exists( 'get_site_option' ) ) {
                                return;
                        }

                        $candidates = $this->collect_option_candidates();

                        if ( empty( $candidates ) ) {
                                return;
                        }

                        foreach ( array_unique( $candidates ) as $option_name ) {
                                if ( ! is_string( $option_name ) || '' === $option_name ) {
                                        continue;
                                }

                                if ( function_exists( 'tsap_option_uses_network_storage' ) && ! tsap_option_uses_network_storage( $option_name ) ) {
                                        continue;
                                }

                                $option_value = function_exists( 'get_option' ) ? get_option( $option_name, null ) : null;

                                if ( null === $option_value ) {
                                        continue;
                                }

                                $existing_network_value = get_site_option( $option_name, null );

                                if ( null === $existing_network_value ) {
                                        update_site_option( $option_name, $option_value );
                                }

                                if ( function_exists( 'delete_option' ) ) {
                                        delete_option( $option_name );
                                }
                        }
                }

                /**
                 * Gather option names that should be migrated to network storage.
                 *
                 * @return array<int, string>
                 */
                private function collect_option_candidates() {
                        $candidates = $this->get_known_option_names();

                        global $wpdb;

                        if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_col' ) ) {
                                $option_table = property_exists( $wpdb, 'options' ) ? $wpdb->options : $wpdb->prefix . 'options';
                                $pattern      = $wpdb->esc_like( 'tts_' ) . '%';
                                $query        = $wpdb->prepare( "SELECT option_name FROM {$option_table} WHERE option_name LIKE %s", $pattern );

                                $results = $wpdb->get_col( $query );

                                if ( is_array( $results ) ) {
                                        $candidates = array_merge( $candidates, $results );
                                }
                        }

                        return $candidates;
                }

                /**
                 * Known plugin option names used when the database cannot be queried.
                 *
                 * @return array<int, string>
                 */
                private function get_known_option_names() {
                        return array(
                                self::OPTION_KEY,
                                'tts_settings',
                                'tts_social_apps',
                                'tts_trello_enabled',
                                'tts_google_drive_settings',
                                'tts_google_drive_access_token',
                                'tts_google_drive_folder_id',
                                'tts_dropbox_settings',
                                'tts_dropbox_access_token',
                                'tts_dropbox_folder_path',
                                'tts_channel_limits',
                                'tts_error_logs',
                                'tts_api_request_logs',
                                'tts_blocked_ips',
                                'tts_last_health_check',
                                'tts_alert_settings',
                                'tts_slack_webhook',
                                'tts_retry_queue',
                                'tts_managed_credentials',
                                'tts_profiler_stats',
                                'tts_youtube_daily_usage',
                                'tts_first_activation',
                                'tts_integration_hub_db_version',
                                'tts_analytics_last_processed_id',
                                'tts_analytics_last_run',
                        );
                }

                /**
                 * Flush WordPress, plugin cache layers, and opcache when supported.
                 *
                 * @return void
                 */
                private function flush_runtime_caches() {
                        if ( function_exists( 'wp_cache_flush' ) ) {
                                wp_cache_flush();
                        }

                        $this->flush_plugin_caches();

                        if ( class_exists( 'TTS_Performance' ) && is_callable( array( 'TTS_Performance', 'cleanup_expired_transients' ) ) ) {
                                try {
                                        TTS_Performance::cleanup_expired_transients();
                                } catch ( \Throwable $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                                        // Ignore transient cleanup failures; production logging will capture via runtime logger.
                                }
                        }

                        if ( function_exists( 'opcache_reset' ) ) {
                                @opcache_reset();
                        }
                }

                /**
                 * Attempt to clear plugin-managed caches without requiring Action Scheduler.
                 *
                 * @return void
                 */
                private function flush_plugin_caches() {
                        $cache_manager = $this->resolve_cache_manager();

                        if ( $cache_manager ) {
                                try {
                                        global $wpdb;

                                        if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_col' ) ) {
                                                $cache_manager->clear_cache();
                                                return;
                                        }

                                        $this->clear_known_transients();
                                } catch ( \Throwable $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                                        $this->clear_known_transients();
                                }

                                return;
                        }

                        $this->clear_known_transients();
                }

                /**
                 * Resolve a cache manager instance if available.
                 *
                 * @return object|null
                 */
                private function resolve_cache_manager() {
                        if ( class_exists( 'TTS_Cache_Manager' ) ) {
                                return new TTS_Cache_Manager();
                        }

                        if ( class_exists( 'TTS_Plugin_Bootstrap' ) ) {
                                try {
                                        $container = TTS_Plugin_Bootstrap::instance()->container();

                                        if ( method_exists( $container, 'has' ) && $container->has( 'cache_manager' ) ) {
                                                return $container->get( 'cache_manager' );
                                        }
                                } catch ( \Throwable $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                                        return null;
                                }
                        }

                        return null;
                }

                /**
                 * Clear a conservative list of plugin-specific transients.
                 *
                 * @return void
                 */
                private function clear_known_transients() {
                        $transients = array(
                                'tts_dashboard_stats',
                                'tts_performance_metrics',
                                'tts_active_channels_stats',
                                'tts_success_rate_stats',
                                'tts_trend_data',
                                'tts_active_channels',
                                'tts_success_rate',
                                'tts_system_health',
                        );

                        foreach ( $transients as $transient ) {
                                if ( function_exists( 'delete_transient' ) ) {
                                        delete_transient( $transient );
                                }

                                if ( function_exists( 'delete_site_transient' ) ) {
                                        delete_site_transient( $transient );
                                }
                        }
                }
        }
}

