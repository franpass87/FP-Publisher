<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';

require_once __DIR__ . '/../trello-social-auto-publisher.php';

if ( ! function_exists( 'delete_option' ) ) {
        function delete_option( $option ) {
                unset( $GLOBALS['tts_test_options'][ $option ] );

                return true;
        }
}

if ( ! function_exists( 'delete_site_option' ) ) {
        function delete_site_option( $option ) {
                unset( $GLOBALS['tsap_test_network_options'][ $option ] );

                return true;
        }
}

if ( ! class_exists( 'TTS_Upgrade_Test_WPDB' ) ) {
        class TTS_Upgrade_Test_WPDB extends TTS_Test_WPDB {
                /** @var string */
                public $options = 'wp_options';

                /**
                 * Return option names matching the provided LIKE clause.
                 *
                 * @param string $query SQL query string.
                 * @return array<int, string>
                 */
                public function get_col( $query ) {
                        if ( ! preg_match( "/LIKE\s+'([^']+)'/", $query, $matches ) ) {
                                return array();
                        }

                        $pattern = stripslashes( $matches[1] );

                        if ( '%' === substr( $pattern, -1 ) ) {
                                $pattern = substr( $pattern, 0, -1 );
                        }

                        $results = array();

                        foreach ( array_keys( $GLOBALS['tts_test_options'] ?? array() ) as $name ) {
                                if ( 0 === strpos( $name, $pattern ) ) {
                                        $results[] = $name;
                                }
                        }

                        return array_unique( $results );
                }
        }
}

$tests = array(
        'upgrades_record_version_after_install' => function () {
                tts_reset_test_state();
                $GLOBALS['tsap_test_network_mode'] = false;

                if ( function_exists( 'tsap_delete_option' ) ) {
                        tsap_delete_option( TSAP_VERSION_OPTION );
                }

                if ( class_exists( 'TTS_Plugin_Upgrades' ) ) {
                        TTS_Plugin_Upgrades::reset_instance_for_tests();
                        TTS_Plugin_Upgrades::instance()->maybe_upgrade();
                }

                $stored_version = tsap_get_option( TSAP_VERSION_OPTION );

                tts_assert_equals(
                        tsap_get_plugin_version(),
                        $stored_version,
                        'The installer should persist the active plugin version.'
                );
        },
        'upgrades_migrate_network_options_and_version' => function () {
                tts_reset_test_state();
                $GLOBALS['tsap_test_network_mode'] = true;

                $GLOBALS['tts_test_options'] = array(
                        'tts_settings'       => array( 'mode' => 'site' ),
                        TSAP_VERSION_OPTION => '0.9.0',
                        'unrelated_option'   => 'preserve',
                );

                $GLOBALS['tsap_test_network_options'] = array();

                global $wpdb;
                $previous_wpdb = $wpdb;
                $wpdb          = new TTS_Upgrade_Test_WPDB();

                if ( class_exists( 'TTS_Plugin_Upgrades' ) ) {
                        TTS_Plugin_Upgrades::reset_instance_for_tests();
                        TTS_Plugin_Upgrades::instance()->maybe_upgrade();
                }

                tts_assert_true(
                        isset( $GLOBALS['tsap_test_network_options']['tts_settings'] ),
                        'Settings should migrate to the network options table during multisite upgrades.'
                );

                tts_assert_false(
                        isset( $GLOBALS['tts_test_options']['tts_settings'] ),
                        'Site-level copies should be removed once migrated.'
                );

                tts_assert_equals(
                        tsap_get_plugin_version(),
                        tsap_get_option( TSAP_VERSION_OPTION ),
                        'The stored plugin version should match the running release after migration.'
                );

                tts_assert_true(
                        isset( $GLOBALS['tsap_test_network_options'][ TSAP_VERSION_OPTION ] ),
                        'The plugin version must be stored as a network option when network mode is active.'
                );

                tts_assert_true(
                        isset( $GLOBALS['tts_test_options']['unrelated_option'] ),
                        'Options outside the plugin namespace must remain untouched.'
                );

                $wpdb = $previous_wpdb;
        },
);

foreach ( $tests as $name => $test ) {
        try {
                $test();
                echo "✅ {$name}\n";
        } catch ( Throwable $e ) {
                echo "❌ {$name}: " . $e->getMessage() . "\n";
                exit( 1 );
        }
}

