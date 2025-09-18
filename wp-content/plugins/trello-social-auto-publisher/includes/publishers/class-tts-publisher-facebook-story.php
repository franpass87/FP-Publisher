<?php
/**
 * Facebook Story publisher.
 *
 * @package TrelloSocialAutoPublisher\Publishers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles publishing Facebook Stories.
 */
class TTS_Publisher_Facebook_Story {

    /**
     * Upload media to Facebook Stories.
     *
     * @param string $media_path Remote URL of the media.
     * @param array  $context    Additional context for the upload.
     * @return array Result data with success flag.
     */
    public function upload_media( $media_path, array $context = array() ) {
        $post_id     = isset( $context['post_id'] ) ? absint( $context['post_id'] ) : 0;
        $credentials = $context['credentials'] ?? '';
        $page_id     = $context['page_id'] ?? '';
        $token       = $context['token'] ?? '';

        if ( empty( $media_path ) ) {
            $error = __( 'Missing credentials or media for Facebook Story', 'fp-publisher' );
            tts_log_event( $post_id, 'facebook_story', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'facebook_story_missing_data',
            );
        }

        if ( ( empty( $page_id ) || empty( $token ) ) && ! empty( $credentials ) ) {
            if ( false !== strpos( $credentials, '|' ) ) {
                list( $cred_page_id, $cred_token ) = array_pad( explode( '|', $credentials, 2 ), 2, '' );
                if ( empty( $page_id ) ) {
                    $page_id = $cred_page_id;
                }
                if ( empty( $token ) ) {
                    $token = $cred_token;
                }
            } else {
                if ( empty( $token ) ) {
                    $token = $credentials;
                }
            }
        }

        if ( empty( $page_id ) && $post_id ) {
            $page_id = get_post_meta( $post_id, '_tts_fb_page_id', true );
        }

        if ( empty( $page_id ) || empty( $token ) ) {
            $error = __( 'Invalid Facebook credentials', 'fp-publisher' );
            tts_log_event( $post_id, 'facebook_story', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'facebook_story_bad_credentials',
            );
        }

        $endpoint = sprintf( 'https://graph.facebook.com/%s/stories', $page_id );
        $body     = array(
            'access_token' => $token,
            'file_url'     => $media_path,
        );

        $result = wp_remote_post(
            $endpoint,
            array(
                'body'    => $body,
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $result ) ) {
            $error = $result->get_error_message();
            tts_log_event( $post_id, 'facebook_story', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'facebook_story_request_error',
            );
        }

        $data = json_decode( wp_remote_retrieve_body( $result ), true );
        $code = wp_remote_retrieve_response_code( $result );

        if ( 200 !== $code || empty( $data['id'] ) ) {
            $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'fp-publisher' );
            tts_log_event( $post_id, 'facebook_story', 'error', $error, $data );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'facebook_story_error',
                'error_data' => $data,
            );
        }

        return array(
            'success' => true,
            'data'    => $data,
        );
    }

    /**
     * Publish a Story to Facebook.
     *
     * @param int    $post_id     Post ID.
     * @param mixed  $credentials Page ID and access token.
     * @param string $media_url   URL of the media to publish.
     * @return array|\WP_Error Log data or error.
     */
    public function publish_story( $post_id, $credentials, $media_url ) {
        $result = $this->upload_media(
            $media_url,
            array(
                'post_id'     => $post_id,
                'credentials' => $credentials,
            )
        );

        if ( empty( $result['success'] ) ) {
            tts_notify_publication( $post_id, 'error', 'facebook_story' );
            $error_code = $result['error_code'] ?? 'facebook_story_error';
            $error_data = $result['error_data'] ?? array();
            $error_msg  = $result['error'] ?? __( 'Unknown error', 'fp-publisher' );

            return new \WP_Error( $error_code, $error_msg, $error_data );
        }

        $data     = $result['data'] ?? array();
        $response = array(
            'message' => __( 'Published Facebook Story', 'fp-publisher' ),
            'id'      => $data['id'] ?? '',
        );

        tts_log_event( $post_id, 'facebook_story', 'success', $response['message'], $data );
        tts_notify_publication( $post_id, 'success', 'facebook_story' );

        return $response;
    }
}
