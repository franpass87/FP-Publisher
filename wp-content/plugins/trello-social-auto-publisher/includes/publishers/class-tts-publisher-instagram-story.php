<?php
/**
 * Instagram Story publisher.
 *
 * @package TrelloSocialAutoPublisher\Publishers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles publishing Instagram Stories.
 */
class TTS_Publisher_Instagram_Story {

    /**
     * Upload media to Instagram Stories.
     *
     * @param string $media_path Remote media URL.
     * @param array  $context    Additional context (post ID, credentials, etc.).
     * @return array Result data with success flag.
     */
    public function upload_media( $media_path, array $context = array() ) {
        $post_id     = isset( $context['post_id'] ) ? absint( $context['post_id'] ) : 0;
        $credentials = $context['credentials'] ?? '';
        $ig_user_id  = $context['ig_user_id'] ?? '';
        $token       = $context['token'] ?? '';

        if ( ( empty( $ig_user_id ) || empty( $token ) ) && ! empty( $credentials ) ) {
            list( $cred_user_id, $cred_token ) = array_pad( explode( '|', $credentials, 2 ), 2, '' );
            if ( empty( $ig_user_id ) ) {
                $ig_user_id = $cred_user_id;
            }
            if ( empty( $token ) ) {
                $token = $cred_token;
            }
        }

        if ( empty( $ig_user_id ) || empty( $token ) || empty( $media_path ) ) {
            $error = __( 'Missing credentials or media for Instagram Story', 'fp-publisher' );
            tts_log_event( $post_id, 'instagram_story', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => empty( $media_path ) ? 'instagram_story_missing_data' : 'instagram_story_bad_credentials',
            );
        }

        $mime     = wp_check_filetype( $media_path );
        $endpoint = sprintf( 'https://graph.facebook.com/%s/media', $ig_user_id );
        $body     = array(
            'access_token' => $token,
            'media_type'   => 'STORIES',
        );

        if ( ! empty( $mime['type'] ) && 0 === strpos( $mime['type'], 'image/' ) ) {
            $body['image_url'] = $media_path;
        } else {
            $body['video_url'] = $media_path;
        }

        $result = wp_remote_post(
            $endpoint,
            array(
                'body'    => $body,
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $result ) ) {
            $error = $result->get_error_message();
            tts_log_event( $post_id, 'instagram_story', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'instagram_story_request_error',
            );
        }

        $data = json_decode( wp_remote_retrieve_body( $result ), true );
        $code = wp_remote_retrieve_response_code( $result );

        if ( 200 !== $code || empty( $data['id'] ) ) {
            $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'fp-publisher' );
            tts_log_event( $post_id, 'instagram_story', 'error', $error, $data );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'instagram_story_error',
                'error_data' => $data,
            );
        }

        $publish_endpoint = sprintf( 'https://graph.facebook.com/%s/media_publish', $ig_user_id );
        $publish_result   = wp_remote_post(
            $publish_endpoint,
            array(
                'body'    => array(
                    'creation_id'  => $data['id'],
                    'access_token' => $token,
                ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $publish_result ) ) {
            $error = $publish_result->get_error_message();
            tts_log_event( $post_id, 'instagram_story', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'instagram_story_publish_request_error',
            );
        }

        $publish_data = json_decode( wp_remote_retrieve_body( $publish_result ), true );
        $publish_code = wp_remote_retrieve_response_code( $publish_result );

        if ( 200 !== $publish_code || empty( $publish_data['id'] ) ) {
            $error = isset( $publish_data['error']['message'] ) ? $publish_data['error']['message'] : __( 'Unknown error', 'fp-publisher' );
            tts_log_event( $post_id, 'instagram_story', 'error', $error, $publish_data );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'instagram_story_error',
                'error_data' => $publish_data,
            );
        }

        return array(
            'success' => true,
            'data'    => $publish_data,
        );
    }

    /**
     * Publish a Story to Instagram.
     *
     * @param int    $post_id     Post ID.
     * @param string $credentials IG user ID and access token.
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
            tts_notify_publication( $post_id, 'error', 'instagram_story' );
            $error_code = $result['error_code'] ?? 'instagram_story_error';
            $error_data = $result['error_data'] ?? array();
            $error_msg  = $result['error'] ?? __( 'Unknown error', 'fp-publisher' );

            return new \WP_Error( $error_code, $error_msg, $error_data );
        }

        $data     = $result['data'] ?? array();
        $response = array(
            'message' => __( 'Published Instagram Story', 'fp-publisher' ),
            'id'      => $data['id'] ?? '',
        );

        tts_log_event( $post_id, 'instagram_story', 'success', $response['message'], $data );
        tts_notify_publication( $post_id, 'success', 'instagram_story' );

        return $response;
    }
}
