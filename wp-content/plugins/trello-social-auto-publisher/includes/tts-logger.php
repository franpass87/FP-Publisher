<?php
/**
 * Logging utilities for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Centralized logger for the plugin.
 */
class TTS_Logger {

    /**
     * Supported log levels.
     *
     * @var array
     */
    private static $levels = array( 'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency' );

    /**
     * Cache for the custom table availability check.
     *
     * @var null|bool
     */
    private static $table_exists = null;

    /**
     * Write a message to the logging facility.
     *
     * @param string|WP_Error $message Log message.
     * @param string           $level   Log level.
     * @param array            $context Optional context information.
     */
    public static function log( $message, $level = 'info', $context = array() ) {
        $level   = self::normalize_level( $level );
        $context = self::normalize_context( $context );

        if ( empty( $context['component'] ) ) {
            $context['component'] = self::determine_component();
        }

        $formatted_message = self::format_message( $message, $level, $context );
        $logged_to_table   = self::log_to_custom_table( $message, $level, $context );

        if ( ! $logged_to_table || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
            error_log( $formatted_message );
        }
    }

    /**
     * Normalize provided log level.
     *
     * @param string $level Requested level.
     *
     * @return string
     */
    private static function normalize_level( $level ) {
        $level = strtolower( (string) ( $level ?: 'info' ) );

        if ( ! in_array( $level, self::$levels, true ) ) {
            return 'info';
        }

        return $level;
    }

    /**
     * Normalize context to an array.
     *
     * @param mixed $context Context data.
     *
     * @return array
     */
    private static function normalize_context( $context ) {
        if ( empty( $context ) ) {
            return array();
        }

        if ( $context instanceof WP_Error ) {
            return array(
                'code'    => $context->get_error_code(),
                'message' => $context->get_error_message(),
                'data'    => $context->get_error_data(),
            );
        }

        if ( is_object( $context ) ) {
            if ( method_exists( $context, 'to_array' ) ) {
                $context = $context->to_array();
            } else {
                $context = json_decode( wp_json_encode( $context ), true );
            }
        }

        if ( ! is_array( $context ) ) {
            $context = array( 'value' => $context );
        }

        return $context;
    }

    /**
     * Determine the component originating the log entry.
     *
     * @return string
     */
    private static function determine_component() {
        $trace           = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        $ignored_classes = array( __CLASS__ );
        $ignored_functions = array( 'log', __FUNCTION__, 'format_message', 'log_to_custom_table', 'normalize_level', 'normalize_context' );

        foreach ( $trace as $frame ) {
            if ( isset( $frame['class'] ) && ! in_array( $frame['class'], $ignored_classes, true ) ) {
                return self::normalize_component_name( $frame['class'] );
            }

            if ( isset( $frame['function'] ) && ! in_array( $frame['function'], $ignored_functions, true ) ) {
                if ( in_array( $frame['function'], array( 'call_user_func', 'call_user_func_array' ), true ) ) {
                    continue;
                }

                return self::normalize_component_name( $frame['function'] );
            }
        }

        return 'general';
    }

    /**
     * Normalize component name.
     *
     * @param string $component Component identifier.
     *
     * @return string
     */
    private static function normalize_component_name( $component ) {
        $component = preg_replace( '/^TTS(?:_|\\\\)/', '', $component );
        $component = strtolower( preg_replace( '/[^a-z0-9]+/i', '_', (string) $component ) );
        $component = trim( $component, '_' );

        return $component ? $component : 'general';
    }

