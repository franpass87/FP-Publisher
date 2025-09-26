<?php
/**
 * Advanced Backup and Recovery System
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TTS_Backup class for enterprise-level data backup and recovery
 */
class TTS_Backup {

	/**
	 * Initialize the backup system
	 */
	public function __construct() {
		add_action( 'wp_ajax_tts_create_backup', array( $this, 'ajax_create_backup' ) );
		add_action( 'wp_ajax_tts_restore_backup', array( $this, 'ajax_restore_backup' ) );
		add_action( 'wp_ajax_tts_download_backup', array( $this, 'ajax_download_backup' ) );
		add_action( 'wp_ajax_tts_delete_backup', array( $this, 'ajax_delete_backup' ) );
		add_action( 'wp_ajax_tts_list_backups', array( $this, 'ajax_list_backups' ) );

		// Schedule automatic backups
		add_action( 'tts_daily_backup', array( $this, 'create_automatic_backup' ) );
		if ( ! wp_next_scheduled( 'tts_daily_backup' ) ) {
			wp_schedule_event( time(), 'daily', 'tts_daily_backup' );
		}
	}

	/**
	 * Create backup via AJAX
	 */
	public function ajax_create_backup() {
		check_ajax_referer( 'tts_backup_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
		}

		$backup_type = sanitize_text_field( $_POST['backup_type'] ?? 'full' );
		$result      = $this->create_backup( $backup_type );

		wp_send_json( $result );
	}

	/**
	 * Validate a backup filename provided by user input.
	 *
	 * @param string $filename Raw filename from the request.
	 *
	 * @return string|WP_Error Sanitized filename or WP_Error on failure.
	 */
	private function normalise_backup_filename( $filename ) {
		$sanitised = sanitize_file_name( wp_basename( (string) $filename ) );

		if ( '' === $sanitised ) {
			return new WP_Error( 'tts_invalid_backup_filename', __( 'A valid backup filename is required.', 'fp-publisher' ) );
		}

		$pattern = '/^tts-backup-[a-z0-9\-]+-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.json(\.gz)?$/i';
		if ( ! preg_match( $pattern, $sanitised ) ) {
			return new WP_Error( 'tts_invalid_backup_filename', __( 'The requested backup filename is not recognised.', 'fp-publisher' ) );
		}

		return $sanitised;
	}

	/**
	 * Resolve a validated backup filename to an absolute path within the backup directory.
	 *
	 * @param string $filename Sanitized filename.
	 *
	 * @return string|WP_Error Absolute path or WP_Error when the path would escape the directory.
	 */
	private function get_backup_path( $filename ) {
		$validated = $this->normalise_backup_filename( $filename );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$directory      = trailingslashit( $this->get_backup_directory() );
		$normalised_dir = wp_normalize_path( $directory );
		$path           = wp_normalize_path( $directory . $validated );

		if ( 0 !== strpos( $path, $normalised_dir ) ) {
			return new WP_Error( 'tts_invalid_backup_path', __( 'The resolved backup path is invalid.', 'fp-publisher' ) );
		}

		return $path;
	}

