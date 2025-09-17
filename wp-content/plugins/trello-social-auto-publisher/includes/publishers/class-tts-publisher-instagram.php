<?php
/**
 * Instagram publisher.
 *
 * @package TrelloSocialAutoPublisher\Publishers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles publishing to Instagram.
 */
class TTS_Publisher_Instagram {

    /**
     * Stored access token for subsequent API calls.
     *
     * @var string
     */
    private $token = '';

    /**
     * Last post ID used for logging.
     *
     * @var int
     */
    private $post_id = 0;

    /**
     * Upload a single media item to Instagram.
     *
     * @param string $media_path Remote URL of the media item.
     * @param array  $context    Additional context such as post ID and credentials.
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

        if ( empty( $ig_user_id ) || empty( $token ) ) {
            $error = __( 'Invalid Instagram credentials', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'instagram_bad_credentials',
            );
        }

        if ( empty( $media_path ) ) {
            $error = __( 'No image or video to publish', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'instagram_no_media',
            );
        }

        $message    = $context['message'] ?? '';
        $lat        = $context['lat'] ?? ( $post_id ? get_post_meta( $post_id, '_tts_lat', true ) : '' );
        $lng        = $context['lng'] ?? ( $post_id ? get_post_meta( $post_id, '_tts_lng', true ) : '' );
        $media_type = strtoupper( $context['media_type'] ?? '' );

        if ( ! $media_type ) {
            $filetype = wp_check_filetype( $media_path );
            if ( ! empty( $filetype['type'] ) && 0 === strpos( $filetype['type'], 'video/' ) ) {
                $media_type = 'VIDEO';
            } else {
                $media_type = 'IMAGE';
            }
        }

        if ( ! in_array( $media_type, array( 'IMAGE', 'VIDEO' ), true ) ) {
            $media_type = 'IMAGE';
        }

        $endpoint = sprintf( 'https://graph.facebook.com/%s/media', $ig_user_id );
        $body     = array(
            'caption'      => $message,
            'access_token' => $token,
        );

        if ( $lat && $lng ) {
            $body['location'] = array(
                'latitude'  => $lat,
                'longitude' => $lng,
            );
        }

        if ( 'IMAGE' === $media_type ) {
            $body['image_url'] = $media_path;
        } else {
            $body['media_type'] = 'VIDEO';
            $body['video_url']  = $media_path;
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
            tts_log_event( $post_id, 'instagram', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'instagram_request_error',
            );
        }

        $code = wp_remote_retrieve_response_code( $result );
        $data = json_decode( wp_remote_retrieve_body( $result ), true );

        if ( 200 !== $code || empty( $data['id'] ) ) {
            $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'error', $error, $data );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'instagram_error',
                'error_data' => $data,
            );
        }

        $publish_endpoint = sprintf( 'https://graph.facebook.com/%s/media_publish', $ig_user_id );
        $publish_body     = array(
            'creation_id'  => $data['id'],
            'access_token' => $token,
        );

        $publish_result = wp_remote_post(
            $publish_endpoint,
            array(
                'body'    => $publish_body,
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $publish_result ) ) {
            $error = $publish_result->get_error_message();
            tts_log_event( $post_id, 'instagram', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'instagram_publish_request_error',
            );
        }

        $publish_code = wp_remote_retrieve_response_code( $publish_result );
        $publish_data = json_decode( wp_remote_retrieve_body( $publish_result ), true );

        if ( 200 !== $publish_code || empty( $publish_data['id'] ) ) {
            $error = isset( $publish_data['error']['message'] ) ? $publish_data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'error', $error, $publish_data );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'instagram_error',
                'error_data' => $publish_data,
            );
        }

        $this->post_id = $post_id ?: $this->post_id;
        $this->token   = $token;

        return array(
            'success'      => true,
            'data'         => $publish_data,
            'creation_id'  => $data['id'],
            'media_type'   => $media_type,
        );
    }

    /**
     * Publish the post to Instagram.
     *
     * Credentials must be provided in the form `{ig-user-id}|{access-token}` where `ig-user-id` is
     * the Instagram Business account ID. The access token requires the following permissions:
     * `instagram_basic`, `pages_show_list`, `pages_read_engagement`, and `pages_manage_posts`.
     *
     * @param int         $post_id     Post ID.
     * @param string      $credentials Instagram user ID and access token.
     * @param string      $message     Message to publish.
     * @return array|\WP_Error Log data or error.
     */
    public function publish( $post_id, $credentials, $message ) {
        $this->post_id = $post_id;
        if ( empty( $credentials ) ) {
            $message = __( 'Instagram token missing', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'error', $message, '' );
            tts_notify_publication( $post_id, 'error', 'instagram' );
            return new \WP_Error( 'instagram_no_token', $message );
        }

        list( $ig_user_id, $token ) = array_pad( explode( '|', $credentials, 2 ), 2, '' );
        if ( empty( $ig_user_id ) || empty( $token ) ) {
            $error = __( 'Invalid Instagram credentials', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'instagram' );
            return new \WP_Error( 'instagram_bad_credentials', $error );
        }

        $this->token = $token;

        $lat = get_post_meta( $post_id, '_tts_lat', true );
        $lng = get_post_meta( $post_id, '_tts_lng', true );

        $attachment_ids = get_post_meta( $post_id, '_tts_attachment_ids', true );
        $attachment_ids = is_array( $attachment_ids ) ? array_map( 'intval', $attachment_ids ) : array();
        $resized_urls   = get_post_meta( $post_id, '_tts_resized_instagram', true );
        $resized_urls   = is_array( $resized_urls ) ? $resized_urls : array();
        $media_items    = array();
        foreach ( $attachment_ids as $att_id ) {
            $mime = get_post_mime_type( $att_id );
            if ( $mime && 0 === strpos( $mime, 'image/' ) ) {
                $media_items[] = array(
                    'type' => 'IMAGE',
                    'url'  => isset( $resized_urls[ $att_id ] ) ? $resized_urls[ $att_id ] : wp_get_attachment_url( $att_id ),
                );
            } elseif ( $mime && 0 === strpos( $mime, 'video/' ) ) {
                $media_items[] = array(
                    'type' => 'VIDEO',
                    'url'  => wp_get_attachment_url( $att_id ),
                );
            }
        }
        if ( empty( $media_items ) ) {
            $manual_id = (int) get_post_meta( $post_id, '_tts_manual_media', true );
            if ( $manual_id ) {
                $mime = get_post_mime_type( $manual_id );
                if ( $mime && 0 === strpos( $mime, 'image/' ) ) {
                    $url = isset( $resized_urls[ $manual_id ] ) ? $resized_urls[ $manual_id ] : wp_get_attachment_url( $manual_id );
                    $media_items[] = array(
                        'type' => 'IMAGE',
                        'url'  => wp_make_link_relative( $url ),
                    );
                } elseif ( $mime && 0 === strpos( $mime, 'video/' ) ) {
                    $media_items[] = array(
                        'type' => 'VIDEO',
                        'url'  => wp_make_link_relative( wp_get_attachment_url( $manual_id ) ),
                    );
                }
            }
        }
        if ( empty( $media_items ) ) {
            $error = __( 'No image or video to publish', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'instagram' );
            return new \WP_Error( 'instagram_no_media', $error );
        }
        $first_media_id = '';
        foreach ( $media_items as $index => $item ) {
            $upload_result = $this->upload_media(
                $item['url'],
                array(
                    'post_id'     => $post_id,
                    'credentials' => $credentials,
                    'ig_user_id'  => $ig_user_id,
                    'token'       => $token,
                    'media_type'  => $item['type'],
                    'message'     => 0 === $index ? $message : '',
                    'lat'         => $lat,
                    'lng'         => $lng,
                )
            );

            if ( empty( $upload_result['success'] ) ) {
                tts_notify_publication( $post_id, 'error', 'instagram' );
                $error_code = $upload_result['error_code'] ?? 'instagram_error';
                $error_data = $upload_result['error_data'] ?? array();
                $error_msg  = $upload_result['error'] ?? __( 'Unknown error', 'trello-social-auto-publisher' );

                return new \WP_Error( $error_code, $error_msg, $error_data );
            }

            if ( '' === $first_media_id ) {
                $first_media_id = $upload_result['data']['id'] ?? '';
            }
        }
        $response = array(
            'message' => __( 'Published to Instagram', 'trello-social-auto-publisher' ),
            'id'      => $first_media_id,
        );
        tts_log_event( $post_id, 'instagram', 'success', $response['message'], '' );
        tts_notify_publication( $post_id, 'success', 'instagram' );
        return $response;
    }

