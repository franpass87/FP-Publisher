<?php
/**
 * Simple WordPress stubs for plugin unit tests.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
    define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
    define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS );
}

// Provide deterministic encryption material for tests.
putenv( 'TTS_ENCRYPTION_KEY=YmJiYmJiYmJiYmJiYmJiYmJiYmJiYmJiYmJiYmJiYmI=' );

// Global containers used by the tests.
$GLOBALS['tts_test_options']         = array();
$GLOBALS['tts_test_post_meta']       = array();
$GLOBALS['tts_test_client_posts']    = array();
$GLOBALS['tts_test_wpdb_results']    = array();
$GLOBALS['tts_localized_scripts']    = array();
$GLOBALS['tts_enqueued_scripts']     = array();
$GLOBALS['tts_registered_actions']   = array();
$GLOBALS['tts_registered_filters']   = array();
$GLOBALS['tts_action_callbacks']     = array();
$GLOBALS['tts_filter_callbacks']     = array();
$GLOBALS['tts_json_responses']       = array();
$GLOBALS['tts_json_errors']          = array();
$GLOBALS['tts_scheduled_events']     = array();
$GLOBALS['tts_nonce_check_results']  = array();
$GLOBALS['tts_current_user_caps']    = array();
$GLOBALS['tts_current_user']         = null;
$GLOBALS['tts_current_screen']       = null;
$GLOBALS['tts_test_transients']      = array();
$GLOBALS['tts_is_user_logged_in']    = null;
$GLOBALS['tts_http_responses']       = array();

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = null ) {
        return $text;
    }
}

if ( ! function_exists( '_e' ) ) {
    function _e( $text, $domain = null ) {
        echo $text;
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = null ) {
        return $text;
    }
}

if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( $text, $domain = null ) {
        echo esc_html__( $text, $domain );
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr__' ) ) {
    function esc_attr__( $text, $domain = null ) {
        return $text;
    }
}

if ( ! function_exists( 'esc_attr_e' ) ) {
    function esc_attr_e( $text, $domain = null ) {
        echo esc_attr__( $text, $domain );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) {
        return (string) $url;
    }
}

if ( ! function_exists( 'esc_js' ) ) {
    function esc_js( $text ) {
        return addslashes( (string) $text );
    }
}

if ( ! function_exists( 'selected' ) ) {
    function selected( $selected, $current, $echo = true ) {
        $result = (string) $selected === (string) $current ? ' selected="selected"' : '';
        if ( $echo ) {
            echo $result;
        }
        return $result;
    }
}

if ( ! function_exists( 'checked' ) ) {
    function checked( $checked, $current = true, $echo = true ) {
        $result = $checked == $current ? ' checked="checked"' : '';
        if ( $echo ) {
            echo $result;
        }
        return $result;
    }
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
    function wp_nonce_field( $action = -1, $name = '_wpnonce' ) {
        // No-op in tests.
    }
}

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        return $value;
    }
}

if ( ! function_exists( 'absint' ) ) {
    function absint( $maybeint ) {
        return abs( (int) $maybeint );
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        $key = strtolower( (string) $key );
        return preg_replace( '/[^a-z0-9_]/', '', $key );
    }
}

if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '' ) {
        return 'admin.php?' . ltrim( (string) $path, '?' );
    }
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
    function plugin_dir_url( $file ) {
        return 'http://example.com/plugin/';
    }
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path( $file ) {
        return rtrim( dirname( $file ), '/\\' ) . '/';
    }
}

if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( $params, $url = '' ) {
        $params = (array) $params;
        $parsed = parse_url( $url );
        $query  = array();

        if ( isset( $parsed['query'] ) ) {
            parse_str( $parsed['query'], $query );
        }

        foreach ( $params as $key => $value ) {
            $query[ $key ] = $value;
        }

        $scheme   = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '';
        $host     = $parsed['host'] ?? '';
        $port     = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
        $path     = $parsed['path'] ?? '';
        $base     = $scheme . $host . $port . $path;
        $fragment = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';

        $query_string = http_build_query( $query );

        if ( '' !== $query_string ) {
            return $base . '?' . $query_string . $fragment;
        }

        return $base . $fragment;
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type ) {
        if ( 'mysql' === $type ) {
            return gmdate( 'Y-m-d H:i:s' );
        }

        return time();
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data ) {
        return json_encode( $data );
    }
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( $action = -1 ) {
        return 'nonce-' . $action;
    }
}

if ( ! function_exists( 'wp_localize_script' ) ) {
    function wp_localize_script( $handle, $object_name, $data ) {
        $GLOBALS['tts_localized_scripts'][ $handle ][ $object_name ] = $data;
    }
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
        $GLOBALS['tts_enqueued_scripts'][] = $handle;
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
        $GLOBALS['tts_registered_actions'][] = array( $hook, $callback, $priority, $accepted_args );
        $GLOBALS['tts_action_callbacks'][ $hook ][ $priority ][] = array(
            'callback'      => $callback,
            'accepted_args' => $accepted_args,
        );
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
        $GLOBALS['tts_registered_filters'][] = array( $hook, $callback, $priority, $accepted_args );
        $GLOBALS['tts_filter_callbacks'][ $hook ][ $priority ][] = array(
            'callback'      => $callback,
            'accepted_args' => $accepted_args,
        );
    }
}

if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) {
        if ( ! isset( $GLOBALS['tts_test_transients'][ $key ] ) ) {
            return false;
        }

        $stored = $GLOBALS['tts_test_transients'][ $key ];

        if ( isset( $stored['expires'] ) && $stored['expires'] < time() ) {
            unset( $GLOBALS['tts_test_transients'][ $key ] );
            return false;
        }

        return $stored['value'];
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $expiration ) {
        $GLOBALS['tts_test_transients'][ $key ] = array(
            'value'   => $value,
            'expires' => time() + (int) $expiration,
        );

        return true;
    }
}

if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $key ) {
        unset( $GLOBALS['tts_test_transients'][ $key ] );

        return true;
    }
}

if ( ! function_exists( 'do_action' ) ) {
    function do_action( $hook, ...$args ) {
        if ( empty( $GLOBALS['tts_action_callbacks'][ $hook ] ) ) {
            return;
        }

        ksort( $GLOBALS['tts_action_callbacks'][ $hook ] );

        foreach ( $GLOBALS['tts_action_callbacks'][ $hook ] as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                $call_args = array_slice( $args, 0, $callback['accepted_args'] );
                call_user_func_array( $callback['callback'], $call_args );
            }
        }
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $hook, $value, ...$args ) {
        if ( empty( $GLOBALS['tts_filter_callbacks'][ $hook ] ) ) {
            return $value;
        }

        ksort( $GLOBALS['tts_filter_callbacks'][ $hook ] );
        $filtered = $value;

        foreach ( $GLOBALS['tts_filter_callbacks'][ $hook ] as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                $callback_args = array_merge(
                    array( $filtered ),
                    array_slice( $args, 0, max( 0, $callback['accepted_args'] - 1 ) )
                );
                $filtered = call_user_func_array( $callback['callback'], $callback_args );
            }
        }

        return $filtered;
    }
}

if ( ! function_exists( 'add_menu_page' ) ) {
    function add_menu_page() {}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
    function add_submenu_page() {}
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) {
        $caps = $GLOBALS['tts_current_user_caps'] ?? null;

        if ( ! is_array( $caps ) || empty( $caps ) ) {
            return true;
        }

        if ( array_key_exists( $capability, $caps ) ) {
            return (bool) $caps[ $capability ];
        }

        $granted_caps = array_keys( array_filter( $caps ) );

        return in_array( $capability, $granted_caps, true );
    }
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
    function is_user_logged_in() {
        if ( isset( $GLOBALS['tts_is_user_logged_in'] ) && null !== $GLOBALS['tts_is_user_logged_in'] ) {
            return (bool) $GLOBALS['tts_is_user_logged_in'];
        }

        if ( isset( $GLOBALS['tts_current_user'] ) && null !== $GLOBALS['tts_current_user'] ) {
            return true;
        }

        $caps = $GLOBALS['tts_current_user_caps'] ?? array();

        return ! empty( $caps );
    }
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
    function wp_get_current_user() {
        if ( isset( $GLOBALS['tts_current_user'] ) && null !== $GLOBALS['tts_current_user'] ) {
            return $GLOBALS['tts_current_user'];
        }

        $caps = $GLOBALS['tts_current_user_caps'] ?? array();

        $user = (object) array(
            'ID'       => 1,
            'user_login' => 'test-user',
            'allcaps'  => $caps,
        );

        $GLOBALS['tts_current_user'] = $user;

        return $user;
    }
}

if ( ! function_exists( 'get_current_screen' ) ) {
    function get_current_screen() {
        return $GLOBALS['tts_current_screen'] ?? null;
    }
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
        $field = '_ajax_nonce';

        if ( false !== $query_arg && null !== $query_arg ) {
            $field = (string) $query_arg;
        } elseif ( isset( $_REQUEST['_ajax_nonce'] ) ) {
            $field = '_ajax_nonce';
        } elseif ( isset( $_REQUEST['_wpnonce'] ) ) {
            $field = '_wpnonce';
        }

        $nonce_value = null;
        if ( isset( $_REQUEST[ $field ] ) ) {
            $nonce_value = (string) $_REQUEST[ $field ];
        }

        $is_valid = null;

        if ( isset( $GLOBALS['tts_nonce_check_results'][ $action ] ) ) {
            $override = $GLOBALS['tts_nonce_check_results'][ $action ];
            if ( is_callable( $override ) ) {
                $is_valid = (bool) call_user_func( $override, $nonce_value, $field, $action );
            } else {
                $is_valid = (bool) $override;
            }
        } elseif ( null !== $nonce_value ) {
            $is_valid = ( $nonce_value === 'nonce-' . $action );
        } else {
            $is_valid = false;
        }

        if ( $is_valid ) {
            return 1;
        }

        if ( $die ) {
            wp_die( -1, '', array( 'response' => 403 ) );
        }

        return false;
    }
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $data = null, $status_code = 200 ) {
        $response = array(
            'success'     => true,
            'data'        => $data,
            'status_code' => $status_code,
        );

        $GLOBALS['tts_json_responses'][] = $response;

        return $response;
    }
}

if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url ) {
        if ( isset( $GLOBALS['tts_http_responses'][ $url ] ) ) {
            return $GLOBALS['tts_http_responses'][ $url ];
        }

        return new WP_Error( 'http_not_stubbed', 'No stubbed response for ' . $url );
    }
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $response ) {
        if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
            return (int) $response['response']['code'];
        }

        return 0;
    }
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $response ) {
        if ( is_array( $response ) && isset( $response['body'] ) ) {
            return $response['body'];
        }

        return '';
    }
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
    function wp_send_json_error( $data = null, $status_code = 200 ) {
        $response = array(
            'success'     => false,
            'data'        => $data,
            'status_code' => $status_code,
        );

        $GLOBALS['tts_json_errors'][] = $response;

        return $response;
    }
}

if ( ! function_exists( 'wp_send_json' ) ) {
    function wp_send_json( $response, $status_code = 200 ) {
        $GLOBALS['tts_json_responses'][] = array(
            'success'     => is_array( $response ) && isset( $response['success'] ) ? (bool) $response['success'] : true,
            'data'        => $response,
            'status_code' => $status_code,
        );

        return $response;
    }
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
    function wp_next_scheduled( $hook ) {
        return false;
    }
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
    function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
        $GLOBALS['tts_scheduled_events'][] = array(
            'hook'       => $hook,
            'timestamp'  => $timestamp,
            'recurrence' => $recurrence,
            'args'       => $args,
        );

        return true;
    }
}

if ( ! function_exists( 'wp_die' ) ) {
    function wp_die( $message = '', $title = '', $args = array() ) {
        do_action( 'wp_die', $message, $title, $args );

        $error_message = $message;
        if ( is_array( $message ) || is_object( $message ) || '' === (string) $message ) {
            $error_message = 'wp_die called during tests';
        }

        throw new RuntimeException( (string) $error_message );
    }
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
    function wp_verify_nonce( $nonce, $action ) {
        return true;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $text ) {
        return is_string( $text ) ? trim( $text ) : $text;
    }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $data ) {
        return $data;
    }
}

if ( ! function_exists( 'wp_insert_post' ) ) {
    function wp_insert_post( $postarr ) {
        return rand( 1, 1000 );
    }
}

if ( ! function_exists( 'wp_parse_args' ) ) {
    function wp_parse_args( $args, $defaults = array() ) {
        if ( is_object( $args ) ) {
            $args = get_object_vars( $args );
        } elseif ( ! is_array( $args ) ) {
            $parsed = array();
            parse_str( (string) $args, $parsed );
            $args = $parsed;
        }

        return array_merge( $defaults, $args );
    }
}

if ( ! function_exists( 'maybe_serialize' ) ) {
    function maybe_serialize( $data ) {
        if ( is_array( $data ) || is_object( $data ) ) {
            return serialize( $data );
        }

        if ( is_serialized( $data ) ) {
            return serialize( $data );
        }

        return $data;
    }
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
    function maybe_unserialize( $data ) {
        if ( ! is_string( $data ) ) {
            return $data;
        }
        $unserialized = @unserialize( $data );
        if ( false !== $unserialized || 'b:0;' === $data ) {
            return $unserialized;
        }
        return $data;
    }
}

if ( ! function_exists( 'is_serialized' ) ) {
    function is_serialized( $data ) {
        if ( ! is_string( $data ) ) {
            return false;
        }

        $data = trim( $data );

        if ( 'N;' === $data ) {
            return true;
        }

        if ( ! preg_match( '/^[adObis]:/', $data ) ) {
            return false;
        }

        return @unserialize( $data ) !== false || 'b:0;' === $data;
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        return $GLOBALS['tts_test_options'][ $option ] ?? $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value ) {
        $GLOBALS['tts_test_options'][ $option ] = $value;
        return true;
    }
}

if ( ! function_exists( 'add_post_meta' ) ) {
    function add_post_meta( $post_id, $key, $value, $unique = false ) {
        $check = apply_filters( 'add_post_metadata', null, $post_id, $key, $value, $unique );
        if ( null !== $check ) {
            return $check;
        }

        if ( $unique && isset( $GLOBALS['tts_test_post_meta'][ $post_id ][ $key ] ) ) {
            return false;
        }

        $GLOBALS['tts_test_post_meta'][ $post_id ][ $key ] = maybe_serialize( $value );

        return true;
    }
}

if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $post_id, $key, $single = false ) {
        $check = apply_filters( 'get_post_metadata', null, $post_id, $key, $single );
        if ( null !== $check ) {
            return $check;
        }

        if ( '' === $key ) {
            $all = $GLOBALS['tts_test_post_meta'][ $post_id ] ?? array();
            $result = array();

            foreach ( $all as $meta_key => $meta_value ) {
                $result[ $meta_key ] = maybe_unserialize( $meta_value );
            }

            return $result;
        }

        if ( ! isset( $GLOBALS['tts_test_post_meta'][ $post_id ][ $key ] ) ) {
            return $single ? '' : array();
        }

        $value = maybe_unserialize( $GLOBALS['tts_test_post_meta'][ $post_id ][ $key ] );

        return $single ? $value : array( $value );
    }
}

if ( ! function_exists( 'update_post_meta' ) ) {
    function update_post_meta( $post_id, $key, $value, $prev_value = '' ) {
        $check = apply_filters( 'update_post_metadata', null, $post_id, $key, $value, $prev_value );
        if ( null !== $check ) {
            return $check;
        }

        $GLOBALS['tts_test_post_meta'][ $post_id ][ $key ] = maybe_serialize( $value );

        return true;
    }
}

if ( ! function_exists( 'delete_post_meta' ) ) {
    function delete_post_meta( $post_id, $key, $value = '' ) {
        $check = apply_filters( 'delete_post_metadata', null, $post_id, $key, $value, false );
        if ( null !== $check ) {
            return $check;
        }

        if ( isset( $GLOBALS['tts_test_post_meta'][ $post_id ][ $key ] ) ) {
            unset( $GLOBALS['tts_test_post_meta'][ $post_id ][ $key ] );
            return true;
        }

        return false;
    }
}

if ( ! function_exists( 'get_posts' ) ) {
    function get_posts( $args = array() ) {
        if ( isset( $args['post_type'] ) && 'tts_client' === $args['post_type'] ) {
            return $GLOBALS['tts_test_client_posts'];
        }
        return array();
    }
}

if ( ! class_exists( 'WP_Query' ) ) {
    class WP_Query {
        public function __construct( $args = array() ) {}
        public function have_posts() {
            return false;
        }
        public function the_post() {}
    }
}

if ( ! function_exists( 'wp_reset_postdata' ) ) {
    function wp_reset_postdata() {}
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        protected $code;
        protected $message;
        protected $data;

        public function __construct( $code = '', $message = '', $data = '' ) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }

        public function get_error_data() {
            return $this->data;
        }
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}

if ( ! isset( $GLOBALS['wpdb'] ) ) {
    class TTS_Test_WPDB {
        public $postmeta = 'wp_postmeta';
        public $posts    = 'wp_posts';
        public $prefix   = 'wp_';

        public function get_results( $query ) {
            return $GLOBALS['tts_test_wpdb_results'];
        }

        public function get_var( $query ) {
            if ( preg_match( '/post_id\s*=\s*(\d+)/', $query, $matches ) ) {
                $post_id = (int) $matches[1];
                if ( preg_match( "/meta_key\s*=\s*'([^']+)'/", $query, $key_match ) ) {
                    $meta_key = stripslashes( $key_match[1] );
                    return $GLOBALS['tts_test_post_meta'][ $post_id ][ $meta_key ] ?? null;
                }
            }

            return null;
        }

        public function insert( $table, $data, $format = array() ) {
            return true;
        }

        public function query( $query ) {
            return true;
        }

        public function prepare( $query, ...$args ) {
            if ( empty( $args ) ) {
                return $query;
            }

            $processed = $query;

            foreach ( $args as $arg ) {
                $replacement = is_numeric( $arg ) ? (string) $arg : "'" . addslashes( (string) $arg ) . "'";
                $processed   = preg_replace( '/%s|%d/', $replacement, $processed, 1 );
            }

            return $processed;
        }

        public function esc_like( $text ) {
            return addslashes( $text );
        }

        public function get_charset_collate() {
            return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
    }
    $GLOBALS['wpdb'] = new TTS_Test_WPDB();
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    class WP_List_Table {
        public function __construct( $args = array() ) {}
        public function prepare_items() {}
        protected function get_column_info() {
            return array();
        }
    }
}

/**
 * Reset the global test state.
 */
