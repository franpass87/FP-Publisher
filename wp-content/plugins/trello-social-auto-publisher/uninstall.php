<?php
/**
 * Uninstall cleanup for FP Publisher.
 *
 * @package FPPublisher
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Clear scheduled events registered by the plugin.
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

if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
	foreach ( $scheduled_hooks as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
}

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	$action_scheduler_hooks = array(
		'tts_publish_social_post',
		'tts_integration_sync_single',
		'tts_process_channel_job',
		'tts_fetch_post_metrics',
	);

	foreach ( $action_scheduler_hooks as $hook ) {
		as_unschedule_all_actions( $hook );
	}
}

// Remove custom post types managed by the plugin.
if ( function_exists( 'get_posts' ) && function_exists( 'wp_delete_post' ) ) {
	$post_types = array( 'tts_social_post', 'tts_client' );

	foreach ( $post_types as $post_type ) {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'numberposts'    => -1,
				'posts_per_page' => -1,
			)
		);

		if ( empty( $posts ) ) {
			continue;
		}

		foreach ( $posts as $post ) {
			$post_id = 0;

			if ( is_object( $post ) && isset( $post->ID ) ) {
				$post_id = (int) $post->ID;
			} elseif ( is_numeric( $post ) ) {
				$post_id = (int) $post;
			}

			if ( $post_id > 0 ) {
				wp_delete_post( $post_id, true );
			}
		}
	}
}

// Remove custom roles and capabilities.
if ( ! class_exists( 'TTS_CPT' ) ) {
	$cpt_file = __DIR__ . '/includes/class-tts-cpt.php';
	if ( file_exists( $cpt_file ) ) {
		require_once $cpt_file;
	}
}

if ( class_exists( 'TTS_CPT' ) && method_exists( 'TTS_CPT', 'remove_roles' ) ) {
	TTS_CPT::remove_roles();
}

// Delete plugin specific options.
$options_to_delete = array(
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
        'tts_plugin_version',
);

foreach ( $options_to_delete as $option_name ) {
	if ( function_exists( 'delete_option' ) ) {
		delete_option( $option_name );
	}

	if ( function_exists( 'delete_site_option' ) ) {
		delete_site_option( $option_name );
	}
}

// Remove option rows stored under known prefixes.
if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_col' ) ) {
	$option_table    = property_exists( $wpdb, 'options' ) ? $wpdb->options : $wpdb->prefix . 'options';
	$option_prefixes = array(
		'tts_daily_report_',
		'tts_quota_',
	);

	foreach ( $option_prefixes as $prefix ) {
		$like = $wpdb->esc_like( $prefix ) . '%';
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$option_table} WHERE option_name LIKE %s", $like ) );

		if ( empty( $rows ) ) {
			continue;
		}

		foreach ( array_unique( $rows ) as $option_name ) {
			if ( function_exists( 'delete_option' ) ) {
				delete_option( $option_name );
			}

			if ( function_exists( 'delete_site_option' ) ) {
				delete_site_option( $option_name );
			}
		}
	}
}

// Clear transients managed by the plugin.
$transient_keys = array(
	'tts_dashboard_stats',
	'tts_performance_metrics',
	'tts_active_channels_stats',
	'tts_success_rate_stats',
	'tts_trend_data',
	'tts_active_channels',
	'tts_success_rate',
	'tts_system_health',
);

foreach ( $transient_keys as $transient ) {
	if ( function_exists( 'delete_transient' ) ) {
		delete_transient( $transient );
	}

	if ( function_exists( 'delete_site_transient' ) ) {
		delete_site_transient( $transient );
	}
}

if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_col' ) ) {
	$option_table       = property_exists( $wpdb, 'options' ) ? $wpdb->options : $wpdb->prefix . 'options';
	$transient_prefixes = array(
		'tts_rate_limit_',
		'tts_emergency_throttle_',
		'tts_critical_throttle_',
		'tts_warning_throttle_',
		'tts_oauth_',
		'tts_trello_boards_',
	);

	foreach ( $transient_prefixes as $prefix ) {
		$patterns = array(
			'_transient_' . $prefix,
			'_transient_timeout_' . $prefix,
		);

		$transient_names = array();

		foreach ( $patterns as $pattern ) {
			$like  = $wpdb->esc_like( $pattern ) . '%';
			$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$option_table} WHERE option_name LIKE %s", $like ) );
			if ( ! empty( $names ) ) {
				$transient_names = array_merge( $transient_names, $names );
			}
		}

		if ( empty( $transient_names ) ) {
			continue;
		}

		foreach ( array_unique( $transient_names ) as $option_name ) {
			if ( 0 === strpos( $option_name, '_transient_timeout_' ) ) {
				$option_name = substr( $option_name, strlen( '_transient_timeout_' ) );
			} elseif ( 0 === strpos( $option_name, '_transient_' ) ) {
				$option_name = substr( $option_name, strlen( '_transient_' ) );
			}

			if ( '' === $option_name ) {
				continue;
			}

			if ( function_exists( 'delete_transient' ) ) {
				delete_transient( $option_name );
			}

			if ( function_exists( 'delete_site_transient' ) ) {
				delete_site_transient( $option_name );
			}
		}
	}
}

// Drop custom database tables.
if ( isset( $wpdb ) && is_object( $wpdb ) ) {
	$tables = array(
		'tts_logs',
		'tts_security_audit',
		'tts_workflow_states',
		'tts_workflow_comments',
		'tts_content_templates',
		'tts_team_assignments',
		'tts_integrations',
		'tts_integration_data',
		'tts_cache',
		'tts_competitors',
	);

	foreach ( $tables as $table ) {
		$table_name = $wpdb->prefix . $table;

		if ( preg_match( '/^[A-Za-z0-9_]+$/', $table_name ) ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		}
	}
}
