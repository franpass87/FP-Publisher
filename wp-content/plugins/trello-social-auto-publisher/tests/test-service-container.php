<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';
require_once __DIR__ . '/../includes/class-tts-service-container.php';

$tests = array(
	'resolves_shared_services_once' => function () {
		tts_reset_test_state();

		$container = new TTS_Service_Container();
		$container->set(
			'shared',
			function () {
				return new stdClass();
			}
		);

		$first  = $container->get( 'shared' );
		$second = $container->get( 'shared' );

		tts_assert_true( $first === $second, 'Shared services should be returned as singletons.' );
	},
	'supports_non_shared_factories' => function () {
		tts_reset_test_state();

		$container = new TTS_Service_Container();
		$container->set(
			'factory',
			function () {
				return new stdClass();
			},
			false
		);

		$first  = $container->get( 'factory' );
		$second = $container->get( 'factory' );

		tts_assert_true( $first !== $second, 'Non shared services should create distinct instances.' );
	},
	'resolves_nested_dependencies'  => function () {
		tts_reset_test_state();

		$container = new TTS_Service_Container();
		$container->set(
			'config',
			function () {
				return array( 'value' => 42 );
			}
		);
		$container->set(
			'dependent',
			function ( TTS_Service_Container $c ) {
				return $c->get( 'config' );
			}
		);

		$result = $container->get( 'dependent' );

		tts_assert_equals( 42, $result['value'], 'Dependencies should be resolved using the container.' );
	},
	'throws_when_service_missing'   => function () {
		tts_reset_test_state();

		$container = new TTS_Service_Container();

		try {
			$container->get( 'missing' );
		} catch ( TTS_Service_Not_Found_Exception $exception ) {
			tts_assert_contains( 'missing', $exception->getMessage(), 'Exception should mention the missing identifier.' );
			return;
		}

		throw new RuntimeException( 'Expecting a TTS_Service_Not_Found_Exception when service is undefined.' );
	},
	'detects_circular_dependencies' => function () {
		tts_reset_test_state();

		$container = new TTS_Service_Container();
		$container->set(
			'a',
			function ( TTS_Service_Container $c ) {
				return $c->get( 'b' );
			}
		);
		$container->set(
			'b',
			function ( TTS_Service_Container $c ) {
				return $c->get( 'a' );
			}
		);

		try {
			$container->get( 'a' );
		} catch ( TTS_Service_Exception $exception ) {
			tts_assert_contains( 'Circular dependency', $exception->getMessage(), 'Circular dependencies should be detected.' );
			return;
		}

		throw new RuntimeException( 'A circular dependency must raise a TTS_Service_Exception.' );
	},
	'accepts_prebuilt_instances'    => function () {
		tts_reset_test_state();

		$container = new TTS_Service_Container();
		$instance  = new stdClass();
		$instance->flag = true;

		$container->set( 'prebuilt', $instance );

		$resolved = $container->get( 'prebuilt' );

		tts_assert_true( $resolved === $instance, 'Prebuilt instances should be reused verbatim.' );
	},
);

$failures = 0;
$messages = array();

echo "Running service container tests\n";

foreach ( $tests as $name => $callback ) {
	try {
		$callback();
		echo '.';
	} catch ( Throwable $exception ) {
		++$failures;
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
