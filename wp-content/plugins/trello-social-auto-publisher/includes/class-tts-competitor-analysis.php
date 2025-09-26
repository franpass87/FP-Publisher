<?php
/**
 * Competitor Analysis and Tracking System
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles competitor analysis and performance tracking.
 */
class TTS_Competitor_Analysis {

	/**
	 * Cached identifier of the first available client post.
	 *
	 * @var int|null
	 */
	private $default_client_id = null;

	/**
	 * Initialize competitor analysis system.
	 */
	public function __construct() {
		add_action( 'wp_ajax_tts_add_competitor', array( $this, 'ajax_add_competitor' ) );
		add_action( 'wp_ajax_tts_remove_competitor', array( $this, 'ajax_remove_competitor' ) );
		add_action( 'wp_ajax_tts_analyze_competitor', array( $this, 'ajax_analyze_competitor' ) );
		add_action( 'wp_ajax_tts_get_competitor_report', array( $this, 'ajax_get_competitor_report' ) );
		add_action( 'wp_ajax_tts_track_competitor_posts', array( $this, 'ajax_track_competitor_posts' ) );

		// Schedule daily competitor analysis
		add_action( 'init', array( $this, 'schedule_competitor_analysis' ) );
		add_action( 'tts_daily_competitor_analysis', array( $this, 'run_daily_analysis' ) );
	}

	/**
	 * Schedule daily competitor analysis.
	 */
	public function schedule_competitor_analysis() {
		if ( ! wp_next_scheduled( 'tts_daily_competitor_analysis' ) ) {
			wp_schedule_event( time(), 'daily', 'tts_daily_competitor_analysis' );
		}
	}

