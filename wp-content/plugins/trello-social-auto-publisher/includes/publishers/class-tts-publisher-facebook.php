<?php
/**
 * Facebook publisher.
 *
 * @package TrelloSocialAutoPublisher\Publishers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles publishing to Facebook.
 */
class TTS_Publisher_Facebook {

    /**
     * Upload a single media item to Facebook.
     *
     * @param string $media_path Remote URL to the media item.
     * @param array  $context    Additional context (post_id, credentials, message, etc.).
     * @return array Result data with success flag.
     */
    public function upload_media( $media_path, array $context = array() ) {
        $post_id     = isset( $context['post_id'] ) ? absint( $context['post_id'] ) : 0;
        $credentials = $context['credentials'] ?? '';

        list( $page_id, $token ) = $this->resolve_credentials( $credentials, $post_id, $context );

        if ( empty( $page_id ) || empty( $token ) ) {
            $error = __( 'Invalid Facebook credentials', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'facebook_bad_credentials',
            );
        }

        if ( empty( $media_path ) ) {
            $error = __( 'Missing media path for Facebook upload', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'facebook_missing_media',
            );
        }

        $media_type = strtolower( $context['media_type'] ?? '' );
        if ( ! $media_type ) {
            $filetype = wp_check_filetype( $media_path );
            if ( ! empty( $filetype['type'] ) ) {
                if ( 0 === strpos( $filetype['type'], 'video/' ) ) {
                    $media_type = 'video';
                } elseif ( 0 === strpos( $filetype['type'], 'image/' ) ) {
                    $media_type = 'image';
                }
            }
        }

        if ( 'photo' === $media_type ) {
            $media_type = 'image';
        }

        if ( ! in_array( $media_type, array( 'video', 'image' ), true ) ) {
            $media_type = 'image';
        }

        $message = $context['message'] ?? '';
        $lat     = $context['lat'] ?? ( $post_id ? get_post_meta( $post_id, '_tts_lat', true ) : '' );
        $lng     = $context['lng'] ?? ( $post_id ? get_post_meta( $post_id, '_tts_lng', true ) : '' );

        if ( 'video' === $media_type ) {
            $endpoint = sprintf( 'https://graph.facebook.com/%s/videos', $page_id );
            $body     = array(
                'access_token' => $token,
                'file_url'     => $media_path,
            );

            if ( '' !== $message ) {
                $body['description'] = $message;
            }
        } else {
            $endpoint = sprintf( 'https://graph.facebook.com/%s/photos', $page_id );
            $body     = array(
                'access_token' => $token,
                'source'       => $media_path,
            );

            if ( '' !== $message ) {
                $body['message'] = $message;
            }
        }

        if ( $lat && $lng ) {
            $body['location'] = array(
                'latitude'  => $lat,
                'longitude' => $lng,
            );
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
            tts_log_event( $post_id, 'facebook', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'facebook_request_error',
            );
        }

        $code = wp_remote_retrieve_response_code( $result );
        $data = json_decode( wp_remote_retrieve_body( $result ), true );

        if ( 200 !== $code || empty( $data['id'] ) ) {
            $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook', 'error', $error, $data );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'facebook_error',
                'error_data' => $data,
            );
        }

