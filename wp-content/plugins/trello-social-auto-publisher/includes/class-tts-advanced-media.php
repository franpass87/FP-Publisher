<?php
/**
 * Advanced Media Management System
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles advanced media optimization, processing, and management.
 */
class TTS_Advanced_Media {

    /**
     * Platform-specific image dimensions.
     */
    private $platform_dimensions = array(
        'instagram' => array(
            'square' => array( 'width' => 1080, 'height' => 1080 ),
            'portrait' => array( 'width' => 1080, 'height' => 1350 ),
            'landscape' => array( 'width' => 1080, 'height' => 566 ),
            'story' => array( 'width' => 1080, 'height' => 1920 )
        ),
        'facebook' => array(
            'shared_image' => array( 'width' => 1200, 'height' => 630 ),
            'cover_photo' => array( 'width' => 1640, 'height' => 859 ),
            'event_image' => array( 'width' => 1920, 'height' => 1080 ),
            'story' => array( 'width' => 1080, 'height' => 1920 )
        ),
        'twitter' => array(
            'header' => array( 'width' => 1500, 'height' => 500 ),
            'in_stream' => array( 'width' => 1024, 'height' => 512 ),
            'card' => array( 'width' => 1200, 'height' => 628 )
        ),
        'linkedin' => array(
            'shared_image' => array( 'width' => 1200, 'height' => 627 ),
            'company_cover' => array( 'width' => 1536, 'height' => 768 ),
            'personal_cover' => array( 'width' => 1584, 'height' => 396 )
        ),
        'youtube' => array(
            'thumbnail' => array( 'width' => 1280, 'height' => 720 ),
            'channel_art' => array( 'width' => 2560, 'height' => 1440 ),
            'video_watermark' => array( 'width' => 150, 'height' => 150 )
        ),
        'tiktok' => array(
            'video' => array( 'width' => 1080, 'height' => 1920 ),
            'profile' => array( 'width' => 200, 'height' => 200 )
        )
    );

    /**
     * Initialize advanced media system.
     */
    public function __construct() {
        add_action( 'wp_ajax_tts_resize_image', array( $this, 'ajax_resize_image' ) );
        add_action( 'wp_ajax_tts_optimize_video', array( $this, 'ajax_optimize_video' ) );
        add_action( 'wp_ajax_tts_add_watermark', array( $this, 'ajax_add_watermark' ) );
        add_action( 'wp_ajax_tts_batch_process_media', array( $this, 'ajax_batch_process_media' ) );
        add_action( 'wp_ajax_tts_get_stock_photos', array( $this, 'ajax_get_stock_photos' ) );
        add_action( 'wp_ajax_tts_create_media_variations', array( $this, 'ajax_create_media_variations' ) );
        add_action( 'wp_ajax_tts_compress_media', array( $this, 'ajax_compress_media' ) );
        add_action( 'wp_ajax_tts_analyze_media_performance', array( $this, 'ajax_analyze_media_performance' ) );
        
        // Add custom image sizes
        add_action( 'init', array( $this, 'register_custom_image_sizes' ) );
        
        // Enhance media library
        add_filter( 'attachment_fields_to_edit', array( $this, 'add_media_fields' ), 10, 2 );
        add_filter( 'attachment_fields_to_save', array( $this, 'save_media_fields' ), 10, 2 );
    }

    /**
     * Register custom image sizes for social platforms.
     */
    public function register_custom_image_sizes() {
        // Instagram sizes
        add_image_size( 'instagram-square', 1080, 1080, true );
        add_image_size( 'instagram-portrait', 1080, 1350, true );
        add_image_size( 'instagram-landscape', 1080, 566, true );
        add_image_size( 'instagram-story', 1080, 1920, true );
        
        // Facebook sizes
        add_image_size( 'facebook-shared', 1200, 630, true );
        add_image_size( 'facebook-cover', 1640, 859, true );
        add_image_size( 'facebook-story', 1080, 1920, true );
        
        // Twitter sizes
        add_image_size( 'twitter-header', 1500, 500, true );
        add_image_size( 'twitter-card', 1200, 628, true );
        
        // LinkedIn sizes
        add_image_size( 'linkedin-shared', 1200, 627, true );
        add_image_size( 'linkedin-cover', 1536, 768, true );
        
        // YouTube sizes
        add_image_size( 'youtube-thumbnail', 1280, 720, true );
        add_image_size( 'youtube-channel-art', 2560, 1440, true );
        
        // TikTok sizes
        add_image_size( 'tiktok-video', 1080, 1920, true );
    }

