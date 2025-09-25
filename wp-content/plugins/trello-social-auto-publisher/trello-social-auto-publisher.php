<?php
/**
 * Plugin Name: FP Publisher
 * Plugin URI:  https://github.com/franpass87/FP-Social-Auto-Publisher
 * Description: Comprehensive multi-source content management system for automated social media publishing. Supports Trello, Google Drive, Dropbox, local uploads, and manual content creation with advanced scheduling and OAuth integration.
 * Version:     1.0.1
 * Author:      Francesco Passeri
 * Author URI:  https://francescopasseri.com
 * Text Domain: fp-publisher
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin directory.
if ( ! defined( 'TSAP_PLUGIN_DIR' ) ) {
    define( 'TSAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'TSAP_ENVIRONMENT_ERROR_TRANSIENT' ) ) {
    define( 'TSAP_ENVIRONMENT_ERROR_TRANSIENT', 'tsap_activation_errors' );
}

require_once TSAP_PLUGIN_DIR . 'includes/class-tts-service-container.php';

if ( ! function_exists( 'tsap_get_environment_issues' ) ) {
    /**
     * Determine whether the hosting environment satisfies plugin prerequisites.
     *
     * @return array<int, string> List of human readable issues. Empty array when compliant.
     */
    function tsap_get_environment_issues() {
        $issues = array();

        if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
            /* translators: %s: current PHP version */
            $issues[] = sprintf( __( 'FP Publisher requires PHP 8.1 or higher. Current version: %s.', 'fp-publisher' ), PHP_VERSION );
        }

        $wp_version = null;

        if ( function_exists( 'get_bloginfo' ) ) {
            $wp_version = get_bloginfo( 'version' );
        } elseif ( isset( $GLOBALS['wp_version'] ) ) {
            $wp_version = $GLOBALS['wp_version'];
        }

        if ( $wp_version && version_compare( $wp_version, '6.1', '<' ) ) {
            /* translators: %s: current WordPress version */
            $issues[] = sprintf( __( 'FP Publisher requires WordPress 6.1 or higher. Current version: %s.', 'fp-publisher' ), $wp_version );
        }

        $required_extensions = array(
            'curl'     => __( 'The cURL PHP extension must be enabled to communicate with external services.', 'fp-publisher' ),
            'json'     => __( 'The JSON PHP extension must be enabled to encode and decode API responses.', 'fp-publisher' ),
            'mbstring' => __( 'The Mbstring PHP extension must be enabled to handle multibyte content safely.', 'fp-publisher' ),
            'openssl'  => __( 'The OpenSSL PHP extension must be enabled to secure stored credentials.', 'fp-publisher' ),
        );

        foreach ( $required_extensions as $extension => $message ) {
            $supported = extension_loaded( $extension );

            if ( 'openssl' === $extension ) {
                $supported = $supported && function_exists( 'openssl_cipher_iv_length' );
            }

            if ( function_exists( 'apply_filters' ) ) {
                /**
                 * Allow overriding extension availability checks.
                 *
                 * This is primarily used for integration tests where PHP extensions
                 * cannot be toggled dynamically.
                 *
                 * @param bool   $supported Whether the extension is considered available.
                 * @param string $extension Extension name being evaluated.
                 */
                $supported = apply_filters( 'tsap_extension_supported', $supported, $extension );
            }

            if ( ! $supported ) {
                $issues[] = $message;
            }
        }

        if ( ! function_exists( 'as_schedule_single_action' ) ) {
            $issues[] = __( 'The Action Scheduler library must be installed and active.', 'fp-publisher' );
        }

        if ( function_exists( 'apply_filters' ) ) {
            /**
             * Allow third parties to add custom environment validation rules.
             *
             * @param array<int, string> $issues Detected issues.
             */
            $issues = apply_filters( 'tsap_environment_issues', $issues );
        }

        return array_values( array_filter( array_map( 'wp_strip_all_tags', $issues ) ) );
    }
}