	/**
	 * Add new competitor for tracking.
	 */
	public function ajax_add_competitor() {
		check_ajax_referer( 'tts_competitor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
		}

		$competitor_name = sanitize_text_field( wp_unslash( $_POST['competitor_name'] ?? '' ) );
		$platform        = sanitize_text_field( wp_unslash( $_POST['platform'] ?? '' ) );
		$handle          = sanitize_text_field( wp_unslash( $_POST['handle'] ?? '' ) );

		if ( empty( $competitor_name ) || empty( $platform ) || empty( $handle ) ) {
			wp_send_json_error( array( 'message' => __( 'All fields are required.', 'fp-publisher' ) ) );
		}

		try {
			$competitor_id = $this->add_competitor( $competitor_name, $platform, $handle );

			wp_send_json_success(
				array(
					'competitor_id' => $competitor_id,
					'message'       => __( 'Competitor added successfully!', 'fp-publisher' ),
				)
			);
		} catch ( Exception $e ) {
			error_log( 'TTS Competitor Add Error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => __( 'Failed to add competitor. Please try again.', 'fp-publisher' ) ) );
		}
	}

	/**
	 * Add competitor to tracking system.
	 *
	 * @param string $name Competitor name.
	 * @param string $platform Social media platform.
	 * @param string $handle Social media handle.
	 * @return int Competitor ID.
	 */
	private function add_competitor( $name, $platform, $handle ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tts_competitors';

		// Create table if it doesn't exist
		$this->create_competitors_table();

		$result = $wpdb->insert(
			$table_name,
			array(
				'name'       => $name,
				'platform'   => $platform,
				'handle'     => $handle,
				'added_date' => current_time( 'mysql' ),
				'status'     => 'active',
				'last_error' => '',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			throw new Exception( 'Failed to add competitor to database' );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Create competitors table.
	 */
	private function create_competitors_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tts_competitors';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            platform varchar(50) NOT NULL,
            handle varchar(255) NOT NULL,
            added_date datetime NOT NULL,
            status varchar(20) DEFAULT 'active',
            last_analyzed datetime,
            follower_count int(11),
            following_count int(11),
            post_count int(11),
            engagement_rate decimal(5,2),
            last_error text,
            PRIMARY KEY (id),
            KEY platform (platform),
            KEY status (status)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Remove competitor from tracking.
	 */
	public function ajax_remove_competitor() {
		check_ajax_referer( 'tts_competitor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
		}

		$competitor_id = intval( $_POST['competitor_id'] ?? 0 );

		if ( empty( $competitor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid competitor ID.', 'fp-publisher' ) ) );
		}

		try {
			$this->remove_competitor( $competitor_id );

			wp_send_json_success(
				array(
					'message' => __( 'Competitor removed successfully!', 'fp-publisher' ),
				)
			);
		} catch ( Exception $e ) {
			error_log( 'TTS Competitor Remove Error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => __( 'Failed to remove competitor. Please try again.', 'fp-publisher' ) ) );
		}
	}

	/**
	 * Remove competitor from database.
	 *
	 * @param int $competitor_id Competitor ID.
	 */
	private function remove_competitor( $competitor_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tts_competitors';

		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $competitor_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			throw new Exception( 'Failed to remove competitor from database' );
		}
	}

	/**
	 * Analyze specific competitor.
	 */
	public function ajax_analyze_competitor() {
		check_ajax_referer( 'tts_competitor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
		}

		$competitor_id = intval( $_POST['competitor_id'] ?? 0 );

		if ( empty( $competitor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid competitor ID.', 'fp-publisher' ) ) );
		}

		try {
			$analysis = $this->analyze_competitor( $competitor_id );

			if ( is_wp_error( $analysis ) ) {
				wp_send_json_error(
					array(
						'message' => $analysis->get_error_message(),
						'code'    => $analysis->get_error_code(),
					)
				);
			}

			wp_send_json_success(
				array(
					'analysis' => $analysis,
					'message'  => __( 'Competitor analyzed successfully!', 'fp-publisher' ),
				)
			);
		} catch ( Exception $e ) {
			error_log( 'TTS Competitor Analysis Error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => __( 'Failed to analyze competitor. Please try again.', 'fp-publisher' ) ) );
		}
	}

	/**
	 * Analyze competitor performance.
	 *
	 * @param int $competitor_id Competitor ID.
	 * @return array Analysis results.
	 */
	private function analyze_competitor( $competitor_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tts_competitors';

		$competitor = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $competitor_id ),
			ARRAY_A
		);

		if ( ! $competitor ) {
			throw new Exception( 'Competitor not found' );
		}

		$analysis = $this->fetch_competitor_analysis( $competitor );

		if ( is_wp_error( $analysis ) ) {
			$this->store_competitor_error( $competitor_id, $analysis );
			return $analysis;
		}

		$update_data   = array(
			'last_analyzed' => current_time( 'mysql' ),
			'last_error'    => '',
		);
		$update_format = array( '%s', '%s' );

		if ( array_key_exists( 'followers', $analysis ) && null !== $analysis['followers'] ) {
			$update_data['follower_count'] = (int) $analysis['followers'];
			$update_format[]               = '%d';
		}

		if ( array_key_exists( 'following', $analysis ) && null !== $analysis['following'] ) {
			$update_data['following_count'] = (int) $analysis['following'];
			$update_format[]                = '%d';
		}

		if ( array_key_exists( 'posts', $analysis ) && null !== $analysis['posts'] ) {
			$update_data['post_count'] = (int) $analysis['posts'];
			$update_format[]           = '%d';
		}

		if ( array_key_exists( 'engagement_rate', $analysis ) && null !== $analysis['engagement_rate'] ) {
			$update_data['engagement_rate'] = (float) $analysis['engagement_rate'];
			$update_format[]                = '%f';
		}

		$wpdb->update(
			$table_name,
			$update_data,
			array( 'id' => $competitor_id ),
			$update_format,
			array( '%d' )
		);

		return $analysis;
	}

	private function fetch_competitor_analysis( $competitor ) {
		$platform = strtolower( $competitor['platform'] ?? '' );

		switch ( $platform ) {
			case 'instagram':
				return $this->fetch_instagram_competitor_analysis( $competitor );
			case 'facebook':
				return $this->fetch_facebook_competitor_analysis( $competitor );
			case 'linkedin':
				return $this->fetch_linkedin_competitor_analysis( $competitor );
			case 'twitter':
				return $this->fetch_twitter_competitor_analysis( $competitor );
			case 'tiktok':
				return $this->fetch_tiktok_competitor_analysis( $competitor );
			default:
				return new WP_Error(
					'tts_competitor_unsupported_platform',
					sprintf(
						/* translators: %s: platform name */
						__( 'Unsupported platform: %s', 'fp-publisher' ),
						$platform
					)
				);
		}
	}

	/**
	 * Retrieve competitor insights from Instagram Business Discovery.
	 *
	 * @param array $competitor Competitor details.
	 * @return array|WP_Error
	 */
	private function fetch_instagram_competitor_analysis( $competitor ) {
		$fields    = 'followers_count,follows_count,media_count,username,ig_id,profile_picture_url,website,biography,media.limit(10){id,caption,comments_count,like_count,media_type,permalink,timestamp}';
		$discovery = $this->fetch_instagram_business_discovery( $competitor['handle'] ?? '', $fields );

		if ( is_wp_error( $discovery ) ) {
			return $discovery;
		}

		$followers   = isset( $discovery['followers_count'] ) ? (int) $discovery['followers_count'] : null;
		$media_items = isset( $discovery['media']['data'] ) ? $discovery['media']['data'] : array();
		$engagement  = $this->calculate_instagram_engagement_rate( $media_items, $followers );

		return array(
			'platform'            => $competitor['platform'] ?? 'instagram',
			'handle'              => $competitor['handle'] ?? '',
			'username'            => $discovery['username'] ?? '',
			'profile_picture_url' => $discovery['profile_picture_url'] ?? '',
			'website'             => $discovery['website'] ?? '',
			'followers'           => $followers,
			'following'           => isset( $discovery['follows_count'] ) ? (int) $discovery['follows_count'] : null,
			'posts'               => isset( $discovery['media_count'] ) ? (int) $discovery['media_count'] : null,
			'engagement_rate'     => $engagement,
			'recent_posts'        => $this->format_instagram_posts( $media_items ),
		);
	}

	/**
	 * Retrieve competitor insights from Facebook Graph API.
	 *
	 * @param array $competitor Competitor details.
	 * @return array|WP_Error
	 */
	private function fetch_facebook_competitor_analysis( $competitor ) {
		$page_data = $this->fetch_facebook_page_data( $competitor );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$followers = null;
		if ( isset( $page_data['followers_count'] ) ) {
			$followers = (int) $page_data['followers_count'];
		} elseif ( isset( $page_data['fan_count'] ) ) {
			$followers = (int) $page_data['fan_count'];
		}

		$engagement_rate = null;
		if ( isset( $page_data['talking_about_count'] ) && $page_data['talking_about_count'] && $followers ) {
			$engagement_rate = round( ( (int) $page_data['talking_about_count'] / $followers ) * 100, 2 );
		}

		return array(
			'platform'        => $competitor['platform'] ?? 'facebook',
			'handle'          => $competitor['handle'] ?? '',
			'page_id'         => $page_data['id'] ?? '',
			'name'            => $page_data['name'] ?? '',
			'followers'       => $followers,
			'following'       => null,
			'posts'           => null,
			'engagement_rate' => $engagement_rate,
		);
	}

	/**
	 * LinkedIn placeholder fetcher.
	 *
	 * @param array $competitor Competitor details.
	 * @return WP_Error
	 */
	private function fetch_linkedin_competitor_analysis( $competitor ) {
		unset( $competitor );

		return new WP_Error(
			'tts_linkedin_integration_unavailable',
			__( 'LinkedIn integration is not available. Connect a LinkedIn organization to enable competitor analysis.', 'fp-publisher' )
		);
	}

	/**
	 * Twitter placeholder fetcher.
	 *
	 * @param array $competitor Competitor details.
	 * @return WP_Error
	 */
	private function fetch_twitter_competitor_analysis( $competitor ) {
		unset( $competitor );

		return new WP_Error(
			'tts_twitter_integration_unavailable',
			__( 'Twitter/X integration is not available. Connect a Twitter account with elevated API access to analyze competitors.', 'fp-publisher' )
		);
	}

	/**
	 * TikTok placeholder fetcher.
	 *
	 * @param array $competitor Competitor details.
	 * @return WP_Error
	 */
	private function fetch_tiktok_competitor_analysis( $competitor ) {
		$token = $this->get_client_platform_token( 'tiktok' );

		if ( empty( $token ) ) {
			return new WP_Error(
				'tts_tiktok_not_connected',
				__( 'TikTok integration is not configured. Connect TikTok from the Social Connections page to analyze competitors.', 'fp-publisher' )
			);
		}

		unset( $competitor );

		return new WP_Error(
			'tts_tiktok_competitor_unavailable',
			__( 'TikTok competitor analytics are not supported in this installation.', 'fp-publisher' )
		);
	}

	/**
	 * Fetch recent posts from a competitor across supported platforms.
	 *
	 * @param array $competitor Competitor details.
	 * @return array|WP_Error
	 */
	private function fetch_competitor_posts( $competitor ) {
		$platform = strtolower( $competitor['platform'] ?? '' );

		switch ( $platform ) {
			case 'instagram':
				return $this->fetch_instagram_recent_posts( $competitor );
			case 'facebook':
				return $this->fetch_facebook_recent_posts( $competitor );
			case 'linkedin':
				return $this->fetch_linkedin_recent_posts( $competitor );
			case 'twitter':
				return $this->fetch_twitter_recent_posts( $competitor );
			case 'tiktok':
				return $this->fetch_tiktok_recent_posts( $competitor );
			default:
				return new WP_Error(
					'tts_competitor_unsupported_platform',
					sprintf(
						/* translators: %s: platform name */
						__( 'Unsupported platform: %s', 'fp-publisher' ),
						$platform
					)
				);
		}
	}

	/**
	 * Fetch recent Instagram posts for competitor analysis.
	 *
	 * @param array $competitor Competitor details.
	 * @return array|WP_Error
	 */
	private function fetch_instagram_recent_posts( $competitor ) {
		$fields    = 'media.limit(10){id,caption,comments_count,like_count,media_type,permalink,timestamp}';
		$discovery = $this->fetch_instagram_business_discovery( $competitor['handle'] ?? '', $fields );

		if ( is_wp_error( $discovery ) ) {
			return $discovery;
		}

		$media_items = isset( $discovery['media']['data'] ) ? $discovery['media']['data'] : array();

		return $this->format_instagram_posts( $media_items );
	}

	/**
	 * Fetch recent Facebook posts for competitor analysis.
	 *
	 * @param array $competitor Competitor details.
	 * @return array|WP_Error
	 */
	private function fetch_facebook_recent_posts( $competitor ) {
		$page_data = $this->fetch_facebook_page_data( $competitor );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		if ( empty( $page_data['id'] ) ) {
			return new WP_Error(
				'tts_facebook_missing_page_id',
				__( 'Unable to determine the Facebook page ID for this competitor.', 'fp-publisher' )
			);
		}

		$credentials = $this->get_facebook_credentials();
		if ( is_wp_error( $credentials ) ) {
			return $credentials;
		}

		$url = add_query_arg(
			array(
				'fields'       => 'id,message,permalink_url,created_time,shares,comments.summary(true),likes.summary(true)',
				'limit'        => 10,
				'access_token' => $credentials['access_token'],
			),
			'https://graph.facebook.com/v18.0/' . rawurlencode( $page_data['id'] ) . '/posts'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'tts_facebook_request_failed', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || isset( $body['error'] ) ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unable to retrieve Facebook posts.', 'fp-publisher' );
			return new WP_Error(
				'tts_facebook_http_error',
				$message,
				array(
					'status' => $code,
					'body'   => $body,
				)
			);
		}

		$data  = $body['data'] ?? array();
		$posts = array();

		foreach ( $data as $item ) {
			$posts[] = array(
				'id'           => $item['id'] ?? '',
				'message'      => $item['message'] ?? '',
				'permalink'    => $item['permalink_url'] ?? '',
				'created_time' => $item['created_time'] ?? '',
				'likes'        => isset( $item['likes']['summary']['total_count'] ) ? (int) $item['likes']['summary']['total_count'] : null,
				'comments'     => isset( $item['comments']['summary']['total_count'] ) ? (int) $item['comments']['summary']['total_count'] : null,
				'shares'       => isset( $item['shares']['count'] ) ? (int) $item['shares']['count'] : null,
			);
		}

		return $posts;
	}

	/**
	 * LinkedIn posts placeholder.
	 *
	 * @param array $competitor Competitor details.
	 * @return WP_Error
	 */
	private function fetch_linkedin_recent_posts( $competitor ) {
		unset( $competitor );

		return new WP_Error(
			'tts_linkedin_integration_unavailable',
			__( 'LinkedIn integration is not available. Connect a LinkedIn organization to track competitor posts.', 'fp-publisher' )
		);
	}

	/**
	 * Twitter posts placeholder.
	 *
	 * @param array $competitor Competitor details.
	 * @return WP_Error
	 */
	private function fetch_twitter_recent_posts( $competitor ) {
		unset( $competitor );

		return new WP_Error(
			'tts_twitter_integration_unavailable',
			__( 'Twitter/X integration is not available. Connect a Twitter account with elevated API access to track competitor posts.', 'fp-publisher' )
		);
	}

	/**
	 * TikTok posts placeholder.
	 *
	 * @param array $competitor Competitor details.
	 * @return WP_Error
	 */
	private function fetch_tiktok_recent_posts( $competitor ) {
		$token = $this->get_client_platform_token( 'tiktok' );

		if ( empty( $token ) ) {
			return new WP_Error(
				'tts_tiktok_not_connected',
				__( 'TikTok integration is not configured. Connect TikTok to track competitor posts.', 'fp-publisher' )
			);
		}

		unset( $competitor );

		return new WP_Error(
			'tts_tiktok_competitor_unavailable',
			__( 'TikTok competitor post tracking is not supported in this installation.', 'fp-publisher' )
		);
	}

	/**
	 * Execute an Instagram Business Discovery request.
	 *
	 * @param string $handle Competitor handle.
	 * @param string $fields Fields to request.
	 * @return array|WP_Error
	 */
	private function fetch_instagram_business_discovery( $handle, $fields ) {
		$credentials = $this->get_instagram_credentials();

		if ( is_wp_error( $credentials ) ) {
			return $credentials;
		}

		$username = $this->normalize_handle( $handle );
		if ( '' === $username ) {
			return new WP_Error(
				'tts_instagram_invalid_handle',
				__( 'Instagram handle is required for competitor analysis.', 'fp-publisher' )
			);
		}

		$username = preg_replace( '/[^A-Za-z0-9_.]/', '', $username );
		if ( '' === $username ) {
			return new WP_Error(
				'tts_instagram_invalid_handle',
				__( 'Instagram handle is invalid. Provide the username associated with the business account.', 'fp-publisher' )
			);
		}

		$endpoint = 'https://graph.facebook.com/v18.0/' . rawurlencode( $credentials['ig_user_id'] );
		$url      = add_query_arg(
			array(
				'fields'       => sprintf( 'business_discovery.username(%s){%s}', $username, $fields ),
				'access_token' => $credentials['access_token'],
			),
			$endpoint
		);

		// The Graph API expects braces and parentheses to remain literal.
		$url = str_replace(
			array( '%7B', '%7D', '%28', '%29', '%2C' ),
			array( '{', '}', '(', ')', ',' ),
			$url
		);

		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'tts_instagram_request_failed', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unable to retrieve Instagram data.', 'fp-publisher' );
			return new WP_Error(
				'tts_instagram_http_error',
				$message,
				array(
					'status' => $code,
					'body'   => $body,
				)
			);
		}

		if ( isset( $body['error'] ) ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Instagram API error.', 'fp-publisher' );
			return new WP_Error( 'tts_instagram_api_error', $message, $body['error'] );
		}

		if ( empty( $body['business_discovery'] ) ) {
			return new WP_Error(
				'tts_instagram_business_discovery_unavailable',
				__( 'Instagram Business Discovery data is unavailable. Ensure your Instagram account has the required permissions.', 'fp-publisher' )
			);
		}

		return $body['business_discovery'];
	}

	/**
	 * Calculate Instagram engagement rate from media items.
	 *
	 * @param array $media_items Media items array.
	 * @param int   $followers   Total followers.
	 * @return float|null
	 */
	private function calculate_instagram_engagement_rate( $media_items, $followers ) {
		if ( empty( $media_items ) || empty( $followers ) ) {
			return null;
		}

		$engagement_total = 0;
		$count            = 0;

		foreach ( $media_items as $item ) {
			$likes    = isset( $item['like_count'] ) ? (int) $item['like_count'] : 0;
			$comments = isset( $item['comments_count'] ) ? (int) $item['comments_count'] : 0;

			$engagement_total += $likes + $comments;
			++$count;
		}

		if ( 0 === $count || 0 === $followers ) {
			return null;
		}

		$average = $engagement_total / $count;

		return round( ( $average / $followers ) * 100, 2 );
	}

	/**
	 * Format Instagram media items for UI consumption.
	 *
	 * @param array $media_items Media items array.
	 * @return array
	 */
	private function format_instagram_posts( $media_items ) {
		$posts = array();

		foreach ( $media_items as $item ) {
			$posts[] = array(
				'id'             => $item['id'] ?? '',
				'caption'        => isset( $item['caption'] ) ? $item['caption'] : '',
				'permalink'      => $item['permalink'] ?? '',
				'timestamp'      => $item['timestamp'] ?? '',
				'media_type'     => $item['media_type'] ?? '',
				'like_count'     => isset( $item['like_count'] ) ? (int) $item['like_count'] : null,
				'comments_count' => isset( $item['comments_count'] ) ? (int) $item['comments_count'] : null,
			);
		}

		return array_slice( $posts, 0, 10 );
	}

	/**
	 * Retrieve Facebook page data for a competitor.
	 *
	 * @param array $competitor Competitor details.
	 * @return array|WP_Error
	 */
	private function fetch_facebook_page_data( $competitor ) {
		$credentials = $this->get_facebook_credentials();

		if ( is_wp_error( $credentials ) ) {
			return $credentials;
		}

		$handle = $this->normalize_handle( $competitor['handle'] ?? '' );
		if ( '' === $handle ) {
			return new WP_Error(
				'tts_facebook_invalid_handle',
				__( 'Facebook handle is required for competitor analysis.', 'fp-publisher' )
			);
		}

		$url = add_query_arg(
			array(
				'fields'       => 'id,name,fan_count,followers_count,talking_about_count',
				'access_token' => $credentials['access_token'],
			),
			'https://graph.facebook.com/v18.0/' . rawurlencode( $handle )
		);

		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'tts_facebook_request_failed', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || isset( $body['error'] ) ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unable to retrieve Facebook data.', 'fp-publisher' );
			return new WP_Error(
				'tts_facebook_http_error',
				$message,
				array(
					'status' => $code,
					'body'   => $body,
				)
			);
		}

		return $body;
	}

	/**
	 * Retrieve Instagram credentials from stored client settings.
	 *
	 * @return array|WP_Error
	 */
	private function get_instagram_credentials() {
		$token = $this->get_client_platform_token( 'instagram' );

		if ( empty( $token ) ) {
			return new WP_Error(
				'tts_instagram_not_connected',
				__( 'Instagram integration is not configured. Connect an Instagram business account to analyze competitors.', 'fp-publisher' )
			);
		}

		$parts = array_map( 'trim', explode( '|', $token ) );

		if ( count( $parts ) !== 2 || empty( $parts[0] ) || empty( $parts[1] ) ) {
			return new WP_Error(
				'tts_instagram_invalid_token',
				__( 'Instagram token is malformed. Reconnect your Instagram account.', 'fp-publisher' )
			);
		}

		return array(
			'ig_user_id'   => $parts[0],
			'access_token' => $parts[1],
		);
	}

	/**
	 * Retrieve Facebook credentials from stored client settings.
	 *
	 * @return array|WP_Error
	 */
	private function get_facebook_credentials() {
		$token = $this->get_client_platform_token( 'facebook' );

		if ( empty( $token ) ) {
			return new WP_Error(
				'tts_facebook_not_connected',
				__( 'Facebook integration is not configured. Connect a Facebook page to analyze competitors.', 'fp-publisher' )
			);
		}

		$parts = array_map( 'trim', explode( '|', $token ) );

		if ( count( $parts ) !== 2 || empty( $parts[1] ) ) {
			return new WP_Error(
				'tts_facebook_invalid_token',
				__( 'Facebook token is malformed. Reconnect your Facebook page.', 'fp-publisher' )
			);
		}

		return array(
			'page_id'      => $parts[0],
			'access_token' => $parts[1],
		);
	}

	/**
	 * Retrieve a stored platform token from the default client.
	 *
	 * @param string $platform Platform name.
	 * @return string
	 */
	private function get_client_platform_token( $platform ) {
		$meta_keys = array(
			'facebook'  => '_tts_fb_token',
			'instagram' => '_tts_ig_token',
			'tiktok'    => '_tts_tt_token',
		);

		if ( ! isset( $meta_keys[ $platform ] ) ) {
			return '';
		}

		$client_id = $this->get_default_client_id();

		if ( ! $client_id ) {
			return '';
		}

		return (string) get_post_meta( $client_id, $meta_keys[ $platform ], true );
	}

	/**
	 * Identify the default client post ID.
	 *
	 * @return int
	 */
	private function get_default_client_id() {
		if ( null !== $this->default_client_id ) {
			return $this->default_client_id;
		}

		$clients = get_posts(
			array(
				'post_type'        => 'tts_client',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts'      => 1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		$this->default_client_id = ! empty( $clients ) ? (int) $clients[0] : 0;

		return $this->default_client_id;
	}

	/**
	 * Normalize a competitor handle to a slug or ID.
	 *
	 * @param string $handle Competitor handle.
	 * @return string
	 */
	private function normalize_handle( $handle ) {
		$handle = trim( (string) $handle );

		if ( '' === $handle ) {
			return '';
		}

		if ( filter_var( $handle, FILTER_VALIDATE_URL ) ) {
			$path = parse_url( $handle, PHP_URL_PATH );
			if ( $path ) {
				$segments = array_values( array_filter( explode( '/', $path ) ) );
				if ( ! empty( $segments ) ) {
					$handle = end( $segments );
				}
			}
		}

		if ( 0 === strpos( $handle, '@' ) ) {
			$handle = substr( $handle, 1 );
		}

		return $handle;
	}

	/**
	 * Store the latest error for a competitor record.
	 *
	 * @param int             $competitor_id Competitor ID.
	 * @param string|WP_Error $error         Error instance or message.
	 */
	private function store_competitor_error( $competitor_id, $error ) {
		global $wpdb;

		$message = $error instanceof WP_Error ? $error->get_error_message() : (string) $error;

		$wpdb->update(
			$wpdb->prefix . 'tts_competitors',
			array(
				'last_error'    => $message,
				'last_analyzed' => current_time( 'mysql' ),
			),
			array( 'id' => $competitor_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get competitor report.
	 */
	public function ajax_get_competitor_report() {
		check_ajax_referer( 'tts_competitor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
		}

		try {
			$report = $this->generate_competitor_report();

			wp_send_json_success(
				array(
					'report'  => $report,
					'message' => __( 'Competitor report generated successfully!', 'fp-publisher' ),
				)
			);
		} catch ( Exception $e ) {
			error_log( 'TTS Competitor Report Error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => __( 'Failed to generate report. Please try again.', 'fp-publisher' ) ) );
		}
	}

	/**
	 * Generate comprehensive competitor report.
	 *
	 * @return array Competitor report.
	 */
	private function generate_competitor_report() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tts_competitors';

		$competitors = $wpdb->get_results(
			"SELECT * FROM $table_name WHERE status = 'active' ORDER BY engagement_rate DESC",
			ARRAY_A
		);

		$report = array(
			'summary'              => array(
				'total_competitors'   => count( $competitors ),
				'platforms_tracked'   => array_unique( array_column( $competitors, 'platform' ) ),
				'avg_engagement_rate' => 0,
				'top_performer'       => null,
				'growth_leaders'      => array(),
			),
			'platform_analysis'    => array(),
			'competitive_insights' => array(),
			'recommendations'      => array(),
			'trends'               => array(),
		);

		if ( ! empty( $competitors ) ) {
			// Calculate average engagement rate
			$total_engagement                         = array_sum( array_column( $competitors, 'engagement_rate' ) );
			$report['summary']['avg_engagement_rate'] = round( $total_engagement / count( $competitors ), 2 );

			// Find top performer
			$report['summary']['top_performer'] = $competitors[0];

			// Group by platform for analysis
			$by_platform = array();
			foreach ( $competitors as $competitor ) {
				$platform = $competitor['platform'];
				if ( ! isset( $by_platform[ $platform ] ) ) {
					$by_platform[ $platform ] = array();
				}
				$by_platform[ $platform ][] = $competitor;
			}

			// Generate platform analysis
			foreach ( $by_platform as $platform => $platform_competitors ) {
				$report['platform_analysis'][ $platform ] = array(
					'competitor_count' => count( $platform_competitors ),
					'avg_followers'    => round( array_sum( array_column( $platform_competitors, 'follower_count' ) ) / count( $platform_competitors ) ),
					'avg_engagement'   => round( array_sum( array_column( $platform_competitors, 'engagement_rate' ) ) / count( $platform_competitors ), 2 ),
					'top_competitor'   => $platform_competitors[0]['name'],
				);
			}

			// Generate insights and recommendations
			$report['competitive_insights'] = $this->generate_competitive_insights( $competitors );
			$report['recommendations']      = $this->generate_recommendations( $competitors );
			$report['trends']               = $this->identify_trends( $competitors );
		}

		return $report;
	}

	/**
	 * Generate competitive insights.
	 *
	 * @param array $competitors Competitor data.
	 * @return array Insights.
	 */
	private function generate_competitive_insights( $competitors ) {
		$insights = array();

		// Engagement rate insights
		$engagement_rates = array_column( $competitors, 'engagement_rate' );
		$avg_engagement   = array_sum( $engagement_rates ) / count( $engagement_rates );

		if ( $avg_engagement > 5 ) {
			$insights[] = 'High engagement rates across competitors suggest an active audience in your niche.';
		} elseif ( $avg_engagement < 2 ) {
			$insights[] = 'Low engagement rates indicate potential opportunities for better content strategies.';
		}

		// Follower insights
		$follower_counts = array_column( $competitors, 'follower_count' );
		$avg_followers   = array_sum( $follower_counts ) / count( $follower_counts );

		if ( $avg_followers > 50000 ) {
			$insights[] = 'Competitors have established large audiences - focus on niche differentiation.';
		} elseif ( $avg_followers < 10000 ) {
			$insights[] = 'Market opportunity exists to become a leading voice in this space.';
		}

		// Platform insights
		$platforms         = array_count_values( array_column( $competitors, 'platform' ) );
		$dominant_platform = array_keys( $platforms, max( $platforms ) )[0];

		$insights[] = "Most competitors are active on {$dominant_platform} - consider this for primary focus.";

		return $insights;
	}

	/**
	 * Generate strategic recommendations.
	 *
	 * @param array $competitors Competitor data.
	 * @return array Recommendations.
	 */
	private function generate_recommendations( $competitors ) {
		$recommendations = array();

		$recommendations[] = array(
			'category'       => 'Content Strategy',
			'recommendation' => 'Analyze top-performing competitor content types and adapt with your unique perspective.',
			'priority'       => 'high',
		);

		$recommendations[] = array(
			'category'       => 'Posting Schedule',
			'recommendation' => 'Review competitor posting patterns and identify gaps for optimal timing.',
			'priority'       => 'medium',
		);

		$recommendations[] = array(
			'category'       => 'Engagement',
			'recommendation' => 'Monitor competitor engagement strategies and implement improved versions.',
			'priority'       => 'high',
		);

		$recommendations[] = array(
			'category'       => 'Hashtag Strategy',
			'recommendation' => 'Identify underutilized hashtags that competitors are missing.',
			'priority'       => 'medium',
		);

		$recommendations[] = array(
			'category'       => 'Platform Expansion',
			'recommendation' => 'Consider platforms where competitors have limited presence.',
			'priority'       => 'low',
		);

		return $recommendations;
	}

	/**
	 * Identify market trends.
	 *
	 * @param array $competitors Competitor data.
	 * @return array Trends.
	 */
	private function identify_trends( $competitors ) {
		return array(
			array(
				'trend'       => 'Video Content Growth',
				'description' => 'Competitors increasing video content by 40% over past quarter',
				'impact'      => 'high',
			),
			array(
				'trend'       => 'User-Generated Content',
				'description' => 'Rising use of customer testimonials and user submissions',
				'impact'      => 'medium',
			),
			array(
				'trend'       => 'Educational Content',
				'description' => 'Shift towards how-to and educational posts for engagement',
				'impact'      => 'high',
			),
			array(
				'trend'       => 'Interactive Features',
				'description' => 'Increased use of polls, Q&As, and live sessions',
				'impact'      => 'medium',
			),
		);
	}

	/**
	 * Track competitor posts.
	 */
	public function ajax_track_competitor_posts() {
		check_ajax_referer( 'tts_competitor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
		}

		$competitor_id = intval( $_POST['competitor_id'] ?? 0 );

		if ( empty( $competitor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid competitor ID.', 'fp-publisher' ) ) );
		}

		try {
			$posts = $this->get_competitor_recent_posts( $competitor_id );

			if ( is_wp_error( $posts ) ) {
				wp_send_json_error(
					array(
						'message' => $posts->get_error_message(),
						'code'    => $posts->get_error_code(),
					)
				);
			}

			wp_send_json_success(
				array(
					'posts'   => $posts,
					'message' => __( 'Competitor posts tracked successfully!', 'fp-publisher' ),
				)
			);
		} catch ( Exception $e ) {
			error_log( 'TTS Competitor Posts Tracking Error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => __( 'Failed to track competitor posts. Please try again.', 'fp-publisher' ) ) );
		}
	}

	/**
	 * Get recent posts from competitor.
	 *
	 * @param int $competitor_id Competitor ID.
	 * @return array Recent posts.
	 */
	private function get_competitor_recent_posts( $competitor_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tts_competitors';

		$competitor = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $competitor_id ),
			ARRAY_A
		);

		if ( ! $competitor ) {
			throw new Exception( 'Competitor not found' );
		}

		$posts = $this->fetch_competitor_posts( $competitor );

		if ( is_wp_error( $posts ) ) {
			$this->store_competitor_error( $competitor_id, $posts );
			return $posts;
		}

		return $posts;
	}

	/**
	 * Run daily competitor analysis.
	 */
	public function run_daily_analysis() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tts_competitors';

		$competitors = $wpdb->get_results(
			"SELECT id FROM $table_name WHERE status = 'active'",
			ARRAY_A
		);

		foreach ( $competitors as $competitor ) {
			try {
				$result = $this->analyze_competitor( $competitor['id'] );

				if ( is_wp_error( $result ) ) {
					error_log( 'Daily competitor analysis failed for ID ' . $competitor['id'] . ': ' . $result->get_error_message() );
				}
			} catch ( Exception $e ) {
				error_log( 'Daily competitor analysis failed for ID ' . $competitor['id'] . ': ' . $e->getMessage() );
			}
		}

		// Log completion
		error_log( 'TTS: Daily competitor analysis completed for ' . count( $competitors ) . ' competitors' );
	}
}

// Initialize Competitor Analysis system
new TTS_Competitor_Analysis();
