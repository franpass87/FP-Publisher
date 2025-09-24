<?php
/**
 * Simple WordPress stubs for plugin unit tests.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! defined( 'TSAP_PLUGIN_DIR' ) ) {
    define( 'TSAP_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
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
$GLOBALS['tts_enqueued_styles']      = array();
$GLOBALS['tts_registered_scripts']   = array();
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
$GLOBALS['tts_registered_post_types'] = array();
$GLOBALS['tts_registered_post_meta']  = array();
$GLOBALS['tts_registered_roles']      = array();
$GLOBALS['tts_test_posts']            = array();
$GLOBALS['tts_deleted_posts']         = array();
$GLOBALS['tts_cleared_scheduled_hooks'] = array();
$GLOBALS['tts_unscheduled_actions']   = array();
$GLOBALS['tts_scheduled_actions']     = array();
$GLOBALS['tts_scheduled_single_events'] = array();
$GLOBALS['tts_registered_activation_hooks'] = array();
$GLOBALS['tts_registered_deactivation_hooks'] = array();
$GLOBALS['tts_registered_rest_routes'] = array();
$GLOBALS['tts_is_admin']              = false;
$GLOBALS['tts_loaded_textdomains']    = array();
$GLOBALS['tts_loaded_plugin_textdomains'] = array();
$GLOBALS['tts_unloaded_textdomains']  = array();

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

if ( ! function_exists( '_n' ) ) {
    function _n( $single, $plural, $number, $domain = null ) {
        unset( $domain );

        return 1 === (int) $number ? $single : $plural;
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

if ( ! function_exists( 'is_admin' ) ) {
    function is_admin() {
        return ! empty( $GLOBALS['tts_is_admin'] );
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) {
        return (string) $url;
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) {
        return (string) $url;
    }
}

if ( ! function_exists( 'esc_js' ) ) {
    function esc_js( $text ) {
        return addslashes( (string) $text );
    }
}

if ( ! function_exists( 'get_locale' ) ) {
    function get_locale() {
        return 'en_US';
    }
}

if ( ! function_exists( 'determine_locale' ) ) {
    function determine_locale() {
        return get_locale();
    }
}

if ( ! function_exists( 'unload_textdomain' ) ) {
    function unload_textdomain( $domain ) {
        $GLOBALS['tts_unloaded_textdomains'][] = $domain;

        return true;
    }
}

if ( ! function_exists( 'load_textdomain' ) ) {
    function load_textdomain( $domain, $mofile ) {
        $GLOBALS['tts_loaded_textdomains'][] = array(
            'domain' => $domain,
            'mofile' => $mofile,
        );

        return true;
    }
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
    function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = '' ) {
        unset( $deprecated );

        $GLOBALS['tts_loaded_plugin_textdomains'][] = array(
            'domain' => $domain,
            'path'   => $plugin_rel_path,
        );

        return true;
    }
}

if ( ! function_exists( 'plugin_basename' ) ) {
    function plugin_basename( $file ) {
        $file = str_replace( '\\', '/', (string) $file );

        $plugins_dir = dirname( TSAP_PLUGIN_DIR );
        $plugins_dir = rtrim( str_replace( '\\', '/', $plugins_dir ), '/' );

        if ( '' !== $plugins_dir && 0 === strpos( $file, $plugins_dir ) ) {
            $file = ltrim( substr( $file, strlen( $plugins_dir ) ), '/' );
        }

        return $file;
    }
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $text ) {
        return trim( strip_tags( (string) $text ) );
    }
}

if ( ! function_exists( 'wp_trim_words' ) ) {
    function wp_trim_words( $text, $num_words = 55 ) {
        $words = preg_split( '/\s+/', trim( (string) $text ) );

        if ( false === $words ) {
            return '';
        }

        if ( count( $words ) <= $num_words ) {
            return implode( ' ', $words );
        }

        $words = array_slice( $words, 0, $num_words );

        return implode( ' ', $words );
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

if ( ! function_exists( 'get_site_url' ) ) {
    function get_site_url() {
        return 'http://example.com';
    }
}

if ( ! function_exists( 'trailingslashit' ) ) {
    function trailingslashit( $string ) {
        return rtrim( (string) $string, '/\\' ) . '/';
    }
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
    function wp_upload_dir() {
        return array(
            'path' => sys_get_temp_dir(),
            'url'  => 'http://example.com/uploads',
            'error'=> false,
        );
    }
}

if ( ! function_exists( 'register_post_type' ) ) {
    function register_post_type( $post_type, $args = array() ) {
        $GLOBALS['tts_registered_post_types'][ $post_type ] = $args;

        return true;
    }
}

if ( ! function_exists( 'register_post_meta' ) ) {
    function register_post_meta( $post_type, $meta_key, $args ) {
        $GLOBALS['tts_registered_post_meta'][ $post_type ][ $meta_key ] = $args;

        return true;
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

if ( ! function_exists( 'wp_register_script' ) ) {
    function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
        $GLOBALS['tts_registered_scripts'][ $handle ] = array(
            'src'       => $src,
            'deps'      => $deps,
            'ver'       => $ver,
            'in_footer' => $in_footer,
        );

        return true;
    }
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
        $GLOBALS['tts_enqueued_styles'][] = $handle;
        return true;
    }
}

if ( ! function_exists( 'plugins_url' ) ) {
    function plugins_url( $path = '', $plugin = '' ) {
        $plugin_dir = '';

        if ( $plugin ) {
            $plugin_dir = dirname( str_replace( '\\', '/', (string) $plugin ) );
            if ( '.' === $plugin_dir ) {
                $plugin_dir = '';
            }
        }

        $base = 'https://example.com/wp-content/plugins';

        if ( '' !== $plugin_dir ) {
            $base .= '/' . trim( $plugin_dir, '/' );
        }

        $path = ltrim( str_replace( '\\', '/', (string) $path ), '/' );

        if ( '' !== $path ) {
            $base .= '/' . $path;
        }

        return $base;
    }
}

if ( ! class_exists( 'WP_Role' ) ) {
    class WP_Role {
        /** @var string */
        public $name;

        /** @var array<string, bool> */
        public $capabilities = array();

        public function __construct( $role, $capabilities = array() ) {
            $this->name         = (string) $role;
            $this->capabilities = array();

            foreach ( (array) $capabilities as $capability => $grant ) {
                $this->capabilities[ $capability ] = (bool) $grant;
            }
        }

        public function add_cap( $capability, $grant = true ) {
            $this->capabilities[ $capability ] = (bool) $grant;
        }

        public function remove_cap( $capability ) {
            unset( $this->capabilities[ $capability ] );
        }

        public function has_cap( $capability ) {
            return ! empty( $this->capabilities[ $capability ] );
        }
    }
}

