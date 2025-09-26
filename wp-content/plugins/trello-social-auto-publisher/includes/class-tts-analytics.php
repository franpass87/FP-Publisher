<?php
/**
 * Fetch social metrics for published posts.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analytics utilities.
 */
class TTS_Analytics {

	const LOCK_TRANSIENT           = 'tts_analytics_fetch_in_progress';
	const OPTION_LAST_PROCESSED_ID = 'tts_analytics_last_processed_id';
	const OPTION_LAST_RUN          = 'tts_analytics_last_run';
	const ASYNC_ACTION_HOOK        = 'tts_fetch_post_metrics';

	/**
	 * Register asynchronous processing hook.
	 */
	public static function register_async_hook() {
		add_action( self::ASYNC_ACTION_HOOK, array( __CLASS__, 'process_post_metrics' ), 10, 1 );
	}

	/**
	 * Fetch metrics for published social posts in controllable batches.
	 */
	public static function fetch_all() {
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			TTS_Logger::log(
				'Analytics batch skipped because a previous run is still in progress.',
				'notice',
				array( 'lock' => self::LOCK_TRANSIENT )
			);
			return;
		}

		set_transient( self::LOCK_TRANSIENT, 1, 30 * MINUTE_IN_SECONDS );

		$overall_start     = microtime( true );
		$batch_size        = (int) apply_filters( 'tts_analytics_batch_size', 75 );
		$batch_size        = max( 1, $batch_size );
		$max_batches       = (int) apply_filters( 'tts_analytics_max_batches_per_run', 5 );
		$max_batches       = max( 1, $max_batches );
		$pointer           = (int) get_option( self::OPTION_LAST_PROCESSED_ID, 0 );
		$total_processed   = 0;
		$total_scheduled   = 0;
		$total_immediate   = 0;
		$total_already_set = 0;
		$batches_executed  = 0;
		$scheduler_enabled = function_exists( 'as_schedule_single_action' );

