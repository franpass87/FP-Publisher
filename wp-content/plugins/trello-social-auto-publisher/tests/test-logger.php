<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';
require_once __DIR__ . '/../includes/tts-logger.php';

$tests = array(
    'normalize_component_name_handles_prefixes_and_namespaces' => function () {
        tts_reset_test_state();

        $normalizer = new ReflectionMethod( TTS_Logger::class, 'normalize_component_name' );
        $normalizer->setAccessible( true );

        tts_assert_equals(
            'admin',
            $normalizer->invoke( null, 'TTS_Admin' ),
            'Class names with the TTS_ prefix should be normalized.'
        );

        tts_assert_equals(
            'integration_hub',
            $normalizer->invoke( null, 'TTS\\Integration_Hub' ),
            'Namespaced class names should be normalized without errors.'
        );

        tts_assert_equals(
            'general',
            $normalizer->invoke( null, 'general' ),
            'Unprefixed component names should remain unchanged.'
        );
    },
    'logging_from_namespaced_class_no_longer_throws' => function () {
        tts_reset_test_state();

        if ( ! class_exists( '\\TTS\\Integration_Hub_Logger_Test' ) ) {
            eval(
                <<<'PHP'
namespace TTS {
    class Integration_Hub_Logger_Test {
        public static function trigger(): void {
            \TTS_Logger::log('Namespaced log invocation from regression test');
        }
    }
}
PHP
            );
        }

        try {
            \TTS\Integration_Hub_Logger_Test::trigger();
        } catch ( Throwable $e ) {
            throw new RuntimeException(
                'Logging from a namespaced class should not throw errors. ' . $e->getMessage(),
                0,
                $e
            );
        }
    },
);

$failures = 0;
$messages = array();

echo "Running logger tests\n";

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
