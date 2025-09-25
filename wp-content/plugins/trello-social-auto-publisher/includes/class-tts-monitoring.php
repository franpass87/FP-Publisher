<?php
/**
 * Advanced monitoring and health check system for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Advanced monitoring and health check system.
 */
class TTS_Monitoring {
    
    /**
     * Initialize monitoring system.
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'schedule_health_checks' ) );
        add_action( 'tts_hourly_health_check', array( __CLASS__, 'perform_health_check' ) );
        add_action( 'tts_daily_system_report', array( __CLASS__, 'generate_daily_report' ) );
        add_action( 'admin_notices', array( __CLASS__, 'show_health_warnings' ) );
    }
    
    /**
     * Schedule health check tasks.
     */
    public static function schedule_health_checks() {
        // Hourly health checks
        if ( ! wp_next_scheduled( 'tts_hourly_health_check' ) ) {
            wp_schedule_event( time(), 'hourly', 'tts_hourly_health_check' );
        }
        
        // Daily system reports
        if ( ! wp_next_scheduled( 'tts_daily_system_report' ) ) {
            wp_schedule_event( strtotime( 'tomorrow 6:00 AM' ), 'daily', 'tts_daily_system_report' );
        }
    }
    
    /**
     * Perform comprehensive health check.
     */
    public static function perform_health_check() {
        $health_data = array(
            'timestamp' => current_time( 'mysql' ),
            'checks' => array(),
            'alerts' => array(),
            'score' => 100
        );
        
        // Database health check
        $db_health = self::check_database_health();
        $health_data['checks']['database'] = $db_health;
        if ( ! $db_health['status'] ) {
            $health_data['score'] -= 20;
            $health_data['alerts'][] = array(
                'type' => 'database',
                'severity' => 'high',
                'message' => $db_health['message']
            );
        }
        
        // API connections health check
        $api_health = self::check_api_connections();
        $health_data['checks']['api_connections'] = $api_health;
        if ( $api_health['failed_connections'] > 0 ) {
            $health_data['score'] -= 15;
            $health_data['alerts'][] = array(
                'type' => 'api',
                'severity' => 'medium',
                'message' => 'Some API connections are failing'
            );
        }
        
        // System resources check
        $resource_health = self::check_system_resources();
        $health_data['checks']['resources'] = $resource_health;
        if ( ! $resource_health['status'] ) {
            $health_data['score'] -= 25;
            $health_data['alerts'][] = array(
                'type' => 'resources',
                'severity' => 'high',
                'message' => $resource_health['message']
            );
        }
        
        // Scheduled tasks check
        $scheduler_health = self::check_scheduled_tasks();
        $health_data['checks']['scheduler'] = $scheduler_health;
        if ( ! $scheduler_health['status'] ) {
            $health_data['score'] -= 10;
            $health_data['alerts'][] = array(
                'type' => 'scheduler',
                'severity' => 'medium',
                'message' => 'Some scheduled tasks are not running'
            );
        }
        
        // Error rate check
        $error_health = self::check_error_rates();
        $health_data['checks']['errors'] = $error_health;
        if ( $error_health['error_rate'] > 10 ) {
            $health_data['score'] -= 20;
            $health_data['alerts'][] = array(
                'type' => 'errors',
                'severity' => 'high',
                'message' => 'High error rate detected: ' . $error_health['error_rate'] . '%'
            );
        }

        // Token health check
        $token_health = self::assess_token_health();
        $health_data['checks']['tokens'] = $token_health;
        if ( isset( $token_health['status'] ) && ! $token_health['status'] ) {
            $health_data['score'] -= 15;

            if ( ! empty( $token_health['alerts'] ) ) {
                $health_data['alerts'] = array_merge( $health_data['alerts'], $token_health['alerts'] );
            } else {
                $health_data['alerts'][] = array(
                    'type'     => 'tokens',
                    'severity' => 'medium',
                    'message'  => isset( $token_health['message'] ) ? $token_health['message'] : __( 'I token social richiedono attenzione.', 'fp-publisher' ),
                );
            }
        }

        // Store health data
        update_option( 'tts_last_health_check', $health_data );
        
        // Send alerts if necessary
        if ( count( $health_data['alerts'] ) > 0 ) {
            self::send_health_alerts( $health_data['alerts'] );
        }
        
        return $health_data;
    }
    
