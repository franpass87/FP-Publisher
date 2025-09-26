<?php
/**
 * Runtime logging utility for development diagnostics.
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Captures PHP notices, warnings, and shutdown errors in a structured log file.
 */
class TTS_Runtime_Logger {

        /**
         * Absolute path to the log file.
         *
         * @var string
         */
        private $log_file = '';

        /**
         * Whether hooks/error handlers have been registered.
         *
         * @var bool
         */
        private $registered = false;

        /**
         * Previous PHP error handler, if any.
         *
         * @var callable|null
         */
        private $previous_error_handler = null;

        /**
         * Constructor.
         *
         * @param string $log_file Optional custom log file path.
         */
        public function __construct( $log_file = '' ) {
                $this->log_file = $log_file ? (string) $log_file : $this->determine_log_file();
        }

        /**
         * Register error handlers and WordPress hooks for runtime logging.
         *
         * @return void
         */
        public function register() {
                if ( $this->registered || empty( $this->log_file ) ) {
                        return;
                }

                if ( ! $this->ensure_directory() ) {
                        return;
                }

                $this->previous_error_handler = set_error_handler( array( $this, 'handle_php_error' ) );
                register_shutdown_function( array( $this, 'handle_shutdown' ) );

                if ( function_exists( 'add_action' ) ) {
                        add_action( 'doing_it_wrong_run', array( $this, 'handle_doing_it_wrong' ), 10, 3 );
                        add_action( 'deprecated_function_run', array( $this, 'handle_deprecated_function' ), 10, 3 );
                        add_action( 'deprecated_argument_run', array( $this, 'handle_deprecated_argument' ), 10, 3 );
                        add_action( 'deprecated_hook_run', array( $this, 'handle_deprecated_hook' ), 10, 4 );
                }

                $this->registered = true;
        }

        /**
         * Handle PHP errors and persist them to the runtime log.
         *
         * @param int    $severity Error severity.
         * @param string $message  Error message.
         * @param string $file     File path.
         * @param int    $line     Line number.
         *
         * @return bool|null
         */
        public function handle_php_error( $severity, $message, $file = '', $line = 0 ) {
                if ( ! $this->should_capture_severity( $severity ) ) {
                        return $this->delegate_error_handling( $severity, $message, $file, $line );
                }

                $entry = array(
                        'type'      => 'php_error',
                        'severity'  => $this->map_severity( $severity ),
                        'message'   => (string) $message,
                        'file'      => (string) $file,
                        'line'      => (int) $line,
                        'timestamp' => $this->current_time(),
                        'context'   => $this->get_request_context(),
                );

                $this->write_entry( $entry );
                $this->record_via_logger( $entry );

                return $this->delegate_error_handling( $severity, $message, $file, $line );
        }

        /**
         * Capture fatal errors on shutdown.
         *
         * @return void
         */
        public function handle_shutdown() {
                $error = error_get_last();

                if ( empty( $error ) || ! $this->is_fatal_severity( $error['type'] ) ) {
                        return;
                }

                $entry = array(
                        'type'      => 'php_fatal',
                        'severity'  => $this->map_severity( $error['type'] ),
                        'message'   => isset( $error['message'] ) ? (string) $error['message'] : '',
                        'file'      => isset( $error['file'] ) ? (string) $error['file'] : '',
                        'line'      => isset( $error['line'] ) ? (int) $error['line'] : 0,
                        'timestamp' => $this->current_time(),
                        'context'   => $this->get_request_context(),
                );

                $this->write_entry( $entry );
                $this->record_via_logger( $entry );
        }

        /**
         * Log occurrences of _doing_it_wrong().
         *
         * @param string $function Function triggering the warning.
         * @param string $message  Diagnostic message.
         * @param string $version  Version when the usage was deprecated.
         *
         * @return void
         */
        public function handle_doing_it_wrong( $function, $message, $version ) {
                $entry = array(
                        'type'      => 'doing_it_wrong',
                        'severity'  => 'warning',
                        'function'  => (string) $function,
                        'message'   => (string) $message,
                        'version'   => (string) $version,
                        'timestamp' => $this->current_time(),
                        'context'   => $this->get_request_context(),
                );

                $this->write_entry( $entry );
                $this->record_via_logger( $entry );
        }

