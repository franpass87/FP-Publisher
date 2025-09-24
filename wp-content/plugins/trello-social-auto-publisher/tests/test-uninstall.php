<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    define( 'WP_UNINSTALL_PLUGIN', true );
}

if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $option ) {
        $GLOBALS['tts_deleted_options'][] = $option;
        unset( $GLOBALS['tts_test_options'][ $option ] );

        return true;
    }
}

if ( ! function_exists( 'delete_site_option' ) ) {
    function delete_site_option( $option ) {
        $GLOBALS['tts_deleted_site_options'][] = $option;
        if ( isset( $GLOBALS['tts_test_site_options'][ $option ] ) ) {
            unset( $GLOBALS['tts_test_site_options'][ $option ] );
        }

        return true;
    }
}

if ( ! function_exists( 'delete_site_transient' ) ) {
    function delete_site_transient( $key ) {
        $GLOBALS['tts_deleted_site_transients'][] = $key;
        if ( isset( $GLOBALS['tts_test_site_transients'][ $key ] ) ) {
            unset( $GLOBALS['tts_test_site_transients'][ $key ] );
        }

        return true;
    }
}

if ( ! function_exists( 'is_multisite' ) ) {
    function is_multisite() {
        return ! empty( $GLOBALS['tts_is_multisite'] );
    }
}

class TTS_Uninstall_Test_WPDB extends TTS_Test_WPDB {
    /** @var array<int, string> */
    public $queries = array();

    /** @var string */
    public $options = 'wp_options';

    /** @var string */
    public $sitemeta = 'wp_sitemeta';

    public function get_col( $query ) {
        $prefix = '';
        if ( preg_match( "/LIKE\s+'([^']+)'/", $query, $matches ) ) {
            $prefix = stripslashes( $matches[1] );
        }

        if ( '' === $prefix ) {
            return array();
        }

        if ( '%' === substr( $prefix, -1 ) ) {
            $prefix = substr( $prefix, 0, -1 );
        }

        $results = array();

        foreach ( array_keys( $GLOBALS['tts_test_options'] ?? array() ) as $name ) {
            if ( 0 === strpos( $name, $prefix ) ) {
                $results[] = $name;
            }
        }

        if ( 0 === strpos( $prefix, '_transient_' ) ) {
            $transient_prefix = substr( $prefix, strlen( '_transient_' ) );
            foreach ( array_keys( $GLOBALS['tts_test_transients'] ?? array() ) as $transient_name ) {
                if ( 0 === strpos( $transient_name, $transient_prefix ) ) {
                    $results[] = '_transient_' . $transient_name;
                    $results[] = '_transient_timeout_' . $transient_name;
                }
            }
        }

        return array_unique( $results );
    }

    public function query( $query ) {
        $this->queries[] = $query;
        return true;
    }
}

