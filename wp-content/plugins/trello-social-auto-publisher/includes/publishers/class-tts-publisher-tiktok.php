<?php
/**
 * TikTok publisher.
 *
 * @package TrelloSocialAutoPublisher\Publishers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles publishing to TikTok.
 */
class TTS_Publisher_TikTok {

    /**
     * Upload a single TikTok video.
     *
     * @param string $media_path Absolute path or URL to the video file.
     * @param array  $context    Additional context (post ID, token, message, etc.).
     * @return array Result data with success flag.
     */
    public function upload_media( $media_path, array $context = array() ) {
        $post_id = isset( $context['post_id'] ) ? absint( $context['post_id'] ) : 0;
        $token   = $context['token'] ?? ( $context['credentials'] ?? '' );

        if ( empty( $token ) ) {
            $error = __( 'TikTok token missing or lacks video.upload scope', 'fp-publisher' );
            tts_log_event( $post_id, 'tiktok', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'tiktok_no_token',
            );
        }

        if ( empty( $media_path ) ) {
            $error = __( 'Video file not found', 'fp-publisher' );
            tts_log_event( $post_id, 'tiktok', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'tiktok_video_missing',
            );
        }

        $video_path = '';
        $cleanup    = false;

        if ( file_exists( $media_path ) ) {
            $video_path = $media_path;
        } elseif ( ! empty( $context['media_id'] ) ) {
            $possible_path = get_attached_file( (int) $context['media_id'] );
            if ( $possible_path && file_exists( $possible_path ) ) {
                $video_path = $possible_path;
            }
        }

        if ( empty( $video_path ) && filter_var( $media_path, FILTER_VALIDATE_URL ) ) {
            $downloaded = download_url( $media_path );
            if ( is_wp_error( $downloaded ) ) {
                $error = $downloaded->get_error_message();
                tts_log_event( $post_id, 'tiktok', 'error', $error, '' );

                return array(
                    'success'    => false,
                    'error'      => $error,
                    'error_code' => 'tiktok_video_download_failed',
                );
            }

            $video_path = $downloaded;
            $cleanup    = true;
        }

        if ( empty( $video_path ) || ! file_exists( $video_path ) ) {
            $error = __( 'Video file not found', 'fp-publisher' );
            tts_log_event( $post_id, 'tiktok', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'tiktok_video_missing',
            );
        }

        $video_contents = file_get_contents( $video_path );

        if ( false === $video_contents ) {
            if ( $cleanup ) {
                @unlink( $video_path );
            }

            $error = __( 'Unable to read video file', 'fp-publisher' );
            tts_log_event( $post_id, 'tiktok', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'tiktok_video_read_error',
            );
        }

        $upload_result = wp_remote_post(
            'https://open.tiktokapis.com/v2/video/upload/',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'video/mp4',
                ),
                'body'    => $video_contents,
                'timeout' => 60,
            )
        );

        if ( $cleanup ) {
            @unlink( $video_path );
        }

        if ( is_wp_error( $upload_result ) ) {
            $error = $upload_result->get_error_message();
            tts_log_event( $post_id, 'tiktok', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'tiktok_upload_error',
            );
        }

        $upload_code = wp_remote_retrieve_response_code( $upload_result );
        $upload_data = json_decode( wp_remote_retrieve_body( $upload_result ), true );

        if ( 200 !== $upload_code || empty( $upload_data['data']['video_id'] ) ) {
            $error = isset( $upload_data['error']['message'] ) ? $upload_data['error']['message'] : __( 'Unknown error', 'fp-publisher' );
            tts_log_event( $post_id, 'tiktok', 'error', $error, $upload_data );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'tiktok_upload_error',
                'error_data' => $upload_data,
            );
        }

        $message = $context['message'] ?? '';
        $lat     = $context['lat'] ?? ( $post_id ? get_post_meta( $post_id, '_tts_lat', true ) : '' );
        $lng     = $context['lng'] ?? ( $post_id ? get_post_meta( $post_id, '_tts_lng', true ) : '' );

        $publish_body = array(
            'video_id' => $upload_data['data']['video_id'],
            'caption'  => $message,
        );

        if ( $lat && $lng ) {
            $publish_body['location'] = array(
                'latitude'  => $lat,
                'longitude' => $lng,
            );
        }

        $publish_result = wp_remote_post(
            'https://open.tiktokapis.com/v2/video/publish/',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $publish_body ),
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $publish_result ) ) {
            $error = $publish_result->get_error_message();
            tts_log_event( $post_id, 'tiktok', 'error', $error, '' );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'tiktok_publish_error',
            );
        }

        $publish_code = wp_remote_retrieve_response_code( $publish_result );
        $publish_data = json_decode( wp_remote_retrieve_body( $publish_result ), true );

        if ( 200 !== $publish_code || empty( $publish_data['data']['video_id'] ) ) {
            $error = isset( $publish_data['error']['message'] ) ? $publish_data['error']['message'] : __( 'Unknown error', 'fp-publisher' );
            tts_log_event( $post_id, 'tiktok', 'error', $error, $publish_data );

            return array(
                'success'    => false,
                'error'      => $error,
                'error_code' => 'tiktok_publish_error',
                'error_data' => $publish_data,
            );
        }

        return array(
            'success'  => true,
            'data'     => $publish_data,
            'video_id' => $upload_data['data']['video_id'],
        );
    }


    /**
     * Publish the post to TikTok.
     *
     * Requires an OAuth 2.0 access token granted with the `video.upload`
     * scope in order to send media and create the video post.
     *
     * @param int    $post_id Post ID.
     * @param string $token   OAuth 2.0 access token.
     * @param string $message Video description to publish.
     * @return string|\WP_Error Log message.
     */
    public function publish( $post_id, $token, $message ) {
        if ( empty( $token ) ) {
            $error = __( 'TikTok token missing or lacks video.upload scope', 'fp-publisher' );
            tts_log_event( $post_id, 'tiktok', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'tiktok' );
            return new \WP_Error( 'tiktok_no_token', $error );
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
            $error = __( 'No video to publish', 'fp-publisher' );
            tts_log_event( $post_id, 'tiktok', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'tiktok' );
            return new \WP_Error( 'tiktok_no_video', $error );
        }
        foreach ( $videos as $video_id ) {
            $video_path    = get_attached_file( $video_id );
            $upload_result = $this->upload_media(
                $video_path,
                array(
                    'post_id'   => $post_id,
                    'token'     => $token,
                    'message'   => $message,
                    'lat'       => $lat,
                    'lng'       => $lng,
                    'media_id'  => $video_id,
                )
            );

            if ( empty( $upload_result['success'] ) ) {
                tts_notify_publication( $post_id, 'error', 'tiktok' );
                $error_code = $upload_result['error_code'] ?? 'tiktok_error';
                $error_data = $upload_result['error_data'] ?? array();
                $error_msg  = $upload_result['error'] ?? __( 'Unknown error', 'fp-publisher' );

                return new \WP_Error( $error_code, $error_msg, $error_data );
            }
        }
        $response = __( 'Published to TikTok', 'fp-publisher' );
        tts_log_event( $post_id, 'tiktok', 'success', $response, '' );
        tts_notify_publication( $post_id, 'success', 'tiktok' );
        return $response;
    }
}