	/**
	 * Create a comprehensive backup
	 *
	 * @param string $type Backup type: 'full', 'settings', 'clients', 'logs'
	 * @return array Backup result
	 */
	public function create_backup( $type = 'full' ) {
		global $wpdb;

		try {
			$backup_data = array(
				'timestamp' => current_time( 'mysql' ),
				'type'      => $type,
				'version'   => '1.1.0',
				'site_url'  => get_site_url(),
				'meta'      => array(),
				'data'      => array(),
			);

			switch ( $type ) {
				case 'full':
					$backup_data['data'] = $this->get_full_backup_data();
					break;
				case 'settings':
					$backup_data['data'] = $this->get_settings_backup_data();
					break;
				case 'clients':
					$backup_data['data'] = $this->get_clients_backup_data();
					break;
				case 'logs':
					$backup_data['data'] = $this->get_logs_backup_data();
					break;
			}

			$backup_data = $this->secure_backup_payload( $backup_data );

			$backup_filename = $this->generate_backup_filename( $type );
			$backup_path     = $this->get_backup_directory() . $backup_filename;

			// Create backup directory if it doesn't exist
			$this->ensure_backup_directory();

			// Save backup to file
			$backup_json = wp_json_encode( $backup_data, JSON_PRETTY_PRINT );
			if ( false === file_put_contents( $backup_path, $backup_json ) ) {
				throw new Exception( __( 'Failed to save backup file', 'fp-publisher' ) );
			}

			// Compress backup
			$compressed_path = $this->compress_backup( $backup_path );
			if ( ! unlink( $backup_path ) ) {
				error_log( 'TTS Backup: Failed to remove uncompressed backup file: ' . $backup_path );
			}

			// Log backup creation
			TTS_Logger::log( 'Backup created successfully: ' . $backup_filename );

			return array(
				'success'   => true,
				'message'   => __( 'Backup created successfully', 'fp-publisher' ),
				'filename'  => basename( $compressed_path ),
				'size'      => $this->format_file_size( filesize( $compressed_path ) ),
				'timestamp' => current_time( 'mysql' ),
			);

		} catch ( Exception $e ) {
			TTS_Logger::log( 'Backup creation failed: ' . $e->getMessage(), 'error' );

			return array(
				'success' => false,
				'message' => __( 'Backup creation failed: ', 'fp-publisher' ) . $e->getMessage(),
			);
		}
	}

	/**
	 * Get full backup data
	 */
	private function get_full_backup_data() {
		return array(
			'settings' => $this->get_settings_backup_data(),
			'clients'  => $this->get_clients_backup_data(),
			'logs'     => $this->get_logs_backup_data(),
			'posts'    => $this->get_posts_backup_data(),
			'media'    => $this->get_media_backup_data(),
		);
	}

	/**
	 * Get settings backup data
	 */
	private function get_settings_backup_data() {
		$settings      = array();
		$settings_keys = array(
			'tts_facebook_app_id',
			'tts_facebook_app_secret',
			'tts_instagram_app_id',
			'tts_instagram_app_secret',
			'tts_youtube_client_id',
			'tts_youtube_client_secret',
			'tts_tiktok_client_key',
			'tts_tiktok_client_secret',
			'tts_default_schedule_time',
			'tts_post_frequency',
			'tts_retry_attempts',
			'tts_enable_analytics',
			'tts_performance_mode',
		);

		foreach ( $settings_keys as $key ) {
			$settings[ $key ] = get_option( $key );
		}

		return $settings;
	}

	/**
	 * Get clients backup data
	 */
	private function get_clients_backup_data() {
		global $wpdb;

		$clients = $wpdb->get_results(
			"SELECT * FROM {$wpdb->posts} WHERE post_type = 'tts_client'",
			ARRAY_A
		);

		// Get client meta data
		foreach ( $clients as &$client ) {
			$client['meta'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
					$client['ID']
				),
				ARRAY_A
			);
		}