if ( ! function_exists( 'tsap_abort_activation_with_issues' ) ) {
    /**
     * Abort plugin activation when requirements are not satisfied.
     *
     * @param array<int, string> $issues Requirement violations to display.
     *
     * @return void
     */
    function tsap_abort_activation_with_issues( array $issues ) {
        if ( empty( $issues ) ) {
            return;
        }

        if ( function_exists( 'set_transient' ) ) {
            set_transient( TSAP_ENVIRONMENT_ERROR_TRANSIENT, $issues, 10 * MINUTE_IN_SECONDS );
        }

        if ( ! function_exists( 'deactivate_plugins' ) && defined( 'ABSPATH' ) ) {
            $plugin_file = ABSPATH . 'wp-admin/includes/plugin.php';

            if ( file_exists( $plugin_file ) ) {
                require_once $plugin_file;
            }
        }

        if ( function_exists( 'deactivate_plugins' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
        }

        $list_items = '';

        foreach ( $issues as $issue ) {
            $list_items .= '<li>' . esc_html( $issue ) . '</li>';
        }

        $message  = '<p>' . esc_html__( 'FP Publisher cannot be activated because your environment does not meet the minimum requirements.', 'fp-publisher' ) . '</p>';
        $message .= '<ul>' . $list_items . '</ul>';

        wp_die(
            wp_kses_post( $message ),
            esc_html__( 'Plugin activation error', 'fp-publisher' ),
            array( 'back_link' => true )
        );
    }
}

if ( ! function_exists( 'tsap_display_environment_notices' ) ) {
    /**
     * Display stored environment notices after a failed activation attempt.
     *
     * @return void
     */
    function tsap_display_environment_notices() {
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

    if ( function_exists( 'add_action' ) ) {
        add_action( 'admin_notices', 'tsap_display_environment_notices' );
    }
}

if ( ! function_exists( 'tsap_flag_environment_issues' ) ) {
    /**
     * Persist detected environment issues so administrators are alerted.
     *
     * @return void
     */
    function tsap_flag_environment_issues() {
        if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        $issues = tsap_get_environment_issues();

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

    if ( function_exists( 'add_action' ) ) {
        add_action( 'admin_init', 'tsap_flag_environment_issues' );
    }
}

if ( ! function_exists( 'tsap_load_textdomain' ) ) {
    /**
     * Load the plugin text domain for translations.
     *
     * @return void
     */
    function tsap_load_textdomain() {
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
            load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
        }
    }

    add_action( 'plugins_loaded', 'tsap_load_textdomain', 5 );
}

if ( ! function_exists( 'tsap_service_container' ) ) {
    /**
     * Retrieve the shared service container instance.
     *
     * @return TTS_Service_Container
     */
    function tsap_service_container() {
        static $container = null;

        if ( null === $container ) {
            $container = new TTS_Service_Container();
        }

        return $container;
    }
}

if ( ! function_exists( 'tsap_register_default_services' ) ) {
    /**
     * Register core services with the container.
     *
     * @return void
     */
    function tsap_register_default_services() {
        $container = tsap_service_container();

        if ( ! $container->has( 'logger' ) && class_exists( 'TTS_Logger_Service' ) ) {
            $container->set( 'logger', function () {
                return new TTS_Logger_Service();
            } );
        }

        if ( ! $container->has( 'telemetry_channel' ) && class_exists( 'TTS_Logger_Observability_Channel' ) ) {
            $container->set( 'telemetry_channel', function () {
                return new TTS_Logger_Observability_Channel();
            } );
        }

        if ( ! $container->has( 'credential_provisioner' ) && class_exists( 'TTS_Option_Credential_Provisioner' ) ) {
            $container->set( 'credential_provisioner', function () {
                return new TTS_Option_Credential_Provisioner();
            } );
        }

        if ( ! $container->has( 'integration_hub' ) && class_exists( 'TTS_Integration_Hub' ) ) {
            $container->set( 'integration_hub', function ( TTS_Service_Container $c ) {
                $provisioner = $c->has( 'credential_provisioner' ) ? $c->get( 'credential_provisioner' ) : null;
                $telemetry   = $c->has( 'telemetry_channel' ) ? $c->get( 'telemetry_channel' ) : null;

                return new TTS_Integration_Hub( $provisioner, $telemetry );
            } );
        }

        if ( ! $container->has( 'rate_limiter' ) && class_exists( 'TTS_Rate_Limiter' ) ) {
            $container->set( 'rate_limiter', function ( TTS_Service_Container $c ) {
                return new TTS_Rate_Limiter( $c->get( 'logger' ) );
            } );
        }

        if ( ! $container->has( 'error_recovery' ) && class_exists( 'TTS_Error_Recovery' ) ) {
            $container->set( 'error_recovery', function () {
                return new TTS_Error_Recovery();
            } );
        }

        if ( ! $container->has( 'channel_queue' ) && class_exists( 'TTS_Channel_Queue' ) ) {
            $container->set( 'channel_queue', function () {
                return new TTS_Channel_Queue();
            } );
        }

        if ( ! $container->has( 'publisher_guard' ) && class_exists( 'TTS_Publisher_Guard' ) ) {
            $container->set( 'publisher_guard', function ( TTS_Service_Container $c ) {
                return new TTS_Publisher_Guard( $c->get( 'rate_limiter' ), $c->get( 'error_recovery' ) );
            } );
        }

        if ( ! $container->has( 'scheduler' ) && class_exists( 'TTS_Scheduler' ) ) {
            $container->set( 'scheduler', function ( TTS_Service_Container $c ) {
                $integration = $c->get( 'integration_hub' );
                $telemetry   = $c->has( 'telemetry_channel' ) ? $c->get( 'telemetry_channel' ) : null;
                $queue       = $c->has( 'channel_queue' ) ? $c->get( 'channel_queue' ) : null;
                $limiter     = $c->has( 'rate_limiter' ) ? $c->get( 'rate_limiter' ) : null;
                $recovery    = $c->has( 'error_recovery' ) ? $c->get( 'error_recovery' ) : null;
                $guard       = $c->has( 'publisher_guard' ) ? $c->get( 'publisher_guard' ) : null;

                return new TTS_Scheduler( $integration, $telemetry, $queue, $limiter, $recovery, $guard );
            } );
        }

        if ( ! $container->has( 'security_audit' ) && class_exists( 'TTS_Security_Audit' ) ) {
            $container->set( 'security_audit', function () {
                return new TTS_Security_Audit();
            } );
        }

        if ( function_exists( 'do_action' ) ) {
            do_action( 'tsap_container_registered', $container );
        }
    }
}

register_activation_hook( __FILE__, 'tsap_plugin_activate' );
register_deactivation_hook( __FILE__, 'tsap_plugin_deactivate' );

/**
 * Handle plugin activation tasks.
 */
function tsap_plugin_activate() {
    $issues = tsap_get_environment_issues();

    if ( ! empty( $issues ) ) {
        tsap_abort_activation_with_issues( $issues );
        return;
    }

    require_once TSAP_PLUGIN_DIR . 'includes/tts-logger.php';

    if ( function_exists( 'tts_create_logs_table' ) ) {
        tts_create_logs_table();
    }

    require_once TSAP_PLUGIN_DIR . 'includes/class-tts-security-audit.php';

    if ( class_exists( 'TTS_Security_Audit' ) ) {
        TTS_Security_Audit::activate();
    }

    require_once TSAP_PLUGIN_DIR . 'includes/class-tts-workflow-system.php';

    if ( class_exists( 'TTS_Workflow_System' ) ) {
        TTS_Workflow_System::install();
    }

    require_once TSAP_PLUGIN_DIR . 'includes/class-tts-integration-hub.php';

    if ( class_exists( 'TTS_Integration_Hub' ) ) {
        TTS_Integration_Hub::install();
    }
}

/**
 * Handle plugin deactivation tasks.
 */
function tsap_plugin_deactivate() {
    $scheduled_hooks = array(
        'tts_refresh_tokens',
        'tts_fetch_metrics',
        'tts_check_links',
        'tts_daily_competitor_analysis',
        'tts_check_publishing_frequencies',
        'tts_hourly_rate_limit_cleanup',
        'tts_process_retry_queue',
        'tts_database_cleanup',
        'tts_weekly_cleanup',
        'tts_hourly_cache_cleanup',
        'tts_daily_backup',
        'tts_hourly_health_check',
        'tts_daily_system_report',
        'tts_daily_security_cleanup',
        'tts_integration_sync',
        'tts_integration_sync_single',
        'tts_purge_old_logs',
    );

    foreach ( $scheduled_hooks as $hook ) {
        if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
            wp_clear_scheduled_hook( $hook );
        }
    }

    $channel_queue_file = TSAP_PLUGIN_DIR . 'includes/class-tts-channel-queue.php';
    if ( ! class_exists( 'TTS_Channel_Queue' ) && file_exists( $channel_queue_file ) ) {
        require_once $channel_queue_file;
    }

    if ( function_exists( 'as_unschedule_all_actions' ) ) {
        as_unschedule_all_actions( 'tts_publish_social_post' );
        as_unschedule_all_actions( 'tts_integration_sync_single' );

        if ( class_exists( 'TTS_Channel_Queue' ) ) {
            as_unschedule_all_actions( TTS_Channel_Queue::ACTION_HOOK );
        }
    }

    $cpt_file = TSAP_PLUGIN_DIR . 'includes/class-tts-cpt.php';
    if ( ! class_exists( 'TTS_CPT' ) && file_exists( $cpt_file ) ) {
        require_once $cpt_file;
    }

    if ( class_exists( 'TTS_CPT' ) && is_callable( array( 'TTS_CPT', 'remove_roles' ) ) ) {
        TTS_CPT::remove_roles();
    }
}

add_action( 'plugins_loaded', function () {
    if ( ! function_exists( 'as_schedule_single_action' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="error"><p>' .
                 esc_html__( 'Action Scheduler plugin is required for FP Publisher.', 'fp-publisher' ) .
                 '</p></div>';
        } );
        return;
    }

    // Load support files from the includes directory using whitelist for security.
    $tsap_includes = array(
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
    
    foreach ( $tsap_includes as $include_file ) {
        $file = TSAP_PLUGIN_DIR . 'includes/' . $include_file;
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }

    if ( class_exists( 'TTS_Secure_Storage' ) ) {
        TTS_Secure_Storage::instance();
    }

    // Load REST API endpoints after other includes.
    require_once TSAP_PLUGIN_DIR . 'includes/class-tts-rest.php';

    tsap_register_default_services();

    $container = tsap_service_container();

    // Ensure core services are bootstrapped so their hooks are registered.
    foreach ( array( 'integration_hub', 'channel_queue', 'error_recovery', 'scheduler', 'rate_limiter', 'publisher_guard', 'security_audit' ) as $service_id ) {
        if ( $container->has( $service_id ) ) {
            $container->get( $service_id );
        }
    }

    if ( function_exists( 'do_action' ) ) {
        do_action( 'tsap_container_bootstrapped', $container );
    }

    // Load admin files when in the dashboard.
    if ( is_admin() ) {
        require_once TSAP_PLUGIN_DIR . 'admin/class-tts-admin.php';
        require_once TSAP_PLUGIN_DIR . 'admin/class-tts-log-page.php';
        require_once TSAP_PLUGIN_DIR . 'admin/class-tts-calendar-page.php';
        require_once TSAP_PLUGIN_DIR . 'admin/class-tts-analytics-page.php';
        require_once TSAP_PLUGIN_DIR . 'admin/class-tts-health-page.php';
        require_once TSAP_PLUGIN_DIR . 'admin/class-tts-frequency-status-page.php';
        require_once TSAP_PLUGIN_DIR . 'admin/class-tts-frequency-dashboard-widget.php';

        $admin_services = array(
            'admin.controller'            => function () {
                return new TTS_Admin();
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
        );

        foreach ( $admin_services as $service_id => $factory ) {
            if ( ! $container->has( $service_id ) ) {
                $container->set( $service_id, $factory );
            }

            $container->get( $service_id );
        }

        // Initialize content source management
        new TTS_Content_Source();

        // Load AI Features page
        require_once TSAP_PLUGIN_DIR . 'admin/class-tts-ai-features-page.php';
        if ( ! $container->has( 'admin.ai_features_page' ) ) {
            $container->set( 'admin.ai_features_page', function () {
                return new TTS_AI_Features_Page();
            } );
        }

        $container->get( 'admin.ai_features_page' );

        add_action( 'admin_enqueue_scripts', function( $hook ) {
            if ( 'fp-publisher_page_fp-publisher-calendar' !== $hook ) {
                return;
            }

            TTS_Asset_Manager::enqueue_style( 'tts-calendar', 'admin/css/tts-calendar.css' );
            TTS_Asset_Manager::register_script( 'tts-calendar', 'admin/js/tts-calendar.js', array( 'jquery' ) );
            wp_enqueue_script( 'tts-calendar' );
        } );
    }

    // Add a weekly cron schedule.
    add_filter( 'cron_schedules', function( $schedules ) {
        if ( ! isset( $schedules['weekly'] ) ) {
            $schedules['weekly'] = array(
                'interval' => WEEK_IN_SECONDS,
                'display'  => __( 'Once Weekly', 'fp-publisher' ),
            );
        }
        return $schedules;
    } );

    // Schedule weekly token refreshes.
    add_action( 'init', function () {
        if ( ! wp_next_scheduled( 'tts_refresh_tokens' ) ) {
            wp_schedule_event( time(), 'weekly', 'tts_refresh_tokens' );
        }
    } );

    // Attach the refresh action to the token refresh handler.
    add_action( 'tts_refresh_tokens', array( 'TTS_Token_Refresh', 'refresh_tokens' ) );

    // Schedule daily metrics fetching.
    add_action( 'init', function () {
        if ( ! wp_next_scheduled( 'tts_fetch_metrics' ) ) {
            wp_schedule_event( time(), 'daily', 'tts_fetch_metrics' );
        }
    } );

    // Hook the analytics fetcher.
    add_action( 'tts_fetch_metrics', array( 'TTS_Analytics', 'fetch_all' ) );

    // Schedule daily link checks.
    add_action( 'init', function () {
        if ( ! wp_next_scheduled( 'tts_check_links' ) ) {
            wp_schedule_event( time(), 'daily', 'tts_check_links' );
        }
    } );

    // Hook the link checker.
    add_action( 'tts_check_links', function () {
        $posts = get_posts(
            array(
                'post_type'      => 'tts_social_post',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => '_published_status',
                'meta_value'     => 'scheduled',
            )
        );

        foreach ( $posts as $post_id ) {
            TTS_Link_Checker::verify_urls( $post_id );
        }
    } );
} );
