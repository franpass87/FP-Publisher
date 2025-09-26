<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';
require_once __DIR__ . '/../includes/class-tts-advanced-utils.php';

$tests = array(
	'export_provides_preencoded_payload'       => function () {
		tts_reset_test_state();

		$result = TTS_Advanced_Utils::export_data(
			array(
				'settings'        => false,
				'social_apps'     => false,
				'clients'         => false,
				'posts'           => false,
				'logs'            => false,
				'analytics'       => false,
				'include_secrets' => false,
			)
		);

		tts_assert_true( $result['success'], 'Export should succeed when no data sets are requested.' );
		tts_assert_true(
			isset( $result['encoded'] ) && is_string( $result['encoded'] ),
			'Export results must include a pre-encoded JSON payload.'
		);
		tts_assert_equals(
			strlen( $result['encoded'] ),
			$result['file_size'],
			'The reported file size should match the encoded payload length.'
		);
	},
	'export_returns_error_on_encoding_failure' => function () {
		tts_reset_test_state();

		$filter = function ( $payload ) {
			$payload['data']['invalid'] = INF;
			return $payload;
		};

		add_filter( 'tts_export_package', $filter, 10, 1 );

		$result = TTS_Advanced_Utils::export_data();

		tts_assert_false( $result['success'], 'Encoding failures should return a failed export response.' );
		tts_assert_equals(
			'tts_export_encoding_failed',
			$result['error_code'],
			'Encoding failures should surface a descriptive error code.'
		);
		tts_assert_contains(
			'encode',
			$result['error'],
			'The error message should mention encoding.'
		);
	},
	'export_validates_encoded_payload_filter'  => function () {
		tts_reset_test_state();

		$filter = function ( $encoded, $payload ) {
			unset( $encoded, $payload );

			return array();
		};

		add_filter( 'tts_export_encoded_payload', $filter, 10, 2 );

		$result = TTS_Advanced_Utils::export_data(
			array(
				'settings'        => false,
				'social_apps'     => false,
				'clients'         => false,
				'posts'           => false,
				'logs'            => false,
				'analytics'       => false,
				'include_secrets' => false,
			)
		);

		tts_assert_false( $result['success'], 'Invalid encoded payload filters should fail the export.' );
		tts_assert_equals(
			'tts_export_encoding_failed',
			$result['error_code'],
			'Invalid encoded payloads should surface the encoding failed error code.'
		);
	},
);

$failures = 0;
$messages = array();

echo "Running export utility tests\n";

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