if ( ! function_exists( 'add_role' ) ) {
    function add_role( $role, $display_name, $capabilities = array() ) {
        unset( $display_name );

        $role_object = new WP_Role( $role, $capabilities );
        $GLOBALS['tts_registered_roles'][ $role ] = $role_object;

        return $role_object;
    }
}

if ( ! function_exists( 'remove_role' ) ) {
    function remove_role( $role ) {
        unset( $GLOBALS['tts_registered_roles'][ $role ] );
    }
}

if ( ! function_exists( 'get_role' ) ) {
    function get_role( $role ) {
        return $GLOBALS['tts_registered_roles'][ $role ] ?? null;
    }
}

if ( ! function_exists( 'register_activation_hook' ) ) {
    function register_activation_hook( $file, $callback ) {
        $GLOBALS['tts_registered_activation_hooks'][ $file ] = $callback;
    }
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
    function register_deactivation_hook( $file, $callback ) {
        $GLOBALS['tts_registered_deactivation_hooks'][ $file ] = $callback;
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

if ( ! function_exists( 'has_action' ) ) {
    function has_action( $hook, $callback = false ) {
        if ( empty( $GLOBALS['tts_action_callbacks'][ $hook ] ) ) {
            return false;
        }

        if ( false === $callback ) {
            return true;
        }

        foreach ( $GLOBALS['tts_action_callbacks'][ $hook ] as $callbacks ) {
            foreach ( $callbacks as $registered ) {
                if ( $registered['callback'] === $callback ) {
                    return true;
                }
            }
        }

        return false;
    }
}

if ( ! function_exists( 'register_rest_route' ) ) {
    function register_rest_route( $namespace, $route, $args = array(), $override = false ) {
        $GLOBALS['tts_registered_rest_routes'][] = array(
            'namespace' => $namespace,
            'route'     => $route,
            'args'      => $args,
            'override'  => (bool) $override,
        );

        return true;
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
    function current_user_can( $capability, ...$args ) {
        $caps = $GLOBALS['tts_current_user_caps'] ?? array();

        if ( ! is_array( $caps ) || empty( $caps ) ) {
            return false;
        }

        if ( array_key_exists( $capability, $caps ) ) {
            return (bool) $caps[ $capability ];
        }

        $normalized_caps = array();
        foreach ( $caps as $key => $value ) {
            if ( is_string( $key ) ) {
                $normalized_caps[ $key ] = (bool) $value;
            }
        }

        if ( isset( $normalized_caps[ $capability ] ) ) {
            return $normalized_caps[ $capability ];
        }

        switch ( $capability ) {
            case 'edit_post':
                $post_id = $args[0] ?? 0;
                if ( isset( $normalized_caps[ 'edit_post_' . $post_id ] ) ) {
                    return $normalized_caps[ 'edit_post_' . $post_id ];
                }

                return ! empty( $normalized_caps['tts_edit_social_posts'] ) || ! empty( $normalized_caps['edit_posts'] );

            case 'delete_post':
                $post_id = $args[0] ?? 0;
                if ( isset( $normalized_caps[ 'delete_post_' . $post_id ] ) ) {
                    return $normalized_caps[ 'delete_post_' . $post_id ];
                }

                return ! empty( $normalized_caps['tts_delete_social_posts'] ) || ! empty( $normalized_caps['delete_posts'] );

            case 'read_post':
                return ! empty( $normalized_caps['tts_read_social_posts'] ) || ! empty( $normalized_caps['read'] );

            default:
                return ! empty( $normalized_caps[ $capability ] );
        }
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

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() {
        $user = wp_get_current_user();

        return isset( $user->ID ) ? (int) $user->ID : 0;
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

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
    function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
        $GLOBALS['tts_scheduled_single_events'][] = array(
            'hook'      => $hook,
            'timestamp' => $timestamp,
            'args'      => $args,
        );

        return true;
    }
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
    function wp_clear_scheduled_hook( $hook, $args = array() ) {
        $GLOBALS['tts_cleared_scheduled_hooks'][] = array(
            'hook' => $hook,
            'args' => $args,
        );

        return true;
    }
}

if ( ! function_exists( 'as_schedule_single_action' ) ) {
    function as_schedule_single_action( $timestamp, $hook, $args = array(), $group = '' ) {
        $GLOBALS['tts_scheduled_actions'][] = array(
            'hook'      => $hook,
            'timestamp' => $timestamp,
            'args'      => $args,
            'group'     => $group,
        );

        return uniqid( 'action_', true );
    }
}

if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
    function as_unschedule_all_actions( $hook, $args = null, $group = null ) {
        $GLOBALS['tts_unscheduled_actions'][] = array(
            'hook'  => $hook,
            'args'  => $args,
            'group' => $group,
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
        if ( isset( $GLOBALS['tts_nonce_check_results'][ $action ] ) ) {
            $override = $GLOBALS['tts_nonce_check_results'][ $action ];

            if ( is_callable( $override ) ) {
                return (bool) call_user_func( $override, $nonce, $action );
            }

            return (bool) $override;
        }

        return ( 'nonce-' . $action ) === (string) $nonce;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $text ) {
        if ( ! is_string( $text ) ) {
            return $text;
        }

        $filtered = wp_strip_all_tags( $text );
        $filtered = preg_replace( '/[\r\n\t ]+/', ' ', $filtered );

        return trim( (string) $filtered );
    }
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $text ) {
        return is_string( $text ) ? trim( preg_replace( "/[\r\0\x0B\f]/", '', $text ) ) : $text;
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

if ( ! function_exists( 'get_post' ) ) {
    function get_post( $post_id ) {
        return $GLOBALS['tts_test_posts'][ $post_id ] ?? null;
    }
}

if ( ! function_exists( 'wp_delete_post' ) ) {
    function wp_delete_post( $post_id, $force_delete = false ) {
        unset( $force_delete );

        if ( isset( $GLOBALS['tts_test_posts'][ $post_id ] ) ) {
            unset( $GLOBALS['tts_test_posts'][ $post_id ] );
            $GLOBALS['tts_deleted_posts'][] = $post_id;

            return true;
        }

        return false;
    }
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
    function get_edit_post_link( $post_id ) {
        return 'post.php?post=' . (int) $post_id;
    }
}

if ( ! function_exists( 'get_posts' ) ) {
    function get_posts( $args = array() ) {
        if ( isset( $args['post_type'] ) && 'tts_client' === $args['post_type'] ) {
            return $GLOBALS['tts_test_client_posts'];
        }
        if ( isset( $args['post_type'] ) && 'tts_social_post' === $args['post_type'] ) {
            return array_values( $GLOBALS['tts_test_posts'] );
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

if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        /** @var mixed */
        protected $data;

        /** @var int */
        protected $status = 200;

        public function __construct( $data = null, $status = 200 ) {
            $this->data   = $data;
            $this->status = (int) $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function set_data( $data ) {
            $this->data = $data;
        }

        public function get_status() {
            return $this->status;
        }

        public function set_status( $status ) {
            $this->status = (int) $status;
        }
    }
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
    function rest_ensure_response( $response ) {
        if ( $response instanceof WP_REST_Response ) {
            return $response;
        }

        return new WP_REST_Response( $response );
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

if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request implements ArrayAccess {
        /** @var array<string, mixed> */
        protected $params = array();

        /** @var array<string, string> */
        protected $headers = array();

        /** @var string */
        protected $method = 'GET';

        public function __construct( $method = 'GET', array $params = array(), array $headers = array() ) {
            $this->method  = (string) $method;
            $this->params  = $params;
            $this->headers = $headers;
        }

        public function offsetExists( $offset ) : bool {
            return isset( $this->params[ $offset ] );
        }

        #[\ReturnTypeWillChange]
        public function offsetGet( $offset ) {
            return $this->params[ $offset ] ?? null;
        }

        #[\ReturnTypeWillChange]
        public function offsetSet( $offset, $value ) : void {
            $this->params[ $offset ] = $value;
        }

        #[\ReturnTypeWillChange]
        public function offsetUnset( $offset ) : void {
            unset( $this->params[ $offset ] );
        }

        public function get_method() : string {
            return $this->method;
        }

        public function set_method( $method ) : void {
            $this->method = (string) $method;
        }

        public function set_param( $key, $value ) : void {
            $this->params[ $key ] = $value;
        }

        public function get_header( $key ) {
            return $this->headers[ $key ] ?? '';
        }

        public function set_header( $key, $value ) : void {
            $this->headers[ $key ] = (string) $value;
        }

        public function get_param( $key ) {
            return $this->params[ $key ] ?? null;
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
    $GLOBALS['tts_enqueued_styles']     = array();
    $GLOBALS['tts_registered_scripts']  = array();
    $GLOBALS['tts_registered_actions']  = array();
    $GLOBALS['tts_registered_filters']  = array();
    $GLOBALS['tts_action_callbacks']    = array();
    $GLOBALS['tts_filter_callbacks']    = array();
    $GLOBALS['tts_json_responses']      = array();
    $GLOBALS['tts_json_errors']         = array();
    $GLOBALS['tts_nonce_check_results'] = array();
    $GLOBALS['tts_scheduled_events']    = array();
    $GLOBALS['tts_registered_rest_routes'] = array();
    $GLOBALS['tts_current_user_caps']   = array();
    $GLOBALS['tts_current_user']        = null;
    $GLOBALS['tts_current_screen']      = null;
    $GLOBALS['tts_test_transients']     = array();
    $GLOBALS['tts_is_user_logged_in']   = null;
    $GLOBALS['tts_http_responses']      = array();
    $GLOBALS['tts_registered_post_types'] = array();
    $GLOBALS['tts_registered_post_meta']  = array();
    $GLOBALS['tts_registered_roles']      = array();
    $GLOBALS['tts_test_posts']            = array();
    $GLOBALS['tts_deleted_posts']         = array();
    $GLOBALS['tts_cleared_scheduled_hooks'] = array();
    $GLOBALS['tts_unscheduled_actions']   = array();
    $GLOBALS['tts_scheduled_actions']     = array();
    $GLOBALS['tts_scheduled_single_events'] = array();
    $GLOBALS['tts_registered_activation_hooks'] = array();
    $GLOBALS['tts_registered_deactivation_hooks'] = array();
    $GLOBALS['tts_is_admin']              = false;
    $GLOBALS['tts_loaded_textdomains']    = array();
    $GLOBALS['tts_loaded_plugin_textdomains'] = array();
    $GLOBALS['tts_unloaded_textdomains']  = array();

    if ( class_exists( 'TTS_Secure_Storage' ) && method_exists( 'TTS_Secure_Storage', 'reset_instance' ) ) {
        TTS_Secure_Storage::reset_instance();
    }

    $_GET     = array();
    $_POST    = array();
    $_REQUEST = array();
}

tts_reset_test_state();

require_once __DIR__ . '/../includes/class-tts-asset-manager.php';
require_once __DIR__ . '/../includes/class-tts-content-source.php';
require_once __DIR__ . '/../admin/class-tts-admin.php';
