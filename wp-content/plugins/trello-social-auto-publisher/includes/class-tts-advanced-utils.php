<?php
/**
 * Advanced utilities for Trello Social Auto Publisher.
 * Export/Import, Batch Operations, and System Maintenance.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Advanced utilities and batch operations.
 */
class TTS_Advanced_Utils {

    const SECRET_PLACEHOLDER = '[REDACTED]';
    
    /**
     * Export plugin settings and data.
     *
     * @param array $options Export options.
     * @return array Export data.
     */
    public static function export_data( $options = array() ) {
        $defaults = array(
            'settings' => true,
            'social_apps' => true,
            'clients' => true,
            'posts' => false,
            'logs' => false,
            'analytics' => false,
            'include_secrets' => false
        );
        
        $options = wp_parse_args( $options, $defaults );
        $export_data = array(
            'version' => '1.0.0',
            'timestamp' => current_time( 'mysql' ),
            'site_url' => get_site_url(),
            'data' => array()
        );
        
        try {
            // Export general settings
            if ( $options['settings'] ) {
                $export_data['data']['settings'] = get_option( 'tts_settings', array() );
            }
            
            // Export social media app configurations
            if ( $options['social_apps'] ) {
                $social_apps = get_option( 'tts_social_apps', array() );
                $redacted_fields = array();

                foreach ( $social_apps as $platform => &$config ) {
                    if ( ! is_array( $config ) ) {
                        continue;
                    }

                    foreach ( $config as $field_key => $field_value ) {
                        if ( ! self::is_secret_field( $field_key ) ) {
                            continue;
                        }

                        if ( $options['include_secrets'] ) {
                            continue;
                        }

                        if ( ! empty( $field_value ) ) {
                            $redacted_fields[ $platform ][] = $field_key;
                        }

                        $config[ $field_key ] = self::SECRET_PLACEHOLDER;
                    }
                }
                unset( $config );

                $export_data['data']['social_apps'] = $social_apps;

                $export_data['data']['social_apps_meta'] = array(
                    'secrets_included' => (bool) $options['include_secrets'],
                );

                if ( ! empty( $redacted_fields ) ) {
                    $export_data['data']['social_apps_meta']['redacted_fields'] = $redacted_fields;
                }
            }
            
            // Export clients
            if ( $options['clients'] ) {
                $clients = get_posts( array(
                    'post_type' => 'tts_client',
                    'posts_per_page' => -1,
                    'post_status' => 'any'
                ) );

                $clients_data = array();
                $clients_meta_notes = array();

                foreach ( $clients as $client ) {
                    $client_meta        = get_post_meta( $client->ID );
                    $client_meta_export = $client_meta;

                    if ( ! $options['include_secrets'] ) {
                        foreach ( $client_meta_export as $meta_key => &$meta_values ) {
                            if ( ! self::is_sensitive_client_meta_key( $meta_key ) ) {
                                continue;
                            }

                            if ( ! empty( $meta_values ) ) {
                                $clients_meta_notes[ $client->ID ][] = $meta_key;
                            }

                            if ( is_array( $meta_values ) ) {
                                foreach ( $meta_values as &$meta_value ) {
                                    $meta_value = self::SECRET_PLACEHOLDER;
                                }
                                unset( $meta_value );
                            } else {
                                $meta_values = self::SECRET_PLACEHOLDER;
                            }
                        }
                        unset( $meta_values );
                    }

                    $clients_data[] = array(
                        'title' => $client->post_title,
                        'content' => $client->post_content,
                        'status' => $client->post_status,
                        'meta' => $client_meta_export,
                        'date_created' => $client->post_date
                    );
                }

                $export_data['data']['clients'] = $clients_data;

                $export_data['data']['clients_meta'] = array(
                    'secrets_included' => (bool) $options['include_secrets'],
                );

                if ( ! empty( $clients_meta_notes ) ) {
                    foreach ( $clients_meta_notes as $client_id => $meta_keys ) {
                        $clients_meta_notes[ $client_id ] = array_values( array_unique( $meta_keys ) );
                    }

                    $export_data['data']['clients_meta']['redacted_fields'] = $clients_meta_notes;
                }
            }
            
            // Export social posts (if requested)
            if ( $options['posts'] ) {
                $posts = get_posts( array(
                    'post_type' => 'tts_social_post',
                    'posts_per_page' => 100, // Limit for performance
                    'post_status' => 'any'
                ) );
                
                $posts_data = array();
                foreach ( $posts as $post ) {
                    $posts_data[] = array(
                        'title' => $post->post_title,
                        'content' => $post->post_content,
                        'status' => $post->post_status,
                        'meta' => get_post_meta( $post->ID ),
                        'date_created' => $post->post_date
                    );
                }
                $export_data['data']['posts'] = $posts_data;
            }
            
            // Export logs (recent only)
            if ( $options['logs'] ) {
                global $wpdb;
                $logs = $wpdb->get_results( $wpdb->prepare( "
                    SELECT post_id, channel AS event_type, status, message, created_at
                    FROM {$wpdb->prefix}tts_logs
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                    ORDER BY created_at DESC
                    LIMIT 500
                ", 30 ), ARRAY_A );
                
                $export_data['data']['logs'] = $logs;
            }
            
            // Export analytics summary
            if ( $options['analytics'] ) {
                $export_data['data']['analytics'] = array(
                    'performance_metrics' => TTS_Performance::get_performance_metrics(),
                    'dashboard_stats' => TTS_Performance::get_cached_dashboard_stats(),
                    'export_date' => current_time( 'mysql' )
                );
            }
            
            return array(
                'success' => true,
                'data' => $export_data,
                'file_size' => strlen( json_encode( $export_data ) )
            );
            
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'error' => 'Export failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Import plugin settings and data.
     *
     * @param array $import_data Import data.
     * @param array $options Import options.
     * @return array Import result.
     */
    public static function import_data( $import_data, $options = array() ) {
        $defaults = array(
            'overwrite_settings' => false,
            'overwrite_social_apps' => false,
            'import_clients' => true,
            'import_posts' => false,
            'validate_data' => true
        );
        
        $options = wp_parse_args( $options, $defaults );
        $import_log = array();
        
        try {
            // Validate import data
            if ( $options['validate_data'] ) {
                $validation = self::validate_import_data( $import_data );
                if ( ! $validation['valid'] ) {
                    return array(
                        'success' => false,
                        'error' => 'Invalid import data: ' . $validation['error']
                    );
                }
            }
            
            $data = $import_data['data'];
            
            // Import settings
            if ( isset( $data['settings'] ) && ( $options['overwrite_settings'] || ! get_option( 'tts_settings' ) ) ) {
                update_option( 'tts_settings', $data['settings'] );
                $import_log[] = 'Settings imported successfully';
            }
            
            // Import social apps (excluding secrets)
            if ( isset( $data['social_apps'] ) && ( $options['overwrite_social_apps'] || ! get_option( 'tts_social_apps' ) ) ) {
                $current_apps = get_option( 'tts_social_apps', array() );
                $redacted_notices = array();

                foreach ( $data['social_apps'] as $platform => $config ) {
                    if ( ! is_array( $config ) ) {
                        continue;
                    }

                    $existing_config = isset( $current_apps[ $platform ] ) && is_array( $current_apps[ $platform ] ) ? $current_apps[ $platform ] : array();

                    foreach ( $config as $field_key => $field_value ) {
                        if ( ! self::is_secret_field( $field_key ) ) {
                            continue;
                        }

                        if ( self::SECRET_PLACEHOLDER === $field_value ) {
                            if ( isset( $existing_config[ $field_key ] ) && '' !== $existing_config[ $field_key ] ) {
                                $config[ $field_key ] = $existing_config[ $field_key ];
                            } else {
                                unset( $config[ $field_key ] );
                                $redacted_notices[ $platform ][] = $field_key;
                            }
                        }
                    }

                    $current_apps[ $platform ] = array_merge( $existing_config, $config );
                }

                update_option( 'tts_social_apps', $current_apps );
                $import_log[] = 'Social media configurations imported';

                if ( ! empty( $redacted_notices ) ) {
                    foreach ( $redacted_notices as $platform => $fields ) {
                        $fields = array_unique( $fields );
                        $field_labels = array_map( array( __CLASS__, 'format_secret_field_label' ), $fields );
                        $import_log[] = sprintf(
                            '%s secrets were not imported. Please re-enter: %s.',
                            ucfirst( $platform ),
                            implode( ', ', $field_labels )
                        );
                    }
                }
            }
            
            // Import clients
            if ( isset( $data['clients'] ) && $options['import_clients'] ) {
                $imported_clients = 0;
                foreach ( $data['clients'] as $client_data ) {
                    $client_id = wp_insert_post( array(
                        'post_title' => sanitize_text_field( $client_data['title'] ),
                        'post_content' => wp_kses_post( $client_data['content'] ),
                        'post_type' => 'tts_client',
                        'post_status' => 'publish'
                    ) );

                    if ( $client_id && ! is_wp_error( $client_id ) ) {
                        // Import meta data
                        if ( isset( $client_data['meta'] ) ) {
                            foreach ( $client_data['meta'] as $key => $values ) {
                                $meta_key = self::sanitize_import_meta_key( $key );

                                if ( null === $meta_key ) {
                                    continue;
                                }

                                $values = is_array( $values ) ? $values : array( $values );

                                foreach ( $values as $value ) {
                                    $meta_value = self::sanitize_import_meta_value( $value );

                                    if ( null === $meta_value ) {
                                        continue;
                                    }

                                    add_post_meta( $client_id, $meta_key, $meta_value );
                                }
                            }
                        }
                        $imported_clients++;
                    }
                }
                $import_log[] = "Imported {$imported_clients} clients";
            }
            
            // Import posts (if requested)
            if ( isset( $data['posts'] ) && $options['import_posts'] ) {
                $imported_posts = 0;
                foreach ( $data['posts'] as $post_data ) {
                    $post_id = wp_insert_post( array(
                        'post_title' => sanitize_text_field( $post_data['title'] ),
                        'post_content' => wp_kses_post( $post_data['content'] ),
                        'post_type' => 'tts_social_post',
                        'post_status' => 'draft' // Always import as draft for safety
                    ) );

                    if ( $post_id && ! is_wp_error( $post_id ) ) {
                        // Import meta data
                        if ( isset( $post_data['meta'] ) ) {
                            foreach ( $post_data['meta'] as $key => $values ) {
                                $meta_key = self::sanitize_import_meta_key( $key );

                                if ( null === $meta_key ) {
                                    continue;
                                }

                                $values = is_array( $values ) ? $values : array( $values );

                                foreach ( $values as $value ) {
                                    $meta_value = self::sanitize_import_meta_value( $value );

                                    if ( null === $meta_value ) {
                                        continue;
                                    }

                                    add_post_meta( $post_id, $meta_key, $meta_value );
                                }
                            }
                        }
                        $imported_posts++;
                    }
                }
                $import_log[] = "Imported {$imported_posts} social posts";
            }
            
            // Clear caches after import
            TTS_Performance::clear_all_performance_cache();
            wp_cache_flush();
            
            return array(
                'success' => true,
                'log' => $import_log,
                'timestamp' => current_time( 'mysql' )
            );
            
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage(),
                'log' => $import_log
            );
        }
    }
    
    /**
     * Validate import data structure.
     *
     * @param array $import_data Import data to validate.
     * @return array Validation result.
     */
    private static function validate_import_data( $import_data ) {
        if ( ! is_array( $import_data ) ) {
            return array( 'valid' => false, 'error' => 'Invalid data format' );
        }
        
        if ( ! isset( $import_data['version'] ) || ! isset( $import_data['data'] ) ) {
            return array( 'valid' => false, 'error' => 'Missing required fields' );
        }
        
        if ( ! is_array( $import_data['data'] ) ) {
            return array( 'valid' => false, 'error' => 'Invalid data structure' );
        }
        
        // Check version compatibility
        if ( version_compare( $import_data['version'], '1.0.0', '<' ) ) {
            return array( 'valid' => false, 'error' => 'Incompatible version' );
        }
        
        return array( 'valid' => true );
    }

    /**
     * Sanitize an imported meta key.
     *
     * @param mixed $meta_key Raw meta key from the import file.
     * @return string|null Sanitized meta key or null when the key is not permitted.
     */
    private static function sanitize_import_meta_key( $meta_key ) {
        if ( ! is_string( $meta_key ) || '' === $meta_key ) {
            return null;
        }

        $allowed_prefixes = self::get_allowed_import_meta_prefixes();

        if ( ! empty( $allowed_prefixes ) ) {
            $matches_prefix = false;

            foreach ( $allowed_prefixes as $prefix ) {
                if ( 0 === strpos( $meta_key, $prefix ) ) {
                    $matches_prefix = true;
                    break;
                }
            }

            if ( ! $matches_prefix ) {
                return null;
            }
        }

        $sanitized_key = sanitize_key( $meta_key );

        if ( '' === $sanitized_key ) {
            return null;
        }

        return $sanitized_key;
    }

    /**
     * Retrieve the list of allowed meta key prefixes for imports.
     *
     * @return array<int, string> Allowed prefixes. An empty array allows all keys.
     */
    private static function get_allowed_import_meta_prefixes() {
        /**
         * Filter the allowed meta key prefixes when importing data.
         *
         * Returning an empty array allows all meta keys that sanitize correctly.
         *
         * @param array<int, string> $prefixes Allowed prefixes.
         */
        $prefixes = apply_filters( 'tts_import_allowed_meta_prefixes', array() );

        if ( ! is_array( $prefixes ) ) {
            return array();
        }

        $prefixes = array_filter(
            array_map(
                function ( $prefix ) {
                    return is_string( $prefix ) ? $prefix : '';
                },
                $prefixes
            ),
            function ( $prefix ) {
                return '' !== $prefix;
            }
        );

        return array_values( $prefixes );
    }

    /**
     * Sanitize a meta value before storing it in the database.
     *
     * @param mixed $value Meta value from the import file.
     * @return mixed|null Sanitized value or null when the value should be skipped.
     */
    private static function sanitize_import_meta_value( $value ) {
        if ( is_object( $value ) ) {
            $value = (array) $value;
        }

        if ( is_array( $value ) ) {
            $sanitized = array();

            foreach ( $value as $key => $item ) {
                $clean = self::sanitize_import_meta_value( $item );

                if ( null === $clean ) {
                    continue;
                }

                $sanitized[ $key ] = $clean;
            }

            return $sanitized;
        }

        if ( null === $value ) {
            return null;
        }

        if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
            return $value;
        }

        if ( is_string( $value ) ) {
            if ( self::SECRET_PLACEHOLDER === $value ) {
                return null;
            }

            $value = wp_kses_post( $value );

            return trim( $value );
        }

        return null;
    }

    /**
     * Determine if a configuration field contains a secret value.
     *
     * @param string $field_key Configuration field key.
     * @return bool Whether the key is considered sensitive.
     */
    private static function is_secret_field( $field_key ) {
        if ( ! is_string( $field_key ) ) {
            return false;
        }

        $indicators = array( 'secret', 'token', 'password' );

        foreach ( $indicators as $indicator ) {
            if ( false !== stripos( $field_key, $indicator ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a client meta key contains sensitive information.
     *
     * @param string $meta_key Meta key to evaluate.
     * @return bool Whether the key should be treated as secret.
     */
    private static function is_sensitive_client_meta_key( $meta_key ) {
        if ( ! is_string( $meta_key ) ) {
            return false;
        }

        $sensitive_keys = array(
            '_tts_trello_key',
            '_tts_trello_token',
            '_tts_trello_secret',
            '_tts_fb_token',
            '_tts_ig_token',
            '_tts_yt_token',
            '_tts_tt_token',
        );

        if ( in_array( $meta_key, $sensitive_keys, true ) ) {
            return true;
        }

        if ( self::is_secret_field( $meta_key ) ) {
            return true;
        }

        if ( preg_match( '/(?:^|[_-])(api_)?key$/i', $meta_key ) ) {
            return true;
        }

        if ( 0 === strpos( $meta_key, '_tts_' ) && false !== stripos( $meta_key, 'access' ) && false !== stripos( $meta_key, 'key' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Convert a secret field key into a human readable label.
     *
     * @param string $field_key Configuration field key.
     * @return string Human readable label.
     */
    private static function format_secret_field_label( $field_key ) {
        if ( ! is_string( $field_key ) ) {
            return '';
        }

        $label = str_replace( array( '_', '-' ), ' ', $field_key );
        return ucwords( $label );
    }
    
    /**
     * Batch delete posts with safety checks.
     *
     * @param array $post_ids Array of post IDs to delete.
     * @param array $options Deletion options.
     * @return array Deletion result.
     */
    public static function batch_delete_posts( $post_ids, $options = array() ) {
        $defaults = array(
            'force_delete' => false,
            'max_batch_size' => 50,
            'verify_permissions' => true
        );
        
        $options = wp_parse_args( $options, $defaults );
        
        // Safety check: limit batch size
        if ( count( $post_ids ) > $options['max_batch_size'] ) {
            return array(
                'success' => false,
                'error' => 'Batch size exceeds maximum allowed (' . $options['max_batch_size'] . ')'
            );
        }
        
        // Verify permissions
        if ( $options['verify_permissions'] && ! current_user_can( 'delete_posts' ) ) {
            return array(
                'success' => false,
                'error' => 'Insufficient permissions'
            );
        }
        
        $deleted_count = 0;
        $failed_deletions = array();
        
        foreach ( $post_ids as $post_id ) {
            $post_id = intval( $post_id );
            
            // Verify post exists and is correct type
            $post = get_post( $post_id );
            if ( ! $post || $post->post_type !== 'tts_social_post' ) {
                $failed_deletions[] = $post_id . ' (invalid post)';
                continue;
            }
            
            // Delete post
            $deleted = wp_delete_post( $post_id, $options['force_delete'] );
            if ( $deleted ) {
                $deleted_count++;
            } else {
                $failed_deletions[] = $post_id . ' (deletion failed)';
            }
        }
        
        return array(
            'success' => true,
            'deleted_count' => $deleted_count,
            'failed_deletions' => $failed_deletions,
            'total_requested' => count( $post_ids )
        );
    }
    
    /**
     * Batch approve/revoke posts.
     *
     * @param array  $post_ids Array of post IDs.
     * @param string $action Action: 'approve' or 'revoke'.
     * @return array Operation result.
     */
    public static function batch_approve_posts( $post_ids, $action ) {
        if ( ! in_array( $action, array( 'approve', 'revoke' ) ) ) {
            return array(
                'success' => false,
                'error' => 'Invalid action'
            );
        }
        
        if ( ! current_user_can( 'edit_posts' ) ) {
            return array(
                'success' => false,
                'error' => 'Insufficient permissions'
            );
        }
        
        $processed_count = 0;
        $failed_operations = array();
        $meta_value = $action === 'approve' ? 'approved' : 'pending';
        
        foreach ( $post_ids as $post_id ) {
            $post_id = intval( $post_id );
            
            // Verify post exists
            $post = get_post( $post_id );
            if ( ! $post || $post->post_type !== 'tts_social_post' ) {
                $failed_operations[] = $post_id . ' (invalid post)';
                continue;
            }
            
            // Update approval status
            $updated = update_post_meta( $post_id, '_tts_approval_status', $meta_value );
            if ( $updated !== false ) {
                $processed_count++;
                
                // Log the action
                tts_log_event( 
                    $post_id, 
                    'approval', 
                    'success', 
                    ucfirst( $action ) . ' operation completed', 
                    get_current_user_id() 
                );
            } else {
                $failed_operations[] = $post_id . ' (update failed)';
            }
        }
        
        return array(
            'success' => true,
            'processed_count' => $processed_count,
            'failed_operations' => $failed_operations,
            'total_requested' => count( $post_ids ),
            'action' => $action
        );
    }
    
    /**
     * System maintenance and cleanup.
     *
     * @param array $tasks Tasks to perform.
     * @return array Maintenance result.
     */
    public static function system_maintenance( $tasks = array() ) {
        $defaults = array(
            'optimize_database' => true,
            'clear_cache' => true,
            'cleanup_logs' => true,
            'update_statistics' => true,
            'check_health' => true
        );
        
        $tasks = wp_parse_args( $tasks, $defaults );
        $maintenance_log = array();
        
        // Database optimization
        if ( $tasks['optimize_database'] ) {
            $optimization_result = TTS_Performance::optimize_database_advanced();
            $maintenance_log[] = 'Database optimization: ' . 
                ( $optimization_result['success'] ? 'completed' : 'failed' );
        }
        
        // Clear all caches
        if ( $tasks['clear_cache'] ) {
            TTS_Performance::clear_all_performance_cache();
            wp_cache_flush();
            $maintenance_log[] = 'Cache cleared successfully';
        }
        
        // Cleanup old logs
        if ( $tasks['cleanup_logs'] ) {
            global $wpdb;
            $old_logs_deleted = $wpdb->query( $wpdb->prepare( "
                DELETE FROM {$wpdb->prefix}tts_logs
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
            ", 90 ) );
            $maintenance_log[] = "Cleaned up {$old_logs_deleted} old log entries";
        }
        
        // Update statistics
        if ( $tasks['update_statistics'] ) {
            // Force regenerate dashboard stats
            TTS_Performance::clear_dashboard_cache();
            TTS_Performance::get_cached_dashboard_stats();
            $maintenance_log[] = 'Statistics updated';
        }
        
        // Health check
        if ( $tasks['check_health'] ) {
            $health_score = TTS_Performance::get_system_health_score();
            $maintenance_log[] = 'System health score: ' . $health_score['score'] . '/100';
        }
        
        return array(
            'success' => true,
            'log' => $maintenance_log,
            'timestamp' => current_time( 'mysql' )
        );
    }
    
    /**
     * Generate system report.
     *
     * @return array System report data.
     */
    public static function generate_system_report() {
        return array(
            'plugin_info' => array(
                'version' => '1.0.0',
                'active_since' => get_option( 'tts_first_activation', 'Unknown' ),
                'last_updated' => current_time( 'mysql' )
            ),
            'performance' => TTS_Performance::get_performance_metrics(),
            'health' => TTS_Performance::get_system_health_score(),
            'statistics' => TTS_Performance::get_cached_dashboard_stats(),
            'server_info' => array(
                'php_version' => PHP_VERSION,
                'wp_version' => get_bloginfo( 'version' ),
                'mysql_version' => self::get_mysql_version(),
                'memory_limit' => ini_get( 'memory_limit' ),
                'max_execution_time' => ini_get( 'max_execution_time' )
            ),
            'configuration' => array(
                'social_platforms' => self::get_configured_platforms(),
                'active_clients' => self::count_active_clients(),
                'total_posts' => wp_count_posts( 'tts_social_post' ),
                'scheduled_tasks' => self::get_scheduled_tasks_status()
            ),
            'generated_at' => current_time( 'mysql' )
        );
    }
    
    /**
     * Get configured social platforms.
     *
     * @return array Configured platforms.
     */
    private static function get_configured_platforms() {
        $social_apps = get_option( 'tts_social_apps', array() );
        $configured = array();
        
        foreach ( $social_apps as $platform => $config ) {
            if ( ! empty( $config ) ) {
                $configured[] = $platform;
            }
        }
        
        return $configured;
    }
    
    /**
     * Count active clients.
     *
     * @return int Number of active clients.
     */
    private static function count_active_clients() {
        return wp_count_posts( 'tts_client' )->publish;
    }
    
    /**
     * Get scheduled tasks status.
     *
     * @return array Scheduled tasks status.
     */
    private static function get_scheduled_tasks_status() {
        return array(
            'token_refresh' => wp_next_scheduled( 'tts_refresh_tokens' ) ? 'scheduled' : 'not_scheduled',
            'metrics_fetch' => wp_next_scheduled( 'tts_fetch_metrics' ) ? 'scheduled' : 'not_scheduled',
            'link_check' => wp_next_scheduled( 'tts_check_links' ) ? 'scheduled' : 'not_scheduled'
        );
    }
    
    /**
     * Get MySQL version (duplicate from Performance class for independence).
     *
     * @return string MySQL version.
     */
    private static function get_mysql_version() {
        global $wpdb;
        return $wpdb->get_var( "SELECT VERSION()" );
    }
}