<?php
declare(strict_types=1);

/**
 * Basic assertion helpers for the lightweight test harness.
 */
function tts_assert_true( $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function tts_assert_false( $condition, string $message ): void {
	tts_assert_true( ! $condition, $message );
}

function tts_assert_contains( string $needle, string $haystack, string $message ): void {
	if ( false === strpos( $haystack, $needle ) ) {
		throw new RuntimeException( $message );
	}
}

function tts_assert_not_contains( string $needle, string $haystack, string $message ): void {
	if ( false !== strpos( $haystack, $needle ) ) {
		throw new RuntimeException( $message );
	}
}

function tts_assert_equals( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		$details = sprintf( ' Expected %s but received %s.', var_export( $expected, true ), var_export( $actual, true ) );
		throw new RuntimeException( $message . $details );
	}
}