    /**
     * Check database health.
     */
    private static function check_database_health() {
        global $wpdb;

        $health = array(
            'status'  => true,
            'message' => 'Database is healthy',
            'details' => array(),
        );

        try {
            // Quick connectivity probe – bail early if the connection is unhealthy.
            $start_time    = microtime( true );
            $test_query    = $wpdb->get_var( 'SELECT 1' );
            $response_time = ( microtime( true ) - $start_time ) * 1000;

            $health['details']['response_time_ms'] = round( $response_time, 2 );

            if ( null === $test_query || $wpdb->last_error ) {
                $health['status']  = false;
                $health['message'] = $wpdb->last_error ? 'Database connectivity error: ' . $wpdb->last_error : 'Database did not respond as expected.';
                return $health;
            }

            if ( $response_time > 1000 ) { // 1 second
                $health['status']  = false;
                $health['message'] = 'Database response time is too slow: ' . round( $response_time, 2 ) . 'ms';
            }

            $supports_mysql_checks = ! property_exists( $wpdb, 'is_mysql' ) || $wpdb->is_mysql;

            if ( ! $supports_mysql_checks ) {
                $health['details']['engine']  = method_exists( $wpdb, 'db_version' ) ? $wpdb->db_version() : 'unknown';
                $health['details']['warnings'] = array(
                    __( 'Advanced MySQL specific health checks are not supported on this database engine.', 'fp-publisher' ),
                );

                return $health;
            }

            // Check table integrity.
            $tables       = array(
                $wpdb->prefix . 'tts_logs',
                $wpdb->posts,
                $wpdb->postmeta,
                $wpdb->options,
            );
            $table_status = array();
            $warnings     = array();

            foreach ( $tables as $table ) {
                $wpdb->flush();
                $check_result = $wpdb->get_row( "CHECK TABLE {$table}", ARRAY_A );

                if ( is_array( $check_result ) && isset( $check_result['Msg_text'] ) ) {
                    $table_status[ $table ] = $check_result['Msg_text'];

                    if ( 'OK' !== $check_result['Msg_text'] ) {
                        $health['status']  = false;
                        $health['message'] = "Table {$table} has issues: " . $check_result['Msg_text'];
                    }
                } elseif ( $wpdb->last_error ) {
                    $table_status[ $table ] = 'unavailable';
                    $warnings[]             = sprintf( __( 'Unable to run integrity check on table %1$s: %2$s', 'fp-publisher' ), $table, $wpdb->last_error );
                } else {
                    $table_status[ $table ] = 'unknown';
                }
            }

            if ( ! empty( $warnings ) ) {
                $health['details']['warnings'] = isset( $health['details']['warnings'] )
                    ? array_merge( $health['details']['warnings'], $warnings )
                    : $warnings;
            }

            $health['details']['table_status'] = $table_status;

            // Check for deadlocks or long-running queries. This query requires PROCESS privilege and may not be available.
            $wpdb->flush();
            $long_queries = $wpdb->get_results( '
                SELECT TIME, STATE, INFO
                FROM INFORMATION_SCHEMA.PROCESSLIST
                WHERE TIME > 30 AND COMMAND != "Sleep"
            ', ARRAY_A );

            if ( is_array( $long_queries ) && ! empty( $long_queries ) ) {
                $health['details']['long_running_queries'] = count( $long_queries );
                if ( count( $long_queries ) > 5 ) {
                    $health['status']  = false;
                    $health['message'] = 'Multiple long-running database queries detected';
                }
            } elseif ( $wpdb->last_error ) {
                $warning = __( 'Process list information is unavailable for the current database user.', 'fp-publisher' );
                $health['details']['warnings'] = isset( $health['details']['warnings'] )
                    ? array_merge( $health['details']['warnings'], array( $warning ) )
                    : array( $warning );
            }

        } catch ( Exception $e ) {
            $health['status']  = false;
            $health['message'] = 'Database error: ' . $e->getMessage();
        }

        return $health;
    }
    
    /**
     * Check API connections health.
     */
    private static function check_api_connections() {
        $social_apps = get_option( 'tts_social_apps', array() );
        $platforms = array( 'facebook', 'instagram', 'youtube', 'tiktok' );
        
        $health = array(
            'total_platforms' => count( $platforms ),
            'configured_platforms' => 0,
            'working_connections' => 0,
            'failed_connections' => 0,
            'platform_status' => array()
        );
        
        foreach ( $platforms as $platform ) {
            if ( ! empty( $social_apps[ $platform ] ) ) {
                $health['configured_platforms']++;
                
                // Test connection (simplified check)
                $test_result = self::test_platform_connection( $platform, $social_apps[ $platform ] );
                $health['platform_status'][ $platform ] = $test_result;
                
                if ( $test_result['success'] ) {
                    $health['working_connections']++;
                } else {
                    $health['failed_connections']++;
                }
            } else {
                $health['platform_status'][ $platform ] = array(
                    'success' => false,
                    'message' => 'Not configured'
                );
            }
        }
        
        return $health;
    }
    
    /**
     * Test platform connection.
     */
    private static function test_platform_connection( $platform, $credentials ) {
        // This is a simplified version - in production you'd test actual API endpoints
        $required_fields = array(
            'facebook' => array( 'app_id', 'app_secret' ),
            'instagram' => array( 'app_id', 'app_secret' ),
            'youtube' => array( 'client_id', 'client_secret' ),
            'tiktok' => array( 'client_key', 'client_secret' )
        );
        
        if ( ! isset( $required_fields[ $platform ] ) ) {
            return array( 'success' => false, 'message' => 'Unknown platform' );
        }
        
        foreach ( $required_fields[ $platform ] as $field ) {
            if ( empty( $credentials[ $field ] ) ) {
                return array( 'success' => false, 'message' => 'Missing credentials' );
            }
        }
        
        return array( 'success' => true, 'message' => 'Credentials configured' );
    }
    
    /**
     * Check system resources.
     */
    private static function check_system_resources() {
        $health = array(
            'status' => true,
            'message' => 'System resources are healthy',
            'details' => array()
        );
        
        // Memory usage check
        $memory_usage = memory_get_usage( true );
        $memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
        $memory_percent = ( $memory_usage / $memory_limit ) * 100;
        
        $health['details']['memory_usage_percent'] = round( $memory_percent, 1 );
        
        if ( $memory_percent > 90 ) {
            $health['status'] = false;
            $health['message'] = 'Memory usage is critically high: ' . round( $memory_percent, 1 ) . '%';
        } elseif ( $memory_percent > 80 ) {
            $health['message'] = 'Memory usage is high: ' . round( $memory_percent, 1 ) . '%';
        }
        
        // Disk space check (if possible)
        if ( function_exists( 'disk_free_space' ) ) {
            $upload_dir = wp_upload_dir();
            $free_space = disk_free_space( $upload_dir['basedir'] );
            $total_space = disk_total_space( $upload_dir['basedir'] );
            
            if ( $free_space && $total_space ) {
                $disk_usage_percent = ( ( $total_space - $free_space ) / $total_space ) * 100;
                $health['details']['disk_usage_percent'] = round( $disk_usage_percent, 1 );
                
                if ( $disk_usage_percent > 95 ) {
                    $health['status'] = false;
                    $health['message'] = 'Disk space is critically low';
                }
            }
        }
        
        // CPU load check (if available)
        if ( function_exists( 'sys_getloadavg' ) ) {
            $load = sys_getloadavg();
            $health['details']['cpu_load_1min'] = $load[0];
            
            if ( $load[0] > 5.0 ) {
                $health['status'] = false;
                $health['message'] = 'CPU load is very high: ' . $load[0];
            }
        }
        
        return $health;
    }
    
    /**
     * Check scheduled tasks.
     */
    private static function check_scheduled_tasks() {
        $required_tasks = array(
            'tts_refresh_tokens',
            'tts_fetch_metrics',
            'tts_check_links',
            'tts_hourly_health_check'
        );
        
        $health = array(
            'status' => true,
            'message' => 'All scheduled tasks are running',
            'details' => array()
        );
        
        foreach ( $required_tasks as $task ) {
            $next_run = wp_next_scheduled( $task );
            $health['details'][ $task ] = $next_run ? 'scheduled' : 'not_scheduled';
            
            if ( ! $next_run ) {
                $health['status'] = false;
                $health['message'] = 'Some scheduled tasks are not running';
            }
        }
        
        return $health;
    }
    
    /**
     * Check error rates.
     */
    private static function check_error_rates() {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'tts_logs';

        // Count total and error logs in last 24 hours
        $total_logs = $wpdb->get_var( "
            SELECT COUNT(*) FROM {$logs_table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        " );
        
        $error_logs = $wpdb->get_var( "
            SELECT COUNT(*) FROM {$logs_table}
            WHERE status = 'error'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        " );
        
        $error_rate = $total_logs > 0 ? ( $error_logs / $total_logs ) * 100 : 0;
        
        return array(
            'total_logs' => (int) $total_logs,
            'error_logs' => (int) $error_logs,
            'error_rate' => round( $error_rate, 2 )
        );
    }

    /**
     * Assess the health of stored social tokens across clients.
     *
     * @return array<string, mixed>
     */
    private static function assess_token_health() {
        $channels = array(
            'facebook'  => array(
                'label'       => 'Facebook',
                'token_meta'  => '_tts_fb_token',
                'expires_meta'=> '_tts_fb_token_expires_at',
            ),
            'instagram' => array(
                'label'       => 'Instagram',
                'token_meta'  => '_tts_ig_token',
                'expires_meta'=> '_tts_ig_token_expires_at',
            ),
            'youtube'   => array(
                'label'       => 'YouTube',
                'token_meta'  => '_tts_yt_token',
                'expires_meta'=> '_tts_yt_token_expires_at',
            ),
            'tiktok'    => array(
                'label'       => 'TikTok',
                'token_meta'  => '_tts_tt_token',
                'expires_meta'=> '_tts_tt_token_expires_at',
            ),
        );

        $clients = get_posts(
            array(
                'post_type'      => 'tts_client',
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'post_status'    => 'any',
            )
        );

        $summary = array(
            'status'          => true,
            'message'         => __( 'Tutti i token social risultano validi.', 'fp-publisher' ),
            'missing_tokens'  => array(),
            'expiring_tokens' => array(),
            'stale_tokens'    => array(),
            'alerts'          => array(),
        );

        if ( empty( $clients ) ) {
            return $summary;
        }

        $now                = time();
        $critical_threshold = DAY_IN_SECONDS;
        $warning_threshold  = 3 * DAY_IN_SECONDS;

        foreach ( $clients as $client_id ) {
            $client_name = get_the_title( $client_id );

            foreach ( $channels as $channel => $meta ) {
                $token_value = trim( (string) get_post_meta( $client_id, $meta['token_meta'], true ) );

                if ( '' === $token_value ) {
                    $summary['status'] = false;
                    $summary['missing_tokens'][] = array(
                        'client_id'     => $client_id,
                        'client_name'   => $client_name,
                        'channel'       => $channel,
                        'channel_label' => $meta['label'],
                    );
                    $summary['alerts'][] = array(
                        'type'     => 'tokens',
                        'severity' => 'high',
                        'message'  => sprintf( __( 'Token mancante per %1$s (%2$s).', 'fp-publisher' ), $client_name, $meta['label'] ),
                    );
                    continue;
                }

                $expires_at = isset( $meta['expires_meta'] ) ? (int) get_post_meta( $client_id, $meta['expires_meta'], true ) : 0;
                if ( $expires_at > 0 ) {
                    $time_remaining = $expires_at - $now;

                    if ( $time_remaining <= 0 ) {
                        $summary['status'] = false;
                        $summary['missing_tokens'][] = array(
                            'client_id'     => $client_id,
                            'client_name'   => $client_name,
                            'channel'       => $channel,
                            'channel_label' => $meta['label'],
                        );
                        $summary['alerts'][] = array(
                            'type'     => 'tokens',
                            'severity' => 'high',
                            'message'  => sprintf( __( 'Token scaduto per %1$s (%2$s).', 'fp-publisher' ), $client_name, $meta['label'] ),
                        );
                        continue;
                    }

                    if ( $time_remaining <= $critical_threshold ) {
                        $summary['status'] = false;
                        $summary['expiring_tokens'][] = array(
                            'client_id'     => $client_id,
                            'client_name'   => $client_name,
                            'channel'       => $channel,
                            'channel_label' => $meta['label'],
                            'seconds'       => $time_remaining,
                        );
                        $summary['alerts'][] = array(
                            'type'     => 'tokens',
                            'severity' => 'high',
                            'message'  => sprintf( __( 'Il token di %1$s (%2$s) scade entro 24 ore.', 'fp-publisher' ), $client_name, $meta['label'] ),
                        );
                    } elseif ( $time_remaining <= $warning_threshold ) {
                        $summary['status'] = false;
                        $summary['expiring_tokens'][] = array(
                            'client_id'     => $client_id,
                            'client_name'   => $client_name,
                            'channel'       => $channel,
                            'channel_label' => $meta['label'],
                            'seconds'       => $time_remaining,
                        );
                        $summary['alerts'][] = array(
                            'type'     => 'tokens',
                            'severity' => 'medium',
                            'message'  => sprintf( __( 'Il token di %1$s (%2$s) scadrà nei prossimi giorni.', 'fp-publisher' ), $client_name, $meta['label'] ),
                        );
                    }
                } else {
                    $summary['stale_tokens'][] = array(
                        'client_id'     => $client_id,
                        'client_name'   => $client_name,
                        'channel'       => $channel,
                        'channel_label' => $meta['label'],
                    );
                }
            }
        }

        if ( ! empty( $summary['missing_tokens'] ) ) {
            $summary['message'] = __( 'Sono presenti clienti senza token social configurati.', 'fp-publisher' );
        } elseif ( ! empty( $summary['expiring_tokens'] ) ) {
            $summary['message'] = __( 'Alcuni token stanno per scadere e richiedono rinnovo.', 'fp-publisher' );
        } elseif ( ! empty( $summary['stale_tokens'] ) ) {
            $summary['message'] = __( 'Alcuni token non indicano la data di scadenza: verifica le integrazioni.', 'fp-publisher' );
        }

        return $summary;
    }

    /**
     * Get actionable remediation suggestions based on the latest health snapshot.
     *
     * @param array $health_data Optional pre-fetched health data.
     * @return array<int, array<string, string>>
     */
    public static function get_remediation_suggestions( $health_data = array() ) {
        if ( empty( $health_data ) || ! is_array( $health_data ) ) {
            $health_data = get_option( 'tts_last_health_check', array() );
        }

        if ( empty( $health_data ) || ! is_array( $health_data ) ) {
            return array();
        }

        $suggestions = array();

        $add_suggestion = static function ( $title, $description, $link = '' ) use ( &$suggestions ) {
            $entry = array(
                'title'       => $title,
                'description' => $description,
            );

            if ( '' !== $link ) {
                $entry['link'] = $link;
            }

            $suggestions[] = $entry;
        };

        $admin_url = admin_url( 'admin.php' );

        $format_token_list = static function ( array $entries ) {
            if ( empty( $entries ) ) {
                return '';
            }

            $labels = array();
            foreach ( $entries as $entry ) {
                $client  = isset( $entry['client_name'] ) ? $entry['client_name'] : __( 'Cliente', 'fp-publisher' );
                $channel = isset( $entry['channel_label'] ) ? $entry['channel_label'] : ( isset( $entry['channel'] ) ? ucfirst( $entry['channel'] ) : __( 'Canale', 'fp-publisher' ) );
                $labels[] = $client . ' – ' . $channel;
            }

            $max = 3;
            if ( count( $labels ) > $max ) {
                $remaining = count( $labels ) - $max;
                $labels    = array_slice( $labels, 0, $max );
                $labels[]  = sprintf( _n( '+%d altro', '+%d altri', $remaining, 'fp-publisher' ), $remaining );
            }

            return implode( ', ', $labels );
        };

        if ( isset( $health_data['checks']['api_connections'] ) ) {
            $api_health = $health_data['checks']['api_connections'];

            if ( ! empty( $api_health['platform_status'] ) && is_array( $api_health['platform_status'] ) ) {
                foreach ( $api_health['platform_status'] as $platform => $status ) {
                    if ( empty( $status['success'] ) ) {
                        $message = isset( $status['message'] ) ? $status['message'] : __( 'Connection requires attention.', 'fp-publisher' );
                        $platform_name = ucfirst( $platform );
                        $add_suggestion(
                            sprintf( __( 'Re-authorize %s', 'fp-publisher' ), $platform_name ),
                            sprintf( __( '%1$s reports "%2$s". Apri la pagina Connessioni Social per aggiornare token e credenziali.', 'fp-publisher' ), $platform_name, $message ),
                            add_query_arg( array( 'page' => 'fp-publisher-social-connections' ), $admin_url )
                        );
                    }
                }
            }

            if ( isset( $api_health['failed_connections'] ) && $api_health['failed_connections'] > 0 ) {
                $add_suggestion(
                    __( 'Controlla le quote API', 'fp-publisher' ),
                    __( 'Una o più integrazioni stanno restituendo errori. Verifica i limiti di utilizzo dalle console dei vari provider.', 'fp-publisher' ),
                    add_query_arg( array( 'page' => 'fp-publisher-test-connections' ), $admin_url )
                );
            }
        }

        if ( isset( $health_data['checks']['scheduler'] ) ) {
            $scheduler = $health_data['checks']['scheduler'];
            if ( ! empty( $scheduler['details'] ) && is_array( $scheduler['details'] ) ) {
                foreach ( $scheduler['details'] as $hook => $status ) {
                    if ( 'not_scheduled' === $status ) {
                        $add_suggestion(
                            __( 'Ripristina le attività pianificate', 'fp-publisher' ),
                            sprintf( __( 'La routine "%s" non è pianificata. Esegui un controllo cron o salva di nuovo le impostazioni per riattivarla.', 'fp-publisher' ), $hook ),
                            add_query_arg( array( 'page' => 'fp-publisher-health' ), $admin_url )
                        );
                    }
                }
            }
        }

        if ( isset( $health_data['checks']['tokens'] ) ) {
            $token_health = $health_data['checks']['tokens'];

            if ( ! empty( $token_health['missing_tokens'] ) ) {
                $add_suggestion(
                    __( 'Completa i token mancanti', 'fp-publisher' ),
                    sprintf( __( 'Clienti da aggiornare: %s.', 'fp-publisher' ), $format_token_list( $token_health['missing_tokens'] ) ),
                    admin_url( 'edit.php?post_type=tts_client' )
                );
            }

            if ( ! empty( $token_health['expiring_tokens'] ) ) {
                $add_suggestion(
                    __( 'Rinnova i token in scadenza', 'fp-publisher' ),
                    sprintf( __( 'Token prossimi alla scadenza: %s.', 'fp-publisher' ), $format_token_list( $token_health['expiring_tokens'] ) ),
                    add_query_arg( array( 'page' => 'fp-publisher-social-connections' ), $admin_url )
                );
            }

            if ( ! empty( $token_health['stale_tokens'] ) ) {
                $add_suggestion(
                    __( 'Verifica la durata dei token', 'fp-publisher' ),
                    sprintf( __( 'Aggiorna le integrazioni per registrare la scadenza dei token: %s.', 'fp-publisher' ), $format_token_list( $token_health['stale_tokens'] ) ),
                    add_query_arg( array( 'page' => 'fp-publisher-test-connections' ), $admin_url )
                );
            }
        }

        global $wpdb;
        $logs_table = $wpdb->prefix . 'tts_logs';

        // Highlight webhook delivery issues.
        $webhook_errors = (int) $wpdb->get_var( "
            SELECT COUNT(*) FROM {$logs_table}
            WHERE channel = 'webhook'
              AND status = 'error'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        " );

        if ( $webhook_errors > 0 ) {
            $add_suggestion(
                __( 'Rinnova il webhook Trello', 'fp-publisher' ),
                __( 'Sono stati registrati errori webhook nelle ultime 24 ore. Conferma che il board Trello sia raggiungibile e rinnova il webhook.', 'fp-publisher' ),
                add_query_arg( array( 'page' => 'fp-publisher-health' ), $admin_url )
            );
        }

        // Detect API quota related messages.
        $quota_errors = (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) FROM {$logs_table}
            WHERE status = 'error'
              AND message LIKE %s
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ", '%quota%' ) );

        if ( $quota_errors > 0 ) {
            $add_suggestion(
                __( 'Pianifica un raffreddamento delle API', 'fp-publisher' ),
                __( 'Sono stati rilevati errori di quota API. Valuta di ridurre la frequenza delle pubblicazioni o di suddividerle su fasce orarie diverse.', 'fp-publisher' ),
                add_query_arg( array( 'page' => 'fp-publisher-frequency-status' ), $admin_url )
            );
        }

        // Highlight token refresh issues in logs.
        $token_errors = (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) FROM {$logs_table}
            WHERE status = 'error'
              AND channel IN ('facebook', 'instagram', 'youtube', 'tiktok')
              AND (message LIKE %s OR message LIKE %s)
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ", '%token%', '%auth%' ) );

        if ( $token_errors > 0 ) {
            $add_suggestion(
                __( 'Aggiorna i token scaduti', 'fp-publisher' ),
                __( 'Il registro mostra errori di autenticazione recenti. Accedi di nuovo ai canali social per generare token aggiornati.', 'fp-publisher' ),
                add_query_arg( array( 'page' => 'fp-publisher-social-connections' ), $admin_url )
            );
        }

        return $suggestions;
    }

    /**
     * Build an actionable health summary for key components.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_actionable_health_summary() {
        $health_data = get_option( 'tts_last_health_check', array() );
        $summary     = array();

        $raise_severity = static function ( array &$item, $candidate ) {
            $current_level = TTS_Monitoring::get_status_level_weight( isset( $item['status'] ) ? $item['status'] : 'ok' );
            $new_level     = TTS_Monitoring::get_status_level_weight( $candidate );

            if ( $new_level > $current_level ) {
                $item['status'] = $candidate;
            }
        };

        $token_overview = array(
            'key'         => 'tokens',
            'label'       => __( 'Token social', 'fp-publisher' ),
            'status'      => 'ok',
            'description' => __( 'Tutti i token risultano validi.', 'fp-publisher' ),
            'count'       => 0,
            'actions'     => array(),
        );

        if ( isset( $health_data['checks']['tokens'] ) && is_array( $health_data['checks']['tokens'] ) ) {
            $token_health          = $health_data['checks']['tokens'];
            $missing_tokens        = isset( $token_health['missing_tokens'] ) ? (array) $token_health['missing_tokens'] : array();
            $expiring_tokens       = isset( $token_health['expiring_tokens'] ) ? (array) $token_health['expiring_tokens'] : array();
            $stale_tokens          = isset( $token_health['stale_tokens'] ) ? (array) $token_health['stale_tokens'] : array();
            $affected_integrations = count( $missing_tokens ) + count( $expiring_tokens );

            if ( $affected_integrations > 0 ) {
                $token_overview['count']       = $affected_integrations;
                $token_overview['description'] = sprintf(
                    /* translators: %d: number of integrations without a valid token. */
                    __( '%d integrazioni richiedono un token valido o aggiornato.', 'fp-publisher' ),
                    (int) $affected_integrations
                );
            }

            if ( ! empty( $missing_tokens ) ) {
                $token_overview['affected_clients'] = array_map(
                    static function ( $entry ) {
                        return isset( $entry['client_name'] ) ? $entry['client_name'] : '';
                    },
                    $missing_tokens
                );

                $token_overview['actions'][] = array(
                    'description' => __( 'Completa le credenziali mancanti per i client interessati.', 'fp-publisher' ),
                    'url'         => admin_url( 'edit.php?post_type=tts_client' ),
                );

                $raise_severity( $token_overview, 'critical' );
            }

            if ( ! empty( $expiring_tokens ) ) {
                $token_overview['actions'][] = array(
                    'description' => __( 'Rinnova i token in scadenza dalla pagina Connessioni social.', 'fp-publisher' ),
                    'url'         => add_query_arg( array( 'page' => 'fp-publisher-social-connections' ), admin_url( 'admin.php' ) ),
                );

                $raise_severity( $token_overview, 'warning' );
            }

            if ( empty( $missing_tokens ) && empty( $expiring_tokens ) && ! empty( $stale_tokens ) ) {
                $token_overview['description'] = __( 'Aggiorna la data di scadenza per alcune integrazioni.', 'fp-publisher' );
                $token_overview['actions'][]   = array(
                    'description' => __( 'Apri la pagina di test connessioni per registrare le nuove scadenze.', 'fp-publisher' ),
                    'url'         => add_query_arg( array( 'page' => 'fp-publisher-test-connections' ), admin_url( 'admin.php' ) ),
                );

                $raise_severity( $token_overview, 'warning' );
            }
        }

        $summary[] = $token_overview;

        $scheduler_overview = array(
            'key'         => 'scheduler',
            'label'       => __( 'Attività pianificate', 'fp-publisher' ),
            'status'      => 'ok',
            'description' => __( 'Tutte le routine richieste risultano pianificate.', 'fp-publisher' ),
            'count'       => 0,
            'actions'     => array(),
        );

        if ( isset( $health_data['checks']['scheduler'] ) && is_array( $health_data['checks']['scheduler'] ) ) {
            $scheduler_checks = $health_data['checks']['scheduler'];

            if ( isset( $scheduler_checks['details'] ) && is_array( $scheduler_checks['details'] ) ) {
                $missing_hooks = array_keys(
                    array_filter(
                        $scheduler_checks['details'],
                        static function ( $status ) {
                            return 'scheduled' !== $status;
                        }
                    )
                );

                if ( ! empty( $missing_hooks ) ) {
                    $scheduler_overview['count']       = count( $missing_hooks );
                    $scheduler_overview['description'] = sprintf(
                        /* translators: %d: number of missing cron hooks. */
                        __( '%d routine non risultano pianificate.', 'fp-publisher' ),
                        (int) $scheduler_overview['count']
                    );

                    $scheduler_overview['details']   = $missing_hooks;
                    $scheduler_overview['actions'][] = array(
                        'description' => __( 'Apri la pagina Salute per riattivare le attività e controllare WP-Cron.', 'fp-publisher' ),
                        'url'         => add_query_arg( array( 'page' => 'fp-publisher-health' ), admin_url( 'admin.php' ) ),
                    );

                    $raise_severity( $scheduler_overview, 'critical' );
                }
            }
        }

        $summary[] = $scheduler_overview;

        global $wpdb;
        $logs_table   = $wpdb->prefix . 'tts_logs';
        $logs_enabled = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $logs_table ) );

