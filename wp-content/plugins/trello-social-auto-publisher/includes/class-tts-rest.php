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

        // Trigger publish via scheduler using the registered action hook.
        do_action( 'tts_publish_social_post', $id );

        return rest_ensure_response( array( 'post_id' => $id ) );
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

        $post_status      = get_post_status( $id );
        $published_status = get_post_meta( $id, '_published_status', true );

        $table = $wpdb->prefix . 'tts_logs';
        $logs  = $wpdb->get_results( $wpdb->prepare( "SELECT channel, status, message, response, created_at FROM {$table} WHERE post_id = %d ORDER BY id DESC", $id ), ARRAY_A );

        return rest_ensure_response(
            array(
                'post_status'       => $post_status,
                '_published_status' => $published_status,
                'logs'              => $logs,
            )
        );
    }
}

new TTS_REST();
