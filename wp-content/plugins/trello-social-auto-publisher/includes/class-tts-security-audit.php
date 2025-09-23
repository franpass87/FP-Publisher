<?php
/**
 * Advanced Security Audit Logging System
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TTS_Security_Audit class for comprehensive security monitoring and logging
 */
class TTS_Security_Audit {

    private $audit_table = 'tts_security_audit';
    private $max_log_entries = 10000;
    private $api_abuse_threshold = 300;

    /**
     * Event types for security auditing
     */
    const EVENT_LOGIN_ATTEMPT = 'login_attempt';
    const EVENT_LOGIN_SUCCESS = 'login_success';
    const EVENT_LOGIN_FAILURE = 'login_failure';
    const EVENT_PERMISSION_VIOLATION = 'permission_violation';
    const EVENT_SUSPICIOUS_ACTIVITY = 'suspicious_activity';
    const EVENT_DATA_ACCESS = 'data_access';
    const EVENT_DATA_MODIFICATION = 'data_modification';
    const EVENT_CONFIG_CHANGE = 'config_change';
    const EVENT_API_ABUSE = 'api_abuse';
    const EVENT_BRUTE_FORCE = 'brute_force';

    /**
     * Risk levels
     */
    const RISK_LOW = 1;
    const RISK_MEDIUM = 2;
    const RISK_HIGH = 3;
    const RISK_CRITICAL = 4;

