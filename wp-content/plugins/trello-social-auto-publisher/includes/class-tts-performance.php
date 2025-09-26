<?php
/**
 * Performance optimization utilities for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performance optimization and caching utilities.
 */
class TTS_Performance {

	/**
	 * Cache group for transients.
	 */
	const CACHE_GROUP = 'tts_performance';

	/**
	 * Default cache expiration (5 minutes).
	 */
	const DEFAULT_EXPIRATION = 300;

	/**
	 * Option key storing aggregated profiling statistics.
	 */
	const PROFILER_OPTION_KEY = 'tts_profiler_stats';

	/**
	 * Active profiling sessions keyed by component.
	 *
	 * @var array
	 */
	private static $active_profiles = array();

	/**
	 * Get cached dashboard statistics.
	 *
	 * @return array Dashboard statistics.
	 */
	public static function get_cached_dashboard_stats() {
		$cache_key = 'tts_dashboard_stats';
		$stats     = get_transient( $cache_key );

		if ( false === $stats ) {
			$stats = self::generate_dashboard_stats();
			set_transient( $cache_key, $stats, self::DEFAULT_EXPIRATION );
		}

		return $stats;
	}

	/**
	 * Generate dashboard statistics with enhanced caching and optimization.
	 *
	 * @return array Statistics array.
	 */
	private static function generate_dashboard_stats() {
		global $wpdb;

		$stats = array();

		try {
			// Ultra-optimized single query for all post statistics
			$query = $wpdb->prepare(
				"
                SELECT 
                    COUNT(CASE WHEN p.post_status = 'publish' THEN 1 END) as total_published,
                    COUNT(CASE WHEN p.post_status = 'draft' THEN 1 END) as total_pending,
                    COUNT(CASE WHEN p.post_status = 'future' THEN 1 END) as total_scheduled,
                    COUNT(CASE WHEN DATE(p.post_date) = %s AND p.post_status = 'publish' THEN 1 END) as published_today,
                    COUNT(CASE WHEN DATE(p.post_date) >= DATE_SUB(%s, INTERVAL 7 DAY) AND p.post_status = 'publish' THEN 1 END) as published_week,
                    COUNT(CASE WHEN DATE(p.post_date) >= DATE_SUB(%s, INTERVAL 30 DAY) AND p.post_status = 'publish' THEN 1 END) as published_month,
                    AVG(CASE WHEN p.post_status = 'publish' THEN 
                        DATEDIFF(p.post_modified, p.post_date) 
                    END) as avg_processing_days
                FROM {$wpdb->posts} p
                WHERE p.post_type = 'tts_social_post'
                AND p.post_status IN ('publish', 'draft', 'future')
            ",
				current_time( 'Y-m-d' ),
				current_time( 'Y-m-d' ),
				current_time( 'Y-m-d' )
			);

			$result = $wpdb->get_row( $query, ARRAY_A );

			if ( $result ) {
				$stats = array(
					'total_posts'         => (int) $result['total_published'],
					'pending_posts'       => (int) $result['total_pending'],
					'scheduled_posts'     => (int) $result['total_scheduled'],
					'published_today'     => (int) $result['published_today'],
					'published_week'      => (int) $result['published_week'],
					'published_month'     => (int) $result['published_month'],
					'avg_processing_days' => round( (float) $result['avg_processing_days'], 1 ),
					'next_scheduled'      => self::get_next_scheduled_post(),
					'performance_metrics' => self::get_performance_metrics(),
					'active_channels'     => self::get_active_channels_optimized(),
					'success_rate'        => self::calculate_success_rate_optimized(),
					'system_health'       => self::get_system_health_score(),
					'trends'              => self::calculate_trend_data(),
					'last_updated'        => current_time( 'mysql' ),
				);
			}
		} catch ( Exception $e ) {
			tts_log_event( 0, 'performance', 'error', 'Dashboard stats generation failed: ' . $e->getMessage(), '' );

			// Enhanced fallback stats
			$stats = array(
				'total_posts'         => 0,
				'pending_posts'       => 0,
				'scheduled_posts'     => 0,
				'published_today'     => 0,
				'published_week'      => 0,
				'published_month'     => 0,
				'avg_processing_days' => 0,
				'next_scheduled'      => null,
				'active_channels'     => array(),
				'success_rate'        => 0,
				'system_health'       => 50,
				'trends'              => array(),
				'last_updated'        => current_time( 'mysql' ),
				'error'               => true,
			);
		}

		return $stats;
	}

	/**
	 * Get next scheduled post information.
	 *
	 * @return array|null Next scheduled post data.
	 */
	private static function get_next_scheduled_post() {
		global $wpdb;

		$query = $wpdb->prepare(
			"
            SELECT p.ID, p.post_title, pm.meta_value as publish_at
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'tts_social_post'
            AND p.post_status = 'future'
            AND pm.meta_key = '_tts_publish_at'
            AND pm.meta_value > %s
            ORDER BY pm.meta_value ASC
            LIMIT 1
        ",
			current_time( 'mysql' )
		);

		$result = $wpdb->get_row( $query, ARRAY_A );

		return $result ? array(
			'id'         => (int) $result['ID'],
			'title'      => $result['post_title'],
			'publish_at' => $result['publish_at'],
		) : null;
	}

	/**
	 * Get active social media channels (optimized).
	 *
	 * @return array Active channels with statistics.
	 */
	private static function get_active_channels_optimized() {
		global $wpdb;

		$cache_key = 'tts_active_channels_stats';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$rows = $wpdb->get_results(
			"
            SELECT
                pm.post_id,
                pm.meta_value,
                p.post_status,
                p.post_date
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_tts_social_channel'
            AND p.post_type = 'tts_social_post'
            AND p.post_status != 'trash'
        ",
			ARRAY_A
		);

		$channel_labels = array(
			'facebook'  => __( 'Facebook', 'fp-publisher' ),
			'instagram' => __( 'Instagram', 'fp-publisher' ),
			'youtube'   => __( 'YouTube', 'fp-publisher' ),
			'tiktok'    => __( 'TikTok', 'fp-publisher' ),
		);

		$accumulators = array();

		foreach ( (array) $rows as $row ) {
			$meta_value = maybe_unserialize( $row['meta_value'] );

			if ( empty( $meta_value ) ) {
				continue;
			}

			if ( ! is_array( $meta_value ) ) {
				$meta_value = array( $meta_value );
			}

			$channel_slugs = array();

			foreach ( $meta_value as $slug ) {
				if ( is_scalar( $slug ) ) {
					$normalized_slug = sanitize_key( $slug );

					if ( '' !== $normalized_slug ) {
						$channel_slugs[] = $normalized_slug;
					}
				}
			}

			if ( empty( $channel_slugs ) ) {
				continue;
			}

			$channel_slugs  = array_unique( $channel_slugs );
			$post_status    = $row['post_status'];
			$post_date      = $row['post_date'];
			$post_timestamp = $post_date ? strtotime( $post_date ) : false;

			foreach ( $channel_slugs as $slug ) {
				if ( ! isset( $accumulators[ $slug ] ) ) {
					$accumulators[ $slug ] = array(
						'posts'                   => 0,
						'published'               => 0,
						'last_activity'           => null,
						'last_activity_timestamp' => 0,
					);
				}

				++$accumulators[ $slug ]['posts'];

				if ( 'publish' === $post_status ) {
					++$accumulators[ $slug ]['published'];
				}

				if ( $post_timestamp && $post_timestamp > $accumulators[ $slug ]['last_activity_timestamp'] ) {
					$accumulators[ $slug ]['last_activity_timestamp'] = $post_timestamp;
					$accumulators[ $slug ]['last_activity']           = $post_date;
				}
			}
		}

		$result = array();

		foreach ( $accumulators as $slug => $data ) {
			$name = isset( $channel_labels[ $slug ] )
				? $channel_labels[ $slug ]
				: ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );

			$posts     = (int) $data['posts'];
			$published = (int) $data['published'];

			$result[] = array(
				'name'          => $name,
				'slug'          => $slug,
				'posts'         => $posts,
				'published'     => $published,
				'last_activity' => $data['last_activity'],
				'success_rate'  => $posts > 0 ? round( ( $published / $posts ) * 100, 1 ) : 0,
			);
		}