        return array(
            'success'    => true,
            'data'       => $data,
            'media_type' => $media_type,
            'endpoint'   => $endpoint,
        );
    }

    /**
     * Publish the post to Facebook.
     *
     * Requires the `pages_manage_posts` permission to publish and
     * `pages_read_engagement` to read the response from the API.
     *
     * @param int    $post_id     Post ID.
     * @param mixed  $credentials Credentials used for publishing.
     * @param string $message     Message to publish.
     * @return string Log message.
     */
    public function publish( $post_id, $credentials, $message ) {
        if ( empty( $credentials ) ) {
            $error = __( 'Facebook token missing', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'facebook' );
            return new \WP_Error( 'facebook_no_token', $error );
        }

        $token   = $credentials;
        $page_id = '';

        // Allow credentials in the form page_id|token for backward compatibility.
        if ( false !== strpos( $credentials, '|' ) ) {
            list( $page_id, $token ) = array_pad( explode( '|', $credentials, 2 ), 2, '' );
        } else {
            // Retrieve the page ID from post meta when not included in the token.
            $page_id = get_post_meta( $post_id, '_tts_fb_page_id', true );
        }

        if ( empty( $page_id ) || empty( $token ) ) {
            $error = __( 'Invalid Facebook credentials', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'facebook' );
            return new \WP_Error( 'facebook_bad_credentials', $error );
        }

        $lat = get_post_meta( $post_id, '_tts_lat', true );
        $lng = get_post_meta( $post_id, '_tts_lng', true );

        $attachment_ids = get_post_meta( $post_id, '_tts_attachment_ids', true );
        $attachment_ids = is_array( $attachment_ids ) ? array_map( 'intval', $attachment_ids ) : array();
        $resized_urls   = get_post_meta( $post_id, '_tts_resized_facebook', true );
        $resized_urls   = is_array( $resized_urls ) ? $resized_urls : array();
        $images         = array();
        $videos         = array();
        foreach ( $attachment_ids as $att_id ) {
            $mime = get_post_mime_type( $att_id );
            if ( $mime && 0 === strpos( $mime, 'image/' ) ) {
                $images[ $att_id ] = isset( $resized_urls[ $att_id ] ) ? $resized_urls[ $att_id ] : wp_get_attachment_url( $att_id );
            } elseif ( $mime && 0 === strpos( $mime, 'video/' ) ) {
                $videos[] = $att_id;
            }
        }

        if ( empty( $images ) && empty( $videos ) ) {
            $manual_id = (int) get_post_meta( $post_id, '_tts_manual_media', true );
            if ( $manual_id ) {
                $mime = get_post_mime_type( $manual_id );
                if ( $mime && 0 === strpos( $mime, 'image/' ) ) {
                    $images[ $manual_id ] = isset( $resized_urls[ $manual_id ] ) ? $resized_urls[ $manual_id ] : wp_get_attachment_url( $manual_id );
                } elseif ( $mime && 0 === strpos( $mime, 'video/' ) ) {
                    $videos[] = $manual_id;
                }
            }
        }

        if ( empty( $images ) && empty( $videos ) ) {
            $endpoint = sprintf( 'https://graph.facebook.com/%s/feed', $page_id );
            $link     = get_permalink( $post_id );
            $body     = array(
                'message'      => $message,
                'access_token' => $token,
            );
            if ( $link ) {
                $body['link'] = $link;
            }
            if ( $lat && $lng ) {
                $body['location'] = array(
                    'latitude'  => $lat,
                    'longitude' => $lng,
                );
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
                tts_log_event( $post_id, 'facebook', 'error', $error, '' );
                tts_notify_publication( $post_id, 'error', 'facebook' );
                return $result;
            }
            $code = wp_remote_retrieve_response_code( $result );
            $data = json_decode( wp_remote_retrieve_body( $result ), true );
            if ( 200 === $code && isset( $data['id'] ) ) {
                $response = __( 'Published to Facebook', 'trello-social-auto-publisher' );
                tts_log_event( $post_id, 'facebook', 'success', $response, $data );
                tts_notify_publication( $post_id, 'success', 'facebook' );
                return $response;
            }
            $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook', 'error', $error, $data );
            tts_notify_publication( $post_id, 'error', 'facebook' );
            return new \WP_Error( 'facebook_error', $error, $data );
        }

        $last_response  = array();
        $common_context = array(
            'post_id'     => $post_id,
            'credentials' => $credentials,
            'token'       => $token,
            'page_id'     => $page_id,
            'lat'         => $lat,
            'lng'         => $lng,
        );

        foreach ( $videos as $index => $video_id ) {
            $video_url    = wp_get_attachment_url( $video_id );
            $video_result = $this->upload_media(
                $video_url,
                array_merge(
                    $common_context,
                    array(
                        'media_type' => 'video',
                        'message'    => 0 === $index ? $message : '',
                        'media_id'   => $video_id,
                    )
                )
            );

            if ( empty( $video_result['success'] ) ) {
                tts_notify_publication( $post_id, 'error', 'facebook' );
                $error_code = $video_result['error_code'] ?? 'facebook_error';
                $error_data = $video_result['error_data'] ?? array();
                $error_msg  = $video_result['error'] ?? __( 'Unknown error', 'trello-social-auto-publisher' );

                return new \WP_Error( $error_code, $error_msg, $error_data );
            }

            $last_response = $video_result['data'];
        }

        foreach ( $images as $image_id => $image_url ) {
            $image_result = $this->upload_media(
                $image_url,
                array_merge(
                    $common_context,
                    array(
                        'media_type' => 'image',
                        'message'    => ( empty( $videos ) && $image_id === array_key_first( $images ) ) ? $message : '',
                        'media_id'   => $image_id,
                    )
                )
            );

            if ( empty( $image_result['success'] ) ) {
                tts_notify_publication( $post_id, 'error', 'facebook' );
                $error_code = $image_result['error_code'] ?? 'facebook_error';
                $error_data = $image_result['error_data'] ?? array();
                $error_msg  = $image_result['error'] ?? __( 'Unknown error', 'trello-social-auto-publisher' );

                return new \WP_Error( $error_code, $error_msg, $error_data );
            }

            $last_response = $image_result['data'];
        }

        $response = __( 'Published to Facebook', 'trello-social-auto-publisher' );
        tts_log_event( $post_id, 'facebook', 'success', $response, $last_response );
        tts_notify_publication( $post_id, 'success', 'facebook' );
        return $response;
    }

    /**
     * Resolve Facebook page credentials.
     *
     * @param string $credentials Raw credentials string.
     * @param int    $post_id     Related post ID.
     * @param array  $context     Additional context.
     * @return array Array with page ID and token.
     */
    private function resolve_credentials( $credentials, $post_id, array $context = array() ) {
        $page_id = $context['page_id'] ?? '';
        $token   = $context['token'] ?? '';

        if ( empty( $page_id ) || empty( $token ) ) {
            if ( ! empty( $credentials ) ) {
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
        }

        if ( empty( $page_id ) && $post_id ) {
            $page_id = get_post_meta( $post_id, '_tts_fb_page_id', true );
        }

        return array( $page_id, $token );
    }
}
