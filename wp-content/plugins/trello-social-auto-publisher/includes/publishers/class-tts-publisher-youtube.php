<?php
/**
 * YouTube publisher.
 *
 * @package TrelloSocialAutoPublisher\Publishers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles publishing to YouTube.
 */
class TTS_Publisher_YouTube {

    /**
     * Upload a single YouTube Shorts video.
     *
     * @param string $media_path Absolute path or URL to the video file.
     * @param array  $context    Additional context (post ID, credentials, etc.).
     * @return array Result data with success flag.
     */
    public function upload_media( $media_path, array $context = array() ) {
        $post_id     = isset( $context['post_id'] ) ? absint( $context['post_id'] ) : 0;
        $credentials = $context['credentials'] ?? '';
        $client_id   = $context['client_id'] ?? '';
        $message     = $context['message'] ?? '';
        $title       = $context['title'] ?? ( $post_id ? get_the_title( $post_id ) : '' );
        $lat         = $context['lat'] ?? ( $post_id ? get_post_meta( $post_id, '_tts_lat', true ) : '' );
        $lng         = $context['lng'] ?? ( $post_id ? get_post_meta( $post_id, '_tts_lng', true ) : '' );
        $token       = $context['token'] ?? '';
        $creds       = $context['creds'] ?? null;

        if ( empty( $token ) ) {
            $source_credentials = null !== $creds ? $creds : $credentials;
            list( $token, $creds, $client_id ) = $this->resolve_access_token( $source_credentials, $post_id, $client_id );
        }

        if ( empty( $token ) ) {
            $error = __( 'Unable to obtain YouTube access token', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'youtube', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'youtube_no_access_token',
            );
        }

        $video_path = '';
        $cleanup    = false;

        if ( $media_path && file_exists( $media_path ) ) {
            $video_path = $media_path;
        } elseif ( ! empty( $context['media_id'] ) ) {
            $possible_path = get_attached_file( (int) $context['media_id'] );
            if ( $possible_path && file_exists( $possible_path ) ) {
                $video_path = $possible_path;
            }
        }

        if ( empty( $video_path ) && $media_path && filter_var( $media_path, FILTER_VALIDATE_URL ) ) {
            $downloaded = download_url( $media_path );
            if ( is_wp_error( $downloaded ) ) {
                $error = $downloaded->get_error_message();
                tts_log_event( $post_id, 'youtube', 'error', $error, '' );

                return array(
                    'success'    => false,
                    'error'      => $error,
                    'error_code' => 'youtube_video_download_failed',
                );
            }
            $video_path = $downloaded;
            $cleanup    = true;
        }

        if ( empty( $video_path ) || ! file_exists( $video_path ) ) {
            $error = __( 'Video file not found', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'youtube', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'youtube_video_missing',
            );
        }

        $snippet = array(
            'title'           => $title,
            'description'     => $message,
            'categoryId'      => '22',
            'shortsVideoType' => 'SHORTS',
        );
        $status = array(
            'privacyStatus'  => 'public',
            'shortsEligible' => true,
        );
        $metadata = array(
            'snippet' => $snippet,
            'status'  => $status,
        );

        if ( $lat && $lng ) {
            $metadata['recordingDetails'] = array(
                'location' => array(
                    'latitude'  => (float) $lat,
                    'longitude' => (float) $lng,
                ),
            );
        }

        $endpoint = 'https://www.googleapis.com/upload/youtube/v3/videos?part=snippet,status';
        if ( $lat && $lng ) {
            $endpoint .= ',recordingDetails';
        }
        $endpoint .= '&uploadType=resumable';

        $init = wp_remote_request(
            $endpoint,
            array(
                'method'  => 'POST',
                'headers' => array(
                    'Authorization'           => 'Bearer ' . $token,
                    'Content-Type'            => 'application/json; charset=UTF-8',
                    'X-Upload-Content-Type'   => 'video/mp4',
                    'X-Upload-Content-Length' => filesize( $video_path ),
                ),
                'body'    => wp_json_encode( $metadata ),
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $init ) ) {
            if ( $cleanup ) {
                @unlink( $video_path );
            }
            $error = $init->get_error_message();
            tts_log_event( $post_id, 'youtube', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'youtube_request_error',
            );
        }

        $upload_url = wp_remote_retrieve_header( $init, 'location' );
        if ( empty( $upload_url ) ) {
            if ( $cleanup ) {
                @unlink( $video_path );
            }
            $error = __( 'Upload URL missing', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'youtube', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'youtube_no_upload_url',
            );
        }

        $video_body = file_get_contents( $video_path );
        if ( $cleanup ) {
            @unlink( $video_path );
        }

        if ( false === $video_body ) {
            $error = __( 'Unable to read video file', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'youtube', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'youtube_video_read_error',
            );
        }

        $upload = wp_remote_request(
            $upload_url,
            array(
                'method'  => 'PUT',
                'headers' => array(
                    'Content-Type'   => 'video/mp4',
                    'Content-Length' => strlen( $video_body ),
                ),
                'body'    => $video_body,
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $upload ) ) {
            $error = $upload->get_error_message();
            tts_log_event( $post_id, 'youtube', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'youtube_error',
            );
        }

        $code = wp_remote_retrieve_response_code( $upload );
        $data = json_decode( wp_remote_retrieve_body( $upload ), true );

        if ( 200 !== $code || empty( $data['id'] ) ) {
            $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'youtube', 'error', $error, $data );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'youtube_error',
                'error_data' => $data,
            );
        }

        return array(
            'success' => true,
            'data'    => $data,
        );
    }

    /**
     * Publish the post to YouTube Shorts.
     *
     * @param int    $post_id     Post ID.
     * @param string $credentials OAuth 2.0 credentials or access token.
     * @param string $message     Message to publish.
     * @return string|\WP_Error  Log message.
     */
    public function publish( $post_id, $credentials, $message ) {
        if ( empty( $credentials ) ) {
            $error = __( 'YouTube token missing', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'youtube', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'youtube' );
            return new \WP_Error( 'youtube_no_token', $error );
        }

        list( $access_tok, $creds, $client_id ) = $this->resolve_access_token( $credentials, $post_id );

        if ( empty( $access_tok ) ) {
            $error = __( 'Unable to obtain YouTube access token', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'youtube', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'youtube' );
            return new \WP_Error( 'youtube_no_access_token', $error );
        }

        $lat = get_post_meta( $post_id, '_tts_lat', true );
        $lng = get_post_meta( $post_id, '_tts_lng', true );

        $attachment_ids = get_post_meta( $post_id, '_tts_attachment_ids', true );
        $attachment_ids = is_array( $attachment_ids ) ? array_map( 'intval', $attachment_ids ) : array();
        $videos         = array();
        foreach ( $attachment_ids as $att_id ) {
            $mime = get_post_mime_type( $att_id );
            if ( $mime && 0 === strpos( $mime, 'video/' ) ) {
                $videos[] = $att_id;
            }
        }
        if ( empty( $videos ) ) {
            $manual_id = (int) get_post_meta( $post_id, '_tts_manual_media', true );
            if ( $manual_id && 0 === strpos( (string) get_post_mime_type( $manual_id ), 'video/' ) ) {
                $videos[] = $manual_id;
            }
        }
        if ( empty( $videos ) ) {
            $error = __( 'No video to publish', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'youtube', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'youtube' );
            return new \WP_Error( 'youtube_no_video', $error );
        }
        foreach ( $videos as $video_id ) {
            $video_path    = get_attached_file( $video_id );
            $upload_result = $this->upload_media(
                $video_path,
                array(
                    'post_id'     => $post_id,
                    'token'       => $access_tok,
                    'credentials' => $creds,
                    'client_id'   => $client_id,
                    'message'     => $message,
                    'title'       => get_the_title( $post_id ),
                    'lat'         => $lat,
                    'lng'         => $lng,
                    'media_id'    => $video_id,
                )
            );

            if ( empty( $upload_result['success'] ) ) {
                tts_notify_publication( $post_id, 'error', 'youtube' );
                $error_code = $upload_result['error_code'] ?? 'youtube_error';
                $error_data = $upload_result['error_data'] ?? array();
                $error_msg  = $upload_result['error'] ?? __( 'Unknown error', 'trello-social-auto-publisher' );

                return new \WP_Error( $error_code, $error_msg, $error_data );
            }
        }
        $response = __( 'Published to YouTube', 'trello-social-auto-publisher' );
        tts_log_event( $post_id, 'youtube', 'success', $response, '' );
        tts_notify_publication( $post_id, 'success', 'youtube' );
        return $response;
}

    /**
     * Resolve YouTube access token from credentials.
     *
     * @param mixed $credentials Raw credentials data.
     * @param int   $post_id     Related post ID.
     * @param mixed $client_id   Optional client post ID storing the token.
     * @return array Array containing access token, normalized credentials, and client ID.
     */
    private function resolve_access_token( $credentials, $post_id, $client_id = '' ) {
        $client_id = $client_id ?: get_post_meta( $post_id, '_tts_client_id', true );
        $creds     = is_string( $credentials ) ? json_decode( $credentials, true ) : $credentials;
        $access_tok = '';

        if ( is_array( $creds ) ) {
            $access_tok = $creds['access_token'] ?? '';

            if ( empty( $access_tok ) && ! empty( $creds['refresh_token'] ) && ! empty( $creds['client_id'] ) && ! empty( $creds['client_secret'] ) ) {
                $token_resp = wp_remote_post(
                    'https://oauth2.googleapis.com/token',
                    array(
                        'body'    => array(
                            'client_id'     => $creds['client_id'],
                            'client_secret' => $creds['client_secret'],
                            'refresh_token' => $creds['refresh_token'],
                            'grant_type'    => 'refresh_token',
                            'scope'         => 'https://www.googleapis.com/auth/youtube.upload',
                        ),
                        'timeout' => 20,
                    )
                );

                if ( ! is_wp_error( $token_resp ) ) {
                    $token_body = json_decode( wp_remote_retrieve_body( $token_resp ), true );
                    if ( ! empty( $token_body['access_token'] ) ) {
                        $access_tok            = $token_body['access_token'];
                        $creds['access_token'] = $access_tok;

                        if ( $client_id ) {
                            update_post_meta( $client_id, '_tts_yt_token', wp_json_encode( $creds ) );
                        }
                    }
                }
            }
        } else {
            $access_tok = $credentials;
        }

        return array( $access_tok, $creds, $client_id );
    }
}