		usort(
			$result,
			function ( $a, $b ) {
				return $b['posts'] <=> $a['posts'];
			}
		);

		set_transient( $cache_key, $result, 15 * MINUTE_IN_SECONDS );
		return $result;
	}

	/**
	 * Calculate publishing success rate (optimized).
	 *
	 * @return array Success rate data with trends.
	 */
	private static function calculate_success_rate_optimized() {
		global $wpdb;

		$cache_key = 'tts_success_rate_stats';

		// Define analysis periods using numeric durations.
		$periods = array(
			'today' => DAY_IN_SECONDS,
			'week'  => WEEK_IN_SECONDS,
			'month' => 30 * DAY_IN_SECONDS,
		);

		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			$has_valid_cutoffs = true;

			foreach ( array_keys( $periods ) as $period_key ) {
				if ( ! isset( $cached[ $period_key ]['cutoff'] ) ) {
					$has_valid_cutoffs = false;
					break;
				}
			}

			if ( $has_valid_cutoffs ) {
				return $cached;
			}

			delete_transient( $cache_key );
		}

		$table             = $wpdb->prefix . 'tts_logs';
		$success_data      = array();
		$current_timestamp = current_time( 'timestamp' );

		foreach ( $periods as $period => $duration ) {
			$duration = absint( $duration );

			if ( $duration <= 0 ) {
				continue;
			}

			$cutoff_timestamp = max( 0, $current_timestamp - $duration );
                        $cutoff           = wp_date( 'Y-m-d H:i:s', $cutoff_timestamp );

			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
					$cutoff
				)
			);

			if ( $total > 0 ) {
				$successful = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} WHERE status = %s AND created_at >= %s",
						'success',
						$cutoff
					)
				);

				$success_data[ $period ] = array(
					'rate'       => round( ( $successful / $total ) * 100, 2 ),
					'total'      => $total,
					'successful' => $successful,
					'failed'     => max( 0, $total - $successful ),
					'cutoff'     => $cutoff,
				);
			} else {
				$success_data[ $period ] = array(
					'rate'       => 100.0,
					'total'      => 0,
					'successful' => 0,
					'failed'     => 0,
					'cutoff'     => $cutoff,
				);
			}
		}

		delete_transient( $cache_key );
		set_transient( $cache_key, $success_data, 10 * MINUTE_IN_SECONDS );

		return $success_data;
	}

	/**
	 * Calculate system health score.
	 *
	 * @return int Health score (0-100).
	 */
	public static function get_system_health_score() {
		$score  = 100;
		$checks = array();

		// Check database performance
		$db_start = microtime( true );
		global $wpdb;
		$wpdb->get_var( 'SELECT 1' );
		$db_time = ( microtime( true ) - $db_start ) * 1000;

		if ( $db_time > 100 ) {
			$score             -= 20;
			$checks['database'] = false;
		} else {
			$checks['database'] = true;
		}

		// Check memory usage
		$memory_limit   = ini_get( 'memory_limit' );
		$memory_usage   = memory_get_usage( true );
		$memory_percent = ( $memory_usage / wp_convert_hr_to_bytes( $memory_limit ) ) * 100;

		if ( $memory_percent > 80 ) {
			$score           -= 15;
			$checks['memory'] = false;
		} else {
			$checks['memory'] = true;
		}

		// Check for recent errors
		$recent_errors = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT COUNT(*) FROM {$wpdb->prefix}tts_logs
            WHERE status = 'error'
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
        ",
				24
			)
		);

		if ( $recent_errors > 10 ) {
			$score           -= 25;
			$checks['errors'] = false;
		} else {
			$checks['errors'] = true;
		}

		// Check API connections
		$social_settings      = tsap_get_option( 'tts_social_apps', array() );
		$configured_platforms = 0;
		foreach ( array( 'facebook', 'instagram', 'youtube', 'tiktok' ) as $platform ) {
			if ( ! empty( $social_settings[ $platform ] ) ) {
				++$configured_platforms;
			}
		}

		if ( $configured_platforms === 0 ) {
			$score                       -= 20;
			$checks['social_connections'] = false;
		} else {
			$checks['social_connections'] = true;
		}

		// Check scheduled tasks
		if ( ! wp_next_scheduled( 'tts_refresh_tokens' ) ) {
			$score                    -= 10;
			$checks['scheduled_tasks'] = false;
		} else {
			$checks['scheduled_tasks'] = true;
		}

		return array(
			'score'           => max( 0, $score ),
			'checks'          => $checks,
			'recommendations' => self::get_health_recommendations( $checks ),
		);
	}

	/**
	 * Get health recommendations based on checks.
	 *
	 * @param array $checks System checks results.
	 * @return array Recommendations.
	 */
	private static function get_health_recommendations( $checks ) {
		$recommendations = array();

		if ( ! $checks['database'] ) {
			$recommendations[] = 'Database performance is slow. Consider optimizing your database or upgrading your hosting.';
		}

		if ( ! $checks['memory'] ) {
			$recommendations[] = 'Memory usage is high. Consider increasing the PHP memory limit or optimizing your site.';
		}

		if ( ! $checks['errors'] ) {
			$recommendations[] = 'Recent errors detected. Check the error logs and resolve any issues.';
		}

		if ( ! $checks['social_connections'] ) {
			$recommendations[] = 'No social media platforms configured. Set up social connections to start publishing.';
		}

		if ( ! $checks['scheduled_tasks'] ) {
			$recommendations[] = 'Scheduled tasks are not properly configured. Check your WordPress cron settings.';
		}

		return $recommendations;
	}

	/**
	 * Calculate trend data for analytics.
	 *
	 * @return array Trend data.
	 */
	private static function calculate_trend_data() {
		global $wpdb;

		$cache_key = 'tts_trend_data';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Get daily posting trends for the last 30 days
		$daily_trends = $wpdb->get_results(
			"
            SELECT 
                DATE(post_date) as date,
                COUNT(*) as posts,
                COUNT(CASE WHEN post_status = 'publish' THEN 1 END) as published
            FROM {$wpdb->posts}
            WHERE post_type = 'tts_social_post'
            AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(post_date)
            ORDER BY date ASC
        ",
			ARRAY_A
		);

		// Calculate growth percentage
		$recent_week   = array_slice( $daily_trends, -7 );
		$previous_week = array_slice( $daily_trends, -14, 7 );

		$recent_total   = array_sum( array_column( $recent_week, 'posts' ) );
		$previous_total = array_sum( array_column( $previous_week, 'posts' ) );

		$growth_rate = $previous_total > 0 ?
			round( ( ( $recent_total - $previous_total ) / $previous_total ) * 100, 1 ) : 0;

		$trends = array(
			'daily_data'          => $daily_trends,
			'growth_rate'         => $growth_rate,
			'total_recent_week'   => $recent_total,
			'total_previous_week' => $previous_total,
			'trend_direction'     => $growth_rate > 0 ? 'up' : ( $growth_rate < 0 ? 'down' : 'stable' ),
		);

		set_transient( $cache_key, $trends, 30 * MINUTE_IN_SECONDS );
		return $trends;
	}

	/**
	 * Get performance metrics with enhanced monitoring.
	 *
	 * @return array Performance metrics.
	 */
	public static function get_performance_metrics() {
		$cache_key = 'tts_performance_metrics';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$start_time = microtime( true );

		// Test database response time with multiple queries
		global $wpdb;
		$db_tests = array();

		// Simple query test
		$db_start = microtime( true );
		$wpdb->get_var( 'SELECT 1' );
		$db_tests['simple'] = ( microtime( true ) - $db_start ) * 1000;

		// Complex query test
		$db_start = microtime( true );
		$wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'tts_social_post' LIMIT 5" );
		$db_tests['complex'] = ( microtime( true ) - $db_start ) * 1000;

		// Average database response time
		$db_time = array_sum( $db_tests ) / count( $db_tests );

		// Test WordPress load time simulation
		$wp_time = ( microtime( true ) - $start_time ) * 1000;

		// Get comprehensive memory usage
		$memory_usage = memory_get_usage( true );
		$memory_peak  = memory_get_peak_usage( true );
		$memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );

		// Cache statistics (enhanced)
		$cache_stats = self::get_cache_statistics();

		// Server information
		$server_info = self::get_server_information();

		// WordPress performance
		$wp_performance = self::get_wordpress_performance();

		$metrics = array(
			'database'     => array(
				'response_ms'      => round( $db_time, 2 ),
				'queries_per_test' => count( $db_tests ),
				'simple_query_ms'  => round( $db_tests['simple'], 2 ),
				'complex_query_ms' => round( $db_tests['complex'], 2 ),
				'status'           => $db_time < 50 ? 'excellent' : ( $db_time < 100 ? 'good' : 'needs_attention' ),
			),
			'memory'       => array(
				'usage_mb'      => round( $memory_usage / 1024 / 1024, 2 ),
				'peak_mb'       => round( $memory_peak / 1024 / 1024, 2 ),
				'limit_mb'      => round( $memory_limit / 1024 / 1024, 2 ),
				'usage_percent' => round( ( $memory_usage / $memory_limit ) * 100, 1 ),
				'status'        => ( $memory_usage / $memory_limit ) < 0.8 ? 'good' : 'warning',
			),
			'cache'        => $cache_stats,
			'server'       => $server_info,
			'wordpress'    => $wp_performance,
			'profiler'     => self::get_profiler_snapshot(),
			'load_time_ms' => round( $wp_time, 2 ),
			'last_updated' => current_time( 'mysql' ),
			'score'        => self::calculate_performance_score( $db_time, $memory_usage, $memory_limit ),
		);

		// Cache for 2 minutes
		set_transient( $cache_key, $metrics, 2 * MINUTE_IN_SECONDS );

		return $metrics;
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array Cache statistics.
	 */
	private static function get_cache_statistics() {
		// Try to get WordPress cache stats
		$cache_hits   = 0;
		$cache_misses = 0;
		$cache_ratio  = 85; // Default

		// If object cache is available
		if ( function_exists( 'wp_cache_get_stats' ) ) {
			$stats = wp_cache_get_stats();
			if ( is_array( $stats ) && ! empty( $stats ) ) {
				foreach ( $stats as $group => $group_stats ) {
					if ( isset( $group_stats['cache_hits'] ) ) {
						$cache_hits += $group_stats['cache_hits'];
					}
					if ( isset( $group_stats['cache_misses'] ) ) {
						$cache_misses += $group_stats['cache_misses'];
					}
				}

				if ( $cache_hits + $cache_misses > 0 ) {
					$cache_ratio = round( ( $cache_hits / ( $cache_hits + $cache_misses ) ) * 100, 1 );
				}
			}
		}

		// Check transient cache health
		$transient_count = self::count_tts_transients();

		return array(
			'hit_ratio'  => $cache_ratio,
			'hits'       => $cache_hits,
			'misses'     => $cache_misses,
			'transients' => $transient_count,
			'status'     => $cache_ratio > 80 ? 'excellent' : ( $cache_ratio > 60 ? 'good' : 'poor' ),
		);
	}

	/**
	 * Get server information.
	 *
	 * @return array Server information.
	 */
	private static function get_server_information() {
		return array(
			'php_version'         => PHP_VERSION,
			'mysql_version'       => self::get_mysql_version(),
			'max_execution_time'  => ini_get( 'max_execution_time' ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			'post_max_size'       => ini_get( 'post_max_size' ),
			'server_software'     => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
		);
	}

	/**
	 * Get WordPress performance metrics.
	 *
	 * @return array WordPress performance data.
	 */
	private static function get_wordpress_performance() {
		global $wpdb;

		// Count plugins and themes
		$active_plugins = count( get_option( 'active_plugins', array() ) );
		$theme          = wp_get_theme();

		// Database size
		$db_size = $wpdb->get_var(
			"
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) 
            FROM information_schema.tables 
            WHERE table_schema = '{$wpdb->dbname}'
        "
		);

		return array(
			'version'          => get_bloginfo( 'version' ),
			'active_plugins'   => $active_plugins,
			'theme'            => $theme->get( 'Name' ),
			'database_size_mb' => (float) $db_size,
			'posts_count'      => wp_count_posts( 'tts_social_post' )->publish,
			'multisite'        => is_multisite(),
		);
	}

	/**
	 * Calculate overall performance score.
	 *
	 * @param float $db_time Database response time.
	 * @param int   $memory_usage Current memory usage.
	 * @param int   $memory_limit Memory limit.
	 * @return int Performance score (0-100).
	 */
	private static function calculate_performance_score( $db_time, $memory_usage, $memory_limit ) {
		$score = 100;

		// Database performance (30% weight)
		if ( $db_time > 100 ) {
			$score -= 30;
		} elseif ( $db_time > 50 ) {
			$score -= 15;
		}

		// Memory usage (25% weight)
		$memory_percent = ( $memory_usage / $memory_limit ) * 100;
		if ( $memory_percent > 90 ) {
			$score -= 25;
		} elseif ( $memory_percent > 80 ) {
			$score -= 15;
		} elseif ( $memory_percent > 70 ) {
			$score -= 10;
		}

		// PHP version (15% weight)
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			$score -= 15;
		} elseif ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			$score -= 10;
		}

		// WordPress version (10% weight)
		if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
			$score -= 10;
		}

		// Cache performance (20% weight)
		$cache_stats = self::get_cache_statistics();
		if ( $cache_stats['hit_ratio'] < 60 ) {
			$score -= 20;
		} elseif ( $cache_stats['hit_ratio'] < 80 ) {
			$score -= 10;
		}

		return max( 0, $score );
	}

	/**
	 * Get MySQL version.
	 *
	 * @return string MySQL version.
	 */
	private static function get_mysql_version() {
		global $wpdb;
		return $wpdb->get_var( 'SELECT VERSION()' );
	}

	/**
	 * Count TTS transients.
	 *
	 * @return int Number of TTS transients.
	 */
	private static function count_tts_transients() {
		global $wpdb;

		return (int) $wpdb->get_var(
			"
            SELECT COUNT(*)
            FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_tts_%'
        "
		);
	}

	/**
	 * Bootstrap profiling hooks for scheduler and integration hub modules.
	 */
	public static function bootstrap_profiling_layer() {
		static $bootstrapped = false;

		if ( $bootstrapped ) {
			return;
		}

		$bootstrapped = true;

		add_action( 'tts_scheduler_job_started', array( __CLASS__, 'record_scheduler_job_started' ), 10, 1 );
		add_action( 'tts_scheduler_job_completed', array( __CLASS__, 'record_scheduler_job_completed' ), 10, 2 );
		add_action( 'tts_scheduler_job_failed', array( __CLASS__, 'record_scheduler_job_failed' ), 10, 2 );

		add_action( 'tts_integration_hub_operation_started', array( __CLASS__, 'record_integration_operation_started' ), 10, 1 );
		add_action( 'tts_integration_hub_operation_completed', array( __CLASS__, 'record_integration_operation_completed' ), 10, 2 );
		add_action( 'tts_integration_hub_operation_failed', array( __CLASS__, 'record_integration_operation_failed' ), 10, 2 );
	}

	/**
	 * Handle scheduler profiling start event.
	 *
	 * @param array $context Profiling context.
	 */
	public static function record_scheduler_job_started( $context ) {
		if ( ! is_array( $context ) ) {
			return;
		}

		$identifier = isset( $context['job_id'] ) ? (string) $context['job_id'] : self::build_profile_identifier( 'scheduler', $context );
		self::start_profile( 'scheduler', $identifier, $context );
	}

	/**
	 * Handle scheduler profiling completion event.
	 *
	 * @param array $context Profiling context.
	 * @param mixed $result  Job result payload.
	 */
	public static function record_scheduler_job_completed( $context, $result ) {
		if ( ! is_array( $context ) ) {
			return;
		}

		self::finalize_profile( 'scheduler', $context, 'success' );
	}

	/**
	 * Handle scheduler failure profiling event.
	 *
	 * @param array $context Profiling context.
	 * @param mixed $error   Error payload.
	 */
	public static function record_scheduler_job_failed( $context, $error ) {
		if ( ! is_array( $context ) ) {
			return;
		}

		self::finalize_profile( 'scheduler', $context, 'error', array( 'error' => $error ) );
	}

	/**
	 * Handle integration hub profiling start event.
	 *
	 * @param array $context Profiling context.
	 */
	public static function record_integration_operation_started( $context ) {
		if ( ! is_array( $context ) ) {
			return;
		}

		$identifier = isset( $context['operation_id'] ) ? (string) $context['operation_id'] : self::build_profile_identifier( 'integration_hub', $context );
		self::start_profile( 'integration_hub', $identifier, $context );
	}

	/**
	 * Handle integration hub success event.
	 *
	 * @param array $context Profiling context.
	 * @param mixed $result  Operation result payload.
	 */
	public static function record_integration_operation_completed( $context, $result ) {
		if ( ! is_array( $context ) ) {
			return;
		}

		if ( is_array( $result ) ) {
			if ( isset( $result['synced_records'] ) && ! isset( $context['synced_records'] ) ) {
				$context['synced_records'] = (int) $result['synced_records'];
			}

			if ( isset( $result['failed_records'] ) && ! isset( $context['failed_records'] ) ) {
				$context['failed_records'] = (int) $result['failed_records'];
			}

			if ( isset( $result['last_sync'] ) && ! isset( $context['last_sync'] ) ) {
				$context['last_sync'] = $result['last_sync'];
			}
		}

		self::finalize_profile( 'integration_hub', $context, 'success' );
	}

	/**
	 * Handle integration hub failure event.
	 *
	 * @param array $context Profiling context.
	 * @param mixed $error   Error payload.
	 */
	public static function record_integration_operation_failed( $context, $error ) {
		if ( ! is_array( $context ) ) {
			return;
		}

		self::finalize_profile( 'integration_hub', $context, 'error', array( 'error' => $error ) );
	}

	/**
	 * Start profiling for a given component.
	 *
	 * @param string $component Component identifier.
	 * @param string $identifier Session identifier.
	 * @param array  $context    Context payload.
	 */
	private static function start_profile( $component, $identifier, array $context ) {
		if ( '' === $identifier ) {
			$identifier = self::build_profile_identifier( $component, $context );
		}

		if ( ! isset( self::$active_profiles[ $component ] ) ) {
			self::$active_profiles[ $component ] = array();
		}

		$start_time = isset( $context['start_timestamp'] ) ? (float) $context['start_timestamp'] : microtime( true );
		self::$active_profiles[ $component ][ $identifier ] = array(
			'started_at' => $start_time,
			'context'    => $context,
		);
	}

	/**
	 * Finalize profiling for a component and store results.
	 *
	 * @param string $component Component identifier.
	 * @param array  $context   Context payload.
	 * @param string $status    Operation status (success|error).
	 * @param array  $extra     Additional payload data.
	 */
	private static function finalize_profile( $component, array $context, $status, array $extra = array() ) {
		$identifier = '';

		if ( 'scheduler' === $component ) {
			$identifier = isset( $context['job_id'] ) ? (string) $context['job_id'] : '';
		} elseif ( 'integration_hub' === $component ) {
			$identifier = isset( $context['operation_id'] ) ? (string) $context['operation_id'] : '';
		}

		if ( '' === $identifier ) {
			$identifier = self::build_profile_identifier( $component, $context );
		}

		$start_data = null;
		if ( isset( self::$active_profiles[ $component ][ $identifier ] ) ) {
			$start_data = self::$active_profiles[ $component ][ $identifier ];
			unset( self::$active_profiles[ $component ][ $identifier ] );

			if ( empty( self::$active_profiles[ $component ] ) ) {
				unset( self::$active_profiles[ $component ] );
			}
		}

		$start_time = isset( $context['start_timestamp'] ) ? (float) $context['start_timestamp'] : microtime( true );

		if ( $start_data && isset( $start_data['started_at'] ) ) {
			$start_time = (float) $start_data['started_at'];
		}

		$end_time    = isset( $context['end_timestamp'] ) ? (float) $context['end_timestamp'] : microtime( true );
		$duration_ms = isset( $context['duration_ms'] )
			? (float) $context['duration_ms']
			: ( $end_time - $start_time ) * 1000;

		$queue_latency_ms = null;

		if ( isset( $context['queue_latency_ms'] ) ) {
			$queue_latency_ms = (float) $context['queue_latency_ms'];
		} else {
			$queued_at = null;

			if ( isset( $context['queued_at'] ) ) {
				$queued_at = (float) $context['queued_at'];
			} elseif ( $start_data && isset( $start_data['context']['queued_at'] ) ) {
				$queued_at = (float) $start_data['context']['queued_at'];
			}

			if ( null !== $queued_at ) {
				$queue_latency_ms = max( 0, ( $start_time - $queued_at ) * 1000 );
			}
		}

		$start_context = $start_data ? (array) $start_data['context'] : array();

		$sample = self::normalize_profile_sample(
			$component,
			$context,
			$status,
			$duration_ms,
			$queue_latency_ms,
			$start_context,
			$extra
		);

		if ( empty( $sample ) ) {
			return;
		}

		self::persist_profiling_sample( $component, $sample );
		self::export_sample_to_metrics( $component, $sample );
	}

	/**
	 * Build a normalized profiling sample for a component.
	 *
	 * @param string $component        Component identifier.
	 * @param array  $context          Context payload.
	 * @param string $status           Operation status.
	 * @param float  $duration_ms      Operation duration in milliseconds.
	 * @param float  $queue_latency_ms Queue latency in milliseconds.
	 * @param array  $start_context    Original start context.
	 * @param array  $extra            Extra payload data.
	 *
	 * @return array Normalized sample.
	 */
	private static function normalize_profile_sample( $component, array $context, $status, $duration_ms, $queue_latency_ms, array $start_context = array(), array $extra = array() ) {
		$timestamp = current_time( 'mysql' );

		if ( 'scheduler' === $component ) {
			$job_id       = isset( $context['job_id'] ) ? (string) $context['job_id'] : ( $start_context['job_id'] ?? self::build_profile_identifier( $component, $context ) );
			$post_id      = isset( $context['post_id'] ) ? absint( $context['post_id'] ) : absint( $start_context['post_id'] ?? 0 );
			$client_id    = isset( $context['client_id'] ) ? absint( $context['client_id'] ) : absint( $start_context['client_id'] ?? 0 );
			$channel      = isset( $context['channel'] ) ? sanitize_key( $context['channel'] ) : sanitize_key( $start_context['channel'] ?? '' );
			$attempt      = isset( $context['attempt'] ) ? absint( $context['attempt'] ) : absint( $start_context['attempt'] ?? 0 );
			$max_attempts = isset( $context['max_attempts'] ) ? absint( $context['max_attempts'] ) : absint( $start_context['max_attempts'] ?? 0 );
			$queued_by    = isset( $context['queued_by'] ) ? sanitize_key( $context['queued_by'] ) : sanitize_key( $start_context['queued_by'] ?? '' );
			$severity     = isset( $context['severity'] ) ? sanitize_key( $context['severity'] ) : sanitize_key( $start_context['severity'] ?? '' );

			$sample = array(
				'id'               => $job_id,
				'status'           => $status,
				'duration_ms'      => round( max( 0, (float) $duration_ms ), 2 ),
				'queue_latency_ms' => isset( $queue_latency_ms ) ? round( max( 0, (float) $queue_latency_ms ), 2 ) : null,
				'attempt'          => $attempt,
				'max_attempts'     => $max_attempts,
				'timestamp'        => $timestamp,
				'post_id'          => $post_id,
				'client_id'        => $client_id,
				'channel'          => $channel,
				'queued_by'        => $queued_by,
			);

			if ( $severity ) {
				$sample['severity'] = $severity;
			}

			if ( 'error' === $status && isset( $extra['error'] ) ) {
				$sample = array_merge( $sample, self::extract_error_details( $extra['error'] ) );
			}

			return $sample;
		}

		if ( 'integration_hub' === $component ) {
			$operation_id   = isset( $context['operation_id'] ) ? (string) $context['operation_id'] : self::build_profile_identifier( $component, $context );
			$integration_id = isset( $context['integration_id'] ) ? absint( $context['integration_id'] ) : absint( $start_context['integration_id'] ?? 0 );
			$integration    = isset( $context['integration'] ) ? sanitize_key( $context['integration'] ) : sanitize_key( $start_context['integration'] ?? '' );
			$operation      = isset( $context['data_type'] ) ? sanitize_key( $context['data_type'] ) : sanitize_key( $start_context['data_type'] ?? '' );
			$trigger        = isset( $context['trigger'] ) ? sanitize_key( $context['trigger'] ) : sanitize_key( $start_context['trigger'] ?? '' );
			$synced_records = isset( $context['synced_records'] ) ? absint( $context['synced_records'] ) : 0;
			$failed_records = isset( $context['failed_records'] ) ? absint( $context['failed_records'] ) : 0;
			$last_sync      = isset( $context['last_sync'] ) ? sanitize_text_field( $context['last_sync'] ) : '';

			$sample = array(
				'id'             => $operation_id,
				'status'         => $status,
				'duration_ms'    => round( max( 0, (float) $duration_ms ), 2 ),
				'timestamp'      => $timestamp,
				'integration_id' => $integration_id,
				'integration'    => $integration,
				'operation'      => $operation,
				'trigger'        => $trigger,
				'synced_records' => $synced_records,
				'failed_records' => $failed_records,
			);

			if ( $last_sync ) {
				$sample['last_sync'] = $last_sync;
			}

			if ( 'error' === $status && isset( $extra['error'] ) ) {
				$sample = array_merge( $sample, self::extract_error_details( $extra['error'] ) );
			}

			return $sample;
		}

		return array();
	}

	/**
	 * Extract error details from various error payload types.
	 *
	 * @param mixed $error Error payload.
	 *
	 * @return array
	 */
	private static function extract_error_details( $error ) {
		$code    = 'unknown';
		$message = '';

		if ( is_wp_error( $error ) ) {
			$code    = $error->get_error_code();
			$message = $error->get_error_message();
		} elseif ( $error instanceof \Throwable ) {
			$code    = $error->getCode() ? (string) $error->getCode() : 'exception';
			$message = $error->getMessage();
		} elseif ( is_array( $error ) ) {
			$code    = isset( $error['code'] ) ? (string) $error['code'] : $code;
			$message = isset( $error['message'] ) ? (string) $error['message'] : $message;
		} elseif ( is_string( $error ) ) {
			$message = $error;
		}

		$message = substr( wp_strip_all_tags( (string) $message ), 0, 200 );

		return array(
			'error_code'    => sanitize_key( $code ),
			'error_message' => sanitize_text_field( $message ),
		);
	}

	/**
	 * Persist profiling sample and update aggregates.
	 *
	 * @param string $component Component identifier.
	 * @param array  $sample    Normalized sample data.
	 */
	private static function persist_profiling_sample( $component, array $sample ) {
		$stats = self::get_profiler_storage();

		if ( ! isset( $stats[ $component ] ) || ! is_array( $stats[ $component ] ) ) {
			$stats[ $component ] = self::get_default_profiler_bucket();
		}

		$bucket = $stats[ $component ];

		$bucket['count']             = isset( $bucket['count'] ) ? (int) $bucket['count'] + 1 : 1;
		$bucket['total_duration_ms'] = isset( $bucket['total_duration_ms'] ) ? (float) $bucket['total_duration_ms'] + (float) $sample['duration_ms'] : (float) $sample['duration_ms'];

		if ( isset( $sample['queue_latency_ms'] ) && null !== $sample['queue_latency_ms'] ) {
			$bucket['queue_samples']  = isset( $bucket['queue_samples'] ) ? (int) $bucket['queue_samples'] + 1 : 1;
			$bucket['total_queue_ms'] = isset( $bucket['total_queue_ms'] ) ? (float) $bucket['total_queue_ms'] + (float) $sample['queue_latency_ms'] : (float) $sample['queue_latency_ms'];
		}

		if ( 'error' === $sample['status'] ) {
			$bucket['errors'] = isset( $bucket['errors'] ) ? (int) $bucket['errors'] + 1 : 1;
		}

		$bucket['max_duration_ms'] = isset( $bucket['max_duration_ms'] ) ? max( (float) $bucket['max_duration_ms'], (float) $sample['duration_ms'] ) : (float) $sample['duration_ms'];
		$bucket['min_duration_ms'] = isset( $bucket['min_duration_ms'] ) && $bucket['min_duration_ms'] > 0
			? min( (float) $bucket['min_duration_ms'], (float) $sample['duration_ms'] )
			: (float) $sample['duration_ms'];

		$recent   = isset( $bucket['recent'] ) && is_array( $bucket['recent'] ) ? $bucket['recent'] : array();
		$recent[] = $sample;
		if ( count( $recent ) > 10 ) {
			$recent = array_slice( $recent, -10 );
		}

		$bucket['recent'] = $recent;
		$bucket['last']   = $sample;

		$stats[ $component ] = $bucket;

		update_option( self::PROFILER_OPTION_KEY, $stats, false );
		wp_cache_set( 'profiler_stats', $stats, self::CACHE_GROUP );
	}

	/**
	 * Retrieve profiling storage from cache or persistent storage.
	 *
	 * @return array
	 */
	private static function get_profiler_storage() {
		$stats = wp_cache_get( 'profiler_stats', self::CACHE_GROUP );

		if ( false === $stats ) {
			$stats = get_option( self::PROFILER_OPTION_KEY, array() );
			if ( ! is_array( $stats ) ) {
				$stats = array();
			}

			$stats = wp_parse_args(
				$stats,
				array(
					'scheduler'       => self::get_default_profiler_bucket(),
					'integration_hub' => self::get_default_profiler_bucket(),
				)
			);

			wp_cache_set( 'profiler_stats', $stats, self::CACHE_GROUP );
		} else {
			if ( ! isset( $stats['scheduler'] ) || ! is_array( $stats['scheduler'] ) ) {
				$stats['scheduler'] = self::get_default_profiler_bucket();
			}

			if ( ! isset( $stats['integration_hub'] ) || ! is_array( $stats['integration_hub'] ) ) {
				$stats['integration_hub'] = self::get_default_profiler_bucket();
			}
		}

		return $stats;
	}

	/**
	 * Default profiler bucket structure.
	 *
	 * @return array
	 */
	private static function get_default_profiler_bucket() {
		return array(
			'count'             => 0,
			'errors'            => 0,
			'total_duration_ms' => 0,
			'total_queue_ms'    => 0,
			'queue_samples'     => 0,
			'max_duration_ms'   => 0,
			'min_duration_ms'   => 0,
			'last'              => array(),
			'recent'            => array(),
		);
	}

	/**
	 * Return summarized profiler statistics for dashboards.
	 *
	 * @return array
	 */
	public static function get_profiler_snapshot() {
		$stats = self::get_profiler_storage();

		return array(
			'scheduler'       => self::prepare_profiler_bucket( $stats['scheduler'] ),
			'integration_hub' => self::prepare_profiler_bucket( $stats['integration_hub'] ),
		);
	}

	/**
	 * Build user-facing snapshot for a profiler bucket.
	 *
	 * @param array $bucket Raw profiler bucket.
	 *
	 * @return array
	 */
	private static function prepare_profiler_bucket( array $bucket ) {
		$total_runs = isset( $bucket['count'] ) ? (int) $bucket['count'] : 0;
		$errors     = isset( $bucket['errors'] ) ? (int) $bucket['errors'] : 0;
		$avg        = $total_runs > 0 ? round( (float) $bucket['total_duration_ms'] / $total_runs, 2 ) : 0;
		$avg_queue  = ( ! empty( $bucket['queue_samples'] ) ) ? round( (float) $bucket['total_queue_ms'] / (int) $bucket['queue_samples'], 2 ) : null;

		return array(
			'total_runs'           => $total_runs,
			'error_rate'           => $total_runs > 0 ? round( ( $errors / $total_runs ) * 100, 2 ) : 0,
			'avg_duration_ms'      => $avg,
			'avg_queue_latency_ms' => $avg_queue,
			'max_duration_ms'      => isset( $bucket['max_duration_ms'] ) ? round( (float) $bucket['max_duration_ms'], 2 ) : 0,
			'min_duration_ms'      => isset( $bucket['min_duration_ms'] ) && $bucket['min_duration_ms'] > 0 ? round( (float) $bucket['min_duration_ms'], 2 ) : 0,
			'last_run'             => isset( $bucket['last'] ) ? $bucket['last'] : array(),
			'recent'               => isset( $bucket['recent'] ) ? $bucket['recent'] : array(),
		);
	}

	/**
	 * Build a deterministic identifier for profiling sessions.
	 *
	 * @param string $component Component identifier.
	 * @param array  $context   Context payload.
	 *
	 * @return string
	 */
	private static function build_profile_identifier( $component, array $context ) {
		$seed = wp_json_encode( array( $component, $context, microtime( true ) ) );
		return substr( md5( (string) $seed ), 0, 12 );
	}

	/**
	 * Export profiling sample to the external metrics system.
	 *
	 * @param string $component Component identifier.
	 * @param array  $sample    Normalized sample data.
	 */
	private static function export_sample_to_metrics( $component, array $sample ) {
		if ( 'scheduler' === $component ) {
			self::emit_metric(
				'scheduler_job_duration_ms',
				$sample['duration_ms'],
				array(
					'channel' => $sample['channel'] ?? 'unknown',
					'status'  => $sample['status'],
				)
			);

			if ( isset( $sample['queue_latency_ms'] ) && null !== $sample['queue_latency_ms'] ) {
				self::emit_metric(
					'scheduler_queue_latency_ms',
					$sample['queue_latency_ms'],
					array(
						'channel' => $sample['channel'] ?? 'unknown',
					)
				);
			}

			self::emit_metric(
				'scheduler_job_attempts',
				isset( $sample['attempt'] ) ? $sample['attempt'] : 0,
				array(
					'channel' => $sample['channel'] ?? 'unknown',
					'status'  => $sample['status'],
				)
			);

			if ( 'error' === $sample['status'] ) {
				self::emit_metric(
					'scheduler_job_errors_total',
					1,
					array(
						'channel'  => $sample['channel'] ?? 'unknown',
						'severity' => $sample['severity'] ?? 'unknown',
					),
					'counter'
				);
			}

			return;
		}

		if ( 'integration_hub' === $component ) {
			self::emit_metric(
				'integration_operation_duration_ms',
				$sample['duration_ms'],
				array(
					'integration' => $sample['integration'] ?? 'unknown',
					'operation'   => $sample['operation'] ?? 'generic',
					'status'      => $sample['status'],
				)
			);

			self::emit_metric(
				'integration_records_synced',
				isset( $sample['synced_records'] ) ? $sample['synced_records'] : 0,
				array(
					'integration' => $sample['integration'] ?? 'unknown',
					'operation'   => $sample['operation'] ?? 'generic',
				),
				'counter'
			);

			if ( ! empty( $sample['failed_records'] ) ) {
				self::emit_metric(
					'integration_records_failed',
					$sample['failed_records'],
					array(
						'integration' => $sample['integration'] ?? 'unknown',
						'operation'   => $sample['operation'] ?? 'generic',
					),
					'counter'
				);
			}

			if ( 'error' === $sample['status'] ) {
				self::emit_metric(
					'integration_operation_errors_total',
					1,
					array(
						'integration' => $sample['integration'] ?? 'unknown',
						'operation'   => $sample['operation'] ?? 'generic',
						'trigger'     => $sample['trigger'] ?? 'unspecified',
					),
					'counter'
				);
			}
		}
	}

	/**
	 * Emit a metric payload via the metrics export hook.
	 *
	 * @param string $metric Metric name.
	 * @param float  $value  Metric value.
	 * @param array  $tags   Metric tags.
	 * @param string $type   Metric type (gauge|counter).
	 */
	private static function emit_metric( $metric, $value, array $tags = array(), $type = 'gauge' ) {
		$clean_tags = array();

		foreach ( $tags as $key => $tag_value ) {
			$key = sanitize_key( $key );
			if ( '' === $key || is_array( $tag_value ) || is_object( $tag_value ) ) {
				continue;
			}

			$clean_tags[ $key ] = is_numeric( $tag_value )
				? (string) $tag_value
				: sanitize_text_field( (string) $tag_value );
		}

		/**
		 * Allow external systems to capture metric samples emitted by the plugin.
		 *
		 * @param array $payload Metric payload.
		 */
		do_action(
			'tts_metrics_emit',
			array(
				'metric'      => sanitize_key( $metric ),
				'value'       => (float) $value,
				'type'        => sanitize_key( $type ),
				'tags'        => $clean_tags,
				'recorded_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Advanced database cleanup with safety checks.
	 */
	public static function optimize_database_advanced() {
		global $wpdb;

		$cleanup_log = array();

		try {
			// 1. Optimize core tables
			$tables_to_optimize = array(
				$wpdb->prefix . 'tts_logs',
				$wpdb->posts,
				$wpdb->postmeta,
				$wpdb->options,
			);

			foreach ( $tables_to_optimize as $table ) {
				$result        = $wpdb->query( "OPTIMIZE TABLE {$table}" );
				$cleanup_log[] = "Optimized table {$table}: " . ( $result ? 'success' : 'failed' );
			}

			// 2. Clean up old transients (older than 1 week)
			$expired_transients = $wpdb->query(
				"
                DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_timeout_tts_%' 
                AND option_value < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))
            "
			);
			$cleanup_log[]      = "Removed {$expired_transients} expired transients";

			// 3. Clean up orphaned transients
			$orphaned_transients = $wpdb->query(
				"
                DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_tts_%' 
                AND option_name NOT IN (
                    SELECT REPLACE(option_name, '_timeout_', '_') 
                    FROM {$wpdb->options} 
                    WHERE option_name LIKE '_transient_timeout_tts_%'
                )
            "
			);
			$cleanup_log[]       = "Removed {$orphaned_transients} orphaned transients";

			// 4. Clean up old log entries (keep last 1000)
			$logs_table    = $wpdb->prefix . 'tts_logs';
			$old_logs      = $wpdb->query(
				$wpdb->prepare(
					"
                DELETE FROM {$logs_table} 
                WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM {$logs_table} 
                        ORDER BY created_at DESC 
                        LIMIT %d
                    ) AS keep_logs
                )
            ",
					1000
				)
			);
			$cleanup_log[] = "Removed {$old_logs} old log entries";

			// 5. Update database statistics
			$wpdb->query( "ANALYZE TABLE {$wpdb->posts}, {$wpdb->postmeta}, {$logs_table}" );
			$cleanup_log[] = 'Updated database statistics';

			// Clear performance cache after optimization
			self::clear_all_performance_cache();

			tts_log_event( 0, 'performance', 'success', 'Database optimization completed: ' . implode( '; ', $cleanup_log ), '' );

			return array(
				'success'   => true,
				'log'       => $cleanup_log,
				'timestamp' => current_time( 'mysql' ),
			);

		} catch ( Exception $e ) {
			$error_msg = 'Database optimization failed: ' . $e->getMessage();
			tts_log_event( 0, 'performance', 'error', $error_msg, '' );

			return array(
				'success'   => false,
				'error'     => $error_msg,
				'log'       => $cleanup_log,
				'timestamp' => current_time( 'mysql' ),
			);
		}
	}

	/**
	 * Clear all performance-related cache.
	 */
	public static function clear_all_performance_cache() {
		$cache_keys = array(
			'tts_dashboard_stats',
			'tts_performance_metrics',
			'tts_active_channels_stats',
			'tts_success_rate_stats',
			'tts_trend_data',
		);

		foreach ( $cache_keys as $key ) {
			delete_transient( $key );
		}
	}

	/**
	 * Clear dashboard statistics cache.
	 */
	public static function clear_dashboard_cache() {
		delete_transient( 'tts_dashboard_stats' );
	}

	/**
	 * Optimize database tables.
	 */
	public static function optimize_database() {
		global $wpdb;

		$tables_to_optimize = array(
			$wpdb->posts,
			$wpdb->postmeta,
			$wpdb->options,
			$wpdb->prefix . 'tts_logs',
		);

		$optimized = 0;
		foreach ( $tables_to_optimize as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				$result = $wpdb->query( "OPTIMIZE TABLE `$table`" );
				if ( $result !== false ) {
					++$optimized;
				}
			}
		}

		tts_log_event(
			0,
			'performance',
			'info',
			"Database optimization completed. Optimized $optimized tables.",
			''
		);

		return $optimized;
	}

	/**
	 * Get cached Trello boards for a client.
	 *
	 * @param string $key   Trello API key.
	 * @param string $token Trello token.
	 * @return array Cached boards or false if not cached.
	 */
	public static function get_cached_trello_boards( $key, $token ) {
		$cache_key = 'tts_trello_boards_' . md5( $key . $token );
		return get_transient( $cache_key );
	}

	/**
	 * Cache Trello boards for a client.
	 *
	 * @param string $key    Trello API key.
	 * @param string $token  Trello token.
	 * @param array  $boards Array of boards.
	 */
	public static function cache_trello_boards( $key, $token, $boards ) {
		$cache_key = 'tts_trello_boards_' . md5( $key . $token );
		set_transient( $cache_key, $boards, HOUR_IN_SECONDS );
	}

	/**
	 * Schedule database cleanup.
	 */
	public static function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'tts_database_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'tts_database_cleanup' );
		}
	}

	/**
	 * Unschedule database cleanup.
	 */
	public static function unschedule_cleanup() {
		wp_clear_scheduled_hook( 'tts_database_cleanup' );
	}

	/**
	 * Get plugin memory usage.
	 *
	 * @return array Memory usage statistics.
	 */
	public static function get_memory_usage() {
		return array(
			'current' => memory_get_usage( true ),
			'peak'    => memory_get_peak_usage( true ),
			'limit'   => ini_get( 'memory_limit' ),
		);
	}

	/**
	 * Enable object caching for the plugin.
	 */
	public static function enable_object_cache() {
		if ( function_exists( 'wp_cache_add_global_groups' ) ) {
			wp_cache_add_global_groups( array( self::CACHE_GROUP ) );
		}
	}

	/**
	 * Invalidate all performance caches.
	 */
	public static function invalidate_all_caches() {
		$cache_keys = array(
			'tts_dashboard_stats',
			'tts_performance_metrics',
			'tts_active_channels',
			'tts_success_rate',
			'tts_system_health',
			'tts_trend_data',
		);

		foreach ( $cache_keys as $key ) {
			delete_transient( $key );
		}

		// Clear object cache if available
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		}

		tts_log_event( 0, 'performance', 'info', 'All performance caches invalidated', '' );
	}

	/**
	 * Clear Trello-related caches.
	 */
	public static function clear_trello_cache() {
		global $wpdb;

		// Get all transients that start with tts_trello_boards_
		$transients = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_tts_trello_boards_%'"
		);

		foreach ( $transients as $transient ) {
			$key = str_replace( '_transient_', '', $transient );
			delete_transient( $key );
		}
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array Cache statistics.
	 */
	public static function get_cache_stats() {
		global $wpdb;

		$cache_stats = array(
			'total_transients' => 0,
			'tts_transients'   => 0,
			'cache_size'       => 0,
			'cache_hit_ratio'  => 0,
		);

		// Count total transients
		$cache_stats['total_transients'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_%'"
		);

		// Count TTS-specific transients
		$cache_stats['tts_transients'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_tts_%'"
		);

		// Calculate approximate cache size
		$tts_cache_data = $wpdb->get_results(
			"SELECT option_value FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_tts_%'"
		);

		foreach ( $tts_cache_data as $cache_item ) {
			$cache_stats['cache_size'] += strlen( $cache_item->option_value );
		}

		// Format cache size
		$cache_stats['cache_size_formatted'] = size_format( $cache_stats['cache_size'] );

		return $cache_stats;
	}

	/**
	 * Clean up expired transients.
	 */
	public static function cleanup_expired_transients() {
		global $wpdb;

		// Delete expired transients
		$expired_transients = $wpdb->query(
			"DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
             WHERE a.option_name LIKE '_transient_%'
             AND a.option_name NOT LIKE '_transient_timeout_%'
             AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
             AND b.option_value < UNIX_TIMESTAMP()"
		);

		// Clean up orphaned timeout options
		$orphaned_timeouts = $wpdb->query(
			"DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_timeout_%'
             AND option_name NOT IN (
                 SELECT CONCAT('_transient_timeout_', SUBSTRING(option_name, 12))
                 FROM (SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_%') AS temp
             )"
		);

		$total_cleaned = $expired_transients + $orphaned_timeouts;

		tts_log_event(
			0,
			'performance',
			'info',
			"Transient cleanup completed. Cleaned $total_cleaned expired/orphaned transients.",
			''
		);

		return $total_cleaned;
	}
}

