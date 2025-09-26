<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';
require_once __DIR__ . '/../trello-social-auto-publisher.php';

$tests = array(
	'deactivation_clears_cron_hooks'                 => function () {
		tts_reset_test_state();

		tsap_plugin_deactivate();

		$expected_hooks = array(
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

		$recorded = $GLOBALS['tts_cleared_scheduled_hooks'];

		foreach ( $expected_hooks as $hook ) {
			$found = false;
			foreach ( $recorded as $entry ) {
				if ( isset( $entry['hook'] ) && $entry['hook'] === $hook ) {
					$found = true;
					break;
				}
			}

			tts_assert_true(
				$found,
				sprintf( 'Expected deactivation to clear the "%s" cron hook.', $hook )
			);
		}
	},
	'deactivation_unschedules_action_scheduler_jobs' => function () {
		tts_reset_test_state();

		tsap_plugin_deactivate();

		$expected = array(
			'tts_publish_social_post',
			'tts_integration_sync_single',
			'tts_process_channel_job',
		);

		$hooks = array();
		foreach ( $GLOBALS['tts_unscheduled_actions'] as $entry ) {
			if ( isset( $entry['hook'] ) ) {
				$hooks[] = $entry['hook'];
			}
		}

		foreach ( $expected as $hook ) {
			tts_assert_true(
				in_array( $hook, $hooks, true ),
				sprintf( 'Expected Action Scheduler hook "%s" to be unscheduled.', $hook )
			);
		}
	},
	'deactivation_removes_plugin_roles_and_caps'     => function () {
		tts_reset_test_state();

		$GLOBALS['tts_registered_roles']['fp_publisher_manager']  = new WP_Role( 'fp_publisher_manager', array( 'tts_manage_clients' => true ) );
		$GLOBALS['tts_registered_roles']['fp_publisher_editor']   = new WP_Role( 'fp_publisher_editor', array( 'tts_publish_social_posts' => true ) );
		$GLOBALS['tts_registered_roles']['fp_publisher_reviewer'] = new WP_Role( 'fp_publisher_reviewer', array( 'tts_approve_posts' => true ) );

		$admin_role = new WP_Role( 'administrator', array( 'manage_options' => true ) );
		$admin_role->add_cap( 'tts_manage_clients' );
		$admin_role->add_cap( 'tts_publish_social_posts' );
		$admin_role->add_cap( 'tts_delete_social_posts' );
		$GLOBALS['tts_registered_roles']['administrator'] = $admin_role;

		tsap_plugin_deactivate();

		tts_assert_false( isset( $GLOBALS['tts_registered_roles']['fp_publisher_manager'] ), 'Manager role should be removed on deactivation.' );
		tts_assert_false( isset( $GLOBALS['tts_registered_roles']['fp_publisher_editor'] ), 'Editor role should be removed on deactivation.' );
		tts_assert_false( isset( $GLOBALS['tts_registered_roles']['fp_publisher_reviewer'] ), 'Reviewer role should be removed on deactivation.' );

		tts_assert_true( $admin_role->has_cap( 'manage_options' ), 'Core administrator capabilities must remain intact.' );
		tts_assert_false( $admin_role->has_cap( 'tts_manage_clients' ), 'Plugin capabilities should be removed from administrator.' );
		tts_assert_false( $admin_role->has_cap( 'tts_publish_social_posts' ), 'Publishing capability should be removed from administrator.' );
		tts_assert_false( $admin_role->has_cap( 'tts_delete_social_posts' ), 'Deletion capability should be removed from administrator.' );
	},
);

$failures = 0;
$messages = array();

echo "Running deactivation hardening tests\n";

foreach ( $tests as $name => $callback ) {
	try {
		$callback();
		echo '.';
	} catch ( Throwable $e ) {
		++$failures;
		$messages[] = $name . ': ' . $e->getMessage();
		echo 'F';
	}
}

echo "\n";

if ( $failures > 0 ) {
	foreach ( $messages as $message ) {
		echo $message . "\n";
	}
	exit( 1 );
}

echo "All tests passed\n";
