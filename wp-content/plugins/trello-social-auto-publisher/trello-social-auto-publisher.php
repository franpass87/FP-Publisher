<?php
/**
 * Plugin Name: FP Publisher
 * Plugin URI:  https://github.com/franpass87/FP-Social-Auto-Publisher
 * Description: Comprehensive multi-source content management system for automated social media publishing. Supports Trello, Google Drive, Dropbox, local uploads, and manual content creation with advanced scheduling and OAuth integration.
 * Version:     1.2.0
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

if ( ! defined( 'TSAP_VERSION' ) ) {
        define( 'TSAP_VERSION', '1.2.0' );
}

if ( ! defined( 'TSAP_VERSION_OPTION' ) ) {
        define( 'TSAP_VERSION_OPTION', 'tts_plugin_version' );
}

if ( ! defined( 'TSAP_PLUGIN_BASENAME' ) ) {
        if ( function_exists( 'plugin_basename' ) ) {
                define( 'TSAP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
        } else {
                define( 'TSAP_PLUGIN_BASENAME', basename( __DIR__ ) . '/trello-social-auto-publisher.php' );
	}
}

if ( ! defined( 'TSAP_ENVIRONMENT_ERROR_TRANSIENT' ) ) {
        define( 'TSAP_ENVIRONMENT_ERROR_TRANSIENT', 'tsap_activation_errors' );
}

if ( ! function_exists( 'tsap_get_plugin_version' ) ) {
        /**
         * Retrieve the active plugin version string.
         *
         * @return string
         */
        function tsap_get_plugin_version() {
                return defined( 'TSAP_VERSION' ) ? TSAP_VERSION : '1.2.0';
        }
}

if ( ! function_exists( 'tsap_is_network_mode' ) ) {
	/**
	 * Determine whether plugin settings should be managed network-wide.
	 *
	 * @return bool
	 */
	function tsap_is_network_mode() {
		if ( ! function_exists( 'is_multisite' ) || ! is_multisite() ) {
			return false;
		}

		if ( defined( 'TSAP_FORCE_NETWORK_MODE' ) ) {
			return (bool) TSAP_FORCE_NETWORK_MODE;
		}

		static $network_active = null;

		if ( null !== $network_active ) {
			return $network_active;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) && defined( 'ABSPATH' ) ) {
			$plugin_file = ABSPATH . 'wp-admin/includes/plugin.php';

			if ( file_exists( $plugin_file ) ) {
				require_once $plugin_file;
			}
		}

		$network_active = function_exists( 'is_plugin_active_for_network' )
			? is_plugin_active_for_network( TSAP_PLUGIN_BASENAME )
			: false;

		return $network_active;
	}
}

if ( ! function_exists( 'tsap_option_uses_network_storage' ) ) {
	/**
	 * Check whether the provided option should leverage the network options table.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	function tsap_option_uses_network_storage( $option ) {
		$is_network_option = 0 === strpos( (string) $option, 'tts_' );

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter whether a specific option should be stored network-wide.
			 *
			 * @param bool   $is_network_option Whether the option is considered network managed.
			 * @param string $option            Option name.
			 */
			$is_network_option = (bool) apply_filters( 'tsap_is_network_option', $is_network_option, $option );
		}

		return $is_network_option;
	}
}

if ( ! function_exists( 'tsap_should_use_network_option' ) ) {
	/**
	 * Determine if network option helpers should be used for the provided option.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	function tsap_should_use_network_option( $option ) {
		return tsap_is_network_mode() && tsap_option_uses_network_storage( $option );
	}
}

if ( ! function_exists( 'tsap_get_option' ) ) {
	/**
	 * Retrieve a plugin option with multisite awareness.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value if the option does not exist.
	 * @return mixed
	 */
	function tsap_get_option( $option, $default = false ) {
		if ( tsap_should_use_network_option( $option ) && function_exists( 'get_site_option' ) ) {
			$sentinel = new stdClass();
			$value    = get_site_option( $option, $sentinel );

			if ( $value !== $sentinel ) {
				return $value;
			}
		}

		return get_option( $option, $default );
	}
}

if ( ! function_exists( 'tsap_update_option' ) ) {
	/**
	 * Update a plugin option with multisite awareness.
	 *
	 * @param string $option   Option name.
	 * @param mixed  $value    Option value.
	 * @param mixed  $autoload Optional. Whether to autoload the option (single-site only).
	 * @return bool
	 */
	function tsap_update_option( $option, $value, $autoload = null ) {
		if ( tsap_should_use_network_option( $option ) && function_exists( 'update_site_option' ) ) {
			$updated = update_site_option( $option, $value );

			if ( $updated && function_exists( 'delete_option' ) ) {
				delete_option( $option );
			}

			return $updated;
		}

		if ( null === $autoload ) {
			return update_option( $option, $value );
		}

		return update_option( $option, $value, $autoload );
	}
}

if ( ! function_exists( 'tsap_delete_option' ) ) {
	/**
	 * Delete a plugin option with multisite awareness.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	function tsap_delete_option( $option ) {
		if ( tsap_should_use_network_option( $option ) && function_exists( 'delete_site_option' ) ) {
			$deleted_network = delete_site_option( $option );
			$deleted_site    = function_exists( 'delete_option' ) ? delete_option( $option ) : false;

			return $deleted_network || $deleted_site;
		}

		return function_exists( 'delete_option' ) ? delete_option( $option ) : false;
	}
}

require_once TSAP_PLUGIN_DIR . 'includes/class-tts-service-container.php';
require_once TSAP_PLUGIN_DIR . 'includes/class-tts-runtime-logger.php';
require_once TSAP_PLUGIN_DIR . 'includes/class-tts-upgrades.php';
require_once TSAP_PLUGIN_DIR . 'includes/class-tts-plugin-bootstrap.php';

if ( ! function_exists( 'tsap_boot_runtime_logger' ) ) {
        /**
         * Initialize the development runtime logger when enabled.
         *
         * @return void
         */
        function tsap_boot_runtime_logger() {
                TTS_Plugin_Bootstrap::instance()->boot_runtime_logger();
        }
}

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
		TTS_Plugin_Bootstrap::instance()->display_environment_notices();
	}
}