		return $clients;
	}

	/**
	 * Get logs backup data
	 */
	private function get_logs_backup_data() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tts_logs';
		return $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 1000", ARRAY_A );
	}

	/**
	 * Get posts backup data
	 */
	private function get_posts_backup_data() {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT p.*, pm.meta_key, pm.meta_value 
             FROM {$wpdb->posts} p 
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type IN ('post', 'page') 
             AND p.post_status = 'publish'
             ORDER BY p.post_date DESC 
             LIMIT 500",
			ARRAY_A
		);
	}

	/**
	 * Get media backup data
	 */
	private function get_media_backup_data() {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT p.*, pm.meta_key, pm.meta_value 
             FROM {$wpdb->posts} p 
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'attachment'
             ORDER BY p.post_date DESC 
             LIMIT 200",
			ARRAY_A
		);
	}

	/**
	 * Restore backup via AJAX
	 */
	public function ajax_restore_backup() {
		check_ajax_referer( 'tts_backup_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
		}

		$restore_type = sanitize_text_field( $_POST['restore_type'] ?? 'full' );
		$filename     = $this->normalise_backup_filename( $_POST['backup_filename'] ?? '' );

		if ( is_wp_error( $filename ) ) {
			wp_send_json_error(
				array( 'message' => $filename->get_error_message() ),
				400
			);

			return;
		}

		$result = $this->restore_backup( $filename, $restore_type );
		wp_send_json( $result );
	}

	/**
	 * Restore backup from file
	 *
	 * @param string $filename Backup filename
	 * @param string $restore_type Type of restore
	 * @return array Restore result
	 */
	public function restore_backup( $filename, $restore_type = 'full' ) {
		try {
			$backup_path = $this->get_backup_path( $filename );
			if ( is_wp_error( $backup_path ) ) {
				throw new Exception( $backup_path->get_error_message() );
			}

			if ( ! file_exists( $backup_path ) ) {
				throw new Exception( __( 'Backup file not found', 'fp-publisher' ) );
			}

			// Decompress backup
			$decompressed_path = $this->decompress_backup( $backup_path );

			// Load backup data
			$backup_content = file_get_contents( $decompressed_path );
			if ( false === $backup_content ) {
				throw new Exception( __( 'Failed to read backup file', 'fp-publisher' ) );
			}

			$backup_data = json_decode( $backup_content, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new Exception( __( 'Backup file contains invalid JSON', 'fp-publisher' ) );
			}

			if ( ! $backup_data ) {
				throw new Exception( __( 'Invalid backup file format', 'fp-publisher' ) );
			}

			$backup_data = $this->decrypt_backup_payload( $backup_data );

			// Perform restore based on type
			switch ( $restore_type ) {
				case 'full':
					$this->restore_full_backup( $backup_data['data'] );
					break;
				case 'settings':
					$this->restore_settings_backup( $backup_data['data']['settings'] ?? $backup_data['data'] );
					break;
				case 'clients':
					$this->restore_clients_backup( $backup_data['data']['clients'] ?? $backup_data['data'] );
					break;
			}

			// Clean up decompressed file
			if ( file_exists( $decompressed_path ) ) {
				unlink( $decompressed_path );
			}

			TTS_Logger::log( 'Backup restored successfully: ' . $filename );

			return array(
				'success' => true,
				'message' => __( 'Backup restored successfully', 'fp-publisher' ),
			);

		} catch ( Exception $e ) {
			TTS_Logger::log( 'Backup restore failed: ' . $e->getMessage(), 'error' );

			return array(
				'success' => false,
				'message' => __( 'Backup restore failed: ', 'fp-publisher' ) . $e->getMessage(),
			);
		}
	}

	/**
	 * Restore full backup
	 */
	private function restore_full_backup( $data ) {
		if ( isset( $data['settings'] ) ) {
			$this->restore_settings_backup( $data['settings'] );
		}
		if ( isset( $data['clients'] ) ) {
			$this->restore_clients_backup( $data['clients'] );
		}
	}

	/**
	 * Restore settings backup
	 */
	private function restore_settings_backup( $settings ) {
		foreach ( $settings as $key => $value ) {
			update_option( $key, $value );
		}
	}

	/**
	 * Restore clients backup
	 */
	private function restore_clients_backup( $clients ) {
		global $wpdb;

		foreach ( $clients as $client ) {
			// Insert or update client post
			$post_data = array(
				'post_title'   => $client['post_title'],
				'post_content' => $client['post_content'],
				'post_status'  => $client['post_status'],
				'post_type'    => 'tts_client',
			);

			$post_id = wp_insert_post( $post_data );

			if ( $post_id && isset( $client['meta'] ) ) {
				foreach ( $client['meta'] as $meta ) {
					update_post_meta( $post_id, $meta['meta_key'], $meta['meta_value'] );
				}
			}
		}
	}

	/**
	 * List available backups via AJAX
	 */
	public function ajax_list_backups() {
		check_ajax_referer( 'tts_backup_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
		}

		$backups = $this->list_backups();
		wp_send_json_success( $backups );
	}

	/**
	 * List available backups
	 */
	public function list_backups() {
		$backup_dir = $this->get_backup_directory();
		$backups    = array();

		if ( is_dir( $backup_dir ) ) {
			$files = scandir( $backup_dir );

			foreach ( $files as $file ) {
				if ( strpos( $file, 'tts-backup-' ) === 0 && strpos( $file, '.gz' ) !== false ) {
					$file_path = $backup_dir . $file;
					$backups[] = array(
						'filename' => $file,
						'size'     => $this->format_file_size( filesize( $file_path ) ),
						'date'     => date( 'Y-m-d H:i:s', filemtime( $file_path ) ),
						'type'     => $this->extract_backup_type( $file ),
					);
				}
			}
		}

		return $backups;
	}

	/**
	 * Create automatic backup
	 */
	public function create_automatic_backup() {
		$this->create_backup( 'full' );
		$this->cleanup_old_backups();
	}

	/**
	 * Delete backup via AJAX
	 */
	public function ajax_delete_backup() {
		check_ajax_referer( 'tts_backup_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
		}

		$filename = $this->normalise_backup_filename( $_POST['filename'] ?? '' );
		if ( is_wp_error( $filename ) ) {
			wp_send_json_error(
				array( 'message' => $filename->get_error_message() ),
				400
			);

			return;
		}

		$result = $this->delete_backup( $filename );

		wp_send_json( $result );
	}

	/**
	 * Delete backup file
	 */
	public function delete_backup( $filename ) {
		$backup_path = $this->get_backup_path( $filename );
		if ( is_wp_error( $backup_path ) ) {
			return array(
				'success' => false,
				'message' => $backup_path->get_error_message(),
			);
		}

		if ( file_exists( $backup_path ) && unlink( $backup_path ) ) {
			return array(
				'success' => true,
				'message' => __( 'Backup deleted successfully', 'fp-publisher' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Failed to delete backup', 'fp-publisher' ),
		);
	}

	/**
	 * Download backup via AJAX
	 */
	public function ajax_download_backup() {
		check_ajax_referer( 'tts_backup_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
		}

		$filename = $this->normalise_backup_filename( $_GET['filename'] ?? '' );
		if ( is_wp_error( $filename ) ) {
			wp_die( esc_html( $filename->get_error_message() ) );
		}

		$this->download_backup( $filename );
	}

	/**
	 * Download backup file
	 */
	public function download_backup( $filename ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
		}

		$backup_path = $this->get_backup_path( $filename );
		if ( is_wp_error( $backup_path ) ) {
			wp_die( esc_html( $backup_path->get_error_message() ) );
		}

		if ( ! file_exists( $backup_path ) ) {
			wp_die( esc_html__( 'Backup file not found', 'fp-publisher' ) );
		}

		if ( ! is_readable( $backup_path ) ) {
			wp_die( esc_html__( 'Backup file is not readable', 'fp-publisher' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/gzip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $backup_path ) );

		readfile( $backup_path );
		exit;
	}

	/**
	 * Generate backup filename
	 */
	private function generate_backup_filename( $type ) {
		return 'tts-backup-' . $type . '-' . date( 'Y-m-d-H-i-s' ) . '.json';
	}

	/**
	 * Get backup directory path
	 */
	private function get_backup_directory() {
		$default_dir = trailingslashit( WP_CONTENT_DIR ) . 'fp-publisher-backups';
		$backup_dir  = apply_filters( 'tts_backup_directory', $default_dir );

		return trailingslashit( $backup_dir );
	}

	/**
	 * Ensure backup directory exists
	 */
	private function ensure_backup_directory() {
		$backup_dir = $this->get_backup_directory();

		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		if ( ! is_dir( $backup_dir ) ) {
			throw new Exception( __( 'Backup directory is not accessible', 'fp-publisher' ) );
		}

		if ( ! is_readable( $backup_dir ) || ! wp_is_writable( $backup_dir ) ) {
			throw new Exception( __( 'Backup directory permissions are insufficient', 'fp-publisher' ) );
		}

		// Add security files if missing.
		if ( ! file_exists( $backup_dir . '.htaccess' ) && false === file_put_contents( $backup_dir . '.htaccess', 'deny from all' ) ) {
			error_log( 'TTS Backup: Failed to create .htaccess security file' );
		}

		if ( ! file_exists( $backup_dir . 'index.php' ) && false === file_put_contents( $backup_dir . 'index.php', '<?php // Silence is golden' ) ) {
			error_log( 'TTS Backup: Failed to create index.php security file' );
		}
	}

	/**
	 * Apply encryption to sensitive fields and document metadata.
	 *
	 * @param array $backup_data Backup payload.
	 * @return array
	 * @throws Exception When encryption key cannot be resolved.
	 */
	private function secure_backup_payload( array $backup_data ) {
		$encryption_key    = $this->get_encryption_key();
		$key_source        = $this->get_encryption_key_source();
		$encryption_method = 'AES-256-CBC';
		$has_encrypted     = false;

		if ( isset( $backup_data['data'] ) ) {
			$backup_data['data'] = $this->encrypt_sensitive_fields( $backup_data['data'], $encryption_key, $encryption_method, $has_encrypted );
		}

		$backup_data['meta']['encryption'] = array(
			'enabled'    => $has_encrypted,
			'method'     => $has_encrypted ? $encryption_method : 'none',
			'key_source' => $key_source,
		);

		return $backup_data;
	}

	/**
	 * Decrypt sensitive fields from the backup payload.
	 *
	 * @param array $backup_data Backup payload.
	 * @return array
	 * @throws Exception When the encryption key is unavailable or decryption fails.
	 */
	private function decrypt_backup_payload( array $backup_data ) {
		if ( empty( $backup_data['meta']['encryption']['enabled'] ) ) {
			return $backup_data;
		}

		$encryption_key = $this->get_encryption_key();

		if ( isset( $backup_data['data'] ) ) {
			$backup_data['data'] = $this->decrypt_sensitive_fields( $backup_data['data'], $encryption_key );
		}

		return $backup_data;
	}

	/**
	 * Recursively encrypt sensitive fields within the payload.
	 *
	 * @param mixed  $data               Data subtree.
	 * @param string $encryption_key     Encryption key.
	 * @param string $encryption_method  Encryption method (updated when fallback is used).
	 * @param bool   $has_encrypted      Flag toggled when a value is encrypted.
	 * @return mixed
	 */
	private function encrypt_sensitive_fields( $data, $encryption_key, &$encryption_method, &$has_encrypted ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( isset( $data['meta_key'], $data['meta_value'] ) && $this->is_sensitive_meta_key( $data['meta_key'] ) && is_string( $data['meta_value'] ) && '' !== $data['meta_value'] ) {
			$data['meta_value'] = $this->encrypt_value( $data['meta_value'], $encryption_key, $encryption_method );
			$has_encrypted      = true;

			return $data;
		}

		foreach ( $data as $field => $value ) {
			if ( is_array( $value ) ) {
				$data[ $field ] = $this->encrypt_sensitive_fields( $value, $encryption_key, $encryption_method, $has_encrypted );
			} elseif ( $this->is_sensitive_key( $field ) && is_string( $value ) && '' !== $value ) {
				$data[ $field ] = $this->encrypt_value( $value, $encryption_key, $encryption_method );
				$has_encrypted  = true;
			}
		}

		return $data;
	}

	/**
	 * Recursively decrypt sensitive fields within the payload.
	 *
	 * @param mixed  $data           Data subtree.
	 * @param string $encryption_key Encryption key.
	 * @return mixed
	 */
	private function decrypt_sensitive_fields( $data, $encryption_key ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( isset( $data['meta_key'], $data['meta_value'] ) && $this->is_encrypted_value( $data['meta_value'] ) ) {
			$data['meta_value'] = $this->decrypt_value( $data['meta_value'], $encryption_key );

			return $data;
		}

		foreach ( $data as $field => $value ) {
			if ( is_array( $value ) ) {
				$data[ $field ] = $this->decrypt_sensitive_fields( $value, $encryption_key );
			} elseif ( is_string( $value ) && $this->is_encrypted_value( $value ) ) {
				$data[ $field ] = $this->decrypt_value( $value, $encryption_key );
			}
		}

		return $data;
	}

	/**
	 * Encrypt a scalar value.
	 *
	 * @param string $value              Value to encrypt.
	 * @param string $encryption_key     Encryption key.
	 * @param string $encryption_method  Encryption method (updated when fallback is used).
	 * @return string
	 * @throws Exception When encryption fails.
	 */
	private function encrypt_value( $value, $encryption_key, &$encryption_method ) {
		if ( function_exists( 'openssl_encrypt' ) ) {
			$cipher     = 'aes-256-cbc';
			$iv_length  = openssl_cipher_iv_length( $cipher );
			$iv         = openssl_random_pseudo_bytes( $iv_length );
			$ciphertext = openssl_encrypt( $value, $cipher, hash( 'sha256', $encryption_key, true ), OPENSSL_RAW_DATA, $iv );

			if ( false === $ciphertext ) {
				throw new Exception( __( 'Failed to encrypt backup data', 'fp-publisher' ) );
			}

			$encryption_method = 'AES-256-CBC';

			return 'enc:' . base64_encode( $iv ) . ':' . base64_encode( $ciphertext );
		}

		$encryption_method = 'BASE64';

		return 'b64:' . base64_encode( $value );
	}

	/**
	 * Decrypt a scalar value.
	 *
	 * @param string $value          Value to decrypt.
	 * @param string $encryption_key Encryption key.
	 * @return string
	 * @throws Exception When decryption fails.
	 */
	private function decrypt_value( $value, $encryption_key ) {
		if ( 0 === strpos( $value, 'b64:' ) ) {
			$decoded = base64_decode( substr( $value, 4 ), true );

			if ( false === $decoded ) {
				throw new Exception( __( 'Failed to decode obfuscated backup data', 'fp-publisher' ) );
			}

			return $decoded;
		}

		if ( 0 === strpos( $value, 'enc:' ) ) {
			if ( ! function_exists( 'openssl_decrypt' ) ) {
				throw new Exception( __( 'OpenSSL is required to decrypt backup data', 'fp-publisher' ) );
			}

			$parts = explode( ':', $value, 3 );

			if ( count( $parts ) !== 3 ) {
				throw new Exception( __( 'Encrypted backup data is malformed', 'fp-publisher' ) );
			}

			list( , $iv_encoded, $ciphertext_encoded ) = $parts;

			$iv         = base64_decode( $iv_encoded, true );
			$ciphertext = base64_decode( $ciphertext_encoded, true );

			if ( false === $iv || false === $ciphertext ) {
				throw new Exception( __( 'Failed to decode encrypted backup payload', 'fp-publisher' ) );
			}

			$plaintext = openssl_decrypt( $ciphertext, 'aes-256-cbc', hash( 'sha256', $encryption_key, true ), OPENSSL_RAW_DATA, $iv );

			if ( false === $plaintext ) {
				throw new Exception( __( 'Unable to decrypt backup payload', 'fp-publisher' ) );
			}

			return $plaintext;
		}

		return $value;
	}

	/**
	 * Determine if the provided value has been encrypted/obfuscated by this system.
	 *
	 * @param mixed $value Value to inspect.
	 * @return bool
	 */
	private function is_encrypted_value( $value ) {
		return is_string( $value ) && ( 0 === strpos( $value, 'enc:' ) || 0 === strpos( $value, 'b64:' ) );
	}

	/**
	 * Determine if a key should be treated as sensitive.
	 *
	 * @param string $key Array key.
	 * @return bool
	 */
	private function is_sensitive_key( $key ) {
		return in_array( $key, $this->get_sensitive_keys(), true );
	}

	/**
	 * Determine if a meta key contains sensitive data.
	 *
	 * @param string $meta_key Meta key name.
	 * @return bool
	 */
	private function is_sensitive_meta_key( $meta_key ) {
		$needles = array( 'secret', 'token', 'key' );

		foreach ( $needles as $needle ) {
			if ( false !== stripos( $meta_key, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * List of known sensitive option keys.
	 *
	 * @return array
	 */
	private function get_sensitive_keys() {
		return array(
			'tts_facebook_app_secret',
			'tts_instagram_app_secret',
			'tts_youtube_client_secret',
			'tts_youtube_client_id',
			'tts_tiktok_client_key',
			'tts_tiktok_client_secret',
		);
	}

	/**
	 * Resolve encryption key for backup payloads.
	 *
	 * @return string
	 * @throws Exception When key cannot be resolved.
	 */
	private function get_encryption_key() {
		if ( defined( 'AUTH_KEY' ) && AUTH_KEY ) {
			return AUTH_KEY;
		}

		$salt = wp_salt( 'auth' );

		if ( ! empty( $salt ) ) {
			return $salt;
		}

		throw new Exception( __( 'Unable to resolve encryption key for backups', 'fp-publisher' ) );
	}

	/**
	 * Describe the source of the encryption key for documentation inside the backup.
	 *
	 * @return string
	 */
	private function get_encryption_key_source() {
		return ( defined( 'AUTH_KEY' ) && AUTH_KEY ) ? 'AUTH_KEY' : 'wp_salt(auth)';
	}

	/**
	 * Compress backup file
	 */
	private function compress_backup( $file_path ) {
		$compressed_path = $file_path . '.gz';

		$fp_out = gzopen( $compressed_path, 'wb9' );
		$fp_in  = fopen( $file_path, 'rb' );

		if ( false === $fp_out || false === $fp_in ) {
			if ( $fp_in ) {
				fclose( $fp_in );
			}
			if ( $fp_out ) {
				gzclose( $fp_out );
			}
			throw new Exception( __( 'Failed to compress backup file', 'fp-publisher' ) );
		}

		while ( ! feof( $fp_in ) ) {
			gzwrite( $fp_out, fread( $fp_in, 1024 * 512 ) );
		}

		fclose( $fp_in );
		gzclose( $fp_out );

		return $compressed_path;
	}

	/**
	 * Decompress backup file
	 */
	private function decompress_backup( $compressed_path ) {
		$decompressed_path = str_replace( '.gz', '', $compressed_path );

		$fp_out = fopen( $decompressed_path, 'wb' );
		$fp_in  = gzopen( $compressed_path, 'rb' );

		if ( false === $fp_out || false === $fp_in ) {
			if ( $fp_out ) {
				fclose( $fp_out );
			}
			if ( $fp_in ) {
				gzclose( $fp_in );
			}
			throw new Exception( __( 'Failed to decompress backup file', 'fp-publisher' ) );
		}

		while ( ! gzeof( $fp_in ) ) {
			fwrite( $fp_out, gzread( $fp_in, 1024 * 512 ) );
		}

		fclose( $fp_out );
		gzclose( $fp_in );

		return $decompressed_path;
	}

	/**
	 * Cleanup old backups
	 */
	private function cleanup_old_backups() {
		$backup_dir = $this->get_backup_directory();
		$files      = glob( $backup_dir . 'tts-backup-*.gz' );

		// Keep only the latest 10 backups
		if ( count( $files ) > 10 ) {
			usort(
				$files,
				function ( $a, $b ) {
					return filemtime( $b ) - filemtime( $a );
				}
			);

			$files_to_delete = array_slice( $files, 10 );
			foreach ( $files_to_delete as $file ) {
				if ( ! unlink( $file ) ) {
					error_log( 'TTS Backup: Failed to delete old backup file: ' . $file );
				}
			}
		}
	}

	/**
	 * Format file size
	 */
	private function format_file_size( $bytes ) {
		if ( $bytes >= 1073741824 ) {
			return number_format( $bytes / 1073741824, 2 ) . ' GB';
		} elseif ( $bytes >= 1048576 ) {
			return number_format( $bytes / 1048576, 2 ) . ' MB';
		} elseif ( $bytes >= 1024 ) {
			return number_format( $bytes / 1024, 2 ) . ' KB';
		} else {
			return $bytes . ' bytes';
		}
	}

	/**
	 * Extract backup type from filename
	 */
	private function extract_backup_type( $filename ) {
		if ( strpos( $filename, '-full-' ) !== false ) {
			return 'full';
		}
		if ( strpos( $filename, '-settings-' ) !== false ) {
			return 'settings';
		}
		if ( strpos( $filename, '-clients-' ) !== false ) {
			return 'clients';
		}
		if ( strpos( $filename, '-logs-' ) !== false ) {
			return 'logs';
		}
		return 'unknown';
	}
}

// Initialize backup system
new TTS_Backup();