        $webhook_overview = array(
            'key'         => 'webhook',
            'label'       => __( 'Consegna webhook', 'fp-publisher' ),
            'status'      => 'ok',
            'description' => __( 'Nessun errore webhook nelle ultime 24 ore.', 'fp-publisher' ),
            'count'       => 0,
            'actions'     => array(),
        );

        $webhook_errors = 0;
        if ( $logs_enabled ) {
            $webhook_errors = (int) $wpdb->get_var( "
                SELECT COUNT(*) FROM {$logs_table}
                WHERE channel = 'webhook'
                  AND status = 'error'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            " );
        }

        if ( $webhook_errors > 0 ) {
            $webhook_overview['count']       = $webhook_errors;
            $webhook_overview['description'] = sprintf(
                /* translators: %d: number of webhook errors. */
                __( '%d errori webhook rilevati nelle ultime 24 ore.', 'fp-publisher' ),
                (int) $webhook_errors
            );

            $webhook_overview['actions'][] = array(
                'description' => __( 'Verifica lo stato del webhook Trello e rinnova la sottoscrizione.', 'fp-publisher' ),
                'url'         => add_query_arg( array( 'page' => 'fp-publisher-health' ), admin_url( 'admin.php' ) ),
            );

            $raise_severity( $webhook_overview, $webhook_errors > 3 ? 'critical' : 'warning' );
        }

        $summary[] = $webhook_overview;

        $quota_overview = array(
            'key'         => 'api_quota',
            'label'       => __( 'Quote API', 'fp-publisher' ),
            'status'      => 'ok',
            'description' => __( 'Nessun errore di quota registrato.', 'fp-publisher' ),
            'count'       => 0,
            'actions'     => array(),
        );

        $quota_errors = 0;
        if ( $logs_enabled ) {
            $quota_errors = (int) $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(*) FROM {$logs_table}
                WHERE status = 'error'
                  AND message LIKE %s
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ", '%quota%' ) );
        }

        if ( $quota_errors > 0 ) {
            $quota_overview['count']       = $quota_errors;
            $quota_overview['description'] = sprintf(
                /* translators: %d: number of quota related errors. */
                __( '%d errori legati alle quote API nelle ultime 24 ore.', 'fp-publisher' ),
                (int) $quota_errors
            );

            $quota_overview['actions'][] = array(
                'description' => __( 'Riduci la frequenza di pubblicazione o suddividi le campagne.', 'fp-publisher' ),
                'url'         => add_query_arg( array( 'page' => 'fp-publisher-frequency-status' ), admin_url( 'admin.php' ) ),
            );

            $raise_severity( $quota_overview, $quota_errors > 5 ? 'critical' : 'warning' );
        }

        $summary[] = $quota_overview;

        if ( isset( $health_data['checks']['database'] ) && is_array( $health_data['checks']['database'] ) ) {
            $database    = $health_data['checks']['database'];
            $db_overview = array(
                'key'         => 'database',
                'label'       => __( 'Database', 'fp-publisher' ),
                'status'      => ! empty( $database['status'] ) ? 'ok' : 'critical',
                'description' => isset( $database['message'] ) ? $database['message'] : __( 'Database non verificato.', 'fp-publisher' ),
                'actions'     => array(),
            );

            if ( isset( $database['status'] ) && ! $database['status'] ) {
                $db_overview['actions'][] = array(
                    'description' => __( 'Esegui l’ottimizzazione tabelle dal pannello manutenzione.', 'fp-publisher' ),
                    'url'         => add_query_arg( array( 'page' => 'fp-publisher-health' ), admin_url( 'admin.php' ) ),
                );
            }

            if ( isset( $database['details']['response_time_ms'] ) && $database['details']['response_time_ms'] > 1200 ) {
                $response_time = number_format_i18n( (float) $database['details']['response_time_ms'], 2 );
                $db_overview['description'] = sprintf(
                    /* translators: %s: database response time. */
                    __( 'Tempo di risposta elevato: %s ms.', 'fp-publisher' ),
                    $response_time
                );

                $raise_severity( $db_overview, 'warning' );
            }

            $summary[] = $db_overview;
        }

        if ( isset( $health_data['checks']['api_connections'] ) && is_array( $health_data['checks']['api_connections'] ) ) {
            $api_health   = $health_data['checks']['api_connections'];
            $api_overview = array(
                'key'         => 'api_connections',
                'label'       => __( 'Connessioni API', 'fp-publisher' ),
                'status'      => 'ok',
                'description' => __( 'Tutte le integrazioni rispondono correttamente.', 'fp-publisher' ),
                'count'       => 0,
                'actions'     => array(),
            );

            if ( isset( $api_health['failed_connections'] ) && $api_health['failed_connections'] > 0 ) {
                $api_overview['count']       = (int) $api_health['failed_connections'];
                $api_overview['description'] = sprintf(
                    /* translators: %d: number of failing API connections. */
                    __( '%d integrazioni stanno riportando errori.', 'fp-publisher' ),
                    (int) $api_overview['count']
                );

                $api_overview['actions'][] = array(
                    'description' => __( 'Apri il pannello di test per ristabilire le connessioni.', 'fp-publisher' ),
                    'url'         => add_query_arg( array( 'page' => 'fp-publisher-test-connections' ), admin_url( 'admin.php' ) ),
                );

                $raise_severity( $api_overview, $api_overview['count'] > 2 ? 'critical' : 'warning' );
            }

            $summary[] = $api_overview;
        }

        return $summary;
    }

