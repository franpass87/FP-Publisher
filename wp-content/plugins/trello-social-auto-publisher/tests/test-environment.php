<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';
require_once __DIR__ . '/../trello-social-auto-publisher.php';

$tests = array(
	'flags_missing_extensions_via_filter' => function () {
		tts_reset_test_state();

		add_filter(
			'tsap_extension_supported',
			function ( $supported, $extension ) {
				if ( in_array( $extension, array( 'curl', 'json', 'mbstring' ), true ) ) {
					return false;
				}

				return $supported;
			},
			10,
			2
		);

		$issues = tsap_get_environment_issues();
		$combined = implode( ' | ', $issues );

		tts_assert_contains(
			'The cURL PHP extension must be enabled to communicate with external services.',
			$combined,
			'Missing cURL support should be reported.'
		);

		tts_assert_contains(
			'The JSON PHP extension must be enabled to encode and decode API responses.',
			$combined,
			'Missing JSON support should be reported.'
		);

		tts_assert_contains(
			'The Mbstring PHP extension must be enabled to handle multibyte content safely.',
			$combined,
			'Missing Mbstring support should be reported.'
		);

		tts_reset_test_state();
	},
);

$failures = 0;
$messages = array();

echo "Running environment requirement tests\n";

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
