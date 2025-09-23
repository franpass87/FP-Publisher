<?php
/**
 * Simple WordPress stubs for plugin unit tests.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}

// Global containers used by the tests.
$GLOBALS['tts_test_options']        = array();
$GLOBALS['tts_test_post_meta']      = array();
$GLOBALS['tts_test_client_posts']   = array();
$GLOBALS['tts_test_wpdb_results']   = array();
$GLOBALS['tts_localized_scripts']   = array();
$GLOBALS['tts_enqueued_scripts']    = array();
$GLOBALS['tts_registered_actions']  = array();
$GLOBALS['tts_registered_filters']  = array();

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
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
        $GLOBALS['tts_registered_filters'][] = array( $hook, $callback, $priority, $accepted_args );
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
        return true;
    }
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
        return true;
    }
}

if ( ! function_exists( 'wp_die' ) ) {
    function wp_die( $message = '' ) {
        throw new RuntimeException( $message ?: 'wp_die called during tests' );
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

if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $post_id, $key, $single = false ) {
        if ( isset( $GLOBALS['tts_test_post_meta'][ $post_id ][ $key ] ) ) {
            $value = $GLOBALS['tts_test_post_meta'][ $post_id ][ $key ];
            return $single ? $value : array( $value );
        }
        return $single ? '' : array();
    }
}

if ( ! function_exists( 'update_post_meta' ) ) {
    function update_post_meta( $post_id, $key, $value ) {
        $GLOBALS['tts_test_post_meta'][ $post_id ][ $key ] = $value;
        return true;
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

        public function get_results( $query ) {
            return $GLOBALS['tts_test_wpdb_results'];
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
}

tts_reset_test_state();

require_once __DIR__ . '/../includes/class-tts-content-source.php';
require_once __DIR__ . '/../admin/class-tts-admin.php';