if ( ! function_exists( 'tsap_flag_environment_issues' ) ) {
	/**
	 * Persist detected environment issues so administrators are alerted.
	 *
	 * @return void
	 */
	function tsap_flag_environment_issues() {
		TTS_Plugin_Bootstrap::instance()->flag_environment_issues();
	}
}

if ( ! function_exists( 'tsap_load_textdomain' ) ) {
        /**
         * Load the plugin text domain for translations.
         *
         * @return void
         */
        function tsap_load_textdomain() {
                TTS_Plugin_Bootstrap::instance()->load_textdomain();
        }
}

if ( ! function_exists( 'tsap_service_container' ) ) {
        /**
         * Retrieve the shared service container instance.
         *
         * @return TTS_Service_Container
         */
        function tsap_service_container() {
                return TTS_Plugin_Bootstrap::instance()->container();
        }
}

if ( ! function_exists( 'tsap_register_default_services' ) ) {
        /**
         * Register core services with the container.
         *
         * @return void
         */
        function tsap_register_default_services() {
                TTS_Plugin_Bootstrap::instance()->register_default_services();
        }
}

TTS_Plugin_Bootstrap::instance()->register();

if ( ! function_exists( 'add_action' ) ) {
        TTS_Plugin_Bootstrap::instance()->boot_runtime_logger();
        TTS_Plugin_Bootstrap::instance()->load_textdomain();
        TTS_Plugin_Bootstrap::instance()->bootstrap();
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

        if ( class_exists( 'TTS_Plugin_Upgrades' ) ) {
                if ( defined( 'TSAP_RUNNING_PHPUNIT' ) && method_exists( 'TTS_Plugin_Upgrades', 'reset_instance_for_tests' ) ) {
                        TTS_Plugin_Upgrades::reset_instance_for_tests();
                }

                TTS_Plugin_Upgrades::instance()->maybe_upgrade();
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

add_action(
	'plugins_loaded',
	function () {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="error"><p>' .
					esc_html__( 'Action Scheduler plugin is required for FP Publisher.', 'fp-publisher' ) .
					'</p></div>';
				}
			);
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
			require_once TSAP_PLUGIN_DIR . 'admin/class-tts-admin-ajax-security.php';
			require_once TSAP_PLUGIN_DIR . 'admin/class-tts-admin-view-helper.php';
                    require_once TSAP_PLUGIN_DIR . 'admin/class-tts-admin.php';
                    require_once TSAP_PLUGIN_DIR . 'admin/class-tts-admin-menu-registry.php';
			require_once TSAP_PLUGIN_DIR . 'admin/controllers/class-tts-admin-menu-controller.php';
			require_once TSAP_PLUGIN_DIR . 'admin/controllers/class-tts-ajax-social-settings-controller.php';
			require_once TSAP_PLUGIN_DIR . 'admin/controllers/class-tts-import-export-controller.php';
			require_once TSAP_PLUGIN_DIR . 'admin/class-tts-log-page.php';
			require_once TSAP_PLUGIN_DIR . 'admin/class-tts-calendar-page.php';
			require_once TSAP_PLUGIN_DIR . 'admin/class-tts-analytics-page.php';
			require_once TSAP_PLUGIN_DIR . 'admin/class-tts-health-page.php';
			require_once TSAP_PLUGIN_DIR . 'admin/class-tts-frequency-status-page.php';
			require_once TSAP_PLUGIN_DIR . 'admin/class-tts-frequency-dashboard-widget.php';

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
				$container->set(
					'admin.ai_features_page',
					function () {
						return new TTS_AI_Features_Page();
					}
				);
			}

			$container->get( 'admin.ai_features_page' );

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

		// Add a weekly cron schedule.
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

		// Schedule weekly token refreshes.
		add_action(
			'init',
			function () {
				if ( ! wp_next_scheduled( 'tts_refresh_tokens' ) ) {
					wp_schedule_event( time(), 'weekly', 'tts_refresh_tokens' );
				}
			}
		);

		// Attach the refresh action to the token refresh handler.
		add_action( 'tts_refresh_tokens', array( 'TTS_Token_Refresh', 'refresh_tokens' ) );

		// Register async analytics processor.
		add_action( 'init', array( 'TTS_Analytics', 'register_async_hook' ) );

		// Schedule daily metrics fetching.
		add_action(
			'init',
			function () {
				if ( ! wp_next_scheduled( 'tts_fetch_metrics' ) ) {
					wp_schedule_event( time(), 'daily', 'tts_fetch_metrics' );
				}
			}
		);

		// Hook the analytics fetcher.
		add_action( 'tts_fetch_metrics', array( 'TTS_Analytics', 'fetch_all' ) );

		// Schedule daily link checks.
		add_action(
			'init',
			function () {
				if ( ! wp_next_scheduled( 'tts_check_links' ) ) {
					wp_schedule_event( time(), 'daily', 'tts_check_links' );
				}
			}
		);

		// Hook the link checker with batching to avoid large queries.
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
);
