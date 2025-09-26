<?php
/**
 * Central plugin bootstrapper responsible for loading dependencies and registering hooks.
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! class_exists( 'TTS_Plugin_Bootstrap' ) ) {
        /**
         * Coordinates plugin bootstrapping tasks and service registration.
         */
        class TTS_Plugin_Bootstrap {

                /**
                 * Singleton instance.
                 *
                 * @var self|null
                 */
                private static $instance = null;

                /**
                 * Shared service container.
                 *
                 * @var TTS_Service_Container|null
                 */
                private $container = null;

                /**
                 * Cached runtime logger instance.
                 *
                 * @var TTS_Runtime_Logger|null
                 */
                private $runtime_logger = null;

                /**
                 * Files required for core plugin functionality.
                 *
                 * @var array<int, string>
                 */
                private $core_includes = array(
                        'class-tts-secure-storage.php',
                        'class-tts-advanced-utils.php',
                        'class-tts-analytics.php',
                        'class-tts-backup.php',
                        'class-tts-cache-manager.php',
                        'class-tts-client.php',
                        'class-tts-asset-manager.php',
                        'class-tts-content-source.php',
                        'class-tts-cpt.php',
                        'class-tts-error-recovery.php',
                        'class-tts-frequency-monitor.php',
                        'class-tts-image-processor.php',
                        'class-tts-link-checker.php',
                        'class-tts-media-importer.php',
                        'class-tts-monitoring.php',
                        'class-tts-notifier.php',
                        'class-tts-performance.php',
                        'class-tts-rate-limiter.php',
                        'class-tts-channel-queue.php',
                        'class-tts-operating-contracts.php',
                        'class-tts-scheduler.php',
                        'class-tts-publisher-guard.php',
                        'class-tts-security-audit.php',
                        'class-tts-upgrades.php',
                        'class-tts-settings.php',
                        'class-tts-shortener.php',
                        'class-tts-timing.php',
                        'class-tts-token-refresh.php',
                        'class-tts-validation.php',
                        'class-tts-webhook.php',
                        'class-tts-ai-content.php',
                        'class-tts-competitor-analysis.php',
                        'class-tts-workflow-system.php',
                        'class-tts-advanced-media.php',
                        'class-tts-integration-hub.php',
                        'class-tts-cli.php',
                        'tts-logger.php',
                        'tts-notify.php',
                        'tts-template.php',
                        'publishers/class-tts-publisher-facebook-story.php',
                        'publishers/class-tts-publisher-facebook.php',
                        'publishers/class-tts-publisher-instagram-story.php',
                        'publishers/class-tts-publisher-instagram.php',
                        'publishers/class-tts-publisher-tiktok.php',
                        'publishers/class-tts-publisher-youtube.php',
                        'publishers/class-tts-publisher-blog.php',
                );

                /**
                 * Files only required when inside the WordPress admin.
                 *
                 * @var array<int, string>
                 */
                private $admin_includes = array(
                        'admin/class-tts-admin-ajax-security.php',
                        'admin/class-tts-admin-view-helper.php',
                        'admin/class-tts-admin.php',
                        'admin/class-tts-admin-menu-registry.php',
                        'admin/controllers/class-tts-admin-menu-controller.php',
                        'admin/controllers/class-tts-ajax-social-settings-controller.php',
                        'admin/controllers/class-tts-import-export-controller.php',
                        'admin/class-tts-log-page.php',
                        'admin/class-tts-calendar-page.php',
                        'admin/class-tts-analytics-page.php',
                        'admin/class-tts-health-page.php',
                        'admin/class-tts-frequency-status-page.php',
                        'admin/class-tts-frequency-dashboard-widget.php',
                        'admin/class-tts-ai-features-page.php',
                );

                /**
                 * Retrieve the shared singleton instance.
                 *
                 * @return self
                 */
                public static function instance() {
                        if ( null === self::$instance ) {
                                self::$instance = new self();
                        }

                        return self::$instance;
                }

                /**
                 * Register plugin hooks.
                 *
                 * @return void
                 */
                public function register() {
                        add_action( 'plugins_loaded', array( $this, 'boot_runtime_logger' ), 1 );
                        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 5 );
                        add_action( 'plugins_loaded', array( $this, 'bootstrap' ) );

                        add_action( 'admin_init', array( $this, 'flag_environment_issues' ) );
                        add_action( 'admin_notices', array( $this, 'display_environment_notices' ) );
                }

                /**
                 * Accessor for the shared service container.
                 *
                 * @return TTS_Service_Container
                 */
                public function container() {
                        if ( null === $this->container ) {
                                $this->container = new TTS_Service_Container();
                        }

                        return $this->container;
                }

                /**
                 * Initialise the runtime logger when enabled.
                 *
                 * @return void
                 */
                public function boot_runtime_logger() {
                        $enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;

                        if ( function_exists( 'apply_filters' ) ) {
                                $enabled = apply_filters( 'tsap_enable_runtime_logger', $enabled );
                        }

                        if ( defined( 'TSAP_DISABLE_RUNTIME_LOGGER' ) && TSAP_DISABLE_RUNTIME_LOGGER ) {
                                $enabled = false;
                        }

                        if ( ! $enabled || ! class_exists( 'TTS_Runtime_Logger' ) ) {
                                return;
                        }

                        if ( $this->runtime_logger instanceof TTS_Runtime_Logger ) {
                                return;
                        }

                        $log_file = '';

                        if ( function_exists( 'apply_filters' ) ) {
                                $log_file = apply_filters( 'tsap_runtime_log_file', $log_file );
                        }

                        $this->runtime_logger = $log_file ? new TTS_Runtime_Logger( $log_file ) : new TTS_Runtime_Logger();
                        $this->runtime_logger->register();

                        $GLOBALS['tsap_runtime_logger'] = $this->runtime_logger;
                }

                /**
                 * Load the plugin text domain for translations.
                 *
                 * @return void
                 */
                public function load_textdomain() {
                        $domain = 'fp-publisher';
                        $locale = 'en_US';

                        if ( function_exists( 'determine_locale' ) ) {
                                $locale = determine_locale();
                        } elseif ( function_exists( 'get_locale' ) ) {
                                $locale = get_locale();
                        }

                        if ( function_exists( 'apply_filters' ) ) {
                                $locale = apply_filters( 'plugin_locale', $locale, $domain );
                        }

                        if ( function_exists( 'unload_textdomain' ) ) {
                                unload_textdomain( $domain );
                        }

                        if ( defined( 'WP_LANG_DIR' ) && function_exists( 'load_textdomain' ) ) {
                                $mofile = trailingslashit( WP_LANG_DIR ) . 'plugins/' . $domain . '-' . $locale . '.mo';

                                if ( file_exists( $mofile ) ) {
                                        load_textdomain( $domain, $mofile );
                                }
                        }

                        if ( function_exists( 'load_plugin_textdomain' ) ) {
                                $relative_path = dirname( TSAP_PLUGIN_BASENAME );
                                load_plugin_textdomain( $domain, false, trailingslashit( $relative_path ) . 'languages/' );
                        }
                }

                /**
                 * Determine whether the hosting environment satisfies plugin prerequisites.
                 *
                 * @return array<int, string>
                 */
                public function get_environment_issues() {
                        return tsap_get_environment_issues();
                }

                /**
                 * Persist detected environment issues so administrators are alerted.
                 *
                 * @return void
                 */
                public function flag_environment_issues() {
                        if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'activate_plugins' ) ) {
                                return;
                        }

                        $issues = $this->get_environment_issues();

                        if ( empty( $issues ) ) {
                                return;
                        }

                        if ( function_exists( 'set_transient' ) ) {
                                $existing = get_transient( TSAP_ENVIRONMENT_ERROR_TRANSIENT );

                                if ( empty( $existing ) ) {
                                        set_transient( TSAP_ENVIRONMENT_ERROR_TRANSIENT, $issues, 10 * MINUTE_IN_SECONDS );
                                }
                        }
                }

                /**
                 * Display stored environment notices after a failed activation attempt.
                 *
                 * @return void
                 */
                public function display_environment_notices() {
                        if ( ! function_exists( 'get_transient' ) ) {
                                return;
                        }

                        $issues = get_transient( TSAP_ENVIRONMENT_ERROR_TRANSIENT );

                        if ( empty( $issues ) || ! is_array( $issues ) ) {
                                return;
                        }

                        if ( function_exists( 'delete_transient' ) ) {
                                delete_transient( TSAP_ENVIRONMENT_ERROR_TRANSIENT );
                        }

                        echo '<div class="notice notice-error"><p>' . esc_html__( 'FP Publisher cannot run because the environment does not satisfy its requirements:', 'fp-publisher' ) . '</p><ul>';

                        foreach ( $issues as $issue ) {
                                echo '<li>' . esc_html( $issue ) . '</li>';
                        }

                        echo '</ul></div>';
                }

                /**
                 * Primary plugin bootstrap executed on `plugins_loaded`.
                 *
                 * @return void
                 */
                public function bootstrap() {
                        if ( ! function_exists( 'as_schedule_single_action' ) ) {
                                add_action( 'admin_notices', array( $this, 'render_action_scheduler_notice' ) );
                                return;
                        }

                        $this->require_files( $this->core_includes );
                        $this->run_upgrade_routines();
                        $this->maybe_boot_secure_storage();
                        $this->require_rest_endpoints();
                        $this->register_default_services();
                        $this->bootstrap_core_services();

                        if ( function_exists( 'do_action' ) ) {
                                do_action( 'tsap_container_bootstrapped', $this->container() );
                        }

                        if ( is_admin() ) {
                                $this->bootstrap_admin();
                        }

                        $this->register_schedules();
                        $this->register_recurring_hooks();
                }

                /**
                 * Render the Action Scheduler dependency notice.
                 *
                 * @return void
                 */
                public function render_action_scheduler_notice() {
                        echo '<div class="error"><p>' . esc_html__( 'Action Scheduler plugin is required for FP Publisher.', 'fp-publisher' ) . '</p></div>';
                }

                /**
                 * Include plugin dependency files from a whitelist.
                 *
                 * @param array<int, string> $files Files to include.
                 *
                 * @return void
                 */
                private function require_files( array $files ) {
                        foreach ( $files as $include_file ) {
                                $file = TSAP_PLUGIN_DIR . 'includes/' . $include_file;

                                if ( file_exists( $file ) ) {
                                        require_once $file;
                                }
                        }
                }

                /**
                 * Execute upgrade routines after dependencies are loaded.
                 *
                 * @return void
                 */
                private function run_upgrade_routines() {
                        if ( ! class_exists( 'TTS_Plugin_Upgrades' ) ) {
                                return;
                        }

                        try {
                                TTS_Plugin_Upgrades::instance()->maybe_upgrade();
                        } catch ( \Throwable $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                                // The runtime logger will capture upgrade failures when enabled; avoid blocking bootstrap.
                        }
                }

                /**
                 * Ensure secure storage component is booted.
                 *
                 * @return void
                 */
                private function maybe_boot_secure_storage() {
                        if ( class_exists( 'TTS_Secure_Storage' ) ) {
                                TTS_Secure_Storage::instance();
                        }
                }

                /**
                 * Load the REST API endpoints after dependencies.
                 *
                 * @return void
                 */
                private function require_rest_endpoints() {
                        $rest_file = TSAP_PLUGIN_DIR . 'includes/class-tts-rest.php';

                        if ( file_exists( $rest_file ) ) {
                                require_once $rest_file;
                        }
                }

                /**
                 * Register default services with the container.
                 *
                 * @return void
                 */
                public function register_default_services() {
                        $container = $this->container();

                        if ( ! $container->has( 'logger' ) && class_exists( 'TTS_Logger_Service' ) ) {
                                $container->set(
                                        'logger',
                                        function () {
                                                return new TTS_Logger_Service();
                                        }
                                );
                        }

                        if ( ! $container->has( 'telemetry_channel' ) && class_exists( 'TTS_Logger_Observability_Channel' ) ) {
                                $container->set(
                                        'telemetry_channel',
                                        function () {
                                                return new TTS_Logger_Observability_Channel();
                                        }
                                );
                        }

                        if ( ! $container->has( 'credential_provisioner' ) && class_exists( 'TTS_Option_Credential_Provisioner' ) ) {
                                $container->set(
                                        'credential_provisioner',
                                        function () {
                                                return new TTS_Option_Credential_Provisioner();
                                        }
                                );
                        }

                        if ( ! $container->has( 'integration_hub' ) && class_exists( 'TTS_Integration_Hub' ) ) {
                                $container->set(
                                        'integration_hub',
                                        function ( TTS_Service_Container $c ) {
                                                return new TTS_Integration_Hub(
                                                        $c->has( 'telemetry_channel' ) ? $c->get( 'telemetry_channel' ) : null,
                                                        $c->has( 'credential_provisioner' ) ? $c->get( 'credential_provisioner' ) : null
                                                );
                                        }
                                );
                        }

                        if ( ! $container->has( 'channel_queue' ) && class_exists( 'TTS_Channel_Queue' ) ) {
                                $container->set(
                                        'channel_queue',
                                        function ( TTS_Service_Container $c ) {
                                                return new TTS_Channel_Queue( $c->get( 'integration_hub' ) );
                                        }
                                );
                        }

                        if ( ! $container->has( 'error_recovery' ) && class_exists( 'TTS_Error_Recovery' ) ) {
                                $container->set(
                                        'error_recovery',
                                        function ( TTS_Service_Container $c ) {
                                                return new TTS_Error_Recovery( $c->get( 'logger' ) );
                                        }
                                );
                        }

                        if ( ! $container->has( 'scheduler' ) && class_exists( 'TTS_Scheduler' ) ) {
                                $container->set(
                                        'scheduler',
                                        function ( TTS_Service_Container $c ) {
                                                return new TTS_Scheduler( $c->get( 'integration_hub' ) );
                                        }
                                );
                        }

                        if ( ! $container->has( 'rate_limiter' ) && class_exists( 'TTS_Rate_Limiter' ) ) {
                                $container->set(
                                        'rate_limiter',
                                        function () {
                                                return new TTS_Rate_Limiter();
                                        }
                                );
                        }

                        if ( ! $container->has( 'publisher_guard' ) && class_exists( 'TTS_Publisher_Guard' ) ) {
                                $container->set(
                                        'publisher_guard',
                                        function () {
                                                return new TTS_Publisher_Guard();
                                        }
                                );
                        }

                        if ( ! $container->has( 'security_audit' ) && class_exists( 'TTS_Security_Audit' ) ) {
                                $container->set(
                                        'security_audit',
                                        function () {
                                                return new TTS_Security_Audit();
                                        }
                                );
                        }
                }

                /**
                 * Ensure core services register their hooks.
                 *
                 * @return void
                 */
                private function bootstrap_core_services() {
                        $container = $this->container();

                        foreach ( array( 'integration_hub', 'channel_queue', 'error_recovery', 'scheduler', 'rate_limiter', 'publisher_guard', 'security_audit' ) as $service_id ) {
                                if ( $container->has( $service_id ) ) {
                                        $container->get( $service_id );
                                }
                        }
                }

                /**
                 * Load admin-only dependencies and register services.
                 *
                 * @return void
                 */
                private function bootstrap_admin() {
                        $this->require_admin_files();

                        $container = $this->container();

                        $admin_services = array(
                                'admin.ajax_security'         => function () {
                                        return new TTS_Admin_Ajax_Security( TTS_Admin::get_ajax_action_security_defaults() );
                                },
                                'admin.view_helper'           => function () {
                                        return new TTS_Admin_View_Helper();
                                },
                                'admin.controller'            => function ( $container ) {
                                        return new TTS_Admin( $container->get( 'admin.ajax_security' ), $container->get( 'admin.view_helper' ) );
                                },
                                'admin.menu_controller'       => function ( $container ) {
                                        $controller = new TTS_Admin_Menu_Controller( $container->get( 'admin.controller' ) );
                                        $controller->register_hooks();
                                        return $controller;
                                },
                                'admin.ajax_social_settings'  => function ( $container ) {
                                        $controller = new TTS_Ajax_Social_Settings_Controller(
                                                $container->get( 'admin.controller' ),
                                                $container->get( 'admin.ajax_security' )
                                        );
                                        $controller->register_hooks();
                                        return $controller;
                                },
                                'admin.import_export'         => function ( $container ) {
                                        $controller = new TTS_Import_Export_Controller(
                                                $container->get( 'admin.ajax_security' ),
                                                $container->get( 'admin.view_helper' )
                                        );
                                        $controller->register_hooks();
                                        return $controller;
                                },
                                'admin.calendar_page'         => function () {
                                        return new TTS_Calendar_Page();
                                },
                                'admin.health_page'           => function () {
                                        return new TTS_Health_Page();
                                },
                                'admin.analytics_page'        => function () {
                                        return new TTS_Analytics_Page();
                                },
                                'admin.log_page'              => function () {
                                        return new TTS_Log_Page();
                                },
                                'admin.frequency_status_page' => function () {
                                        return new TTS_Frequency_Status_Page();
                                },
                                'admin.ai_features_page'      => function () {
                                        return new TTS_AI_Features_Page();
                                },
                        );

                        foreach ( $admin_services as $service_id => $factory ) {
                                if ( ! $container->has( $service_id ) ) {
                                        $container->set( $service_id, $factory );
                                }

                                $container->get( $service_id );
                        }

                        new TTS_Content_Source();

                        add_action(
                                'admin_enqueue_scripts',
                                function ( $hook ) {
                                        if ( 'fp-publisher_page_fp-publisher-calendar' !== $hook ) {
                                                return;
                                        }

                                        TTS_Asset_Manager::enqueue_style( 'tts-calendar', 'admin/css/tts-calendar.css' );
                                        TTS_Asset_Manager::register_script( 'tts-calendar', 'admin/js/tts-calendar.js', array( 'jquery' ) );
                                        wp_enqueue_script( 'tts-calendar' );
                                }
                        );
                }

                /**
                 * Require admin-specific files.
                 *
                 * @return void
                 */
                private function require_admin_files() {
                        foreach ( $this->admin_includes as $admin_file ) {
                                $file = TSAP_PLUGIN_DIR . $admin_file;

                                if ( file_exists( $file ) ) {
                                        require_once $file;
                                }
                        }
                }

                /**
                 * Register cron schedules and events.
                 *
                 * @return void
                 */
                private function register_schedules() {
                        add_filter(
                                'cron_schedules',
                                function ( $schedules ) {
                                        if ( ! isset( $schedules['weekly'] ) ) {
                                                $schedules['weekly'] = array(
                                                        'interval' => WEEK_IN_SECONDS,
                                                        'display'  => __( 'Once Weekly', 'fp-publisher' ),
                                                );
                                        }
                                        return $schedules;
                                }
                        );

                        add_action(
                                'init',
                                function () {
                                        if ( ! wp_next_scheduled( 'tts_refresh_tokens' ) ) {
                                                wp_schedule_event( time(), 'weekly', 'tts_refresh_tokens' );
                                        }
                                }
                        );

                        add_action(
                                'init',
                                function () {
                                        if ( ! wp_next_scheduled( 'tts_fetch_metrics' ) ) {
                                                wp_schedule_event( time(), 'daily', 'tts_fetch_metrics' );
                                        }
                                }
                        );

                        add_action(
                                'init',
                                function () {
                                        if ( ! wp_next_scheduled( 'tts_check_links' ) ) {
                                                wp_schedule_event( time(), 'daily', 'tts_check_links' );
                                        }
                                }
                        );
                }

                /**
                 * Register recurring hooks for analytics, token refresh, and link checking.
                 *
                 * @return void
                 */
                private function register_recurring_hooks() {
                        add_action( 'tts_refresh_tokens', array( 'TTS_Token_Refresh', 'refresh_tokens' ) );
                        add_action( 'init', array( 'TTS_Analytics', 'register_async_hook' ) );
                        add_action( 'tts_fetch_metrics', array( 'TTS_Analytics', 'fetch_all' ) );

                        add_action(
                                'tts_check_links',
                                function () {
                                        $batch_size = (int) apply_filters( 'tts_link_check_batch_size', 75 );
                                        $batch_size = max( 1, $batch_size );
                                        $paged      = 1;

                                        do {
                                                $query = new WP_Query(
                                                        array(
                                                                'post_type'              => 'tts_social_post',
                                                                'post_status'            => 'any',
                                                                'posts_per_page'         => $batch_size,
                                                                'paged'                  => $paged,
                                                                'fields'                 => 'ids',
                                                                'orderby'                => 'ID',
                                                                'order'                  => 'ASC',
                                                                'meta_key'               => '_published_status',
                                                                'meta_value'             => 'scheduled',
                                                                'no_found_rows'          => true,
                                                                'update_post_term_cache' => false,
                                                                'update_post_meta_cache' => false,
                                                        )
                                                );

                                                if ( empty( $query->posts ) ) {
                                                        break;
                                                }

                                                foreach ( $query->posts as $post_id ) {
                                                        TTS_Link_Checker::verify_urls( (int) $post_id );
                                                }

                                                if ( count( $query->posts ) < $batch_size ) {
                                                        break;
                                                }

                                                $paged++;
                                        } while ( true );
                                }
                        );
                }
        }
}