    /**
     * Initialize security audit system
     */
    public function __construct() {
        $this->ensure_audit_table_exists();

        add_action( 'init', array( $this, 'init_security_monitoring' ) );
        add_action( 'wp_ajax_tts_get_security_audit', array( $this, 'ajax_get_security_audit' ) );
        add_action( 'wp_ajax_tts_get_security_stats', array( $this, 'ajax_get_security_stats' ) );
        add_action( 'wp_ajax_tts_clear_audit_logs', array( $this, 'ajax_clear_audit_logs' ) );

        // Hook into WordPress security events
        add_action( 'wp_login', array( $this, 'log_login_success' ), 10, 2 );
        add_action( 'wp_login_failed', array( $this, 'log_login_failure' ) );
        add_action( 'user_register', array( $this, 'log_user_registration' ) );

        // Schedule cleanup
        add_action( 'tts_daily_security_cleanup', array( $this, 'cleanup_old_logs' ) );
        if ( ! wp_next_scheduled( 'tts_daily_security_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'tts_daily_security_cleanup' );
        }
    }

    /**
     * Initialize security monitoring hooks
     */
    public function init_security_monitoring() {
        // Monitor failed nonce verifications
        add_action( 'wp_die', array( $this, 'monitor_nonce_failures' ), 10, 3 );
        
        // Monitor file access attempts
        add_action( 'wp_loaded', array( $this, 'monitor_file_access' ) );
        
        // Monitor admin area access
        add_action( 'admin_init', array( $this, 'monitor_admin_access' ) );
        
        // Monitor API requests
        add_filter( 'rest_request_before_callbacks', array( $this, 'monitor_api_requests' ), 10, 3 );
    }

    /**
     * Create audit table
     */
    public function create_audit_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . $this->audit_table;

        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_description text NOT NULL,
            risk_level tinyint(1) NOT NULL DEFAULT 1,
            user_id bigint(20) unsigned DEFAULT NULL,
            user_login varchar(60) DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text DEFAULT NULL,
            request_uri text DEFAULT NULL,
            event_data longtext DEFAULT NULL,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_risk_level (risk_level),
            INDEX idx_user_id (user_id),
            INDEX idx_ip_address (ip_address),
            INDEX idx_timestamp (timestamp),
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Ensure the audit table exists before the logger is used.
     */
    private function ensure_audit_table_exists() {
        global $wpdb;

        if ( ! isset( $wpdb ) || empty( $wpdb->prefix ) ) {
            return;
        }

        $table_name = $wpdb->prefix . $this->audit_table;
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) )
        );

        if ( $table_name !== $table_exists ) {
            $this->create_audit_table();
        }
    }

    /**
     * Log security event
     *
     * @param string $event_type Event type constant
     * @param string $description Human-readable description
     * @param int $risk_level Risk level constant
     * @param array $additional_data Additional event data
     */
    public function log_security_event( $event_type, $description, $risk_level = self::RISK_LOW, $additional_data = array() ) {
        global $wpdb;
        
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID ?: null;
        $user_login = $current_user->user_login ?: null;
        
        $event_data = array_merge( array(
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'http_referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'query_string' => $_SERVER['QUERY_STRING'] ?? '',
            'post_data' => $this->sanitize_post_data( $_POST ),
            'session_id' => session_id() ?: 'no_session'
        ), $additional_data );
        
        $table_name = $wpdb->prefix . $this->audit_table;
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'event_type' => $event_type,
                'event_description' => $description,
                'risk_level' => $risk_level,
                'user_id' => $user_id,
                'user_login' => $user_login,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'event_data' => wp_json_encode( $event_data ),
                'timestamp' => current_time( 'mysql' )
            ),
            array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        
        // Trigger alerts for high-risk events
        if ( $risk_level >= self::RISK_HIGH ) {
            $this->trigger_security_alert( $event_type, $description, $risk_level, $event_data );
        }
        
        // Auto-block for critical events
        if ( $risk_level === self::RISK_CRITICAL ) {
            $this->auto_block_ip( $this->get_client_ip(), $event_type );
        }
        
        return $result !== false;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_CLIENT_IP',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ( $ip_keys as $key ) {
            if ( array_key_exists( $key, $_SERVER ) && ! empty( $_SERVER[ $key ] ) ) {
                $ips = explode( ',', $_SERVER[ $key ] );
                $ip = trim( $ips[0] );
                
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Sanitize POST data for logging
     */
    private function sanitize_post_data( $post_data ) {
        $sensitive_keys = array( 'password', 'pass', 'pwd', 'secret', 'token', 'key', 'auth' );
        $sanitized = array();
        
        foreach ( $post_data as $key => $value ) {
            $key_lower = strtolower( $key );
            $is_sensitive = false;
            
            foreach ( $sensitive_keys as $sensitive_key ) {
                if ( strpos( $key_lower, $sensitive_key ) !== false ) {
                    $is_sensitive = true;
                    break;
                }
            }
            
            if ( $is_sensitive ) {
                $sanitized[ $key ] = '[REDACTED]';
            } else {
                $sanitized[ $key ] = is_string( $value ) ? substr( $value, 0, 200 ) : $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Monitor login success
     */
    public function log_login_success( $user_login, $user ) {
        $this->log_security_event(
            self::EVENT_LOGIN_SUCCESS,
            sprintf( 'User %s logged in successfully', $user_login ),
            self::RISK_LOW,
            array(
                'user_id' => $user->ID,
                'user_roles' => $user->roles
            )
        );
    }

    /**
     * Monitor login failures
     */
    public function log_login_failure( $username ) {
        $ip = $this->get_client_ip();
        $recent_failures = $this->count_recent_failures( $ip, 15 ); // Last 15 minutes
        
        $risk_level = self::RISK_LOW;
        if ( $recent_failures >= 10 ) {
            $risk_level = self::RISK_CRITICAL; // Brute force attack
        } elseif ( $recent_failures >= 5 ) {
            $risk_level = self::RISK_HIGH;
        } elseif ( $recent_failures >= 3 ) {
            $risk_level = self::RISK_MEDIUM;
        }
        
        $this->log_security_event(
            self::EVENT_LOGIN_FAILURE,
            sprintf( 'Failed login attempt for username: %s (attempt #%d)', $username, $recent_failures + 1 ),
            $risk_level,
            array(
                'attempted_username' => $username,
                'failure_count' => $recent_failures + 1
            )
        );
        
        // Log brute force pattern
        if ( $recent_failures >= 5 ) {
            $this->log_security_event(
                self::EVENT_BRUTE_FORCE,
                sprintf( 'Potential brute force attack detected from IP %s', $ip ),
                self::RISK_CRITICAL,
                array(
                    'failure_count' => $recent_failures + 1,
                    'time_window' => 15
                )
            );
        }
    }

    /**
     * Count recent login failures from IP
     */
    private function count_recent_failures( $ip, $minutes = 15 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->audit_table;
        $cutoff_time = date( 'Y-m-d H:i:s', time() - ( $minutes * 60 ) );
        
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                 WHERE event_type = %s 
                 AND ip_address = %s 
                 AND timestamp >= %s",
                self::EVENT_LOGIN_FAILURE,
                $ip,
                $cutoff_time
            )
        );
    }

    /**
     * Monitor admin access
     */
    public function monitor_admin_access() {
        $current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $requested_page = '';

        if ( isset( $_REQUEST['page'] ) ) {
            $requested_page = (string) $_REQUEST['page'];
        }

        $script_name = basename( $_SERVER['PHP_SELF'] ?? '' );

        if ( '' === $script_name && isset( $_SERVER['SCRIPT_NAME'] ) ) {
            $script_name = basename( (string) $_SERVER['SCRIPT_NAME'] );
        }

        $allowed_scripts = array( 'profile.php', 'index.php' );

        $should_log_violation = false;

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
            $should_log_violation = true;

            if ( $current_screen ) {
                $is_entitled = in_array( $script_name, $allowed_scripts, true );

                if ( ! $is_entitled && isset( $current_screen->base ) ) {
                    $is_entitled = in_array( $current_screen->base, array( 'profile', 'dashboard' ), true );
                }

                if ( ! $is_entitled && isset( $current_screen->id ) ) {
                    $is_entitled = in_array( $current_screen->id, array( 'profile', 'dashboard' ), true );
                }

                if ( ! $is_entitled && isset( $current_screen->cap ) ) {
                    foreach ( (array) $current_screen->cap as $cap_key => $capability ) {
                        if ( is_string( $capability ) && '' !== $capability && current_user_can( $capability ) ) {
                            $is_entitled = true;
                            break;
                        }

                        if ( is_string( $cap_key ) && '' !== $cap_key && current_user_can( $cap_key ) ) {
                            $is_entitled = true;
                            break;
                        }
                    }
                }

                if ( $is_entitled ) {
                    $should_log_violation = false;
                }
            } else {
                if ( in_array( $script_name, $allowed_scripts, true ) ) {
                    $should_log_violation = false;
                } elseif ( '' === $requested_page || 0 !== strpos( $requested_page, 'fp-publisher-' ) ) {
                    $should_log_violation = false;
                }
            }
        }

        if ( $should_log_violation ) {
            $this->log_security_event(
                self::EVENT_PERMISSION_VIOLATION,
                'Unauthorized admin area access attempt',
                self::RISK_HIGH,
                array(
                    'requested_page' => $requested_page ? sanitize_text_field( $requested_page ) : 'unknown',
                    'capabilities' => wp_get_current_user()->allcaps ?? array()
                )
            );
        }

        // Monitor sensitive page access
        $sensitive_pages = array( 'users.php', 'plugins.php', 'themes.php', 'options-general.php' );

        if ( $current_screen && in_array( $current_screen->base, $sensitive_pages, true ) ) {
            $this->log_security_event(
                self::EVENT_DATA_ACCESS,
                sprintf( 'Access to sensitive admin page: %s', $current_screen->base ),
                self::RISK_MEDIUM,
                array(
                    'admin_page' => $current_screen->base,
                    'screen_id' => $current_screen->id
                )
            );
        }
    }

    /**
     * Monitor API requests.
     *
     * @param mixed $response Response generated before callbacks are executed.
     * @param mixed $handler  Route handler details.
     * @param mixed $request  The REST request object.
     *
     * @return mixed Unmodified REST response.
     */
    public function monitor_api_requests( $response, $handler, $request ) {
        if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
            return $response;
        }

        $route = (string) $request->get_route();

        if ( '' === $route || 0 !== strpos( $route, '/tts/v1/' ) ) {
            return $response;
        }

        if (
            function_exists( 'is_user_logged_in' )
            && is_user_logged_in()
            && function_exists( 'current_user_can' )
            && current_user_can( 'edit_posts' )
        ) {
            return $response;
        }

        $ip_address  = $this->get_client_ip();
        $counter_key = 'tts_api_calls_' . md5( $ip_address . '|' . $route );
        $api_calls   = (int) get_transient( $counter_key );
        $api_calls++;

        set_transient( $counter_key, $api_calls, HOUR_IN_SECONDS );

        if ( $api_calls === $this->api_abuse_threshold + 1 ) {
            $this->log_security_event(
                self::EVENT_API_ABUSE,
                sprintf( 'Potential API abuse detected for %s', $route ),
                self::RISK_HIGH,
                array(
                    'api_calls_per_hour' => $api_calls,
                    'endpoint'           => $route,
                    'ip_address'         => $ip_address,
                )
            );
        }

        return $response;
    }

    /**
     * Monitor file access
     */
    public function monitor_file_access() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        if ( empty( $request_uri ) ) {
            return;
        }

        if ( ( function_exists( 'is_admin' ) && is_admin() ) ||
             ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ||
             false !== strpos( $request_uri, '/wp-admin/' ) ) {
            return;
        }

        $suspicious_patterns = array(
            'wp-config',
            '\.sql$',
            '\.log$',
            'backup',
            'install\.php'
        );
        
        foreach ( $suspicious_patterns as $pattern ) {
            if ( preg_match( '/' . $pattern . '/i', $request_uri ) ) {
                $this->log_security_event(
                    self::EVENT_SUSPICIOUS_ACTIVITY,
                    'Suspicious file access pattern detected',
                    self::RISK_MEDIUM,
                    array(
                        'pattern_matched' => $pattern,
                        'request_uri' => $request_uri
                    )
                );
                break;
            }
        }
    }

    /**
     * Monitor nonce failures
     */
    public function monitor_nonce_failures( $message = '', $title = '', $args = array() ) {
        $request_action = isset( $_REQUEST['action'] )
            ? sanitize_key( wp_unslash( $_REQUEST['action'] ) )
            : '';

        if ( '' === $request_action || 0 !== strpos( $request_action, 'tts_' ) ) {
            return $message;
        }

        $nonce_map = array(
            'tts_rate_limit_nonce' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_check_rate_limits',
                    'tts_reset_rate_limits',
                    'tts_get_quota_status',
                ),
            ),
            'tts_ajax_nonce' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_export_data',
                    'tts_import_data',
                    'tts_system_maintenance',
                    'tts_generate_report',
                    'tts_quick_connection_check',
                    'tts_refresh_health',
                    'tts_save_social_settings',
                    'tts_show_export_modal',
                    'tts_show_import_modal',
                    'tts_test_client_connections',
                    'tts_test_single_connection',
                ),
            ),
            'tts_media_nonce' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_resize_image',
                    'tts_optimize_video',
                    'tts_add_watermark',
                    'tts_batch_process_media',
                    'tts_get_stock_photos',
                    'tts_create_media_variations',
                    'tts_compress_media',
                    'tts_analyze_media_performance',
                ),
            ),
            'tts_workflow_nonce' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_submit_for_approval',
                    'tts_approve_content',
                    'tts_reject_content',
                    'tts_add_workflow_comment',
                    'tts_assign_task',
                    'tts_get_workflow_status',
                    'tts_create_content_template',
                    'tts_get_team_dashboard',
                ),
            ),
            'tts_competitor_nonce' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_add_competitor',
                    'tts_remove_competitor',
                    'tts_analyze_competitor',
                    'tts_get_competitor_report',
                    'tts_track_competitor_posts',
                ),
            ),
            'tts_ai_nonce' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_generate_hashtags',
                    'tts_analyze_content',
                    'tts_predict_performance',
                    'tts_suggest_content',
                    'tts_auto_tag_image',
                ),
            ),
            'tts_integration_nonce' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_connect_integration',
                    'tts_disconnect_integration',
                    'tts_test_integration',
                    'tts_sync_integration_data',
                    'tts_get_integration_data',
                    'tts_configure_integration',
                    'tts_get_available_integrations',
                ),
            ),
            'tts_security_audit_nonce' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_get_security_audit',
                    'tts_get_security_stats',
                    'tts_clear_audit_logs',
                ),
            ),
            'tts_cache_nonce' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_clear_cache',
                    'tts_get_cache_stats',
                    'tts_optimize_cache',
                ),
            ),
            'tts_backup_nonce' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_create_backup',
                    'tts_restore_backup',
                    'tts_download_backup',
                    'tts_delete_backup',
                    'tts_list_backups',
                ),
            ),
            'tts_error_recovery_nonce' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_retry_failed_operation',
                    'tts_get_error_analytics',
                    'tts_clear_retry_queue',
                    'tts_get_retry_queue',
                ),
            ),
            'tts_admin_nonce' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_add_content_source',
                    'tts_sync_content_sources',
                ),
            ),
            'tts_frequency_status' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_refresh_frequency_status',
                    'tts_check_all_frequencies',
                    'tts_test_alert_system',
                ),
            ),
            'tts_frequency_widget' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_frequency_widget_refresh',
                ),
            ),
            'tts_test_connection' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_test_connection',
                ),
            ),
            'tts_check_rate_limits' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_check_rate_limits',
                ),
            ),
            'tts_wizard' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_get_lists',
                ),
            ),
            'tts_dashboard' => array(
                'field'   => 'nonce',
                'actions' => array(
                    'tts_refresh_posts',
                    'tts_delete_post',
                    'tts_bulk_action',
                ),
            ),
        );

        $action_checks = array();

        foreach ( $nonce_map as $nonce_action => $data ) {
            $field = $data['field'];
            foreach ( $data['actions'] as $action_name ) {
                $action_checks[ $action_name ][] = array(
                    'nonce' => $nonce_action,
                    'field' => $field,
                );
            }
        }

        if ( empty( $action_checks[ $request_action ] ) ) {
            return $message;
        }

        $failed_checks = array();

        foreach ( $action_checks[ $request_action ] as $check ) {
            $result = check_ajax_referer( $check['nonce'], $check['field'], false );

            if ( false === $result ) {
                $failed_checks[] = $check;
                continue;
            }

            return $message;
        }

        if ( empty( $failed_checks ) ) {
            return $message;
        }

        $failed_check = $failed_checks[0];

        $this->log_security_event(
            self::EVENT_PERMISSION_VIOLATION,
            'Nonce verification failed',
            self::RISK_MEDIUM,
            array(
                'nonce_action'   => $failed_check['nonce'],
                'nonce_field'    => $failed_check['field'],
                'request_action' => $request_action,
                'nonce_provided' => ! empty( $_REQUEST[ $failed_check['field'] ] ),
            )
        );

        return $message;
    }

    /**
     * Trigger security alert
     */
    private function trigger_security_alert( $event_type, $description, $risk_level, $event_data ) {
        $admin_email = get_option( 'admin_email' );
        $site_name = get_bloginfo( 'name' );
        
        $risk_names = array(
            self::RISK_LOW => 'Low',
            self::RISK_MEDIUM => 'Medium',
            self::RISK_HIGH => 'High',
            self::RISK_CRITICAL => 'Critical'
        );
        
        $subject = sprintf(
            '[%s] Security Alert: %s Risk Event',
            $site_name,
            $risk_names[ $risk_level ] ?? 'Unknown'
        );
        
        $message = sprintf(
            "A %s risk security event has been detected:\n\n" .
            "Event Type: %s\n" .
            "Description: %s\n" .
            "IP Address: %s\n" .
            "Time: %s\n" .
            "User Agent: %s\n\n" .
            "Please review the security audit logs for more details.",
            $risk_names[ $risk_level ] ?? 'unknown',
            $event_type,
            $description,
            $this->get_client_ip(),
            current_time( 'mysql' ),
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        );
        
        $mail_sent = wp_mail( $admin_email, $subject, $message );
        if ( ! $mail_sent ) {
            error_log( 'TTS Security Audit: Failed to send security alert email to ' . $admin_email );
        }
    }

    /**
     * Auto-block IP for critical events
     */
    private function auto_block_ip( $ip, $event_type ) {
        $blocked_ips = get_option( 'tts_blocked_ips', array() );
        
        if ( ! in_array( $ip, $blocked_ips ) ) {
            $blocked_ips[] = $ip;
            update_option( 'tts_blocked_ips', $blocked_ips );
            
            $this->log_security_event(
                self::EVENT_SUSPICIOUS_ACTIVITY,
                sprintf( 'IP %s automatically blocked due to %s', $ip, $event_type ),
                self::RISK_CRITICAL,
                array(
                    'blocked_ip' => $ip,
                    'trigger_event' => $event_type,
                    'auto_blocked' => true
                )
            );
        }
    }

    /**
     * Get security audit logs
     */
    public function get_audit_logs( $limit = 100, $offset = 0, $filters = array() ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->audit_table;
        $where_clauses = array( '1=1' );
        $where_values = array();
        
        // Apply filters
        if ( ! empty( $filters['event_type'] ) ) {
            $where_clauses[] = 'event_type = %s';
            $where_values[] = $filters['event_type'];
        }
        
        if ( ! empty( $filters['risk_level'] ) ) {
            $where_clauses[] = 'risk_level >= %d';
            $where_values[] = $filters['risk_level'];
        }
        
        if ( ! empty( $filters['ip_address'] ) ) {
            $where_clauses[] = 'ip_address = %s';
            $where_values[] = $filters['ip_address'];
        }
        
        if ( ! empty( $filters['date_from'] ) ) {
            $where_clauses[] = 'timestamp >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if ( ! empty( $filters['date_to'] ) ) {
            $where_clauses[] = 'timestamp <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        $where_clause = implode( ' AND ', $where_clauses );
        
        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY timestamp DESC LIMIT %d OFFSET %d";
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        if ( ! empty( $where_values ) ) {
            $query = $wpdb->prepare( $query, $where_values );
        }
        
        return $wpdb->get_results( $query );
    }

    /**
     * Get security statistics
     */
    public function get_security_stats( $days = 7 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->audit_table;
        $cutoff_date = date( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
        
        // Total events
        $total_events = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE timestamp >= %s",
                $cutoff_date
            )
        );
        
        // Events by risk level
        $risk_stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT risk_level, COUNT(*) as count 
                 FROM $table_name 
                 WHERE timestamp >= %s 
                 GROUP BY risk_level",
                $cutoff_date
            )
        );
        
        // Events by type
        $type_stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event_type, COUNT(*) as count 
                 FROM $table_name 
                 WHERE timestamp >= %s 
                 GROUP BY event_type 
                 ORDER BY count DESC 
                 LIMIT 10",
                $cutoff_date
            )
        );
        
        // Top suspicious IPs
        $suspicious_ips = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ip_address, COUNT(*) as event_count, 
                        MAX(risk_level) as max_risk,
                        MAX(timestamp) as last_activity
                 FROM $table_name 
                 WHERE timestamp >= %s AND risk_level >= %d
                 GROUP BY ip_address 
                 ORDER BY event_count DESC, max_risk DESC 
                 LIMIT 10",
                $cutoff_date,
                self::RISK_MEDIUM
            )
        );
        
        // Security health score
        $high_risk_events = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                 WHERE timestamp >= %s AND risk_level >= %d",
                $cutoff_date,
                self::RISK_HIGH
            )
        );
        
        $health_score = max( 0, 100 - ( $high_risk_events * 5 ) );
        
        return array(
            'period_days' => $days,
            'total_events' => (int) $total_events,
            'events_per_day' => round( $total_events / max( 1, $days ), 2 ),
            'health_score' => min( 100, $health_score ),
            'risk_distribution' => $risk_stats,
            'event_types' => $type_stats,
            'suspicious_ips' => $suspicious_ips,
            'blocked_ips_count' => count( get_option( 'tts_blocked_ips', array() ) )
        );
    }

    /**
     * AJAX: Get security audit logs
     */
    public function ajax_get_security_audit() {
        check_ajax_referer( 'tts_security_audit_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
        }

        $page = max( 1, intval( $_POST['page'] ?? 1 ) );
        $per_page = min( 100, max( 10, intval( $_POST['per_page'] ?? 50 ) ) );
        $offset = ( $page - 1 ) * $per_page;
        
        $filters = array();
        if ( ! empty( $_POST['event_type'] ) ) {
            $filters['event_type'] = sanitize_text_field( $_POST['event_type'] );
        }
        if ( ! empty( $_POST['risk_level'] ) ) {
            $filters['risk_level'] = intval( $_POST['risk_level'] );
        }
        if ( ! empty( $_POST['ip_address'] ) ) {
            $filters['ip_address'] = sanitize_text_field( $_POST['ip_address'] );
        }
        
        $logs = $this->get_audit_logs( $per_page, $offset, $filters );
        
        wp_send_json_success( array(
            'logs' => $logs,
            'page' => $page,
            'per_page' => $per_page
        ));
    }

    /**
     * AJAX: Get security statistics
     */
    public function ajax_get_security_stats() {
        check_ajax_referer( 'tts_security_audit_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
        }

        $days = max( 1, min( 90, intval( $_POST['days'] ?? 7 ) ) );
        $stats = $this->get_security_stats( $days );
        
        wp_send_json_success( $stats );
    }

    /**
     * AJAX: Clear audit logs
     */
    public function ajax_clear_audit_logs() {
        check_ajax_referer( 'tts_security_audit_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . $this->audit_table;
        
        $days_to_keep = max( 1, intval( $_POST['days_to_keep'] ?? 30 ) );
        $cutoff_date = date( 'Y-m-d H:i:s', time() - ( $days_to_keep * DAY_IN_SECONDS ) );
        
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE timestamp < %s",
                $cutoff_date
            )
        );
        
        wp_send_json_success( array(
            'message' => sprintf( __( 'Deleted %d old audit log entries', 'fp-publisher' ), $deleted ),
            'deleted_count' => $deleted
        ));
    }

    /**
     * Cleanup old logs
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->audit_table;
        
        // Delete logs older than 90 days
        $cutoff_date = date( 'Y-m-d H:i:s', time() - ( 90 * DAY_IN_SECONDS ) );
        
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE timestamp < %s",
                $cutoff_date
            )
        );
        
        // Ensure table doesn't exceed max entries
        $total_entries = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
        
        if ( $total_entries > $this->max_log_entries ) {
            $excess = $total_entries - $this->max_log_entries;
            $wpdb->query(
                "DELETE FROM $table_name ORDER BY timestamp ASC LIMIT $excess"
            );
        }
        
        if ( $deleted > 0 ) {
            TTS_Logger::log( "Security audit cleanup: Deleted $deleted old log entries" );
        }
    }

    /**
     * Log user registration
     */
    public function log_user_registration( $user_id ) {
        $user = get_user_by( 'id', $user_id );

        $this->log_security_event(
            self::EVENT_DATA_MODIFICATION,
            sprintf( 'New user registered: %s', $user->user_login ),
            self::RISK_MEDIUM,
            array(
                'new_user_id' => $user_id,
                'user_email' => $user->user_email,
                'user_roles' => $user->roles
            )
        );
    }

    /**
     * Handle plugin activation by ensuring the audit table exists.
     */
    public static function activate() {
        $instance = new self();
        $instance->create_audit_table();
    }
}
