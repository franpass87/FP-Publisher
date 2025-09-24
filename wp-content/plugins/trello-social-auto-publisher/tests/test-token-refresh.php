<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';
require_once __DIR__ . '/../includes/tts-logger.php';
require_once __DIR__ . '/../includes/class-tts-secure-storage.php';
require_once __DIR__ . '/../includes/class-tts-token-refresh.php';

class TTS_Token_Refresh_Testable extends TTS_Token_Refresh {
    public static function run_refresh( $client_id ) {
        return parent::refresh_client_tokens( $client_id );
    }
}

$tests = array(
    'refresh_skips_recent_rotations' => function () {
        tts_reset_test_state();

        update_post_meta( 200, '_tts_fb_token', 'recent-token' );
        update_post_meta( 200, '_tts_fb_token_rotated_at', time() );
        update_option( 'tts_social_apps', array(
            'facebook' => array(
                'app_id'     => '123',
                'app_secret' => '456',
            ),
        ) );

        $result = TTS_Token_Refresh_Testable::run_refresh( 200 );

        tts_assert_true( true === $result, 'Tokens rotated recently should not trigger remote refresh attempts.' );
        tts_assert_true(
            empty( $GLOBALS['tts_http_responses'] ),
            'No HTTP responses should be consumed when refresh is skipped.'
        );
    },
    'refresh_updates_tokens_when_expiring' => function () {
        tts_reset_test_state();

        $client_id = 201;
        $old_token = 'old-token-value';
        update_post_meta( $client_id, '_tts_fb_token', $old_token );
        update_post_meta( $client_id, '_tts_fb_token_expires_at', time() + 1800 );
        update_option( 'tts_social_apps', array(
            'facebook' => array(
                'app_id'     => 'my-app',
                'app_secret' => 'vault:aws-kms:fb-secret',
            ),
        ) );

        add_filter(
            'tts_vault_resolve_aws-kms',
            function ( $value, $reference ) {
                if ( 'fb-secret' === $reference ) {
                    return 'resolved-secret';
                }

                return $value;
            },
            10,
            2
        );

        $expected_url = add_query_arg(
            array(
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => 'my-app',
                'client_secret'     => 'resolved-secret',
                'fb_exchange_token' => $old_token,
            ),
            'https://graph.facebook.com/v18.0/oauth/access_token'
        );

        $GLOBALS['tts_http_responses'][ $expected_url ] = array(
            'response' => array( 'code' => 200 ),
            'body'     => wp_json_encode(
                array(
                    'access_token' => 'new-token-value',
                    'expires_in'   => 3600,
                )
            ),
        );

        $result = TTS_Token_Refresh_Testable::run_refresh( $client_id );

        tts_assert_true( true === $result, 'Token refresh should succeed when the token is nearing expiration.' );

        $stored_token = get_post_meta( $client_id, '_tts_fb_token', true );
        tts_assert_equals( 'new-token-value', $stored_token, 'Refreshed tokens should be stored securely.' );

        $previous_token = get_post_meta( $client_id, '_tts_fb_token_previous', true );
        tts_assert_equals( $old_token, $previous_token, 'Previous token should be retained for fallback purposes.' );

        $expires_at = get_post_meta( $client_id, '_tts_fb_token_expires_at', true );
        tts_assert_true( $expires_at > time(), 'Expiration metadata should be updated after refresh.' );

        $rotated_at = get_post_meta( $client_id, '_tts_fb_token_rotated_at', true );
        tts_assert_true( $rotated_at > 0, 'Rotation timestamp should be recorded after refresh.' );
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