// Initialize performance optimizations
add_action( 'plugins_loaded', array( 'TTS_Performance', 'enable_object_cache' ) );
add_action( 'plugins_loaded', array( 'TTS_Performance', 'bootstrap_profiling_layer' ) );
add_action( 'plugins_loaded', array( 'TTS_Performance', 'schedule_cleanup' ) );
add_action( 'tts_database_cleanup', array( 'TTS_Performance', 'optimize_database' ) );
add_action( 'tts_database_cleanup', array( 'TTS_Performance', 'cleanup_expired_transients' ) );

// Clear cache when posts are updated
add_action( 'save_post_tts_social_post', array( 'TTS_Performance', 'clear_dashboard_cache' ) );
add_action( 'delete_post', array( 'TTS_Performance', 'clear_dashboard_cache' ) );
add_action( 'wp_trash_post', array( 'TTS_Performance', 'clear_dashboard_cache' ) );

// Clear cache when client settings are updated
add_action( 'save_post_tts_client', array( 'TTS_Performance', 'clear_trello_cache' ) );

// Add weekly transient cleanup
if ( ! wp_next_scheduled( 'tts_weekly_cleanup' ) ) {
	wp_schedule_event( time(), 'weekly', 'tts_weekly_cleanup' );
}
add_action( 'tts_weekly_cleanup', array( 'TTS_Performance', 'cleanup_expired_transients' ) );
add_action( 'delete_post', array( 'TTS_Performance', 'clear_dashboard_cache' ) );