$tests = array(
    'uninstall_removes_plugin_options_and_transients' => function () {
        tts_reset_test_state();

        $GLOBALS['tts_is_multisite'] = false;
        $GLOBALS['tts_test_options'] = array(
            'tts_settings'              => array( 'foo' => 'bar' ),
            'tts_daily_report_20240101' => 'report',
            'tts_quota_facebook'        => array( 'limit' => 1 ),
            'unrelated_option'          => 'keep',
        );
        $GLOBALS['tts_test_site_options'] = array(
            'tts_settings' => array( 'site' => true ),
        );
        $GLOBALS['tts_test_transients'] = array(
            'tts_rate_limit_facebook_hourly_123' => array( 'value' => 10, 'expires' => time() + 100 ),
            'tts_trello_boards_' . md5( 'demo' ) => array( 'value' => array(), 'expires' => time() + 100 ),
            'keep_transient'                     => array( 'value' => 1, 'expires' => time() + 100 ),
        );
        $GLOBALS['tts_test_site_transients'] = array(
            'tts_dashboard_stats' => array( 'value' => array(), 'expires' => time() + 100 ),
        );

        $GLOBALS['tts_deleted_options']         = array();
        $GLOBALS['tts_deleted_site_options']    = array();
        $GLOBALS['tts_deleted_site_transients'] = array();

        global $wpdb;
        $wpdb = new TTS_Uninstall_Test_WPDB();

        include __DIR__ . '/../uninstall.php';

        tts_assert_false( isset( $GLOBALS['tts_test_options']['tts_settings'] ), 'Core settings option should be removed.' );
        tts_assert_false( isset( $GLOBALS['tts_test_options']['tts_daily_report_20240101'] ), 'Historical reports should be deleted.' );
        tts_assert_false( isset( $GLOBALS['tts_test_options']['tts_quota_facebook'] ), 'Quota tracking options should be purged.' );
        tts_assert_true( isset( $GLOBALS['tts_test_options']['unrelated_option'] ), 'Unrelated options should be preserved.' );

        tts_assert_false( isset( $GLOBALS['tts_test_transients']['tts_rate_limit_facebook_hourly_123'] ), 'Rate limit transients should be removed.' );
        tts_assert_false( isset( $GLOBALS['tts_test_transients']['tts_trello_boards_' . md5( 'demo' ) ] ), 'Cached Trello boards should be deleted.' );
        tts_assert_true( isset( $GLOBALS['tts_test_transients']['keep_transient'] ), 'Non plugin transients should remain untouched.' );

        tts_assert_true( in_array( 'tts_settings', $GLOBALS['tts_deleted_site_options'], true ), 'Site options must be cleared when available.' );
        tts_assert_true( in_array( 'tts_dashboard_stats', $GLOBALS['tts_deleted_site_transients'], true ), 'Site transients must be cleared when available.' );
    },
    'uninstall_drops_all_custom_tables' => function () {
        tts_reset_test_state();

        global $wpdb;
        $wpdb = new TTS_Uninstall_Test_WPDB();

        include __DIR__ . '/../uninstall.php';

        $expected = array(
            'DROP TABLE IF EXISTS wp_tts_logs',
            'DROP TABLE IF EXISTS wp_tts_security_audit',
            'DROP TABLE IF EXISTS wp_tts_workflow_states',
            'DROP TABLE IF EXISTS wp_tts_workflow_comments',
            'DROP TABLE IF EXISTS wp_tts_content_templates',
            'DROP TABLE IF EXISTS wp_tts_team_assignments',
            'DROP TABLE IF EXISTS wp_tts_integrations',
            'DROP TABLE IF EXISTS wp_tts_integration_data',
            'DROP TABLE IF EXISTS wp_tts_cache',
            'DROP TABLE IF EXISTS wp_tts_competitors',
        );

        foreach ( $expected as $query ) {
            tts_assert_true(
                in_array( $query, $wpdb->queries, true ),
                sprintf( 'Expected uninstall to execute query: %s', $query )
            );
        }
    },
    'uninstall_clears_cron_and_deletes_posts' => function () {
        tts_reset_test_state();

        $GLOBALS['tts_test_posts'][101]     = (object) array( 'ID' => 101, 'post_type' => 'tts_social_post' );
        $GLOBALS['tts_test_posts'][55]      = (object) array( 'ID' => 55, 'post_type' => 'tts_client' );
        $GLOBALS['tts_test_client_posts'][] = (object) array( 'ID' => 55, 'post_type' => 'tts_client' );

        global $wpdb;
        $wpdb = new TTS_Uninstall_Test_WPDB();

        include __DIR__ . '/../uninstall.php';

        tts_assert_true( in_array( 101, $GLOBALS['tts_deleted_posts'], true ), 'Social posts must be force-deleted on uninstall.' );
        tts_assert_true( in_array( 55, $GLOBALS['tts_deleted_posts'], true ), 'Client posts must be force-deleted on uninstall.' );

        $cleared_hooks = array_map(
            function ( $entry ) {
                return $entry['hook'] ?? '';
            },
            $GLOBALS['tts_cleared_scheduled_hooks']
        );

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

        foreach ( $expected_hooks as $hook ) {
            tts_assert_true(
                in_array( $hook, $cleared_hooks, true ),
                sprintf( 'Expected cron hook "%s" to be cleared during uninstall.', $hook )
            );
        }

        $unscheduled_actions = array_map(
            function ( $entry ) {
                return $entry['hook'] ?? '';
            },
            $GLOBALS['tts_unscheduled_actions']
        );

        $expected_async = array(
            'tts_publish_social_post',
            'tts_integration_sync_single',
            'tts_process_channel_job',
        );

        foreach ( $expected_async as $hook ) {
            tts_assert_true(
                in_array( $hook, $unscheduled_actions, true ),
                sprintf( 'Expected Action Scheduler hook "%s" to be unscheduled.', $hook )
            );
        }
    },
);

$failures = 0;
$messages  = array();

echo "Running uninstall hardening tests\n";

foreach ( $tests as $name => $callback ) {
    try {
        $callback();
        echo '.';
    } catch ( Throwable $e ) {
        $failures++;
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