        /**
         * Log deprecated function notices.
         *
         * @param string $function    Deprecated function name.
         * @param string $replacement Suggested replacement.
         * @param string $version     Version when deprecated.
         *
         * @return void
         */
        public function handle_deprecated_function( $function, $replacement, $version ) {
                $entry = array(
                        'type'        => 'deprecated_function',
                        'severity'    => 'notice',
                        'function'    => (string) $function,
                        'replacement' => (string) $replacement,
                        'version'     => (string) $version,
                        'timestamp'   => $this->current_time(),
                        'context'     => $this->get_request_context(),
                );

                $this->write_entry( $entry );
                $this->record_via_logger( $entry );
        }

        /**
         * Log deprecated argument notices.
         *
         * @param string $function Function using a deprecated argument.
         * @param string $message  Diagnostic message.
         * @param string $version  Version when deprecated.
         *
         * @return void
         */
        public function handle_deprecated_argument( $function, $message, $version ) {
                $entry = array(
                        'type'      => 'deprecated_argument',
                        'severity'  => 'notice',
                        'function'  => (string) $function,
                        'message'   => (string) $message,
                        'version'   => (string) $version,
                        'timestamp' => $this->current_time(),
                        'context'   => $this->get_request_context(),
                );

                $this->write_entry( $entry );
                $this->record_via_logger( $entry );
        }

        /**
         * Log deprecated hook usage.
         *
         * @param string $hook        Deprecated hook name.
         * @param string $replacement Suggested replacement.
         * @param string $version     Version when deprecated.
         * @param string $message     Additional context message.
         *
         * @return void
         */
        public function handle_deprecated_hook( $hook, $replacement, $version, $message ) {
                $entry = array(
                        'type'        => 'deprecated_hook',
                        'severity'    => 'notice',
                        'hook'        => (string) $hook,
                        'replacement' => (string) $replacement,
                        'version'     => (string) $version,
                        'message'     => (string) $message,
                        'timestamp'   => $this->current_time(),
                        'context'     => $this->get_request_context(),
                );

                $this->write_entry( $entry );
                $this->record_via_logger( $entry );
        }

        /**
         * Delegate to the previous error handler when available.
         *
         * @param int    $severity Error severity.
         * @param string $message  Error message.
         * @param string $file     File path.
         * @param int    $line     Line number.
         *
         * @return bool|null
         */
        private function delegate_error_handling( $severity, $message, $file, $line ) {
                if ( is_callable( $this->previous_error_handler ) ) {
                        return call_user_func( $this->previous_error_handler, $severity, $message, $file, $line );
                }

                return false;
        }

        /**
         * Ensure the log directory exists.
         *
         * @return bool
         */
        private function ensure_directory() {
                $directory = dirname( $this->log_file );

                if ( is_dir( $directory ) ) {
                        return is_writable( $directory );
                }

                if ( function_exists( 'wp_mkdir_p' ) ) {
                        wp_mkdir_p( $directory );
                } else {
                        @mkdir( $directory, 0755, true );
                }

                return is_dir( $directory ) && is_writable( $directory );
        }

        /**
         * Determine whether a severity should be captured.
         *
         * @param int $severity Severity level.
         *
         * @return bool
         */
        private function should_capture_severity( $severity ) {
                $capture_levels = array(
                        E_WARNING,
                        E_USER_WARNING,
                        E_NOTICE,
                        E_USER_NOTICE,
                        E_DEPRECATED,
                        E_USER_DEPRECATED,
                        E_RECOVERABLE_ERROR,
                        E_STRICT,
                        E_USER_ERROR,
                );

                return in_array( (int) $severity, $capture_levels, true );
        }

        /**
         * Determine whether a severity is fatal.
         *
         * @param int $severity Severity level.
         *
         * @return bool
         */
        private function is_fatal_severity( $severity ) {
                $fatal_levels = array(
                        E_ERROR,
                        E_PARSE,
                        E_CORE_ERROR,
                        E_COMPILE_ERROR,
                        E_USER_ERROR,
                );

                return in_array( (int) $severity, $fatal_levels, true );
        }

        /**
         * Map PHP severity to a human readable level.
         *
         * @param int $severity Severity level.
         *
         * @return string
         */
        private function map_severity( $severity ) {
                switch ( (int) $severity ) {
                        case E_ERROR:
                        case E_CORE_ERROR:
                        case E_COMPILE_ERROR:
                        case E_USER_ERROR:
                                return 'error';
                        case E_WARNING:
                        case E_USER_WARNING:
                        case E_RECOVERABLE_ERROR:
                                return 'warning';
                        case E_NOTICE:
                        case E_USER_NOTICE:
                        case E_DEPRECATED:
                        case E_USER_DEPRECATED:
                        case E_STRICT:
                                return 'notice';
                        case E_PARSE:
                                return 'critical';
                }

                return 'notice';
        }

