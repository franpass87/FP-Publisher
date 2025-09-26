<?php
/**
 * Remote media importer.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imports media files from a URL into the Media Library with caching and batching support.
 */
class TTS_Media_Importer {

	/**
	 * Cache group used for object cache entries.
	 */
	const CACHE_GROUP = 'tts_media_importer';

	/**
	 * Default cache lifetime for cached media identifiers.
	 */
	const DEFAULT_CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Register cache group on instantiation.
	 */
	public function __construct() {
		if ( function_exists( 'wp_cache_add_global_groups' ) ) {
			wp_cache_add_global_groups( array( self::CACHE_GROUP ) );
		}
	}

	/**
	 * Import a batch of URLs.
	 *
	 * @param array $urls List of remote URLs.
	 * @param array $args Optional import arguments shared across URLs.
	 *
	 * @return array Results keyed by original array keys.
	 */
	public function import_batch( array $urls, array $args = array() ) {
		$results = array();

		foreach ( $urls as $key => $url ) {
			$results[ $key ] = $this->import_from_url( $url, $args );
		}

		return $results;
	}

	/**
	 * Import a file from a URL and add it to the Media Library.
	 *
	 * @param string $url  Remote file URL.
	 * @param array  $args Optional import arguments.
	 *
	 * @return int|WP_Error Attachment ID on success, WP_Error on failure.
	 */
	public function import_from_url( $url, array $args = array() ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$url = esc_url_raw( $url );

		if ( empty( $url ) ) {
			return new WP_Error( 'invalid_url', __( 'Invalid media URL provided.', 'fp-publisher' ) );
		}

		$args = wp_parse_args(
			$args,
			array(
				'timeout'   => 30,
				'cache_ttl' => self::DEFAULT_CACHE_TTL,
				'force'     => false,
				'cdn_bust'  => false,
				'context'   => array(),
			)
		);

		$context   = is_array( $args['context'] ) ? $args['context'] : array();
		$cache_ttl = isset( $args['cache_ttl'] ) ? max( MINUTE_IN_SECONDS, absint( $args['cache_ttl'] ) ) : self::DEFAULT_CACHE_TTL;
		$cache_key = $this->build_cache_key( $url );

		if ( ! $args['force'] ) {
			$cdn_cached = $this->get_cdn_cached_attachment( $cache_key, $url, $args );
			if ( $cdn_cached && ! $args['cdn_bust'] ) {
				wp_cache_set( $cache_key, $cdn_cached, self::CACHE_GROUP, $cache_ttl );
				return (int) $cdn_cached;
			}

			$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( $cached && ! $args['cdn_bust'] ) {
				return (int) $cached;
			}

			$transient_cached = get_transient( $cache_key );
			if ( false !== $transient_cached ) {
				$transient_cached = (int) $transient_cached;
				wp_cache_set( $cache_key, $transient_cached, self::CACHE_GROUP, $cache_ttl );
				if ( ! $args['cdn_bust'] ) {
					$this->maybe_prime_cdn( $cache_key, $transient_cached, $url, $cache_ttl );
				}

				return $transient_cached;
			}
		}

		if ( preg_match( '/\.(jpe?g|png|gif|webp|bmp)$/i', $url ) && ! $args['force'] ) {
			$attachment_id = media_sideload_image( $url, 0, null, 'id' );
			if ( ! is_wp_error( $attachment_id ) ) {
				$attachment_id = (int) $attachment_id;
				$this->set_cached_attachment( $cache_key, $attachment_id, $url, $cache_ttl, $args['cdn_bust'] );
				return $attachment_id;
			}
		}

		$download = $this->download_stream( $url, $args['timeout'] );

		if ( is_wp_error( $download ) ) {
			$this->log_stream_error( $url, $download, array_merge( $context, array( 'stage' => 'download' ) ) );
			return $download;
		}

		$tmp_file = $download['file'];
		$headers  = $download['headers'];

		try {
			$file_array = array(
				'name'     => $this->determine_filename( $url, $headers ),
				'tmp_name' => $tmp_file,
			);

			$overrides = array( 'test_form' => false );
			$results   = wp_handle_sideload( $file_array, $overrides );

			if ( isset( $results['error'] ) ) {
				$error = new WP_Error( 'sideload_error', $results['error'] );
				$this->log_stream_error( $url, $error, array_merge( $context, array( 'stage' => 'sideload' ) ) );
				return $error;
			}

			$attachment = array(
				'post_mime_type' => $results['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', $file_array['name'] ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			$attach_id = wp_insert_attachment( $attachment, $results['file'] );

			if ( is_wp_error( $attach_id ) ) {
				$this->log_stream_error( $url, $attach_id, array_merge( $context, array( 'stage' => 'insert' ) ) );
				return $attach_id;
			}

			$attach_id = (int) $attach_id;

			$attach_data = wp_generate_attachment_metadata( $attach_id, $results['file'] );
			wp_update_attachment_metadata( $attach_id, $attach_data );

			$this->set_cached_attachment( $cache_key, $attach_id, $url, $cache_ttl, $args['cdn_bust'] );

			return $attach_id;
		} finally {
			if ( file_exists( $tmp_file ) ) {
				@unlink( $tmp_file );
			}
		}
	}

	/**
	 * Remove a URL from all cache layers.
	 *
	 * @param string $url Remote URL to purge.
	 */
	public function purge_cache( $url ) {
		$cache_key = $this->build_cache_key( $url );
		delete_transient( $cache_key );
		wp_cache_delete( $cache_key, self::CACHE_GROUP );
		do_action( 'tts_media_importer_cdn_purge', $cache_key, $url );
	}

	/**
	 * Build a cache key for a remote URL.
	 *
	 * @param string $url Remote URL.
	 *
	 * @return string Cache key.
	 */
	private function build_cache_key( $url ) {
		return 'tts_media_' . md5( strtolower( $url ) );
	}

	/**
	 * Download a remote file using a streaming HTTP request.
	 *
	 * @param string $url     Remote URL.
	 * @param int    $timeout Request timeout.
	 *
	 * @return array|WP_Error Download metadata on success, error otherwise.
	 */
	private function download_stream( $url, $timeout ) {
		$tmp = wp_tempnam( $url );
		if ( ! $tmp ) {
			return new WP_Error( 'temp_file_failed', __( 'Unable to create a temporary file for download.', 'fp-publisher' ) );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => max( 1, absint( $timeout ) ),
				'stream'              => true,
				'filename'            => $tmp,
				'limit_response_size' => $this->get_stream_size_limit(),
			)
		);

		if ( is_wp_error( $response ) ) {
			@unlink( $tmp );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			@unlink( $tmp );
			return new WP_Error( 'http_error', sprintf( __( 'Unexpected HTTP status: %d', 'fp-publisher' ), $status_code ) );
		}

		if ( ! file_exists( $tmp ) || 0 === filesize( $tmp ) ) {
			@unlink( $tmp );
			return new WP_Error( 'empty_download', __( 'Downloaded file is empty.', 'fp-publisher' ) );
		}

		$content_length = wp_remote_retrieve_header( $response, 'content-length' );
		if ( $content_length && absint( $content_length ) > 0 ) {
			$actual_size = filesize( $tmp );
			if ( absint( $content_length ) !== $actual_size ) {
				@unlink( $tmp );
				return new WP_Error( 'incomplete_download', __( 'Download was interrupted before completion.', 'fp-publisher' ) );
			}
		}

		return array(
			'file'    => $tmp,
			'headers' => array(
				'content_type'   => wp_remote_retrieve_header( $response, 'content-type' ),
				'content_length' => $content_length,
				'disposition'    => wp_remote_retrieve_header( $response, 'content-disposition' ),
			),
		);
	}

	/**
	 * Resolve a normalized filename from headers or URL.
	 *
	 * @param string $url     Remote URL.
	 * @param array  $headers Response headers.
	 *
	 * @return string File name.
	 */
	private function determine_filename( $url, array $headers ) {
		if ( ! empty( $headers['disposition'] ) ) {
			$disposition = (string) $headers['disposition'];

			if ( preg_match( '/filename\*=UTF-8\'\'([^;]+)/i', $disposition, $matches ) ) {
				$candidate = rawurldecode( $matches[1] );
				if ( $candidate ) {
					return sanitize_file_name( $candidate );
				}
			}

			if ( preg_match( '/filename="?([^";]+)"?/i', $disposition, $matches ) ) {
				$candidate = $matches[1] ?? '';
				if ( $candidate ) {
					return sanitize_file_name( $candidate );
				}
			}
		}

		$path     = wp_parse_url( $url, PHP_URL_PATH );
		$basename = $path ? basename( $path ) : '';
		$basename = sanitize_file_name( $basename );

		if ( ! empty( $basename ) ) {
			return $basename;
		}

		return 'remote-media-' . substr( md5( $url ), 0, 12 );
	}

	/**
	 * Store attachment identifier in cache and optionally propagate to CDN.
	 *
	 * @param string $cache_key    Cache key.
	 * @param int    $attachment_id Attachment identifier.
	 * @param string $url           Remote URL.
	 * @param int    $ttl           Cache lifetime.
	 * @param bool   $skip_cdn      Whether CDN priming should be skipped.
	 */
	private function set_cached_attachment( $cache_key, $attachment_id, $url, $ttl, $skip_cdn = false ) {
		if ( $attachment_id <= 0 ) {
			return;
		}

		wp_cache_set( $cache_key, $attachment_id, self::CACHE_GROUP, $ttl );
		set_transient( $cache_key, $attachment_id, $ttl );

		if ( ! $skip_cdn ) {
			$this->maybe_prime_cdn( $cache_key, $attachment_id, $url, $ttl );
		}
	}

	/**
	 * Retrieve an attachment identifier from an external CDN cache.
	 *
	 * @param string $cache_key Cache key.
	 * @param string $url       Remote URL.
	 * @param array  $args      Import arguments.
	 *
	 * @return int|false Attachment identifier if available, false otherwise.
	 */
	private function get_cdn_cached_attachment( $cache_key, $url, array $args ) {
		$value = apply_filters( 'tts_media_importer_cdn_get', null, $cache_key, $url, $args );

		if ( null === $value ) {
			return false;
		}

		return absint( $value );
	}

	/**
	 * Notify CDN listeners about a cached attachment.
	 *
	 * @param string $cache_key     Cache key.
	 * @param int    $attachment_id Attachment identifier.
	 * @param string $url           Remote URL.
	 * @param int    $ttl           Cache lifetime.
	 */
	private function maybe_prime_cdn( $cache_key, $attachment_id, $url, $ttl ) {
		do_action( 'tts_media_importer_cdn_set', $cache_key, $attachment_id, $url, $ttl );
	}

	/**
	 * Record stream errors for monitoring and observability.
	 *
	 * @param string         $url     Remote URL.
	 * @param WP_Error|mixed $error   Error instance or message.
	 * @param array          $context Additional context.
	 */
	private function log_stream_error( $url, $error, array $context = array() ) {
		if ( ! is_wp_error( $error ) ) {
			$error = new WP_Error( 'media_import_error', is_scalar( $error ) ? (string) $error : __( 'Unknown media import error.', 'fp-publisher' ) );
		}

		do_action( 'tts_media_importer_stream_error', $url, $error, $context );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[TTS_Media_Importer] %s: %s', $url, $error->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Determine the maximum number of bytes allowed when streaming downloads.
	 *
	 * @return int
	 */
	private function get_stream_size_limit() {
		$megabyte = defined( 'MB_IN_BYTES' ) ? MB_IN_BYTES : 1024 * 1024;
		return 50 * $megabyte;
	}
}