    /**
     * Translate status label to sorting weight.
     *
     * @param string $status Status label.
     * @return int
     */
    private static function get_status_level_weight( $status ) {
        switch ( $status ) {
            case 'critical':
                return 3;
            case 'warning':
                return 2;
            case 'ok':
            default:
                return 1;
        }
    }

    /**
     * Return a summary of scheduled maintenance hooks and their next execution.
     *
     * @return array<int, array<string, string|int|null>>
     */
    public static function get_scheduled_task_summary() {
        $tasks = array(
            'tts_refresh_tokens'      => array(
                'label' => __( 'Refresh token social', 'fp-publisher' ),
                'frequency' => __( 'Settimanale', 'fp-publisher' ),
            ),
            'tts_hourly_health_check' => array(
                'label' => __( 'Verifica salute sistema', 'fp-publisher' ),
                'frequency' => __( 'Oraria', 'fp-publisher' ),
            ),
            'tts_fetch_metrics'       => array(
                'label' => __( 'Import metriche canali', 'fp-publisher' ),
                'frequency' => __( 'Ogni 6 ore', 'fp-publisher' ),
            ),
            'tts_check_links'         => array(
                'label' => __( 'Link checker contenuti', 'fp-publisher' ),
                'frequency' => __( 'Giornaliera', 'fp-publisher' ),
            ),
        );

        $summary = array();

        foreach ( $tasks as $hook => $meta ) {
            $next_run = wp_next_scheduled( $hook );
            $summary[] = array(
                'hook'      => $hook,
                'label'     => $meta['label'],
                'frequency' => $meta['frequency'],
                'next_run'  => $next_run ? $next_run : null,
                'status'    => $next_run ? 'scheduled' : 'missing',
            );
        }

        return $summary;
    }
    
