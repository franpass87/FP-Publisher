<?php
/**
 * REST endpoints for manual publish and status checks.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles custom REST API routes.
 */
class TTS_REST {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route(
            'tts/v1',
            '/post/(?P<id>\d+)/publish',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'publish' ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            'tts/v1',
            '/post/(?P<id>\d+)/status',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'status' ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );
    }

    /**
     * Check permissions for routes.
     *
     * @param WP_REST_Request $request The current request.
     *
     * @return bool
     */
    public function permissions_check( WP_REST_Request $request ) {
        $post_id = intval( $request['id'] );

        if ( ! $this->verify_rest_nonce( $request ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Invalid REST nonce.', 'fp-publisher' ), array( 'status' => 403 ) );
        }

        if ( 'POST' === $request->get_method() ) {
            if ( ! current_user_can( 'tts_publish_social_posts' ) || ! current_user_can( 'edit_post', $post_id ) ) {
                return new WP_Error( 'rest_forbidden', __( 'You do not have permission to publish this social post.', 'fp-publisher' ), array( 'status' => 403 ) );
            }
        } else {
            if ( ! current_user_can( 'tts_read_social_posts' ) || ( $post_id && ! current_user_can( 'read_post', $post_id ) ) ) {
                return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view this social post.', 'fp-publisher' ), array( 'status' => 403 ) );
            }
        }

        return true;
    }

    /**
     * Validate REST nonce provided via header or request parameter.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return bool
     */
    private function verify_rest_nonce( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );

        if ( empty( $nonce ) ) {
            $nonce = $request->get_param( '_wpnonce' );
        }

        if ( empty( $nonce ) ) {
            return false;
        }

        return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
    }

    /**
     * Publish the social post immediately.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function publish( WP_REST_Request $request ) {
        $id = intval( $request['id'] );

        if ( $id <= 0 ) {
            return new WP_Error( 'invalid_post', __( 'Invalid post ID.', 'fp-publisher' ), array( 'status' => 404 ) );
        }

        $post = get_post( $id );

        if ( ! $post ) {
            return new WP_Error( 'invalid_post', __( 'Invalid post ID.', 'fp-publisher' ), array( 'status' => 404 ) );
        }

        if ( 'tts_social_post' !== $post->post_type ) {
            return new WP_Error( 'invalid_post_type', __( 'The requested post is not managed by FP Publisher.', 'fp-publisher' ), array( 'status' => 400 ) );
        }

        if ( 'trash' === $post->post_status ) {
            return new WP_Error( 'invalid_post_status', __( 'Cannot publish a trashed social post.', 'fp-publisher' ), array( 'status' => 409 ) );
        }

        $approved = (bool) get_post_meta( $id, '_tts_approved', true );

        if ( ! $approved ) {
            return new WP_Error( 'post_not_approved', __( 'The social post must be approved before publishing.', 'fp-publisher' ), array( 'status' => 409 ) );
        }

        $channels = get_post_meta( $id, '_tts_social_channel', true );
        if ( empty( $channels ) ) {
            return new WP_Error( 'missing_channels', __( 'No social channels are configured for this post.', 'fp-publisher' ), array( 'status' => 409 ) );
        }

        $channels = is_array( $channels ) ? $channels : array( $channels );
        $channels = array_values( array_filter( array_map( 'sanitize_key', $channels ) ) );

        if ( empty( $channels ) ) {
            return new WP_Error( 'missing_channels', __( 'No valid social channels are configured for this post.', 'fp-publisher' ), array( 'status' => 409 ) );
        }

        if ( ! has_action( 'tts_publish_social_post' ) ) {
            return new WP_Error( 'scheduler_unavailable', __( 'The scheduler is not available to process the publish request.', 'fp-publisher' ), array( 'status' => 503 ) );
        }

        do_action( 'tts_publish_social_post', $id );

        $response = new WP_REST_Response(
            array(
                'post_id'  => $id,
                'status'   => get_post_meta( $id, '_published_status', true ) ?: 'queued',
                'channels' => $channels,
            ),
            202
        );

        return rest_ensure_response( $response );
    }

    /**
     * Get publication status and logs for a post.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function status( WP_REST_Request $request ) {
        global $wpdb;

        $id   = intval( $request['id'] );
        $post = get_post( $id );
        if ( ! $post ) {
            return new WP_Error( 'invalid_post', __( 'Invalid post ID.', 'fp-publisher' ), array( 'status' => 404 ) );
        }

        $post_status_raw      = get_post_status( $id );
        $published_status_raw = get_post_meta( $id, '_published_status', true );

        $post_status = '';
        if ( false !== $post_status_raw && null !== $post_status_raw ) {
            $post_status = sanitize_text_field( (string) $post_status_raw );
        }

        $published_status = '';
        if ( ! empty( $published_status_raw ) ) {
            $published_status = sanitize_text_field( (string) $published_status_raw );
        }

        $table = $wpdb->prefix . 'tts_logs';
        $logs  = $wpdb->get_results( $wpdb->prepare( "SELECT channel, status, message, response, created_at FROM {$table} WHERE post_id = %d ORDER BY id DESC", $id ), ARRAY_A );

        $sanitized_logs = array();

        if ( is_array( $logs ) ) {
            foreach ( $logs as $log_entry ) {
                if ( is_object( $log_entry ) ) {
                    $log_entry = (array) $log_entry;
                }

                if ( ! is_array( $log_entry ) ) {
                    continue;
                }

                $sanitized_logs[] = $this->sanitize_log_entry( $log_entry );
            }
        }

        return rest_ensure_response(
            array(
                'post_status'       => $post_status,
                '_published_status' => $published_status,
                'logs'              => $sanitized_logs,
            )
        );
    }

    /**
     * Sanitize a single log entry retrieved from the database.
     *
     * @param array<string, mixed> $log_entry Raw log entry array.
     *
     * @return array<string, mixed> Sanitized log data.
     */
    private function sanitize_log_entry( array $log_entry ) {
        $channel    = isset( $log_entry['channel'] ) ? $this->sanitize_channel_value( $log_entry['channel'] ) : '';
        $status     = isset( $log_entry['status'] ) ? sanitize_key( sanitize_text_field( (string) $log_entry['status'] ) ) : '';
        $message    = isset( $log_entry['message'] ) ? sanitize_textarea_field( (string) $log_entry['message'] ) : '';
        $response   = isset( $log_entry['response'] ) ? $this->sanitize_log_response_field( $log_entry['response'] ) : '';
        $created_at = isset( $log_entry['created_at'] ) ? sanitize_text_field( (string) $log_entry['created_at'] ) : '';

        return array(
            'channel'    => $channel,
            'status'     => $status,
            'message'    => $message,
            'response'   => $response,
            'created_at' => $created_at,
        );
    }

    /**
     * Normalize and sanitize channel values for REST output.
     *
     * @param mixed $channel Raw channel value.
     *
     * @return string
     */
    private function sanitize_channel_value( $channel ) {
        if ( empty( $channel ) ) {
            return '';
        }

        $channel_value = strtolower( sanitize_text_field( (string) $channel ) );

        if ( '' === $channel_value ) {
            return '';
        }

        $channel_value = preg_replace( '/[^a-z0-9_\-]+/', '', $channel_value );

        $known_channels = array( 'facebook', 'instagram', 'youtube', 'tiktok', 'linkedin', 'twitter', 'blog' );

        foreach ( $known_channels as $known_channel ) {
            if ( false !== strpos( $channel_value, $known_channel ) ) {
                return $known_channel;
            }
        }

        return sanitize_key( $channel_value );
    }

    /**
     * Sanitize the response field of a log entry.
     *
     * @param mixed $response Raw response data.
     *
     * @return mixed
     */
    private function sanitize_log_response_field( $response ) {
        if ( is_string( $response ) ) {
            $decoded = json_decode( $response, true );

            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                $sanitized = $this->sanitize_nested_log_value( $decoded );
                $encoded   = wp_json_encode( $sanitized );

                if ( false !== $encoded ) {
                    return $encoded;
                }
            }

            return sanitize_textarea_field( $response );
        }

        if ( is_array( $response ) || is_object( $response ) ) {
            return $this->sanitize_nested_log_value( $response );
        }

        if ( null === $response ) {
            return '';
        }

        if ( is_bool( $response ) ) {
            return $response;
        }

        if ( is_int( $response ) || is_float( $response ) ) {
            return $response;
        }

        return sanitize_textarea_field( (string) $response );
    }

    /**
     * Recursively sanitize nested log data structures.
     *
     * @param mixed $value Raw value.
     *
     * @return mixed
     */
    private function sanitize_nested_log_value( $value ) {
        if ( is_array( $value ) ) {
            $sanitized = array();

            foreach ( $value as $key => $nested_value ) {
                $sanitized_key = is_string( $key ) ? sanitize_key( $key ) : $key;
                $sanitized[ $sanitized_key ] = $this->sanitize_nested_log_value( $nested_value );
            }

            return $sanitized;
        }

        if ( is_object( $value ) ) {
            return $this->sanitize_nested_log_value( (array) $value );
        }

        if ( null === $value ) {
            return '';
        }

        if ( is_bool( $value ) ) {
            return $value;
        }

        if ( is_int( $value ) || is_float( $value ) ) {
            return $value;
        }

        return sanitize_textarea_field( (string) $value );
    }
}

new TTS_REST();
