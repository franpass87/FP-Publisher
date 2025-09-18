<?php
/**
 * Token refresh utilities.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles refreshing of social tokens for clients.
 */
class TTS_Token_Refresh {

    /**
     * Refresh tokens for all tts_client posts.
     */
    public static function refresh_tokens() {
        $clients = get_posts(
            array(
                'post_type'      => 'tts_client',
                'posts_per_page' => -1,
                'post_status'    => 'any',
            )
        );

        if ( empty( $clients ) ) {
            return;
        }

        foreach ( $clients as $client ) {
            self::refresh_client_tokens( $client->ID );
        }
    }

    /**
     * Refresh tokens for a single client.
     *
     * @param int $client_id Client post ID.
     */
    protected static function refresh_client_tokens( $client_id ) {
        $tokens       = array(
            'facebook'  => array(
                'meta'  => '_tts_fb_token',
                'token' => get_post_meta( $client_id, '_tts_fb_token', true ),
            ),
            'instagram' => array(
                'meta'  => '_tts_ig_token',
                'token' => get_post_meta( $client_id, '_tts_ig_token', true ),
            ),
        );
        $social_apps = get_option( 'tts_social_apps', array() );
        $errors      = array();

        foreach ( $tokens as $channel => $data ) {
            $token          = $data['token'];
            $meta_key       = $data['meta'];
            $channel_config = isset( $social_apps[ $channel ] ) ? $social_apps[ $channel ] : array();

            if ( empty( $token ) ) {
                continue;
            }

            $endpoint = '';
            $url      = '';

            if ( 'facebook' === $channel ) {
                $app_id     = isset( $channel_config['app_id'] ) ? $channel_config['app_id'] : '';
                $app_secret = isset( $channel_config['app_secret'] ) ? $channel_config['app_secret'] : '';

                if ( empty( $app_id ) || empty( $app_secret ) ) {
                    $error_message = __( 'Facebook app credentials are missing; cannot refresh token.', 'fp-publisher' );
                    $error_data    = array(
                        'client_id'       => $client_id,
                        'missing_app_id'  => empty( $app_id ),
                        'missing_secret'  => empty( $app_secret ),
                    );
                    $error         = new WP_Error( 'tts_facebook_credentials_missing', $error_message, $error_data );

                    tts_log_event(
                        $client_id,
                        $channel,
                        'error',
                        'Token refresh failed: missing app credentials',
                        array(
                            'has_app_id'     => ! empty( $app_id ),
                            'has_app_secret' => ! empty( $app_secret ),
                        )
                    );

                    $errors[ $channel ] = $error;
                    continue;
                }

                $endpoint = 'https://graph.facebook.com/v18.0/oauth/access_token';
                $url      = add_query_arg(
                    array(
                        'grant_type'        => 'fb_exchange_token',
                        'client_id'         => $app_id,
                        'client_secret'     => $app_secret,
                        'fb_exchange_token' => $token,
                    ),
                    $endpoint
                );
            } elseif ( 'instagram' === $channel ) {
                $endpoint = 'https://graph.instagram.com/refresh_access_token';
                $url      = add_query_arg(
                    array(
                        'grant_type'   => 'ig_refresh_token',
                        'access_token' => $token,
                    ),
                    $endpoint
                );
            }

            if ( empty( $url ) ) {
                continue;
            }

            $response = wp_remote_get( $url );
            if ( is_wp_error( $response ) ) {
                $message = sprintf(
                    __( '%1$s token refresh request failed: %2$s', 'fp-publisher' ),
                    ucfirst( $channel ),
                    $response->get_error_message()
                );
                $error   = new WP_Error(
                    'tts_' . $channel . '_http_error',
                    $message,
                    array(
                        'client_id' => $client_id,
                        'endpoint'  => $endpoint,
                    )
                );

                tts_log_event( $client_id, $channel, 'error', 'Token refresh request failed', $response->get_error_message() );

                $errors[ $channel ] = $error;
                continue;
            }

            $response_code = (int) wp_remote_retrieve_response_code( $response );
            $body_raw      = wp_remote_retrieve_body( $response );
            $body          = json_decode( $body_raw, true );

            if ( 200 !== $response_code ) {
                $error = new WP_Error(
                    'tts_' . $channel . '_http_status_error',
                    sprintf(
                        __( '%1$s token refresh returned unexpected HTTP status: %2$d.', 'fp-publisher' ),
                        ucfirst( $channel ),
                        $response_code
                    ),
                    array(
                        'client_id' => $client_id,
                        'status'    => $response_code,
                        'body'      => $body_raw,
                    )
                );

                tts_log_event( $client_id, $channel, 'error', 'Token refresh HTTP error', array( 'status' => $response_code, 'body' => $body_raw ) );

                $errors[ $channel ] = $error;
                continue;
            }

            if ( null === $body && JSON_ERROR_NONE !== json_last_error() ) {
                $error = new WP_Error(
                    'tts_' . $channel . '_invalid_response',
                    sprintf( __( '%s token refresh returned an invalid response.', 'fp-publisher' ), ucfirst( $channel ) ),
                    array(
                        'client_id' => $client_id,
                        'body'      => $body_raw,
                    )
                );

                tts_log_event( $client_id, $channel, 'error', 'Token refresh invalid response', $body_raw );

                $errors[ $channel ] = $error;
                continue;
            }

            if ( ! is_array( $body ) ) {
                $error = new WP_Error(
                    'tts_' . $channel . '_unexpected_response_format',
                    sprintf( __( '%s token refresh response could not be parsed.', 'fp-publisher' ), ucfirst( $channel ) ),
                    array(
                        'client_id' => $client_id,
                        'body'      => $body_raw,
                    )
                );

                tts_log_event( $client_id, $channel, 'error', 'Token refresh response parse error', $body_raw );

                $errors[ $channel ] = $error;
                continue;
            }

            if ( isset( $body['error'] ) ) {
                $error_details = is_scalar( $body['error'] ) ? $body['error'] : wp_json_encode( $body['error'] );
                $error         = new WP_Error(
                    'tts_' . $channel . '_api_error',
                    sprintf( __( '%1$s token refresh error: %2$s', 'fp-publisher' ), ucfirst( $channel ), $error_details ),
                    array(
                        'client_id' => $client_id,
                        'response'  => $body,
                    )
                );

                tts_log_event( $client_id, $channel, 'error', 'Token refresh API error', $body['error'] );

                $errors[ $channel ] = $error;
                continue;
            }

            if ( empty( $body['access_token'] ) ) {
                $error = new WP_Error(
                    'tts_' . $channel . '_missing_access_token',
                    sprintf( __( '%s token refresh response did not include a new access token.', 'fp-publisher' ), ucfirst( $channel ) ),
                    array(
                        'client_id' => $client_id,
                        'response'  => $body,
                    )
                );

                tts_log_event( $client_id, $channel, 'error', 'Token refresh response missing token', $body );

                $errors[ $channel ] = $error;
                continue;
            }

            update_post_meta( $client_id, $meta_key, sanitize_text_field( $body['access_token'] ) );

            $log_context = array();
            if ( isset( $body['expires_in'] ) ) {
                $log_context['expires_in'] = absint( $body['expires_in'] );
            }

            tts_log_event( $client_id, $channel, 'success', 'Token refreshed successfully', $log_context );
        }

        if ( ! empty( $errors ) ) {
            return new WP_Error(
                'tts_token_refresh_failed',
                __( 'One or more token refresh operations failed.', 'fp-publisher' ),
                $errors
            );
        }

        return true;
    }
}
