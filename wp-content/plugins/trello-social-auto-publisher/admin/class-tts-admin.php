<?php
/**
 * Admin functionality for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles admin pages and filters.
 */
class TTS_Admin {

    /**
     * Hook into WordPress actions.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'restrict_manage_posts', array( $this, 'add_client_filter' ) );
        add_action( 'restrict_manage_posts', array( $this, 'add_approved_filter' ) );
        add_action( 'pre_get_posts', array( $this, 'filter_posts_by_client' ) );
        add_action( 'pre_get_posts', array( $this, 'filter_posts_by_approved' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wizard_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_assets' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'register_scheduled_posts_widget' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_widget_assets' ) );
        add_action( 'wp_ajax_tts_get_lists', array( $this, 'ajax_get_lists' ) );
        add_action( 'wp_ajax_tts_refresh_posts', array( $this, 'ajax_refresh_posts' ) );
        add_action( 'wp_ajax_tts_delete_post', array( $this, 'ajax_delete_post' ) );
        add_action( 'wp_ajax_tts_bulk_action', array( $this, 'ajax_bulk_action' ) );
        add_action( 'wp_ajax_tts_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_tts_check_rate_limits', array( $this, 'ajax_check_rate_limits' ) );
        add_action( 'wp_ajax_tts_export_data', array( $this, 'ajax_export_data' ) );
        add_action( 'wp_ajax_tts_import_data', array( $this, 'ajax_import_data' ) );
        add_action( 'wp_ajax_tts_system_maintenance', array( $this, 'ajax_system_maintenance' ) );
        add_action( 'wp_ajax_tts_generate_report', array( $this, 'ajax_generate_report' ) );
        add_action( 'wp_ajax_tts_quick_connection_check', array( $this, 'ajax_quick_connection_check' ) );
        add_action( 'wp_ajax_tts_refresh_health', array( $this, 'ajax_refresh_health' ) );
        add_action( 'wp_ajax_tts_save_social_settings', array( $this, 'ajax_save_social_settings' ) );
        add_action( 'wp_ajax_tts_show_export_modal', array( $this, 'ajax_show_export_modal' ) );
        add_action( 'wp_ajax_tts_show_import_modal', array( $this, 'ajax_show_import_modal' ) );
        add_action( 'wp_ajax_tts_test_client_connections', array( $this, 'ajax_test_client_connections' ) );
        add_action( 'wp_ajax_tts_test_single_connection', array( $this, 'ajax_test_single_connection' ) );
        add_filter( 'manage_tts_social_post_posts_columns', array( $this, 'add_approved_column' ) );
        add_action( 'manage_tts_social_post_posts_custom_column', array( $this, 'render_approved_column' ), 10, 2 );
        add_filter( 'bulk_actions-edit-tts_social_post', array( $this, 'register_bulk_actions' ) );
        add_filter( 'handle_bulk_actions-edit-tts_social_post', array( $this, 'handle_bulk_actions' ), 10, 3 );
    }

    /**
     * Register plugin menu pages.
     */
    public function register_menu() {
        // Verify that all required methods exist before registering menus
        $required_methods = array(
            'render_dashboard_page',
            'render_content_management_page', 
            'render_clients_page',
            'tts_render_client_wizard',
            'render_social_posts_page',
            'render_connection_test_page',
            'render_settings_page',
            'render_social_connections_page',
            'render_help_page',
            'render_calendar_page',
            'render_analytics_page',
            'render_health_page',
            'render_log_page',
            'render_ai_features_page',
            'render_frequency_status_page'
        );
        
        foreach ( $required_methods as $method ) {
            if ( ! method_exists( $this, $method ) ) {
                error_log( "TTS_Admin: Missing method $method" );
            }
        }
        
        // Main menu page
        add_menu_page(
            __( 'FP Publisher', 'fp-publisher' ),
            __( 'FP Publisher', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-main',
            array( $this, 'render_dashboard_page' ),
            'dashicons-share-alt',
            25
        );

        // Dashboard as first submenu (same as main page)
        add_submenu_page(
            'fp-publisher-main',
            __( 'Dashboard', 'fp-publisher' ),
            __( 'Dashboard', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-main',
            array( $this, 'render_dashboard_page' )
        );

        // Content Management submenu - NEW
        add_submenu_page(
            'fp-publisher-main',
            __( 'Content Management', 'fp-publisher' ),
            __( 'Content Management', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-content',
            array( $this, 'render_content_management_page' )
        );

        // Clients submenu - moved under main menu properly
        add_submenu_page(
            'fp-publisher-main',
            __( 'Clienti', 'fp-publisher' ),
            __( 'Clienti', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-clienti',
            array( $this, 'render_clients_page' )
        );

        // Client Wizard submenu
        add_submenu_page(
            'fp-publisher-main',
            __( 'Client Wizard', 'fp-publisher' ),
            __( 'Client Wizard', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-client-wizard',
            array( $this, 'tts_render_client_wizard' )
        );

        // Social Posts submenu - moved under main menu properly
        add_submenu_page(
            'fp-publisher-main',
            __( 'Social Post', 'fp-publisher' ),
            __( 'Social Post', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-social-posts',
            array( $this, 'render_social_posts_page' )
        );

        // Connection Testing submenu - NEW
        add_submenu_page(
            'fp-publisher-main',
            __( 'Test Connections', 'fp-publisher' ),
            __( 'Test Connections', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-test-connections',
            array( $this, 'render_connection_test_page' )
        );

        // Settings submenu
        add_submenu_page(
            'fp-publisher-main',
            __( 'Settings', 'fp-publisher' ),
            __( 'Settings', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-settings',
            array( $this, 'render_settings_page' )
        );

        // Social Connections submenu
        add_submenu_page(
            'fp-publisher-main',
            __( 'Social Connections', 'fp-publisher' ),
            __( 'Social Connections', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-social-connections',
            array( $this, 'render_social_connections_page' )
        );

        // Help submenu
        add_submenu_page(
            'fp-publisher-main',
            __( 'Help & Setup', 'fp-publisher' ),
            __( 'Help & Setup', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-help',
            array( $this, 'render_help_page' )
        );

        // Calendar submenu
        add_submenu_page(
            'fp-publisher-main',
            __( 'Calendario', 'fp-publisher' ),
            __( 'Calendario', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-calendar',
            array( $this, 'render_calendar_page' )
        );

        // Analytics submenu
        add_submenu_page(
            'fp-publisher-main',
            __( 'Analytics', 'fp-publisher' ),
            __( 'Analytics', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-analytics',
            array( $this, 'render_analytics_page' )
        );

        // Health Status submenu
        add_submenu_page(
            'fp-publisher-main',
            __( 'Stato', 'fp-publisher' ),
            __( 'Stato', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-health',
            array( $this, 'render_health_page' )
        );

        // Log submenu
        add_submenu_page(
            'fp-publisher-main',
            __( 'Log', 'fp-publisher' ),
            __( 'Log', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-log',
            array( $this, 'render_log_page' )
        );

        // AI Features submenu
        add_submenu_page(
            'fp-publisher-main',
            __( 'AI & Advanced Features', 'fp-publisher' ),
            __( 'AI & Advanced Features', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-ai-features',
            array( $this, 'render_ai_features_page' )
        );

        // Frequency Status submenu
        add_submenu_page(
            'fp-publisher-main',
            __( 'Publishing Status', 'fp-publisher' ),
            __( 'Publishing Status', 'fp-publisher' ),
            'manage_options',
            'fp-publisher-frequency-status',
            array( $this, 'render_frequency_status_page' )
        );
    }

    /**
     * Enqueue assets for the dashboard page.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_dashboard_assets( $hook ) {
        // Optimized hook checking - only load on FP Publisher pages
        $fp_publisher_pages = array(
            'toplevel_page_fp-publisher-main',
            'fp-publisher_page_fp-publisher-main',
            'fp-publisher_page_fp-publisher-ai-features',
            'fp-publisher_page_fp-publisher-analytics',
            'fp-publisher_page_fp-publisher-calendar',
            'fp-publisher_page_fp-publisher-client-wizard',
            'fp-publisher_page_fp-publisher-clienti',
            'fp-publisher_page_fp-publisher-content',
            'fp-publisher_page_fp-publisher-frequency-status',
            'fp-publisher_page_fp-publisher-health',
            'fp-publisher_page_fp-publisher-help',
            'fp-publisher_page_fp-publisher-log',
            'fp-publisher_page_fp-publisher-settings',
            'fp-publisher_page_fp-publisher-social-connections',
            'fp-publisher_page_fp-publisher-social-posts',
            'fp-publisher_page_fp-publisher-test-connections'
        );

        if ( ! in_array( $hook, $fp_publisher_pages, true ) ) {
            return;
        }

        // Core assets - loaded on all FP Publisher pages with conditional loading
        $this->enqueue_core_assets( $hook );

        // Page-specific assets with lazy loading
        $this->enqueue_page_specific_assets( $hook );
    }

    /**
     * Enqueue core assets needed on all TTS pages with conditional loading.
     *
     * @param string $hook Current admin page hook.
     */
    private function enqueue_core_assets( $hook ) {
        // Essential styles with version based on file modification time for better caching
        $css_version = filemtime( plugin_dir_path( __FILE__ ) . 'css/tts-core.css' );
        wp_enqueue_style(
            'tts-core',
            plugin_dir_url( __FILE__ ) . 'css/tts-core.css',
            array(),
            $css_version
        );

        $notifications_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/tts-notifications.js' );
        wp_register_script(
            'tts-notifications',
            plugin_dir_url( __FILE__ ) . 'js/tts-notifications.js',
            array(),
            $notifications_version,
            true
        );
        wp_enqueue_script( 'tts-notifications' );

        $admin_utils_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/tts-admin-utils.js' );
        wp_register_script(
            'tts-admin-utils',
            plugin_dir_url( __FILE__ ) . 'js/tts-admin-utils.js',
            array( 'tts-notifications', 'wp-util' ),
            $admin_utils_version,
            true
        );
        wp_enqueue_script( 'tts-admin-utils' );

        $help_system_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/tts-help-system.js' );
        wp_register_script(
            'tts-help-system',
            plugin_dir_url( __FILE__ ) . 'js/tts-help-system.js',
            array( 'tts-admin-utils' ),
            $help_system_version,
            true
        );
        wp_enqueue_script( 'tts-help-system' );

        // Essential JavaScript with optimized dependencies
        $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/tts-core.js' );
        wp_enqueue_script(
            'tts-core',
            plugin_dir_url( __FILE__ ) . 'js/tts-core.js',
            array( 'jquery', 'tts-notifications' ),
            $js_version,
            true
        );

        // Localize core script with minimal data
        wp_localize_script( 'tts-core', 'tts_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'tts_ajax_nonce' ),
            'current_page' => $hook, // Pass current page for conditional frontend logic
        ));
    }

    /**
     * Enqueue page-specific assets with conditional loading.
     *
     * @param string $hook Current admin page hook.
     */
    private function enqueue_page_specific_assets( $hook ) {
        switch ( $hook ) {
            case 'toplevel_page_fp-publisher-main':
            case 'fp-publisher_page_fp-publisher-main':
                $this->enqueue_dashboard_specific_assets();
                break;
            case 'fp-publisher_page_fp-publisher-calendar':
                $this->enqueue_calendar_assets();
                break;
            case 'fp-publisher_page_fp-publisher-analytics':
                $this->enqueue_analytics_assets();
                break;
            case 'fp-publisher_page_fp-publisher-social-connections':
                $this->enqueue_social_connections_assets();
                break;
            case 'fp-publisher_page_fp-publisher-client-wizard':
                // Wizard assets are handled separately to avoid duplication
                break;
            case 'fp-publisher_page_fp-publisher-health':
                $this->enqueue_health_assets();
                break;
            case 'fp-publisher_page_fp-publisher-ai-features':
                $this->enqueue_ai_features_assets();
                break;
            case 'fp-publisher_page_fp-publisher-social-posts':
            case 'fp-publisher_page_fp-publisher-settings':
            case 'fp-publisher_page_fp-publisher-test-connections':
            case 'fp-publisher_page_fp-publisher-help':
            case 'fp-publisher_page_fp-publisher-frequency-status':
                $this->enqueue_shared_admin_page_assets();
                break;
        }

        if ( in_array( $hook, array( 'fp-publisher_page_fp-publisher-clienti', 'fp-publisher_page_fp-publisher-content' ), true ) ) {
            $this->enqueue_shared_admin_page_assets();
        }
    }

    /**
     * Enqueue shared assets for admin pages without dedicated bundles.
     */
    private function enqueue_shared_admin_page_assets() {
        $css_path = plugin_dir_path( __FILE__ ) . 'css/tts-optimized.css';
        if ( file_exists( $css_path ) ) {
            $css_version = filemtime( $css_path );
            wp_enqueue_style(
                'tts-optimized',
                plugin_dir_url( __FILE__ ) . 'css/tts-optimized.css',
                array( 'tts-core' ),
                $css_version
            );
        }

        $js_path = plugin_dir_path( __FILE__ ) . 'js/tts-optimized-core.js';
        if ( file_exists( $js_path ) ) {
            $js_version = filemtime( $js_path );
            wp_enqueue_script(
                'tts-optimized-core',
                plugin_dir_url( __FILE__ ) . 'js/tts-optimized-core.js',
                array( 'tts-core', 'tts-admin-utils' ),
                $js_version,
                true
            );
        }
    }

    /**
     * Enqueue dashboard-specific assets.
     */
    private function enqueue_dashboard_specific_assets() {
        $css_version = filemtime( plugin_dir_path( __FILE__ ) . 'css/tts-dashboard.css' );
        wp_enqueue_style(
            'tts-dashboard',
            plugin_dir_url( __FILE__ ) . 'css/tts-dashboard.css',
            array( 'tts-core' ),
            $css_version
        );

        if ( ! $this->dashboard_needs_react_components() ) {
            return;
        }

        $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/tts-dashboard.js' );
        wp_enqueue_script(
            'tts-dashboard',
            plugin_dir_url( __FILE__ ) . 'js/tts-dashboard.js',
            array( 'tts-core', 'tts-notifications', 'wp-element', 'wp-components', 'wp-api-fetch' ),
            $js_version,
            true
        );
    }

    /**
     * Check if dashboard needs React components.
     *
     * @return bool
     */
    private function dashboard_needs_react_components() {
        // Load the React bundle for users who can manage the plugin.
        return current_user_can( 'manage_options' );
    }

    /**
     * Enqueue calendar specific assets.
     */
    private function enqueue_calendar_assets() {
        $css_version = filemtime( plugin_dir_path( __FILE__ ) . 'css/tts-calendar.css' );
        wp_enqueue_style(
            'tts-calendar',
            plugin_dir_url( __FILE__ ) . 'css/tts-calendar.css',
            array( 'tts-core' ),
            $css_version
        );

        $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/tts-calendar.js' );
        wp_enqueue_script(
            'tts-calendar',
            plugin_dir_url( __FILE__ ) . 'js/tts-calendar.js',
            array( 'tts-core' ),
            $js_version,
            true
        );
    }

    /**
     * Enqueue health page specific assets.
     */
    private function enqueue_health_assets() {
        $css_version = filemtime( plugin_dir_path( __FILE__ ) . 'css/tts-health.css' );
        wp_enqueue_style(
            'tts-health',
            plugin_dir_url( __FILE__ ) . 'css/tts-health.css',
            array( 'tts-core' ),
            $css_version
        );
    }

    /**
     * Enqueue AI features specific assets.
     */
    private function enqueue_ai_features_assets() {
        $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/tts-advanced-features.js' );
        wp_enqueue_script(
            'tts-advanced-features',
            plugin_dir_url( __FILE__ ) . 'js/tts-advanced-features.js',
            array( 'tts-core', 'tts-notifications', 'tts-admin-utils', 'tts-help-system' ),
            $js_version,
            true
        );
    }

    /**
     * Enqueue social connections specific assets.
     */
    private function enqueue_social_connections_assets() {
        $css_version = filemtime( plugin_dir_path( __FILE__ ) . 'css/tts-social-connections.css' );
        wp_enqueue_style(
            'tts-social-connections',
            plugin_dir_url( __FILE__ ) . 'css/tts-social-connections.css',
            array( 'tts-core' ),
            $css_version
        );

        $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/tts-social-connections.js' );
        wp_enqueue_script(
            'tts-social-connections',
            plugin_dir_url( __FILE__ ) . 'js/tts-social-connections.js',
            array( 'tts-core' ),
            $js_version,
            true
        );

        wp_localize_script(
            'tts-social-connections',
            'ttsSocialConnections',
            array(
                'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
                'testConnectionNonce'  => wp_create_nonce( 'tts_test_connection' ),
                'checkRateLimitsNonce' => wp_create_nonce( 'tts_check_rate_limits' ),
            )
        );
    }

    /**
     * Enqueue analytics specific assets with conditional Chart.js loading.
     */
    private function enqueue_analytics_assets() {
        $css_version = filemtime( plugin_dir_path( __FILE__ ) . 'css/tts-analytics.css' );
        wp_enqueue_style(
            'tts-analytics',
            plugin_dir_url( __FILE__ ) . 'css/tts-analytics.css',
            array( 'tts-core' ),
            $css_version
        );

        // Load Chart.js from CDN with integrity check for better performance
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js',
            array(),
            '4.4.0',
            true
        );

        $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/tts-analytics.js' );
        wp_enqueue_script(
            'tts-analytics',
            plugin_dir_url( __FILE__ ) . 'js/tts-analytics.js',
            array( 'tts-core', 'chart-js' ),
            $js_version,
            true
        );

        $localized_data = array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'tts_analytics' ),
            'chartColors'  => $this->get_chart_color_scheme(),
            'data'         => array(),
        );

        /**
         * Filters the localized data made available to the analytics script.
         *
         * This allows other components (e.g. the analytics page renderer) to
         * inject the chart dataset so everything travels in a single
         * configuration object.
         *
         * @param array $localized_data Localized configuration for the script.
         */
        $localized_data = apply_filters( 'tts_analytics_localized_data', $localized_data );

        if ( ! isset( $localized_data['data'] ) ) {
            $localized_data['data'] = array();
        }

        wp_localize_script(
            'tts-analytics',
            'ttsAnalytics',
            $localized_data
        );
    }

    /**
     * Get optimized chart color scheme.
     *
     * @return array Color scheme for charts.
     */
    private function get_chart_color_scheme() {
        return array(
            'primary' => '#135e96',
            'secondary' => '#f56e28',
            'success' => '#00a32a',
            'warning' => '#f6c23e',
            'error' => '#dc3545',
            'info' => '#17a2b8',
        );
    }

    /**
     * Enqueue optimized wizard assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_wizard_assets( $hook ) {
        if ( 'fp-publisher_page_fp-publisher-client-wizard' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'tts-wizard',
            plugin_dir_url( __FILE__ ) . 'js/tts-wizard.js',
            array( 'tts-core' ),
            '1.1',
            true
        );

        wp_localize_script(
            'tts-wizard',
            'ttsWizard',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'tts_wizard' ),
                'strings' => array(
                    'validating' => __( 'Validating...', 'fp-publisher' ),
                    'connecting' => __( 'Connecting...', 'fp-publisher' ),
                    'success' => __( 'Success!', 'fp-publisher' ),
                    'error' => __( 'Error occurred', 'fp-publisher' ),
                )
            )
        );
    }

    /**
     * Enqueue optimized media assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_media_assets( $hook ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'tts_social_post' !== $screen->post_type ) {
            return;
        }

        $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/tts-media.js' );
        wp_enqueue_script(
            'tts-media',
            plugin_dir_url( __FILE__ ) . 'js/tts-media.js',
            array( 'tts-core', 'media-editor', 'jquery-ui-sortable' ),
            $js_version,
            true
        );
    }

    /**
     * Register dashboard widget listing scheduled social posts.
     */
    public function register_scheduled_posts_widget() {
        wp_add_dashboard_widget(
            'tts_scheduled_posts',
            __( 'Social Post programmati', 'fp-publisher' ),
            array( $this, 'render_scheduled_posts_widget' )
        );
    }

    /**
     * Render the scheduled social posts widget with optimized database queries and caching.
     */
    public function render_scheduled_posts_widget() {
        // Check cache first
        $cache_key = 'tts_scheduled_posts_widget_' . get_current_user_id();
        $cached_output = get_transient( $cache_key );
        
        if ( false !== $cached_output ) {
            echo $cached_output;
            return;
        }

        // Optimized query with specific fields and meta_value ordering
        $posts = get_posts(
            array(
                'post_type'      => 'tts_social_post',
                'posts_per_page' => 5,
                'post_status'    => 'any',
                'fields'         => 'ids', // Only get IDs to reduce memory usage
                'meta_key'       => '_tts_publish_at',
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'meta_query'     => array(
                    array(
                        'key'     => '_tts_publish_at',
                        'value'   => current_time( 'mysql' ),
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ),
                ),
                'suppress_filters' => true, // Avoid unnecessary filter execution
            )
        );

        if ( empty( $posts ) ) {
            $output = '<p>' . esc_html__( 'Nessun post programmato.', 'fp-publisher' ) . '</p>';
            set_transient( $cache_key, $output, 300 ); // Cache for 5 minutes
            echo $output;
            return;
        }

        // Batch fetch meta data to reduce database queries
        global $wpdb;
        $post_ids_placeholders = implode( ',', array_fill( 0, count( $posts ), '%d' ) );
        $meta_results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_key, meta_value 
                FROM {$wpdb->postmeta} 
                WHERE post_id IN ($post_ids_placeholders) 
                AND meta_key IN ('_tts_social_channel', '_tts_publish_at')",
                ...$posts
            )
        );

        // Organize meta data by post ID
        $meta_by_post = array();
        foreach ( $meta_results as $meta_row ) {
            $meta_by_post[ $meta_row->post_id ][ $meta_row->meta_key ] = $meta_row->meta_value;
        }

        $output = '<ul>';
        foreach ( $posts as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                continue;
            }
            
            $channel = isset( $meta_by_post[ $post_id ]['_tts_social_channel'] ) 
                ? maybe_unserialize( $meta_by_post[ $post_id ]['_tts_social_channel'] )
                : '';
            $publish_at = isset( $meta_by_post[ $post_id ]['_tts_publish_at'] ) 
                ? $meta_by_post[ $post_id ]['_tts_publish_at']
                : '';
            
            $edit_link = get_edit_post_link( $post_id );
            $channel_display = is_array( $channel ) ? implode( ', ', $channel ) : $channel;
            
            $output .= sprintf(
                '<li><a href="%s">%s</a> - %s - %s</li>',
                esc_url( $edit_link ),
                esc_html( $post->post_title ),
                esc_html( $channel_display ),
                esc_html( $publish_at ? date_i18n( 'Y-m-d H:i', strtotime( $publish_at ) ) : '' )
            );
        }
        $output .= '</ul>';

        // Cache the output for 5 minutes
        set_transient( $cache_key, $output, 300 );
        echo $output;
    }

    /**
     * Enqueue assets for the dashboard widget.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_widget_assets( $hook ) {
        if ( 'index.php' !== $hook ) {
            return;
        }

        $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/tts-dashboard-widget.js' );
        wp_enqueue_script(
            'tts-dashboard-widget',
            plugin_dir_url( __FILE__ ) . 'js/tts-dashboard-widget.js',
            array( 'jquery' ),
            $js_version,
            true
        );

        wp_localize_script(
            'tts-dashboard-widget',
            'ttsDashboardWidget',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'tts_dashboard_widget' ),
            )
        );
    }

    /**
     * AJAX callback: fetch lists for a Trello board.
     */
    public function ajax_get_lists() {
        check_ajax_referer( 'tts_wizard', 'nonce' );

        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'You do not have permission to perform this action.', 'fp-publisher' ) );
        }

        $board = isset( $_POST['board'] ) ? sanitize_text_field( $_POST['board'] ) : '';
        $key   = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : '';
        $token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';

        // Enhanced validation with specific error messages
        if ( empty( $board ) ) {
            wp_send_json_error( __( 'Board ID is required.', 'fp-publisher' ) );
        }
        if ( empty( $key ) ) {
            wp_send_json_error( __( 'Trello API key is required.', 'fp-publisher' ) );
        }
        if ( empty( $token ) ) {
            wp_send_json_error( __( 'Trello token is required.', 'fp-publisher' ) );
        }

        // Validate board ID format (should be 24 character hex string)
        if ( ! preg_match( '/^[a-f0-9]{24}$/i', $board ) ) {
            wp_send_json_error( __( 'Invalid board ID format.', 'fp-publisher' ) );
        }

        $response = wp_remote_get(
            'https://api.trello.com/1/boards/' . rawurlencode( $board ) . '/lists?key=' . rawurlencode( $key ) . '&token=' . rawurlencode( $token ),
            array( 'timeout' => 20 )
        );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'TTS AJAX Error: ' . $response->get_error_message() );
            wp_send_json_error( 
                sprintf( 
                    __( 'Failed to connect to Trello API: %s', 'fp-publisher' ), 
                    $response->get_error_message() 
                ) 
            );
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        if ( $http_code !== 200 ) {
            error_log( "TTS AJAX Error: HTTP $http_code from Trello API" );
            wp_send_json_error( 
                sprintf( 
                    __( 'Trello API returned error code %d. Please check your credentials.', 'fp-publisher' ), 
                    $http_code 
                ) 
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log( 'TTS AJAX Error: Invalid JSON response from Trello API' );
            wp_send_json_error( __( 'Invalid response from Trello API.', 'fp-publisher' ) );
        }

        wp_send_json_success( $data );
    }

    /**
     * AJAX callback: refresh posts data for dashboard.
     */
    public function ajax_refresh_posts() {
        check_ajax_referer( 'tts_dashboard', 'nonce' );

        // Check user capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'You do not have permission to view posts.', 'fp-publisher' ) );
        }

        try {
            $posts = get_posts(array(
                'post_type' => 'tts_social_post',
                'posts_per_page' => 10,
                'post_status' => 'any',
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => '_tts_publish_at',
                        'compare' => 'EXISTS'
                    ),
                    array(
                        'key' => '_tts_publish_at',
                        'compare' => 'NOT EXISTS'
                    )
                )
            ));

            if ( empty( $posts ) ) {
                wp_send_json_success( array(
                    'posts' => array(),
                    'message' => __( 'No posts found.', 'fp-publisher' ),
                    'timestamp' => current_time( 'timestamp' )
                ) );
            }

            $formatted_posts = array();
            foreach ( $posts as $post ) {
                $channel = get_post_meta( $post->ID, '_tts_social_channel', true );
                $status = get_post_meta( $post->ID, '_published_status', true );
                $publish_at = get_post_meta( $post->ID, '_tts_publish_at', true );
                
                $formatted_posts[] = array(
                    'ID' => intval( $post->ID ),
                    'title' => wp_trim_words( $post->post_title, 10 ),
                    'channel' => is_array( $channel ) ? $channel : array( $channel ),
                    'status' => $status ?: 'scheduled',
                    'publish_at' => $publish_at ?: $post->post_date,
                    'edit_link' => current_user_can( 'edit_post', $post->ID ) ? get_edit_post_link( $post->ID ) : ''
                );
            }

            wp_send_json_success( array(
                'posts' => $formatted_posts,
                'message' => sprintf( 
                    _n( 
                        '%d post refreshed successfully', 
                        '%d posts refreshed successfully', 
                        count( $formatted_posts ), 
                        'fp-publisher' 
                    ), 
                    count( $formatted_posts ) 
                ),
                'timestamp' => current_time( 'timestamp' )
            ) );

        } catch ( Exception $e ) {
            error_log( 'TTS Refresh Posts Error: ' . $e->getMessage() );
            wp_send_json_error( __( 'An error occurred while refreshing posts. Please try again.', 'fp-publisher' ) );
        }
    }

    /**
     * AJAX callback: delete a social post.
     */
    public function ajax_delete_post() {
        check_ajax_referer( 'tts_dashboard', 'nonce' );

        // Rate limiting check
        if (!$this->check_rate_limit('delete_post', 20, 60)) {
            wp_send_json_error(__('Too many delete requests. Please wait a moment and try again.', 'fp-publisher'));
        }

        if (!current_user_can('delete_posts')) {
            wp_send_json_error(__('You do not have permission to delete posts.', 'fp-publisher'));
        }

        $post_id = isset($_POST['postId']) ? intval($_POST['postId']) : 0;
        
        if (!$post_id || $post_id <= 0) {
            wp_send_json_error(__('Invalid post ID.', 'fp-publisher'));
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'tts_social_post') {
            wp_send_json_error(__('Post not found.', 'fp-publisher'));
        }

        // Check specific delete permission for this post
        if (!current_user_can('delete_post', $post_id)) {
            wp_send_json_error(__('You do not have permission to delete this specific post.', 'fp-publisher'));
        }

        $result = wp_delete_post($post_id, true);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Post deleted successfully.', 'fp-publisher'),
                'refresh' => true
            ));
        } else {
            wp_send_json_error(__('Failed to delete post.', 'fp-publisher'));
        }
    }

    /**
     * AJAX callback: handle bulk actions on social posts.
     */
    public function ajax_bulk_action() {
        check_ajax_referer( 'tts_dashboard', 'nonce' );

        // Rate limiting check
        if (!$this->check_rate_limit('bulk_action', 10, 60)) {
            wp_send_json_error(__('Too many requests. Please wait a moment and try again.', 'fp-publisher'));
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'fp-publisher'));
        }

        $action = isset($_POST['bulkAction']) ? sanitize_text_field($_POST['bulkAction']) : '';
        $post_ids = isset($_POST['postIds']) ? array_map('intval', $_POST['postIds']) : array();

        // Input validation
        if (!$action || empty($post_ids)) {
            wp_send_json_error(__('Invalid action or no posts selected.', 'fp-publisher'));
        }

        // Validate action is allowed
        $allowed_actions = array('delete', 'approve', 'revoke');
        if (!in_array($action, $allowed_actions, true)) {
            wp_send_json_error(__('Invalid action specified.', 'fp-publisher'));
        }

        // Limit number of posts that can be processed at once
        if (count($post_ids) > 100) {
            wp_send_json_error(__('Too many posts selected. Please select 100 or fewer posts.', 'fp-publisher'));
        }

        $processed = 0;
        $errors = array();

        foreach ($post_ids as $post_id) {
            // Additional validation for each post ID
            if ($post_id <= 0) {
                $errors[] = __('Invalid post ID provided.', 'fp-publisher');
                continue;
            }

            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'tts_social_post') {
                $errors[] = sprintf(__('Post ID %d not found.', 'fp-publisher'), $post_id);
                continue;
            }

            switch ($action) {
                case 'delete':
                    if (current_user_can('delete_post', $post_id)) {
                        if (wp_delete_post($post_id, true)) {
                            $processed++;
                        } else {
                            $errors[] = sprintf(__('Failed to delete post ID %d.', 'fp-publisher'), $post_id);
                        }
                    } else {
                        $errors[] = sprintf(__('You do not have permission to delete post ID %d.', 'fp-publisher'), $post_id);
                    }
                    break;

                case 'approve':
                    if (current_user_can('edit_post', $post_id)) {
                        update_post_meta($post_id, '_tts_approved', true);
                        do_action('save_post_tts_social_post', $post_id, $post, true);
                        do_action('tts_post_approved', $post_id);
                        $processed++;
                    } else {
                        $errors[] = sprintf(__('You do not have permission to approve post ID %d.', 'fp-publisher'), $post_id);
                    }
                    break;

                case 'revoke':
                    if (current_user_can('edit_post', $post_id)) {
                        delete_post_meta($post_id, '_tts_approved');
                        do_action('save_post_tts_social_post', $post_id, $post, true);
                        $processed++;
                    } else {
                        $errors[] = sprintf(__('You do not have permission to revoke approval for post ID %d.', 'fp-publisher'), $post_id);
                    }
                    break;
            }
        }

        if ($processed > 0) {
            $message = sprintf(
                _n(
                    '%d post processed successfully.',
                    '%d posts processed successfully.',
                    $processed,
                    'fp-publisher'
                ),
                $processed
            );

            if (!empty($errors)) {
                $message .= ' ' . sprintf(__('However, %d errors occurred.', 'fp-publisher'), count($errors));
            }

            wp_send_json_success(array(
                'message' => $message,
                'processed' => $processed,
                'errors' => $errors,
                'refresh' => true
            ));
        } else {
            wp_send_json_error(__('No posts were processed.', 'fp-publisher') . ' ' . implode(' ', $errors));
        }
    }

    /**
     * Render the dashboard page.
     */
    public function render_dashboard_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Social Auto Publisher Dashboard', 'fp-publisher' ) . '</h1>';
        
        // Add notification area
        echo '<div id="tts-notification-area" style="margin: 15px 0;"></div>';
        
        // Health status banner (if there are issues)
        $this->render_health_status_banner();
        
        // Quick stats cards
        $this->render_dashboard_stats();
        
        // Enhanced monitoring section
        $this->render_monitoring_dashboard();
        
        // Recent activity and actions
        echo '<div class="tts-dashboard-sections">';
        echo '<div class="tts-dashboard-left">';
        $this->render_recent_posts_section();
        echo '</div>';
        
        echo '<div class="tts-dashboard-right">';
        $this->render_quick_actions_section();
        $this->render_connection_test_widget();
        $this->render_system_status_widget();
        echo '</div>';
        echo '</div>';
        
        // Advanced tools section
        $this->render_advanced_tools_section();
        
        // React component container for advanced features
        echo '<div id="tts-dashboard-root"></div>';
        echo '</div>';
    }

    /**
     * Render health status banner.
     */
    private function render_health_status_banner() {
        $health_status = TTS_Monitoring::get_current_health_status();
        
        if ( $health_status['status'] === 'critical' || $health_status['status'] === 'warning' ) {
            $banner_class = $health_status['status'] === 'critical' ? 'error' : 'warning';
            echo '<div class="notice notice-' . $banner_class . ' is-dismissible tts-health-banner">';
            echo '<div style="display: flex; align-items: center; gap: 15px;">';
            echo '<span style="font-size: 24px;">' . ( $health_status['status'] === 'critical' ? '🚨' : '⚠️' ) . '</span>';
            echo '<div>';
            echo '<h3 style="margin: 0;">System Health Alert</h3>';
            echo '<p style="margin: 5px 0 0 0;">' . esc_html( $health_status['message'] ) . '</p>';
            if ( ! empty( $health_status['alerts'] ) ) {
                echo '<p style="margin: 5px 0 0 0; font-size: 12px;">Issues: ';
                $issue_types = array_unique( array_column( $health_status['alerts'], 'type' ) );
                echo esc_html( implode( ', ', $issue_types ) );
                echo '</p>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Render monitoring dashboard.
     */
    private function render_monitoring_dashboard() {
        echo '<div class="tts-monitoring-section">';
        echo '<h2>' . esc_html__( 'System Monitoring', 'fp-publisher' ) . '</h2>';
        
        echo '<div class="tts-monitoring-grid">';
        
        // Real-time health score
        $this->render_health_score_widget();
        
        // Performance metrics
        $this->render_performance_metrics_widget();
        
        // API status
        $this->render_api_status_widget();
        
        // Recent activity
        $this->render_activity_timeline_widget();
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render health score widget.
     */
    private function render_health_score_widget() {
        $health_status = TTS_Monitoring::get_current_health_status();
        
        echo '<div class="tts-monitoring-card tts-health-score-card">';
        echo '<div class="tts-card-header">';
        echo '<h3>' . esc_html__( 'System Health', 'fp-publisher' ) . '</h3>';
        echo '<button class="tts-btn small" data-ajax-action="tts_refresh_health" data-loading-text="' . esc_attr__( 'Checking...', 'fp-publisher' ) . '">';
        echo esc_html__( 'Refresh', 'fp-publisher' );
        echo '</button>';
        echo '</div>';
        
        echo '<div class="tts-health-score-display">';
        $score = $health_status['score'];
        $score_class = $score >= 90 ? 'excellent' : ( $score >= 70 ? 'good' : 'needs-attention' );
        
        echo '<div class="tts-score-circle ' . $score_class . '" style="--score-percent: ' . $score . '%;">';
        echo '<div class="tts-score-text">' . $score . '</div>';
        echo '</div>';
        
        echo '<div class="tts-health-status">';
        echo '<h4>' . esc_html( ucfirst( $health_status['status'] ) ) . '</h4>';
        echo '<p>' . esc_html( $health_status['message'] ) . '</p>';
        if ( $health_status['last_check'] ) {
            echo '<p class="tts-last-check">Last check: ' . esc_html( human_time_diff( strtotime( $health_status['last_check'] ) ) ) . ' ago</p>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render performance metrics widget.
     */
    private function render_performance_metrics_widget() {
        $performance = TTS_Performance::get_performance_metrics();
        
        echo '<div class="tts-monitoring-card">';
        echo '<div class="tts-card-header">';
        echo '<h3>' . esc_html__( 'Performance Metrics', 'fp-publisher' ) . '</h3>';
        echo '</div>';
        
        echo '<div class="tts-metrics-display">';
        
        // Database performance
        if ( isset( $performance['database'] ) ) {
            $db_status = isset( $performance['database']['status'] ) ? $performance['database']['status'] : 'unknown';
            $status_icon = $db_status === 'excellent' ? '🟢' : ( $db_status === 'good' ? '🟡' : '🔴' );
            
            echo '<div class="tts-metric-item">';
            echo '<span class="tts-metric-label">' . $status_icon . ' Database</span>';
            echo '<span class="tts-metric-value">' . ( isset( $performance['database']['response_ms'] ) ? $performance['database']['response_ms'] : 'N/A' ) . 'ms</span>';
            echo '</div>';
        }
        
        // Memory usage
        if ( isset( $performance['memory'] ) ) {
            $memory_status = isset( $performance['memory']['status'] ) ? $performance['memory']['status'] : 'unknown';
            $status_icon = $memory_status === 'good' ? '🟢' : '🟡';
            
            echo '<div class="tts-metric-item">';
            echo '<span class="tts-metric-label">' . $status_icon . ' Memory</span>';
            echo '<span class="tts-metric-value">' . ( isset( $performance['memory']['usage_percent'] ) ? $performance['memory']['usage_percent'] : 'N/A' ) . '%</span>';
            echo '</div>';
        }
        
        // Cache performance
        if ( isset( $performance['cache'] ) ) {
            $cache_status = isset( $performance['cache']['status'] ) ? $performance['cache']['status'] : 'unknown';
            $status_icon = $cache_status === 'excellent' ? '🟢' : ( $cache_status === 'good' ? '🟡' : '🔴' );
            
            echo '<div class="tts-metric-item">';
            echo '<span class="tts-metric-label">' . $status_icon . ' Cache</span>';
            echo '<span class="tts-metric-value">' . ( isset( $performance['cache']['hit_ratio'] ) ? $performance['cache']['hit_ratio'] : 'N/A' ) . '%</span>';
            echo '</div>';
        }
        
        // Performance score
        if ( isset( $performance['score'] ) ) {
            echo '<div class="tts-metric-item">';
            echo '<span class="tts-metric-label">Overall Score</span>';
            echo '<span class="tts-metric-value">' . $performance['score'] . '/100</span>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render API status widget.
     */
    private function render_api_status_widget() {
        $health_data = get_option( 'tts_last_health_check', array() );
        $api_status = isset( $health_data['checks']['api_connections'] ) ? $health_data['checks']['api_connections'] : array();
        
        echo '<div class="tts-monitoring-card">';
        echo '<div class="tts-card-header">';
        echo '<h3>' . esc_html__( 'API Connections', 'fp-publisher' ) . '</h3>';
        echo '</div>';
        
        echo '<div class="tts-api-status-display">';
        
        if ( ! empty( $api_status['platform_status'] ) ) {
            foreach ( $api_status['platform_status'] as $platform => $status ) {
                $platform_icon = array(
                    'facebook' => '📘',
                    'instagram' => '📷',
                    'youtube' => '🎥',
                    'tiktok' => '🎵'
                );
                
                $status_icon = $status['success'] ? '🟢' : '🔴';
                $status_text = $status['success'] ? 'Connected' : 'Failed';
                
                echo '<div class="tts-api-platform-item">';
                echo '<span class="tts-platform-icon">' . ( $platform_icon[$platform] ?? '📱' ) . '</span>';
                echo '<span class="tts-platform-name">' . esc_html( ucfirst( $platform ) ) . '</span>';
                echo '<span class="tts-platform-status">' . $status_icon . ' ' . esc_html( $status_text ) . '</span>';
                echo '</div>';
            }
        } else {
            echo '<p class="tts-no-data">' . esc_html__( 'No API connection data available', 'fp-publisher' ) . '</p>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render activity timeline widget.
     */
    private function render_activity_timeline_widget() {
        global $wpdb;
        
        // Get recent activity logs
        $recent_logs = $wpdb->get_results( $wpdb->prepare( "
            SELECT channel, status, message, created_at
            FROM {$wpdb->prefix}tts_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
            ORDER BY created_at DESC
            LIMIT 10
        ", 24 ), ARRAY_A );
        
        echo '<div class="tts-monitoring-card tts-activity-timeline">';
        echo '<div class="tts-card-header">';
        echo '<h3>' . esc_html__( 'Recent Activity', 'fp-publisher' ) . '</h3>';
        echo '</div>';
        
        echo '<div class="tts-timeline-container">';
        
        if ( ! empty( $recent_logs ) ) {
            foreach ( $recent_logs as $log ) {
                $status_icon = $log['status'] === 'success' ? '✅' : 
                              ( $log['status'] === 'error' ? '❌' : '⚠️' );
                
                $channel = ! empty( $log['channel'] ) ? $log['channel'] : __( 'Unknown channel', 'fp-publisher' );

                echo '<div class="tts-timeline-item">';
                echo '<div class="tts-timeline-icon">' . $status_icon . '</div>';
                echo '<div class="tts-timeline-content">';
                echo '<div class="tts-timeline-event">' . esc_html( $channel ) . '</div>';
                echo '<div class="tts-timeline-message">' . esc_html( wp_trim_words( $log['message'], 10 ) ) . '</div>';
                echo '<div class="tts-timeline-time">' . esc_html( human_time_diff( strtotime( $log['created_at'] ) ) ) . ' ago</div>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<p class="tts-no-data">' . esc_html__( 'No recent activity', 'fp-publisher' ) . '</p>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render advanced tools section.
     */
    private function render_advanced_tools_section() {
        echo '<div class="tts-advanced-tools-section">';
        echo '<h2>' . esc_html__( 'Advanced Tools', 'fp-publisher' ) . '</h2>';
        
        echo '<div class="tts-tools-grid">';
        
        // Export/Import Tools
        echo '<div class="tts-tool-card">';
        echo '<h3>📦 ' . esc_html__( 'Export & Import', 'fp-publisher' ) . '</h3>';
        echo '<p>' . esc_html__( 'Backup your settings and data or migrate from another installation.', 'fp-publisher' ) . '</p>';
        echo '<div class="tts-tool-actions">';
        echo '<button class="tts-btn primary" data-ajax-action="tts_show_export_modal">';
        echo esc_html__( 'Export Data', 'fp-publisher' );
        echo '</button>';
        echo '<button class="tts-btn secondary" data-ajax-action="tts_show_import_modal">';
        echo esc_html__( 'Import Data', 'fp-publisher' );
        echo '</button>';
        echo '</div>';
        echo '</div>';
        
        // System Maintenance
        echo '<div class="tts-tool-card">';
        echo '<h3>🔧 ' . esc_html__( 'System Maintenance', 'fp-publisher' ) . '</h3>';
        echo '<p>' . esc_html__( 'Optimize database, clear cache, and perform system cleanup.', 'fp-publisher' ) . '</p>';
        echo '<div class="tts-tool-actions">';
        echo '<button class="tts-btn warning" data-ajax-action="tts_system_maintenance" data-confirm="' . esc_attr__( 'This will perform system maintenance. Continue?', 'fp-publisher' ) . '">';
        echo esc_html__( 'Run Maintenance', 'fp-publisher' );
        echo '</button>';
        echo '</div>';
        echo '</div>';
        
        // System Report
        echo '<div class="tts-tool-card">';
        echo '<h3>📊 ' . esc_html__( 'System Report', 'fp-publisher' ) . '</h3>';
        echo '<p>' . esc_html__( 'Generate comprehensive system report for troubleshooting.', 'fp-publisher' ) . '</p>';
        echo '<div class="tts-tool-actions">';
        echo '<button class="tts-btn info" data-ajax-action="tts_generate_report">';
        echo esc_html__( 'Generate Report', 'fp-publisher' );
        echo '</button>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render dashboard statistics cards with optimized queries and caching.
     */
    private function render_dashboard_stats() {
        // Use transient caching for expensive queries (cache for 5 minutes)
        $cache_key = 'tts_dashboard_stats_' . get_current_user_id();
        $stats = get_transient($cache_key);
        
        if (false === $stats) {
            $stats = $this->get_optimized_dashboard_statistics();
            set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
        }
        
        // Access variables directly from the stats array for security
        $total_posts = $stats['total_posts'];
        $total_clients = $stats['total_clients'];
        $scheduled_posts = $stats['scheduled_posts'];
        $published_today = $stats['published_today'];
        $published_yesterday = $stats['published_yesterday'];
        $failed_today = $stats['failed_today'];
        $success_rate = $stats['success_rate'];
        $trend_percentage = $stats['trend_percentage'];
        $next_scheduled = $stats['next_scheduled'];
        $weekly_average = $stats['weekly_average'];

        echo '<div class="tts-stats-row">';
        
        // Total Posts Card
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Total Posts', 'fp-publisher') . '</h3>';
        echo '<span class="tts-stat-number">' . intval($total_posts->publish + $total_posts->draft + $total_posts->private) . '</span>';
        echo '<div class="tts-stat-trend">All time posts created</div>';
        echo '<span class="tts-tooltiptext">Total number of social media posts created in the system</span>';
        echo '</div>';
        
        // Active Clients Card
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Active Clients', 'fp-publisher') . '</h3>';
        echo '<span class="tts-stat-number">' . intval($total_clients->publish) . '</span>';
        echo '<div class="tts-stat-trend">Currently configured</div>';
        echo '<span class="tts-tooltiptext">Number of clients with active social media configurations</span>';
        echo '</div>';
        
        // Scheduled Posts Card
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Scheduled Posts', 'fp-publisher') . '</h3>';
        echo '<span class="tts-stat-number">' . intval($scheduled_posts) . '</span>';
        echo '<div class="tts-stat-trend">Awaiting publication</div>';
        echo '<span class="tts-tooltiptext">Posts scheduled for future publication</span>';
        echo '</div>';
        
        // Published Today Card with Trend
        $today_count = intval($published_today);
        $trend_class = $trend_percentage > 0 ? 'positive' : ($trend_percentage < 0 ? 'negative' : '');
        $trend_icon = $trend_percentage > 0 ? '↗' : ($trend_percentage < 0 ? '↘' : '→');
        
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Published Today', 'fp-publisher') . '</h3>';
        echo '<span class="tts-stat-number">' . $today_count . '</span>';
        if ($published_yesterday > 0) {
            echo '<div class="tts-stat-trend ' . esc_attr($trend_class) . '">';
            echo esc_html($trend_icon . ' ' . abs($trend_percentage) . '% vs yesterday');
            echo '</div>';
        } else {
            echo '<div class="tts-stat-trend">Published today</div>';
        }
        echo '<span class="tts-tooltiptext">Posts successfully published today with trend comparison</span>';
        echo '</div>';

        echo '</div>';

        // Additional stats row for more detailed metrics
        echo '<div class="tts-stats-row">';
        
        // Failed Posts Today
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Failed Today', 'fp-publisher') . '</h3>';
        echo '<span class="tts-stat-number" style="color: #d63638;">' . $failed_today . '</span>';
        echo '<div class="tts-stat-trend">Requires attention</div>';
        echo '<span class="tts-tooltiptext">Posts that failed to publish today and need attention</span>';
        echo '</div>';

        // Success Rate (already calculated in optimized method)
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Success Rate', 'fp-publisher') . '</h3>';
        echo '<span class="tts-stat-number" style="color: ' . ($success_rate >= 95 ? '#00a32a' : ($success_rate >= 80 ? '#f56e28' : '#d63638')) . ';">' . $success_rate . '%</span>';
        echo '<div class="tts-stat-trend">Today\'s performance</div>';
        echo '<span class="tts-tooltiptext">Percentage of successful publications today</span>';
        echo '</div>';

        // Next Scheduled (already fetched in optimized method)
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Next Post', 'fp-publisher') . '</h3>';
        if ($next_scheduled) {
            $time_diff = human_time_diff(current_time('timestamp'), strtotime($next_scheduled->publish_at));
            echo '<span class="tts-stat-number" style="font-size: 20px;">in ' . $time_diff . '</span>';
            echo '<div class="tts-stat-trend">' . esc_html($next_scheduled->post_title) . '</div>';
        } else {
            echo '<span class="tts-stat-number" style="font-size: 20px;">None</span>';
            echo '<div class="tts-stat-trend">No posts scheduled</div>';
        }
        echo '<span class="tts-tooltiptext">Time until the next scheduled post publication</span>';
        echo '</div>';

        // Weekly Average (already calculated in optimized method)
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Daily Average', 'fp-publisher') . '</h3>';
        echo '<span class="tts-stat-number">' . $weekly_average . '</span>';
        echo '<div class="tts-stat-trend">Posts per day (7-day avg)</div>';
        echo '<span class="tts-tooltiptext">Average number of posts published per day over the last week</span>';
        echo '</div>';
        
        // Performance Metrics Card
        if ( isset( $stats['performance_metrics'] ) ) {
            $perf = $stats['performance_metrics'];
            echo '<div class="tts-stat-card tts-performance-card tts-tooltip">';
            echo '<h3>' . esc_html__('Performance', 'fp-publisher') . '</h3>';
            echo '<div class="tts-perf-metrics">';
            echo '<div class="tts-perf-item">DB: ' . ( isset( $perf['database_response_ms'] ) ? $perf['database_response_ms'] : 'N/A' ) . 'ms</div>';
            echo '<div class="tts-perf-item">Memory: ' . ( isset( $perf['memory_usage_mb'] ) ? $perf['memory_usage_mb'] : 'N/A' ) . 'MB</div>';
            echo '<div class="tts-perf-item">Cache: ' . ( isset( $perf['cache_hit_ratio'] ) ? $perf['cache_hit_ratio'] : 'N/A' ) . '%</div>';
            echo '</div>';
            echo '<span class="tts-tooltiptext">System performance metrics: database response time, memory usage, and cache hit ratio</span>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Render recent posts section.
     */
    private function render_recent_posts_section() {
        echo '<div class="tts-dashboard-section">';
        echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">';
        echo '<h2 style="margin: 0;">' . esc_html__('Recent Social Posts', 'fp-publisher') . '</h2>';
        echo '<div>';
        echo '<button class="tts-btn small" data-ajax-action="tts_refresh_posts" data-loading-text="' . esc_attr__('Refreshing...', 'fp-publisher') . '">';
        echo esc_html__('Refresh', 'fp-publisher');
        echo '</button>';
        echo '</div>';
        echo '</div>';
        
        $recent_posts = get_posts(array(
            'post_type' => 'tts_social_post',
            'posts_per_page' => 10,
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (!empty($recent_posts)) {
            echo '<div class="tts-enhanced-table-container">';
            echo '<table class="widefat tts-enhanced-table">';
            echo '<thead><tr>';
            echo '<th style="width: 20px;"><input type="checkbox" class="tts-bulk-select-all"></th>';
            echo '<th>' . esc_html__('Title', 'fp-publisher') . '</th>';
            echo '<th>' . esc_html__('Channel', 'fp-publisher') . '</th>';
            echo '<th>' . esc_html__('Status', 'fp-publisher') . '</th>';
            echo '<th>' . esc_html__('Date', 'fp-publisher') . '</th>';
            echo '<th>' . esc_html__('Actions', 'fp-publisher') . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($recent_posts as $post) {
                $channel = get_post_meta($post->ID, '_tts_social_channel', true);
                $status = get_post_meta($post->ID, '_published_status', true);
                $publish_at = get_post_meta($post->ID, '_tts_publish_at', true);
                
                // Determine status class and text
                $status_class = $status === 'published' ? 'success' : ($status === 'failed' ? 'error' : 'warning');
                $status_text = $status ?: __('Scheduled', 'fp-publisher');
                
                echo '<tr class="tts-list-item">';
                echo '<td><input type="checkbox" class="tts-bulk-select-item" value="' . esc_attr($post->ID) . '"></td>';
                echo '<td>';
                echo '<a href="' . esc_url(get_edit_post_link($post->ID)) . '" class="tts-tooltip">';
                echo '<strong>' . esc_html($post->post_title) . '</strong>';
                echo '<span class="tts-tooltiptext">' . esc_html__('Click to edit this post', 'fp-publisher') . '</span>';
                echo '</a>';
                echo '<div class="row-actions">';
                echo '<span class="edit"><a href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html__('Edit', 'fp-publisher') . '</a> | </span>';
                echo '<span class="delete"><a href="#" data-confirm="' . esc_attr__('Are you sure you want to delete this post?', 'fp-publisher') . '" data-dangerous data-ajax-action="tts_delete_post" data-post-id="' . esc_attr($post->ID) . '">' . esc_html__('Delete', 'fp-publisher') . '</a></span>';
                echo '</div>';
                echo '</td>';
                echo '<td>';
                if (is_array($channel)) {
                    foreach ($channel as $ch) {
                        echo '<span class="tts-status-badge info" style="margin-right: 5px;">' . esc_html($ch) . '</span>';
                    }
                } else {
                    echo '<span class="tts-status-badge info">' . esc_html($channel ?: __('No channel', 'fp-publisher')) . '</span>';
                }
                echo '</td>';
                echo '<td><span class="tts-status-badge ' . $status_class . '">' . esc_html($status_text) . '</span></td>';
                echo '<td>';
                $date_text = $publish_at ? date_i18n('Y-m-d H:i', strtotime($publish_at)) : get_the_date('Y-m-d H:i', $post);
                echo '<span class="tts-tooltip">';
                echo esc_html($date_text);
                echo '<span class="tts-tooltiptext">' . esc_html(human_time_diff(strtotime($date_text), current_time('timestamp'))) . ' ago</span>';
                echo '</span>';
                echo '</td>';
                echo '<td>';
                echo '<a href="' . esc_url(admin_url('admin.php?page=fp-publisher-social-posts&action=log&post=' . $post->ID)) . '" class="tts-btn small secondary">';
                echo esc_html__('View Log', 'fp-publisher');
                echo '</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
            
            // Bulk actions
            echo '<div class="tts-bulk-actions">';
            echo '<h4>' . esc_html__('Bulk Actions', 'fp-publisher') . '</h4>';
            echo '<div style="display: flex; gap: 10px; align-items: center;">';
            echo '<select class="tts-bulk-action-select">';
            echo '<option value="">' . esc_html__('Choose an action...', 'fp-publisher') . '</option>';
            echo '<option value="delete">' . esc_html__('Delete', 'fp-publisher') . '</option>';
            echo '<option value="approve">' . esc_html__('Approve', 'fp-publisher') . '</option>';
            echo '<option value="revoke">' . esc_html__('Revoke', 'fp-publisher') . '</option>';
            echo '</select>';
            echo '<button class="tts-btn" data-ajax-action="tts_bulk_action" data-confirm="' . esc_attr__('Are you sure you want to perform this action on the selected posts?', 'fp-publisher') . '">';
            echo esc_html__('Apply', 'fp-publisher');
            echo '</button>';
            echo '</div>';
            echo '</div>';
            
        } else {
            echo '<div style="text-align: center; padding: 40px; color: #666;">';
            echo '<span style="font-size: 48px; margin-bottom: 10px; display: block;">📝</span>';
            echo '<p style="margin: 0; font-size: 16px;">' . esc_html__('No social posts found.', 'fp-publisher') . '</p>';
            echo '<p style="margin: 5px 0 0 0; font-size: 14px;">' . esc_html__('Create your first social media post to get started!', 'fp-publisher') . '</p>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=fp-publisher-client-wizard')) . '" class="tts-btn" style="margin-top: 15px;">';
            echo esc_html__('Add New Client', 'fp-publisher');
            echo '</a>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Render connection test widget for dashboard.
     */
    private function render_connection_test_widget() {
        echo '<div class="tts-dashboard-widget">';
        echo '<h3>🔗 ' . esc_html__( 'Connection Testing', 'fp-publisher' ) . '</h3>';
        
        // Quick connection status
        $clients = get_posts( array(
            'post_type'      => 'tts_client',
            'posts_per_page' => 3,
            'post_status'    => 'any'
        ) );
        
        if ( ! empty( $clients ) ) {
            echo '<div class="tts-connection-quick-status">';
            foreach ( $clients as $client ) {
                $title = get_the_title( $client->ID );

                $platform_labels = array(
                    'facebook'  => __( 'Facebook', 'fp-publisher' ),
                    'instagram' => __( 'Instagram', 'fp-publisher' ),
                    'youtube'   => __( 'YouTube', 'fp-publisher' ),
                    'tiktok'    => __( 'TikTok', 'fp-publisher' ),
                );

                $connected_platforms = array();

                foreach ( $platform_labels as $platform => $label ) {
                    $meta_key = $this->get_platform_token_meta_key( $platform );

                    if ( empty( $meta_key ) ) {
                        continue;
                    }

                    $token = get_post_meta( $client->ID, $meta_key, true );

                    if ( ! empty( $token ) ) {
                        $connected_platforms[ $platform ] = $label;
                    }
                }

                $connected_count = count( $connected_platforms );

                echo '<div class="tts-client-status">';
                echo '<strong>' . esc_html( wp_trim_words( $title, 3 ) ) . '</strong><br>';
                echo '<small>' . sprintf( esc_html__( '%d platforms connected', 'fp-publisher' ), $connected_count ) . '</small>';

                if ( ! empty( $connected_platforms ) ) {
                    echo '<div class="tts-platform-badges" style="margin-top: 4px; display: flex; gap: 4px; flex-wrap: wrap;">';
                    foreach ( $connected_platforms as $label ) {
                        echo '<span class="tts-platform-badge" style="background: #f0f0f1; border-radius: 12px; padding: 2px 8px; font-size: 11px;">' . esc_html( $label ) . '</span>';
                    }
                    echo '</div>';
                }

                echo '</div>';
            }
            echo '</div>';
        }
        
        echo '<div class="tts-widget-actions">';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-test-connections' ) ) . '" class="button button-primary">';
        echo esc_html__( 'Test All Connections', 'fp-publisher' );
        echo '</a>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Render quick actions section.
     */
    private function render_quick_actions_section() {
        echo '<div class="tts-dashboard-section">';
        echo '<h2>' . esc_html__('Quick Actions', 'fp-publisher') . '</h2>';
        echo '<div class="tts-quick-actions">';
        
        $actions = array(
            array(
                'title' => __('Add New Client', 'fp-publisher'),
                'description' => __('Set up a new social media client', 'fp-publisher'),
                'url' => admin_url('admin.php?page=fp-publisher-client-wizard'),
                'icon' => 'dashicons-plus',
                'color' => '#135e96'
            ),
            array(
                'title' => __('View Calendar', 'fp-publisher'),
                'description' => __('See scheduled posts in calendar view', 'fp-publisher'),
                'url' => admin_url('admin.php?page=fp-publisher-calendar'),
                'icon' => 'dashicons-calendar',
                'color' => '#f56e28'
            ),
            array(
                'title' => __('Check Health Status', 'fp-publisher'),
                'description' => __('Monitor system health and tokens', 'fp-publisher'),
                'url' => admin_url('admin.php?page=fp-publisher-health'),
                'icon' => 'dashicons-heart',
                'color' => '#00a32a'
            ),
            array(
                'title' => __('View Analytics', 'fp-publisher'),
                'description' => __('Analyze performance and engagement', 'fp-publisher'),
                'url' => admin_url('admin.php?page=fp-publisher-analytics'),
                'icon' => 'dashicons-chart-area',
                'color' => '#7c3aed'
            ),
            array(
                'title' => __('Manage Posts', 'fp-publisher'),
                'description' => __('View and manage all social posts', 'fp-publisher'),
                'url' => admin_url('admin.php?page=fp-publisher-social-posts'),
                'icon' => 'dashicons-admin-post',
                'color' => '#2563eb'
            ),
            array(
                'title' => __('View Logs', 'fp-publisher'),
                'description' => __('Check system logs and debugging info', 'fp-publisher'),
                'url' => admin_url('admin.php?page=fp-publisher-log'),
                'icon' => 'dashicons-list-view',
                'color' => '#64748b'
            )
        );
        
        foreach ($actions as $action) {
            echo '<a href="' . esc_url($action['url']) . '" class="tts-quick-action tts-tooltip" style="border-left: 4px solid ' . $action['color'] . ';">';
            echo '<div style="display: flex; align-items: center;">';
            echo '<span class="dashicons ' . esc_attr($action['icon']) . '" style="color: ' . $action['color'] . '; margin-right: 12px; font-size: 20px;"></span>';
            echo '<div>';
            echo '<div style="font-weight: 600; margin-bottom: 2px;">' . esc_html($action['title']) . '</div>';
            echo '<div style="font-size: 12px; color: #666;">' . esc_html($action['description']) . '</div>';
            echo '</div>';
            echo '</div>';
            echo '<span class="tts-tooltiptext">' . esc_html($action['description']) . '</span>';
            echo '</a>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render system status widget for dashboard.
     */
    private function render_system_status_widget() {
        echo '<div class="tts-dashboard-section">';
        echo '<h2>' . esc_html__('System Status', 'fp-publisher') . '</h2>';
        
        // Check various system components
        $status_checks = array();
        
        // Check WordPress requirements
        $wp_version = get_bloginfo('version');
        $status_checks['wordpress'] = array(
            'name' => 'WordPress Version',
            'status' => version_compare($wp_version, '5.0', '>=') ? 'success' : 'error',
            'message' => 'WordPress ' . $wp_version
        );
        
        // Check if Action Scheduler is available
        $status_checks['scheduler'] = array(
            'name' => 'Action Scheduler',
            'status' => class_exists('ActionScheduler') ? 'success' : 'warning',
            'message' => class_exists('ActionScheduler') ? 'Available' : 'Not available'
        );
        
        // Check recent error logs
        $recent_errors = get_posts(array(
            'post_type' => 'tts_log',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => '_log_level',
                    'value' => 'error',
                    'compare' => '='
                )
            ),
            'date_query' => array(
                array(
                    'after' => '24 hours ago'
                )
            )
        ));
        
        $status_checks['errors'] = array(
            'name' => 'Recent Errors',
            'status' => empty($recent_errors) ? 'success' : 'warning',
            'message' => empty($recent_errors) ? 'No errors in 24h' : count($recent_errors) . ' error(s) in 24h'
        );
        
        // Overall health calculation
        $success_count = 0;
        foreach ($status_checks as $check) {
            if ($check['status'] === 'success') $success_count++;
        }
        $health_percentage = round(($success_count / count($status_checks)) * 100);
        
        // Health indicator
        echo '<div style="text-align: center; margin-bottom: 15px;">';
        $health_color = $health_percentage >= 80 ? '#00a32a' : ($health_percentage >= 60 ? '#f56e28' : '#d63638');
        echo '<div style="font-size: 24px; color: ' . $health_color . '; font-weight: bold;">';
        echo $health_percentage . '% ' . esc_html__('Healthy', 'fp-publisher');
        echo '</div>';
        echo '</div>';
        
        // Status items
        foreach ($status_checks as $key => $check) {
            $icon_color = $check['status'] === 'success' ? '#00a32a' : ($check['status'] === 'warning' ? '#f56e28' : '#d63638');
            echo '<div style="display: flex; align-items: center; margin-bottom: 8px;">';
            echo '<span class="tts-status-indicator ' . $check['status'] . '" style="background: ' . $icon_color . ';"></span>';
            echo '<span style="flex: 1;">' . esc_html($check['name']) . '</span>';
            echo '<span style="color: #666; font-size: 12px;">' . esc_html($check['message']) . '</span>';
            echo '</div>';
        }
        
        echo '<div style="margin-top: 15px;">';
        echo '<a href="' . admin_url('admin.php?page=fp-publisher-health') . '" class="tts-btn small">View Detailed Status</a>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Render the clients list page.
     */
    public function render_clients_page() {
        $clients = get_posts(
            array(
                'post_type'      => 'tts_client',
                'posts_per_page' => -1,
            )
        );
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Clienti', 'fp-publisher' ) . '</h1>';
        if ( ! empty( $clients ) ) {
            echo '<ul>';
            foreach ( $clients as $client ) {
                $url = admin_url( 'edit.php?post_type=tts_social_post&tts_client=' . $client->ID );
                echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $client->post_title ) . '</a></li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__( 'Nessun cliente trovato.', 'fp-publisher' ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Render the client creation wizard.
     */
    public function tts_render_client_wizard() {
        if ( ! session_id() ) {
            session_start();
        }

        // Security: Verify nonce for form submissions
        if ( isset( $_POST['step'] ) && $_POST['step'] > 1 ) {
            if ( ! wp_verify_nonce( $_POST['tts_wizard_nonce'], 'tts_client_wizard' ) ) {
                wp_die( esc_html__( 'Security verification failed. Please try again.', 'fp-publisher' ) );
            }
        }

        $step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;

        echo '<div class="wrap tts-client-wizard">';
        echo '<h1>' . esc_html__( 'Client Wizard', 'fp-publisher' ) . '</h1>';

        // Add helpful notice about social media setup
        if ( 2 === $step ) {
            echo '<div class="notice notice-info">';
            echo '<h3>' . esc_html__( 'Social Media Setup Required', 'fp-publisher' ) . '</h3>';
            echo '<p>' . esc_html__( 'To connect social media accounts, you must first configure OAuth apps for each platform. Click "Configure App" for platforms that are not set up.', 'fp-publisher' ) . '</p>';
            echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-social-connections' ) ) . '" class="button">' . esc_html__( 'Manage Social Connections', 'fp-publisher' ) . '</a> ';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-help' ) ) . '" target="_blank">' . esc_html__( 'View Setup Guide', 'fp-publisher' ) . '</a></p>';
            echo '</div>';
        }

        $fb_token = get_transient( 'tts_oauth_facebook_token' );
        $ig_token = get_transient( 'tts_oauth_instagram_token' );
        $yt_token = get_transient( 'tts_oauth_youtube_token' );
        $tt_token = get_transient( 'tts_oauth_tiktok_token' );

        $trello_key   = isset( $_GET['trello_key'] ) ? sanitize_text_field( wp_unslash( $_GET['trello_key'] ) ) : '';
        $trello_token = isset( $_GET['trello_token'] ) ? sanitize_text_field( wp_unslash( $_GET['trello_token'] ) ) : '';
        $board        = isset( $_GET['trello_board'] ) ? sanitize_text_field( wp_unslash( $_GET['trello_board'] ) ) : '';
        $channels     = isset( $_GET['channels'] ) ? array_map( 'sanitize_text_field', (array) $_GET['channels'] ) : array();

        if ( 1 === $step ) {
            echo '<form method="post" class="tts-wizard-step tts-step-1">';
            wp_nonce_field( 'tts_client_wizard', 'tts_wizard_nonce' );
            echo '<input type="hidden" name="step" value="2" />';
            echo '<p><label>' . esc_html__( 'Trello API Key', 'fp-publisher' ) . '<br />';
            echo '<input type="text" name="trello_key" value="' . esc_attr( $trello_key ) . '" required /></label></p>';
            echo '<p><label>' . esc_html__( 'Trello Token', 'fp-publisher' ) . '<br />';
            echo '<input type="text" name="trello_token" value="' . esc_attr( $trello_token ) . '" required /></label></p>';

            $boards = array();
            if ( $trello_key && $trello_token ) {
                $response = wp_remote_get(
                    'https://api.trello.com/1/members/me/boards?key=' . rawurlencode( $trello_key ) . '&token=' . rawurlencode( $trello_token ),
                    array( 'timeout' => 20 )
                );
                if ( ! is_wp_error( $response ) ) {
                    $boards = json_decode( wp_remote_retrieve_body( $response ), true );
                }
            }

            if ( ! empty( $boards ) ) {
                echo '<p><label>' . esc_html__( 'Trello Board', 'fp-publisher' ) . '<br />';
                echo '<select name="trello_board">';
                foreach ( $boards as $b ) {
                    printf( '<option value="%s" %s>%s</option>', esc_attr( $b['id'] ), selected( $board, $b['id'], false ), esc_html( $b['name'] ) );
                }
                echo '</select></label></p>';
            }

            echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Next', 'fp-publisher' ) . '</button></p>';
            echo '</form>';
        } elseif ( 2 === $step ) {
            echo '<form method="post" class="tts-wizard-step tts-step-2">';
            wp_nonce_field( 'tts_client_wizard', 'tts_wizard_nonce' );
            echo '<input type="hidden" name="step" value="3" />';
            echo '<input type="hidden" name="trello_key" value="' . esc_attr( $trello_key ) . '" />';
            echo '<input type="hidden" name="trello_token" value="' . esc_attr( $trello_token ) . '" />';
            echo '<input type="hidden" name="trello_board" value="' . esc_attr( $board ) . '" />';

            $opts = array(
                'facebook'  => __( 'Facebook', 'fp-publisher' ),
                'instagram' => __( 'Instagram', 'fp-publisher' ),
                'youtube'   => __( 'YouTube', 'fp-publisher' ),
                'tiktok'    => __( 'TikTok', 'fp-publisher' ),
            );

            foreach ( $opts as $slug => $label ) {
                $token     = '';
                $connected = false;
                $app_configured = false;
                
                // Check if app is configured
                $settings = get_option( 'tts_social_apps', array() );
                $platform_settings = isset( $settings[$slug] ) ? $settings[$slug] : array();
                
                switch ( $slug ) {
                    case 'facebook':
                        $token     = $fb_token;
                        $connected = ! empty( $fb_token );
                        $app_configured = ! empty( $platform_settings['app_id'] ) && ! empty( $platform_settings['app_secret'] );
                        break;
                    case 'instagram':
                        $token     = $ig_token;
                        $connected = ! empty( $ig_token );
                        $app_configured = ! empty( $platform_settings['app_id'] ) && ! empty( $platform_settings['app_secret'] );
                        break;
                    case 'youtube':
                        $token     = $yt_token;
                        $connected = ! empty( $yt_token );
                        $app_configured = ! empty( $platform_settings['client_id'] ) && ! empty( $platform_settings['client_secret'] );
                        break;
                    case 'tiktok':
                        $token     = $tt_token;
                        $connected = ! empty( $tt_token );
                        $app_configured = ! empty( $platform_settings['client_key'] ) && ! empty( $platform_settings['client_secret'] );
                        break;
                }

                echo '<div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;">';
                echo '<p><label><input type="checkbox" name="channels[]" value="' . esc_attr( $slug ) . '" ' . checked( in_array( $slug, $channels, true ) || $connected, true, false ) . ' /> <strong>' . esc_html( $label ) . '</strong></label>';
                
                if ( ! $app_configured ) {
                    echo '<br><span style="color: #d63638;">⚠️ ' . esc_html__( 'App not configured', 'fp-publisher' ) . '</span>';
                    echo '<br><a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-social-connections' ) ) . '" class="button">' . esc_html__( 'Configure App', 'fp-publisher' ) . '</a>';
                } elseif ( $connected ) {
                    echo '<br><span style="color: #00a32a;">✅ ' . esc_html__( 'Connected', 'fp-publisher' ) . '</span>';
                } else {
                    $url = add_query_arg( array( 'action' => 'tts_oauth_' . $slug, 'step' => 2 ), admin_url( 'admin-post.php' ) );
                    echo '<br><span style="color: #f56e28;">🟡 ' . esc_html__( 'Ready to connect', 'fp-publisher' ) . '</span>';
                    echo '<br><a href="' . esc_url( $url ) . '" class="button button-primary">' . esc_html__( 'Connect Account', 'fp-publisher' ) . '</a>';
                }
                echo '</div>';
            }

            echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Next', 'fp-publisher' ) . '</button></p>';
            echo '</form>';
        } elseif ( 3 === $step ) {
            echo '<form method="post" class="tts-wizard-step tts-step-3">';
            echo '<input type="hidden" name="step" value="4" />';
            echo '<input type="hidden" name="trello_key" value="' . esc_attr( $trello_key ) . '" />';
            echo '<input type="hidden" name="trello_token" value="' . esc_attr( $trello_token ) . '" />';
            echo '<input type="hidden" name="trello_board" value="' . esc_attr( $board ) . '" />';
            foreach ( $channels as $ch ) {
                echo '<input type="hidden" name="channels[]" value="' . esc_attr( $ch ) . '" />';
            }
            echo '<div id="tts-lists" data-board="' . esc_attr( $board ) . '" data-key="' . esc_attr( $trello_key ) . '" data-token="' . esc_attr( $trello_token ) . '"></div>';
            echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Next', 'fp-publisher' ) . '</button></p>';
            echo '</form>';
        } else {
            if ( isset( $_POST['finalize'] ) ) {
                $post_id = wp_insert_post(
                    array(
                        'post_type'   => 'tts_client',
                        'post_status' => 'publish',
                        'post_title'  => 'Client ' . $board,
                    )
                );
                if ( $post_id ) {
                    update_post_meta( $post_id, '_tts_trello_key', $trello_key );
                    update_post_meta( $post_id, '_tts_trello_token', $trello_token );
                    if ( $fb_token ) {
                        update_post_meta( $post_id, '_tts_fb_token', $fb_token );
                    }
                    if ( $ig_token ) {
                        update_post_meta( $post_id, '_tts_ig_token', $ig_token );
                    }
                    if ( $yt_token ) {
                        update_post_meta( $post_id, '_tts_yt_token', $yt_token );
                    }
                    if ( $tt_token ) {
                        update_post_meta( $post_id, '_tts_tt_token', $tt_token );
                    }
                    if ( isset( $_POST['tts_trello_map'] ) && is_array( $_POST['tts_trello_map'] ) ) {
                        $map = array();
                        foreach ( $_POST['tts_trello_map'] as $id_list => $row ) {
                            if ( empty( $row['canale_social'] ) ) {
                                continue;
                            }
                            $map[] = array(
                                'idList'        => sanitize_text_field( $id_list ),
                                'canale_social' => sanitize_text_field( $row['canale_social'] ),
                            );
                        }
                        if ( ! empty( $map ) ) {
                            update_post_meta( $post_id, '_tts_trello_map', $map );
                        }
                    }

                    delete_transient( 'tts_oauth_facebook_token' );
                    delete_transient( 'tts_oauth_instagram_token' );
                    delete_transient( 'tts_oauth_youtube_token' );
                    delete_transient( 'tts_oauth_tiktok_token' );

                    echo '<p>' . esc_html__( 'Client created.', 'fp-publisher' ) . '</p>';
                }
                echo '</div>';
                return;
            }

            echo '<form method="post" class="tts-wizard-step tts-step-4">';
            echo '<input type="hidden" name="step" value="4" />';
            echo '<input type="hidden" name="finalize" value="1" />';
            echo '<input type="hidden" name="trello_key" value="' . esc_attr( $trello_key ) . '" />';
            echo '<input type="hidden" name="trello_token" value="' . esc_attr( $trello_token ) . '" />';
            echo '<input type="hidden" name="trello_board" value="' . esc_attr( $board ) . '" />';
            foreach ( $channels as $ch ) {
                echo '<input type="hidden" name="channels[]" value="' . esc_attr( $ch ) . '" />';
            }
            if ( isset( $_POST['tts_trello_map'] ) && is_array( $_POST['tts_trello_map'] ) ) {
                foreach ( $_POST['tts_trello_map'] as $id_list => $row ) {
                    echo '<input type="hidden" name="tts_trello_map[' . esc_attr( $id_list ) . '][canale_social]" value="' . esc_attr( $row['canale_social'] ) . '" />';
                }
            }

            echo '<h2>' . esc_html__( 'Summary', 'fp-publisher' ) . '</h2>';
            echo '<p>' . esc_html__( 'Trello Board:', 'fp-publisher' ) . ' ' . esc_html( $board ) . '</p>';
            echo '<p>' . esc_html__( 'Channels:', 'fp-publisher' ) . ' ' . esc_html( implode( ', ', $channels ) ) . '</p>';
            echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Create Client', 'fp-publisher' ) . '</button></p>';
            echo '</form>';
        }

        echo '</div>';
    }

    /**
     * Add dropdown filter on social posts list table.
     *
     * @param string $post_type Current post type.
     */
    public function add_client_filter( $post_type ) {
        if ( 'tts_social_post' !== $post_type ) {
            return;
        }

        $selected = isset( $_GET['tts_client'] ) ? absint( $_GET['tts_client'] ) : 0;
        $clients  = get_posts(
            array(
                'post_type'      => 'tts_client',
                'posts_per_page' => -1,
            )
        );
        echo '<select name="tts_client">';
        echo '<option value="">' . esc_html__( 'All Clients', 'fp-publisher' ) . '</option>';
        foreach ( $clients as $client ) {
            printf(
                '<option value="%1$d" %3$s>%2$s</option>',
                $client->ID,
                esc_html( $client->post_title ),
                selected( $selected, $client->ID, false )
            );
        }
        echo '</select>';
    }

    /**
     * Filter social posts list by selected client.
     *
     * @param WP_Query $query Current query instance.
     */
    public function filter_posts_by_client( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( 'tts_social_post' !== $query->get( 'post_type' ) ) {
            return;
        }

        if ( ! empty( $_GET['tts_client'] ) ) {
            $query->set(
                'meta_query',
                array(
                    array(
                        'key'   => '_tts_client_id',
                        'value' => absint( $_GET['tts_client'] ),
                    ),
                )
            );
        }
    }

    /**
     * Add approval status filter on social posts list table.
     *
     * @param string $post_type Current post type.
     */
    public function add_approved_filter( $post_type ) {
        if ( 'tts_social_post' !== $post_type ) {
            return;
        }

        $selected = isset( $_GET['tts_approved'] ) ? sanitize_text_field( $_GET['tts_approved'] ) : '';
        echo '<select name="tts_approved">';
        echo '<option value="">' . esc_html__( 'Stato approvazione', 'fp-publisher' ) . '</option>';
        echo '<option value="1" ' . selected( $selected, '1', false ) . '>' . esc_html__( 'Approvato', 'fp-publisher' ) . '</option>';
        echo '<option value="0" ' . selected( $selected, '0', false ) . '>' . esc_html__( 'Non approvato', 'fp-publisher' ) . '</option>';
        echo '</select>';
    }

    /**
     * Filter social posts list by approval status.
     *
     * @param WP_Query $query Current query instance.
     */
    public function filter_posts_by_approved( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( 'tts_social_post' !== $query->get( 'post_type' ) ) {
            return;
        }

        if ( isset( $_GET['tts_approved'] ) && '' !== $_GET['tts_approved'] ) {
            $meta_query   = (array) $query->get( 'meta_query', array() );
            $meta_query[] = array(
                'key'   => '_tts_approved',
                'value' => '1' === $_GET['tts_approved'] ? '1' : '0',
            );
            $query->set( 'meta_query', $meta_query );
        }
    }

    /**
     * Add approved column to social posts list.
     *
     * @param array $columns Existing columns.
     *
     * @return array
     */
    public function add_approved_column( $columns ) {
        $columns['tts_approved'] = __( 'Approvato', 'fp-publisher' );
        return $columns;
    }

    /**
     * Render approved column content.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */
    public function render_approved_column( $column, $post_id ) {
        if ( 'tts_approved' === $column ) {
            $approved = (bool) get_post_meta( $post_id, '_tts_approved', true );
            echo $approved ? esc_html__( 'Si', 'fp-publisher' ) : esc_html__( 'No', 'fp-publisher' );
        }
    }

    /**
     * Register bulk actions for approving/revoking posts.
     *
     * @param array $actions Existing actions.
     *
     * @return array
     */
    public function register_bulk_actions( $actions ) {
        $actions['tts_approve'] = __( 'Approva', 'fp-publisher' );
        $actions['tts_revoke']  = __( 'Revoca', 'fp-publisher' );
        return $actions;
    }

    /**
     * Handle bulk actions for approval status.
     *
     * @param string $redirect_to Redirect URL.
     * @param string $doaction    Action name.
     * @param array  $post_ids    Selected post IDs.
     *
     * @return string
     */
    public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
        if ( 'tts_approve' === $doaction ) {
            foreach ( $post_ids as $post_id ) {
                update_post_meta( $post_id, '_tts_approved', true );
                do_action( 'save_post_tts_social_post', $post_id, get_post( $post_id ), true );
                do_action( 'tts_post_approved', $post_id );
            }
        } elseif ( 'tts_revoke' === $doaction ) {
            foreach ( $post_ids as $post_id ) {
                delete_post_meta( $post_id, '_tts_approved' );
                do_action( 'save_post_tts_social_post', $post_id, get_post( $post_id ), true );
            }
        }

        return $redirect_to;
    }

    /**
     * Render social posts list page.
     */
    public function render_social_posts_page() {
        // Handle publish now action.
        if ( isset( $_GET['action'], $_GET['post'] ) && 'publish' === $_GET['action'] ) {
            if ( ! current_user_can( 'publish_posts' ) ) {
                wp_die( esc_html__( 'Sorry, you are not allowed to publish this post.', 'fp-publisher' ) );
            }

            $post_id = absint( $_GET['post'] );
            check_admin_referer( 'tts_publish_social_post_' . $post_id );
            do_action( 'tts_publish_social_post', $post_id );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Post published.', 'fp-publisher' ) . '</p></div>';
        }

        // Handle log view.
        if ( isset( $_GET['action'], $_GET['post'] ) && 'log' === $_GET['action'] ) {
            $log = get_post_meta( absint( $_GET['post'] ), '_tts_publish_log', true );
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Log', 'fp-publisher' ) . '</h1>';
            if ( ! empty( $log ) ) {
                echo '<div class="tts-log-display">';
                if ( is_array( $log ) || is_object( $log ) ) {
                    echo '<pre class="tts-log-content">' . esc_html( wp_json_encode( $log, JSON_PRETTY_PRINT ) ) . '</pre>';
                } else {
                    echo '<pre class="tts-log-content">' . esc_html( $log ) . '</pre>';
                }
                echo '</div>';
            } else {
                echo '<p>' . esc_html__( 'No log entries found.', 'fp-publisher' ) . '</p>';
            }
            echo '</div>';
            return;
        }

        $table = new TTS_Social_Posts_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Social Post', 'fp-publisher' ) . '</h1>';
        $table->display();
        echo '</div>';
    }

    /**
     * Get optimized dashboard statistics with single database query.
     *
     * @return array Optimized statistics data.
     */
    private function get_optimized_dashboard_statistics() {
        global $wpdb;

        // Get basic post counts
        $total_posts = wp_count_posts('tts_social_post');
        $total_clients = wp_count_posts('tts_client');

        // Single optimized query to get all post statistics by date and status
        $today = current_time('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
        $current_time = current_time('mysql');

        $query = $wpdb->prepare("
            SELECT 
                pm.meta_value as status,
                DATE(p.post_date) as post_date,
                pm2.meta_value as publish_at,
                COUNT(*) as count
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_published_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_tts_publish_at'
            WHERE p.post_type = 'tts_social_post'
            AND (
                DATE(p.post_date) = %s 
                OR DATE(p.post_date) = %s
                OR (pm2.meta_value IS NOT NULL AND pm2.meta_value >= %s)
            )
            GROUP BY pm.meta_value, DATE(p.post_date)
        ", $today, $yesterday, $current_time);

        $results = $wpdb->get_results($query);

        // Process results
        $published_today = 0;
        $published_yesterday = 0;
        $failed_today = 0;
        $scheduled_posts = 0;

        foreach ($results as $row) {
            if ($row->post_date === $today) {
                if ($row->status === 'published') {
                    $published_today = $row->count;
                } elseif ($row->status === 'failed') {
                    $failed_today = $row->count;
                }
            } elseif ($row->post_date === $yesterday && $row->status === 'published') {
                $published_yesterday = $row->count;
            }
            
            // Count scheduled posts (those with future publish_at)
            if ($row->publish_at && $row->publish_at >= $current_time) {
                $scheduled_posts += $row->count;
            }
        }

        // Calculate additional metrics
        $total_today = $published_today + $failed_today;
        $success_rate = $total_today > 0 ? round(($published_today / $total_today) * 100) : 100;
        $trend_percentage = $published_yesterday > 0 ? round((($published_today - $published_yesterday) / $published_yesterday) * 100) : 0;

        // Get next scheduled post
        $next_scheduled = $wpdb->get_row($wpdb->prepare("
            SELECT p.ID, p.post_title, pm.meta_value as publish_at
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'tts_social_post'
            AND pm.meta_key = '_tts_publish_at'
            AND pm.meta_value >= %s
            ORDER BY pm.meta_value ASC
            LIMIT 1
        ", $current_time));

        // Weekly average
        $week_published = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'tts_social_post'
            AND pm.meta_key = '_published_status'
            AND pm.meta_value = 'published'
            AND p.post_date >= %s
        ", date('Y-m-d H:i:s', strtotime('-1 week', current_time('timestamp')))));

        $weekly_average = round($week_published / 7, 1);

        return array(
            'total_posts' => $total_posts,
            'total_clients' => $total_clients,
            'scheduled_posts' => $scheduled_posts,
            'published_today' => $published_today,
            'published_yesterday' => $published_yesterday,
            'failed_today' => $failed_today,
            'success_rate' => $success_rate,
            'trend_percentage' => $trend_percentage,
            'next_scheduled' => $next_scheduled,
            'weekly_average' => $weekly_average,
            'performance_metrics' => TTS_Performance::get_performance_metrics(),
        );
    }
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table implementation for social posts.
 */
class TTS_Social_Posts_Table extends WP_List_Table {

    /**
     * Retrieve table columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'title'        => __( 'Titolo', 'fp-publisher' ),
            'channel'      => __( 'Canale', 'fp-publisher' ),
            'publish_date' => __( 'Data Pubblicazione', 'fp-publisher' ),
            'status'       => __( 'Stato', 'fp-publisher' ),
        );
    }

    /**
     * Prepare the table items.
     */
    public function prepare_items() {
        $posts = get_posts(
            array(
                'post_type'      => 'tts_social_post',
                'post_status'    => 'any',
                'posts_per_page' => -1,
            )
        );

        $data = array();
        foreach ( $posts as $post ) {
            $channel = get_post_meta( $post->ID, '_tts_social_channel', true );
            $publish = get_post_meta( $post->ID, '_tts_publish_at', true );
            $status  = get_post_meta( $post->ID, '_published_status', true );

            $data[] = array(
                'ID'          => $post->ID,
                'title'       => $post->post_title,
                'channel'     => is_array( $channel ) ? implode( ', ', $channel ) : $channel,
                'publish_date'=> $publish ? date_i18n( 'Y-m-d H:i', strtotime( $publish ) ) : '',
                'status'      => $status ? $status : __( 'scheduled', 'fp-publisher' ),
            );
        }

        $this->items = $data;
    }

    /**
     * Render title column with row actions.
     *
     * @param array $item Current row.
     *
     * @return string
     */
    public function column_title( $item ) {
        $publish_url = wp_nonce_url(
            add_query_arg(
                array(
                    'page'   => 'fp-publisher-social-posts',
                    'action' => 'publish',
                    'post'   => $item['ID'],
                ),
                admin_url( 'admin.php' )
            ),
            'tts_publish_social_post_' . $item['ID']
        );

        $actions = array(
            'publish'  => sprintf( '<a href="%s">%s</a>', esc_url( $publish_url ), __( 'Publish Now', 'fp-publisher' ) ),
            'edit'     => sprintf( '<a href="%s">%s</a>', get_edit_post_link( $item['ID'] ), __( 'Edit', 'fp-publisher' ) ),
            'view_log' => sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( array( 'page' => 'fp-publisher-social-posts', 'action' => 'log', 'post' => $item['ID'] ), admin_url( 'admin.php' ) ) ), __( 'View Log', 'fp-publisher' ) ),
        );

        return sprintf( '<strong>%1$s</strong>%2$s', esc_html( $item['title'] ), $this->row_actions( $actions ) );
    }

    /**
     * Default column rendering.
     *
     * @param array  $item        Row item.
     * @param string $column_name Column name.
     *
     * @return string
     */
    public function column_default( $item, $column_name ) {
        return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
    }

    /**
     * Simple rate limiting for AJAX endpoints.
     *
     * @param string $action Action being performed.
     * @param int $limit Maximum number of requests.
     * @param int $window Time window in seconds.
     * @return bool Whether the request is within limits.
     */
    private function check_rate_limit($action, $limit = 10, $window = 60) {
        $user_id = get_current_user_id();
        $transient_key = "tts_rate_limit_{$action}_{$user_id}";
        
        $current_count = get_transient($transient_key);
        
        if (false === $current_count) {
            // First request in this window
            set_transient($transient_key, 1, $window);
            return true;
        }
        
        if ($current_count >= $limit) {
            return false; // Rate limit exceeded
        }
        
        // Increment counter
        set_transient($transient_key, $current_count + 1, $window);
        return true;
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Social Auto Publisher Settings', 'fp-publisher' ); ?></h1>
            
            <div class="notice notice-info">
                <p><?php esc_html_e( 'Configure your global plugin settings here. For social media connections, please visit the Social Connections page.', 'fp-publisher' ); ?></p>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields( 'tts_settings_group' );
                do_settings_sections( 'tts_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the social connections page.
     */
    public function render_social_connections_page() {
        // Ensure we're in WordPress admin context
        if ( ! is_admin() ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'fp-publisher' ) );
        }
        
        // Check user capabilities early
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'fp-publisher' ) );
        }
        
        try {
            // Handle form submissions
            if ( isset( $_POST['action'] ) && $_POST['action'] === 'save_social_apps' ) {
                if ( wp_verify_nonce( $_POST['tts_social_nonce'], 'tts_save_social_apps' ) ) {
                    $this->save_social_app_settings();
                    echo '<div class="notice notice-success"><p>' . esc_html__( 'Social media app settings saved successfully!', 'fp-publisher' ) . '</p></div>';
                }
            }

            $settings = get_option( 'tts_social_apps', array() );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Social Media Connections', 'fp-publisher' ); ?></h1>
            
            <div class="notice notice-info">
                <h3><?php esc_html_e( 'Setup Instructions', 'fp-publisher' ); ?></h3>
                <p><?php esc_html_e( 'To connect your social media accounts, you need to create apps on each platform and configure OAuth credentials:', 'fp-publisher' ); ?></p>
                <ol>
                    <li><strong>Facebook:</strong> <?php esc_html_e( 'Create an app at', 'fp-publisher' ); ?> <a href="https://developers.facebook.com/apps/" target="_blank">Facebook Developers</a></li>
                    <li><strong>Instagram:</strong> <?php esc_html_e( 'Use Facebook app with Instagram Basic Display product', 'fp-publisher' ); ?></li>
                    <li><strong>YouTube:</strong> <?php esc_html_e( 'Create a project at', 'fp-publisher' ); ?> <a href="https://console.developers.google.com/" target="_blank">Google Developers Console</a></li>
                    <li><strong>TikTok:</strong> <?php esc_html_e( 'Apply for TikTok for Developers at', 'fp-publisher' ); ?> <a href="https://developers.tiktok.com/" target="_blank">TikTok Developers</a></li>
                </ol>
                <p><strong><?php esc_html_e( 'Redirect URI:', 'fp-publisher' ); ?></strong> <code><?php echo esc_url( admin_url( 'admin-post.php' ) ); ?></code></p>
            </div>

            <div class="tts-social-apps-container">
                <form method="post" action="">
                    <?php wp_nonce_field( 'tts_save_social_apps', 'tts_social_nonce' ); ?>
                    <input type="hidden" name="action" value="save_social_apps" />

                    <div class="tts-social-platforms">
                        <?php
                        $platforms = array(
                            'facebook' => array(
                                'name' => 'Facebook',
                                'icon' => '📘',
                                'fields' => array( 'app_id', 'app_secret' )
                            ),
                            'instagram' => array(
                                'name' => 'Instagram',
                                'icon' => '📷',
                                'fields' => array( 'app_id', 'app_secret' )
                            ),
                            'youtube' => array(
                                'name' => 'YouTube',
                                'icon' => '🎥',
                                'fields' => array( 'client_id', 'client_secret' )
                            ),
                            'tiktok' => array(
                                'name' => 'TikTok',
                                'icon' => '🎵',
                                'fields' => array( 'client_key', 'client_secret' )
                            )
                        );

                        foreach ( $platforms as $platform => $config ) :
                            $platform_settings = isset( $settings[$platform] ) ? $settings[$platform] : array();
                        ?>
                        <div class="tts-platform-config" data-platform="<?php echo esc_attr( $platform ); ?>">
                            <h2><?php echo esc_html( $config['icon'] . ' ' . $config['name'] ); ?></h2>
                            
                            <?php foreach ( $config['fields'] as $field ) : 
                                $field_value = isset( $platform_settings[$field] ) ? $platform_settings[$field] : '';
                                $field_label = ucwords( str_replace( '_', ' ', $field ) );
                            ?>
                            <p>
                                <label for="<?php echo esc_attr( $platform . '_' . $field ); ?>">
                                    <?php echo esc_html( $field_label ); ?>:
                                </label>
                                <input type="text" 
                                       id="<?php echo esc_attr( $platform . '_' . $field ); ?>"
                                       name="social_apps[<?php echo esc_attr( $platform ); ?>][<?php echo esc_attr( $field ); ?>]"
                                       value="<?php echo esc_attr( $field_value ); ?>"
                                       class="regular-text" />
                            </p>
                            <?php endforeach; ?>

                            <?php 
                            // Check connection status
                            $connection_status = $this->check_platform_connection_status( $platform );
                            ?>
                            <div class="tts-connection-status" data-platform="<?php echo esc_attr( $platform ); ?>">
                                <strong><?php esc_html_e( 'Status:', 'fp-publisher' ); ?></strong>
                                <span class="tts-status-message tts-status-<?php echo esc_attr( $connection_status['status'] ); ?>">
                                    <?php echo esc_html( $connection_status['message'] ); ?>
                                </span>
                                
                                <?php if ( $connection_status['status'] === 'configured' ) : ?>
                                    <div class="tts-platform-actions">
                                        <a href="<?php echo esc_url( $this->get_oauth_url( $platform ) ); ?>" 
                                           class="button button-primary">
                                            <?php esc_html_e( 'Connect Account', 'fp-publisher' ); ?>
                                        </a>
                                        <button type="button" class="button tts-test-connection" 
                                                data-platform="<?php echo esc_attr( $platform ); ?>">
                                            <?php esc_html_e( 'Test Connection', 'fp-publisher' ); ?>
                                        </button>
                                    </div>
                                    <div class="tts-test-result" id="test-result-<?php echo esc_attr( $platform ); ?>" style="display: none;"></div>
                                <?php endif; ?>
                                
                                <?php if ( $connection_status['status'] === 'connected' ) : ?>
                                    <div class="tts-rate-limit-info" id="rate-limit-<?php echo esc_attr( $platform ); ?>">
                                        <button type="button" class="button tts-check-limits" 
                                                data-platform="<?php echo esc_attr( $platform ); ?>">
                                            <?php esc_html_e( 'Check API Limits', 'fp-publisher' ); ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save App Settings', 'fp-publisher' ); ?>" />
                    </p>
                </form>
            </div>

            <script>
            jQuery(document).ready(function($) {
                // Connection testing
                $('.tts-test-connection').on('click', function() {
                    var platform = $(this).data('platform');
                    var resultDiv = $('#test-result-' + platform);
                    var button = $(this);
                    
                    button.prop('disabled', true).text('<?php esc_html_e( 'Testing...', 'fp-publisher' ); ?>');
                    resultDiv.hide();
                    
                    $.post(ajaxurl, {
                        action: 'tts_test_connection',
                        platform: platform,
                        nonce: '<?php echo wp_create_nonce( 'tts_test_connection' ); ?>'
                    }, function(response) {
                        button.prop('disabled', false).text('<?php esc_html_e( 'Test Connection', 'fp-publisher' ); ?>');
                        
                        if (response.success) {
                            resultDiv.removeClass('error').addClass('success')
                                     .html('✅ ' + response.data.message).show();
                        } else {
                            resultDiv.removeClass('success').addClass('error')
                                     .html('❌ ' + (response.data.message || 'Connection test failed')).show();
                        }
                    }).fail(function() {
                        button.prop('disabled', false).text('<?php esc_html_e( 'Test Connection', 'fp-publisher' ); ?>');
                        resultDiv.removeClass('success').addClass('error')
                                 .html('❌ Failed to test connection').show();
                    });
                });
                
                // Rate limit checking
                $('.tts-check-limits').on('click', function() {
                    var platform = $(this).data('platform');
                    var container = $('#rate-limit-' + platform);
                    var button = $(this);
                    
                    button.prop('disabled', true).text('<?php esc_html_e( 'Checking...', 'fp-publisher' ); ?>');
                    
                    $.post(ajaxurl, {
                        action: 'tts_check_rate_limits',
                        platform: platform,
                        nonce: '<?php echo wp_create_nonce( 'tts_check_rate_limits' ); ?>'
                    }, function(response) {
                        button.prop('disabled', false).text('<?php esc_html_e( 'Check API Limits', 'fp-publisher' ); ?>');
                        
                        if (response.success) {
                            var limits = response.data;
                            var html = '<div class="tts-rate-limit-display">';
                            html += '<strong><?php esc_html_e( 'API Rate Limits:', 'fp-publisher' ); ?></strong><br>';
                            html += '<?php esc_html_e( 'Used:', 'fp-publisher' ); ?> ' + limits.used + ' / ' + limits.limit + '<br>';
                            html += '<?php esc_html_e( 'Remaining:', 'fp-publisher' ); ?> ' + limits.remaining + '<br>';
                            html += '<?php esc_html_e( 'Reset:', 'fp-publisher' ); ?> ' + limits.reset_time;
                            html += '</div>';
                            container.append(html);
                        }
                    });
                });
            });
            </script>

            <style>
            .tts-social-apps-container {
                margin-top: 20px;
            }
            .tts-social-platforms {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }
            .tts-platform-config {
                border: 1px solid #ddd;
                padding: 20px;
                border-radius: 8px;
                background: #fff;
            }
            .tts-platform-config h2 {
                margin-top: 0;
                font-size: 1.3em;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
            .tts-connection-status {
                margin-top: 15px;
                padding: 10px;
                background: #f9f9f9;
                border-radius: 4px;
            }
            .tts-status-not-configured {
                color: #d63638;
            }
            .tts-status-configured {
                color: #f56e28;
            }
            .tts-status-connected {
                color: #00a32a;
            }
            .tts-status-error {
                color: #d63638;
            }
            .tts-platform-actions {
                display: flex;
                gap: 10px;
                margin-top: 10px;
            }
            .tts-test-result {
                margin-top: 10px;
                padding: 8px;
                border-radius: 4px;
                font-size: 14px;
            }
            .tts-test-result.success {
                background: #d1eddd;
                color: #00a32a;
                border: 1px solid #00a32a;
            }
            .tts-test-result.error {
                background: #f7dde0;
                color: #d63638;
                border: 1px solid #d63638;
            }
            .tts-rate-limit-info {
                margin-top: 10px;
            }
            .tts-rate-limit-display {
                font-size: 12px;
                color: #666;
                margin-top: 5px;
            }
            </style>
        </div>
        <?php
        } catch ( Exception $e ) {
            // Log the error and show a user-friendly message
            error_log( 'TTS_Admin render_social_connections_page error: ' . $e->getMessage() );
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Social Media Connections', 'fp-publisher' ) . '</h1>';
            echo '<div class="notice notice-error">';
            echo '<p>' . esc_html__( 'An error occurred while loading the social connections page. Please refresh the page or contact support.', 'fp-publisher' ) . '</p>';
            echo '</div>';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-main' ) ) . '" class="button button-primary">' . esc_html__( 'Return to Dashboard', 'fp-publisher' ) . '</a>';
            echo '</div>';
        }
    }

    /**
     * Save social media app settings.
     */
    private function save_social_app_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $social_apps = isset( $_POST['social_apps'] ) ? $_POST['social_apps'] : array();
        $sanitized_apps = array();

        foreach ( $social_apps as $platform => $settings ) {
            $platform = sanitize_key( $platform );
            $sanitized_apps[$platform] = array();

            foreach ( $settings as $key => $value ) {
                $key = sanitize_key( $key );
                $sanitized_apps[$platform][$key] = sanitize_text_field( $value );
            }
        }

        update_option( 'tts_social_apps', $sanitized_apps );
    }

    /**
     * Retrieve the token meta key for a given platform.
     *
     * @param string $platform Platform identifier.
     * @return string Token meta key or empty string if unsupported.
     */
    private function get_platform_token_meta_key( $platform ) {
        $meta_keys = array(
            'facebook'  => '_tts_fb_token',
            'instagram' => '_tts_ig_token',
            'youtube'   => '_tts_yt_token',
            'tiktok'    => '_tts_tt_token',
        );

        $platform = sanitize_key( $platform );

        return isset( $meta_keys[ $platform ] ) ? $meta_keys[ $platform ] : '';
    }

    /**
     * Check the connection status for a platform.
     *
     * @param string $platform Platform name.
     * @return array Status information.
     */
    private function check_platform_connection_status( $platform ) {
        $platform = sanitize_key( $platform );
        $settings = get_option( 'tts_social_apps', array() );
        $platform_settings = isset( $settings[ $platform ] ) ? $settings[ $platform ] : array();

        // Check if app credentials are configured
        $required_fields = $this->get_required_platform_fields( $platform );

        $configured = true;
        foreach ( $required_fields as $field ) {
            if ( empty( $platform_settings[$field] ) ) {
                $configured = false;
                break;
            }
        }

        if ( ! $configured ) {
            return array(
                'status' => 'not-configured',
                'message' => __( 'App credentials not configured', 'fp-publisher' )
            );
        }

        $meta_key = $this->get_platform_token_meta_key( $platform );
        $connected_clients = 0;

        if ( $meta_key ) {
            global $wpdb;

            $query = $wpdb->prepare(
                "
                SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} AS p
                INNER JOIN {$wpdb->postmeta} AS pm
                    ON pm.post_id = p.ID
                WHERE p.post_type = %s
                    AND p.post_status NOT IN ('trash', 'auto-draft')
                    AND pm.meta_key = %s
                    AND pm.meta_value <> ''
                ",
                'tts_client',
                $meta_key
            );

            $connected_clients = absint( $wpdb->get_var( $query ) );
        }

        if ( $connected_clients > 0 ) {
            $message = ( 1 === $connected_clients )
                ? __( 'Account connected', 'fp-publisher' )
                : sprintf( __( '%d accounts connected', 'fp-publisher' ), $connected_clients );

            return array(
                'status' => 'connected',
                'message' => $message
            );
        }

        return array(
            'status' => 'configured',
            'message' => __( 'Ready to connect accounts', 'fp-publisher' )
        );
    }

    /**
     * Generate OAuth URL for a platform.
     *
     * @param string $platform Platform name.
     * @return string OAuth URL.
     */
    private function get_oauth_url( $platform ) {
        $settings = get_option( 'tts_social_apps', array() );
        $platform_settings = isset( $settings[$platform] ) ? $settings[$platform] : array();
        $redirect_uri = admin_url( 'admin-post.php?action=tts_oauth_' . $platform );
        $state = wp_generate_password( 20, false );
        
        // Store state for verification
        if ( ! session_id() ) {
            session_start();
        }
        $_SESSION['tts_oauth_state'] = $state;

        switch ( $platform ) {
            case 'facebook':
                if ( ! empty( $platform_settings['app_id'] ) ) {
                    return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query( array(
                        'client_id' => $platform_settings['app_id'],
                        'redirect_uri' => $redirect_uri,
                        'scope' => 'pages_manage_posts,pages_read_engagement,pages_show_list',
                        'state' => $state,
                        'response_type' => 'code'
                    ) );
                }
                break;
            case 'instagram':
                if ( ! empty( $platform_settings['app_id'] ) ) {
                    return 'https://api.instagram.com/oauth/authorize?' . http_build_query( array(
                        'client_id' => $platform_settings['app_id'],
                        'redirect_uri' => $redirect_uri,
                        'scope' => 'user_profile,user_media',
                        'state' => $state,
                        'response_type' => 'code'
                    ) );
                }
                break;
            case 'youtube':
                if ( ! empty( $platform_settings['client_id'] ) ) {
                    return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query( array(
                        'client_id' => $platform_settings['client_id'],
                        'redirect_uri' => $redirect_uri,
                        'scope' => 'https://www.googleapis.com/auth/youtube.upload',
                        'state' => $state,
                        'response_type' => 'code',
                        'access_type' => 'offline'
                    ) );
                }
                break;
            case 'tiktok':
                if ( ! empty( $platform_settings['client_key'] ) ) {
                    return 'https://www.tiktok.com/auth/authorize/?' . http_build_query( array(
                        'client_key' => $platform_settings['client_key'],
                        'redirect_uri' => $redirect_uri,
                        'scope' => 'user.info.basic,video.upload',
                        'state' => $state,
                        'response_type' => 'code'
                    ) );
                }
                break;
        }

        return '#';
    }

    /**
     * Render the help and setup page.
     */
    public function render_help_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Help & Setup Guide', 'fp-publisher' ); ?></h1>
            
            <div class="tts-help-container">
                <div class="tts-help-sidebar">
                    <h3><?php esc_html_e( 'Quick Links', 'fp-publisher' ); ?></h3>
                    <ul>
                        <li><a href="#overview"><?php esc_html_e( 'Overview', 'fp-publisher' ); ?></a></li>
                        <li><a href="#facebook"><?php esc_html_e( 'Facebook Setup', 'fp-publisher' ); ?></a></li>
                        <li><a href="#instagram"><?php esc_html_e( 'Instagram Setup', 'fp-publisher' ); ?></a></li>
                        <li><a href="#youtube"><?php esc_html_e( 'YouTube Setup', 'fp-publisher' ); ?></a></li>
                        <li><a href="#tiktok"><?php esc_html_e( 'TikTok Setup', 'fp-publisher' ); ?></a></li>
                        <li><a href="#troubleshooting"><?php esc_html_e( 'Troubleshooting', 'fp-publisher' ); ?></a></li>
                    </ul>
                    
                    <div class="tts-help-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-publisher-social-connections' ) ); ?>" class="button button-primary">
                            <?php esc_html_e( 'Configure Social Apps', 'fp-publisher' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-publisher-client-wizard' ) ); ?>" class="button">
                            <?php esc_html_e( 'Create Client', 'fp-publisher' ); ?>
                        </a>
                    </div>
                </div>
                
                <div class="tts-help-content">
                    <section id="overview">
                        <h2><?php esc_html_e( '🚀 Getting Started', 'fp-publisher' ); ?></h2>
                        <p><?php esc_html_e( 'To use the Social Auto Publisher, you need to:', 'fp-publisher' ); ?></p>
                        <ol>
                            <li><strong><?php esc_html_e( 'Create developer apps', 'fp-publisher' ); ?></strong> <?php esc_html_e( 'on each social media platform', 'fp-publisher' ); ?></li>
                            <li><strong><?php esc_html_e( 'Configure OAuth credentials', 'fp-publisher' ); ?></strong> <?php esc_html_e( 'in Social Connections', 'fp-publisher' ); ?></li>
                            <li><strong><?php esc_html_e( 'Connect your accounts', 'fp-publisher' ); ?></strong> <?php esc_html_e( 'using the OAuth flow', 'fp-publisher' ); ?></li>
                            <li><strong><?php esc_html_e( 'Create clients', 'fp-publisher' ); ?></strong> <?php esc_html_e( 'and assign social accounts', 'fp-publisher' ); ?></li>
                        </ol>
                        
                        <div class="tts-notice-warning">
                            <p><strong><?php esc_html_e( 'Important:', 'fp-publisher' ); ?></strong> <?php esc_html_e( 'Each social media platform requires you to create a developer application. This is a one-time setup per platform.', 'fp-publisher' ); ?></p>
                        </div>
                    </section>

                    <section id="facebook">
                        <h2><?php esc_html_e( '📘 Facebook Setup', 'fp-publisher' ); ?></h2>
                        <h3><?php esc_html_e( 'Step 1: Create Facebook App', 'fp-publisher' ); ?></h3>
                        <ol>
                            <li><?php esc_html_e( 'Visit', 'fp-publisher' ); ?> <a href="https://developers.facebook.com/apps/" target="_blank">Facebook Developers</a></li>
                            <li><?php esc_html_e( 'Click "Create App" → "Business" → "Consumer"', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Enter app name and contact email', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Add "Facebook Login" product', 'fp-publisher' ); ?></li>
                        </ol>
                        
                        <h3><?php esc_html_e( 'Step 2: Configure OAuth Settings', 'fp-publisher' ); ?></h3>
                        <ol>
                            <li><?php esc_html_e( 'Go to Facebook Login → Settings', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Add redirect URI:', 'fp-publisher' ); ?> <code><?php echo esc_url( admin_url( 'admin-post.php?action=tts_oauth_facebook' ) ); ?></code></li>
                            <li><?php esc_html_e( 'Enable "Use Strict Mode for Redirect URIs"', 'fp-publisher' ); ?></li>
                        </ol>
                        
                        <h3><?php esc_html_e( 'Step 3: Get Credentials', 'fp-publisher' ); ?></h3>
                        <ol>
                            <li><?php esc_html_e( 'Go to Settings → Basic', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Copy App ID and App Secret', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Enter these in Social Connections page', 'fp-publisher' ); ?></li>
                        </ol>
                    </section>

                    <section id="instagram">
                        <h2><?php esc_html_e( '📷 Instagram Setup', 'fp-publisher' ); ?></h2>
                        <p><?php esc_html_e( 'Instagram uses the same Facebook app with additional configuration:', 'fp-publisher' ); ?></p>
                        <ol>
                            <li><?php esc_html_e( 'In your Facebook app, add "Instagram Basic Display" product', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Configure redirect URI:', 'fp-publisher' ); ?> <code><?php echo esc_url( admin_url( 'admin-post.php?action=tts_oauth_instagram' ) ); ?></code></li>
                            <li><?php esc_html_e( 'Add your Instagram account as a test user', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Use the same App ID and App Secret from Facebook', 'fp-publisher' ); ?></li>
                        </ol>
                    </section>

                    <section id="youtube">
                        <h2><?php esc_html_e( '🎥 YouTube Setup', 'fp-publisher' ); ?></h2>
                        <h3><?php esc_html_e( 'Step 1: Create Google Project', 'fp-publisher' ); ?></h3>
                        <ol>
                            <li><?php esc_html_e( 'Visit', 'fp-publisher' ); ?> <a href="https://console.developers.google.com/" target="_blank">Google Developers Console</a></li>
                            <li><?php esc_html_e( 'Create a new project or select existing one', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Enable "YouTube Data API v3"', 'fp-publisher' ); ?></li>
                        </ol>
                        
                        <h3><?php esc_html_e( 'Step 2: Create OAuth Credentials', 'fp-publisher' ); ?></h3>
                        <ol>
                            <li><?php esc_html_e( 'Go to Credentials → Create Credentials → OAuth 2.0 Client IDs', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Choose "Web application"', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Add redirect URI:', 'fp-publisher' ); ?> <code><?php echo esc_url( admin_url( 'admin-post.php?action=tts_oauth_youtube' ) ); ?></code></li>
                        </ol>
                    </section>

                    <section id="tiktok">
                        <h2><?php esc_html_e( '🎵 TikTok Setup', 'fp-publisher' ); ?></h2>
                        <div class="tts-notice-warning">
                            <p><strong><?php esc_html_e( 'Note:', 'fp-publisher' ); ?></strong> <?php esc_html_e( 'TikTok requires developer account approval, which can take several days.', 'fp-publisher' ); ?></p>
                        </div>
                        <ol>
                            <li><?php esc_html_e( 'Visit', 'fp-publisher' ); ?> <a href="https://developers.tiktok.com/" target="_blank">TikTok Developers</a></li>
                            <li><?php esc_html_e( 'Apply for developer access', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Create a new app in the developer portal', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Configure redirect URI:', 'fp-publisher' ); ?> <code><?php echo esc_url( admin_url( 'admin-post.php?action=tts_oauth_tiktok' ) ); ?></code></li>
                        </ol>
                    </section>

                    <section id="troubleshooting">
                        <h2><?php esc_html_e( '🔧 Troubleshooting Guide', 'fp-publisher' ); ?></h2>
                        
                        <h3><?php esc_html_e( 'Common Issues and Solutions', 'fp-publisher' ); ?></h3>
                        
                        <div class="tts-troubleshoot-item">
                            <h4><?php esc_html_e( '❌ "OAuth verification failed" Error', 'fp-publisher' ); ?></h4>
                            <p><strong><?php esc_html_e( 'Causes:', 'fp-publisher' ); ?></strong></p>
                            <ul>
                                <li><?php esc_html_e( 'Incorrect redirect URI in app settings', 'fp-publisher' ); ?></li>
                                <li><?php esc_html_e( 'App ID/Secret mismatch', 'fp-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Session issues', 'fp-publisher' ); ?></li>
                            </ul>
                            <p><strong><?php esc_html_e( 'Solutions:', 'fp-publisher' ); ?></strong></p>
                            <ol>
                                <li><?php esc_html_e( 'Verify redirect URI matches exactly:', 'fp-publisher' ); ?> <code><?php echo esc_url( admin_url( 'admin-post.php' ) ); ?></code></li>
                                <li><?php esc_html_e( 'Double-check App ID and App Secret', 'fp-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Clear browser cache and cookies', 'fp-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Try the connection in an incognito/private window', 'fp-publisher' ); ?></li>
                            </ol>
                        </div>
                        
                        <div class="tts-troubleshoot-item">
                            <h4><?php esc_html_e( '🔑 "Failed to obtain access token" Error', 'fp-publisher' ); ?></h4>
                            <p><strong><?php esc_html_e( 'Causes:', 'fp-publisher' ); ?></strong></p>
                            <ul>
                                <li><?php esc_html_e( 'Invalid app credentials', 'fp-publisher' ); ?></li>
                                <li><?php esc_html_e( 'App not approved/active', 'fp-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Insufficient permissions granted', 'fp-publisher' ); ?></li>
                            </ul>
                            <p><strong><?php esc_html_e( 'Solutions:', 'fp-publisher' ); ?></strong></p>
                            <ol>
                                <li><?php esc_html_e( 'Verify app is in "Live" mode (not development)', 'fp-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Check that required permissions are granted during OAuth', 'fp-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Use the "Test Connection" button to validate credentials', 'fp-publisher' ); ?></li>
                            </ol>
                        </div>
                        
                        <div class="tts-troubleshoot-item">
                            <h4><?php esc_html_e( '⚠️ Rate Limiting Issues', 'fp-publisher' ); ?></h4>
                            <p><strong><?php esc_html_e( 'Symptoms:', 'fp-publisher' ); ?></strong></p>
                            <ul>
                                <li><?php esc_html_e( 'Posts failing to publish', 'fp-publisher' ); ?></li>
                                <li><?php esc_html_e( '"Rate limit exceeded" errors in logs', 'fp-publisher' ); ?></li>
                            </ul>
                            <p><strong><?php esc_html_e( 'Solutions:', 'fp-publisher' ); ?></strong></p>
                            <ol>
                                <li><?php esc_html_e( 'Use "Check API Limits" button to monitor usage', 'fp-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Reduce posting frequency in high-volume periods', 'fp-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Consider upgrading to business/developer API tiers', 'fp-publisher' ); ?></li>
                            </ol>
                        </div>

                        <div class="tts-troubleshoot-item">
                            <h4><?php esc_html_e( '🔧 Performance Issues', 'fp-publisher' ); ?></h4>
                            <p><strong><?php esc_html_e( 'Solutions:', 'fp-publisher' ); ?></strong></p>
                            <ol>
                                <li><?php esc_html_e( 'Enable WordPress object caching', 'fp-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Limit number of concurrent social posts', 'fp-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Monitor system performance in Dashboard', 'fp-publisher' ); ?></li>
                            </ol>
                        </div>
                        
                        <h3><?php esc_html_e( 'Getting Support', 'fp-publisher' ); ?></h3>
                        <p><?php esc_html_e( 'If you continue experiencing issues:', 'fp-publisher' ); ?></p>
                        <ol>
                            <li><?php esc_html_e( 'Check the plugin logs for detailed error messages', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Use the "Test Connection" feature to isolate the problem', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Document the exact error message and steps to reproduce', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Contact support with your findings', 'fp-publisher' ); ?></li>
                        </ol>
                    </section>
                </div>
            </div>
        </div>
        
        <style>
        .tts-help-container {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        .tts-help-sidebar {
            flex: 0 0 250px;
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            height: fit-content;
            position: sticky;
            top: 32px;
        }
        .tts-help-content {
            flex: 1;
            background: #fff;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .tts-help-sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .tts-help-sidebar li {
            margin-bottom: 8px;
        }
        .tts-help-sidebar a {
            text-decoration: none;
            padding: 8px 12px;
            display: block;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .tts-help-sidebar a:hover {
            background-color: #f0f0f1;
        }
        .tts-help-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .tts-help-actions .button {
            display: block;
            margin-bottom: 10px;
            text-align: center;
        }
        .tts-help-content section {
            margin-bottom: 40px;
        }
        .tts-help-content h2 {
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .tts-help-content h3 {
            color: #0073aa;
            margin-top: 25px;
        }
        .tts-help-content code {
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 13px;
            word-break: break-all;
        }
        .tts-notice-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
        }
        .tts-help-content dt {
            margin-top: 15px;
        }
        .tts-help-content dd {
            margin-left: 20px;
            margin-bottom: 10px;
        }
        .tts-troubleshoot-item {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .tts-troubleshoot-item h4 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .tts-troubleshoot-item ul, .tts-troubleshoot-item ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        .tts-troubleshoot-item li {
            margin: 5px 0;
        }
        </style>
        <?php
    }

    /**
     * AJAX handler for testing social media connections.
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'tts_test_connection', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'fp-publisher' ) );
        }
        
        $platform = sanitize_key( $_POST['platform'] );
        $settings = get_option( 'tts_social_apps', array() );
        $platform_settings = isset( $settings[$platform] ) ? $settings[$platform] : array();
        
        $result = $this->test_platform_connection( $platform, $platform_settings );
        
        if ( $result['success'] ) {
            wp_send_json_success( array( 'message' => $result['message'] ) );
        } else {
            wp_send_json_error( array( 'message' => $result['message'] ) );
        }
    }
    
    /**
     * AJAX handler for checking API rate limits.
     */
    public function ajax_check_rate_limits() {
        check_ajax_referer( 'tts_check_rate_limits', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'fp-publisher' ) );
        }

        $platform = sanitize_key( $_POST['platform'] );
        $limits = $this->get_platform_rate_limits( $platform );

        wp_send_json_success( $limits );
    }

    /**
     * AJAX handler for saving social media settings.
     */
    public function ajax_save_social_settings() {
        check_ajax_referer( 'tts_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'fp-publisher' ) ) );
        }

        $platform = isset( $_POST['platform'] ) ? sanitize_key( wp_unslash( $_POST['platform'] ) ) : '';
        $credentials = isset( $_POST['credentials'] ) ? wp_unslash( $_POST['credentials'] ) : array();

        if ( empty( $platform ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid platform specified.', 'fp-publisher' ) ) );
        }

        if ( ! is_array( $credentials ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid credentials payload.', 'fp-publisher' ) ) );
        }

        $sanitized_credentials = array();

        foreach ( $credentials as $key => $value ) {
            $sanitized_key = sanitize_key( $key );
            $sanitized_credentials[ $sanitized_key ] = sanitize_text_field( $value );
        }

        $social_apps = get_option( 'tts_social_apps', array() );
        $social_apps[ $platform ] = $sanitized_credentials;

        $updated = update_option( 'tts_social_apps', $social_apps );

        if ( false === $updated && get_option( 'tts_social_apps', array() ) !== $social_apps ) {
            wp_send_json_error( array( 'message' => __( 'Unable to save social media credentials. Please try again.', 'fp-publisher' ) ) );
        }

        wp_send_json_success( array( 'message' => __( 'Social media credentials saved successfully.', 'fp-publisher' ) ) );
    }

    /**
     * Test platform connection.
     *
     * @param string $platform Platform name.
     * @param array  $settings Platform settings.
     * @return array Test result.
     */
    private function test_platform_connection( $platform, $settings ) {
        switch ( $platform ) {
            case 'facebook':
                if ( empty( $settings['app_id'] ) || empty( $settings['app_secret'] ) ) {
                    return array( 'success' => false, 'message' => __( 'App credentials not configured', 'fp-publisher' ) );
                }

                $response = wp_remote_get( 'https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query( array(
                    'client_id' => $settings['app_id'],
                    'client_secret' => $settings['app_secret'],
                    'grant_type' => 'client_credentials'
                ) ) );

                if ( is_wp_error( $response ) ) {
                    return array( 'success' => false, 'message' => __( 'Connection failed: ', 'fp-publisher' ) . $response->get_error_message() );
                }

                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( isset( $body['access_token'] ) ) {
                    return array( 'success' => true, 'message' => __( 'Facebook app credentials valid', 'fp-publisher' ) );
                } else {
                    return array( 'success' => false, 'message' => __( 'Invalid Facebook app credentials', 'fp-publisher' ) );
                }

            case 'instagram':
                if ( empty( $settings['app_id'] ) || empty( $settings['app_secret'] ) ) {
                    return array( 'success' => false, 'message' => __( 'Instagram app credentials not configured', 'fp-publisher' ) );
                }

                $response = wp_remote_get( 'https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query( array(
                    'client_id' => $settings['app_id'],
                    'client_secret' => $settings['app_secret'],
                    'grant_type' => 'client_credentials'
                ) ) );

                if ( is_wp_error( $response ) ) {
                    return array( 'success' => false, 'message' => __( 'Connection failed: ', 'fp-publisher' ) . $response->get_error_message() );
                }

                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( isset( $body['access_token'] ) ) {
                    return array( 'success' => true, 'message' => __( 'Instagram app credentials validated successfully', 'fp-publisher' ) );
                }

                return array( 'success' => false, 'message' => __( 'Invalid Instagram app credentials', 'fp-publisher' ) );

            case 'youtube':
                if ( empty( $settings['client_id'] ) || empty( $settings['client_secret'] ) ) {
                    return array( 'success' => false, 'message' => __( 'Client credentials not configured', 'fp-publisher' ) );
                }

                return array( 'success' => true, 'message' => __( 'YouTube client credentials format valid', 'fp-publisher' ) );

            case 'tiktok':
                $client_key    = isset( $settings['client_key'] ) ? trim( $settings['client_key'] ) : '';
                $client_secret = isset( $settings['client_secret'] ) ? trim( $settings['client_secret'] ) : '';
                $token         = '';
                foreach ( array( 'access_token', 'token', 'tiktok_access_token' ) as $token_key ) {
                    if ( ! empty( $settings[ $token_key ] ) ) {
                        $token = trim( $settings[ $token_key ] );
                        break;
                    }
                }

                $placeholder_checks = array( 'placeholder', 'changeme', 'your-token', 'token' );
                if ( '' !== $token ) {
                    if ( strlen( $token ) < 10 || in_array( strtolower( $token ), $placeholder_checks, true ) ) {
                        return array( 'success' => false, 'message' => __( 'TikTok access token is missing or appears invalid', 'fp-publisher' ) );
                    }

                    $response = wp_remote_get(
                        'https://open.tiktokapis.com/v2/user/info/?fields=open_id',
                        array(
                            'timeout' => 15,
                            'headers' => array(
                                'Authorization' => 'Bearer ' . $token,
                            ),
                        )
                    );

                    if ( is_wp_error( $response ) ) {
                        return array( 'success' => false, 'message' => __( 'Connection failed: ', 'fp-publisher' ) . $response->get_error_message() );
                    }

                    $response_code = wp_remote_retrieve_response_code( $response );
                    if ( 200 === $response_code ) {
                        return array( 'success' => true, 'message' => __( 'TikTok access token validated successfully', 'fp-publisher' ) );
                    }

                    $body          = json_decode( wp_remote_retrieve_body( $response ), true );
                    $error_message = '';

                    if ( isset( $body['error']['message'] ) ) {
                        $error_message = $body['error']['message'];
                    } elseif ( isset( $body['message'] ) ) {
                        $error_message = $body['message'];
                    } else {
                        $error_message = sprintf( __( 'HTTP %d response received', 'fp-publisher' ), $response_code );
                    }

                    return array( 'success' => false, 'message' => sprintf( __( 'TikTok API error: %s', 'fp-publisher' ), $error_message ) );
                }

                if ( empty( $client_key ) || empty( $client_secret ) ) {
                    return array( 'success' => false, 'message' => __( 'TikTok client credentials not configured', 'fp-publisher' ) );
                }

                $credential_placeholders = array( 'placeholder', 'changeme', 'your-client-key', 'your-client-secret' );
                if ( in_array( strtolower( $client_key ), $credential_placeholders, true ) || in_array( strtolower( $client_secret ), $credential_placeholders, true ) ) {
                    return array( 'success' => false, 'message' => __( 'TikTok client credentials appear to be placeholders', 'fp-publisher' ) );
                }

                return array( 'success' => true, 'message' => __( 'TikTok client credentials format valid', 'fp-publisher' ) );

            default:
                return array( 'success' => true, 'message' => __( 'Platform configuration appears valid', 'fp-publisher' ) );
        }
    }
    
    /**
     * Get platform rate limits.
     *
     * @param string $platform Platform name.
     * @return array Rate limit information.
     */
    private function get_platform_rate_limits( $platform ) {
        $settings = get_option( 'tts_settings', array() );
        
        // Check if we have cached rate limit data (updated every 15 minutes)
        $cache_key = "tts_rate_limits_{$platform}";
        $cached_limits = get_transient( $cache_key );
        
        if ( $cached_limits !== false ) {
            return $cached_limits;
        }
        
        $limits = array( 'used' => 0, 'limit' => 100, 'remaining' => 100, 'reset_time' => 'Unknown' );
        
        switch ( $platform ) {
            case 'facebook':
                $limits = $this->get_facebook_rate_limits( $settings );
                break;
                
            case 'instagram':
                $limits = $this->get_instagram_rate_limits( $settings );
                break;
                
            case 'youtube':
                $limits = $this->get_youtube_rate_limits( $settings );
                break;
                
            case 'tiktok':
                $limits = $this->get_tiktok_rate_limits( $settings );
                break;
        }
        
        // Cache the rate limits for 15 minutes
        set_transient( $cache_key, $limits, 15 * MINUTE_IN_SECONDS );
        
        return $limits;
    }
    
    /**
     * Get Facebook API rate limits.
     *
     * @param array $settings Plugin settings.
     * @return array Rate limit data.
     */
    private function get_facebook_rate_limits( $settings ) {
        $access_token = $settings['facebook_access_token'] ?? '';
        
        if ( empty( $access_token ) ) {
            return array( 'used' => 0, 'limit' => 200, 'remaining' => 200, 'reset_time' => 'No token configured' );
        }
        
        $response = wp_remote_get( 'https://graph.facebook.com/me?access_token=' . $access_token, array(
            'timeout' => 10
        ) );
        
        if ( is_wp_error( $response ) ) {
            return array( 'used' => 0, 'limit' => 200, 'remaining' => 200, 'reset_time' => 'API Error' );
        }
        
        $headers = wp_remote_retrieve_headers( $response );
        
        return array(
            'used' => intval( $headers['X-App-Usage'] ?? 0 ),
            'limit' => 200, // Facebook default
            'remaining' => 200 - intval( $headers['X-App-Usage'] ?? 0 ),
            'reset_time' => $headers['X-App-Usage-Reset-Time'] ?? '1 hour'
        );
    }
    
    /**
     * Get Instagram API rate limits.
     *
     * @param array $settings Plugin settings.
     * @return array Rate limit data.
     */
    private function get_instagram_rate_limits( $settings ) {
        $access_token = $settings['instagram_access_token'] ?? '';
        
        if ( empty( $access_token ) ) {
            return array( 'used' => 0, 'limit' => 100, 'remaining' => 100, 'reset_time' => 'No token configured' );
        }
        
        // Instagram Basic Display API limits
        $response = wp_remote_get( 'https://graph.instagram.com/me?access_token=' . $access_token, array(
            'timeout' => 10
        ) );
        
        if ( is_wp_error( $response ) ) {
            return array( 'used' => 0, 'limit' => 100, 'remaining' => 100, 'reset_time' => 'API Error' );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $headers = wp_remote_retrieve_headers( $response );
        
        if ( $response_code === 429 ) {
            return array( 'used' => 100, 'limit' => 100, 'remaining' => 0, 'reset_time' => 'Rate limited' );
        }
        
        return array(
            'used' => intval( $headers['X-RateLimit-Used'] ?? 0 ),
            'limit' => intval( $headers['X-RateLimit-Limit'] ?? 100 ),
            'remaining' => intval( $headers['X-RateLimit-Remaining'] ?? 100 ),
            'reset_time' => $headers['X-RateLimit-Reset'] ?? '1 hour'
        );
    }
    
    /**
     * Get YouTube API rate limits.
     *
     * @param array $settings Plugin settings.
     * @return array Rate limit data.
     */
    private function get_youtube_rate_limits( $settings ) {
        $api_key = $settings['youtube_api_key'] ?? '';
        
        if ( empty( $api_key ) ) {
            return array( 'used' => 0, 'limit' => 10000, 'remaining' => 10000, 'reset_time' => 'No API key configured' );
        }
        
        // YouTube Data API quota information isn't directly available via API
        // We track usage internally
        $daily_usage = get_option( 'tts_youtube_daily_usage', 0 );
        $daily_limit = 10000; // YouTube default quota
        
        return array(
            'used' => $daily_usage,
            'limit' => $daily_limit,
            'remaining' => max( 0, $daily_limit - $daily_usage ),
            'reset_time' => 'Daily at midnight PST'
        );
    }
    
    /**
     * Get TikTok API rate limits.
     *
     * @param array $settings Plugin settings.
     * @return array Rate limit data.
     */
    private function get_tiktok_rate_limits( $settings ) {
        $access_token = $settings['tiktok_access_token'] ?? '';
        
        if ( empty( $access_token ) ) {
            return array( 'used' => 0, 'limit' => 50, 'remaining' => 50, 'reset_time' => 'No token configured' );
        }
        
        // TikTok for Business API has limited public rate limit endpoints
        // We track usage internally based on API calls
        $hourly_usage = get_transient( 'tts_tiktok_hourly_usage' ) ?: 0;
        $hourly_limit = 50; // Typical TikTok limit
        
        return array(
            'used' => $hourly_usage,
            'limit' => $hourly_limit,
            'remaining' => max( 0, $hourly_limit - $hourly_usage ),
            'reset_time' => 'Hourly'
        );
    }
    
    /**
     * AJAX handler for data export.
     */
    public function ajax_export_data() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'fp-publisher' ) ) );
        }
        
        $export_options = array(
            'settings' => isset( $_POST['export_settings'] ) && $_POST['export_settings'] === 'true',
            'social_apps' => isset( $_POST['export_social_apps'] ) && $_POST['export_social_apps'] === 'true',
            'clients' => isset( $_POST['export_clients'] ) && $_POST['export_clients'] === 'true',
            'posts' => isset( $_POST['export_posts'] ) && $_POST['export_posts'] === 'true',
            'logs' => isset( $_POST['export_logs'] ) && $_POST['export_logs'] === 'true',
            'analytics' => isset( $_POST['export_analytics'] ) && $_POST['export_analytics'] === 'true',
            'include_secrets' => isset( $_POST['export_include_secrets'] ) && in_array( $_POST['export_include_secrets'], array( 'true', 'on', '1' ), true )
        );
        
        $result = TTS_Advanced_Utils::export_data( $export_options );
        
        if ( $result['success'] ) {
            // Create download file
            $filename = 'tts-export-' . date( 'Y-m-d-H-i-s' ) . '.json';
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['path'] . '/' . $filename;
            
            file_put_contents( $file_path, json_encode( $result['data'], JSON_PRETTY_PRINT ) );
            
            wp_send_json_success( array( 
                'message' => __( 'Export completed successfully', 'fp-publisher' ),
                'download_url' => $upload_dir['url'] . '/' . $filename,
                'file_size' => $result['file_size']
            ) );
        } else {
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }
    }
    
    /**
     * AJAX handler for data import.
     */
    public function ajax_import_data() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'fp-publisher' ) ) );
        }
        
        if ( ! isset( $_FILES['import_file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No file provided', 'fp-publisher' ) ) );
        }
        
        $file = $_FILES['import_file'];
        $import_data = json_decode( file_get_contents( $file['tmp_name'] ), true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( array( 'message' => __( 'Invalid JSON file', 'fp-publisher' ) ) );
        }
        
        $import_options = array(
            'overwrite_settings' => isset( $_POST['overwrite_settings'] ) && $_POST['overwrite_settings'] === 'true',
            'overwrite_social_apps' => isset( $_POST['overwrite_social_apps'] ) && $_POST['overwrite_social_apps'] === 'true',
            'import_clients' => isset( $_POST['import_clients'] ) && $_POST['import_clients'] === 'true',
            'import_posts' => isset( $_POST['import_posts'] ) && $_POST['import_posts'] === 'true'
        );
        
        $result = TTS_Advanced_Utils::import_data( $import_data, $import_options );
        
        if ( $result['success'] ) {
            wp_send_json_success( array( 
                'message' => __( 'Import completed successfully', 'fp-publisher' ),
                'log' => $result['log']
            ) );
        } else {
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }
    }
    
    /**
     * AJAX handler for system maintenance.
     */
    public function ajax_system_maintenance() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'fp-publisher' ) ) );
        }
        
        $tasks = array(
            'optimize_database' => isset( $_POST['optimize_database'] ) && $_POST['optimize_database'] === 'true',
            'clear_cache' => isset( $_POST['clear_cache'] ) && $_POST['clear_cache'] === 'true',
            'cleanup_logs' => isset( $_POST['cleanup_logs'] ) && $_POST['cleanup_logs'] === 'true',
            'update_statistics' => isset( $_POST['update_statistics'] ) && $_POST['update_statistics'] === 'true',
            'check_health' => isset( $_POST['check_health'] ) && $_POST['check_health'] === 'true'
        );
        
        $result = TTS_Advanced_Utils::system_maintenance( $tasks );
        
        if ( $result['success'] ) {
            wp_send_json_success( array( 
                'message' => __( 'System maintenance completed', 'fp-publisher' ),
                'log' => $result['log']
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Maintenance failed', 'fp-publisher' ) ) );
        }
    }
    
    /**
     * AJAX handler for system report generation.
     */
    public function ajax_generate_report() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'fp-publisher' ) ) );
        }
        
        $report = TTS_Advanced_Utils::generate_system_report();
        
        wp_send_json_success( array( 
            'message' => __( 'System report generated', 'fp-publisher' ),
            'report' => $report
        ) );
    }
    
    /**
     * AJAX handler for quick connection check.
     */
    public function ajax_quick_connection_check() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        $platform = isset( $_POST['platform'] ) ? sanitize_key( $_POST['platform'] ) : '';

        $status_messages = array(
            'not-configured' => __( 'App credentials not configured', 'fp-publisher' ),
            'configured' => __( 'Ready to connect accounts', 'fp-publisher' ),
            'connected' => __( 'Account connected', 'fp-publisher' ),
            'error' => __( 'Connection error. Please try again.', 'fp-publisher' ),
        );

        if ( empty( $platform ) ) {
            wp_send_json_success(
                array(
                    'status'  => 'error',
                    'message' => $status_messages['error'],
                )
            );
            return;
        }

        $connection_status = $this->check_platform_connection_status( $platform );
        $status = isset( $connection_status['status'] ) ? $connection_status['status'] : 'error';
        $message = isset( $connection_status['message'] ) && '' !== $connection_status['message']
            ? $connection_status['message']
            : ( isset( $status_messages[ $status ] ) ? $status_messages[ $status ] : $status_messages['error'] );

        wp_send_json_success(
            array(
                'status'  => $status,
                'message' => $message,
            )
        );
    }
    
    /**
     * Get required fields for platform.
     *
     * @param string $platform Platform name.
     * @return array Required fields.
     */
    private function get_required_platform_fields( $platform ) {
        $fields = array(
            'facebook' => array( 'app_id', 'app_secret' ),
            'instagram' => array( 'app_id', 'app_secret' ),
            'youtube' => array( 'client_id', 'client_secret' ),
            'tiktok' => array( 'client_key', 'client_secret' )
        );
        
        return isset( $fields[$platform] ) ? $fields[$platform] : array();
    }
    
    /**
     * AJAX handler for health check refresh.
     */
    public function ajax_refresh_health() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'fp-publisher' ) ) );
        }
        
        // Perform fresh health check
        $health_data = TTS_Monitoring::perform_health_check();
        
        wp_send_json_success( array( 
            'message' => __( 'Health check completed', 'fp-publisher' ),
            'health_data' => $health_data
        ) );
    }
    
    /**
     * AJAX handler for showing export modal.
     */
    public function ajax_show_export_modal() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'fp-publisher' ) ) );
        }
        
        ob_start();
        ?>
        <div class="tts-modal-content">
            <h2><?php esc_html_e( 'Export Data', 'fp-publisher' ); ?></h2>
            <form id="tts-export-form">
                <div class="tts-export-options">
                    <p class="description">
                        <?php esc_html_e( 'Sensitive credentials are excluded unless you explicitly include them below.', 'fp-publisher' ); ?>
                    </p>
                    <label>
                        <input type="checkbox" name="export_settings" checked>
                        <?php esc_html_e( 'Plugin Settings', 'fp-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="export_social_apps" checked>
                        <?php esc_html_e( 'Social Media Configurations', 'fp-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="export_clients" checked>
                        <?php esc_html_e( 'Clients', 'fp-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="export_posts">
                        <?php esc_html_e( 'Social Posts (last 100)', 'fp-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="export_logs">
                        <?php esc_html_e( 'Recent Logs (last 30 days)', 'fp-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="export_analytics">
                        <?php esc_html_e( 'Analytics Data', 'fp-publisher' ); ?>
                    </label>
                    <label class="tts-export-include-secrets">
                        <input type="checkbox" name="export_include_secrets">
                        <?php esc_html_e( 'Include secrets (app/client secrets, tokens)', 'fp-publisher' ); ?>
                        <span class="description"><?php esc_html_e( 'Only enable this on secure systems. Without this option the export will mark secrets as [REDACTED].', 'fp-publisher' ); ?></span>
                    </label>
                </div>
                <div class="tts-modal-actions">
                    <button type="submit" class="tts-btn primary">
                        <?php esc_html_e( 'Export', 'fp-publisher' ); ?>
                    </button>
                    <button type="button" class="tts-btn secondary tts-modal-close">
                        <?php esc_html_e( 'Cancel', 'fp-publisher' ); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        $modal_html = ob_get_clean();
        
        wp_send_json_success( array( 
            'modal_html' => $modal_html
        ) );
    }
    
    /**
     * AJAX handler for showing import modal.
     */
    public function ajax_show_import_modal() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'fp-publisher' ) ) );
        }
        
        ob_start();
        ?>
        <div class="tts-modal-content">
            <h2><?php esc_html_e( 'Import Data', 'fp-publisher' ); ?></h2>
            <form id="tts-import-form" enctype="multipart/form-data">
                <div class="tts-import-file">
                    <label for="import_file">
                        <?php esc_html_e( 'Select Export File:', 'fp-publisher' ); ?>
                    </label>
                    <input type="file" id="import_file" name="import_file" accept=".json" required>
                </div>
                
                <div class="tts-import-options">
                    <h4><?php esc_html_e( 'Import Options:', 'fp-publisher' ); ?></h4>
                    <label>
                        <input type="checkbox" name="overwrite_settings">
                        <?php esc_html_e( 'Overwrite existing settings', 'fp-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="overwrite_social_apps">
                        <?php esc_html_e( 'Overwrite social media configurations', 'fp-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="import_clients" checked>
                        <?php esc_html_e( 'Import clients', 'fp-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="import_posts">
                        <?php esc_html_e( 'Import social posts (as drafts)', 'fp-publisher' ); ?>
                    </label>
                </div>
                
                <div class="tts-modal-actions">
                    <button type="submit" class="tts-btn primary">
                        <?php esc_html_e( 'Import', 'fp-publisher' ); ?>
                    </button>
                    <button type="button" class="tts-btn secondary tts-modal-close">
                        <?php esc_html_e( 'Cancel', 'fp-publisher' ); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        $modal_html = ob_get_clean();
        
        wp_send_json_success( array( 
            'modal_html' => $modal_html
        ) );
    }

    /**
     * Render Content Management page.
     */
    public function render_content_management_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Content Management', 'fp-publisher' ) . '</h1>';
        echo '<p>' . esc_html__( 'Manage content from multiple sources: Trello, Google Drive, Dropbox, local uploads, and manual creation.', 'fp-publisher' ) . '</p>';
        
        $this->render_content_management_tabs();
        
        echo '</div>';
    }

    /**
     * Render Content Management tabs interface.
     */
    private function render_content_management_tabs() {
        $sources = TTS_Content_Source::SOURCES;
        $stats = TTS_Content_Source::get_source_stats();
        $trello_enabled = get_option( 'tts_trello_enabled', 1 );
        
        echo '<div class="tts-content-tabs">';
        echo '<nav class="nav-tab-wrapper">';
        
        // Overview tab
        echo '<a href="#overview" class="nav-tab nav-tab-active" data-tab="overview">' . esc_html__( 'Overview', 'fp-publisher' ) . '</a>';
        
        // Source tabs
        foreach ( $sources as $source_key => $source_name ) {
            // Skip Trello if disabled
            if ( ! $trello_enabled && $source_key === 'trello' ) {
                continue;
            }
            
            $count = isset( $stats[ $source_key ] ) ? $stats[ $source_key ]['count'] : 0;
            echo '<a href="#' . esc_attr( $source_key ) . '" class="nav-tab" data-tab="' . esc_attr( $source_key ) . '">';
            echo esc_html( $source_name );
            if ( $count > 0 ) {
                echo ' <span class="count">(' . intval( $count ) . ')</span>';
            }
            echo '</a>';
        }
        
        echo '</nav>';
        
        // Tab content
        echo '<div class="tab-content">';
        
        // Overview tab content
        echo '<div id="overview-content" class="tab-panel active">';
        $this->render_overview_content( $stats );
        echo '</div>';
        
        // Source tab contents
        foreach ( $sources as $source_key => $source_name ) {
            // Skip Trello if disabled
            if ( ! $trello_enabled && $source_key === 'trello' ) {
                continue;
            }
            
            echo '<div id="' . esc_attr( $source_key ) . '-content" class="tab-panel">';
            $this->render_source_content( $source_key, $source_name );
            echo '</div>';
        }
        
        echo '</div>'; // tab-content
        echo '</div>'; // tts-content-tabs
        
        // Add JavaScript for tab functionality
        $this->add_content_management_scripts();
    }

    /**
     * Render overview content.
     *
     * @param array $stats Source statistics.
     */
    private function render_overview_content( $stats ) {
        $trello_enabled = get_option( 'tts_trello_enabled', 1 );
        
        echo '<div class="tts-overview-grid">';
        
        if ( ! $trello_enabled ) {
            // Show prominent manual upload section when Trello is disabled
            echo '<div class="tts-manual-first-section">';
            echo '<h3>📁 ' . esc_html__( 'Manual Content Management', 'fp-publisher' ) . '</h3>';
            echo '<p>' . esc_html__( 'Trello integration is disabled. Focus on manual content creation and uploads.', 'fp-publisher' ) . '</p>';
            echo '<div class="tts-manual-actions">';
            echo '<button class="tts-btn primary large" data-action="create-manual">';
            echo '<span class="dashicons dashicons-plus"></span>';
            echo esc_html__( 'Create New Content', 'fp-publisher' );
            echo '</button>';
            echo '<button class="tts-btn secondary large" data-action="upload-file">';
            echo '<span class="dashicons dashicons-upload"></span>';
            echo esc_html__( 'Upload Files', 'fp-publisher' );
            echo '</button>';
            echo '</div>';
            echo '</div>';
        }
        
        // Statistics cards
        echo '<div class="tts-stats-grid">';
        foreach ( TTS_Content_Source::SOURCES as $source_key => $source_name ) {
            // Skip Trello if disabled
            if ( ! $trello_enabled && $source_key === 'trello' ) {
                continue;
            }
            
            $count = isset( $stats[ $source_key ] ) ? $stats[ $source_key ]['count'] : 0;
            echo '<div class="tts-stat-card">';
            echo '<h3>' . esc_html( $source_name ) . '</h3>';
            echo '<div class="stat-number">' . intval( $count ) . '</div>';
            echo '<div class="stat-label">' . esc_html__( 'Content Items', 'fp-publisher' ) . '</div>';
            echo '</div>';
        }
        echo '</div>';
        
        // Quick actions
        echo '<div class="tts-quick-actions">';
        echo '<h3>' . esc_html__( 'Quick Actions', 'fp-publisher' ) . '</h3>';
        echo '<div class="actions-grid">';
        
        if ( $trello_enabled ) {
            echo '<button class="tts-btn primary" data-action="sync-all" data-source="all">';
            echo '<span class="dashicons dashicons-update"></span>';
            echo esc_html__( 'Sync All Sources', 'fp-publisher' );
            echo '</button>';
        }
        
        echo '<button class="tts-btn secondary" data-action="create-manual">';
        echo '<span class="dashicons dashicons-plus"></span>';
        echo esc_html__( 'Create Manual Content', 'fp-publisher' );
        echo '</button>';
        
        echo '<button class="tts-btn secondary" data-action="upload-file">';
        echo '<span class="dashicons dashicons-upload"></span>';
        echo esc_html__( 'Upload Files', 'fp-publisher' );
        echo '</button>';
        
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Render source-specific content.
     *
     * @param string $source_key The source key.
     * @param string $source_name The source name.
     */
    private function render_source_content( $source_key, $source_name ) {
        echo '<div class="tts-source-content">';
        
        // Source header with actions
        echo '<div class="source-header">';
        echo '<h3>' . esc_html( $source_name ) . ' ' . esc_html__( 'Content', 'fp-publisher' ) . '</h3>';
        echo '<div class="source-actions">';
        
        if ( in_array( $source_key, array( 'trello', 'google_drive', 'dropbox' ), true ) ) {
            echo '<button class="tts-btn primary" data-action="sync" data-source="' . esc_attr( $source_key ) . '">';
            echo '<span class="dashicons dashicons-update"></span>';
            echo esc_html__( 'Sync Now', 'fp-publisher' );
            echo '</button>';
        }
        
        if ( in_array( $source_key, array( 'local_upload', 'manual' ), true ) ) {
            echo '<button class="tts-btn primary" data-action="add-content" data-source="' . esc_attr( $source_key ) . '">';
            echo '<span class="dashicons dashicons-plus"></span>';
            echo esc_html__( 'Add Content', 'fp-publisher' );
            echo '</button>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // Content list
        echo '<div class="source-content-list" id="content-list-' . esc_attr( $source_key ) . '">';
        $this->render_source_content_list( $source_key );
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Render content list for a specific source.
     *
     * @param string $source_key The source key.
     */
    private function render_source_content_list( $source_key ) {
        $query = TTS_Content_Source::get_posts_by_source( $source_key, array( 'posts_per_page' => 20 ) );
        
        if ( $query->have_posts() ) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . esc_html__( 'Title', 'fp-publisher' ) . '</th>';
            echo '<th>' . esc_html__( 'Source Reference', 'fp-publisher' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'fp-publisher' ) . '</th>';
            echo '<th>' . esc_html__( 'Date', 'fp-publisher' ) . '</th>';
            echo '<th>' . esc_html__( 'Actions', 'fp-publisher' ) . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();
                $source_ref = get_post_meta( $post_id, '_tts_source_reference', true );
                
                echo '<tr>';
                echo '<td><strong>' . esc_html( get_the_title() ) . '</strong></td>';
                echo '<td>' . esc_html( $source_ref ) . '</td>';
                echo '<td>' . esc_html( get_post_status() ) . '</td>';
                echo '<td>' . esc_html( get_the_date() ) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url( get_edit_post_link( $post_id ) ) . '" class="button button-small">' . esc_html__( 'Edit', 'fp-publisher' ) . '</a>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            
            wp_reset_postdata();
        } else {
            echo '<div class="tts-empty-state">';
            echo '<p>' . esc_html__( 'No content found for this source.', 'fp-publisher' ) . '</p>';
            if ( in_array( $source_key, array( 'trello', 'google_drive', 'dropbox' ), true ) ) {
                echo '<p><em>' . esc_html__( 'Try syncing to import content from this source.', 'fp-publisher' ) . '</em></p>';
            }
            echo '</div>';
        }
    }

    /**
     * Add Content Management scripts and styles.
     */
    private function add_content_management_scripts() {
        ?>
        <style>
        .tts-content-tabs { margin-top: 20px; }
        .tab-panel { display: none; padding: 20px 0; }
        .tab-panel.active { display: block; }
        .tts-overview-grid { display: grid; gap: 20px; }
        .tts-manual-first-section { 
            background: #e7f3ff; 
            border: 2px solid #135e96; 
            border-radius: 8px; 
            padding: 20px; 
            margin-bottom: 20px; 
            text-align: center; 
        }
        .tts-manual-first-section h3 { margin-top: 0; color: #135e96; }
        .tts-manual-actions { margin-top: 15px; display: flex; gap: 15px; justify-content: center; }
        .tts-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .tts-stat-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; text-align: center; }
        .tts-stat-card h3 { margin: 0 0 10px; font-size: 14px; color: #50575e; }
        .stat-number { font-size: 32px; font-weight: bold; color: #1d2327; margin-bottom: 5px; }
        .stat-label { font-size: 12px; color: #646970; }
        .tts-quick-actions h3 { margin: 0 0 15px; }
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        .tts-btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; }
        .tts-btn.large { padding: 12px 20px; font-size: 16px; font-weight: bold; }
        .tts-btn.primary { background: #2271b1; color: #fff; }
        .tts-btn.secondary { background: #f0f0f1; color: #50575e; border: 1px solid #c3c4c7; }
        .tts-btn:hover.primary { background: #135e96; }
        .tts-btn:hover.secondary { background: #e8e8e9; }
        .source-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .source-header h3 { margin: 0; }
        .tts-empty-state { text-align: center; padding: 40px; color: #646970; }
        .nav-tab .count { background: #72aee6; color: #fff; border-radius: 9px; padding: 2px 6px; font-size: 11px; margin-left: 4px; }
        .tts-connection-quick-status { margin: 10px 0; }
        .tts-client-status { margin-bottom: 8px; padding: 8px; background: #f9f9f9; border-radius: 4px; }
        .tts-widget-actions { margin-top: 15px; }
        </style>
        
        <?php
        $syncable_sources = array();
        $potential_sources = array( 'trello', 'google_drive', 'dropbox' );

        foreach ( $potential_sources as $sync_source ) {
            if ( 'trello' === $sync_source && ! get_option( 'tts_trello_enabled', 1 ) ) {
                continue;
            }

            if ( array_key_exists( $sync_source, TTS_Content_Source::SOURCES ) ) {
                $syncable_sources[] = $sync_source;
            }
        }

        $sync_nonce = wp_create_nonce( 'tts_admin_nonce' );
        ?>
        <script>
        const ttsSyncSources = <?php echo wp_json_encode( array_values( $syncable_sources ) ); ?>;
        const ttsSyncNonce = '<?php echo esc_js( $sync_nonce ); ?>';

        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                
                // Update tab appearance
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show corresponding content
                $('.tab-panel').removeClass('active');
                $('#' + tab + '-content').addClass('active');
            });
            
            // Quick actions
            $('[data-action]').on('click', function() {
                var action = $(this).data('action');
                var source = $(this).data('source');
                
                switch(action) {
                    case 'sync':
                    case 'sync-all':
                        handleSync(source);
                        break;
                    case 'add-content':
                        handleAddContent(source);
                        break;
                    case 'create-manual':
                        handleCreateManual();
                        break;
                    case 'upload-file':
                        handleUploadFile();
                        break;
                }
            });
            
            function toggleLoading($button, isLoading) {
                if (!$button || !$button.length) {
                    return;
                }

                $button.prop('disabled', isLoading);

                var $icon = $button.find('.dashicons');
                if (isLoading) {
                    $icon.addClass('fa-spin');
                } else {
                    $icon.removeClass('fa-spin');
                }
            }

            function handleSync(source) {
                var queue = [];

                if (source === 'all') {
                    queue = Array.isArray(ttsSyncSources) ? ttsSyncSources.slice() : [];
                } else if (source) {
                    queue = [source];
                }

                if (!queue.length) {
                    return;
                }

                var $triggerBtn = (source === 'all')
                    ? $('[data-action="sync-all"][data-source="all"]')
                    : $('[data-action="sync"][data-source="' + source + '"]');

                if (!$triggerBtn.length) {
                    return;
                }

                var successMessages = [];
                var errorMessages = [];

                toggleLoading($triggerBtn, true);

                var processNext = function() {
                    if (!queue.length) {
                        toggleLoading($triggerBtn, false);

                        if (errorMessages.length) {
                            alert(errorMessages.join('\n'));
                            return;
                        }

                        if (successMessages.length) {
                            alert(successMessages.join('\n'));
                        }

                        location.reload();
                        return;
                    }

                    var currentSource = queue.shift();
                    var $sourceButton = (source === 'all')
                        ? $('[data-action="sync"][data-source="' + currentSource + '"]')
                        : $triggerBtn;

                    toggleLoading($sourceButton, true);

                    $.post(ajaxurl, {
                        action: 'tts_sync_content_sources',
                        source: currentSource,
                        nonce: ttsSyncNonce
                    }, function(response) {
                        if (response && response.success) {
                            var message = '';

                            if (response.data) {
                                if (typeof response.data === 'string') {
                                    message = response.data;
                                } else if (response.data.message) {
                                    message = response.data.message;
                                }
                            }

                            if (!message) {
                                message = '<?php echo esc_js( __( 'Sync completed successfully.', 'fp-publisher' ) ); ?>';
                            }

                            successMessages.push(message);
                        } else {
                            var errorText = '';

                            if (response && response.data) {
                                if (typeof response.data === 'string') {
                                    errorText = response.data;
                                } else if (response.data.message) {
                                    errorText = response.data.message;
                                } else {
                                    try {
                                        errorText = JSON.stringify(response.data);
                                    } catch (err) {
                                        errorText = '';
                                    }
                                }
                            }

                            if (!errorText) {
                                errorText = '<?php echo esc_js( __( 'Unknown error', 'fp-publisher' ) ); ?>';
                            }

                            errorMessages.push('<?php echo esc_js( __( 'Error syncing source', 'fp-publisher' ) ); ?> ' + currentSource + ': ' + errorText);
                        }
                    }).fail(function() {
                        errorMessages.push('<?php echo esc_js( __( 'Error syncing source', 'fp-publisher' ) ); ?> ' + currentSource + ': <?php echo esc_js( __( 'Request failed', 'fp-publisher' ) ); ?>');
                    }).always(function() {
                        toggleLoading($sourceButton, false);
                        processNext();
                    });
                };

                processNext();
            }
            
            function handleAddContent(source) {
                var title = prompt('<?php echo esc_js( __( "Enter content title:", "fp-publisher" ) ); ?>');
                if (!title) return;
                
                var content = prompt('<?php echo esc_js( __( "Enter content:", "fp-publisher" ) ); ?>');
                if (!content) return;
                
                $.post(ajaxurl, {
                    action: 'tts_add_content_source',
                    source: source,
                    title: title,
                    content: content,
                    nonce: '<?php echo wp_create_nonce( "tts_admin_nonce" ); ?>'
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            }
            
            function handleCreateManual() {
                window.location.href = '<?php echo admin_url( "post-new.php?post_type=tts_social_post&content_source=manual" ); ?>';
            }
            
            function handleUploadFile() {
                window.location.href = '<?php echo admin_url( "post-new.php?post_type=tts_social_post&content_source=local_upload" ); ?>';
            }
        });
        </script>
        <?php
    }

    /**
     * Delegate to calendar page render method.
     */
    public function render_calendar_page() {
        // Check user capabilities early
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'fp-publisher' ) );
        }
        
        try {
            global $tts_calendar_page;
            if ( isset( $tts_calendar_page ) && $tts_calendar_page instanceof TTS_Calendar_Page ) {
                $tts_calendar_page->render_page();
            } else {
                // Fallback content when calendar page class is not available
                echo '<div class="wrap">';
                echo '<h1>' . esc_html__( 'Calendar', 'fp-publisher' ) . '</h1>';
                echo '<div class="notice notice-warning">';
                echo '<p>' . esc_html__( 'Calendar functionality is temporarily unavailable. Please refresh the page or contact support if the issue persists.', 'fp-publisher' ) . '</p>';
                echo '</div>';
                echo '<p>' . esc_html__( 'This page will display your scheduled social media posts in a calendar view.', 'fp-publisher' ) . '</p>';
                echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-main' ) ) . '" class="button button-primary">' . esc_html__( 'Return to Dashboard', 'fp-publisher' ) . '</a>';
                echo '</div>';
            }
        } catch ( Exception $e ) {
            error_log( 'TTS_Admin render_calendar_page error: ' . $e->getMessage() );
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Calendar', 'fp-publisher' ) . '</h1>';
            echo '<div class="notice notice-error">';
            echo '<p>' . esc_html__( 'An error occurred while loading the calendar page. Please refresh the page or contact support.', 'fp-publisher' ) . '</p>';
            echo '</div>';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-main' ) ) . '" class="button button-primary">' . esc_html__( 'Return to Dashboard', 'fp-publisher' ) . '</a>';
            echo '</div>';
        }
    }

    /**
     * Delegate to analytics page render method.
     */
    public function render_analytics_page() {
        // Check user capabilities early
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'fp-publisher' ) );
        }
        
        try {
            global $tts_analytics_page;
            if ( isset( $tts_analytics_page ) && $tts_analytics_page instanceof TTS_Analytics_Page ) {
                $tts_analytics_page->render_page();
            } else {
                // Fallback content when analytics page class is not available
                echo '<div class="wrap">';
                echo '<h1>' . esc_html__( 'Analytics', 'fp-publisher' ) . '</h1>';
                echo '<div class="notice notice-warning">';
                echo '<p>' . esc_html__( 'Analytics functionality is temporarily unavailable. Please refresh the page or contact support if the issue persists.', 'fp-publisher' ) . '</p>';
                echo '</div>';
                echo '<p>' . esc_html__( 'This page will display analytics and insights for your social media publishing activities.', 'fp-publisher' ) . '</p>';
                echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-main' ) ) . '" class="button button-primary">' . esc_html__( 'Return to Dashboard', 'fp-publisher' ) . '</a>';
                echo '</div>';
            }
        } catch ( Exception $e ) {
            error_log( 'TTS_Admin render_analytics_page error: ' . $e->getMessage() );
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Analytics', 'fp-publisher' ) . '</h1>';
            echo '<div class="notice notice-error">';
            echo '<p>' . esc_html__( 'An error occurred while loading the analytics page. Please refresh the page or contact support.', 'fp-publisher' ) . '</p>';
            echo '</div>';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-main' ) ) . '" class="button button-primary">' . esc_html__( 'Return to Dashboard', 'fp-publisher' ) . '</a>';
            echo '</div>';
        }
    }

    /**
     * Delegate to health page render method.
     */
    public function render_health_page() {
        global $tts_health_page;
        if ( isset( $tts_health_page ) && $tts_health_page instanceof TTS_Health_Page ) {
            $tts_health_page->render_page();
        }
    }

    /**
     * Delegate to log page render method.
     */
    public function render_log_page() {
        global $tts_log_page;
        if ( isset( $tts_log_page ) && $tts_log_page instanceof TTS_Log_Page ) {
            $tts_log_page->render_page();
        }
    }

    /**
     * Delegate to AI features page render method.
     */
    public function render_ai_features_page() {
        global $tts_ai_features_page;
        if ( isset( $tts_ai_features_page ) && $tts_ai_features_page instanceof TTS_AI_Features_Page ) {
            $tts_ai_features_page->render_page();
        }
    }

    /**
     * Render connection test page.
     */
    public function render_connection_test_page() {
        // Handle settings save
        if ( isset( $_POST['tts_settings_nonce'] ) && wp_verify_nonce( $_POST['tts_settings_nonce'], 'tts_settings' ) ) {
            $trello_enabled = isset( $_POST['trello_enabled'] ) ? 1 : 0;
            update_option( 'tts_trello_enabled', $trello_enabled );
            
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully.', 'fp-publisher' ) . '</p></div>';
        }
        
        $trello_enabled = get_option( 'tts_trello_enabled', 1 );
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Test Connections & Settings', 'fp-publisher' ) . '</h1>';
        
        // Trello Settings Section
        echo '<div class="tts-connection-section">';
        echo '<h2>' . esc_html__( 'Trello Integration Settings', 'fp-publisher' ) . '</h2>';
        echo '<form method="post">';
        wp_nonce_field( 'tts_settings', 'tts_settings_nonce' );
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__( 'Enable Trello Integration', 'fp-publisher' ) . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="trello_enabled" value="1" ' . checked( $trello_enabled, 1, false ) . ' /> ';
        echo esc_html__( 'Enable automatic content import from Trello boards', 'fp-publisher' ) . '</label>';
        echo '<p class="description">' . esc_html__( 'When disabled, the platform will focus on manual content uploads and creation.', 'fp-publisher' ) . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        submit_button( __( 'Save Settings', 'fp-publisher' ) );
        echo '</form>';
        echo '</div>';
        
        // Connection Testing Section
        echo '<div class="tts-connection-section">';
        echo '<h2>' . esc_html__( 'Test Client Connections', 'fp-publisher' ) . '</h2>';
        echo '<p>' . esc_html__( 'Test the API connections for your configured clients and social media accounts.', 'fp-publisher' ) . '</p>';
        
        // Get all clients
        $clients = get_posts( array(
            'post_type'      => 'tts_client',
            'posts_per_page' => -1,
            'post_status'    => 'any'
        ) );
        
        if ( ! empty( $clients ) ) {
            echo '<div class="tts-clients-grid">';
            foreach ( $clients as $client ) {
                $this->render_client_connection_card( $client );
            }
            echo '</div>';
        } else {
            echo '<div class="tts-no-clients">';
            echo '<p>' . esc_html__( 'No clients configured yet.', 'fp-publisher' ) . '</p>';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-client-wizard' ) ) . '" class="button button-primary">' . esc_html__( 'Create Your First Client', 'fp-publisher' ) . '</a>';
            echo '</div>';
        }
        echo '</div>';
        
        // Social Media Platform Testing Section
        echo '<div class="tts-connection-section">';
        echo '<h2>' . esc_html__( 'Test Social Media API Connections', 'fp-publisher' ) . '</h2>';
        echo '<p>' . esc_html__( 'Test the global API configurations for each social media platform.', 'fp-publisher' ) . '</p>';
        
        $social_apps = get_option( 'tts_social_apps', array() );
        $platforms = array(
            'facebook' => __( 'Facebook', 'fp-publisher' ),
            'instagram' => __( 'Instagram', 'fp-publisher' ),
            'youtube' => __( 'YouTube', 'fp-publisher' ),
            'tiktok' => __( 'TikTok', 'fp-publisher' )
        );
        
        echo '<div class="tts-platforms-grid">';
        foreach ( $platforms as $platform_key => $platform_name ) {
            $this->render_platform_connection_card( $platform_key, $platform_name, $social_apps );
        }
        echo '</div>';
        echo '</div>';
        
        // Add JavaScript for testing
        $this->add_connection_test_scripts();
        
        echo '</div>';
    }

    /**
     * Render a client connection card.
     *
     * @param WP_Post $client The client post object.
     */
    private function render_client_connection_card( $client ) {
        $client_id = $client->ID;
        $client_title = get_the_title( $client_id );
        
        // Get client tokens and settings
        $trello_key = get_post_meta( $client_id, '_tts_trello_key', true );
        $trello_token = get_post_meta( $client_id, '_tts_trello_token', true );
        $facebook_token = get_post_meta( $client_id, '_tts_fb_token', true );
        $instagram_token = get_post_meta( $client_id, '_tts_ig_token', true );
        $youtube_token = get_post_meta( $client_id, '_tts_yt_token', true );
        $tiktok_token = get_post_meta( $client_id, '_tts_tt_token', true );
        
        echo '<div class="tts-client-card" data-client-id="' . esc_attr( $client_id ) . '">';
        echo '<h3>' . esc_html( $client_title ) . '</h3>';
        
        // Test results container
        echo '<div class="tts-test-results" id="test-results-' . esc_attr( $client_id ) . '"></div>';
        
        // Connection status overview
        echo '<div class="tts-connection-overview">';
        if ( $trello_key && $trello_token ) {
            echo '<span class="tts-connection-item configured">📋 Trello</span>';
        } else {
            echo '<span class="tts-connection-item not-configured">📋 Trello</span>';
        }
        
        if ( $facebook_token ) {
            echo '<span class="tts-connection-item configured">📘 Facebook</span>';
        } else {
            echo '<span class="tts-connection-item not-configured">📘 Facebook</span>';
        }
        
        if ( $instagram_token ) {
            echo '<span class="tts-connection-item configured">📷 Instagram</span>';
        } else {
            echo '<span class="tts-connection-item not-configured">📷 Instagram</span>';
        }
        
        if ( $youtube_token ) {
            echo '<span class="tts-connection-item configured">🎥 YouTube</span>';
        } else {
            echo '<span class="tts-connection-item not-configured">🎥 YouTube</span>';
        }
        
        if ( $tiktok_token ) {
            echo '<span class="tts-connection-item configured">🎵 TikTok</span>';
        } else {
            echo '<span class="tts-connection-item not-configured">🎵 TikTok</span>';
        }
        echo '</div>';
        
        // Action buttons
        echo '<div class="tts-client-actions">';
        echo '<button class="button button-primary tts-test-client-btn" data-client-id="' . esc_attr( $client_id ) . '">';
        echo esc_html__( 'Test All Connections', 'fp-publisher' );
        echo '</button>';
        echo '<a href="' . esc_url( get_edit_post_link( $client_id ) ) . '" class="button">' . esc_html__( 'Edit Client', 'fp-publisher' ) . '</a>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Render a platform connection card.
     *
     * @param string $platform_key   The platform key.
     * @param string $platform_name  The platform display name.
     * @param array  $social_apps    Social apps configuration.
     */
    private function render_platform_connection_card( $platform_key, $platform_name, $social_apps ) {
        $is_configured = isset( $social_apps[ $platform_key ] ) && ! empty( $social_apps[ $platform_key ] );
        
        echo '<div class="tts-platform-card" data-platform="' . esc_attr( $platform_key ) . '">';
        echo '<h3>' . esc_html( $platform_name ) . '</h3>';
        
        // Test results container
        echo '<div class="tts-test-results" id="platform-results-' . esc_attr( $platform_key ) . '"></div>';
        
        // Configuration status
        echo '<div class="tts-config-status">';
        if ( $is_configured ) {
            echo '<span class="tts-status configured">✅ ' . esc_html__( 'API Configured', 'fp-publisher' ) . '</span>';
        } else {
            echo '<span class="tts-status not-configured">❌ ' . esc_html__( 'Not Configured', 'fp-publisher' ) . '</span>';
        }
        echo '</div>';
        
        // Action buttons
        echo '<div class="tts-platform-actions">';
        if ( $is_configured ) {
            echo '<button class="button button-primary tts-test-platform-btn" data-platform="' . esc_attr( $platform_key ) . '">';
            echo esc_html__( 'Test Connection', 'fp-publisher' );
            echo '</button>';
        }
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-social-connections' ) ) . '" class="button">' . esc_html__( 'Configure', 'fp-publisher' ) . '</a>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Add connection test scripts.
     */
    private function add_connection_test_scripts() {
        ?>
        <style>
        .tts-connection-section {
            background: #fff;
            border: 1px solid #c3c4c7;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .tts-clients-grid, .tts-platforms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .tts-client-card, .tts-platform-card {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            background: #fafafa;
        }
        .tts-client-card h3, .tts-platform-card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .tts-connection-overview {
            margin: 15px 0;
        }
        .tts-connection-item {
            display: inline-block;
            margin: 2px 5px 2px 0;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .tts-connection-item.configured {
            background: #d1eddd;
            color: #00a32a;
        }
        .tts-connection-item.not-configured {
            background: #f7dde0;
            color: #d63638;
        }
        .tts-client-actions, .tts-platform-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        .tts-test-results {
            margin: 10px 0;
            min-height: 30px;
        }
        .tts-test-result {
            padding: 8px 12px;
            border-radius: 4px;
            margin: 5px 0;
            font-size: 14px;
        }
        .tts-test-result.success {
            background: #d1eddd;
            color: #00a32a;
            border: 1px solid #46b450;
        }
        .tts-test-result.error {
            background: #f7dde0;
            color: #d63638;
            border: 1px solid #d63638;
        }
        .tts-test-result.info {
            background: #e5f5fa;
            color: #0073aa;
            border: 1px solid #00a0d2;
        }
        .tts-no-clients {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .tts-status.configured {
            color: #00a32a;
        }
        .tts-status.not-configured {
            color: #d63638;
        }
        .tts-config-status {
            margin: 10px 0;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Test client connections
            $('.tts-test-client-btn').on('click', function() {
                var $btn = $(this);
                var clientId = $btn.data('client-id');
                var $results = $('#test-results-' + clientId);
                
                $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Testing...', 'fp-publisher' ) ); ?>');
                $results.html('<div class="tts-test-result info">🔄 ' + '<?php echo esc_js( __( 'Testing client connections...', 'fp-publisher' ) ); ?>' + '</div>');
                
                $.post(ajaxurl, {
                    action: 'tts_test_client_connections',
                    client_id: clientId,
                    nonce: '<?php echo wp_create_nonce( 'tts_ajax_nonce' ); ?>'
                }, function(response) {
                    if (response.success) {
                        var html = '';
                        $.each(response.data.results, function(platform, result) {
                            var className = result.success ? 'success' : 'error';
                            var icon = result.success ? '✅' : '❌';
                            html += '<div class="tts-test-result ' + className + '">' + icon + ' ' + platform + ': ' + result.message + '</div>';
                        });
                        $results.html(html);
                    } else {
                        $results.html('<div class="tts-test-result error">❌ ' + response.data + '</div>');
                    }
                }).fail(function() {
                    $results.html('<div class="tts-test-result error">❌ ' + '<?php echo esc_js( __( 'Connection test failed', 'fp-publisher' ) ); ?>' + '</div>');
                }).always(function() {
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Test All Connections', 'fp-publisher' ) ); ?>');
                });
            });
            
            // Test platform connections
            $('.tts-test-platform-btn').on('click', function() {
                var $btn = $(this);
                var platform = $btn.data('platform');
                var $results = $('#platform-results-' + platform);
                
                $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Testing...', 'fp-publisher' ) ); ?>');
                $results.html('<div class="tts-test-result info">🔄 ' + '<?php echo esc_js( __( 'Testing platform connection...', 'fp-publisher' ) ); ?>' + '</div>');
                
                $.post(ajaxurl, {
                    action: 'tts_test_single_connection',
                    platform: platform,
                    nonce: '<?php echo wp_create_nonce( 'tts_ajax_nonce' ); ?>'
                }, function(response) {
                    if (response.success) {
                        var className = response.data.success ? 'success' : 'error';
                        var icon = response.data.success ? '✅' : '❌';
                        $results.html('<div class="tts-test-result ' + className + '">' + icon + ' ' + response.data.message + '</div>');
                    } else {
                        $results.html('<div class="tts-test-result error">❌ ' + response.data + '</div>');
                    }
                }).fail(function() {
                    $results.html('<div class="tts-test-result error">❌ ' + '<?php echo esc_js( __( 'Connection test failed', 'fp-publisher' ) ); ?>' + '</div>');
                }).always(function() {
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Test Connection', 'fp-publisher' ) ); ?>');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for testing client connections.
     */
    public function ajax_test_client_connections() {
        check_ajax_referer( 'tts_ajax_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'fp-publisher' ) );
        }
        
        $client_id = absint( $_POST['client_id'] );
        if ( ! $client_id ) {
            wp_send_json_error( __( 'Invalid client ID.', 'fp-publisher' ) );
        }
        
        $results = array();
        
        // Test Trello connection
        $trello_key = get_post_meta( $client_id, '_tts_trello_key', true );
        $trello_token = get_post_meta( $client_id, '_tts_trello_token', true );
        
        if ( $trello_key && $trello_token ) {
            $trello_result = $this->test_trello_connection( $trello_key, $trello_token );
            $results['Trello'] = $trello_result;
        } else {
            $results['Trello'] = array(
                'success' => false,
                'message' => __( 'No Trello credentials configured', 'fp-publisher' )
            );
        }
        
        // Test Facebook connection
        $facebook_token = get_post_meta( $client_id, '_tts_fb_token', true );
        if ( $facebook_token ) {
            $facebook_result = $this->test_facebook_client_connection( $facebook_token );
            $results['Facebook'] = $facebook_result;
        } else {
            $results['Facebook'] = array(
                'success' => false,
                'message' => __( 'No Facebook token configured', 'fp-publisher' )
            );
        }
        
        // Test Instagram connection
        $instagram_token = get_post_meta( $client_id, '_tts_ig_token', true );
        if ( $instagram_token ) {
            $instagram_result = $this->test_instagram_client_connection( $instagram_token );
            $results['Instagram'] = $instagram_result;
        } else {
            $results['Instagram'] = array(
                'success' => false,
                'message' => __( 'No Instagram token configured', 'fp-publisher' )
            );
        }
        
        // Test YouTube connection
        $youtube_token = get_post_meta( $client_id, '_tts_yt_token', true );
        if ( $youtube_token ) {
            $youtube_result = $this->test_youtube_client_connection( $youtube_token );
            $results['YouTube'] = $youtube_result;
        } else {
            $results['YouTube'] = array(
                'success' => false,
                'message' => __( 'No YouTube token configured', 'fp-publisher' )
            );
        }
        
        // Test TikTok connection
        $tiktok_token = get_post_meta( $client_id, '_tts_tt_token', true );
        if ( $tiktok_token ) {
            $tiktok_result = $this->test_tiktok_client_connection( $tiktok_token );
            $results['TikTok'] = $tiktok_result;
        } else {
            $results['TikTok'] = array(
                'success' => false,
                'message' => __( 'No TikTok token configured', 'fp-publisher' )
            );
        }
        
        wp_send_json_success( array( 'results' => $results ) );
    }

    /**
     * AJAX handler for testing single platform connection.
     */
    public function ajax_test_single_connection() {
        check_ajax_referer( 'tts_ajax_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'fp-publisher' ) );
        }
        
        $platform = sanitize_key( $_POST['platform'] );
        if ( ! $platform ) {
            wp_send_json_error( __( 'Invalid platform.', 'fp-publisher' ) );
        }
        
        $social_apps = get_option( 'tts_social_apps', array() );
        $platform_settings = isset( $social_apps[ $platform ] ) ? $social_apps[ $platform ] : array();
        
        $result = $this->test_platform_connection( $platform, $platform_settings );
        
        wp_send_json_success( $result );
    }

    /**
     * Test Trello connection.
     *
     * @param string $api_key   Trello API key.
     * @param string $token     Trello token.
     * @return array Test result.
     */
    private function test_trello_connection( $api_key, $token ) {
        $response = wp_remote_get(
            'https://api.trello.com/1/members/me?key=' . rawurlencode( $api_key ) . '&token=' . rawurlencode( $token ),
            array( 'timeout' => 15 )
        );
        
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => __( 'Connection failed: ', 'fp-publisher' ) . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code === 200 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['username'] ) ) {
                return array(
                    'success' => true,
                    'message' => sprintf( __( 'Connected as %s', 'fp-publisher' ), $body['username'] )
                );
            }
        }
        
        return array(
            'success' => false,
            'message' => sprintf( __( 'HTTP %d - Invalid credentials', 'fp-publisher' ), $response_code )
        );
    }

    /**
     * Test Facebook client connection.
     *
     * @param string $token Facebook token (format: page_id|access_token).
     * @return array Test result.
     */
    private function test_facebook_client_connection( $token ) {
        $parts = explode( '|', $token );
        if ( count( $parts ) !== 2 ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid token format. Expected: page_id|access_token', 'fp-publisher' )
            );
        }
        
        list( $page_id, $access_token ) = $parts;
        
        $response = wp_remote_get(
            'https://graph.facebook.com/v18.0/' . rawurlencode( $page_id ) . '?fields=name,access_token&access_token=' . rawurlencode( $access_token ),
            array( 'timeout' => 15 )
        );
        
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => __( 'Connection failed: ', 'fp-publisher' ) . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code === 200 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['name'] ) ) {
                return array(
                    'success' => true,
                    'message' => sprintf( __( 'Connected to page: %s', 'fp-publisher' ), $body['name'] )
                );
            }
        }
        
        return array(
            'success' => false,
            'message' => sprintf( __( 'HTTP %d - Unable to access page', 'fp-publisher' ), $response_code )
        );
    }

    /**
     * Test Instagram client connection.
     *
     * @param string $token Instagram token (format: ig_user_id|access_token).
     * @return array Test result.
     */
    private function test_instagram_client_connection( $token ) {
        $parts = explode( '|', $token );
        if ( count( $parts ) !== 2 ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid token format. Expected: ig_user_id|access_token', 'fp-publisher' )
            );
        }
        
        list( $ig_user_id, $access_token ) = $parts;
        
        $response = wp_remote_get(
            'https://graph.instagram.com/' . rawurlencode( $ig_user_id ) . '?fields=username&access_token=' . rawurlencode( $access_token ),
            array( 'timeout' => 15 )
        );
        
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => __( 'Connection failed: ', 'fp-publisher' ) . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code === 200 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['username'] ) ) {
                return array(
                    'success' => true,
                    'message' => sprintf( __( 'Connected as @%s', 'fp-publisher' ), $body['username'] )
                );
            }
        }
        
        return array(
            'success' => false,
            'message' => sprintf( __( 'HTTP %d - Unable to access Instagram account', 'fp-publisher' ), $response_code )
        );
    }

    /**
     * Test YouTube client connection.
     *
     * @param string $token YouTube access token.
     * @return array Test result.
     */
    private function test_youtube_client_connection( $token ) {
        $response = wp_remote_get(
            'https://www.googleapis.com/youtube/v3/channels?part=snippet&mine=true&access_token=' . rawurlencode( $token ),
            array( 'timeout' => 15 )
        );
        
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => __( 'Connection failed: ', 'fp-publisher' ) . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code === 200 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['items'] ) && ! empty( $body['items'] ) ) {
                $channel_title = $body['items'][0]['snippet']['title'];
                return array(
                    'success' => true,
                    'message' => sprintf( __( 'Connected to channel: %s', 'fp-publisher' ), $channel_title )
                );
            }
        }
        
        return array(
            'success' => false,
            'message' => sprintf( __( 'HTTP %d - Unable to access YouTube channel', 'fp-publisher' ), $response_code )
        );
    }

    /**
     * Test TikTok client connection.
     *
     * @param string $token TikTok access token.
     * @return array Test result.
     */
    private function test_tiktok_client_connection( $token ) {
        // Note: TikTok API testing is more complex and requires specific endpoints
        // For now, we'll do a basic token validation
        
        if ( empty( $token ) || strlen( $token ) < 10 ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid or empty token', 'fp-publisher' )
            );
        }
        
        // Basic validation passed
        return array(
            'success' => true,
            'message' => __( 'Token format appears valid (full API test requires video upload)', 'fp-publisher' )
        );
    }

    /**
     * Delegate to frequency status page render method.
     */
    public function render_frequency_status_page() {
        global $tts_frequency_status_page;
        if ( isset( $tts_frequency_status_page ) && $tts_frequency_status_page instanceof TTS_Frequency_Status_Page ) {
            $tts_frequency_status_page->render_page();
        }
    }
}