    /**
     * Send health alerts.
     */
    private static function send_health_alerts( $alerts ) {
        $alert_settings = get_option( 'tts_alert_settings', array(
            'enabled' => false,
            'email' => get_option( 'admin_email' ),
            'severity_threshold' => 'medium'
        ) );
        
        if ( ! $alert_settings['enabled'] ) {
            return;
        }
        
        $high_priority_alerts = array_filter( $alerts, function( $alert ) {
            return $alert['severity'] === 'high';
        } );
        
        if ( empty( $high_priority_alerts ) && $alert_settings['severity_threshold'] === 'high' ) {
            return;
        }
        
        $subject = 'TTS Health Alert: ' . count( $alerts ) . ' issue(s) detected';
        $message = "Health check detected the following issues:\n\n";
        
        foreach ( $alerts as $alert ) {
            $message .= sprintf(
                "Type: %s\nSeverity: %s\nMessage: %s\n\n",
                ucfirst( $alert['type'] ),
                ucfirst( $alert['severity'] ),
                $alert['message']
            );
        }
        
        $message .= "Please check your Social Auto Publisher dashboard for more details.\n";
        $message .= "Dashboard: " . admin_url( 'admin.php?page=fp-publisher-main' );
        
        wp_mail( $alert_settings['email'], $subject, $message );
    }
    