        /**
         * Persist a log entry to disk.
         *
         * @param array $entry Structured entry data.
         *
         * @return void
         */
        private function write_entry( array $entry ) {
                if ( empty( $this->log_file ) ) {
                        return;
                }

                if ( function_exists( 'apply_filters' ) ) {
                        $entry = apply_filters( 'tsap_runtime_log_entry', $entry );
                }

                $encoded = $this->encode_entry( $entry );

                if ( false === $encoded ) {
                        return;
                }

                @file_put_contents( $this->log_file, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX );
        }

        /**
         * Relay the entry through the plugin logger when available.
         *
         * @param array $entry Structured entry data.
         *
         * @return void
         */
        private function record_via_logger( array $entry ) {
                if ( ! class_exists( 'TTS_Logger' ) ) {
                        return;
                }

                $level   = isset( $entry['severity'] ) ? $entry['severity'] : 'notice';
                $message = isset( $entry['message'] ) ? $entry['message'] : $this->encode_entry( $entry );
                $context = array(
                        'component' => 'runtime_logger',
                        'type'      => $entry['type'] ?? 'runtime',
                        'file'      => $entry['file'] ?? '',
                        'line'      => $entry['line'] ?? 0,
                );

                if ( isset( $entry['function'] ) ) {
                        $context['function'] = $entry['function'];
                }

                if ( isset( $entry['hook'] ) ) {
                        $context['hook'] = $entry['hook'];
                }

                TTS_Logger::log( $message, $level, $context );
        }

        /**
         * Encode a log entry as JSON.
         *
         * @param array $entry Structured entry data.
         *
         * @return string|false
         */
        private function encode_entry( array $entry ) {
                if ( function_exists( 'wp_json_encode' ) ) {
                        return wp_json_encode( $entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
                }

                return json_encode( $entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        }

        /**
         * Determine the log file path.
         *
         * @return string
         */
        private function determine_log_file() {
                $path = '';

                if ( function_exists( 'wp_upload_dir' ) ) {
                        $uploads = wp_upload_dir();
                        if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
                                $directory = $this->trailingslashit( $uploads['basedir'] ) . 'fp-publisher-logs';
                                $path      = $this->trailingslashit( $directory ) . 'runtime.log';
                        }
                }

                if ( empty( $path ) ) {
                        $base = defined( 'TSAP_PLUGIN_DIR' ) ? TSAP_PLUGIN_DIR : dirname( __DIR__ ) . '/';
                        $path = $this->trailingslashit( $base ) . 'runtime/runtime.log';
                }

                if ( function_exists( 'apply_filters' ) ) {
                        $path = apply_filters( 'tsap_runtime_log_file', $path );
                }

                return (string) $path;
        }

        /**
         * Get the current timestamp in ISO8601 format.
         *
         * @return string
         */
        private function current_time() {
                if ( function_exists( 'current_time' ) ) {
                        return current_time( 'mysql', true );
                }

                return gmdate( 'Y-m-d H:i:s' );
        }

        /**
         * Gather request context for the log entry.
         *
         * @return array
         */
        private function get_request_context() {
                $method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'CLI';
                $uri    = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'cli';

                if ( function_exists( 'wp_unslash' ) ) {
                        $method = wp_unslash( $method );
                        $uri    = wp_unslash( $uri );
                }

                if ( function_exists( 'sanitize_text_field' ) ) {
                        $method = sanitize_text_field( $method );
                } else {
                        $method = preg_replace( '/[^A-Z]/i', '', (string) $method );
                }

                if ( function_exists( 'esc_url_raw' ) ) {
                        $uri = esc_url_raw( $uri );
                } else {
                        $uri = filter_var( (string) $uri, FILTER_SANITIZE_URL );
                }

                $context = array(
                        'method' => $method,
                        'uri'    => $uri,
                );

                if ( function_exists( 'is_admin' ) ) {
                        $context['is_admin'] = is_admin();
                }

                return $context;
        }

        /**
         * Ensure a trailing slash is present on a path.
         *
         * @param string $path Path to normalize.
         *
         * @return string
         */
        private function trailingslashit( $path ) {
                $path = (string) $path;
                if ( '' === $path ) {
                        return '';
                }

                return rtrim( $path, '/\\' ) . '/';
        }
}
