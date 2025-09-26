<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';
require_once __DIR__ . '/../includes/class-tts-secure-storage.php';

$tests = array(
	'metadata_encryption_round_trip'                  => function () {
		tts_reset_test_state();

		$storage = TTS_Secure_Storage::instance();

		$post_id = 101;
		$token   = 'plain-token-value-123';

		update_post_meta( $post_id, '_tts_fb_token', $token );

		tts_assert_true(
			isset( $GLOBALS['tts_test_post_meta'][ $post_id ]['_tts_fb_token'] ),
			'Encrypted meta should be stored in the test meta table.'
		);

		$stored_value = $GLOBALS['tts_test_post_meta'][ $post_id ]['_tts_fb_token'];

		tts_assert_true(
			is_string( $stored_value ) && 0 === strpos( $stored_value, TTS_Secure_Storage::ENCRYPTION_PREFIX ),
			'Sensitive metadata should be stored using the secure storage prefix.'
		);

		$retrieved = get_post_meta( $post_id, '_tts_fb_token', true );

		tts_assert_equals( $token, $retrieved, 'Encrypted metadata should be transparently decrypted when retrieved.' );
	},
	'resolve_managed_secret_supports_vault_providers' => function () {
		tts_reset_test_state();

		$storage = TTS_Secure_Storage::instance();

		add_filter(
			'tts_vault_resolve_aws-kms',
			function ( $value, $reference ) {
				if ( 'alias/test-secret' === $reference ) {
					return 'kms-secret-value';
				}

				return $value;
			},
			10,
			2
		);

		$resolved = $storage->resolve_managed_secret( 'vault:aws-kms:alias/test-secret', array() );

		tts_assert_equals( 'kms-secret-value', $resolved, 'AWS KMS provider filter should resolve vault secrets.' );
	},
	'mask_sensitive_data_redacts_tokens'              => function () {
		tts_reset_test_state();

		$storage = TTS_Secure_Storage::instance();

		$payload = array(
			'token' => 'abcd1234',
			'note'  => 'hello',
		);

		$masked = $storage->mask_sensitive_data( $payload );

		tts_assert_true(
			isset( $masked['token'] ) && 'abcd1234' !== $masked['token'],
			'Token values should be masked when preparing data for display.'
		);

		tts_assert_equals( 'hello', $masked['note'], 'Non-sensitive fields should remain unchanged.' );
	},
	'plaintext_fallback_when_encryption_unavailable'  => function () {
		tts_reset_test_state();

		$disable_encryption = function () {
			return false;
		};

		add_filter( 'tts_secure_storage_encryption_supported', $disable_encryption );

		TTS_Secure_Storage::reset_instance();
		TTS_Secure_Storage::instance();

		$post_id = 207;
		$token   = 'fallback-token-value';

		update_post_meta( $post_id, '_tts_fb_token', $token );

		tts_assert_true(
			isset( $GLOBALS['tts_test_post_meta'][ $post_id ]['_tts_fb_token'] ),
			'Meta value should be stored even when encryption support is missing.'
		);

		$stored_value = $GLOBALS['tts_test_post_meta'][ $post_id ]['_tts_fb_token'];

		tts_assert_false(
			is_string( $stored_value ) && 0 === strpos( $stored_value, TTS_Secure_Storage::ENCRYPTION_PREFIX ),
			'Secure prefix should not be applied when encryption is unavailable.'
		);

		tts_assert_equals(
			$token,
			maybe_unserialize( $stored_value ),
			'Plaintext fallback should preserve the original value.'
		);

		$retrieved = get_post_meta( $post_id, '_tts_fb_token', true );

		tts_assert_equals(
			$token,
			$retrieved,
			'Retrieving fallback metadata should return the original value.'
		);

		tts_reset_test_state();
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