    /**
     * Format log message for error_log output.
     *
     * @param mixed  $message  Log message.
     * @param string $level    Log level.
     * @param array  $context  Context data.
     *
     * @return string
     */
    private static function format_message( $message, $level, $context ) {
        if ( $message instanceof WP_Error ) {
            $message = $message->get_error_message();
        } elseif ( is_array( $message ) || is_object( $message ) ) {
            $message = wp_json_encode( $message );
        } else {
            $message = (string) $message;
        }

        $component = strtoupper( str_replace( '-', '_', $context['component'] ?? 'general' ) );
        $prefix    = sprintf( '[TTS][%s]', strtoupper( $level ) );

        if ( $component ) {
            $prefix .= sprintf( '[%s]', $component );
        }

        $context_for_log = $context;
        unset( $context_for_log['component'] );

        if ( ! empty( $context_for_log ) ) {
            $message .= ' | Context: ' . wp_json_encode( $context_for_log );
        }

        return $prefix . ' ' . $message;
    }

    /**
     * Persist the log entry in the custom table when available.
     *
     * @param mixed  $message Log message.
     * @param string $level   Log level.
     * @param array  $context Context data.
     *
     * @return bool
     */
    private static function log_to_custom_table( $message, $level, $context ) {
        if ( ! function_exists( 'tts_log_event' ) ) {
            return false;
        }

        if ( ! self::has_custom_table() ) {
            return false;
        }

        $channel = ! empty( $context['component'] ) ? $context['component'] : 'system';
        $response = empty( $context ) ? '' : $context;

        tts_log_event( 0, $channel, $level, (string) ( is_scalar( $message ) ? $message : wp_json_encode( $message ) ), $response );

        return true;
    }

    /**
     * Check if the custom logs table is available.
     *
     * @return bool
     */
    private static function has_custom_table() {
        if ( null !== self::$table_exists ) {
            return self::$table_exists;
        }

        global $wpdb;

        if ( ! isset( $wpdb ) || empty( $wpdb->prefix ) ) {
            self::$table_exists = false;

            return self::$table_exists;
        }

        $table_name = $wpdb->prefix . 'tts_logs';
        $result     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

        self::$table_exists = ( $result === $table_name );

        return self::$table_exists;
    }
}

/**
 * Create the tts logs table.
 */
function tts_create_logs_table() {
    global $wpdb;

    $table_name      = $wpdb->prefix . 'tts_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL,
        channel varchar(50) NOT NULL,
        status varchar(20) NOT NULL,
        message text NOT NULL,
        response longtext NULL,
        content_source varchar(50) NULL,
        source_reference varchar(255) NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY post_id (post_id),
        KEY channel_status (channel, status),
        KEY created_at (created_at),
        KEY status_created (status, created_at),
        KEY content_source (content_source)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/**
 * Log an event to the custom table.
 *
 * @param int    $post_id  Post ID.
 * @param string $channel  Channel identifier.
 * @param string $status   Status of the event.
 * @param string $message  Message to log.
 * @param mixed  $response Response data.
 */
function tts_log_event( $post_id, $channel, $status, $message, $response ) {
    global $wpdb;

    // Get content source information
    $content_source = get_post_meta( $post_id, '_tts_content_source', true );
    $source_reference = get_post_meta( $post_id, '_tts_source_reference', true );

    $table = $wpdb->prefix . 'tts_logs';
    $wpdb->insert(
        $table,
        array(
            'post_id'          => $post_id,
            'channel'          => $channel,
            'status'           => $status,
            'message'          => $message,
            'response'         => is_scalar( $response ) ? $response : wp_json_encode( $response ),
            'content_source'   => $content_source,
            'source_reference' => $source_reference,
            'created_at'       => current_time( 'mysql' ),
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
    );
}

/**
 * Purge log records older than the retention period.
 */
function tts_purge_old_logs() {
    global $wpdb;

    $options = get_option( 'tts_settings', array() );
    $days    = isset( $options['log_retention_days'] ) ? (int) $options['log_retention_days'] : 30;

    $table  = $wpdb->prefix . 'tts_logs';
    $cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );

    $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
}

add_action( 'tts_purge_old_logs', 'tts_purge_old_logs' );

add_action(
    'init',
    function () {
        if ( ! wp_next_scheduled( 'tts_purge_old_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'tts_purge_old_logs' );
        }
    }
);