function tts_reset_test_state() {
    $GLOBALS['tts_test_options']        = array();
    $GLOBALS['tts_test_post_meta']      = array();
    $GLOBALS['tts_test_client_posts']   = array();
    $GLOBALS['tts_test_wpdb_results']   = array();
    $GLOBALS['tts_localized_scripts']   = array();
    $GLOBALS['tts_enqueued_scripts']    = array();
    $GLOBALS['tts_registered_actions']  = array();
    $GLOBALS['tts_registered_filters']  = array();
    $GLOBALS['tts_action_callbacks']    = array();
    $GLOBALS['tts_filter_callbacks']    = array();
    $GLOBALS['tts_json_responses']      = array();
    $GLOBALS['tts_json_errors']         = array();
    $GLOBALS['tts_nonce_check_results'] = array();
    $GLOBALS['tts_scheduled_events']    = array();
    $GLOBALS['tts_current_user_caps']   = array();
    $GLOBALS['tts_current_user']        = null;
    $GLOBALS['tts_current_screen']      = null;
    $GLOBALS['tts_test_transients']     = array();
    $GLOBALS['tts_is_user_logged_in']   = null;
    $GLOBALS['tts_http_responses']      = array();

    $_GET     = array();
    $_POST    = array();
    $_REQUEST = array();
}

tts_reset_test_state();

require_once __DIR__ . '/../includes/class-tts-content-source.php';
require_once __DIR__ . '/../admin/class-tts-admin.php';