    /**
     * Post a comment to a published Instagram media.
     *
     * @param string $media_id Media ID returned by Instagram.
     * @param string $text     Comment text.
     * @return string|\WP_Error Result message or error.
     */
    public function post_comment( $media_id, $text ) {
        if ( empty( $this->token ) || empty( $media_id ) || empty( $text ) ) {
            return new \WP_Error( 'instagram_comment_missing_data', __( 'Missing data for Instagram comment', 'trello-social-auto-publisher' ) );
        }
        $endpoint = sprintf( 'https://graph.facebook.com/%s/comments', $media_id );
        $result   = wp_remote_post(
            $endpoint,
            array(
                'body'    => array(
                    'message'      => $text,
                    'access_token' => $this->token,
                ),
                'timeout' => 20,
            )
        );
        if ( is_wp_error( $result ) ) {
            $error = $result->get_error_message();
            tts_log_event( $this->post_id, 'instagram', 'error', $error, '' );
            return $result;
        }
        $code = wp_remote_retrieve_response_code( $result );
        $data = json_decode( wp_remote_retrieve_body( $result ), true );
        if ( 200 !== $code || empty( $data['id'] ) ) {
            $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
            tts_log_event( $this->post_id, 'instagram', 'error', $error, $data );
            return new \WP_Error( 'instagram_comment_error', $error, $data );
        }
        $success = __( 'Comment posted to Instagram', 'trello-social-auto-publisher' );
        tts_log_event( $this->post_id, 'instagram', 'success', $success, $data );
        return $success;
    }
}