		try {
			for ( $batch_index = 0; $batch_index < $max_batches; $batch_index++ ) {
				$batch_start = microtime( true );
				$post_ids    = self::query_batch_after( $pointer, $batch_size );

				if ( empty( $post_ids ) ) {
					if ( 0 !== $pointer ) {
						TTS_Logger::log(
							'Analytics batch run reached the end of the dataset. Resetting pointer.',
							'debug',
							array(
								'last_pointer' => $pointer,
								'batch_size'   => $batch_size,
							)
						);
					}

					$pointer = 0;
					update_option( self::OPTION_LAST_PROCESSED_ID, $pointer, false );
					break;
				}

				++$batches_executed;
				$scheduled_in_batch = 0;
				$immediate_in_batch = 0;
				$already_in_queue   = 0;

				foreach ( $post_ids as $post_id ) {
					++$total_processed;
					if ( $scheduler_enabled ) {
						$scheduled = self::schedule_async_job( $post_id );
						if ( $scheduled ) {
							++$scheduled_in_batch;
							++$total_scheduled;
						} else {
							++$already_in_queue;
							++$total_already_set;
						}
					} else {
						self::process_post_metrics( $post_id );
						++$immediate_in_batch;
						++$total_immediate;
					}
				}

				$pointer       = (int) end( $post_ids );
				$batch_time    = (int) round( ( microtime( true ) - $batch_start ) * 1000 );
				$has_more      = count( $post_ids ) >= $batch_size;
				$batch_context = array(
					'pointer'             => $pointer,
					'processed'           => count( $post_ids ),
					'duration_ms'         => $batch_time,
					'scheduled'           => $scheduled_in_batch,
					'immediate'           => $immediate_in_batch,
					'already_in_queue'    => $already_in_queue,
					'scheduler_available' => $scheduler_enabled,
					'batch'               => $batch_index + 1,
				);

				TTS_Logger::log( 'Analytics batch processed.', 'info', $batch_context );

				update_option( self::OPTION_LAST_PROCESSED_ID, $pointer, false );

				if ( ! $has_more ) {
					$pointer = 0;
					update_option( self::OPTION_LAST_PROCESSED_ID, $pointer, false );
					break;
				}
			}

			if ( $batches_executed >= $max_batches && $pointer > 0 ) {
				TTS_Logger::log(
					'Analytics fetch paused after reaching the batch limit; processing will resume on the next run.',
					'notice',
					array(
						'pointer'     => $pointer,
						'max_batches' => $max_batches,
					)
				);
			}

			$overall_duration = (int) round( ( microtime( true ) - $overall_start ) * 1000 );
			update_option(
				self::OPTION_LAST_RUN,
				array(
					'timestamp'        => current_time( 'timestamp' ),
					'duration_ms'      => $overall_duration,
					'processed'        => $total_processed,
					'batches'          => $batches_executed,
					'batch_size'       => $batch_size,
					'max_batches'      => $max_batches,
					'scheduled'        => $total_scheduled,
					'immediate'        => $total_immediate,
					'already_in_queue' => $total_already_set,
					'pointer'          => $pointer,
				),
				false
			);

			TTS_Logger::log(
				'Analytics fetch run completed.',
				'notice',
				array(
					'processed'        => $total_processed,
					'duration_ms'      => $overall_duration,
					'batches'          => $batches_executed,
					'batch_size'       => $batch_size,
					'max_batches'      => $max_batches,
					'scheduled'        => $total_scheduled,
					'immediate'        => $total_immediate,
					'already_in_queue' => $total_already_set,
					'pointer'          => $pointer,
				)
			);
		} catch ( Throwable $throwable ) {
			$error_duration = (int) round( ( microtime( true ) - $overall_start ) * 1000 );
			TTS_Logger::log(
				'Analytics batch execution failed: ' . $throwable->getMessage(),
				'error',
				array(
					'duration_ms' => $error_duration,
					'pointer'     => $pointer,
					'processed'   => $total_processed,
				)
			);
		} finally {
			delete_transient( self::LOCK_TRANSIENT );
		}
	}

	/**
	 * Query the next batch of posts after the provided pointer.
	 *
	 * @param int $pointer   Last processed post ID.
	 * @param int $batch_size Number of posts to fetch.
	 *
	 * @return int[]
	 */
	private static function query_batch_after( $pointer, $batch_size ) {
		$args = array(
			'post_type'              => 'tts_social_post',
			'post_status'            => 'any',
			'posts_per_page'         => $batch_size,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'meta_query'             => array(
				array(
					'key'   => '_published_status',
					'value' => 'published',
				),
			),
		);

		$filter = null;
		if ( $pointer > 0 ) {
			$filter = function ( $where ) use ( $pointer ) {
				global $wpdb;
				return $where . $wpdb->prepare( ' AND ' . $wpdb->posts . '.ID > %d', $pointer );
			};
			add_filter( 'posts_where', $filter );
		}

		if ( ! class_exists( 'WP_Query' ) ) {
			require_once ABSPATH . WPINC . '/class-wp-query.php';
		}

		try {
			$query = new WP_Query( $args );
		} finally {
			if ( $filter ) {
				remove_filter( 'posts_where', $filter );
			}
		}

		if ( ! isset( $query->posts ) || ! is_array( $query->posts ) ) {
			return array();
		}

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Schedule an asynchronous analytics job for the provided post ID.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool True when the job has been scheduled, false if it was already queued or scheduling is unavailable.
	 */
	private static function schedule_async_job( $post_id ) {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return false;
		}

		$args = array( (int) $post_id );

		if ( function_exists( 'as_next_scheduled_action' ) ) {
			$existing = as_next_scheduled_action( self::ASYNC_ACTION_HOOK, $args, 'tts_analytics' );
			if ( $existing ) {
				return false;
			}
		}

		static $offset = 0;
		$spacing       = (int) apply_filters( 'tts_analytics_schedule_interval', 30 );
		$spacing       = max( 0, $spacing );
		$timestamp     = time();

		if ( $spacing > 0 ) {
			$timestamp += $offset * $spacing;
		}

		as_schedule_single_action( $timestamp, self::ASYNC_ACTION_HOOK, $args, 'tts_analytics' );

		if ( $spacing > 0 ) {
			++$offset;
		}

		return true;
	}

	/**
	 * Process metrics for a single post (used both synchronously and via Action Scheduler).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function process_post_metrics( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		$start_time = microtime( true );
		$client_id  = (int) get_post_meta( $post_id, '_tts_client_id', true );

		if ( ! $client_id ) {
			TTS_Logger::log(
				'Skipping analytics processing because the post has no associated client.',
				'debug',
				array( 'post_id' => $post_id )
			);
			return;
		}

		$tokens = array(
			'facebook'  => get_post_meta( $client_id, '_tts_fb_token', true ),
			'instagram' => get_post_meta( $client_id, '_tts_ig_token', true ),
			'youtube'   => get_post_meta( $client_id, '_tts_yt_token', true ),
			'tiktok'    => get_post_meta( $client_id, '_tts_tt_token', true ),
		);

		$channels_meta = get_post_meta( $post_id, '_tts_social_channel', true );
		$channels      = is_array( $channels_meta ) ? array_filter( $channels_meta ) : array_filter( array( $channels_meta ) );

		if ( empty( $channels ) ) {
			TTS_Logger::log(
				'Skipping analytics processing because no channels are associated with the post.',
				'debug',
				array( 'post_id' => $post_id )
			);
			return;
		}

		$metrics = array();
		$errors  = array();

		foreach ( $channels as $channel ) {
			$method = 'fetch_' . $channel . '_metrics';
			if ( ! method_exists( __CLASS__, $method ) ) {
				$errors[ $channel ] = 'Metrics fetcher not available for channel.';
				continue;
			}

			$credentials = isset( $tokens[ $channel ] ) ? $tokens[ $channel ] : '';
			$result      = call_user_func( array( __CLASS__, $method ), $post_id, $credentials );

			if ( is_wp_error( $result ) ) {
				$errors[ $channel ] = $result->get_error_message();
				continue;
			}

			$metrics[ $channel ] = self::count_interactions( (array) $result );
		}

		if ( ! empty( $metrics ) ) {
			update_post_meta( $post_id, '_tts_metrics', $metrics );
		}

		$duration_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );
		$context     = array(
			'post_id'     => $post_id,
			'client_id'   => $client_id,
			'channels'    => array_values( $channels ),
			'metrics_set' => count( $metrics ),
			'duration_ms' => $duration_ms,
		);

		if ( ! empty( $errors ) ) {
			$context['errors'] = $errors;
			TTS_Logger::log( 'Analytics metrics processed with partial failures.', 'warning', $context );
		} else {
			TTS_Logger::log( 'Analytics metrics processed successfully.', 'debug', $context );
		}
	}

	/**
	 * Count total interactions from a metrics array.
	 *
	 * @param array $data Metrics data.
	 * @return int
	 */
	private static function count_interactions( $data ) {
		$sum = 0;
		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				$sum += self::count_interactions( $value );
			} elseif ( is_numeric( $value ) ) {
				$sum += (int) $value;
			}
		}
		return $sum;
	}

	/**
	 * Fetch Facebook metrics.
	 *
	 * @param int   $post_id     Post ID.
	 * @param mixed $credentials Access token.
	 *
	 * @return array|WP_Error
	 */
	public static function fetch_facebook_metrics( $post_id, $credentials ) {
		if ( empty( $credentials ) ) {
			return new WP_Error( 'fb_no_token', __( 'Facebook token missing', 'fp-publisher' ) );
		}

		$remote_id = get_post_meta( $post_id, '_tts_facebook_id', true );
		if ( empty( $remote_id ) ) {
			return new WP_Error( 'fb_no_id', __( 'Missing Facebook post ID', 'fp-publisher' ) );
		}

		$endpoint = sprintf( 'https://graph.facebook.com/%s?fields=engagement&access_token=%s', rawurlencode( $remote_id ), rawurlencode( $credentials ) );
		$response = wp_remote_get( $endpoint, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_decode_error', 'Failed to decode Facebook API response' );
		}
		return isset( $data['engagement'] ) ? $data['engagement'] : array();
	}

	/**
	 * Fetch Instagram metrics.
	 *
	 * @param int   $post_id     Post ID.
	 * @param mixed $credentials Access token.
	 *
	 * @return array|WP_Error
	 */
	public static function fetch_instagram_metrics( $post_id, $credentials ) {
		if ( empty( $credentials ) ) {
			return new WP_Error( 'ig_no_token', __( 'Instagram token missing', 'fp-publisher' ) );
		}

		$remote_id = get_post_meta( $post_id, '_tts_instagram_id', true );
		if ( empty( $remote_id ) ) {
			return new WP_Error( 'ig_no_id', __( 'Missing Instagram media ID', 'fp-publisher' ) );
		}

		$endpoint = sprintf( 'https://graph.facebook.com/%s?fields=like_count,comments_count&access_token=%s', rawurlencode( $remote_id ), rawurlencode( $credentials ) );
		$response = wp_remote_get( $endpoint, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_decode_error', 'Failed to decode Instagram API response' );
		}
		return $data;
	}

	/**
	 * Fetch YouTube metrics.
	 *
	 * @param int   $post_id     Post ID.
	 * @param mixed $credentials API key or access token.
	 *
	 * @return array|WP_Error
	 */
	public static function fetch_youtube_metrics( $post_id, $credentials ) {
		if ( empty( $credentials ) ) {
			return new WP_Error( 'yt_no_token', __( 'YouTube token missing', 'fp-publisher' ) );
		}

		$remote_id = get_post_meta( $post_id, '_tts_youtube_id', true );
		if ( empty( $remote_id ) ) {
			return new WP_Error( 'yt_no_id', __( 'Missing YouTube video ID', 'fp-publisher' ) );
		}

		$access_token = '';
		$api_key      = '';

		if ( is_object( $credentials ) ) {
			$credentials = (array) $credentials;
		}

		if ( is_array( $credentials ) ) {
			$access_token = isset( $credentials['access_token'] ) ? $credentials['access_token'] : '';
			$api_key      = isset( $credentials['api_key'] ) ? $credentials['api_key'] : '';
		} elseif ( is_string( $credentials ) ) {
			$raw_creds = trim( $credentials );

			$decoded = json_decode( $raw_creds, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				$access_token = isset( $decoded['access_token'] ) ? $decoded['access_token'] : '';
				$api_key      = isset( $decoded['api_key'] ) ? $decoded['api_key'] : '';
			} elseif ( preg_match( '/^[A-Za-z0-9_\-]{35,40}$/', $raw_creds ) ) {
				// Assume this is a Google API key based on its format (typically 39 chars, alphanumeric, -, _)
				$api_key = $raw_creds;
			} else {
				$access_token = $raw_creds;
			}
		}

		$query_args = array(
			'id'   => $remote_id,
			'part' => 'statistics',
		);

		if ( ! empty( $api_key ) ) {
			$query_args['key'] = $api_key;
		}

		$request_args = array( 'timeout' => 20 );
		if ( ! empty( $access_token ) ) {
			$request_args['headers'] = array(
				'Authorization' => 'Bearer ' . $access_token,
			);
		}

		$endpoint = add_query_arg( $query_args, 'https://www.googleapis.com/youtube/v3/videos' );
		$response = wp_remote_get( $endpoint, $request_args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_decode_error', 'Failed to decode YouTube API response' );
		}
		if ( isset( $data['items'][0]['statistics'] ) ) {
			return $data['items'][0]['statistics'];
		}
		return array();
	}

	/**
	 * Fetch TikTok metrics.
	 *
	 * @param int   $post_id     Post ID.
	 * @param mixed $credentials Access token.
	 *
	 * @return array|WP_Error
	 */
	public static function fetch_tiktok_metrics( $post_id, $credentials ) {
		if ( empty( $credentials ) ) {
			return new WP_Error( 'tt_no_token', __( 'TikTok token missing', 'fp-publisher' ) );
		}

		$remote_id = get_post_meta( $post_id, '_tts_tiktok_id', true );
		if ( empty( $remote_id ) ) {
			return new WP_Error( 'tt_no_id', __( 'Missing TikTok video ID', 'fp-publisher' ) );
		}

		$endpoint = sprintf( 'https://open.tiktokapis.com/v2/video/%s/metrics?access_token=%s', rawurlencode( $remote_id ), rawurlencode( $credentials ) );
		$response = wp_remote_get( $endpoint, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_decode_error', 'Failed to decode TikTok API response' );
		}
		return $data;
	}
}