    /**
     * Generate daily system report.
     */
    public static function generate_daily_report() {
        $report = TTS_Advanced_Utils::generate_system_report();
        $performance_metrics = TTS_Performance::get_performance_metrics();
        $health_data = get_option( 'tts_last_health_check', array() );
        
        $daily_report = array(
            'date' => current_time( 'Y-m-d' ),
            'system_report' => $report,
            'performance_metrics' => $performance_metrics,
            'health_status' => $health_data,
            'daily_stats' => self::get_daily_stats()
        );
        
        // Store report
        update_option( 'tts_daily_report_' . current_time( 'Y_m_d' ), $daily_report );
        
        // Clean up old reports (keep last 30 days)
        self::cleanup_old_reports();
        
        return $daily_report;
    }
    
    /**
     * Get daily statistics.
     */
    private static function get_daily_stats() {
        global $wpdb;

        $today = current_time( 'Y-m-d' );
        $api_channels = array(
            'facebook',
            'instagram',
            'youtube',
            'tiktok',
            'blog',
            'facebook_story',
            'instagram_story',
            'trello',
            'webhook',
        );

        $api_calls = 0;

        if ( ! empty( $api_channels ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $api_channels ), '%s' ) );
            $query_args   = array_merge( $api_channels, array( $today ) );