    /**
     * Resize image for specific platform.
     */
    public function ajax_resize_image() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
        }

        $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
        $platform = sanitize_text_field( wp_unslash( $_POST['platform'] ?? '' ) );
        $format = sanitize_text_field( wp_unslash( $_POST['format'] ?? '' ) );

        if ( empty( $attachment_id ) || empty( $platform ) || empty( $format ) ) {
            wp_send_json_error( array( 'message' => __( 'Attachment ID, platform, and format are required.', 'fp-publisher' ) ) );
        }

        try {
            $resized_url = $this->resize_image_for_platform( $attachment_id, $platform, $format );
            
            wp_send_json_success( array(
                'resized_url' => $resized_url,
                'message' => __( 'Image resized successfully!', 'fp-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Media Resize Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to resize image. Please try again.', 'fp-publisher' ) ) );
        }
    }

    /**
     * Resize image for specific platform and format.
     *
     * @param int $attachment_id Attachment ID.
     * @param string $platform Target platform.
     * @param string $format Image format.
     * @return string Resized image URL.
     */
    private function resize_image_for_platform( $attachment_id, $platform, $format ) {
        if ( ! isset( $this->platform_dimensions[ $platform ][ $format ] ) ) {
            throw new Exception( 'Invalid platform or format specified' );
        }
        
        $dimensions = $this->platform_dimensions[ $platform ][ $format ];
        $image_path = get_attached_file( $attachment_id );
        
        if ( ! $image_path || ! file_exists( $image_path ) ) {
            throw new Exception( 'Image file not found' );
        }
        
        // Get image editor
        $image_editor = wp_get_image_editor( $image_path );
        
        if ( is_wp_error( $image_editor ) ) {
            throw new Exception( 'Failed to load image editor: ' . $image_editor->get_error_message() );
        }
        
        // Resize image
        $image_editor->resize( $dimensions['width'], $dimensions['height'], true );
        
        // Generate filename
        $path_info = pathinfo( $image_path );
        $new_filename = $path_info['dirname'] . '/' . $path_info['filename'] . '-' . $platform . '-' . $format . '.' . $path_info['extension'];
        
        // Save resized image
        $saved = $image_editor->save( $new_filename );
        
        if ( is_wp_error( $saved ) ) {
            throw new Exception( 'Failed to save resized image: ' . $saved->get_error_message() );
        }
        
        // Get URL
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace( $upload_dir['basedir'], '', $saved['path'] );
        $resized_url = $upload_dir['baseurl'] . $relative_path;
        
        // Store metadata
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! isset( $metadata['tts_resized_versions'] ) ) {
            $metadata['tts_resized_versions'] = array();
        }
        
        $metadata['tts_resized_versions'][ $platform . '_' . $format ] = array(
            'file' => basename( $saved['path'] ),
            'width' => $saved['width'],
            'height' => $saved['height'],
            'url' => $resized_url,
            'created' => current_time( 'mysql' )
        );
        
        wp_update_attachment_metadata( $attachment_id, $metadata );
        
        return $resized_url;
    }

    /**
     * Optimize video for social media.
     */
    public function ajax_optimize_video() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
        }

        $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
        $platform = sanitize_text_field( wp_unslash( $_POST['platform'] ?? '' ) );
        $quality = sanitize_text_field( wp_unslash( $_POST['quality'] ?? 'medium' ) );

        if ( empty( $attachment_id ) || empty( $platform ) ) {
            wp_send_json_error( array( 'message' => __( 'Attachment ID and platform are required.', 'fp-publisher' ) ) );
        }

        try {
            $optimized_info = $this->optimize_video_for_platform( $attachment_id, $platform, $quality );

            if ( is_wp_error( $optimized_info ) ) {
                wp_send_json_error(
                    array(
                        'message' => $optimized_info->get_error_message(),
                        'code'    => $optimized_info->get_error_code(),
                    )
                );
            }

            wp_send_json_success( array(
                'optimized_info' => $optimized_info,
                'message' => __( 'Video optimized successfully!', 'fp-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Video Optimization Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to optimize video. Please try again.', 'fp-publisher' ) ) );
        }
    }

    /**
     * Optimize video for specific platform.
     *
     * @param int $attachment_id Attachment ID.
     * @param string $platform Target platform.
     * @param string $quality  Quality setting.
     * @return array|WP_Error Optimization info or error.
     */
    private function optimize_video_for_platform( $attachment_id, $platform, $quality ) {
        $video_path = get_attached_file( $attachment_id );

        if ( ! $video_path || ! file_exists( $video_path ) ) {
            return new WP_Error(
                'tts_video_not_found',
                __( 'The original video file could not be located for optimization.', 'fp-publisher' )
            );
        }
        
        // Platform-specific video settings
        $platform_settings = array(
            'instagram' => array(
                'max_duration' => 60,
                'max_size' => 100, // MB
                'aspect_ratio' => '1:1',
                'formats' => array( 'mp4', 'mov' )
            ),
            'facebook' => array(
                'max_duration' => 240,
                'max_size' => 4096, // MB
                'aspect_ratio' => '16:9',
                'formats' => array( 'mp4', 'mov', 'avi' )
            ),
            'twitter' => array(
                'max_duration' => 140,
                'max_size' => 512, // MB
                'aspect_ratio' => '16:9',
                'formats' => array( 'mp4', 'mov' )
            ),
            'linkedin' => array(
                'max_duration' => 600,
                'max_size' => 5120, // MB
                'aspect_ratio' => '16:9',
                'formats' => array( 'mp4', 'asf', 'avi' )
            ),
            'youtube' => array(
                'max_duration' => 43200, // 12 hours
                'max_size' => 256 * 1024, // 256 GB
                'aspect_ratio' => '16:9',
                'formats' => array( 'mp4', 'mov', 'avi', 'wmv', 'flv' )
            ),
            'tiktok' => array(
                'max_duration' => 60,
                'max_size' => 500, // MB
                'aspect_ratio' => '9:16',
                'formats' => array( 'mp4', 'mov' )
            )
        );
        
        $settings = $platform_settings[ $platform ] ?? $platform_settings['instagram'];
        
        // Get video information
        $video_info = $this->get_video_info( $attachment_id, $video_path );

        // Quality settings
        $quality_settings = array(
            'low' => array( 'bitrate' => '500k', 'width' => 640 ),
            'medium' => array( 'bitrate' => '1000k', 'width' => 1280 ),
            'high' => array( 'bitrate' => '2000k', 'width' => 1920 )
        );

        $quality_setting = $quality_settings[ $quality ] ?? $quality_settings['medium'];

        $original_size = filesize( $video_path );
        if ( false === $original_size ) {
            $original_size = 0;
        }

        $original_dimensions = $video_info['dimensions'] ?? array();

        $path_info = pathinfo( $video_path );
        $extension = isset( $path_info['extension'] ) ? strtolower( $path_info['extension'] ) : 'mp4';

        if ( empty( $extension ) ) {
            $extension = 'mp4';
        }

        $safe_platform = sanitize_file_name( $platform );
        if ( '' === $safe_platform ) {
            $safe_platform = 'platform';
        }

        $safe_quality = sanitize_file_name( $quality );
        if ( '' === $safe_quality ) {
            $safe_quality = 'quality';
        }

        $optimized_filename = sprintf(
            '%s-%s-%s-optimized.%s',
            $path_info['filename'],
            $safe_platform,
            $safe_quality,
            $extension
        );

        $optimized_path = rtrim( $path_info['dirname'], '/\\' ) . '/' . $optimized_filename;

        $transcode_result = $this->compress_video_file( $video_path, $quality, $optimized_path );

        if ( is_wp_error( $transcode_result ) ) {
            return $transcode_result;
        }

        $optimized_path = $transcode_result['compressed_path'] ?? $optimized_path;

        if ( empty( $optimized_path ) || ! file_exists( $optimized_path ) ) {
            return new WP_Error(
                'tts_optimized_file_missing',
                __( 'The optimized video file could not be created.', 'fp-publisher' )
            );
        }

        $optimized_size = filesize( $optimized_path );

        if ( false === $optimized_size ) {
            return new WP_Error(
                'tts_optimized_filesize',
                __( 'Unable to determine the optimized video size.', 'fp-publisher' )
            );
        }

        $optimized_metadata = $this->load_video_metadata_from_file( $optimized_path );
        $optimized_duration = $this->read_duration_from_metadata( $optimized_metadata );
        $optimized_width    = $this->read_dimension_from_metadata( $optimized_metadata, 'width' );
        $optimized_height   = $this->read_dimension_from_metadata( $optimized_metadata, 'height' );

        if ( null !== $optimized_duration ) {
            $optimized_duration = (int) min( (int) $optimized_duration, (int) $settings['max_duration'] );
        } elseif ( isset( $video_info['duration'] ) && is_numeric( $video_info['duration'] ) ) {
            $optimized_duration = (int) min( (int) $video_info['duration'], (int) $settings['max_duration'] );
        } else {
            $optimized_duration = null;
        }

        $optimized_dimensions = array(
            'width'  => isset( $optimized_width ) && '' !== $optimized_width ? (int) $optimized_width : null,
            'height' => isset( $optimized_height ) && '' !== $optimized_height ? (int) $optimized_height : null,
        );

        if ( empty( $optimized_dimensions['width'] ) || empty( $optimized_dimensions['height'] ) ) {
            $optimized_dimensions = $this->calculate_optimized_dimensions(
                $original_dimensions,
                $settings['aspect_ratio'],
                $quality_setting['width']
            );
        }

        $upload_dir    = wp_upload_dir();
        $optimized_url = '';

        if ( ! empty( $transcode_result['compressed_url'] ) ) {
            $optimized_url = $transcode_result['compressed_url'];
        } elseif ( ! empty( $upload_dir['basedir'] ) && ! empty( $upload_dir['baseurl'] ) && 0 === strpos( $optimized_path, $upload_dir['basedir'] ) ) {
            $optimized_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $optimized_path );
        }

        $optimized_video_info = array(
            'duration'   => $optimized_duration,
            'dimensions' => $optimized_dimensions,
            'filesize'   => (int) $optimized_size,
            'format'     => $extension,
        );

        $optimized_info = array(
            'original_size'       => (int) $original_size,
            'original_duration'   => $video_info['duration'],
            'original_dimensions' => $original_dimensions,
            'optimized_size'      => (int) $optimized_size,
            'optimized_duration'  => $optimized_duration,
            'optimized_dimensions'=> $optimized_dimensions,
            'optimized_path'      => $optimized_path,
            'optimized_url'       => $optimized_url,
            'platform_settings'   => $settings,
            'quality_used'        => $quality,
            'compression_ratio'   => ( $original_size > 0 ) ? round( $optimized_size / $original_size, 4 ) : null,
            'meets_requirements'  => $this->check_video_requirements( $optimized_video_info, $settings ),
            'optimized_at'        => current_time( 'mysql' )
        );

        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! is_array( $metadata ) ) {
            $metadata = array();
        }

        if ( ! isset( $metadata['tts_video_optimizations'] ) || ! is_array( $metadata['tts_video_optimizations'] ) ) {
            $metadata['tts_video_optimizations'] = array();
        }

        $metadata['tts_video_optimizations'][ $platform ] = $optimized_info;
        wp_update_attachment_metadata( $attachment_id, $metadata );

        return $optimized_info;
    }

    /**
     * Get video information.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $video_path    Video file path.
     * @return array Video information.
     */
    private function get_video_info( $attachment_id, $video_path ) {
        $metadata = wp_get_attachment_metadata( $attachment_id );

        if ( ! is_array( $metadata ) ) {
            $metadata = array();
        }

        $duration      = $this->read_duration_from_metadata( $metadata );
        $width         = $this->read_dimension_from_metadata( $metadata, 'width' );
        $height        = $this->read_dimension_from_metadata( $metadata, 'height' );
        $bitrate       = $this->read_bitrate_from_metadata( $metadata );
        $framerate     = $this->read_framerate_from_metadata( $metadata );
        $codec         = $this->read_codec_from_metadata( $metadata );
        $audio_codec   = $this->read_audio_codec_from_metadata( $metadata );

        if (
            null === $duration ||
            null === $width ||
            null === $height ||
            null === $bitrate ||
            null === $framerate ||
            null === $codec ||
            null === $audio_codec
        ) {
            $fallback_metadata = $this->load_video_metadata_from_file( $video_path );

            if ( null === $duration ) {
                $duration = $this->read_duration_from_metadata( $fallback_metadata );
            }

            if ( null === $width ) {
                $width = $this->read_dimension_from_metadata( $fallback_metadata, 'width' );
            }

            if ( null === $height ) {
                $height = $this->read_dimension_from_metadata( $fallback_metadata, 'height' );
            }

            if ( null === $bitrate ) {
                $bitrate = $this->read_bitrate_from_metadata( $fallback_metadata );
            }

            if ( null === $framerate ) {
                $framerate = $this->read_framerate_from_metadata( $fallback_metadata );
            }

            if ( null === $codec ) {
                $codec = $this->read_codec_from_metadata( $fallback_metadata );
            }

            if ( null === $audio_codec ) {
                $audio_codec = $this->read_audio_codec_from_metadata( $fallback_metadata );
            }
        }

        return array(
            'duration' => $duration,
            'dimensions' => array(
                'width' => $width,
                'height' => $height,
            ),
            'bitrate' => $bitrate,
            'framerate' => $framerate,
            'codec' => $codec,
            'audio_codec' => $audio_codec,
        );
    }

    /**
     * Attempt to read the duration value from attachment or file metadata.
     *
     * @param array $metadata Metadata array.
     * @return int|null Duration in seconds.
     */
    private function read_duration_from_metadata( $metadata ) {
        if ( ! is_array( $metadata ) ) {
            return null;
        }

        $numeric_keys = array( 'length', 'video.length', 'playtime_seconds' );

        foreach ( $numeric_keys as $key ) {
            $value = $this->get_metadata_value( $metadata, $key );

            if ( is_numeric( $value ) ) {
                $seconds = (int) round( (float) $value );

                if ( $seconds >= 0 ) {
                    return $seconds;
                }
            }
        }

        $formatted_keys = array( 'length_formatted', 'video.length_formatted', 'playtime_string' );

        foreach ( $formatted_keys as $key ) {
            $value = $this->get_metadata_value( $metadata, $key );

            if ( is_string( $value ) && '' !== trim( $value ) ) {
                $seconds = $this->parse_duration_string( $value );

                if ( null !== $seconds ) {
                    return $seconds;
                }
            }
        }

        return null;
    }

    /**
     * Attempt to read a dimension value from metadata.
     *
     * @param array  $metadata  Metadata array.
     * @param string $dimension Dimension key (width or height).
     * @return int|null Dimension in pixels.
     */
    private function read_dimension_from_metadata( $metadata, $dimension ) {
        if ( ! is_array( $metadata ) ) {
            return null;
        }

        $keys = array(
            $dimension,
            'video.' . $dimension,
        );

        if ( 'width' === $dimension ) {
            $keys[] = 'video.resolution_x';
            $keys[] = 'resolution_x';
        } elseif ( 'height' === $dimension ) {
            $keys[] = 'video.resolution_y';
            $keys[] = 'resolution_y';
        }

        foreach ( $keys as $key ) {
            $value = $this->get_metadata_value( $metadata, $key );

            if ( is_numeric( $value ) ) {
                $dimension_value = (int) round( (float) $value );

                if ( $dimension_value > 0 ) {
                    return $dimension_value;
                }
            }
        }

        return null;
    }

    /**
     * Attempt to read the bitrate value from metadata.
     *
     * @param array $metadata Metadata array.
     * @return int|null Bitrate value.
     */
    private function read_bitrate_from_metadata( $metadata ) {
        if ( ! is_array( $metadata ) ) {
            return null;
        }

        $keys = array( 'bitrate', 'video.bitrate', 'audio.bitrate' );

        foreach ( $keys as $key ) {
            $value = $this->get_metadata_value( $metadata, $key );

            if ( is_numeric( $value ) ) {
                $bitrate = (int) round( (float) $value );

                if ( $bitrate > 0 ) {
                    return $bitrate;
                }
            }
        }

        return null;
    }

    /**
     * Attempt to read the frame rate from metadata.
     *
     * @param array $metadata Metadata array.
     * @return float|null Frame rate value.
     */
    private function read_framerate_from_metadata( $metadata ) {
        if ( ! is_array( $metadata ) ) {
            return null;
        }

        $keys = array( 'framerate', 'frame_rate', 'video.frame_rate', 'video.framerate' );

        foreach ( $keys as $key ) {
            $value = $this->get_metadata_value( $metadata, $key );

            if ( is_numeric( $value ) ) {
                $framerate = (float) $value;

                if ( $framerate > 0 ) {
                    return $framerate;
                }
            }
        }

        return null;
    }

    /**
     * Attempt to read the video codec from metadata.
     *
     * @param array $metadata Metadata array.
     * @return string|null Codec value.
     */
    private function read_codec_from_metadata( $metadata ) {
        if ( ! is_array( $metadata ) ) {
            return null;
        }

        $keys = array( 'codec', 'video.codec', 'video.dataformat', 'dataformat', 'fileformat', 'encoder', 'video.encoder' );

        foreach ( $keys as $key ) {
            $value = $this->get_metadata_value( $metadata, $key );

            if ( is_string( $value ) ) {
                $value = trim( $value );

                if ( '' !== $value ) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Attempt to read the audio codec from metadata.
     *
     * @param array $metadata Metadata array.
     * @return string|null Audio codec value.
     */
    private function read_audio_codec_from_metadata( $metadata ) {
        if ( ! is_array( $metadata ) ) {
            return null;
        }

        $keys = array( 'audio.codec', 'audio.dataformat', 'audio.encoder' );

        foreach ( $keys as $key ) {
            $value = $this->get_metadata_value( $metadata, $key );

            if ( is_string( $value ) ) {
                $value = trim( $value );

                if ( '' !== $value ) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Parse a duration string (e.g. 00:01:30) into seconds.
     *
     * @param string $duration_string Duration string.
     * @return int|null Duration in seconds.
     */
    private function parse_duration_string( $duration_string ) {
        if ( ! is_string( $duration_string ) ) {
            return null;
        }

        $duration_string = trim( $duration_string );

        if ( '' === $duration_string ) {
            return null;
        }

        $parts = explode( ':', $duration_string );

        if ( empty( $parts ) ) {
            return null;
        }

        $seconds    = 0;
        $multiplier = 1;

        while ( $parts ) {
            $part = array_pop( $parts );
            $part = trim( $part );

            if ( '' === $part || ! is_numeric( $part ) ) {
                return null;
            }

            $seconds   += (float) $part * $multiplier;
            $multiplier *= 60;
        }

        $seconds = (int) round( $seconds );

        return ( $seconds >= 0 ) ? $seconds : null;
    }

    /**
     * Safely retrieve a nested metadata value using dot notation.
     *
     * @param array  $metadata Metadata array.
     * @param string $key_path Dot-notated key path.
     * @return mixed|null Metadata value or null when unavailable.
     */
    private function get_metadata_value( $metadata, $key_path ) {
        if ( ! is_array( $metadata ) ) {
            return null;
        }

        $segments = explode( '.', $key_path );
        $value    = $metadata;

        foreach ( $segments as $segment ) {
            if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
                return null;
            }

            $value = $value[ $segment ];
        }

        return $value;
    }

    /**
     * Load metadata directly from the video file when attachment metadata is incomplete.
     *
     * @param string $video_path Video file path.
     * @return array Metadata array.
     */
    private function load_video_metadata_from_file( $video_path ) {
        if ( empty( $video_path ) || ! file_exists( $video_path ) ) {
            return array();
        }

        if ( ! function_exists( 'wp_read_video_metadata' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $metadata = wp_read_video_metadata( $video_path );

        return is_array( $metadata ) ? $metadata : array();
    }

    /**
     * Calculate optimized dimensions based on aspect ratio.
     *
     * @param array $original_dimensions Original dimensions.
     * @param string $target_aspect_ratio Target aspect ratio.
     * @param int $max_width Maximum width.
     * @return array Optimized dimensions.
     */
    private function calculate_optimized_dimensions( $original_dimensions, $target_aspect_ratio, $max_width ) {
        $width  = $original_dimensions['width'] ?? null;
        $height = $original_dimensions['height'] ?? null;

        if ( empty( $width ) || empty( $height ) ) {
            return array(
                'width' => $width,
                'height' => $height,
            );
        }

        if ( empty( $target_aspect_ratio ) || false === strpos( $target_aspect_ratio, ':' ) ) {
            return array(
                'width' => min( (int) $width, (int) $max_width ),
                'height' => (int) $height,
            );
        }

        list( $ratio_width, $ratio_height ) = explode( ':', $target_aspect_ratio );

        $ratio_width  = (float) $ratio_width;
        $ratio_height = (float) $ratio_height;

        if ( $ratio_width <= 0 || $ratio_height <= 0 ) {
            return array(
                'width' => min( (int) $width, (int) $max_width ),
                'height' => (int) $height,
            );
        }

        $target_ratio = $ratio_width / $ratio_height;
        $width        = min( (int) $width, (int) $max_width );
        $height       = (int) round( $width / $target_ratio );

        return array(
            'width' => $width,
            'height' => $height,
        );
    }

    /**
     * Check if video meets platform requirements.
     *
     * @param array $video_info Video information.
     * @param array $platform_settings Platform settings.
     * @return array Requirements check.
     */
    private function check_video_requirements( $video_info, $platform_settings ) {
        $duration_ok = null;

        if ( isset( $video_info['duration'] ) && is_numeric( $video_info['duration'] ) ) {
            $duration_ok = ( (int) $video_info['duration'] ) <= (int) $platform_settings['max_duration'];
        }

        $aspect_ratio_ok = null;
        $dimensions      = $video_info['dimensions'] ?? array();

        if ( ! empty( $dimensions['width'] ) && ! empty( $dimensions['height'] ) ) {
            $aspect_ratio_ok = true;

            if ( ! empty( $platform_settings['aspect_ratio'] ) && false !== strpos( $platform_settings['aspect_ratio'], ':' ) ) {
                list( $ratio_width, $ratio_height ) = explode( ':', $platform_settings['aspect_ratio'] );

                $ratio_width  = (float) $ratio_width;
                $ratio_height = (float) $ratio_height;

                if ( $ratio_width > 0 && $ratio_height > 0 ) {
                    $target_ratio = $ratio_width / $ratio_height;
                    $actual_ratio = (float) $dimensions['width'] / (float) $dimensions['height'];

                    $aspect_ratio_ok = abs( $actual_ratio - $target_ratio ) < 0.1;
                }
            }
        }

        $size_ok = null;

        if ( isset( $video_info['filesize'] ) && isset( $platform_settings['max_size'] ) ) {
            $max_bytes = (int) $platform_settings['max_size'] * 1024 * 1024;
            $size_ok   = ( (int) $video_info['filesize'] ) <= $max_bytes;
        }

        $format_ok = null;

        if ( ! empty( $platform_settings['formats'] ) && is_array( $platform_settings['formats'] ) ) {
            if ( ! empty( $video_info['format'] ) ) {
                $format_ok = in_array( strtolower( (string) $video_info['format'] ), array_map( 'strtolower', $platform_settings['formats'] ), true );
            }
        }

        return array(
            'duration_ok'     => $duration_ok,
            'size_ok'         => $size_ok,
            'format_ok'       => $format_ok,
            'aspect_ratio_ok' => $aspect_ratio_ok,
        );
    }

    /**
     * Add watermark to image.
     */
    public function ajax_add_watermark() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
        }

        $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
        $watermark_type = sanitize_text_field( wp_unslash( $_POST['watermark_type'] ?? 'text' ) );
        $watermark_text = sanitize_text_field( wp_unslash( $_POST['watermark_text'] ?? '' ) );
        $watermark_position = sanitize_text_field( wp_unslash( $_POST['watermark_position'] ?? 'bottom-right' ) );
        $watermark_opacity = intval( $_POST['watermark_opacity'] ?? 50 );

        if ( empty( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Attachment ID is required.', 'fp-publisher' ) ) );
        }

        try {
            $watermarked_url = $this->add_watermark_to_image( $attachment_id, $watermark_type, $watermark_text, $watermark_position, $watermark_opacity );
            
            wp_send_json_success( array(
                'watermarked_url' => $watermarked_url,
                'message' => __( 'Watermark added successfully!', 'fp-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Watermark Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to add watermark. Please try again.', 'fp-publisher' ) ) );
        }
    }

    /**
     * Add watermark to image.
     *
     * @param int $attachment_id Attachment ID.
     * @param string $watermark_type Watermark type.
     * @param string $watermark_text Watermark text.
     * @param string $watermark_position Watermark position.
     * @param int $watermark_opacity Watermark opacity.
     * @return string Watermarked image URL.
     */
    private function add_watermark_to_image( $attachment_id, $watermark_type, $watermark_text, $watermark_position, $watermark_opacity ) {
        $image_path = get_attached_file( $attachment_id );
        
        if ( ! $image_path || ! file_exists( $image_path ) ) {
            throw new Exception( 'Image file not found' );
        }
        
        // Get image editor
        $image_editor = wp_get_image_editor( $image_path );
        
        if ( is_wp_error( $image_editor ) ) {
            throw new Exception( 'Failed to load image editor: ' . $image_editor->get_error_message() );
        }
        
        // Get image dimensions
        $size = $image_editor->get_size();
        
        // Calculate watermark position
        $positions = array(
            'top-left' => array( 'x' => 20, 'y' => 20 ),
            'top-right' => array( 'x' => $size['width'] - 200, 'y' => 20 ),
            'bottom-left' => array( 'x' => 20, 'y' => $size['height'] - 50 ),
            'bottom-right' => array( 'x' => $size['width'] - 200, 'y' => $size['height'] - 50 ),
            'center' => array( 'x' => $size['width'] / 2 - 100, 'y' => $size['height'] / 2 - 25 )
        );
        
        $position = $positions[ $watermark_position ] ?? $positions['bottom-right'];
        
        // Create watermark using GD library
        if ( $watermark_type === 'text' && ! empty( $watermark_text ) ) {
            // Check if GD extension is available
            if ( ! extension_loaded( 'gd' ) ) {
                throw new Exception( 'GD extension is required for watermarking' );
            }
            
            $path_info = pathinfo( $image_path );
            $new_filename = $path_info['dirname'] . '/' . $path_info['filename'] . '-watermarked.' . $path_info['extension'];
            
            // Load the original image
            $image_type = exif_imagetype( $image_path );
            
            switch ( $image_type ) {
                case IMAGETYPE_JPEG:
                    $source_image = imagecreatefromjpeg( $image_path );
                    break;
                case IMAGETYPE_PNG:
                    $source_image = imagecreatefrompng( $image_path );
                    break;
                case IMAGETYPE_GIF:
                    $source_image = imagecreatefromgif( $image_path );
                    break;
                default:
                    throw new Exception( 'Unsupported image type for watermarking' );
            }
            
            if ( ! $source_image ) {
                throw new Exception( 'Failed to load source image' );
            }
            
            // Get image dimensions
            $image_width = imagesx( $source_image );
            $image_height = imagesy( $source_image );
            
            // Calculate font size based on image size
            $font_size = max( 12, min( 48, $image_width / 20 ) );
            
            // Calculate text dimensions
            $text_box = imagettfbbox( $font_size, 0, $this->get_watermark_font(), $watermark_text );
            $text_width = abs( $text_box[4] - $text_box[0] );
            $text_height = abs( $text_box[5] - $text_box[1] );
            
            // Calculate position
            $positions = array(
                'top-left' => array( 'x' => 20, 'y' => 20 + $text_height ),
                'top-right' => array( 'x' => $image_width - $text_width - 20, 'y' => 20 + $text_height ),
                'bottom-left' => array( 'x' => 20, 'y' => $image_height - 20 ),
                'bottom-right' => array( 'x' => $image_width - $text_width - 20, 'y' => $image_height - 20 ),
                'center' => array( 'x' => ( $image_width - $text_width ) / 2, 'y' => ( $image_height + $text_height ) / 2 )
            );
            
            $position = $positions[ $watermark_position ] ?? $positions['bottom-right'];
            
            // Create watermark color with opacity
            $alpha = 127 - ( $watermark_opacity * 127 / 100 );
            $watermark_color = imagecolorallocatealpha( $source_image, 255, 255, 255, $alpha );
            
            // Add text watermark
            $font_path = $this->get_watermark_font();
            if ( $font_path && file_exists( $font_path ) ) {
                imagettftext( 
                    $source_image, 
                    $font_size, 
                    0, 
                    $position['x'], 
                    $position['y'], 
                    $watermark_color, 
                    $font_path, 
                    $watermark_text 
                );
            } else {
                // Fallback to built-in font
                imagestring( 
                    $source_image, 
                    5, 
                    $position['x'], 
                    $position['y'] - $text_height, 
                    $watermark_text, 
                    $watermark_color 
                );
            }
            
            // Save the watermarked image
            $save_success = false;
            switch ( $image_type ) {
                case IMAGETYPE_JPEG:
                    $save_success = imagejpeg( $source_image, $new_filename, 90 );
                    break;
                case IMAGETYPE_PNG:
                    $save_success = imagepng( $source_image, $new_filename, 9 );
                    break;
                case IMAGETYPE_GIF:
                    $save_success = imagegif( $source_image, $new_filename );
                    break;
            }
            
            // Clean up memory
            imagedestroy( $source_image );
            
            if ( ! $save_success ) {
                throw new Exception( 'Failed to save watermarked image' );
            }
            
            // Get URL
            $upload_dir = wp_upload_dir();
            $relative_path = str_replace( $upload_dir['basedir'], '', $new_filename );
            $watermarked_url = $upload_dir['baseurl'] . $relative_path;
            
            // Store watermark metadata
            $metadata = wp_get_attachment_metadata( $attachment_id );
            if ( ! isset( $metadata['tts_watermarks'] ) ) {
                $metadata['tts_watermarks'] = array();
            }
            
            $metadata['tts_watermarks'][] = array(
                'type' => $watermark_type,
                'text' => $watermark_text,
                'position' => $watermark_position,
                'opacity' => $watermark_opacity,
                'file' => basename( $new_filename ),
                'url' => $watermarked_url,
                'created' => current_time( 'mysql' )
            );
            
            wp_update_attachment_metadata( $attachment_id, $metadata );
            
            return $watermarked_url;
        }
        
        throw new Exception( 'Invalid watermark configuration' );
    }
    
    /**
     * Get watermark font path.
     *
     * @return string|false Font path or false if not available.
     */
    private function get_watermark_font() {
        // Try to find a suitable font file
        $font_paths = array(
            TSAP_PLUGIN_DIR . 'assets/fonts/arial.ttf',
            TSAP_PLUGIN_DIR . 'assets/fonts/default.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/System/Library/Fonts/Arial.ttf', // macOS
            'C:\\Windows\\Fonts\\arial.ttf' // Windows
        );
        
        foreach ( $font_paths as $font_path ) {
            if ( file_exists( $font_path ) ) {
                return $font_path;
            }
        }
        
        return false;
    }

    /**
     * Batch process multiple media files.
     */
    public function ajax_batch_process_media() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
        }

        $attachment_ids = array_map( 'intval', $_POST['attachment_ids'] ?? array() );
        $operation = sanitize_text_field( wp_unslash( $_POST['operation'] ?? '' ) );
        $settings = array_map( 'sanitize_text_field', wp_unslash( $_POST['settings'] ?? array() ) );

        if ( empty( $attachment_ids ) || empty( $operation ) ) {
            wp_send_json_error( array( 'message' => __( 'Attachment IDs and operation are required.', 'fp-publisher' ) ) );
        }

        // Limit batch size for performance
        if ( count( $attachment_ids ) > 20 ) {
            wp_send_json_error( array( 'message' => __( 'Maximum 20 files can be processed at once.', 'fp-publisher' ) ) );
        }

        try {
            $results = $this->batch_process_media( $attachment_ids, $operation, $settings );
            
            wp_send_json_success( array(
                'results' => $results,
                'message' => __( 'Batch processing completed successfully!', 'fp-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Batch Media Processing Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to process media files. Please try again.', 'fp-publisher' ) ) );
        }
    }

    /**
     * Batch process media files.
     *
     * @param array $attachment_ids Attachment IDs.
     * @param string $operation Operation to perform.
     * @param array $settings Operation settings.
     * @return array Processing results.
     */
    private function batch_process_media( $attachment_ids, $operation, $settings ) {
        $results = array(
            'processed' => 0,
            'failed' => 0,
            'details' => array()
        );
        
        foreach ( $attachment_ids as $attachment_id ) {
            try {
                $result = null;
                
                switch ( $operation ) {
                    case 'resize':
                        if ( ! empty( $settings['platform'] ) && ! empty( $settings['format'] ) ) {
                            $result = $this->resize_image_for_platform( $attachment_id, $settings['platform'], $settings['format'] );
                        }
                        break;
                        
                    case 'compress':
                        $result = $this->compress_media_file( $attachment_id, $settings['quality'] ?? 'medium' );
                        break;
                        
                    case 'watermark':
                        if ( ! empty( $settings['watermark_text'] ) ) {
                            $result = $this->add_watermark_to_image( 
                                $attachment_id, 
                                $settings['watermark_type'] ?? 'text',
                                $settings['watermark_text'],
                                $settings['watermark_position'] ?? 'bottom-right',
                                intval( $settings['watermark_opacity'] ?? 50 )
                            );
                        }
                        break;
                        
                    case 'optimize_video':
                        if ( ! empty( $settings['platform'] ) ) {
                            $result = $this->optimize_video_for_platform( $attachment_id, $settings['platform'], $settings['quality'] ?? 'medium' );
                        }
                        break;
                        
                    default:
                        throw new Exception( 'Unknown operation: ' . $operation );
                }
                
                if ( is_wp_error( $result ) ) {
                    $results['failed']++;
                    $results['details'][ $attachment_id ] = array(
                        'status' => 'failed',
                        'error'  => $result->get_error_message(),
                        'code'   => $result->get_error_code(),
                    );
                    continue;
                }

                if ( $result ) {
                    $results['processed']++;
                    $results['details'][ $attachment_id ] = array(
                        'status' => 'success',
                        'result' => $result
                    );
                } else {
                    $results['failed']++;
                    $results['details'][ $attachment_id ] = array(
                        'status' => 'failed',
                        'error' => 'Operation returned no result'
                    );
                }
                
            } catch ( Exception $e ) {
                $results['failed']++;
                $results['details'][ $attachment_id ] = array(
                    'status' => 'failed',
                    'error' => $e->getMessage()
                );
            }
        }
        
        return $results;
    }

    /**
     * Get stock photos from various providers.
     */
    public function ajax_get_stock_photos() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
        }

        $search_term = sanitize_text_field( wp_unslash( $_POST['search_term'] ?? '' ) );
        $provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? 'unsplash' ) );
        $per_page = intval( $_POST['per_page'] ?? 20 );

        if ( empty( $search_term ) ) {
            wp_send_json_error( array( 'message' => __( 'Search term is required.', 'fp-publisher' ) ) );
        }

        try {
            $photos = $this->search_stock_photos( $search_term, $provider, $per_page );

            if ( is_wp_error( $photos ) ) {
                wp_send_json_error( array(
                    'message' => $photos->get_error_message(),
                    'code'    => $photos->get_error_code(),
                ) );
            }

            wp_send_json_success( array(
                'photos' => $photos,
                'message' => __( 'Stock photos retrieved successfully!', 'fp-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Stock Photos Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to retrieve stock photos. Please try again.', 'fp-publisher' ) ) );
        }
    }

    /**
     * Search stock photos from providers.
     *
     * @param string $search_term Search term.
     * @param string $provider Provider name.
     * @param int $per_page Results per page.
     * @return array|WP_Error Stock photos or error.
     */
    private function search_stock_photos( $search_term, $provider, $per_page ) {
        $provider = strtolower( $provider );
        $per_page = max( 1, absint( $per_page ) );

        $settings = get_option( 'tts_settings', array() );
        $args     = array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        );

        switch ( $provider ) {
            case 'pexels':
                $api_key = isset( $settings['pexels_api_key'] ) ? trim( $settings['pexels_api_key'] ) : '';

                if ( empty( $api_key ) ) {
                    return new WP_Error(
                        'tts_missing_api_key',
                        __( 'Pexels API key is not configured. Please add it in the plugin settings.', 'fp-publisher' )
                    );
                }

                $endpoint = add_query_arg(
                    array(
                        'query'   => $search_term,
                        'per_page'=> min( $per_page, 80 ),
                    ),
                    'https://api.pexels.com/v1/search'
                );

                $args['headers']['Authorization'] = $api_key;
                $provider_name                     = 'pexels';
                break;

            case 'unsplash':
            default:
                $api_key = isset( $settings['unsplash_access_key'] ) ? trim( $settings['unsplash_access_key'] ) : '';

                if ( empty( $api_key ) ) {
                    return new WP_Error(
                        'tts_missing_api_key',
                        __( 'Unsplash access key is not configured. Please add it in the plugin settings.', 'fp-publisher' )
                    );
                }

                $endpoint = add_query_arg(
                    array(
                        'query'    => $search_term,
                        'per_page' => min( $per_page, 30 ),
                    ),
                    'https://api.unsplash.com/search/photos'
                );

                $args['headers']['Authorization'] = 'Client-ID ' . $api_key;
                $provider_name                     = 'unsplash';
                break;
        }

        $response = wp_remote_get( esc_url_raw( $endpoint ), $args );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'tts_stock_api_request_failed',
                __( 'Unable to contact the stock photo provider. Please try again later.', 'fp-publisher' ),
                $response->get_error_message()
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( 200 !== $status_code ) {
            $message = __( 'Unexpected response from the stock photo provider. Please try again later.', 'fp-publisher' );

            if ( in_array( $status_code, array( 401, 403 ), true ) ) {
                $message = __( 'Authentication with the stock photo provider failed. Please verify your API key.', 'fp-publisher' );
            } elseif ( 429 === $status_code ) {
                $message = __( 'The stock photo provider rate limit has been reached. Please wait before trying again.', 'fp-publisher' );
            }

            return new WP_Error(
                'tts_stock_api_http_error',
                $message,
                array( 'status_code' => $status_code )
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( null === $data || JSON_ERROR_NONE !== json_last_error() ) {
            return new WP_Error(
                'tts_stock_api_invalid_json',
                __( 'The stock photo provider returned an invalid response. Please try again.', 'fp-publisher' )
            );
        }

        $photos = array();

        if ( 'pexels' === $provider_name ) {
            if ( empty( $data['photos'] ) || ! is_array( $data['photos'] ) ) {
                return array();
            }

            foreach ( $data['photos'] as $photo ) {
                $title   = ! empty( $photo['alt'] ) ? $photo['alt'] : sprintf( __( '%s photo', 'fp-publisher' ), ucfirst( $search_term ) );
                $tags    = array( $search_term, 'pexels' );
                $photos[] = array(
                    'id'               => isset( $photo['id'] ) ? (string) $photo['id'] : '',
                    'title'            => $title,
                    'description'      => $photo['alt'] ?? '',
                    'url'              => $photo['src']['large2x'] ?? $photo['src']['large'] ?? $photo['src']['original'] ?? '',
                    'thumbnail_url'    => $photo['src']['medium'] ?? $photo['src']['small'] ?? '',
                    'width'            => isset( $photo['width'] ) ? intval( $photo['width'] ) : 0,
                    'height'           => isset( $photo['height'] ) ? intval( $photo['height'] ) : 0,
                    'photographer'     => $photo['photographer'] ?? '',
                    'photographer_url' => $photo['photographer_url'] ?? '',
                    'provider'         => 'pexels',
                    'license'          => 'Pexels License',
                    'download_url'     => $photo['src']['original'] ?? '',
                    'tags'             => array_values( array_filter( array_unique( $tags ) ) ),
                );
            }

            return $photos;
        }

        if ( empty( $data['results'] ) || ! is_array( $data['results'] ) ) {
            return array();
        }

        foreach ( $data['results'] as $photo ) {
            $description = $photo['description'] ?? $photo['alt_description'] ?? '';
            $title       = $description ? $description : sprintf( __( '%s photo', 'fp-publisher' ), ucfirst( $search_term ) );

            $tags = array();
            if ( ! empty( $photo['tags'] ) && is_array( $photo['tags'] ) ) {
                foreach ( $photo['tags'] as $tag ) {
                    if ( is_array( $tag ) && isset( $tag['title'] ) ) {
                        $tags[] = $tag['title'];
                    } elseif ( is_string( $tag ) ) {
                        $tags[] = $tag;
                    }
                }
            }
            $tags[] = $search_term;
            $tags[] = 'unsplash';

            $photos[] = array(
                'id'               => isset( $photo['id'] ) ? (string) $photo['id'] : '',
                'title'            => $title,
                'description'      => $photo['alt_description'] ?? $photo['description'] ?? '',
                'url'              => $photo['urls']['regular'] ?? $photo['urls']['full'] ?? '',
                'thumbnail_url'    => $photo['urls']['small'] ?? $photo['urls']['thumb'] ?? '',
                'width'            => isset( $photo['width'] ) ? intval( $photo['width'] ) : 0,
                'height'           => isset( $photo['height'] ) ? intval( $photo['height'] ) : 0,
                'photographer'     => $photo['user']['name'] ?? '',
                'photographer_url' => $photo['user']['links']['html'] ?? '',
                'provider'         => 'unsplash',
                'license'          => 'Unsplash License',
                'download_url'     => $photo['links']['download'] ?? $photo['urls']['full'] ?? '',
                'tags'             => array_values( array_filter( array_unique( $tags ) ) ),
            );
        }

        return $photos;
    }

    /**
     * Create media variations for different platforms.
     */
    public function ajax_create_media_variations() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
        }

        $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
        $platforms = array_map( 'sanitize_text_field', wp_unslash( $_POST['platforms'] ?? array() ) );

        if ( empty( $attachment_id ) || empty( $platforms ) ) {
            wp_send_json_error( array( 'message' => __( 'Attachment ID and platforms are required.', 'fp-publisher' ) ) );
        }

        try {
            $variations = $this->create_platform_variations( $attachment_id, $platforms );
            
            wp_send_json_success( array(
                'variations' => $variations,
                'message' => __( 'Media variations created successfully!', 'fp-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Media Variations Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to create variations. Please try again.', 'fp-publisher' ) ) );
        }
    }

    /**
     * Create platform-specific variations of media.
     *
     * @param int $attachment_id Attachment ID.
     * @param array $platforms Target platforms.
     * @return array Created variations.
     */
    private function create_platform_variations( $attachment_id, $platforms ) {
        $variations = array();
        
        foreach ( $platforms as $platform ) {
            if ( ! isset( $this->platform_dimensions[ $platform ] ) ) {
                continue;
            }
            
            $platform_variations = array();
            foreach ( $this->platform_dimensions[ $platform ] as $format => $dimensions ) {
                try {
                    $url = $this->resize_image_for_platform( $attachment_id, $platform, $format );
                    $platform_variations[ $format ] = array(
                        'url' => $url,
                        'dimensions' => $dimensions,
                        'format' => $format
                    );
                } catch ( Exception $e ) {
                    error_log( "Failed to create {$platform} {$format} variation: " . $e->getMessage() );
                }
            }
            
            if ( ! empty( $platform_variations ) ) {
                $variations[ $platform ] = $platform_variations;
            }
        }
        
        return $variations;
    }

    /**
     * Compress media file.
     */
    public function ajax_compress_media() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
        }

        $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
        $quality = sanitize_text_field( wp_unslash( $_POST['quality'] ?? 'medium' ) );

        if ( empty( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Attachment ID is required.', 'fp-publisher' ) ) );
        }

        try {
            $compression_info = $this->compress_media_file( $attachment_id, $quality );

            if ( is_wp_error( $compression_info ) ) {
                wp_send_json_error( array(
                    'message' => $compression_info->get_error_message(),
                    'code'    => $compression_info->get_error_code(),
                ) );
                return;
            }

            wp_send_json_success( array(
                'compression_info' => $compression_info,
                'message' => __( 'Media compressed successfully!', 'fp-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Media Compression Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to compress media. Please try again.', 'fp-publisher' ) ) );
        }
    }

    /**
     * Compress media file.
     *
     * @param int $attachment_id Attachment ID.
     * @param string $quality Quality setting.
     * @return array|WP_Error Compression information or error details.
     */
    private function compress_media_file( $attachment_id, $quality ) {
        $file_path = get_attached_file( $attachment_id );

        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return new WP_Error(
                'tts_missing_media',
                __( 'Media file not found.', 'fp-publisher' )
            );
        }

        $original_size = filesize( $file_path );
        if ( false === $original_size ) {
            return new WP_Error(
                'tts_media_filesize',
                __( 'Unable to determine the size of the original media file.', 'fp-publisher' )
            );
        }

        $mime_type = get_post_mime_type( $attachment_id );
        if ( empty( $mime_type ) && function_exists( 'mime_content_type' ) ) {
            $mime_type = mime_content_type( $file_path );
        }

        // Quality settings
        $quality_levels = array(
            'low'    => 0.6,
            'medium' => 0.8,
            'high'   => 0.9,
        );

        $compression_ratio = $quality_levels[ $quality ] ?? $quality_levels['medium'];

        if ( 0 === strpos( (string) $mime_type, 'image/' ) ) {
            $handler_result = $this->compress_image_file( $file_path, $compression_ratio );
        } elseif ( 0 === strpos( (string) $mime_type, 'video/' ) ) {
            $handler_result = $this->compress_video_file( $file_path, $quality );
        } else {
            $type = $mime_type ? sanitize_text_field( $mime_type ) : __( 'this media type', 'fp-publisher' );

            return new WP_Error(
                'tts_unsupported_media_type',
                sprintf(
                    /* translators: %s: MIME type. */
                    __( 'Compression is not supported for %s.', 'fp-publisher' ),
                    $type
                )
            );
        }

        if ( is_wp_error( $handler_result ) ) {
            return $handler_result;
        }

        $compressed_path = $handler_result['compressed_path'] ?? '';

        if ( empty( $compressed_path ) || ! file_exists( $compressed_path ) ) {
            return new WP_Error(
                'tts_missing_compressed_file',
                __( 'The compressed media file could not be located.', 'fp-publisher' )
            );
        }

        $compressed_size = filesize( $compressed_path );
        if ( false === $compressed_size ) {
            return new WP_Error(
                'tts_compressed_filesize',
                __( 'Unable to determine the compressed file size.', 'fp-publisher' )
            );
        }

        $savings = $original_size - $compressed_size;
        if ( $savings < 0 ) {
            $savings = 0;
        }

        $savings_percentage = 0;
        if ( $original_size > 0 ) {
            $savings_percentage = round( ( $savings / $original_size ) * 100, 1 );
        }

        $compression_info = array(
            'original_size'      => $original_size,
            'compressed_size'    => $compressed_size,
            'savings'            => $savings,
            'savings_percentage' => $savings_percentage,
            'quality_used'       => $quality,
            'compression_ratio'  => $handler_result['compression_ratio'] ?? ( $original_size > 0 ? round( $compressed_size / $original_size, 4 ) : 0 ),
            'compressed_at'      => current_time( 'mysql' ),
            'compressed_path'    => $compressed_path,
        );

        if ( ! empty( $handler_result['compressed_url'] ) ) {
            $compression_info['compressed_url'] = $handler_result['compressed_url'];
        } else {
            $upload_dir = wp_upload_dir();

            if ( ! empty( $upload_dir['basedir'] ) && ! empty( $upload_dir['baseurl'] ) && 0 === strpos( $compressed_path, $upload_dir['basedir'] ) ) {
                $compression_info['compressed_url'] = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $compressed_path );
            }
        }

        // Store compression metadata only when a new file exists.
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! is_array( $metadata ) ) {
            $metadata = array();
        }

        if ( ! isset( $metadata['tts_compression'] ) || ! is_array( $metadata['tts_compression'] ) ) {
            $metadata['tts_compression'] = array();
        }

        $metadata['tts_compression'][] = $compression_info;
        wp_update_attachment_metadata( $attachment_id, $metadata );

        return $compression_info;
    }

    /**
     * Compress an image file using the WordPress image editor.
     *
     * @param string $file_path         Absolute path to the image.
     * @param float  $compression_ratio Compression ratio between 0 and 1.
     * @return array|WP_Error Compression details or error.
     */
    private function compress_image_file( $file_path, $compression_ratio ) {
        $image_editor = wp_get_image_editor( $file_path );

        if ( is_wp_error( $image_editor ) ) {
            return $image_editor;
        }

        $quality = max( 1, min( 100, intval( round( $compression_ratio * 100 ) ) ) );
        $image_editor->set_quality( $quality );

        $path_info = pathinfo( $file_path );
        if ( empty( $path_info['dirname'] ) || empty( $path_info['filename'] ) ) {
            return new WP_Error(
                'tts_image_path_error',
                __( 'Unable to determine a filename for the compressed image.', 'fp-publisher' )
            );
        }

        $extension           = isset( $path_info['extension'] ) ? $path_info['extension'] : 'jpg';
        $compressed_filename = $path_info['dirname'] . '/' . $path_info['filename'] . '-compressed.' . $extension;
        $saved               = $image_editor->save( $compressed_filename );

        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        if ( empty( $saved['path'] ) ) {
            return new WP_Error(
                'tts_image_compression_failed',
                __( 'Image compression did not produce a valid file.', 'fp-publisher' )
            );
        }

        return array(
            'compressed_path'   => $saved['path'],
            'compression_ratio' => $compression_ratio,
        );
    }

    /**
     * Compress a video file using the configured transcoder.
     *
     * @param string      $file_path   Absolute path to the video.
     * @param string      $quality     Compression quality (low|medium|high).
     * @param string|null $output_path Optional absolute path where the compressed file should be written.
     * @return array|WP_Error Compression details or error.
     */
    private function compress_video_file( $file_path, $quality, $output_path = null ) {
        $settings        = get_option( 'tts_settings', array() );
        $transcoder_path = isset( $settings['media_transcoder_path'] ) ? trim( $settings['media_transcoder_path'] ) : '';

        if ( empty( $transcoder_path ) ) {
            return new WP_Error(
                'tts_transcoder_missing',
                __( 'Video compression requires configuring the ffmpeg binary in the plugin settings.', 'fp-publisher' )
            );
        }

        if ( ! file_exists( $transcoder_path ) ) {
            return new WP_Error(
                'tts_transcoder_not_found',
                __( 'The configured ffmpeg binary could not be found on the server.', 'fp-publisher' )
            );
        }

        if ( ! is_executable( $transcoder_path ) ) {
            return new WP_Error(
                'tts_transcoder_not_executable',
                __( 'The configured ffmpeg binary is not executable.', 'fp-publisher' )
            );
        }

        if ( ! $this->is_shell_exec_available() ) {
            return new WP_Error(
                'tts_exec_disabled',
                __( "Video compression is not available because PHP's exec() function is disabled.", 'fp-publisher' )
            );
        }

        $quality_presets = array(
            'low'    => array(
                'video_bitrate' => '800k',
                'audio_bitrate' => '96k',
                'preset'        => 'veryfast',
            ),
            'medium' => array(
                'video_bitrate' => '1500k',
                'audio_bitrate' => '128k',
                'preset'        => 'faster',
            ),
            'high'   => array(
                'video_bitrate' => '2500k',
                'audio_bitrate' => '192k',
                'preset'        => 'medium',
            ),
        );

        $preset = $quality_presets[ $quality ] ?? $quality_presets['medium'];

        $path_info = pathinfo( $file_path );
        $extension = isset( $path_info['extension'] ) ? strtolower( $path_info['extension'] ) : 'mp4';

        if ( empty( $extension ) ) {
            $extension = 'mp4';
        }

        if ( ! empty( $output_path ) ) {
            $compressed_path = $output_path;
        } else {
            $compressed_path = $path_info['dirname'] . '/' . $path_info['filename'] . '-compressed.' . $extension;
        }

        $compressed_dir = dirname( $compressed_path );

        if ( ! empty( $compressed_dir ) && ! is_dir( $compressed_dir ) ) {
            if ( function_exists( 'wp_mkdir_p' ) ) {
                wp_mkdir_p( $compressed_dir );
            } else {
                @mkdir( $compressed_dir, 0755, true );
            }
        }

        if ( file_exists( $compressed_path ) ) {
            @unlink( $compressed_path );
        }

        $command = sprintf(
            "%s -y -i %s -vcodec libx264 -preset %s -b:v %s -acodec aac -b:a %s -movflags +faststart %s 2>&1",
            escapeshellarg( $transcoder_path ),
            escapeshellarg( $file_path ),
            escapeshellarg( $preset['preset'] ),
            escapeshellarg( $preset['video_bitrate'] ),
            escapeshellarg( $preset['audio_bitrate'] ),
            escapeshellarg( $compressed_path )
        );

        $output     = array();
        $return_var = 0;
        exec( $command, $output, $return_var );

        if ( 0 !== $return_var ) {
            $message = __( 'Video compression failed to complete.', 'fp-publisher' );

            if ( ! empty( $output ) ) {
                $error_output = wp_strip_all_tags( implode( ' ', array_slice( $output, -5 ) ) );

                if ( ! empty( $error_output ) ) {
                    $message = sprintf(
                        /* translators: %s: error message returned by ffmpeg. */
                        __( 'Video compression failed: %s', 'fp-publisher' ),
                        $error_output
                    );
                }
            }

            return new WP_Error( 'tts_transcoder_failed', $message );
        }

        if ( ! file_exists( $compressed_path ) ) {
            return new WP_Error(
                'tts_transcoder_output_missing',
                __( 'The video transcoder did not produce an output file.', 'fp-publisher' )
            );
        }

        $result = array(
            'compressed_path' => $compressed_path,
        );

        $upload_dir = wp_upload_dir();

        if ( ! empty( $upload_dir['basedir'] ) && ! empty( $upload_dir['baseurl'] ) && 0 === strpos( $compressed_path, $upload_dir['basedir'] ) ) {
            $result['compressed_url'] = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $compressed_path );
        }

        return $result;
    }

    /**
     * Determine whether shell execution is available.
     *
     * @return bool
     */
    private function is_shell_exec_available() {
        if ( ! function_exists( 'exec' ) ) {
            return false;
        }

        $disabled_functions = (string) ini_get( 'disable_functions' );

        if ( '' === $disabled_functions ) {
            return true;
        }

        $disabled_functions = array_map( 'trim', explode( ',', $disabled_functions ) );

        return ! in_array( 'exec', $disabled_functions, true );
    }

    /**
     * Analyze media performance across platforms.
     */
    public function ajax_analyze_media_performance() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
        }

        try {
            $analysis = $this->analyze_media_performance();
            
            wp_send_json_success( array(
                'analysis' => $analysis,
                'message' => __( 'Media performance analyzed successfully!', 'fp-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Media Performance Analysis Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to analyze performance. Please try again.', 'fp-publisher' ) ) );
        }
    }

    /**
     * Analyze media performance across platforms.
     *
     * @return array Performance analysis.
     */
    private function analyze_media_performance() {
        $posts = get_posts(
            array(
                'post_type'      => 'tts_social_post',
                'post_status'    => 'publish',
                'posts_per_page' => 50,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        $analysis = array(
            'total_posts_analyzed'       => 0,
            'platform_performance'       => array(),
            'top_performing_media'       => array(),
            'optimization_opportunities' => array(),
            'recommendations'            => array(),
            'posts_without_metrics'      => 0,
        );

        if ( empty( $posts ) ) {
            return $analysis;
        }

        $top_posts = array();

        foreach ( $posts as $post ) {
            $channels = get_post_meta( $post->ID, '_tts_social_channel', true );
            if ( empty( $channels ) ) {
                continue;
            }

            if ( ! is_array( $channels ) ) {
                $channels = array( $channels );
            }

            $channels = array_unique( array_filter( array_map( array( $this, 'normalize_platform_key' ), $channels ) ) );

            if ( empty( $channels ) ) {
                continue;
            }

            $metrics = get_post_meta( $post->ID, '_tts_metrics', true );
            if ( ! is_array( $metrics ) ) {
                $metrics = array();
            }

            $metrics = array_change_key_case( $metrics, CASE_LOWER );

            $post_has_metrics = false;
            $post_total       = 0;
            $post_platforms   = array();

            foreach ( $channels as $channel ) {
                if ( ! isset( $analysis['platform_performance'][ $channel ] ) ) {
                    $analysis['platform_performance'][ $channel ] = array(
                        'posts_count'               => 0,
                        'posts_without_metric_data' => 0,
                        'metrics'                   => array(),
                        'total_interactions'        => 0,
                        'missing_metrics'           => array(),
                    );
                }

                $platform_metrics = isset( $metrics[ $channel ] ) ? $metrics[ $channel ] : array();
                $flat_metrics     = $this->flatten_numeric_metrics( $platform_metrics );

                if ( empty( $flat_metrics ) ) {
                    $analysis['platform_performance'][ $channel ]['posts_without_metric_data']++;
                    continue;
                }

                $analysis['platform_performance'][ $channel ]['posts_count']++;
                $post_platforms[] = $channel;
                $post_has_metrics  = true;

                $post_platform_total = 0;

                foreach ( $flat_metrics as $metric_key => $value ) {
                    if ( ! isset( $analysis['platform_performance'][ $channel ]['metrics'][ $metric_key ] ) ) {
                        $analysis['platform_performance'][ $channel ]['metrics'][ $metric_key ] = array(
                            'total' => 0,
                            'count' => 0,
                        );
                    }

                    $analysis['platform_performance'][ $channel ]['metrics'][ $metric_key ]['total'] += (float) $value;
                    $analysis['platform_performance'][ $channel ]['metrics'][ $metric_key ]['count']++;
                    $post_platform_total += (float) $value;
                }

                $analysis['platform_performance'][ $channel ]['total_interactions'] += $post_platform_total;
                $post_total += $post_platform_total;
            }

            if ( $post_has_metrics ) {
                $analysis['total_posts_analyzed']++;

                if ( $post_total > 0 ) {
                    $permalink = get_permalink( $post );
                    $top_posts[ $post->ID ] = array(
                        'post_id'            => $post->ID,
                        'title'              => get_the_title( $post->ID ),
                        'permalink'          => $permalink ? $permalink : '',
                        'total_interactions' => round( $post_total, 2 ),
                        'platforms'          => array_values( array_unique( $post_platforms ) ),
                        'date'               => get_post_time( 'mysql', false, $post ),
                    );
                }
            } else {
                $analysis['posts_without_metrics']++;
            }
        }

        foreach ( $analysis['platform_performance'] as $platform => &$data ) {
            foreach ( $data['metrics'] as $metric_key => &$metric_data ) {
                $metric_data['total'] = round( $metric_data['total'], 2 );

                if ( $metric_data['count'] > 0 ) {
                    $metric_data['average'] = round( $metric_data['total'] / $metric_data['count'], 2 );
                } else {
                    $metric_data['average'] = null;
                }

                unset( $metric_data['count'] );
            }

            if ( $data['posts_count'] > 0 ) {
                $data['avg_interactions_per_post'] = round( $data['total_interactions'] / $data['posts_count'], 2 );
            } else {
                $data['avg_interactions_per_post'] = null;
            }

            $expected_metrics = $this->get_expected_metrics_for_platform( $platform );
            if ( ! empty( $expected_metrics ) ) {
                $present_metrics         = array_keys( $data['metrics'] );
                $data['missing_metrics'] = array_values( array_diff( $expected_metrics, $present_metrics ) );
            } else {
                $data['missing_metrics'] = array();
            }

            $data['total_interactions'] = round( $data['total_interactions'], 2 );

            if ( ! empty( $data['missing_metrics'] ) ) {
                $data['missing_metrics'] = array_values( array_unique( $data['missing_metrics'] ) );
            }

            if ( empty( $data['metrics'] ) ) {
                $data['metrics'] = array();
            } else {
                ksort( $data['metrics'] );
            }
        }
        unset( $data );

        if ( ! empty( $top_posts ) ) {
            uasort(
                $top_posts,
                function( $a, $b ) {
                    if ( $a['total_interactions'] === $b['total_interactions'] ) {
                        return strcmp( $b['date'], $a['date'] );
                    }

                    return ( $b['total_interactions'] <=> $a['total_interactions'] );
                }
            );

            $analysis['top_performing_media'] = array_slice( array_values( $top_posts ), 0, 5 );
        }

        $analysis['recommendations'] = $this->build_performance_recommendations( $analysis );

        return $analysis;
    }

    /**
     * Normalize a platform key for consistent indexing.
     *
     * @param mixed $platform Platform identifier.
     * @return string
     */
    private function normalize_platform_key( $platform ) {
        $platform = is_string( $platform ) ? $platform : (string) $platform;
        $platform = strtolower( trim( $platform ) );
        $platform = str_replace( array( ' ', '/' ), '_', $platform );
        $platform = preg_replace( '/[^a-z0-9_\-]/', '', $platform );

        return $platform;
    }

    /**
     * Normalize a metric key into snake case.
     *
     * @param mixed $key Metric key.
     * @return string
     */
    private function normalize_metric_key( $key ) {
        if ( is_int( $key ) || ( is_string( $key ) && ctype_digit( $key ) ) ) {
            return 'metric_' . $key;
        }

        $key = is_string( $key ) ? $key : (string) $key;
        $key = trim( $key );

        if ( '' === $key ) {
            return '';
        }

        $key = preg_replace( '/([a-z])([A-Z])/', '$1_$2', $key );
        $key = str_replace( array( '-', ' ', '/' ), '_', $key );
        $key = strtolower( $key );

        return $key;
    }

    /**
     * Flatten nested metrics into a single-level array of numeric values.
     *
     * @param mixed  $metrics Metrics array.
     * @param string $prefix  Metric prefix.
     * @return array
     */
    private function flatten_numeric_metrics( $metrics, $prefix = '' ) {
        $flattened = array();

        if ( is_object( $metrics ) ) {
            $metrics = (array) $metrics;
        }

        if ( ! is_array( $metrics ) ) {
            return $flattened;
        }

        foreach ( $metrics as $key => $value ) {
            $normalized_key = $this->normalize_metric_key( $key );
            $metric_key     = $prefix ? $prefix . '.' . $normalized_key : $normalized_key;

            if ( '' === $metric_key ) {
                continue;
            }

            if ( is_array( $value ) || is_object( $value ) ) {
                $child_metrics = $this->flatten_numeric_metrics( $value, $metric_key );

                if ( ! empty( $child_metrics ) ) {
                    $flattened = array_merge( $flattened, $child_metrics );
                }
            } elseif ( is_numeric( $value ) ) {
                $flattened[ $metric_key ] = (float) $value;
            }
        }

        return $flattened;
    }

    /**
     * Expected metrics for each platform.
     *
     * @param string $platform Platform key.
     * @return array
     */
    private function get_expected_metrics_for_platform( $platform ) {
        $expected = array(
            'facebook'  => array(
                'engagement.comment_count',
                'engagement.reaction_count',
                'engagement.share_count',
            ),
            'instagram' => array(
                'like_count',
                'comments_count',
            ),
            'youtube'   => array(
                'view_count',
                'like_count',
                'comment_count',
            ),
            'tiktok'    => array(
                'data.metrics.play_count',
                'data.metrics.like_count',
                'data.metrics.comment_count',
                'data.metrics.share_count',
            ),
        );

        return isset( $expected[ $platform ] ) ? $expected[ $platform ] : array();
    }

    /**
     * Build recommendations based on aggregated metrics.
     *
     * @param array $analysis Analysis data.
     * @return array
     */
    private function build_performance_recommendations( $analysis ) {
        $recommendations = array();

        $missing_posts = isset( $analysis['posts_without_metrics'] ) ? (int) $analysis['posts_without_metrics'] : 0;

        if ( $missing_posts > 0 ) {
            $recommendations[] = array(
                'category'       => __( 'Data completeness', 'fp-publisher' ),
                'recommendation' => sprintf(
                    _n(
                        '%s post is missing refreshed analytics. Run the analytics sync to update metrics.',
                        '%s posts are missing refreshed analytics. Run the analytics sync to update metrics.',
                        $missing_posts,
                        'fp-publisher'
                    ),
                    number_format_i18n( $missing_posts )
                ),
                'impact'         => __( 'High', 'fp-publisher' ),
                'effort'         => __( 'Low', 'fp-publisher' ),
            );
        }

        $platform_avgs = array();

        if ( ! empty( $analysis['platform_performance'] ) ) {
            foreach ( $analysis['platform_performance'] as $platform => $data ) {
                if ( isset( $data['avg_interactions_per_post'] ) && is_numeric( $data['avg_interactions_per_post'] ) && $data['avg_interactions_per_post'] > 0 ) {
                    $platform_avgs[ $platform ] = $data['avg_interactions_per_post'];
                }

                if ( ! empty( $data['missing_metrics'] ) ) {
                    $recommendations[] = array(
                        'category'       => sprintf( __( '%s metrics', 'fp-publisher' ), ucwords( $platform ) ),
                        'recommendation' => sprintf(
                            __( 'Some expected metrics (%1$s) are not available for %2$s. Verify the integration credentials and retry syncing.', 'fp-publisher' ),
                            implode( ', ', $data['missing_metrics'] ),
                            ucwords( $platform )
                        ),
                        'impact'         => __( 'Medium', 'fp-publisher' ),
                        'effort'         => __( 'Medium', 'fp-publisher' ),
                    );
                }

                if ( ! empty( $data['posts_without_metric_data'] ) ) {
                    $recommendations[] = array(
                        'category'       => sprintf( __( '%s coverage', 'fp-publisher' ), ucwords( $platform ) ),
                        'recommendation' => sprintf(
                            _n(
                                '%1$s post for %2$s has not returned analytics data yet.',
                                '%1$s posts for %2$s have not returned analytics data yet.',
                                $data['posts_without_metric_data'],
                                'fp-publisher'
                            ),
                            number_format_i18n( (int) $data['posts_without_metric_data'] ),
                            ucwords( $platform )
                        ),
                        'impact'         => __( 'Medium', 'fp-publisher' ),
                        'effort'         => __( 'Low', 'fp-publisher' ),
                    );
                }
            }
        }

        if ( ! empty( $platform_avgs ) ) {
            arsort( $platform_avgs );
            reset( $platform_avgs );
            $top_platform = key( $platform_avgs );
            $top_average  = current( $platform_avgs );

            $recommendations[] = array(
                'category'       => __( 'Content focus', 'fp-publisher' ),
                'recommendation' => sprintf(
                    __( '%1$s posts average %2$s interactions. Continue prioritizing this channel while engagement remains strong.', 'fp-publisher' ),
                    ucwords( $top_platform ),
                    number_format_i18n( round( $top_average, 2 ) )
                ),
                'impact'         => __( 'High', 'fp-publisher' ),
                'effort'         => __( 'Medium', 'fp-publisher' ),
            );

            end( $platform_avgs );
            $lowest_platform = key( $platform_avgs );
            $lowest_average  = current( $platform_avgs );

            if ( $lowest_platform && $lowest_platform !== $top_platform ) {
                $recommendations[] = array(
                    'category'       => __( 'Optimization', 'fp-publisher' ),
                    'recommendation' => sprintf(
                        __( '%1$s lags with an average of %2$s interactions per post. Review timing or creative to lift results.', 'fp-publisher' ),
                        ucwords( $lowest_platform ),
                        number_format_i18n( round( $lowest_average, 2 ) )
                    ),
                    'impact'         => __( 'Medium', 'fp-publisher' ),
                    'effort'         => __( 'Medium', 'fp-publisher' ),
                );
            }
        }

        if ( empty( $recommendations ) ) {
            $recommendations[] = array(
                'category'       => __( 'Monitoring', 'fp-publisher' ),
                'recommendation' => __( 'Metrics are up to date. Keep monitoring performance trends to surface new opportunities.', 'fp-publisher' ),
                'impact'         => __( 'Low', 'fp-publisher' ),
                'effort'         => __( 'Low', 'fp-publisher' ),
            );
        }

        return $recommendations;
    }

    /**
     * Add custom fields to media library.
     *
     * @param array $form_fields Form fields.
     * @param object $post Post object.
     * @return array Modified form fields.
     */
    public function add_media_fields( $form_fields, $post ) {
        // Add platform optimization status
        $form_fields['tts_platform_optimized'] = array(
            'label' => __( 'Platform Optimized', 'fp-publisher' ),
            'input' => 'html',
            'html' => '<select name="attachments[' . $post->ID . '][tts_platform_optimized]">
                        <option value="">Not optimized</option>
                        <option value="instagram">Instagram</option>
                        <option value="facebook">Facebook</option>
                        <option value="twitter">Twitter</option>
                        <option value="linkedin">LinkedIn</option>
                        <option value="youtube">YouTube</option>
                        <option value="tiktok">TikTok</option>
                      </select>',
            'value' => get_post_meta( $post->ID, '_tts_platform_optimized', true )
        );
        
        // Add usage rights
        $form_fields['tts_usage_rights'] = array(
            'label' => __( 'Usage Rights', 'fp-publisher' ),
            'input' => 'text',
            'value' => get_post_meta( $post->ID, '_tts_usage_rights', true ),
            'helps' => __( 'Specify usage rights and licensing information', 'fp-publisher' )
        );
        
        return $form_fields;
    }

    /**
     * Save custom media fields.
     *
     * @param array $post Post data.
     * @param array $attachment Attachment data.
     * @return array Post data.
     */
    public function save_media_fields( $post, $attachment ) {
        if ( isset( $attachment['tts_platform_optimized'] ) ) {
            update_post_meta( $post['ID'], '_tts_platform_optimized', sanitize_text_field( $attachment['tts_platform_optimized'] ) );
        }
        
        if ( isset( $attachment['tts_usage_rights'] ) ) {
            update_post_meta( $post['ID'], '_tts_usage_rights', sanitize_text_field( $attachment['tts_usage_rights'] ) );
        }
        
        return $post;
    }
}

// Initialize Advanced Media system
new TTS_Advanced_Media();