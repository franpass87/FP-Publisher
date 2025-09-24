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

    const EXPIRY_REFRESH_THRESHOLD = DAY_IN_SECONDS;
    const DEFAULT_ROTATION_INTERVAL = WEEK_IN_SECONDS;
    const MINIMUM_ROTATION_INTERVAL = HOUR_IN_SECONDS;

    /**
     * Shared secure storage instance.
     *
     * @var TTS_Secure_Storage|null
     */
    private static $secure_storage = null;

    /**
     * Metadata configuration per channel.
     *
     * @var array<string, array<string, string>>
     */
    private static $token_meta_map = array(
        'facebook'  => array(
            'meta'          => '_tts_fb_token',
            'previous_meta' => '_tts_fb_token_previous',
            'expires_meta'  => '_tts_fb_token_expires_at',
            'rotated_meta'  => '_tts_fb_token_rotated_at',
        ),
        'instagram' => array(
            'meta'          => '_tts_ig_token',
            'previous_meta' => '_tts_ig_token_previous',
            'expires_meta'  => '_tts_ig_token_expires_at',
            'rotated_meta'  => '_tts_ig_token_rotated_at',
        ),
    );

    /**
     * Refresh tokens for all tts_client posts.
     */
    public static function refresh_tokens() {
        self::get_secure_storage();

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
        $secure_storage = self::get_secure_storage();
        $meta_map       = self::$token_meta_map;
        $social_apps    = get_option( 'tts_social_apps', array() );
        $errors         = array();

        foreach ( $meta_map as $channel => $meta_info ) {
            $raw_token = get_post_meta( $client_id, $meta_info['meta'], true );

            if ( $secure_storage ) {
                $raw_token = $secure_storage->resolve_managed_secret(
                    $raw_token,
                    array(
                        'context'   => 'token_refresh',
                        'channel'   => $channel,
                        'client_id' => $client_id,
                        'purpose'   => 'access_token',
                    )
                );
            }

            $token = self::normalize_token_value( $raw_token );

            if ( '' === $token ) {
                continue;
            }

            $meta_info['token']      = $token;
            $meta_info['expires_at'] = isset( $meta_info['expires_meta'] ) ? intval( get_post_meta( $client_id, $meta_info['expires_meta'], true ) ) : 0;
            $meta_info['rotated_at'] = isset( $meta_info['rotated_meta'] ) ? intval( get_post_meta( $client_id, $meta_info['rotated_meta'], true ) ) : 0;

            if ( ! self::should_refresh_token( $client_id, $channel, $meta_info ) ) {
                continue;
            }

            $channel_config = isset( $social_apps[ $channel ] ) ? self::resolve_channel_config( $channel, $social_apps[ $channel ] ) : array();
            $request        = self::build_refresh_request( $channel, $token, $channel_config, $client_id );

            if ( is_wp_error( $request ) ) {
                $errors[ $channel ] = $request;
                continue;
            }

            $response = wp_remote_get( $request['url'] );

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
                        'endpoint'  => $request['endpoint'],
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

            $storage_context = self::store_refreshed_token( $client_id, $channel, $meta_info, $body );

            $log_context = array(
                'rotation_source' => $request['endpoint'],
                'storage'         => isset( $storage_context['storage'] ) ? $storage_context['storage'] : 'meta',
            );

            if ( isset( $body['expires_in'] ) ) {
                $log_context['expires_in'] = absint( $body['expires_in'] );
            }

            if ( isset( $storage_context['expires_at'] ) && $storage_context['expires_at'] ) {
                $log_context['expires_at'] = $storage_context['expires_at'];
            }

            if ( isset( $meta_info['rotated_meta'] ) ) {
                $log_context['rotated_at'] = time();
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

    /**
     * Retrieve the secure storage instance when available.
     *
     * @return TTS_Secure_Storage|null
     */
    private static function get_secure_storage() {
        if ( null === self::$secure_storage && class_exists( 'TTS_Secure_Storage' ) ) {
            self::$secure_storage = TTS_Secure_Storage::instance();
        }

        return self::$secure_storage;
    }

    /**
     * Normalize token values to trimmed strings.
     *
     * @param mixed $token Raw token value.
     *
     * @return string
     */
    private static function normalize_token_value( $token ) {
        if ( is_array( $token ) || is_object( $token ) ) {
            $token = wp_json_encode( $token );
        }

        if ( ! is_string( $token ) ) {
            return '';
        }

        return trim( $token );
    }

    /**
     * Resolve channel configuration using managed secret providers.
     *
     * @param string $channel Channel identifier.
     * @param mixed  $config  Raw configuration.
     *
     * @return array<string, mixed>
     */
    private static function resolve_channel_config( $channel, $config ) {
        $config = is_array( $config ) ? $config : array();
        $storage = self::get_secure_storage();

        if ( ! $storage ) {
            return $config;
        }

        foreach ( $config as $key => $value ) {
            if ( is_scalar( $value ) ) {
                $resolved = $storage->resolve_managed_secret(
                    $value,
                    array(
                        'context' => 'token_refresh',
                        'channel' => $channel,
                        'field'   => $key,
                    )
                );

                if ( is_scalar( $resolved ) ) {
                    $config[ $key ] = trim( (string) $resolved );
                }
            }
        }

        return $config;
    }

    /**
     * Determine whether a token should be rotated.
     *
     * @param int    $client_id Client identifier.
     * @param string $channel   Channel key.
     * @param array  $meta_info Metadata for the token.
     *
     * @return bool
     */
    private static function should_refresh_token( $client_id, $channel, array $meta_info ) {
        $decision = apply_filters( 'tts_should_refresh_social_token', null, $client_id, $channel, $meta_info );

        if ( null !== $decision ) {
            return (bool) $decision;
        }

        $now        = time();
        $expires_at = isset( $meta_info['expires_at'] ) ? (int) $meta_info['expires_at'] : 0;
        $rotated_at = isset( $meta_info['rotated_at'] ) ? (int) $meta_info['rotated_at'] : 0;

        if ( $expires_at > 0 ) {
            if ( $expires_at <= $now ) {
                return true;
            }

            if ( ( $expires_at - $now ) <= self::EXPIRY_REFRESH_THRESHOLD ) {
                return true;
            }

            if ( $rotated_at > 0 && ( $now - $rotated_at ) < self::MINIMUM_ROTATION_INTERVAL ) {
                return false;
            }

            return false;
        }

        if ( $rotated_at <= 0 ) {
            return true;
        }

        if ( ( $now - $rotated_at ) < self::MINIMUM_ROTATION_INTERVAL ) {
            return false;
        }

        if ( ( $now - $rotated_at ) >= self::DEFAULT_ROTATION_INTERVAL ) {
            return true;
        }

        return false;
    }

    /**
     * Build the refresh request for a specific channel.
     *
     * @param string $channel Channel key.
     * @param string $token   Current token.
     * @param array  $config  Channel configuration.
     * @param int    $client_id Client identifier.
     *
     * @return array{endpoint: string, url: string}|WP_Error
     */
    private static function build_refresh_request( $channel, $token, array $config, $client_id ) {
        if ( 'facebook' === $channel ) {
            $app_id     = isset( $config['app_id'] ) ? trim( (string) $config['app_id'] ) : '';
            $app_secret = isset( $config['app_secret'] ) ? trim( (string) $config['app_secret'] ) : '';

            if ( '' === $app_id || '' === $app_secret ) {
                $error_message = __( 'Facebook app credentials are missing; cannot refresh token.', 'fp-publisher' );
                $error_data    = array(
                    'client_id'       => $client_id,
                    'missing_app_id'  => '' === $app_id,
                    'missing_secret'  => '' === $app_secret,
                );

                tts_log_event(
                    $client_id,
                    $channel,
                    'error',
                    'Token refresh failed: missing app credentials',
                    array(
                        'has_app_id'     => '' !== $app_id,
                        'has_app_secret' => '' !== $app_secret,
                    )
                );

                return new WP_Error( 'tts_facebook_credentials_missing', $error_message, $error_data );
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

            return compact( 'endpoint', 'url' );
        }

        if ( 'instagram' === $channel ) {
            $endpoint = 'https://graph.instagram.com/refresh_access_token';
            $url      = add_query_arg(
                array(
                    'grant_type'   => 'ig_refresh_token',
                    'access_token' => $token,
                ),
                $endpoint
            );

            return compact( 'endpoint', 'url' );
        }

        return new WP_Error(
            'tts_' . $channel . '_unsupported_channel',
            sprintf( __( 'Token refresh is not configured for the %s channel.', 'fp-publisher' ), $channel ),
            array( 'client_id' => $client_id )
        );
    }

    /**
     * Persist refreshed token information and metadata.
     *
     * @param int    $client_id  Client identifier.
     * @param string $channel    Channel key.
     * @param array  $meta_info  Token metadata definitions.
     * @param array  $body       API response payload.
     *
     * @return array<string, mixed>
     */
    private static function store_refreshed_token( $client_id, $channel, array $meta_info, array $body ) {
        $new_token = sanitize_text_field( (string) $body['access_token'] );
        $previous  = isset( $meta_info['token'] ) ? $meta_info['token'] : '';

        if ( isset( $meta_info['previous_meta'] ) && '' !== $previous ) {
            update_post_meta( $client_id, $meta_info['previous_meta'], $previous );
        }

        $handled = apply_filters( 'tts_token_refresh_persist_token', false, $client_id, $channel, $new_token, $meta_info, $body );

        if ( ! $handled ) {
            update_post_meta( $client_id, $meta_info['meta'], $new_token );
        }

        $expires_at = null;

        if ( isset( $meta_info['expires_meta'] ) ) {
            if ( isset( $body['expires_in'] ) ) {
                $expires_at = time() + absint( $body['expires_in'] );
                update_post_meta( $client_id, $meta_info['expires_meta'], $expires_at );
            } else {
                delete_post_meta( $client_id, $meta_info['expires_meta'] );
            }
        }

        if ( isset( $meta_info['rotated_meta'] ) ) {
            update_post_meta( $client_id, $meta_info['rotated_meta'], time() );
        }

        return array(
            'storage'    => $handled ? 'external' : 'meta',
            'expires_at' => $expires_at,
        );
    }
}
