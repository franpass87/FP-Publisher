<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';

// Ensure the plugin is loaded so hooks are registered once for the suite.
tts_reset_test_state();
require_once __DIR__ . '/../trello-social-auto-publisher.php';

$tests = array(
    'registers_textdomain_loader' => function () {
        $callbacks = $GLOBALS['tts_action_callbacks']['plugins_loaded'][5] ?? array();
        $found     = false;

        foreach ( $callbacks as $callback ) {
            if ( isset( $callback['callback'] ) && 'tsap_load_textdomain' === $callback['callback'] ) {
                $found = true;
                break;
            }
        }

        tts_assert_true( $found, 'Text domain loader should be hooked into plugins_loaded with priority 5.' );
    },
    'loads_textdomain_on_plugins_loaded' => function () {
        $GLOBALS['tts_loaded_textdomains']         = array();
        $GLOBALS['tts_loaded_plugin_textdomains']  = array();
        $GLOBALS['tts_unloaded_textdomains']       = array();

        do_action( 'plugins_loaded' );

        tts_assert_true(
            ! empty( $GLOBALS['tts_loaded_plugin_textdomains'] ),
            'Text domain loader should invoke load_plugin_textdomain.'
        );

        $plugin_entry = end( $GLOBALS['tts_loaded_plugin_textdomains'] );

        tts_assert_equals(
            'fp-publisher',
            $plugin_entry['domain'] ?? '',
            'Text domain loader should register the fp-publisher domain.'
        );

        tts_assert_equals(
            'trello-social-auto-publisher/languages/',
            $plugin_entry['path'] ?? '',
            'Text domain loader should target the plugin languages directory.'
        );

        tts_assert_contains(
            'fp-publisher',
            implode( '', $GLOBALS['tts_unloaded_textdomains'] ),
            'Existing translations should be unloaded before reloading.'
        );
    },
);

$failures = 0;
$messages = array();

echo "Running i18n tests\n";

foreach ( $tests as $name => $callback ) {
    try {
        $callback();
        echo '.';
    } catch ( Throwable $exception ) {
        $failures++;
        $messages[] = $name . ': ' . $exception->getMessage();
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