            $api_calls = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "
                        SELECT COUNT(*) FROM {$wpdb->prefix}tts_logs
                        WHERE channel IN ($placeholders)
                        AND DATE(created_at) = %s
                    ",
                    $query_args
                )
            );
        }

        return array(
            'posts_created' => $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_type = 'tts_social_post'
                AND DATE(post_date) = %s
            ", $today ) ),
            'posts_published' => $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_type = 'tts_social_post'
                AND post_status = 'publish'
                AND DATE(post_modified) = %s
            ", $today ) ),
            'errors_logged' => $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(*) FROM {$wpdb->prefix}tts_logs
                WHERE status = 'error'
                AND DATE(created_at) = %s
            ", $today ) ),
            'api_calls' => $api_calls,
        );
    }
    
    /**
     * Clean up old reports.
     */
    private static function cleanup_old_reports() {
        global $wpdb;
        
        $cutoff_date = date( 'Y_m_d', strtotime( '-30 days' ) );
        
        $wpdb->query( $wpdb->prepare( "
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE 'tts_daily_report_%'
            AND option_name < %s
        ", 'tts_daily_report_' . $cutoff_date ) );
    }
    
    /**
     * Show health warnings in admin.
     */
    public static function show_health_warnings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $health_data = get_option( 'tts_last_health_check', array() );
        
        if ( empty( $health_data['alerts'] ) ) {
            return;
        }
        
        $high_priority_alerts = array_filter( $health_data['alerts'], function( $alert ) {
            return $alert['severity'] === 'high';
        } );
        
        if ( ! empty( $high_priority_alerts ) ) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>Social Auto Publisher Health Alert:</strong> ' . count( $high_priority_alerts ) . ' critical issue(s) detected.</p>';
            echo '<p><a href="' . admin_url( 'admin.php?page=fp-publisher-main' ) . '">View Dashboard</a></p>';
            echo '</div>';
        }
    }
    
    /**
     * Get current health status.
     */
    public static function get_current_health_status() {
        $health_data = get_option( 'tts_last_health_check', array() );
        
        if ( empty( $health_data ) ) {
            return array(
                'score' => 0,
                'status' => 'unknown',
                'message' => 'No health data available'
            );
        }
        
        $score = isset( $health_data['score'] ) ? $health_data['score'] : 0;
        
        if ( $score >= 90 ) {
            $status = 'excellent';
            $message = 'System is running optimally';
        } elseif ( $score >= 70 ) {
            $status = 'good';
            $message = 'System is running well with minor issues';
        } elseif ( $score >= 50 ) {
            $status = 'warning';
            $message = 'System has some issues that need attention';
        } else {
            $status = 'critical';
            $message = 'System has serious issues requiring immediate attention';
        }
        
        return array(
            'score' => $score,
            'status' => $status,
            'message' => $message,
            'alerts' => isset( $health_data['alerts'] ) ? $health_data['alerts'] : array(),
            'last_check' => isset( $health_data['timestamp'] ) ? $health_data['timestamp'] : null
        );
    }
}

// Initialize monitoring system
TTS_Monitoring::init();