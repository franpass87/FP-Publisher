<?php
/**
 * Admin functionality for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'MB_IN_BYTES' ) ) {
    define( 'MB_IN_BYTES', 1024 * 1024 );
}

/**
 * Handles admin pages and filters.
 */
class TTS_Admin {

    const DEFAULT_IMPORT_MAX_FILE_SIZE = 5 * MB_IN_BYTES;

    /**
     * Supported social channels handled by the custom editor experience.
     *
     * @var array<int, string>
     */
    private static $editor_social_channels = array( 'facebook', 'instagram', 'youtube', 'tiktok' );

    /**
     * Stores admin notices for missing menu callbacks.
     *
     * @var array<string, array{menu_title: string, method: string}>
     */
    private $missing_method_notices = array();

    /**
     * Shared AJAX security helper.
     *
     * @var TTS_Admin_Ajax_Security
     */
    private $ajax_security;

    /**
     * View helper used to render templates when required.
     *
     * @var TTS_Admin_View_Helper
     */
    private $view_helper;

    /**
     * Menu registry responsible for canonical slugs and aliases.
     *
     * @var TTS_Admin_Menu_Registry|null
     */
    private $menu_registry;

    /**
     * Default AJAX security ruleset.
     *
     * @return array<string, array{nonce_action: string, capabilities: array<int, string>, nonce_field?: string}>
     */
    public static function get_ajax_action_security_defaults() {
        return array(
        'ajax_get_lists' => array(
            'nonce_action' => 'tts_wizard',
            'capabilities' => array( 'tts_manage_clients' ),
        ),
        'ajax_refresh_posts' => array(
            'nonce_action' => 'tts_dashboard',
            'capabilities' => array( 'tts_read_social_posts' ),
        ),
        'ajax_delete_post' => array(
            'nonce_action' => 'tts_dashboard',
            'capabilities' => array( 'tts_delete_social_posts' ),
        ),
        'ajax_bulk_action' => array(
            'nonce_action' => 'tts_dashboard',
            'capabilities' => array( 'tts_edit_social_posts' ),
        ),
        'ajax_test_connection' => array(
            'nonce_action' => 'tts_test_connection',
            'capabilities' => array( 'tts_manage_integrations' ),
        ),
        'ajax_check_rate_limits' => array(
            'nonce_action' => 'tts_check_rate_limits',
            'capabilities' => array( 'tts_manage_integrations' ),
        ),
        'ajax_save_social_settings' => array(
            'nonce_action' => 'tts_ajax_nonce',
            'capabilities' => array( 'tts_manage_integrations' ),
        ),
        'ajax_export_data' => array(
            'nonce_action' => 'tts_ajax_nonce',
            'capabilities' => array( 'tts_export_data' ),
        ),
        'ajax_import_data' => array(
            'nonce_action' => 'tts_ajax_nonce',
            'capabilities' => array( 'tts_import_data' ),
        ),
        'ajax_system_maintenance' => array(
            'nonce_action' => 'tts_ajax_nonce',
            'capabilities' => array( 'tts_manage_system' ),
        ),
        'ajax_generate_report' => array(
            'nonce_action' => 'tts_ajax_nonce',
            'capabilities' => array( 'tts_view_reports' ),
        ),
        'ajax_quick_connection_check' => array(
            'nonce_action' => 'tts_ajax_nonce',
            'capabilities' => array( 'tts_manage_integrations' ),
        ),
        'ajax_refresh_health' => array(
            'nonce_action' => 'tts_ajax_nonce',
            'capabilities' => array( 'tts_manage_health' ),
        ),
        'ajax_show_export_modal' => array(
            'nonce_action' => 'tts_ajax_nonce',
            'capabilities' => array( 'tts_export_data' ),
        ),
        'ajax_show_import_modal' => array(
            'nonce_action' => 'tts_ajax_nonce',
            'capabilities' => array( 'tts_import_data' ),
        ),
        'ajax_test_client_connections' => array(
            'nonce_action' => 'tts_ajax_nonce',
            'capabilities' => array( 'tts_manage_clients' ),
        ),
        'ajax_test_single_connection' => array(
            'nonce_action' => 'tts_ajax_nonce',
            'capabilities' => array( 'tts_manage_integrations' ),
        ),
        'ajax_validate_trello_credentials' => array(
            'nonce_action' => 'tts_wizard',
            'capabilities' => array( 'tts_manage_clients' ),
        ),
        'ajax_test_wizard_token' => array(
            'nonce_action' => 'tts_wizard',
            'capabilities' => array( 'tts_manage_clients' ),
        ),
        );
    }

    /**
     * Build the admin URL that opens the custom social post editor.
     *
     * @param int                $post_id Optional post identifier.
     * @param array<string,mixed> $args    Extra query parameters to merge.
     *
     * @return string
     */
    public static function get_social_post_editor_url( $post_id = 0, array $args = array() ) {
        $defaults = array(
            'page' => 'fp-publisher-queue',
        );

        if ( $post_id > 0 ) {
            $defaults['action'] = 'edit';
            $defaults['post']   = absint( $post_id );
        }

        $args = array_merge( $defaults, $args );

        if ( isset( $args['post'] ) ) {
            $args['post'] = absint( $args['post'] );
        }

        if ( isset( $args['tts_open_editor'] ) ) {
            $args['tts_open_editor'] = (int) (bool) $args['tts_open_editor'];
        }

        if ( isset( $args['action'] ) ) {
            $args['action'] = sanitize_key( $args['action'] );
        }

        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    /**
     * Validate nonce and capability requirements for AJAX handlers.
     *
     * @param string               $context   AJAX handler context.
     * @param array<string, mixed> $overrides Optional overrides for nonce/capability evaluation.
     *
     * @return bool
     */
    private function enforce_ajax_security( $context, array $overrides = array() ) {
        if ( ! $this->ajax_security instanceof TTS_Admin_Ajax_Security ) {
            return true;
        }

        return $this->ajax_security->check( $context, $overrides );
    }

    /**
     * Hook into WordPress actions.
     */
    public function __construct( ?TTS_Admin_Ajax_Security $ajax_security = null, ?TTS_Admin_View_Helper $view_helper = null ) {
        if ( null === $ajax_security ) {
            $ajax_security = new TTS_Admin_Ajax_Security( self::get_ajax_action_security_defaults() );
        }

        $this->ajax_security  = $ajax_security;
        $this->view_helper    = $view_helper ?: new TTS_Admin_View_Helper();
        $this->menu_registry = null;

        add_action( 'admin_init', array( $this, 'maybe_redirect_legacy_menu_slugs' ), 1 );
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
        $blueprint = array(
            'dashboard' => array(
                'label' => __( 'Dashboard', 'fp-publisher' ),
                'hub'   => array(
                    'title'      => __( 'Dashboard', 'fp-publisher' ),
                    'slug'       => 'fp-publisher-dashboard',
                    'aliases'    => array( 'fp-publisher-main' ),
                    'callback'   => 'render_dashboard_page',
                    'capability' => 'manage_options',
                    'page_title' => __( 'FP Publisher Dashboard', 'fp-publisher' ),
                    'primary'    => true,
                ),
                'items' => array(),
            ),
            'configuration' => array(
                'label' => __( 'Onboarding & Setup', 'fp-publisher' ),
                'hub'   => array(
                    'title'       => __( 'Onboarding Hub', 'fp-publisher' ),
                    'description' => __( 'Coordina la configurazione clienti, collega i canali social e completa i prerequisiti prima di pubblicare.', 'fp-publisher' ),
                    'class'       => 'tts-hub--configuration',
                    'slug'        => 'fp-publisher-onboarding',
                    'aliases'     => array( 'fp-publisher-configuration-hub' ),
                    'callback'    => 'render_configuration_hub_page',
                    'capability'  => 'tts_manage_clients',
                    'page_title'  => __( 'Onboarding Hub', 'fp-publisher' ),
                    'primary'     => true,
                    'footer'      => array(
                        'title'       => __( 'Hai bisogno di ulteriore supporto?', 'fp-publisher' ),
                        'description' => __( 'Consulta la knowledge base o contatta il team per ottenere assistenza sull’onboarding.', 'fp-publisher' ),
                        'links'       => array(
                            array(
                                'label' => __( 'Apri documentazione', 'fp-publisher' ),
                                'url'   => admin_url( 'admin.php?page=fp-publisher-support' ),
                            ),
                            array(
                                'label' => __( 'Contatta il supporto', 'fp-publisher' ),
                                'url'   => 'mailto:info@francescopasseri.com',
                            ),
                        ),
                    ),
                    'quick_action' => array(
                        'title'       => __( 'Apri Onboarding', 'fp-publisher' ),
                        'description' => __( 'Gestisci clienti, connessioni e impostazioni iniziali', 'fp-publisher' ),
                        'icon'        => 'dashicons-admin-generic',
                        'color'       => '#135e96',
                        'profiles'    => array( 'standard', 'advanced', 'enterprise' ),
                    ),
                ),
                'items' => array(
                    array(
                        'title'      => __( 'Clients', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-clients',
                        'aliases'    => array( 'fp-publisher-clienti' ),
                        'callback'   => 'render_clients_page',
                        'capability' => 'tts_manage_clients',
                        'card'       => array(
                            'description' => __( 'Rivedi tutti i clienti attivi, lo stato di collegamento e accedi ai loro contenuti.', 'fp-publisher' ),
                            'icon'        => 'dashicons-groups',
                            'meta'        => array(
                                __( 'Shortcut a contenuti e modifiche rapide', 'fp-publisher' ),
                            ),
                        ),
                    ),
                    array(
                        'title'      => __( 'Client Wizard', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-client-wizard',
                        'callback'   => 'tts_render_client_wizard',
                        'capability' => 'tts_manage_clients',
                        'card'       => array(
                            'description' => __( 'Configura un nuovo cliente con checklist guidata e mapping Trello.', 'fp-publisher' ),
                            'icon'        => 'dashicons-admin-users',
                            'meta'        => array(
                                __( 'Verifica token e liste Trello durante il setup', 'fp-publisher' ),
                            ),
                        ),
                        'quick_action' => array(
                            'title'       => __( 'Client Wizard', 'fp-publisher' ),
                            'description' => __( 'Imposta un nuovo cliente social', 'fp-publisher' ),
                            'icon'        => 'dashicons-plus',
                            'color'       => '#6366f1',
                        ),
                    ),
                    array(
                        'title'      => __( 'Templates & Automations', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-templates',
                        'aliases'    => array( 'fp-publisher-quickstart' ),
                        'callback'   => 'render_quickstart_packages_page',
                        'capability' => 'tts_manage_clients',
                        'card'       => array(
                            'description' => __( 'Importa preset con automazioni, template social e mapping già pronti.', 'fp-publisher' ),
                            'icon'        => 'dashicons-category',
                        ),
                        'quick_action' => array(
                            'title'       => __( 'Templates & Automations', 'fp-publisher' ),
                            'description' => __( 'Importa preset e automazioni', 'fp-publisher' ),
                            'icon'        => 'dashicons-portfolio',
                            'color'       => '#0f172a',
                        ),
                    ),
                    array(
                        'title'      => __( 'Channel Connections', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-connections',
                        'aliases'    => array( 'fp-publisher-social-connections' ),
                        'callback'   => 'render_social_connections_page',
                        'capability' => 'tts_manage_integrations',
                        'card'       => array(
                            'description' => __( 'Gestisci account collegati, rinnova token e abilita nuovi canali.', 'fp-publisher' ),
                            'icon'        => 'dashicons-admin-links',
                            'meta'        => array(
                                __( 'Supporto per Facebook, Instagram, YouTube e TikTok', 'fp-publisher' ),
                            ),
                        ),
                    ),
                    array(
                        'title'      => __( 'Connection Diagnostics', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-connection-diagnostics',
                        'aliases'    => array( 'fp-publisher-test-connections' ),
                        'callback'   => 'render_connection_test_page',
                        'capability' => 'tts_manage_integrations',
                        'card'       => array(
                            'description' => __( 'Esegui verifiche rapide su webhook, permessi e limiti API.', 'fp-publisher' ),
                            'icon'        => 'dashicons-controls-repeat',
                        ),
                    ),
                    array(
                        'title'      => __( 'Global Settings', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-settings',
                        'callback'   => 'render_settings_page',
                        'capability' => 'manage_options',
                        'card'       => array(
                            'description' => __( 'Definisci preferenze globali di branding, pianificazione e profilo d’uso.', 'fp-publisher' ),
                            'icon'        => 'dashicons-admin-settings',
                        ),
                    ),
                    array(
                        'title'      => __( 'Support Center', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-support',
                        'aliases'    => array( 'fp-publisher-help' ),
                        'callback'   => 'render_help_page',
                        'capability' => 'manage_options',
                        'card'       => array(
                            'description' => __( 'Accedi a guide passo-passo, checklist e materiali di formazione.', 'fp-publisher' ),
                            'icon'        => 'dashicons-sos',
                        ),
                    ),
                ),
            ),
            'production' => array(
                'label' => __( 'Publishing Operations', 'fp-publisher' ),
                'hub'   => array(
                    'title'       => __( 'Publishing Hub', 'fp-publisher' ),
                    'description' => __( 'Coordina il lavoro quotidiano del team editoriale e controlla lo stato di avanzamento dei contenuti.', 'fp-publisher' ),
                    'class'       => 'tts-hub--production',
                    'slug'        => 'fp-publisher-publishing',
                    'aliases'     => array( 'fp-publisher-production-hub' ),
                    'callback'    => 'render_production_hub_page',
                    'capability'  => 'tts_read_social_posts',
                    'page_title'  => __( 'Publishing Hub', 'fp-publisher' ),
                    'primary'     => true,
                    'footer'      => array(
                        'title'       => __( 'Risorse per il team editoriale', 'fp-publisher' ),
                        'description' => __( 'Allinea il team con guide operative e scorciatoie per la collaborazione quotidiana.', 'fp-publisher' ),
                        'links'       => array(
                            array(
                                'label' => __( 'Apri guida al calendario', 'fp-publisher' ),
                                'url'   => admin_url( 'admin.php?page=fp-publisher-support#calendar' ),
                            ),
                            array(
                                'label' => __( 'Template briefing', 'fp-publisher' ),
                                'url'   => admin_url( 'admin.php?page=fp-publisher-templates' ),
                            ),
                        ),
                    ),
                    'quick_action' => array(
                        'title'       => __( 'Vai alla pubblicazione', 'fp-publisher' ),
                        'description' => __( 'Accedi a coda, calendario e strumenti operativi', 'fp-publisher' ),
                        'icon'        => 'dashicons-megaphone',
                        'color'       => '#2563eb',
                        'profiles'    => array( 'standard', 'advanced', 'enterprise' ),
                    ),
                ),
                'items' => array(
                    array(
                        'title'      => __( 'Publishing Queue', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-queue',
                        'aliases'    => array( 'fp-publisher-social-posts' ),
                        'callback'   => 'render_social_posts_page',
                        'capability' => 'tts_read_social_posts',
                        'card'       => array(
                            'description' => __( 'Gestisci la pipeline dei contenuti, approva bozze e monitora gli stati.', 'fp-publisher' ),
                            'icon'        => 'dashicons-schedule',
                        ),
                        'quick_action' => array(
                            'title'       => __( 'Apri Publishing Queue', 'fp-publisher' ),
                            'description' => __( 'Rivedi post in uscita e approvazioni', 'fp-publisher' ),
                            'icon'        => 'dashicons-yes',
                            'color'       => '#0f172a',
                        ),
                    ),
                    array(
                        'title'      => __( 'Calendar', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-calendar',
                        'callback'   => 'render_calendar_page',
                        'capability' => 'tts_read_social_posts',
                        'card'       => array(
                            'description' => __( 'Visualizza la pianificazione settimanale e mensile delle pubblicazioni.', 'fp-publisher' ),
                            'icon'        => 'dashicons-calendar-alt',
                        ),
                    ),
                    array(
                        'title'      => __( 'Content Library', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-content-library',
                        'aliases'    => array( 'fp-publisher-content' ),
                        'callback'   => 'render_content_management_page',
                        'capability' => 'tts_edit_social_posts',
                        'card'       => array(
                            'description' => __( 'Organizza asset, bozze e varianti approvate per canale.', 'fp-publisher' ),
                            'icon'        => 'dashicons-archive',
                        ),
                    ),
                    array(
                        'title'      => __( 'Publishing Health', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-publishing-health',
                        'aliases'    => array( 'fp-publisher-frequency-status' ),
                        'callback'   => 'render_frequency_status_page',
                        'capability' => 'tts_read_social_posts',
                        'card'       => array(
                            'description' => __( 'Controlla ritmo di pubblicazione, SLA e campagne critiche.', 'fp-publisher' ),
                            'icon'        => 'dashicons-chart-line',
                        ),
                    ),
                    array(
                        'title'      => __( 'AI Assistants', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-ai',
                        'aliases'    => array( 'fp-publisher-ai-features' ),
                        'callback'   => 'render_ai_features_page',
                        'capability' => 'tts_edit_social_posts',
                        'card'       => array(
                            'description' => __( 'Sfrutta automazioni AI per copy, asset e risposte rapide.', 'fp-publisher' ),
                            'icon'        => 'dashicons-robot',
                        ),
                    ),
                ),
            ),
            'monitoring' => array(
                'label' => __( 'Monitoring & Health', 'fp-publisher' ),
                'hub'   => array(
                    'title'       => __( 'Monitoring Hub', 'fp-publisher' ),
                    'description' => __( 'Analizza performance, stato sistema e audit trail per mantenere il servizio affidabile.', 'fp-publisher' ),
                    'class'       => 'tts-hub--monitoring',
                    'slug'        => 'fp-publisher-monitoring',
                    'aliases'     => array( 'fp-publisher-monitoring-hub' ),
                    'callback'    => 'render_monitoring_hub_page',
                    'capability'  => 'tts_view_reports',
                    'page_title'  => __( 'Monitoring Hub', 'fp-publisher' ),
                    'primary'     => true,
                    'footer'      => array(
                        'title'       => __( 'Approfondisci i report', 'fp-publisher' ),
                        'description' => __( 'Scarica template, linee guida e checklist per i controlli periodici.', 'fp-publisher' ),
                        'links'       => array(
                            array(
                                'label' => __( 'Report mensili', 'fp-publisher' ),
                                'url'   => admin_url( 'admin.php?page=fp-publisher-analytics' ),
                            ),
                            array(
                                'label' => __( 'Linee guida manutenzione', 'fp-publisher' ),
                                'url'   => admin_url( 'admin.php?page=fp-publisher-system-health' ),
                            ),
                        ),
                    ),
                    'quick_action' => array(
                        'title'       => __( 'Apri Monitoring Hub', 'fp-publisher' ),
                        'description' => __( 'Controlla metriche, log e integrità di sistema', 'fp-publisher' ),
                        'icon'        => 'dashicons-visibility',
                        'color'       => '#0f766e',
                        'profiles'    => array( 'advanced', 'enterprise' ),
                    ),
                ),
                'items' => array(
                    array(
                        'title'      => __( 'Analytics & Reports', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-analytics',
                        'callback'   => 'render_analytics_page',
                        'capability' => 'tts_view_reports',
                        'card'       => array(
                            'description' => __( 'Visualizza metriche aggregate e report personalizzati.', 'fp-publisher' ),
                            'icon'        => 'dashicons-chart-area',
                        ),
                    ),
                    array(
                        'title'      => __( 'System Health', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-system-health',
                        'aliases'    => array( 'fp-publisher-health' ),
                        'callback'   => 'render_health_page',
                        'capability' => 'tts_manage_health',
                        'card'       => array(
                            'description' => __( 'Monitora code, storage e stato dei servizi esterni.', 'fp-publisher' ),
                            'icon'        => 'dashicons-shield-alt',
                        ),
                    ),
                    array(
                        'title'      => __( 'Activity Log', 'fp-publisher' ),
                        'slug'       => 'fp-publisher-activity-log',
                        'aliases'    => array( 'fp-publisher-log' ),
                        'callback'   => 'render_log_page',
                        'capability' => 'tts_manage_system',
                        'card'       => array(
                            'description' => __( 'Esamina audit trail, eventi recenti e azioni pianificate.', 'fp-publisher' ),
                            'icon'        => 'dashicons-list-view',
                        ),
                    ),
                ),
            ),
        );

        /**
         * Allow third parties to adjust the navigation blueprint before it is consumed.
         *
         * @param array<string, array<string, mixed>> $blueprint Navigation blueprint.
         */
        $blueprint = apply_filters( 'tts_admin_navigation_blueprint', $blueprint );

        return $blueprint;
    }

    /**
     * Return the dashboard page slug defined in the navigation blueprint.
     *
     * @return string
     */
    private function get_dashboard_page_slug() {
        $blueprint = $this->get_navigation_blueprint();

        if ( isset( $blueprint['dashboard']['hub']['slug'] ) ) {
            return (string) $blueprint['dashboard']['hub']['slug'];
        }

        return 'fp-publisher-dashboard';
    }

    /**
     * Build the WordPress admin hook name for a given slug.
     *
     * @param string $slug        Admin page slug.
     * @param bool   $is_top_level Whether the hook should represent the top-level menu.
     *
     * @return string
     */
    private function build_admin_hook_from_slug( $slug, $is_top_level = false ) {
        $slug = (string) $slug;

        if ( $is_top_level ) {
            return 'toplevel_page_' . $slug;
        }

        return 'fp-publisher_page_' . $slug;
    }

    /**
     * Collect all admin page hooks registered through the navigation blueprint.
     *
     * @return array<int, string>
     */
    private function get_registered_admin_page_hooks() {
        static $hooks = null;

        if ( null !== $hooks ) {
            return $hooks;
        }

        $hooks          = array();
        $dashboard_slug = $this->get_dashboard_page_slug();

        $hooks[] = $this->build_admin_hook_from_slug( $dashboard_slug, true );
        $hooks[] = $this->build_admin_hook_from_slug( $dashboard_slug );

        $blueprint = $this->get_navigation_blueprint();

        foreach ( $blueprint as $section ) {
            $definitions = array();

            if ( isset( $section['hub'] ) && is_array( $section['hub'] ) ) {
                $definitions[] = $section['hub'];
            }

            if ( ! empty( $section['items'] ) && is_array( $section['items'] ) ) {
                foreach ( $section['items'] as $item_definition ) {
                    if ( is_array( $item_definition ) ) {
                        $definitions[] = $item_definition;
                    }
                }
            }

            foreach ( $definitions as $definition ) {
                if ( empty( $definition['slug'] ) ) {
                    continue;
                }

                $hooks[] = $this->build_admin_hook_from_slug( $definition['slug'] );
            }
        }

        $hooks = array_values( array_unique( $hooks ) );

        return $hooks;
    }

    /**
     * Register plugin menu pages.
     */
    public function register_menu() {
        $blueprint = $this->get_navigation_blueprint();

        $required_methods = array();

        foreach ( $blueprint as $section ) {
            if ( isset( $section['hub']['callback'] ) ) {
                $required_methods[] = $section['hub']['callback'];
            }

            if ( empty( $section['items'] ) || ! is_array( $section['items'] ) ) {
                continue;
            }

            foreach ( $section['items'] as $item ) {
                if ( isset( $item['callback'] ) ) {
                    $required_methods[] = $item['callback'];
                }
            }
        }

        $required_methods  = array_unique( array_filter( $required_methods ) );
        $available_methods = array();

        foreach ( $required_methods as $method ) {
            $method_exists = method_exists( $this, $method );
            $is_callable   = $method_exists && is_callable( array( $this, $method ) );

            $available_methods[ $method ] = array(
                'exists'   => $method_exists,
                'callable' => $is_callable,
            );

            if ( ! $method_exists ) {
                error_log( "TTS_Admin: Missing method $method" );
            } elseif ( ! $is_callable ) {
                error_log( "TTS_Admin: Method $method exists but is not callable" );
            }
        }

        $dashboard_hub      = isset( $blueprint['dashboard']['hub'] ) ? $blueprint['dashboard']['hub'] : array();
        $dashboard_slug     = $this->get_dashboard_page_slug();
        $dashboard_callback = isset( $dashboard_hub['callback'] ) ? $dashboard_hub['callback'] : 'render_dashboard_page';
        $dashboard_cap      = isset( $dashboard_hub['capability'] ) ? $dashboard_hub['capability'] : 'manage_options';
        $dashboard_page     = isset( $dashboard_hub['page_title'] ) ? $dashboard_hub['page_title'] : __( 'FP Publisher Dashboard', 'fp-publisher' );

        $fp_publisher_title = __( 'FP Publisher', 'fp-publisher' );
        if ( ! $this->can_register_menu_callback( $dashboard_callback, $dashboard_slug, $fp_publisher_title, $available_methods ) ) {
            return;
        }

        $menu_items = array();

        foreach ( $this->get_admin_menu_items() as $item ) {
            if ( ! $this->can_register_menu_callback( $item['callback'], $item['slug'], $item['menu_title'], $available_methods ) ) {
                continue;
            }

            $menu_items[] = $item;
        }

        $this->get_menu_registry()->register_menus(
            array(
                'slug'       => $dashboard_slug,
                'callback'   => $dashboard_callback,
                'capability' => $dashboard_cap,
                'page_title' => $dashboard_page,
                'menu_title' => $fp_publisher_title,
                'icon'       => 'dashicons-share-alt',
                'position'   => 25,
            ),
            $menu_items
        );
    }

    /**
     * Redirect legacy menu slugs to their canonical counterparts.
     */
    public function maybe_redirect_legacy_menu_slugs() {
        if ( ! is_admin() || wp_doing_ajax() ) {
            return;
        }

        if ( 'GET' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET' ) ) {
            return;
        }

        if ( empty( $_GET['page'] ) ) {
            return;
        }

        $requested_slug = sanitize_key( wp_unslash( (string) $_GET['page'] ) );

        if ( '' === $requested_slug ) {
            return;
        }

        $canonical_slug = $this->get_menu_registry()->get_canonical_slug( $requested_slug );

        if ( null === $canonical_slug || $canonical_slug === $requested_slug ) {
            return;
        }

        $redirect_args = array( 'page' => $canonical_slug );

        foreach ( $_GET as $key => $value ) {
            if ( 'page' === $key ) {
                continue;
            }

            $sanitized_key = sanitize_key( (string) $key );

            if ( '' === $sanitized_key || isset( $redirect_args[ $sanitized_key ] ) ) {
                continue;
            }

            if ( is_scalar( $value ) ) {
                $redirect_args[ $sanitized_key ] = sanitize_text_field( wp_unslash( (string) $value ) );
            }
        }

        $redirect_url = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );

        wp_safe_redirect( $redirect_url, 302 );
        exit;
    }

    /**
     * Convert a blueprint definition into a menu item structure.
     *
     * @param string               $group_label Section label.
     * @param array<string, mixed> $definition  Item definition.
     * @param bool                 $is_primary  Whether the entry should appear as a standalone label.
     *
     * @return array<string, mixed>|null
     */
    private function build_menu_item_from_definition( $group_label, array $definition, $is_primary = false ) {
        $title    = isset( $definition['title'] ) ? (string) $definition['title'] : '';
        $slug     = isset( $definition['slug'] ) ? (string) $definition['slug'] : '';
        $callback = isset( $definition['callback'] ) ? (string) $definition['callback'] : '';

        if ( '' === $title || '' === $slug || '' === $callback ) {
            return null;
        }

        $capability = isset( $definition['capability'] ) ? (string) $definition['capability'] : 'manage_options';

        $menu_title = isset( $definition['menu_title'] )
            ? (string) $definition['menu_title']
            : ( $is_primary
                ? $title
                : sprintf(
                    /* translators: 1: Section name. 2: Menu label. */
                    __( '%1$s · %2$s', 'fp-publisher' ),
                    $group_label,
                    $title
                )
            );

        $page_title = isset( $definition['page_title'] )
            ? (string) $definition['page_title']
            : sprintf(
                /* translators: 1: Section name. 2: Page title. */
                __( '%1$s — %2$s', 'fp-publisher' ),
                $group_label,
                $title
            );

        $aliases = array();

        if ( ! empty( $definition['aliases'] ) && is_array( $definition['aliases'] ) ) {
            foreach ( $definition['aliases'] as $alias ) {
                $alias = sanitize_key( (string) $alias );

                if ( '' === $alias ) {
                    continue;
                }

                $aliases[] = $alias;
            }
        }

        return array(
            'menu_title' => $menu_title,
            'page_title' => $page_title,
            'slug'       => $slug,
            'callback'   => $callback,
            'capability' => $capability,
            'aliases'    => $aliases,
        );
    }

    /**
     * Return the structured menu items for the admin navigation.
     *
     * @return array<int, array<string, string>>
     */
    private function get_admin_menu_items() {
        $blueprint = $this->get_navigation_blueprint();
        $items     = array();

        foreach ( $blueprint as $section ) {
            $group_label = isset( $section['label'] ) ? (string) $section['label'] : '';

            if ( isset( $section['hub'] ) ) {
                $menu_item = $this->build_menu_item_from_definition( $group_label, $section['hub'], ! empty( $section['hub']['primary'] ) );
                if ( null !== $menu_item ) {
                    $items[] = $menu_item;
                }
            }

            if ( empty( $section['items'] ) || ! is_array( $section['items'] ) ) {
                continue;
            }

            foreach ( $section['items'] as $item_definition ) {
                $menu_item = $this->build_menu_item_from_definition( $group_label, $item_definition, ! empty( $item_definition['primary'] ) );
                if ( null !== $menu_item ) {
                    $items[] = $menu_item;
                }
            }
        }

        return $items;
    }

    /**
     * Map menu slugs to the capability required to access them.
     *
     * @return array<string, string>
     */
    private function get_admin_menu_capability_map() {
        static $cache = null;

        if ( null !== $cache ) {
            return $cache;
        }

        $capabilities = array();

        foreach ( $this->get_admin_menu_items() as $item ) {
            if ( empty( $item['slug'] ) ) {
                continue;
            }

            $capability = isset( $item['capability'] ) ? $item['capability'] : 'manage_options';
            $capabilities[ $item['slug'] ] = $capability;

            if ( ! empty( $item['aliases'] ) && is_array( $item['aliases'] ) ) {
                foreach ( $item['aliases'] as $alias ) {
                    if ( '' === $alias ) {
                        continue;
                    }

                    $capabilities[ $alias ] = $capability;
                }
            }
        }

        foreach ( $this->get_menu_registry()->get_alias_map() as $alias => $canonical ) {
            if ( isset( $capabilities[ $canonical ] ) ) {
                $capabilities[ $alias ] = $capabilities[ $canonical ];
            }
        }

        $cache = $capabilities;

        return $cache;
    }

    /**
     * Return the active usage profile.
     *
     * @return string
     */
    private function get_usage_profile() {
        $settings = tsap_get_option( 'tts_settings', array() );
        $profile  = isset( $settings['usage_profile'] ) ? sanitize_key( $settings['usage_profile'] ) : 'standard';

        $allowed = array( 'standard', 'advanced', 'enterprise' );
        if ( ! in_array( $profile, $allowed, true ) ) {
            $profile = 'standard';
        }

        return $profile;
    }

    /**
     * Check if the active usage profile grants access to a capability tier.
     *
     * @param string $target Target profile tier.
     * @return bool
     */
    private function usage_profile_allows( $target ) {
        $order = array(
            'standard'   => 1,
            'advanced'   => 2,
            'enterprise' => 3,
        );

        $current = $this->get_usage_profile();

        if ( ! isset( $order[ $target ] ) || ! isset( $order[ $current ] ) ) {
            return false;
        }

        return $order[ $current ] >= $order[ $target ];
    }

    /**
     * Public helper to expose the localized label for a usage profile.
     *
     * @param string $profile Usage profile slug.
     * @return string
     */
    public function get_usage_profile_label( $profile ) {
        return $this->format_usage_profile_label( $profile );
    }

    /**
     * Return a human readable label for a usage profile slug.
     *
     * @param string $profile Usage profile slug.
     * @return string
     */
    private function format_usage_profile_label( $profile ) {
        $labels = array(
            'standard'   => __( 'Profilo Standard', 'fp-publisher' ),
            'advanced'   => __( 'Profilo Avanzato', 'fp-publisher' ),
            'enterprise' => __( 'Profilo Enterprise', 'fp-publisher' ),
        );

        $profile = sanitize_key( $profile );

        if ( isset( $labels[ $profile ] ) ) {
            return $labels[ $profile ];
        }

        return ucfirst( $profile );
    }

    /**
     * Resolve an asset URL within the plugin directory.
     *
     * @param string $relative_path Relative path from the plugin root.
     *
     * @return string Asset URL or empty string when missing.
     */
    private function get_plugin_asset_url( $relative_path ) {
        $relative_path = ltrim( (string) $relative_path, '/' );

        if ( '' === $relative_path ) {
            return '';
        }

        $base_path = trailingslashit( TSAP_PLUGIN_DIR );
        $absolute  = $base_path . $relative_path;

        if ( ! file_exists( $absolute ) ) {
            return '';
        }

        return plugins_url( $relative_path, TSAP_PLUGIN_DIR . 'trello-social-auto-publisher.php' );
    }

    /**
     * Retrieve the shared menu registry instance.
     *
     * @return TTS_Admin_Menu_Registry
     */
    private function get_menu_registry() {
        if ( ! $this->menu_registry instanceof TTS_Admin_Menu_Registry ) {
            $this->menu_registry = new TTS_Admin_Menu_Registry( $this );
        }

        return $this->menu_registry;
    }

    /**
     * Determine if a menu callback can be registered.
     *
     * @param string $method            Callback method name.
     * @param string $slug              Menu slug.
     * @param string $menu_title        Menu title displayed to the user.
     * @param array  $available_methods Map of available methods and their callability.
     *
     * @return bool
     */
    private function can_register_menu_callback( $method, $slug, $menu_title, array $available_methods ) {
        if (
            isset( $available_methods[ $method ] )
            && ! empty( $available_methods[ $method ]['callable'] )
        ) {
            return true;
        }

        $this->add_missing_method_notice( $slug, $menu_title, $method );

        return false;
    }

    /**
     * Store a notice for missing menu callbacks.
     *
     * @param string $slug       Menu slug.
     * @param string $menu_title Menu title used for the admin notice.
     * @param string $method     Callback method name.
     */
    private function add_missing_method_notice( $slug, $menu_title, $method ) {
        $key = $slug . '|' . $menu_title;

        if ( isset( $this->missing_method_notices[ $key ] ) ) {
            return;
        }

        $this->missing_method_notices[ $key ] = array(
            'menu_title' => $menu_title,
            'method'     => $method,
        );
    }


    /**
     * Evaluate environment readiness for a quickstart package.
     *
     * @param array<string, mixed> $package Quickstart definition.
     * @return array<string, mixed>
     */
    private function assess_quickstart_package_readiness( array $package ) {
        $status = 'ready';
        $checks = array();

        if ( ! empty( $package['column_mapping'] ) ) {
            $trello_enabled = (bool) tsap_get_option( 'tts_trello_enabled', 1 );

            if ( $trello_enabled ) {
                $checks[] = array(
                    'status'  => 'ok',
                    'label'   => __( 'Trello abilitato', 'fp-publisher' ),
                    'message' => __( 'La sincronizzazione Trello è pronta per ricevere le nuove mappature.', 'fp-publisher' ),
                    'scope'   => 'trello',
                );
            } else {
                $status   = 'blocked';
                $checks[] = array(
                    'status'  => 'blocked',
                    'label'   => __( 'Abilita Trello', 'fp-publisher' ),
                    'message' => __( 'Riattiva Trello nelle impostazioni generali prima di usare questo preset.', 'fp-publisher' ),
                    'scope'   => 'trello',
                );
            }
        }

        $channels         = isset( $package['channels'] ) ? (array) $package['channels'] : array();
        $channel_messages = array();

        foreach ( $channels as $channel ) {
            $channel       = sanitize_key( $channel );
            $channel_label = ucfirst( $channel );
            $connection    = $this->check_platform_connection_status( $channel );
            $message       = isset( $connection['message'] ) ? $connection['message'] : '';

            switch ( isset( $connection['status'] ) ? $connection['status'] : '' ) {
                case 'not-configured':
                    $status             = 'blocked';
                    $channel_messages[] = array(
                        'status'  => 'blocked',
                        'label'   => sprintf( __( '%s: credenziali mancanti', 'fp-publisher' ), $channel_label ),
                        'message' => $message ? $message : __( "Configura l'app dal pannello Connessioni social.", 'fp-publisher' ),
                        'scope'   => 'channels',
                        'channel' => $channel,
                    );
                    break;
                case 'configured':
                    if ( 'blocked' !== $status ) {
                        $status = 'warning';
                    }
                    $channel_messages[] = array(
                        'status'  => 'warning',
                        'label'   => sprintf( __( "%s: collega l'account", 'fp-publisher' ), $channel_label ),
                        'message' => $message ? $message : __( 'Apri il Client Wizard per completare la connessione OAuth.', 'fp-publisher' ),
                        'scope'   => 'channels',
                        'channel' => $channel,
                    );
                    break;
                default:
                    $checks[] = array(
                        'status'  => 'ok',
                        'label'   => sprintf( __( '%s: integrazione pronta', 'fp-publisher' ), $channel_label ),
                        'message' => $message,
                        'scope'   => 'channels',
                        'channel' => $channel,
                    );
                    break;
            }
        }

        if ( ! empty( $channel_messages ) ) {
            $checks = array_merge( $checks, $channel_messages );
        }

        $blog_settings = isset( $package['blog_settings'] ) ? (string) $package['blog_settings'] : '';
        if ( '' !== $blog_settings ) {
            $blog_config   = $this->parse_quickstart_blog_settings( $blog_settings );
            $blog_messages = array();

            if ( isset( $blog_config['post_type'] ) && ! post_type_exists( $blog_config['post_type'] ) ) {
                $status         = 'blocked';
                $blog_messages[] = array(
                    'status'  => 'blocked',
                    'label'   => __( 'Post type inesistente', 'fp-publisher' ),
                    'message' => sprintf( __( 'Il post type "%s" non è registrato in questo sito.', 'fp-publisher' ), $blog_config['post_type'] ),
                    'scope'   => 'blog',
                );
            }

            if ( isset( $blog_config['author_id'] ) ) {
                $author_id = absint( $blog_config['author_id'] );
                if ( $author_id && ! get_user_by( 'ID', $author_id ) ) {
                    if ( 'blocked' !== $status ) {
                        $status = 'warning';
                    }
                    $blog_messages[] = array(
                        'status'  => 'warning',
                        'label'   => __( 'Autore non trovato', 'fp-publisher' ),
                        'message' => sprintf( __( "Crea o aggiorna l'utente #%d per assegnare i post.", 'fp-publisher' ), $author_id ),
                        'scope'   => 'blog',
                    );
                }
            }

            if ( isset( $blog_config['category_id'] ) ) {
                $category_id = absint( $blog_config['category_id'] );
                if ( $category_id && ! get_term( $category_id, 'category' ) ) {
                    if ( 'blocked' !== $status ) {
                        $status = 'warning';
                    }
                    $blog_messages[] = array(
                        'status'  => 'warning',
                        'label'   => __( 'Categoria assente', 'fp-publisher' ),
                        'message' => sprintf( __( 'La categoria #%d non è disponibile in questo WordPress.', 'fp-publisher' ), $category_id ),
                        'scope'   => 'blog',
                    );
                }
            }

            if ( empty( $blog_messages ) ) {
                $checks[] = array(
                    'status'  => 'ok',
                    'label'   => __( 'Preset blog valido', 'fp-publisher' ),
                    'message' => __( 'Autore e tassonomie corrispondono a risorse disponibili.', 'fp-publisher' ),
                    'scope'   => 'blog',
                );
            } else {
                $checks = array_merge( $checks, $blog_messages );
            }
        } else {
            if ( 'blocked' !== $status ) {
                $status = 'warning';
            }
            $checks[] = array(
                'status'  => 'warning',
                'label'   => __( 'Compila le impostazioni blog', 'fp-publisher' ),
                'message' => __( 'Il pacchetto non fornisce parametri blog: completali dal Client Wizard.', 'fp-publisher' ),
                'scope'   => 'blog',
            );
        }

        $checks = array_map( array( $this, 'sanitize_quickstart_check_entry' ), $checks );

        if ( 'ready' === $status ) {
            $summary = __( 'Tutti i prerequisiti risultano soddisfatti: puoi applicare il pacchetto in sicurezza.', 'fp-publisher' );
        } elseif ( 'warning' === $status ) {
            $summary = __( "Alcuni elementi richiedono attenzione ma non impediscono l'applicazione del preset.", 'fp-publisher' );
        } else {
            $summary = __( "Risolvi i requisiti bloccanti prima di procedere con l'applicazione.", 'fp-publisher' );
        }

        return array(
            'status'  => $status,
            'label'   => __( 'Validazione ambiente', 'fp-publisher' ),
            'summary' => $summary,
            'checks'  => $checks,
        );
    }

    /**
     * Sanitize quickstart checklist entries.
     *
     * @param array<string, mixed> $entry Raw entry.
     * @return array<string, mixed>
     */
    private function sanitize_quickstart_check_entry( $entry ) {
        $allowed_status = array( 'ok', 'warning', 'blocked', 'skipped' );

        $status = isset( $entry['status'] ) ? sanitize_key( $entry['status'] ) : 'ok';
        if ( ! in_array( $status, $allowed_status, true ) ) {
            $status = 'ok';
        }

        $sanitized = array(
            'status'  => $status,
            'label'   => isset( $entry['label'] ) ? wp_strip_all_tags( $entry['label'], true ) : '',
            'message' => isset( $entry['message'] ) ? wp_strip_all_tags( $entry['message'], true ) : '',
        );

        if ( isset( $entry['scope'] ) ) {
            $sanitized['scope'] = sanitize_key( $entry['scope'] );
        }

        if ( isset( $entry['channel'] ) ) {
            $sanitized['channel'] = sanitize_key( $entry['channel'] );
        }

        return $sanitized;
    }

    /**
     * Parse the blog settings string into an associative array.
     *
     * @param string $settings Blog settings string.
     * @return array<string, string>
     */
    private function parse_quickstart_blog_settings( $settings ) {
        $result = array();

        foreach ( explode( '|', $settings ) as $chunk ) {
            $chunk = trim( $chunk );
            if ( '' === $chunk ) {
                continue;
            }

            $parts = explode( ':', $chunk, 2 );
            if ( count( $parts ) < 2 ) {
                continue;
            }

            $key   = sanitize_key( trim( $parts[0] ) );
            $value = trim( $parts[1] );

            if ( '' === $key || '' === $value ) {
                continue;
            }

            $result[ $key ] = $value;
        }

        return $result;
    }

    /**
     * Render admin notices for missing or invalid menu callbacks.
     */
    public function render_missing_method_notices() {
        if ( empty( $this->missing_method_notices ) ) {
            return;
        }

        foreach ( $this->missing_method_notices as $notice ) {
            printf(
                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                sprintf(
                    /* translators: 1: Menu title. 2: Callback method name. */
                    esc_html__( 'The "%1$s" admin page could not be loaded because the callback "%2$s" is missing or not callable.', 'fp-publisher' ),
                    esc_html( $notice['menu_title'] ),
                    esc_html( $notice['method'] )
                )
            );
        }
    }

    /**
     * Configure screen options for the activity log.
     */
    public function setup_log_screen() {
        add_screen_option(
            'per_page',
            array(
                'label'   => __( 'Log entries per page', 'fp-publisher' ),
                'default' => 20,
                'option'  => 'fp_publisher_logs_per_page',
            )
        );

        add_filter( 'manage_fp-publisher_page_fp-publisher-log_columns', array( $this, 'filter_log_screen_columns' ) );
    }

    /**
     * Configure screen options for the social posts table.
     */
    public function setup_social_posts_screen() {
        add_screen_option(
            'per_page',
            array(
                'label'   => __( 'Social posts per page', 'fp-publisher' ),
                'default' => 20,
                'option'  => 'fp_publisher_social_posts_per_page',
            )
        );

        add_filter( 'manage_fp-publisher_page_fp-publisher-queue_columns', array( $this, 'filter_social_posts_columns' ) );
        add_filter( 'manage_fp-publisher_page_fp-publisher-social-posts_columns', array( $this, 'filter_social_posts_columns' ) );
    }

    /**
     * Persist screen option values for custom list tables.
     *
     * @param bool|int $status Whether to save the option, or the value to save.
     * @param string    $option Option name.
     * @param int       $value  Submitted value.
     *
     * @return bool|int
     */
    public function persist_screen_options( $status, $option, $value ) {
        if ( in_array( $option, array( 'fp_publisher_logs_per_page', 'fp_publisher_social_posts_per_page' ), true ) ) {
            return max( 1, (int) $value );
        }

        return $status;
    }

    /**
     * Render contextual help tabs for custom admin screens.
     *
     * @param WP_Screen $screen Current screen object.
     */
    public function register_screen_help_tabs( $screen ) {
        if ( ! $screen instanceof WP_Screen ) {
            return;
        }

        if ( 'fp-publisher_page_fp-publisher-log' === $screen->id ) {
            $screen->add_help_tab(
                array(
                    'id'      => 'fp-publisher-log-overview',
                    'title'   => __( 'Overview', 'fp-publisher' ),
                    'content' => '<p>' . esc_html__( 'Review every publication event, filter by channel or status, and download audit trails when troubleshooting delivery issues.', 'fp-publisher' ) . '</p>',
                )
            );

            $screen->add_help_tab(
                array(
                    'id'      => 'fp-publisher-log-actions',
                    'title'   => __( 'Filters & bulk actions', 'fp-publisher' ),
                    'content' => '<p>' . esc_html__( 'Use the filters above the table or the quick view links to focus on failed or successful deliveries. Select multiple rows to purge old entries once they have been exported.', 'fp-publisher' ) . '</p>',
                )
            );

            $screen->set_help_sidebar(
                '<p><strong>' . esc_html__( 'Additional resources', 'fp-publisher' ) . '</strong></p>' .
                '<p><a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-support#logs' ) ) . '">' . esc_html__( 'Troubleshooting guide', 'fp-publisher' ) . '</a></p>' .
                '<p><a href="' . esc_url( plugins_url( '../docs/admin-ui/list-tables.md', __FILE__ ) ) . '">' . esc_html__( 'Admin UI list tables handbook', 'fp-publisher' ) . '</a></p>'
            );
        }

        if ( in_array( $screen->id, array( 'fp-publisher_page_fp-publisher-queue', 'fp-publisher_page_fp-publisher-social-posts' ), true ) ) {
            $screen->add_help_tab(
                array(
                    'id'      => 'fp-publisher-queue-overview',
                    'title'   => __( 'Overview', 'fp-publisher' ),
                    'content' => '<p>' . esc_html__( 'Manage queued and published social posts, adjust scheduling details, and jump into the custom editor without leaving the table.', 'fp-publisher' ) . '</p>',
                )
            );

            $screen->add_help_tab(
                array(
                    'id'      => 'fp-publisher-queue-actions',
                    'title'   => __( 'Filters & bulk actions', 'fp-publisher' ),
                    'content' => '<p>' . esc_html__( 'Filter posts by client or publishing outcome, and bulk delete outdated drafts once they have been exported.', 'fp-publisher' ) . '</p>',
                )
            );

            $screen->set_help_sidebar(
                '<p><strong>' . esc_html__( 'Need more help?', 'fp-publisher' ) . '</strong></p>' .
                '<p><a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-support#production' ) ) . '">' . esc_html__( 'Production workflow checklist', 'fp-publisher' ) . '</a></p>' .
                '<p><a href="' . esc_url( plugins_url( '../docs/admin-ui/list-tables.md', __FILE__ ) ) . '">' . esc_html__( 'Admin UI list tables handbook', 'fp-publisher' ) . '</a></p>'
            );
        }
    }

    /**
     * Provide columns for the log screen so Screen Options can toggle them.
     *
     * @return array<string, string>
     */
    public function filter_log_screen_columns( $columns ) {
        $columns = TTS_Log_Table::get_column_definitions();
        unset( $columns['cb'] );

        return $columns;
    }

    /**
     * Provide columns for the social posts screen so Screen Options can toggle them.
     *
     * @return array<string, string>
     */
    public function filter_social_posts_columns( $columns ) {
        $columns = TTS_Social_Posts_Table::get_column_definitions();
        unset( $columns['cb'] );

        return $columns;
    }

    /**
     * Enqueue assets for the dashboard page.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_dashboard_assets( $hook ) {
        // Optimized hook checking - only load on FP Publisher pages
        $fp_publisher_pages = $this->get_registered_admin_page_hooks();

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
        TTS_Asset_Manager::enqueue_style( 'tts-foundation', 'admin/css/tts-foundation.css' );
        TTS_Asset_Manager::enqueue_style( 'tts-components', 'admin/css/tts-components.css', array( 'tts-foundation' ) );
        TTS_Asset_Manager::enqueue_style( 'tts-core', 'admin/css/tts-core.css', array( 'tts-components' ) );

        TTS_Asset_Manager::register_script( 'tts-notifications', 'admin/js/tts-notifications.js' );
        wp_enqueue_script( 'tts-notifications' );

        TTS_Asset_Manager::register_script( 'tts-admin-utils', 'admin/js/tts-admin-utils.js', array( 'tts-notifications', 'wp-util' ) );
        wp_enqueue_script( 'tts-admin-utils' );

        TTS_Asset_Manager::register_script( 'tts-help-system', 'admin/js/tts-help-system.js', array( 'tts-admin-utils' ) );
        wp_enqueue_script( 'tts-help-system' );

        // Essential JavaScript with optimized dependencies
        TTS_Asset_Manager::register_script( 'tts-core', 'admin/js/tts-core.js', array( 'jquery', 'tts-notifications' ) );
        wp_enqueue_script( 'tts-core' );

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
        $dashboard_hooks = array(
            $this->build_admin_hook_from_slug( $this->get_dashboard_page_slug(), true ),
            $this->build_admin_hook_from_slug( $this->get_dashboard_page_slug() ),
        );

        if ( in_array( $hook, $dashboard_hooks, true ) ) {
            $this->enqueue_dashboard_specific_assets();
        }

        $blueprint = $this->get_navigation_blueprint();
        $hub_hooks = array();

        foreach ( array( 'configuration', 'production', 'monitoring' ) as $section_key ) {
            if ( isset( $blueprint[ $section_key ]['hub']['slug'] ) ) {
                $hub_hooks[] = $this->build_admin_hook_from_slug( $blueprint[ $section_key ]['hub']['slug'] );
            }
        }

        if ( in_array( $hook, $hub_hooks, true ) ) {
            $this->enqueue_shared_admin_page_assets();
            $this->enqueue_hub_assets();
        }

        switch ( $hook ) {
            case 'fp-publisher_page_fp-publisher-calendar':
                $this->enqueue_calendar_assets();
                break;
            case 'fp-publisher_page_fp-publisher-analytics':
                $this->enqueue_analytics_assets();
                break;
            case 'fp-publisher_page_fp-publisher-connections':
            case 'fp-publisher_page_fp-publisher-social-connections':
                $this->enqueue_social_connections_assets();
                break;
            case 'fp-publisher_page_fp-publisher-client-wizard':
                // Wizard assets are handled separately to avoid duplication
                break;
            case 'fp-publisher_page_fp-publisher-system-health':
            case 'fp-publisher_page_fp-publisher-health':
                $this->enqueue_health_assets();
                break;
            case 'fp-publisher_page_fp-publisher-ai':
            case 'fp-publisher_page_fp-publisher-ai-features':
                $this->enqueue_ai_features_assets();
                break;
            case 'fp-publisher_page_fp-publisher-activity-log':
            case 'fp-publisher_page_fp-publisher-log':
            case 'fp-publisher_page_fp-publisher-templates':
            case 'fp-publisher_page_fp-publisher-quickstart':
            case 'fp-publisher_page_fp-publisher-queue':
            case 'fp-publisher_page_fp-publisher-social-posts':
            case 'fp-publisher_page_fp-publisher-settings':
            case 'fp-publisher_page_fp-publisher-connection-diagnostics':
            case 'fp-publisher_page_fp-publisher-test-connections':
            case 'fp-publisher_page_fp-publisher-support':
            case 'fp-publisher_page_fp-publisher-help':
            case 'fp-publisher_page_fp-publisher-publishing-health':
            case 'fp-publisher_page_fp-publisher-frequency-status':
                $this->enqueue_shared_admin_page_assets();

                if ( in_array( $hook, array( 'fp-publisher_page_fp-publisher-queue', 'fp-publisher_page_fp-publisher-social-posts' ), true ) ) {
                    $this->enqueue_social_post_editor_assets();
                }
                break;
        }

        if ( in_array( $hook, array( 'fp-publisher_page_fp-publisher-clients', 'fp-publisher_page_fp-publisher-clienti', 'fp-publisher_page_fp-publisher-content-library', 'fp-publisher_page_fp-publisher-content' ), true ) ) {
            $this->enqueue_shared_admin_page_assets();
        }
    }

    /**
     * Enqueue shared assets for admin pages without dedicated bundles.
     */
    private function enqueue_shared_admin_page_assets() {
        TTS_Asset_Manager::enqueue_style( 'tts-optimized', 'admin/css/tts-optimized.css', array( 'tts-core' ) );
        TTS_Asset_Manager::enqueue_script( 'tts-optimized-core', 'admin/js/tts-optimized-core.js', array( 'tts-core', 'tts-admin-utils' ) );
    }

    /**
     * Enqueue shared styling for admin hub pages.
     */
    private function enqueue_hub_assets() {
        TTS_Asset_Manager::enqueue_style( 'tts-hubs', 'admin/css/tts-hubs.css', array( 'tts-optimized' ) );
    }

    /**
     * Enqueue assets for the custom social post editor.
     */
    private function enqueue_social_post_editor_assets() {
        TTS_Asset_Manager::enqueue_style(
            'tts-social-post-editor',
            'admin/css/tts-social-post-editor.css',
            array( 'tts-optimized' )
        );

        wp_enqueue_media();
        wp_enqueue_script( 'jquery-ui-sortable' );

        TTS_Asset_Manager::enqueue_script(
            'tts-media',
            'admin/js/tts-media.js',
            array( 'tts-core', 'media-editor', 'jquery-ui-sortable' )
        );

        TTS_Asset_Manager::enqueue_script(
            'tts-social-post-editor',
            'admin/js/tts-social-post-editor.js',
            array( 'tts-core', 'jquery' )
        );
    }

    /**
     * Enqueue dashboard-specific assets.
     */
    private function enqueue_dashboard_specific_assets() {
        TTS_Asset_Manager::enqueue_style( 'tts-dashboard', 'admin/css/tts-dashboard.css', array( 'tts-core' ) );

        if ( ! $this->dashboard_needs_react_components() ) {
            return;
        }

        TTS_Asset_Manager::enqueue_script(
            'tts-dashboard',
            'admin/js/tts-dashboard.js',
            array( 'tts-core', 'tts-notifications', 'wp-element', 'wp-components', 'wp-api-fetch' )
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
        TTS_Asset_Manager::enqueue_style( 'tts-calendar', 'admin/css/tts-calendar.css', array( 'tts-core' ) );
        TTS_Asset_Manager::enqueue_script( 'tts-calendar', 'admin/js/tts-calendar.js', array( 'tts-core' ) );
    }

    /**
     * Enqueue health page specific assets.
     */
    private function enqueue_health_assets() {
        TTS_Asset_Manager::enqueue_style( 'tts-health', 'admin/css/tts-health.css', array( 'tts-core' ) );
    }

    /**
     * Enqueue AI features specific assets.
     */
    private function enqueue_ai_features_assets() {
        TTS_Asset_Manager::enqueue_script(
            'tts-advanced-features',
            'admin/js/tts-advanced-features.js',
            array( 'tts-core', 'tts-notifications', 'tts-admin-utils', 'tts-help-system' )
        );
    }

    /**
     * Enqueue social connections specific assets.
     */
    private function enqueue_social_connections_assets() {
        TTS_Asset_Manager::enqueue_style( 'tts-social-connections', 'admin/css/tts-social-connections.css', array( 'tts-core' ) );
        TTS_Asset_Manager::register_script( 'tts-social-connections', 'admin/js/tts-social-connections.js', array( 'tts-core' ) );
        wp_enqueue_script( 'tts-social-connections' );

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
        TTS_Asset_Manager::enqueue_style( 'tts-analytics', 'admin/css/tts-analytics.css', array( 'tts-core' ) );

        // Load Chart.js from CDN with integrity check for better performance
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js',
            array(),
            '4.4.0',
            true
        );

        TTS_Asset_Manager::register_script( 'tts-analytics', 'admin/js/tts-analytics.js', array( 'tts-core', 'chart-js' ) );
        wp_enqueue_script( 'tts-analytics' );

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

        TTS_Asset_Manager::enqueue_script( 'tts-wizard', 'admin/js/tts-wizard.js', array( 'tts-core' ) );

        wp_localize_script(
            'tts-wizard',
            'ttsWizard',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'tts_wizard' ),
                'trelloEnabled' => (bool) tsap_get_option( 'tts_trello_enabled', 1 ),
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

        TTS_Asset_Manager::enqueue_script(
            'tts-media',
            'admin/js/tts-media.js',
            array( 'tts-core', 'media-editor', 'jquery-ui-sortable' )
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
            
            $edit_link = self::get_social_post_editor_url(
                $post_id,
                array(
                    'tts_open_editor' => 1,
                )
            );
            $channel_display = is_array( $channel ) ? implode( ', ', $channel ) : $channel;
            
            $output .= sprintf(
                '<li><a href="%s">%s</a> - %s - %s</li>',
                esc_url( $edit_link ),
                esc_html( $post->post_title ),
                esc_html( $channel_display ),
                esc_html( $publish_at ? wp_date( 'Y-m-d H:i', strtotime( $publish_at ) ) : '' )
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

        TTS_Asset_Manager::register_script( 'tts-dashboard-widget', 'admin/js/tts-dashboard-widget.js', array( 'jquery' ) );
        wp_enqueue_script( 'tts-dashboard-widget' );

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
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        if ( ! (bool) tsap_get_option( 'tts_trello_enabled', 1 ) ) {
            return wp_send_json_error( __( 'Trello integration is disabled.', 'fp-publisher' ), 400 );
        }

        $board = isset( $_POST['board'] ) ? sanitize_text_field( wp_unslash( $_POST['board'] ) ) : '';
        $key   = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
        $token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

        if ( empty( $board ) ) {
            return wp_send_json_error( __( 'Board ID is required.', 'fp-publisher' ), 400 );
        }
        if ( empty( $key ) ) {
            return wp_send_json_error( __( 'Trello API key is required.', 'fp-publisher' ), 400 );
        }
        if ( empty( $token ) ) {
            return wp_send_json_error( __( 'Trello token is required.', 'fp-publisher' ), 400 );
        }

        if ( ! preg_match( '/^[a-f0-9]{24}$/i', $board ) ) {
            return wp_send_json_error( __( 'Invalid board ID format.', 'fp-publisher' ), 400 );
        }

        $response = wp_remote_get(
            'https://api.trello.com/1/boards/' . rawurlencode( $board ) . '/lists?key=' . rawurlencode( $key ) . '&token=' . rawurlencode( $token ),
            array( 'timeout' => 20 )
        );

        if ( is_wp_error( $response ) ) {
            error_log( 'TTS AJAX Error: ' . $response->get_error_message() );

            return wp_send_json_error(
                sprintf(
                    __( 'Failed to connect to Trello API: %s', 'fp-publisher' ),
                    $response->get_error_message()
                ),
                502
            );
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $http_code ) {
            error_log( "TTS AJAX Error: HTTP $http_code from Trello API" );

            return wp_send_json_error(
                sprintf(
                    __( 'Trello API returned error code %d. Please check your credentials.', 'fp-publisher' ),
                    $http_code
                ),
                502
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log( 'TTS AJAX Error: Invalid JSON response from Trello API' );

            return wp_send_json_error( __( 'Invalid response from Trello API.', 'fp-publisher' ), 502 );
        }

        return wp_send_json_success( $data );
    }

    /**
     * AJAX callback: refresh posts data for dashboard.
     */
    public function ajax_refresh_posts() {
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        try {
            $posts = get_posts(
                array(
                    'post_type'      => 'tts_social_post',
                    'posts_per_page' => 10,
                    'post_status'    => 'any',
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'meta_query'     => array(
                        'relation' => 'OR',
                        array(
                            'key'     => '_tts_publish_at',
                            'compare' => 'EXISTS',
                        ),
                        array(
                            'key'     => '_tts_publish_at',
                            'compare' => 'NOT EXISTS',
                        ),
                    ),
                )
            );

            if ( empty( $posts ) ) {
                return wp_send_json_success(
                    array(
                        'posts'     => array(),
                        'message'   => __( 'No posts found.', 'fp-publisher' ),
                        'timestamp' => current_time( 'timestamp' ),
                    )
                );
            }

            $formatted_posts = array();
            foreach ( $posts as $post ) {
                $channels   = get_post_meta( $post->ID, '_tts_social_channel', true );
                $status     = get_post_meta( $post->ID, '_published_status', true );
                $publish_at = get_post_meta( $post->ID, '_tts_publish_at', true );

                $channels = is_array( $channels ) ? $channels : ( $channels ? array( $channels ) : array() );
                $channels = array_map( 'sanitize_text_field', $channels );

                $title       = wp_strip_all_tags( wp_trim_words( $post->post_title, 10 ) );
                $status_safe = $status ? sanitize_text_field( $status ) : 'scheduled';
                $publish_safe = $publish_at ? sanitize_text_field( $publish_at ) : sanitize_text_field( $post->post_date );

                $edit_link = '';
                if ( current_user_can( 'edit_post', $post->ID ) ) {
                    $edit_link = add_query_arg(
                        array(
                            'post'            => absint( $post->ID ),
                            'action'          => 'edit',
                            'tts_open_editor' => 1,
                        ),
                        'admin.php?page=fp-publisher-queue'
                    );

                    $edit_link = esc_url_raw( $edit_link );
                }

                $formatted_posts[] = array(
                    'ID'         => intval( $post->ID ),
                    'title'      => $title,
                    'channel'    => $channels,
                    'status'     => $status_safe,
                    'publish_at' => $publish_safe,
                    'edit_link'  => $edit_link,
                );
            }

            return wp_send_json_success(
                array(
                    'posts'     => $formatted_posts,
                    'message'   => sprintf(
                        _n(
                            '%d post refreshed successfully',
                            '%d posts refreshed successfully',
                            count( $formatted_posts ),
                            'fp-publisher'
                        ),
                        count( $formatted_posts )
                    ),
                    'timestamp' => current_time( 'timestamp' ),
                )
            );
        } catch ( Exception $e ) {
            error_log( 'TTS Refresh Posts Error: ' . $e->getMessage() );

            return wp_send_json_error( __( 'An error occurred while refreshing posts. Please try again.', 'fp-publisher' ), 500 );
        }
    }

    /**
     * AJAX callback: delete a social post.
     */
    public function ajax_delete_post() {
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        if ( ! $this->check_rate_limit( 'delete_post', 20, 60 ) ) {
            return wp_send_json_error( __( 'Too many delete requests. Please wait a moment and try again.', 'fp-publisher' ), 429 );
        }

        $post_id = isset( $_POST['postId'] ) ? absint( wp_unslash( $_POST['postId'] ) ) : 0;

        if ( ! $post_id ) {
            return wp_send_json_error( __( 'Invalid post ID.', 'fp-publisher' ), 400 );
        }

        $post = get_post( $post_id );
        if ( ! $post || 'tts_social_post' !== $post->post_type ) {
            return wp_send_json_error( __( 'Post not found.', 'fp-publisher' ), 404 );
        }

        if ( ! current_user_can( 'delete_post', $post_id ) ) {
            return wp_send_json_error( __( 'You do not have permission to delete this specific post.', 'fp-publisher' ), 403 );
        }

        $result = wp_delete_post( $post_id, true );

        if ( $result ) {
            return wp_send_json_success(
                array(
                    'message' => __( 'Post deleted successfully.', 'fp-publisher' ),
                    'refresh' => true,
                )
            );
        }

        return wp_send_json_error( __( 'Failed to delete post.', 'fp-publisher' ), 500 );
    }

    /**
     * AJAX callback: handle bulk actions on social posts.
     */
    public function ajax_bulk_action() {
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        if ( ! $this->check_rate_limit( 'bulk_action', 10, 60 ) ) {
            return wp_send_json_error( __( 'Too many requests. Please wait a moment and try again.', 'fp-publisher' ), 429 );
        }

        $action   = isset( $_POST['bulkAction'] ) ? sanitize_key( wp_unslash( $_POST['bulkAction'] ) ) : '';
        $post_ids = isset( $_POST['postIds'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['postIds'] ) ) : array();

        if ( ! $action || empty( $post_ids ) ) {
            return wp_send_json_error( __( 'Invalid action or no posts selected.', 'fp-publisher' ), 400 );
        }

        $allowed_actions = array( 'delete', 'approve', 'revoke' );
        if ( ! in_array( $action, $allowed_actions, true ) ) {
            return wp_send_json_error( __( 'Invalid action specified.', 'fp-publisher' ), 400 );
        }

        if ( count( $post_ids ) > 100 ) {
            return wp_send_json_error( __( 'Too many posts selected. Please select 100 or fewer posts.', 'fp-publisher' ), 400 );
        }

        if ( 'delete' === $action && ! current_user_can( 'tts_delete_social_posts' ) ) {
            return wp_send_json_error( __( 'You do not have permission to delete social posts.', 'fp-publisher' ), 403 );
        }

        if ( in_array( $action, array( 'approve', 'revoke' ), true ) && ! current_user_can( 'tts_approve_posts' ) ) {
            return wp_send_json_error( __( 'You do not have permission to approve social posts.', 'fp-publisher' ), 403 );
        }

        $processed = 0;
        $errors    = array();

        foreach ( $post_ids as $post_id ) {
            if ( $post_id <= 0 ) {
                $errors[] = __( 'Invalid post ID provided.', 'fp-publisher' );
                continue;
            }

            $post = get_post( $post_id );
            if ( ! $post || 'tts_social_post' !== $post->post_type ) {
                $errors[] = sprintf( __( 'Post ID %d not found.', 'fp-publisher' ), $post_id );
                continue;
            }

            switch ( $action ) {
                case 'delete':
                    if ( current_user_can( 'delete_post', $post_id ) ) {
                        if ( wp_delete_post( $post_id, true ) ) {
                            $processed++;
                        } else {
                            $errors[] = sprintf( __( 'Failed to delete post ID %d.', 'fp-publisher' ), $post_id );
                        }
                    } else {
                        $errors[] = sprintf( __( 'You do not have permission to delete post ID %d.', 'fp-publisher' ), $post_id );
                    }
                    break;

                case 'approve':
                    if ( current_user_can( 'edit_post', $post_id ) ) {
                        update_post_meta( $post_id, '_tts_approved', true );
                        do_action( 'save_post_tts_social_post', $post_id, $post, true );
                        do_action( 'tts_post_approved', $post_id );
                        $processed++;
                    } else {
                        $errors[] = sprintf( __( 'You do not have permission to approve post ID %d.', 'fp-publisher' ), $post_id );
                    }
                    break;

                case 'revoke':
                    if ( current_user_can( 'edit_post', $post_id ) ) {
                        delete_post_meta( $post_id, '_tts_approved' );
                        do_action( 'save_post_tts_social_post', $post_id, $post, true );
                        $processed++;
                    } else {
                        $errors[] = sprintf( __( 'You do not have permission to revoke approval for post ID %d.', 'fp-publisher' ), $post_id );
                    }
                    break;
            }
        }

        if ( $processed > 0 ) {
            $message = sprintf(
                _n(
                    '%d post processed successfully.',
                    '%d posts processed successfully.',
                    $processed,
                    'fp-publisher'
                ),
                $processed
            );

            if ( ! empty( $errors ) ) {
                $message .= ' ' . sprintf( __( 'However, %d errors occurred.', 'fp-publisher' ), count( $errors ) );
            }

            return wp_send_json_success(
                array(
                    'message'   => $message,
                    'processed' => $processed,
                    'errors'    => $errors,
                    'refresh'   => true,
                )
            );
        }

        return wp_send_json_error( __( 'No posts were processed.', 'fp-publisher' ) . ' ' . implode( ' ', $errors ), 400 );
    }

    /**
     * Render the dashboard page.
     */
    public function render_dashboard_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Social Auto Publisher Dashboard', 'fp-publisher' ) . '</h1>';

        $profile = $this->get_usage_profile();
        if ( 'standard' === $profile ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'Modalità Standard attiva: mostriamo solo i widget essenziali per monitorare le pubblicazioni.', 'fp-publisher' ) . '</p></div>';
        } elseif ( 'enterprise' === $profile ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'Modalità Enterprise: controlli avanzati, audit e strumenti di remediation sono abilitati.', 'fp-publisher' ) . '</p></div>';
        }

        // Add notification area
        echo '<div id="tts-notification-area" style="margin: 15px 0;"></div>';

        // Health status banner (if there are issues)
        $this->render_health_status_banner();
        
        // Quick stats cards
        $this->render_dashboard_stats();

        // Enhanced monitoring section
        if ( $this->usage_profile_allows( 'advanced' ) ) {
            $this->render_monitoring_dashboard();
        } else {
            $this->render_standard_health_snapshot( $profile );
        }
        
        // Recent activity and actions
        echo '<div class="tts-dashboard-sections">';
        echo '<div class="tts-dashboard-left">';
        $this->render_recent_posts_section();
        echo '</div>';
        
        echo '<div class="tts-dashboard-right">';
        $this->render_quick_actions_section( $profile );
        $this->render_connection_test_widget();
        $this->render_system_status_widget( $profile );
        echo '</div>';
        echo '</div>';

        // Advanced tools section
        if ( $this->usage_profile_allows( 'enterprise' ) ) {
            $this->render_advanced_tools_section();
        }
        
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
     * Render condensed health overview for standard profile.
     *
     * @param string $profile Active usage profile.
     */
    private function render_standard_health_snapshot( $profile ) {
        $health            = TTS_Monitoring::get_current_health_status();
        $health_data       = tsap_get_option( 'tts_last_health_check', array() );
        $suggestions       = TTS_Monitoring::get_remediation_suggestions( $health_data );
        $actionable_items  = TTS_Monitoring::get_actionable_health_summary();
        $open_issues       = array_filter(
            $actionable_items,
            static function ( $item ) {
                return isset( $item['status'] ) && 'ok' !== $item['status'];
            }
        );

        echo '<div class="tts-standard-health">';
        echo '<h2>' . esc_html__( 'Stato rapido di pubblicazione', 'fp-publisher' ) . '</h2>';
        echo '<p class="tts-health-score">' . esc_html__( 'Salute sistema', 'fp-publisher' ) . ': <strong>' . esc_html( ucfirst( $health['status'] ) ) . '</strong> (' . intval( $health['score'] ) . '/100)</p>';
        if ( ! empty( $health['message'] ) ) {
            echo '<p>' . esc_html( $health['message'] ) . '</p>';
        }

        if ( ! empty( $open_issues ) ) {
            $this->render_actionable_health_summary( $open_issues, true );
        }

        if ( ! empty( $suggestions ) ) {
            echo '<div class="tts-health-suggestions">';
            echo '<strong>' . esc_html__( 'Azioni consigliate', 'fp-publisher' ) . '</strong>';
            echo '<ul>';
            foreach ( $suggestions as $suggestion ) {
                echo '<li>' . esc_html( $suggestion['title'] ) . ' — ' . esc_html( $suggestion['description'] );
                if ( ! empty( $suggestion['link'] ) ) {
                    echo ' <a href="' . esc_url( $suggestion['link'] ) . '" class="button-link">' . esc_html__( 'Apri', 'fp-publisher' ) . '</a>';
                }
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<p>' . esc_html__( 'Nessuna azione pendente: sei pronto a pubblicare.', 'fp-publisher' ) . '</p>';
        }

        if ( 'standard' === $profile ) {
            echo '<p class="description">' . esc_html__( 'Passa al profilo Avanzato per sbloccare monitoraggio in tempo reale e controlli granulari.', 'fp-publisher' ) . '</p>';
        }

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
        $health_data = tsap_get_option( 'tts_last_health_check', array() );
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
        $has_syncable_sources = ! empty( TTS_Content_Source::get_syncable_sources() );

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

        if ( ! $has_syncable_sources ) {
            echo '<p class="description tts-sync-disabled-note">' . esc_html__( 'Connect at least one remote content source to enable syncing.', 'fp-publisher' ) . '</p>';
        }
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
                $editor_link = self::get_social_post_editor_url(
                    $post->ID,
                    array(
                        'tts_open_editor' => 1,
                    )
                );
                echo '<a href="' . esc_url( $editor_link ) . '" class="tts-tooltip">';
                echo '<strong>' . esc_html($post->post_title) . '</strong>';
                echo '<span class="tts-tooltiptext">' . esc_html__('Click to edit this post', 'fp-publisher') . '</span>';
                echo '</a>';
                echo '<div class="row-actions">';
                echo '<span class="edit"><a href="' . esc_url( $editor_link ) . '">' . esc_html__('Edit', 'fp-publisher') . '</a> | </span>';
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
                $date_text = $publish_at ? wp_date( 'Y-m-d H:i', strtotime( $publish_at ) ) : get_the_date( 'Y-m-d H:i', $post );
                echo '<span class="tts-tooltip">';
                echo esc_html($date_text);
                echo '<span class="tts-tooltiptext">' . esc_html(human_time_diff(strtotime($date_text), current_time('timestamp'))) . ' ago</span>';
                echo '</span>';
                echo '</td>';
                echo '<td>';
                echo '<a href="' . esc_url(admin_url('admin.php?page=fp-publisher-queue&action=log&post=' . $post->ID)) . '" class="tts-btn small secondary">';
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
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-connection-diagnostics' ) ) . '" class="button button-primary">';
        echo esc_html__( 'Test All Connections', 'fp-publisher' );
        echo '</a>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Render quick actions section.
     *
     * @param string $profile Active usage profile.
     */
    private function render_quick_actions_section( $profile = 'standard' ) {
        echo '<div class="tts-dashboard-section">';
        echo '<h2>' . esc_html__( 'Quick Actions', 'fp-publisher' ) . '</h2>';
        echo '<div class="tts-quick-actions">';

        $visible_actions = $this->get_dashboard_quick_actions( $profile );

        if ( empty( $visible_actions ) ) {
            echo '<div class="notice notice-info" style="margin: 0;">';
            echo '<p>' . esc_html__( 'Non sono disponibili azioni rapide con i permessi correnti.', 'fp-publisher' ) . '</p>';
            echo '</div>';
        } else {
            foreach ( $visible_actions as $action ) {
                echo '<a href="' . esc_url( $action['url'] ) . '" class="tts-quick-action tts-tooltip" style="border-left: 4px solid ' . esc_attr( $action['color'] ) . ';">';
                echo '<div style="display: flex; align-items: center;">';
                echo '<span class="dashicons ' . esc_attr( $action['icon'] ) . '" style="color: ' . esc_attr( $action['color'] ) . '; margin-right: 12px; font-size: 20px;"></span>';
                echo '<div>';
                echo '<div style="font-weight: 600; margin-bottom: 2px;">' . esc_html( $action['title'] ) . '</div>';
                echo '<div style="font-size: 12px; color: #666;">' . esc_html( $action['description'] ) . '</div>';
                echo '</div>';
                echo '</div>';
                echo '<span class="tts-tooltiptext">' . esc_html( $action['description'] ) . '</span>';
                echo '</a>';
            }
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Transform a blueprint definition into a quick action configuration.
     *
     * @param array<string, mixed> $definition     Navigation definition.
     * @param array<string, string> $capability_map Map of slug to capability.
     * @return array<string, mixed>|null
     */
    private function build_quick_action_from_definition( array $definition, array $capability_map ) {
        if ( empty( $definition['quick_action'] ) || empty( $definition['slug'] ) ) {
            return null;
        }

        $quick_action = $definition['quick_action'];

        $title = isset( $quick_action['title'] )
            ? (string) $quick_action['title']
            : ( isset( $definition['title'] ) ? (string) $definition['title'] : '' );

        if ( '' === $title ) {
            return null;
        }

        $capability = '';
        if ( array_key_exists( 'capability', $quick_action ) ) {
            $capability = (string) $quick_action['capability'];
        } elseif ( isset( $definition['capability'] ) ) {
            $capability = (string) $definition['capability'];
        } elseif ( isset( $capability_map[ $definition['slug'] ] ) ) {
            $capability = (string) $capability_map[ $definition['slug'] ];
        }

        return array(
            'title'       => $title,
            'description' => isset( $quick_action['description'] ) ? (string) $quick_action['description'] : '',
            'slug'        => (string) $definition['slug'],
            'icon'        => isset( $quick_action['icon'] ) ? (string) $quick_action['icon'] : 'dashicons-admin-generic',
            'color'       => isset( $quick_action['color'] ) ? (string) $quick_action['color'] : '#0f172a',
            'profiles'    => isset( $quick_action['profiles'] ) ? (array) $quick_action['profiles'] : array( 'standard', 'advanced', 'enterprise' ),
            'capability'  => $capability,
            'url'         => admin_url( 'admin.php?page=' . $definition['slug'] ),
        );
    }

    /**
     * Return the list of quick actions visible on the dashboard for the given profile.
     *
     * @param string $profile Active usage profile.
     * @return array<int, array<string, mixed>>
     */
    private function get_dashboard_quick_actions( $profile ) {
        $profile = sanitize_key( $profile );

        if ( ! in_array( $profile, array( 'standard', 'advanced', 'enterprise' ), true ) ) {
            $profile = 'standard';
        }

        $capability_map = $this->get_admin_menu_capability_map();
        $blueprint      = $this->get_navigation_blueprint();
        $actions        = array();

        foreach ( $blueprint as $section ) {
            if ( isset( $section['hub'] ) ) {
                $action = $this->build_quick_action_from_definition( $section['hub'], $capability_map );
                if ( null !== $action ) {
                    $actions[] = $action;
                }
            }

            if ( empty( $section['items'] ) || ! is_array( $section['items'] ) ) {
                continue;
            }

            foreach ( $section['items'] as $item ) {
                $action = $this->build_quick_action_from_definition( $item, $capability_map );
                if ( null !== $action ) {
                    $actions[] = $action;
                }
            }
        }

        /**
         * Allow plugins to customize the dashboard quick actions before filtering.
         *
         * @param array<int, array<string, mixed>> $actions Quick action definitions.
         * @param string                           $profile Active usage profile.
         */
        $actions = apply_filters( 'tts_dashboard_quick_actions', $actions, $profile );

        $visible_actions = array_filter(
            $actions,
            function ( $action ) use ( $profile ) {
                if ( empty( $action['url'] ) ) {
                    return false;
                }

                if ( ! empty( $action['profiles'] ) && ! in_array( $profile, (array) $action['profiles'], true ) ) {
                    return false;
                }

                if ( ! empty( $action['capability'] ) && ! current_user_can( $action['capability'] ) ) {
                    return false;
                }

                return true;
            }
        );

        return array_values( $visible_actions );
    }


    /**
     * Render a hub page with navigational cards for grouped features.
     *
     * @param array<string, mixed> $config Rendering configuration.
     */
    private function render_hub_page( array $config ) {
        $title       = isset( $config['title'] ) ? (string) $config['title'] : '';
        $description = isset( $config['description'] ) ? (string) $config['description'] : '';
        $class       = isset( $config['class'] ) ? (string) $config['class'] : '';
        $cards       = isset( $config['cards'] ) && is_array( $config['cards'] ) ? $config['cards'] : array();
        $footer      = isset( $config['footer'] ) ? (array) $config['footer'] : array();

        $heading_id     = wp_unique_id( 'fp-admin-page-title-' );
        $description_id = '' !== $description ? wp_unique_id( 'fp-admin-page-lead-' ) : '';

        echo '<div class="wrap fp-admin-hub tts-hub ' . esc_attr( $class ) . '">';

        echo '<div class="fp-admin-hub__intro">';
        echo '<div class="fp-admin-page-header" role="group" aria-labelledby="' . esc_attr( $heading_id ) . '"';

        if ( '' !== $description_id ) {
            echo ' aria-describedby="' . esc_attr( $description_id ) . '"';
        }

        echo '>';
        echo '<h1 class="fp-admin-page-header__title" id="' . esc_attr( $heading_id ) . '"';

        if ( '' !== $description_id ) {
            echo ' aria-describedby="' . esc_attr( $description_id ) . '"';
        }

        echo '>' . esc_html( $title ) . '</h1>';

        if ( '' !== $description && '' !== $description_id ) {
            echo '<p class="fp-admin-page-header__lead fp-admin-hub__description" id="' . esc_attr( $description_id ) . '">' . esc_html( $description ) . '</p>';
        }

        echo '</div>';
        echo '</div>';

        $normalized_cards = array();

        foreach ( $cards as $card ) {
            $capability = isset( $card['capability'] ) ? (string) $card['capability'] : '';
            $card['capability'] = $capability;
            $card['accessible'] = ( '' === $capability || current_user_can( $capability ) );

            $normalized_cards[] = $card;
        }

        $accessible_cards = array_filter(
            $normalized_cards,
            function ( $card ) {
                return ! empty( $card['accessible'] );
            }
        );

        $restricted_cards = array_filter(
            $normalized_cards,
            function ( $card ) {
                return empty( $card['accessible'] );
            }
        );

        if ( empty( $normalized_cards ) ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'Nessuna card è stata configurata per questo hub.', 'fp-publisher' ) . '</p></div>';
            echo '</div>';
            return;
        }

        if ( empty( $accessible_cards ) ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'Tutti gli strumenti di questa sezione richiedono permessi aggiuntivi. Contatta un amministratore per ottenere l’accesso.', 'fp-publisher' ) . '</p></div>';
        }

        echo '<div class="fp-admin-hub__grid">';

        foreach ( $accessible_cards as $card ) {
            $icon             = isset( $card['icon'] ) ? (string) $card['icon'] : 'dashicons-admin-generic';
            $title_text       = isset( $card['title'] ) ? (string) $card['title'] : '';
            $card_description = isset( $card['description'] ) ? (string) $card['description'] : '';
            $url              = isset( $card['url'] ) ? (string) $card['url'] : '';
            $meta             = isset( $card['meta'] ) && is_array( $card['meta'] ) ? $card['meta'] : array();

            echo '<a class="fp-admin-card fp-admin-hub-card tts-hub-card" href="' . esc_url( $url ) . '">';
            echo '<div class="fp-admin-hub-card__header">';
            echo '<span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
            echo '<div class="fp-admin-hub-card__heading">';
            echo '<h2 class="fp-admin-hub-card__title">' . esc_html( $title_text ) . '</h2>';
            if ( '' !== $card_description ) {
                echo '<p class="fp-admin-hub-card__description">' . esc_html( $card_description ) . '</p>';
            }
            echo '</div>';
            echo '<span class="fp-admin-hub-card__cta" aria-hidden="true">&rarr;</span>';
            echo '</div>';

            if ( ! empty( $meta ) ) {
                echo '<ul class="fp-admin-hub-card__meta tts-hub-card__meta">';
                foreach ( $meta as $meta_item ) {
                    echo '<li>' . esc_html( $meta_item ) . '</li>';
                }
                echo '</ul>';
            }

            echo '</a>';
        }

        foreach ( $restricted_cards as $card ) {
            $icon             = isset( $card['icon'] ) ? (string) $card['icon'] : 'dashicons-admin-generic';
            $title_text       = isset( $card['title'] ) ? (string) $card['title'] : '';
            $card_description = isset( $card['description'] ) ? (string) $card['description'] : '';
            $meta             = isset( $card['meta'] ) && is_array( $card['meta'] ) ? $card['meta'] : array();
            $capability       = isset( $card['capability'] ) ? (string) $card['capability'] : '';

            echo '<div class="fp-admin-card fp-admin-hub-card fp-admin-hub-card--locked tts-hub-card tts-hub-card--locked" aria-disabled="true">';
            echo '<div class="fp-admin-hub-card__header">';
            echo '<span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
            echo '<div class="fp-admin-hub-card__heading">';
            echo '<h2 class="fp-admin-hub-card__title">' . esc_html( $title_text ) . '</h2>';
            if ( '' !== $card_description ) {
                echo '<p class="fp-admin-hub-card__description">' . esc_html( $card_description ) . '</p>';
            }

            if ( ! empty( $meta ) ) {
                echo '<ul class="fp-admin-hub-card__meta tts-hub-card__meta">';
                foreach ( $meta as $meta_item ) {
                    echo '<li>' . esc_html( $meta_item ) . '</li>';
                }
                echo '</ul>';
            }

            echo '</div>';
            echo '<span class="fp-admin-hub-card__cta" aria-hidden="true"><span class="dashicons dashicons-lock"></span></span>';
            echo '</div>';

            $lock_message = __( 'Richiede permessi aggiuntivi per essere utilizzato.', 'fp-publisher' );
            if ( '' !== $capability ) {
                $lock_message = sprintf(
                    /* translators: %s is the capability name required to access the hub card. */
                    __( 'Richiede il permesso "%s".', 'fp-publisher' ),
                    $capability
                );
            }

            echo '<p class="fp-admin-hub-card__lock tts-hub-card__lock"><span class="dashicons dashicons-lock" aria-hidden="true"></span>' . esc_html( $lock_message ) . '</p>';

            echo '</div>';
        }

        echo '</div>';

        if ( ! empty( $footer ) ) {
            echo '<div class="fp-admin-hub__footer tts-hub-footer">';
            if ( ! empty( $footer['title'] ) ) {
                echo '<h2 class="fp-admin-hub__footer-title">' . esc_html( $footer['title'] ) . '</h2>';
            }

            if ( ! empty( $footer['description'] ) ) {
                echo '<p class="fp-admin-hub__footer-description">' . esc_html( $footer['description'] ) . '</p>';
            }

            if ( ! empty( $footer['links'] ) && is_array( $footer['links'] ) ) {
                echo '<ul class="fp-admin-hub__links tts-hub-footer__links">';
                foreach ( $footer['links'] as $link ) {
                    $label = isset( $link['label'] ) ? (string) $link['label'] : '';
                    $url   = isset( $link['url'] ) ? (string) $link['url'] : '';
                    if ( '' === $label || '' === $url ) {
                        continue;
                    }
                    $link_target = empty( $link['external'] ) ? '_self' : '_blank';
                    echo '<li><a href="' . esc_url( $url ) . '" target="' . esc_attr( $link_target ) . '"' . ( '_blank' === $link_target ? ' rel="noopener noreferrer"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
                }
                echo '</ul>';
            }

            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Build the render configuration for a hub section based on the navigation blueprint.
     *
     * @param string $section_key Blueprint section identifier.
     * @return array<string, mixed>
     */
    private function get_hub_render_config_from_blueprint( $section_key ) {
        $blueprint       = $this->get_navigation_blueprint();
        $capability_map  = $this->get_admin_menu_capability_map();

        if ( empty( $blueprint[ $section_key ] ) ) {
            return array(
                'title'       => '',
                'description' => '',
                'class'       => '',
                'cards'       => array(),
            );
        }

        $section = $blueprint[ $section_key ];
        $hub     = isset( $section['hub'] ) ? $section['hub'] : array();
        $cards   = array();

        if ( ! empty( $section['items'] ) && is_array( $section['items'] ) ) {
            foreach ( $section['items'] as $item ) {
                if ( empty( $item['card'] ) || empty( $item['slug'] ) ) {
                    continue;
                }

                $card = $item['card'];

                $capability = '';
                if ( array_key_exists( 'capability', $card ) ) {
                    $capability = (string) $card['capability'];
                } elseif ( isset( $item['capability'] ) ) {
                    $capability = (string) $item['capability'];
                } elseif ( isset( $capability_map[ $item['slug'] ] ) ) {
                    $capability = (string) $capability_map[ $item['slug'] ];
                }

                if ( '' === $capability && ! array_key_exists( 'capability', $card ) && ! isset( $item['capability'] ) ) {
                    $capability = 'manage_options';
                }

                $cards[] = array(
                    'title'       => isset( $card['title'] ) ? (string) $card['title'] : ( isset( $item['title'] ) ? (string) $item['title'] : '' ),
                    'description' => isset( $card['description'] ) ? (string) $card['description'] : '',
                    'url'         => admin_url( 'admin.php?page=' . $item['slug'] ),
                    'icon'        => isset( $card['icon'] ) ? (string) $card['icon'] : 'dashicons-admin-generic',
                    'capability'  => $capability,
                    'meta'        => isset( $card['meta'] ) ? (array) $card['meta'] : array(),
                );
            }
        }

        if ( ! empty( $hub['extra_cards'] ) && is_array( $hub['extra_cards'] ) ) {
            foreach ( $hub['extra_cards'] as $extra_card ) {
                if ( empty( $extra_card['title'] ) || empty( $extra_card['url'] ) ) {
                    continue;
                }

                $capability = '';
                if ( array_key_exists( 'capability', $extra_card ) ) {
                    $capability = (string) $extra_card['capability'];
                }

                if ( '' === $capability && ! array_key_exists( 'capability', $extra_card ) ) {
                    $capability = 'manage_options';
                }

                $cards[] = array(
                    'title'       => (string) $extra_card['title'],
                    'description' => isset( $extra_card['description'] ) ? (string) $extra_card['description'] : '',
                    'url'         => (string) $extra_card['url'],
                    'icon'        => isset( $extra_card['icon'] ) ? (string) $extra_card['icon'] : 'dashicons-admin-generic',
                    'capability'  => $capability,
                    'meta'        => isset( $extra_card['meta'] ) ? (array) $extra_card['meta'] : array(),
                );
            }
        }

        $config = array(
            'title'       => isset( $hub['title'] ) ? (string) $hub['title'] : ( isset( $section['label'] ) ? (string) $section['label'] : '' ),
            'description' => isset( $hub['description'] ) ? (string) $hub['description'] : '',
            'class'       => isset( $hub['class'] ) ? (string) $hub['class'] : '',
            'cards'       => $cards,
        );

        if ( ! empty( $hub['footer'] ) ) {
            $config['footer'] = $hub['footer'];
        }

        return $config;
    }

    /**
     * Render the configuration hub page.
     */
    public function render_configuration_hub_page() {
        $this->render_hub_page( $this->get_hub_render_config_from_blueprint( 'configuration' ) );
    }

    /**
     * Render the production hub page.
     */
    public function render_production_hub_page() {
        $this->render_hub_page( $this->get_hub_render_config_from_blueprint( 'production' ) );
    }

    /**
     * Render the monitoring hub page.
     */
    public function render_monitoring_hub_page() {
        $this->render_hub_page( $this->get_hub_render_config_from_blueprint( 'monitoring' ) );
    }


    /**
     * Render actionable health summary items.
     *
     * @param array<int, array<string, mixed>> $items   Summary items from monitoring.
     * @param bool                              $compact Whether to render a compact layout.
     */
    private function render_actionable_health_summary( array $items, $compact = false ) {
        if ( empty( $items ) ) {
            return;
        }

        $status_icons = array(
            'critical' => '🚨',
            'warning'  => '⚠️',
            'ok'       => '✅',
        );

        $status_classes = array(
            'critical' => 'tts-status-critical',
            'warning'  => 'tts-status-warning',
            'ok'       => 'tts-status-ok',
        );

        $wrapper_classes = array( 'tts-actionable-summary' );
        if ( $compact ) {
            $wrapper_classes[] = 'tts-actionable-summary--compact';
        }

        echo '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '">';

        if ( ! $compact ) {
            echo '<h4>' . esc_html__( 'Componenti da monitorare', 'fp-publisher' ) . '</h4>';
        }

        echo '<ul class="tts-actionable-list">';
        foreach ( $items as $item ) {
            $status = isset( $item['status'] ) ? $item['status'] : 'ok';
            $icon   = isset( $status_icons[ $status ] ) ? $status_icons[ $status ] : 'ℹ️';
            $class  = isset( $status_classes[ $status ] ) ? $status_classes[ $status ] : 'tts-status-ok';

            echo '<li class="' . esc_attr( 'tts-actionable-item ' . $class ) . '">';
            echo '<div class="tts-actionable-item-header">';
            echo '<span class="tts-actionable-icon">' . esc_html( $icon ) . '</span>';

            if ( isset( $item['count'] ) && (int) $item['count'] > 0 ) {
                echo '<span class="tts-actionable-count">' . intval( $item['count'] ) . '</span>';
            }

            echo '<div class="tts-actionable-copy">';
            echo '<strong>' . esc_html( isset( $item['label'] ) ? $item['label'] : __( 'Componente', 'fp-publisher' ) ) . '</strong>';

            if ( ! empty( $item['description'] ) ) {
                echo '<p>' . esc_html( $item['description'] ) . '</p>';
            }

            echo '</div>';
            echo '</div>';

            if ( ! empty( $item['actions'] ) && is_array( $item['actions'] ) ) {
                echo '<ul class="tts-actionable-actions">';
                foreach ( $item['actions'] as $action ) {
                    if ( empty( $action ) || ! is_array( $action ) ) {
                        continue;
                    }

                    echo '<li>' . esc_html( isset( $action['description'] ) ? $action['description'] : '' );

                    if ( ! empty( $action['url'] ) ) {
                        echo ' <a class="button-link" href="' . esc_url( $action['url'] ) . '">' . esc_html__( 'Dettagli', 'fp-publisher' ) . '</a>';
                    }

                    echo '</li>';
                }
                echo '</ul>';
            }

            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }


    /**
     * Render system status widget for dashboard.
     *
     * @param string $profile Active usage profile.
     */
    private function render_system_status_widget( $profile = 'standard' ) {
        echo '<div class="tts-dashboard-section">';
        echo '<h2>' . esc_html__( 'System Status', 'fp-publisher' ) . '</h2>';

        // Check various system components
        $status_checks = array();
        $health_data   = tsap_get_option( 'tts_last_health_check', array() );

        // Check WordPress requirements
        $wp_version = get_bloginfo( 'version' );
        $status_checks['wordpress'] = array(
            'name'    => 'WordPress Version',
            'status'  => version_compare( $wp_version, '5.0', '>=' ) ? 'success' : 'error',
            'message' => 'WordPress ' . $wp_version,
        );

        // Check if Action Scheduler is available
        $status_checks['scheduler'] = array(
            'name'    => 'Action Scheduler',
            'status'  => class_exists( 'ActionScheduler' ) ? 'success' : 'warning',
            'message' => class_exists( 'ActionScheduler' ) ? 'Available' : 'Not available',
        );

        // Check recent error logs
        $recent_errors = get_posts( array(
            'post_type'      => 'tts_log',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'   => '_log_level',
                    'value' => 'error',
                    'compare' => '=',
                ),
            ),
            'date_query'     => array(
                array(
                    'after' => '24 hours ago',
                ),
            ),
        ) );

        $status_checks['errors'] = array(
            'name'    => 'Recent Errors',
            'status'  => empty( $recent_errors ) ? 'success' : 'warning',
            'message' => empty( $recent_errors ) ? 'No errors in 24h' : count( $recent_errors ) . ' error(s) in 24h',
        );

        // Overall health calculation
        $success_count = 0;
        foreach ( $status_checks as $check ) {
            if ( 'success' === $check['status'] ) {
                $success_count++;
            }
        }
        $health_percentage = round( ( $success_count / count( $status_checks ) ) * 100 );

        // Health indicator
        echo '<div style="text-align: center; margin-bottom: 15px;">';
        $health_color = $health_percentage >= 80 ? '#00a32a' : ( $health_percentage >= 60 ? '#f56e28' : '#d63638' );
        echo '<div style="font-size: 24px; color: ' . $health_color . '; font-weight: bold;">';
        echo $health_percentage . '% ' . esc_html__( 'Healthy', 'fp-publisher' );
        echo '</div>';
        echo '</div>';

        // Status items
        foreach ( $status_checks as $key => $check ) {
            $icon_color = 'success' === $check['status'] ? '#00a32a' : ( 'warning' === $check['status'] ? '#f56e28' : '#d63638' );
            echo '<div style="display: flex; align-items: center; margin-bottom: 8px;">';
            echo '<span class="tts-status-indicator ' . esc_attr( $check['status'] ) . '" style="background: ' . esc_attr( $icon_color ) . ';"></span>';
            echo '<span style="flex: 1;">' . esc_html( $check['name'] ) . '</span>';
            echo '<span style="color: #666; font-size: 12px;">' . esc_html( $check['message'] ) . '</span>';
            echo '</div>';
        }

        $actionable_summary = TTS_Monitoring::get_actionable_health_summary();
        if ( ! empty( $actionable_summary ) ) {
            $this->render_actionable_health_summary( $actionable_summary );
        }

        // Scheduled tasks summary
        $tasks = TTS_Monitoring::get_scheduled_task_summary();
        if ( ! empty( $tasks ) ) {
            echo '<div class="tts-scheduled-tasks">';
            echo '<h4>' . esc_html__( 'Attività pianificate', 'fp-publisher' ) . '</h4>';
            echo '<ul>';
            foreach ( $tasks as $task ) {
                $status    = 'scheduled' === $task['status'] ? '🟢' : '⚠️';
                $next_run  = $task['next_run'];
                $next_text = __( 'Non pianificata', 'fp-publisher' );

                if ( $next_run ) {
                    $next_text = human_time_diff( $next_run, time() );
                }

                echo '<li>' . esc_html( $status . ' ' . $task['label'] . ' · ' . $task['frequency'] . ' — ' . $next_text ) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        if ( $this->usage_profile_allows( 'advanced' ) ) {
            $suggestions = TTS_Monitoring::get_remediation_suggestions( $health_data );
            if ( ! empty( $suggestions ) ) {
                echo '<div class="tts-remediation-hints">';
                echo '<h4>' . esc_html__( 'Prossime azioni consigliate', 'fp-publisher' ) . '</h4>';
                echo '<ul>';
                foreach ( $suggestions as $suggestion ) {
                    echo '<li>' . esc_html( $suggestion['title'] ) . ' — ' . esc_html( $suggestion['description'] );
                    if ( ! empty( $suggestion['link'] ) ) {
                        echo ' <a href="' . esc_url( $suggestion['link'] ) . '" class="button-link">' . esc_html__( 'Dettagli', 'fp-publisher' ) . '</a>';
                    }
                    echo '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
        }

        echo '<div style="margin-top: 15px;">';
        echo '<a href="' . admin_url( 'admin.php?page=fp-publisher-system-health' ) . '" class="tts-btn small">' . esc_html__( 'View Detailed Status', 'fp-publisher' ) . '</a>';
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
        $client_wizard_url = admin_url( 'admin.php?page=fp-publisher-client-wizard' );
        $new_client_url    = admin_url( 'post-new.php?post_type=tts_client' );

        echo '<div class="wrap tts-clients-page">';
        echo '<h1>' . esc_html__( 'Clienti', 'fp-publisher' ) . '</h1>';
        echo '<p class="description">' . esc_html__( 'Raccogli qui tutti i brand o progetti che gestisci: ogni cliente ha credenziali, flussi e canali social dedicati.', 'fp-publisher' ) . '</p>';

        echo '<div class="notice notice-info tts-client-instructions">';
        echo '<h2>' . esc_html__( 'Come creare un nuovo cliente', 'fp-publisher' ) . '</h2>';
        echo '<ol>';
        echo '<li>' . sprintf(
            /* translators: %s is a link to the Client Wizard page. */
            esc_html__( 'Apri il Client Wizard dal menu oppure %s.', 'fp-publisher' ),
            '<a href="' . esc_url( $client_wizard_url ) . '" class="button-link">' . esc_html__( 'clicca qui per iniziare', 'fp-publisher' ) . '</a>'
        ) . '</li>';
        echo '<li>' . esc_html__( 'Completa i passaggi con le informazioni dell’azienda e scegli quali canali social collegare.', 'fp-publisher' ) . '</li>';
        echo '<li>' . esc_html__( 'Inserisci API key, token e credenziali richieste per ogni piattaforma selezionata.', 'fp-publisher' ) . '</li>';
        echo '<li>' . esc_html__( 'Definisci mappature Trello, frequenze di pubblicazione e approvatori del contenuto.', 'fp-publisher' ) . '</li>';
        echo '<li>' . esc_html__( 'Conferma il riepilogo finale: il cliente sarà pronto e i post verranno importati automaticamente.', 'fp-publisher' ) . '</li>';
        echo '</ol>';
        echo '<p>' . esc_html__( 'Se preferisci, puoi creare un cliente manualmente e completare i metadati in un secondo momento.', 'fp-publisher' ) . '</p>';
        echo '</div>';

        echo '<div class="tts-client-actions">';
        echo '<a href="' . esc_url( $client_wizard_url ) . '" class="button button-primary">' . esc_html__( 'Avvia Client Wizard', 'fp-publisher' ) . '</a> ';
        echo '<a href="' . esc_url( $new_client_url ) . '" class="button">' . esc_html__( 'Crea cliente manualmente', 'fp-publisher' ) . '</a>';
        echo '</div>';

        if ( ! empty( $clients ) ) {
            $client_count = count( $clients );
            echo '<h2>' . esc_html__( 'Clienti configurati', 'fp-publisher' ) . '</h2>';
            echo '<p>' . sprintf(
                /* translators: %d is the number of configured clients. */
                esc_html__( 'Hai %d cliente/i pronti alla pubblicazione. Seleziona un cliente per vedere i suoi contenuti pianificati.', 'fp-publisher' ),
                (int) $client_count
            ) . '</p>';

            echo '<ul class="tts-client-list">';
            foreach ( $clients as $client ) {
                $url          = add_query_arg(
                    array(
                        'page'       => 'fp-publisher-queue',
                        'tts_client' => $client->ID,
                    ),
                    admin_url( 'admin.php' )
                );
                $last_updated = get_the_modified_date( get_option( 'date_format' ), $client );

                echo '<li class="tts-client-list-item">';
                echo '<strong><a href="' . esc_url( $url ) . '">' . esc_html( $client->post_title ) . '</a></strong>';
                if ( $last_updated ) {
                    echo '<span class="tts-client-updated">' . sprintf(
                        /* translators: %s is the last updated date. */
                        esc_html__( 'Ultimo aggiornamento: %s', 'fp-publisher' ),
                        esc_html( $last_updated )
                    ) . '</span>';
                }
                echo '<div class="tts-client-shortcuts">';
                echo '<a href="' . esc_url( get_edit_post_link( $client->ID ) ) . '" class="button-link">' . esc_html__( 'Modifica cliente', 'fp-publisher' ) . '</a> | ';
                $new_post_url = add_query_arg(
                    array(
                        'page'            => 'fp-publisher-queue',
                        'tts_client'      => $client->ID,
                        'tts_open_editor' => 1,
                    ),
                    admin_url( 'admin.php' )
                );
                echo '<a href="' . esc_url( $new_post_url ) . '" class="button-link">' . esc_html__( 'Nuovo post social', 'fp-publisher' ) . '</a>';
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<div class="notice notice-warning">';
            echo '<p>' . esc_html__( 'Non hai ancora creato clienti. Usa il wizard per configurare il primo e collegare i canali social.', 'fp-publisher' ) . '</p>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Render the quickstart packages page.
     */
    public function render_quickstart_packages_page() {
        if ( ! session_id() ) {
            session_start();
        }

        $packages        = $this->get_quickstart_packages();
        $active_prefill  = isset( $_SESSION['tts_quickstart_prefill'] ) ? $_SESSION['tts_quickstart_prefill'] : array();
        $current_settings = tsap_get_option( 'tts_settings', array() );
        $previewed_slug   = isset( $_GET['package_preview'] ) ? sanitize_key( wp_unslash( $_GET['package_preview'] ) ) : '';
        $notice_message  = '';
        $notice_type     = 'updated';
        $current_profile = $this->get_usage_profile();
        $base_quickstart_url = admin_url( 'admin.php?page=fp-publisher-templates' );

        if ( isset( $_POST['tts_apply_package'] ) ) {
            check_admin_referer( 'tts_apply_quickstart' );

            $slug     = isset( $_POST['tts_package_slug'] ) ? sanitize_key( wp_unslash( $_POST['tts_package_slug'] ) ) : '';
            $override = ! empty( $_POST['tts_override_existing'] );
            $result   = $this->apply_quickstart_package( $slug, $override );

            if ( is_wp_error( $result ) ) {
                $notice_message = $result->get_error_message();
                $notice_type    = 'error';
            } else {
                $notice_message = $result['message'];
                $active_prefill = isset( $_SESSION['tts_quickstart_prefill'] ) ? $_SESSION['tts_quickstart_prefill'] : array();
            }
        }

        echo '<div class="wrap tts-quickstart-packages">';
        echo '<h1>' . esc_html__( 'Pacchetti Quickstart', 'fp-publisher' ) . '</h1>';
        echo '<p class="description">' . esc_html__( 'Seleziona un pacchetto precostituito per popolare mapping Trello, template social e impostazioni blog prima di avviare il Client Wizard.', 'fp-publisher' ) . '</p>';
        echo '<p class="description tts-active-profile">' . sprintf(
            /* translators: %s: current usage profile label. */
            esc_html__( 'Profilo attivo: %s', 'fp-publisher' ),
            esc_html( $this->format_usage_profile_label( $current_profile ) )
        ) . '</p>';
        $quickstart_guide_url = admin_url( 'admin.php?page=fp-publisher-support#quickstart' );
        printf(
            '<p class="description">%s</p>',
            sprintf(
                wp_kses(
                    __( 'Consulta la <a href="%s">Guida Quickstart</a> per esempi dettagliati e checklist complete.', 'fp-publisher' ),
                    array( 'a' => array( 'href' => array() ) )
                ),
                esc_url( $quickstart_guide_url )
            )
        );
        echo '<style>'
            . '.tts-packages-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:24px;margin-top:20px;}'
            . '.tts-package-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;box-shadow:0 1px 1px rgb(0 0 0 / 4%);display:flex;flex-direction:column;}'
            . '.tts-package-head{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;}'
            . '.tts-package-profile{display:inline-flex;align-items:center;font-size:12px;font-weight:600;padding:2px 8px;border-radius:999px;background:#f6f7f7;color:#1d2327;text-transform:uppercase;letter-spacing:0.5px;}'
            . '.tts-package-readiness{border:1px solid #e0e0e0;border-radius:6px;padding:12px;margin:16px 0;background:#f9fafb;}'
            . '.tts-package-readiness.is-warning{border-color:#f56e28;background:#fff4e5;}'
            . '.tts-package-readiness.is-blocked{border-color:#d63638;background:#fde7ea;}'
            . '.tts-package-checklist{margin:10px 0 0;padding-left:0;list-style:none;}'
            . '.tts-package-checklist li{display:flex;gap:8px;align-items:flex-start;margin-bottom:6px;}'
            . '.tts-check-icon{font-size:18px;line-height:1;}'
            . '.tts-check-message{display:block;font-size:13px;color:#50575e;}'
            . '.tts-package-actions{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0 0;}'
            . '.tts-package-preview{margin-top:12px;}'
            . '.tts-package-preview summary{cursor:pointer;font-weight:600;}'
            . '.tts-package-preview[open]{margin-bottom:10px;}'
            . '.tts-package-preview.is-active{border:1px solid #2271b1;border-radius:6px;padding:8px 12px;background:#f0f6fc;}'
            . '.tts-override-toggle{display:block;margin-top:12px;}'
            . '.tts-profile-warning,.tts-readiness-warning{margin:8px 0 0;color:#d63638;}'
            . '.tts-preview-section{margin-top:12px;padding-top:12px;border-top:1px solid #ececec;}'
            . '.tts-preview-section:first-of-type{border-top:none;padding-top:0;margin-top:0;}'
            . '.tts-preview-table{width:100%;border-collapse:collapse;margin-top:8px;}'
            . '.tts-preview-table th,.tts-preview-table td{border-bottom:1px solid #ececec;padding:6px 8px;font-size:13px;text-align:left;vertical-align:top;}'
            . '.tts-preview-table th{background:#f6f7f7;font-weight:600;}'
            . '.tts-preview-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;letter-spacing:.3px;text-transform:uppercase;}'
            . '.tts-preview-status-override{background:#fde7ea;color:#b52738;}'
            . '.tts-preview-status-add{background:#e6f4ea;color:#1b5e20;}'
            . '.tts-preview-status-match{background:#edf2fa;color:#1d3f72;}'
            . '.tts-preview-meta{font-size:12px;color:#50575e;margin-top:4px;}'
            . '.tts-preview-list{list-style:disc;margin:8px 0 0 18px;font-size:13px;color:#1d2327;}'
            . '.tts-package-resources{margin-top:12px;padding:12px;border:1px dashed #c3c4c7;border-radius:6px;background:#f8f9fa;}'
            . '.tts-package-resources strong{display:block;margin-bottom:6px;}'
            . '.tts-package-resources .description{margin:0 0 12px;font-size:13px;color:#50575e;}'
            . '.tts-package-links{display:flex;flex-wrap:wrap;gap:8px;}'
            . '</style>';

        if ( $notice_message ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $notice_type ), esc_html( $notice_message ) );
        }

        if ( $previewed_slug && isset( $packages[ $previewed_slug ] ) ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'Anteprima attiva: scorri il pacchetto selezionato per verificare sovrascritture e nuovi elementi.', 'fp-publisher' ) . '</p></div>';
        }

        if ( ! empty( $active_prefill['package'] ) ) {
            echo '<div class="notice notice-info"><p>' . sprintf(
                /* translators: %s: quickstart package label. */
                esc_html__( 'Pacchetto attivo: %s. I passaggi del wizard verranno precompilati di conseguenza.', 'fp-publisher' ),
                '<strong>' . esc_html( isset( $active_prefill['label'] ) ? $active_prefill['label'] : $active_prefill['package'] ) . '</strong>'
            ) . '</p></div>';
        }

        echo '<div class="tts-packages-grid">';
        foreach ( $packages as $slug => $package ) {
            $package_profile = isset( $package['profile'] ) ? sanitize_key( $package['profile'] ) : 'standard';
            $profile_allowed = $this->usage_profile_allows( $package_profile );
            $profile_label   = $this->format_usage_profile_label( $package_profile );
            $readiness       = $this->assess_quickstart_package_readiness( $package );
            $state_class     = isset( $readiness['status'] ) ? ' is-' . sanitize_html_class( $readiness['status'] ) : '';

            echo '<div class="tts-package-card">';
            echo '<div class="tts-package-head">';
            echo '<h2>' . esc_html( $package['title'] ) . '</h2>';
            echo '<span class="tts-package-profile tts-profile-' . esc_attr( $package_profile ) . '">' . esc_html( $profile_label ) . '</span>';
            echo '</div>';
            echo '<p>' . esc_html( $package['description'] ) . '</p>';

            $preview       = $this->build_quickstart_preview( $package, $current_settings );
            $is_previewed  = ( $previewed_slug === $slug );
            $preview_url   = add_query_arg( array( 'package_preview' => $slug ), $base_quickstart_url );
            $clear_preview = $base_quickstart_url;

            if ( ! empty( $package['channels'] ) ) {
                echo '<p><strong>' . esc_html__( 'Canali inclusi', 'fp-publisher' ) . ':</strong> ' . esc_html( implode( ', ', array_map( 'ucfirst', $package['channels'] ) ) ) . '</p>';
            }

            if ( ! empty( $preview['trello_guidelines'] ) ) {
                echo '<div class="tts-package-section">';
                echo '<strong>' . esc_html__( 'Board Trello consigliata', 'fp-publisher' ) . '</strong>';
                echo '<ul>';
                foreach ( $preview['trello_guidelines'] as $guideline ) {
                    echo '<li>' . esc_html( $guideline ) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }

            $template_url = '';
            if ( isset( $package['trello_template'] ) ) {
                $template_url = $this->get_plugin_asset_url( $package['trello_template'] );
            }

            $journey_url = '';
            if ( isset( $package['journey_doc'] ) ) {
                $journey_doc = $package['journey_doc'];
                if ( is_string( $journey_doc ) ) {
                    $journey_url = $this->get_plugin_asset_url( $journey_doc );
                } elseif ( is_array( $journey_doc ) ) {
                    $journey_path   = isset( $journey_doc['path'] ) ? (string) $journey_doc['path'] : '';
                    $journey_anchor = isset( $journey_doc['anchor'] ) ? (string) $journey_doc['anchor'] : '';
                    $journey_url    = $this->get_plugin_asset_url( $journey_path );
                    if ( $journey_url && '' !== $journey_anchor ) {
                        $journey_anchor = '#' === $journey_anchor[0] ? $journey_anchor : '#' . ltrim( $journey_anchor, '#' );
                        $journey_url   .= $journey_anchor;
                    }
                }
            }

            if ( $template_url || $journey_url ) {
                echo '<div class="tts-package-resources">';
                echo '<strong>' . esc_html__( 'Risorse collegate', 'fp-publisher' ) . '</strong>';
                echo '<p class="description">' . esc_html__( 'Scarica la board di partenza o apri il percorso guidato per completare onboarding e checklist.', 'fp-publisher' ) . '</p>';
                echo '<div class="tts-package-links">';
                if ( $template_url ) {
                    echo '<a class="button button-secondary" href="' . esc_url( $template_url ) . '" download>' . esc_html__( 'Scarica template Trello', 'fp-publisher' ) . '</a>';
                }
                if ( $journey_url ) {
                    echo '<a class="button button-link" href="' . esc_url( $journey_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Apri percorso guidato', 'fp-publisher' ) . '</a>';
                }
                echo '</div>';
                echo '</div>';
            }

            echo '<div class="tts-package-actions">';
            echo '<a class="button button-secondary" href="' . esc_url( $preview_url ) . '">' . esc_html__( 'Anteprima modifiche', 'fp-publisher' ) . '</a>';
            if ( $is_previewed ) {
                echo '<a class="button-link" href="' . esc_url( $clear_preview ) . '">' . esc_html__( 'Nascondi anteprima', 'fp-publisher' ) . '</a>';
            }
            echo '</div>';

            echo '<div class="tts-package-readiness' . esc_attr( $state_class ) . '">';
            $status_label = isset( $readiness['label'] ) ? $readiness['label'] : '';
            if ( $status_label ) {
                echo '<strong>' . esc_html( $status_label ) . '</strong>';
            }

            if ( ! empty( $readiness['summary'] ) ) {
                echo '<p>' . esc_html( $readiness['summary'] ) . '</p>';
            }

            if ( ! empty( $readiness['checks'] ) ) {
                echo '<ul class="tts-package-checklist">';
                foreach ( $readiness['checks'] as $check ) {
                    $icon = '✅';
                    if ( isset( $check['status'] ) ) {
                        if ( 'warning' === $check['status'] ) {
                            $icon = '⚠️';
                        } elseif ( 'blocked' === $check['status'] ) {
                            $icon = '❌';
                        } elseif ( 'skipped' === $check['status'] ) {
                            $icon = '➖';
                        }
                    }

                    $label = isset( $check['label'] ) ? $check['label'] : '';
                    $message = isset( $check['message'] ) ? $check['message'] : '';

                    echo '<li>';
                    echo '<span class="tts-check-icon">' . esc_html( $icon ) . '</span>';
                    echo '<span class="tts-check-content">';
                    if ( '' !== $label ) {
                        echo '<strong>' . esc_html( $label ) . '</strong>';
                    }
                    if ( '' !== $message ) {
                        echo '<span class="tts-check-message">' . esc_html( $message ) . '</span>';
                    }
                    echo '</span>';
                    echo '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';

            $details_classes = 'tts-package-preview';
            if ( $is_previewed ) {
                $details_classes .= ' is-active';
            }

            echo '<details class="' . esc_attr( $details_classes ) . '"' . ( $is_previewed ? ' open' : '' ) . '>';
            echo '<summary>' . esc_html__( 'Anteprima modifiche', 'fp-publisher' ) . '</summary>';

            if ( ! empty( $preview['mapping']['overrides'] ) || ! empty( $preview['mapping']['added'] ) ) {
                echo '<div class="tts-preview-section">';
                echo '<h4>' . esc_html__( 'Mappature Trello proposte', 'fp-publisher' ) . '</h4>';
                if ( $preview['mapping']['current_total'] > 0 && ! empty( $preview['mapping']['overrides'] ) ) {
                    echo '<p class="tts-preview-meta">' . esc_html__( 'Le liste esistenti contrassegnate saranno aggiornate per allinearsi al pacchetto.', 'fp-publisher' ) . '</p>';
                }
                echo '<table class="tts-preview-table"><thead><tr>';
                echo '<th>' . esc_html__( 'Lista', 'fp-publisher' ) . '</th>';
                echo '<th>' . esc_html__( 'Canale', 'fp-publisher' ) . '</th>';
                echo '<th>' . esc_html__( 'Stato', 'fp-publisher' ) . '</th>';
                echo '<th>' . esc_html__( 'Azione', 'fp-publisher' ) . '</th>';
                echo '</tr></thead><tbody>';
                foreach ( $preview['mapping']['overrides'] as $entry ) {
                    $action_label    = __( 'Aggiorna esistente', 'fp-publisher' );
                    $channel_label   = 'all' === $entry['channel'] ? __( 'Tutti', 'fp-publisher' ) : ucfirst( $entry['channel'] );
                    $current_channel = isset( $entry['current_channel'] ) ? $entry['current_channel'] : '';
                    $current_status  = isset( $entry['current_status'] ) ? $entry['current_status'] : '';
                    echo '<tr>';
                    echo '<td>' . esc_html( $entry['list'] ) . '</td>';
                    echo '<td>' . esc_html( $channel_label ) . '</td>';
                    echo '<td>' . esc_html( $entry['status'] ) . '</td>';
                    echo '<td><span class="tts-preview-badge tts-preview-status-override">' . esc_html( $action_label ) . '</span>';
                    if ( '' !== $current_status && $current_status !== $entry['status'] ) {
                        echo '<div class="tts-preview-meta">' . sprintf( esc_html__( 'Stato attuale: %s', 'fp-publisher' ), esc_html( $current_status ) ) . '</div>';
                    }
                    if ( '' !== $current_channel && $current_channel !== $entry['channel'] ) {
                        $current_channel_label = 'all' === $current_channel ? __( 'Tutti', 'fp-publisher' ) : ucfirst( $current_channel );
                        echo '<div class="tts-preview-meta">' . sprintf( esc_html__( 'Canale attuale: %s', 'fp-publisher' ), esc_html( $current_channel_label ) ) . '</div>';
                    }
                    echo '</td></tr>';
                }
                foreach ( $preview['mapping']['added'] as $entry ) {
                    $channel_label = 'all' === $entry['channel'] ? __( 'Tutti', 'fp-publisher' ) : ucfirst( $entry['channel'] );
                    echo '<tr>';
                    echo '<td>' . esc_html( $entry['list'] ) . '</td>';
                    echo '<td>' . esc_html( $channel_label ) . '</td>';
                    echo '<td>' . esc_html( $entry['status'] ) . '</td>';
                    echo '<td><span class="tts-preview-badge tts-preview-status-add">' . esc_html__( 'Nuova mappatura', 'fp-publisher' ) . '</span></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            }

            if ( ! empty( $preview['templates'] ) ) {
                echo '<div class="tts-preview-section">';
                echo '<h4>' . esc_html__( 'Template social', 'fp-publisher' ) . '</h4>';
                echo '<table class="tts-preview-table"><thead><tr>';
                echo '<th>' . esc_html__( 'Canale', 'fp-publisher' ) . '</th>';
                echo '<th>' . esc_html__( 'Nuovo contenuto', 'fp-publisher' ) . '</th>';
                echo '<th>' . esc_html__( 'Valore attuale', 'fp-publisher' ) . '</th>';
                echo '<th>' . esc_html__( 'Azione', 'fp-publisher' ) . '</th>';
                echo '</tr></thead><tbody>';
                foreach ( $preview['templates'] as $entry ) {
                    $action_class = 'tts-preview-status-add';
                    $action_label = __( 'Nuovo', 'fp-publisher' );
                    if ( 'override' === $entry['action'] ) {
                        $action_class = 'tts-preview-status-override';
                        $action_label = __( 'Sovrascrive', 'fp-publisher' );
                    } elseif ( 'match' === $entry['action'] ) {
                        $action_class = 'tts-preview-status-match';
                        $action_label = __( 'Invariato', 'fp-publisher' );
                    }
                    echo '<tr>';
                    echo '<td>' . esc_html( $entry['channel_label'] ) . '</td>';
                    echo '<td><code>' . esc_html( $entry['new'] ) . '</code></td>';
                    echo '<td>' . ( '' !== $entry['current'] ? '<code>' . esc_html( $entry['current'] ) . '</code>' : '—' ) . '</td>';
                    echo '<td><span class="tts-preview-badge ' . esc_attr( $action_class ) . '">' . esc_html( $action_label ) . '</span></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            }

            if ( ! empty( $preview['utm'] ) ) {
                echo '<div class="tts-preview-section">';
                echo '<h4>' . esc_html__( 'Parametri UTM', 'fp-publisher' ) . '</h4>';
                echo '<table class="tts-preview-table"><thead><tr>';
                echo '<th>' . esc_html__( 'Canale', 'fp-publisher' ) . '</th>';
                echo '<th>' . esc_html__( 'Parametro', 'fp-publisher' ) . '</th>';
                echo '<th>' . esc_html__( 'Nuovo valore', 'fp-publisher' ) . '</th>';
                echo '<th>' . esc_html__( 'Valore attuale', 'fp-publisher' ) . '</th>';
                echo '<th>' . esc_html__( 'Azione', 'fp-publisher' ) . '</th>';
                echo '</tr></thead><tbody>';
                foreach ( $preview['utm'] as $entry ) {
                    $action_class = 'tts-preview-status-add';
                    $action_label = __( 'Nuovo', 'fp-publisher' );
                    if ( 'override' === $entry['action'] ) {
                        $action_class = 'tts-preview-status-override';
                        $action_label = __( 'Sovrascrive', 'fp-publisher' );
                    } elseif ( 'match' === $entry['action'] ) {
                        $action_class = 'tts-preview-status-match';
                        $action_label = __( 'Invariato', 'fp-publisher' );
                    }
                    $channel_label = 'all' === $entry['channel'] ? __( 'Tutti', 'fp-publisher' ) : ucfirst( $entry['channel'] );
                    echo '<tr>';
                    echo '<td>' . esc_html( $channel_label ) . '</td>';
                    echo '<td><code>' . esc_html( $entry['param'] ) . '</code></td>';
                    echo '<td><code>' . esc_html( $entry['new'] ) . '</code></td>';
                    $current_value = '' !== $entry['current'] ? '<code>' . esc_html( $entry['current'] ) . '</code>' : '—';
                    echo '<td>' . $current_value . '</td>';
                    echo '<td><span class="tts-preview-badge ' . esc_attr( $action_class ) . '">' . esc_html( $action_label ) . '</span></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            }

            if ( ! empty( $preview['blog'] ) ) {
                echo '<div class="tts-preview-section">';
                echo '<h4>' . esc_html__( 'Prefill blog', 'fp-publisher' ) . '</h4>';
                echo '<ul class="tts-preview-list">';
                foreach ( $preview['blog'] as $key => $value ) {
                    echo '<li><strong>' . esc_html( $key ) . '</strong>: ' . esc_html( $value ) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }

            if ( ! empty( $preview['notes'] ) ) {
                echo '<div class="tts-preview-section"><em>' . esc_html( $preview['notes'] ) . '</em></div>';
            }

            echo '</details>';

            echo '<form method="post" class="tts-package-form">';
            wp_nonce_field( 'tts_apply_quickstart' );
            echo '<input type="hidden" name="tts_package_slug" value="' . esc_attr( $slug ) . '" />';
            echo '<label class="tts-override-toggle"><input type="checkbox" name="tts_override_existing" value="1" /> ' . esc_html__( 'Sovrascrivi impostazioni esistenti (template, mapping, UTM)', 'fp-publisher' ) . '</label>';

            $button_attrs = array();
            if ( 'blocked' === $readiness['status'] || ! $profile_allowed ) {
                $button_attrs[] = 'disabled="disabled"';
            }

            $button_label = esc_html__( 'Applica pacchetto', 'fp-publisher' );
            if ( ! $profile_allowed ) {
                echo '<p class="description tts-profile-warning">' . sprintf(
                    /* translators: %s: required usage profile label. */
                    esc_html__( 'Richiede profilo %s o superiore.', 'fp-publisher' ),
                    esc_html( $profile_label )
                ) . '</p>';
            }

            if ( 'blocked' === $readiness['status'] ) {
                echo '<p class="description tts-readiness-warning">' . esc_html__( 'Completa i requisiti contrassegnati in rosso prima di applicare il preset.', 'fp-publisher' ) . '</p>';
            }

            echo '<p><button type="submit" name="tts_apply_package" class="button button-primary" ' . implode( ' ', $button_attrs ) . '>' . $button_label . '</button></p>';
            echo '</form>';

            echo '</div>';
        }
        echo '</div>';

        echo '<p style="margin-top:30px;">' . esc_html__( "Dopo l'applicazione apri il Client Wizard per completare la checklist guidata.", 'fp-publisher' ) . '</p>';

        echo '</div>';
    }

    /**
     * Expose quickstart packages for programmatic contexts (CLI/tests).
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_quickstart_package_catalog() {
        $packages = $this->get_quickstart_packages();
        $catalog  = array();

        foreach ( $packages as $slug => $package ) {
            $catalog[] = array(
                'slug'        => $slug,
                'title'       => isset( $package['title'] ) ? (string) $package['title'] : $slug,
                'profile'     => isset( $package['profile'] ) ? sanitize_key( $package['profile'] ) : 'standard',
                'description' => isset( $package['description'] ) ? (string) $package['description'] : '',
            );
        }

        return $catalog;
    }

    /**
     * Return readiness information and preview details for a quickstart package.
     *
     * @param string               $slug     Package identifier.
     * @param array<string, mixed> $settings Optional settings snapshot to compare.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function get_quickstart_package_overview( $slug, $settings = null ) {
        $slug     = sanitize_key( $slug );
        $packages = $this->get_quickstart_packages();

        if ( ! isset( $packages[ $slug ] ) ) {
            return new WP_Error( 'tts-invalid-package', __( 'Pacchetto non disponibile.', 'fp-publisher' ) );
        }

        $package = $packages[ $slug ];
        $profile = isset( $package['profile'] ) ? sanitize_key( $package['profile'] ) : 'standard';

        if ( null === $settings ) {
            $settings = tsap_get_option( 'tts_settings', array() );
        }

        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $readiness = $this->assess_quickstart_package_readiness( $package );
        $preview   = $this->build_quickstart_preview( $package, $settings );

        return array(
            'slug'             => $slug,
            'definition'       => $package,
            'readiness'        => $readiness,
            'preview'          => $preview,
            'required_profile' => $profile,
            'profile_allowed'  => $this->usage_profile_allows( $profile ),
            'active_profile'   => $this->get_usage_profile(),
        );
    }

    /**
     * Return predefined quickstart package definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    private function get_quickstart_packages() {
        return array(
            'social_starter'  => array(
                'title'            => __( 'Starter Social', 'fp-publisher' ),
                'description'      => __( 'Flusso base per team piccoli con mapping Trello lineare e template social immediati.', 'fp-publisher' ),
                'profile'          => 'standard',
                'channels'         => array( 'facebook', 'instagram' ),
                'blog_settings'    => 'post_type:post|post_status:draft|author_id:1|language:it|seo_title={title} | canonical_url:{url}',
                'column_mapping'   => array(
                    array( 'list' => 'Idee', 'status' => 'draft', 'channel' => 'all' ),
                    array( 'list' => 'In approvazione', 'status' => 'review', 'channel' => 'all' ),
                    array( 'list' => 'Pronto', 'status' => 'scheduled', 'channel' => 'all' ),
                    array( 'list' => 'Pubblicato', 'status' => 'published', 'channel' => 'all' ),
                ),
                'templates'        => array(
                    'facebook_template'  => '{title} {url} #brand',
                    'instagram_template' => '{title}\n\n{hashtags}',
                ),
                'utm'              => array(
                    'facebook'  => array(
                        'utm_source'   => 'facebook',
                        'utm_medium'   => 'social',
                        'utm_campaign' => 'editoriale',
                    ),
                    'instagram' => array(
                        'utm_source'   => 'instagram',
                        'utm_medium'   => 'social',
                        'utm_campaign' => 'editoriale',
                    ),
                ),
                'trello_guidelines' => array(
                    __( 'Liste suggerite: Idee → In approvazione → Pronto → Pubblicato.', 'fp-publisher' ),
                    __( 'Usa etichette Trello per distinguere i canali Facebook e Instagram.', 'fp-publisher' ),
                ),
                'trello_template'   => 'assets/quickstart/social-starter.json',
                'journey_doc'       => array(
                    'path'   => 'docs/journeys/client-onboarding.md',
                    'anchor' => '#starter-social',
                ),
                'notes'            => __( 'Ideale per avviare nuovi brand senza complicare il flusso.', 'fp-publisher' ),
            ),
            'editorial_suite' => array(
                'title'            => __( 'Editorial Suite', 'fp-publisher' ),
                'description'      => __( 'Preset completo per team editoriali con blog, YouTube e short form video.', 'fp-publisher' ),
                'profile'          => 'advanced',
                'channels'         => array( 'facebook', 'instagram', 'youtube', 'tiktok' ),
                'blog_settings'    => 'post_type:post|post_status:pending|author_id:2|category_id:3|language:it|focus_keyword:{title}|seo_title={title} | meta_description={excerpt}',
                'column_mapping'   => array(
                    array( 'list' => 'Briefing', 'status' => 'draft', 'channel' => 'all' ),
                    array( 'list' => 'Produzione', 'status' => 'in-progress', 'channel' => 'all' ),
                    array( 'list' => 'Revisione', 'status' => 'review', 'channel' => 'all' ),
                    array( 'list' => 'Pronto Social', 'status' => 'scheduled', 'channel' => 'social' ),
                    array( 'list' => 'Pubblicato', 'status' => 'published', 'channel' => 'all' ),
                ),
                'templates'        => array(
                    'facebook_template'  => '{title} → {url}\n#contentcalendar',
                    'instagram_template' => '{title}\n\n📅 {due}\n{hashtags}',
                    'youtube_template'   => '{title} | {due} | {url}',
                    'tiktok_template'    => '{title} 🎬 {due}',
                ),
                'utm'              => array(
                    'facebook'  => array(
                        'utm_source'   => 'facebook',
                        'utm_medium'   => 'social',
                        'utm_campaign' => 'editorial-suite',
                    ),
                    'instagram' => array(
                        'utm_source'   => 'instagram',
                        'utm_medium'   => 'social',
                        'utm_campaign' => 'editorial-suite',
                    ),
                    'youtube'   => array(
                        'utm_source'   => 'youtube',
                        'utm_medium'   => 'video',
                        'utm_campaign' => 'editorial-suite',
                    ),
                    'tiktok'    => array(
                        'utm_source'   => 'tiktok',
                        'utm_medium'   => 'video',
                        'utm_campaign' => 'editorial-suite',
                    ),
                ),
                'trello_guidelines' => array(
                    __( 'Aggiungi checklist Trello per script, grafiche e revisione SEO.', 'fp-publisher' ),
                    __( 'Allinea le scadenze con il calendario editoriale di FP Publisher.', 'fp-publisher' ),
                ),
                'trello_template'   => 'assets/quickstart/editorial-suite.json',
                'journey_doc'       => array(
                    'path'   => 'docs/journeys/client-onboarding.md',
                    'anchor' => '#editorial-suite',
                ),
                'notes'            => __( 'Perfetto per contenuti multi-canale con controllo qualità centralizzato.', 'fp-publisher' ),
            ),
            'enterprise_control' => array(
                'title'            => __( 'Enterprise Control', 'fp-publisher' ),
                'description'      => __( 'Configurazione per organizzazioni complesse con audit trail e fasi di verifica aggiuntive.', 'fp-publisher' ),
                'profile'          => 'enterprise',
                'channels'         => array( 'facebook', 'instagram', 'youtube', 'tiktok' ),
                'blog_settings'    => 'post_type:post|post_status:pending|author_id:5|language:it|seo_title={title} | canonical_url:{url}|meta_description={excerpt}',
                'column_mapping'   => array(
                    array( 'list' => 'Intake', 'status' => 'draft', 'channel' => 'all' ),
                    array( 'list' => 'Quality Assurance', 'status' => 'review', 'channel' => 'all' ),
                    array( 'list' => 'Security Check', 'status' => 'review', 'channel' => 'all' ),
                    array( 'list' => 'Ready to Launch', 'status' => 'scheduled', 'channel' => 'all' ),
                    array( 'list' => 'Live + Audit', 'status' => 'published', 'channel' => 'all' ),
                ),
                'templates'        => array(
                    'facebook_template'  => '[APPROVED] {title} {url} #{client}',
                    'instagram_template' => '{title}\n\nTeam: {owner}\n{hashtags}',
                    'youtube_template'   => '[Release] {title} | Owner: {owner}',
                    'tiktok_template'    => '{title} ⚙️ QA:{qa_owner}',
                ),
                'utm'              => array(
                    'facebook'  => array(
                        'utm_source'   => 'facebook',
                        'utm_medium'   => 'social',
                        'utm_campaign' => 'enterprise-control',
                    ),
                    'instagram' => array(
                        'utm_source'   => 'instagram',
                        'utm_medium'   => 'social',
                        'utm_campaign' => 'enterprise-control',
                    ),
                    'youtube'   => array(
                        'utm_source'   => 'youtube',
                        'utm_medium'   => 'video',
                        'utm_campaign' => 'enterprise-control',
                    ),
                    'tiktok'    => array(
                        'utm_source'   => 'tiktok',
                        'utm_medium'   => 'video',
                        'utm_campaign' => 'enterprise-control',
                    ),
                ),
                'trello_guidelines' => array(
                    __( 'Prevedi una card checklist per i controlli legali e privacy prima della pubblicazione.', 'fp-publisher' ),
                    __( "Attiva l'audit Trello e sincronizza i log con FP Publisher per avere una cronologia completa.", 'fp-publisher' ),
                ),
                'trello_template'   => 'assets/quickstart/enterprise-control.json',
                'journey_doc'       => array(
                    'path'   => 'docs/journeys/client-onboarding.md',
                    'anchor' => '#enterprise-control',
                ),
                'notes'            => __( 'Pensato per chi necessita di visibilità completa su approvatori e compliance.', 'fp-publisher' ),
            ),
        );
    }

    /**
     * Build preview information for a quickstart package versus current settings.
     *
     * @param array<string, mixed> $package  Quickstart definition.
     * @param array<string, mixed> $settings Current plugin settings.
     * @return array<string, mixed>
     */
    private function build_quickstart_preview( array $package, array $settings ) {
        $settings = is_array( $settings ) ? $settings : array();

        $preview = array(
            'mapping'           => array(
                'added'         => array(),
                'overrides'     => array(),
                'current_total' => 0,
            ),
            'templates'         => array(),
            'utm'               => array(),
            'blog'              => array(),
            'notes'             => isset( $package['notes'] ) ? (string) $package['notes'] : '',
            'trello_guidelines' => array(),
        );

        $current_mapping = array();
        if ( isset( $settings['column_mapping'] ) ) {
            $decoded = $settings['column_mapping'];
            if ( is_string( $decoded ) ) {
                $decoded = json_decode( $decoded, true );
            }
            if ( is_array( $decoded ) ) {
                foreach ( $decoded as $entry ) {
                    if ( ! is_array( $entry ) ) {
                        continue;
                    }
                    $item = array(
                        'list'    => isset( $entry['list'] ) ? (string) $entry['list'] : '',
                        'channel' => isset( $entry['channel'] ) ? (string) $entry['channel'] : 'all',
                        'status'  => isset( $entry['status'] ) ? (string) $entry['status'] : '',
                    );
                    if ( '' === $item['list'] ) {
                        continue;
                    }
                    if ( '' === $item['channel'] ) {
                        $item['channel'] = 'all';
                    }
                    $current_mapping[] = $item;
                }
            }
        }

        $preview['mapping']['current_total'] = count( $current_mapping );

        $current_index = array();
        foreach ( $current_mapping as $item ) {
            $key                 = strtolower( $item['list'] ) . '|' . strtolower( $item['channel'] );
            $current_index[ $key ] = $item;
        }

        if ( isset( $package['column_mapping'] ) && is_array( $package['column_mapping'] ) ) {
            foreach ( $package['column_mapping'] as $entry ) {
                if ( ! is_array( $entry ) ) {
                    continue;
                }
                $item = array(
                    'list'    => isset( $entry['list'] ) ? (string) $entry['list'] : '',
                    'channel' => isset( $entry['channel'] ) ? (string) $entry['channel'] : 'all',
                    'status'  => isset( $entry['status'] ) ? (string) $entry['status'] : '',
                );
                if ( '' === $item['list'] ) {
                    continue;
                }
                if ( '' === $item['channel'] ) {
                    $item['channel'] = 'all';
                }

                $key = strtolower( $item['list'] ) . '|' . strtolower( $item['channel'] );
                if ( isset( $current_index[ $key ] ) ) {
                    $current_item              = $current_index[ $key ];
                    $item['current_channel']   = isset( $current_item['channel'] ) ? (string) $current_item['channel'] : '';
                    $item['current_status']    = isset( $current_item['status'] ) ? (string) $current_item['status'] : '';
                    $preview['mapping']['overrides'][] = $item;
                } else {
                    $preview['mapping']['added'][] = $item;
                }
            }
        }

        if ( isset( $package['templates'] ) && is_array( $package['templates'] ) ) {
            foreach ( $package['templates'] as $key => $template ) {
                $key            = sanitize_key( $key );
                $new_value      = (string) $template;
                $current_value  = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
                $action         = 'add';
                if ( '' !== trim( $current_value ) ) {
                    $action = trim( $current_value ) === trim( $new_value ) ? 'match' : 'override';
                }
                $channel_slug  = str_replace( '_template', '', $key );
                $channel_label = ucwords( str_replace( '_', ' ', $channel_slug ) );

                $preview['templates'][] = array(
                    'key'           => $key,
                    'channel_label' => $channel_label,
                    'new'           => $new_value,
                    'current'       => $current_value,
                    'action'        => $action,
                );
            }
        }

        if ( isset( $package['utm'] ) && is_array( $package['utm'] ) ) {
            foreach ( $package['utm'] as $channel => $params ) {
                $channel = sanitize_key( $channel );
                if ( ! is_array( $params ) ) {
                    continue;
                }
                foreach ( $params as $param_key => $param_value ) {
                    $param_key     = sanitize_key( $param_key );
                    $new_value     = (string) $param_value;
                    $current_value = isset( $settings['utm'][ $channel ][ $param_key ] ) ? (string) $settings['utm'][ $channel ][ $param_key ] : '';
                    $action        = 'add';
                    if ( '' !== trim( $current_value ) ) {
                        $action = trim( $current_value ) === trim( $new_value ) ? 'match' : 'override';
                    }

                    $preview['utm'][] = array(
                        'channel' => $channel,
                        'param'   => $param_key,
                        'new'     => $new_value,
                        'current' => $current_value,
                        'action'  => $action,
                    );
                }
            }
        }

        if ( ! empty( $package['blog_settings'] ) ) {
            $preview['blog'] = $this->parse_quickstart_blog_settings( (string) $package['blog_settings'] );
        }

        if ( isset( $package['trello_guidelines'] ) && is_array( $package['trello_guidelines'] ) ) {
            $preview['trello_guidelines'] = array_map( 'sanitize_text_field', $package['trello_guidelines'] );
        }

        return $preview;
    }

    /**
     * Apply quickstart package presets.
     *
     * @param string $slug     Package slug.
     * @param bool   $override Whether to override existing settings.
     * @return array|WP_Error
     */
    private function apply_quickstart_package( $slug, $override = false ) {
        $packages = $this->get_quickstart_packages();

        if ( ! isset( $packages[ $slug ] ) ) {
            return new WP_Error( 'tts-invalid-package', __( 'Pacchetto non disponibile.', 'fp-publisher' ) );
        }

        $package         = $packages[ $slug ];
        $package_profile = isset( $package['profile'] ) ? sanitize_key( $package['profile'] ) : 'standard';

        if ( ! $this->usage_profile_allows( $package_profile ) ) {
            return new WP_Error(
                'tts-package-profile-mismatch',
                __( 'Il pacchetto richiede un profilo di utilizzo più avanzato rispetto a quello attivo.', 'fp-publisher' )
            );
        }

        $readiness = $this->assess_quickstart_package_readiness( $package );
        if ( isset( $readiness['status'] ) && 'blocked' === $readiness['status'] ) {
            return new WP_Error(
                'tts-package-not-ready',
                __( 'Completa prima i prerequisiti critici evidenziati nella validazione del pacchetto.', 'fp-publisher' )
            );
        }

        $settings = tsap_get_option( 'tts_settings', array() );

        if ( isset( $package['column_mapping'] ) ) {
            $encoded = wp_json_encode( $package['column_mapping'] );
            if ( $override || empty( $settings['column_mapping'] ) ) {
                $settings['column_mapping'] = $encoded;
            }
        }

        if ( isset( $package['templates'] ) && is_array( $package['templates'] ) ) {
            foreach ( $package['templates'] as $key => $value ) {
                if ( $override || empty( $settings[ $key ] ) ) {
                    $settings[ $key ] = sanitize_text_field( $value );
                }
            }
        }

        if ( isset( $package['utm'] ) && is_array( $package['utm'] ) ) {
            foreach ( $package['utm'] as $channel => $params ) {
                foreach ( $params as $param_key => $param_value ) {
                    if ( $override || empty( $settings['utm'][ $channel ][ $param_key ] ) ) {
                        $settings['utm'][ $channel ][ $param_key ] = sanitize_text_field( $param_value );
                    }
                }
            }
        }

        tsap_update_option( 'tts_settings', $settings );
        tsap_update_option( 'tts_quickstart_last_package', array(
            'slug'       => $slug,
            'applied_at' => current_time( 'mysql' ),
        ) );

        if ( ! session_id() ) {
            session_start();
        }

        $_SESSION['tts_quickstart_prefill'] = array(
            'package'        => $slug,
            'label'          => $package['title'],
            'channels'       => isset( $package['channels'] ) ? array_map( 'sanitize_key', (array) $package['channels'] ) : array(),
            'blog_settings'  => isset( $package['blog_settings'] ) ? sanitize_textarea_field( $package['blog_settings'] ) : '',
            'trello_guidelines' => isset( $package['trello_guidelines'] ) ? array_map( 'sanitize_text_field', (array) $package['trello_guidelines'] ) : array(),
            'column_mapping' => isset( $package['column_mapping'] ) ? $package['column_mapping'] : array(),
            'profile'        => $package_profile,
            'validation'     => $readiness,
        );

        $message = sprintf(
            /* translators: %s: quickstart package title. */
            __( 'Pacchetto "%s" applicato con successo. Apri il Client Wizard per completare la configurazione guidata.', 'fp-publisher' ),
            $package['title']
        );

        return array(
            'message' => $message,
            'package' => $package,
        );
    }

    /**
     * Render onboarding checklist for the wizard.
     *
     * @param array $state          Progress state.
     * @param int   $step           Current step number.
     * @param array $prefill        Prefill metadata from quickstart packages.
     * @param bool  $trello_enabled Whether Trello step is enabled.
     * @param array $context        Runtime context (tokens, channels, validation hints).
     */
    private function render_client_wizard_checklist( array $state, $step, array $prefill, $trello_enabled, array $context = array() ) {
        $defaults = array(
            'trello_key'      => '',
            'trello_token'    => '',
            'trello_board'    => '',
            'channels'        => array(),
            'blog_settings'   => '',
            'tokens'          => array(),
            'mapping_count'   => 0,
            'prefill_package' => '',
            'validation'      => array(),
            'usage_profile'   => $this->get_usage_profile(),
        );

        $context = wp_parse_args( $context, $defaults );

        $validation_checks = array();
        if ( isset( $context['validation']['checks'] ) && is_array( $context['validation']['checks'] ) ) {
            foreach ( $context['validation']['checks'] as $check ) {
                $scope = isset( $check['scope'] ) ? $check['scope'] : 'general';
                if ( ! isset( $validation_checks[ $scope ] ) ) {
                    $validation_checks[ $scope ] = array();
                }
                $validation_checks[ $scope ][] = $check;
            }
        }
        $validation_summary = isset( $context['validation']['summary'] ) ? $context['validation']['summary'] : '';

        $trello_status = array(
            'status'  => 'pending',
            'message' => __( 'Inserisci API key e token Trello per iniziare.', 'fp-publisher' ),
        );

        if ( ! $trello_enabled ) {
            $trello_status = array(
                'status'  => 'skipped',
                'message' => __( 'Trello è disattivato nelle impostazioni generali.', 'fp-publisher' ),
            );
        } else {
            $has_key    = '' !== trim( (string) $context['trello_key'] );
            $has_token  = '' !== trim( (string) $context['trello_token'] );
            $has_board  = '' !== trim( (string) $context['trello_board'] );

            if ( ! $has_key || ! $has_token ) {
                $trello_status = array(
                    'status'  => 'blocked',
                    'message' => __( 'Aggiungi API key e token Trello per proseguire.', 'fp-publisher' ),
                );
            } elseif ( ! $has_board ) {
                $trello_status = array(
                    'status'  => 'warning',
                    'message' => __( 'Seleziona la board Trello da monitorare.', 'fp-publisher' ),
                );
            } else {
                $trello_status = array(
                    'status'  => ! empty( $state['trello'] ) ? 'complete' : 'pending',
                    'message' => __( 'Credenziali Trello impostate, passa alla scelta dei canali.', 'fp-publisher' ),
                );
            }
        }

        $selected_channels = array_filter( array_map( 'sanitize_key', (array) $context['channels'] ) );
        $connected_channels = array();
        foreach ( $selected_channels as $selected_channel ) {
            $token_value = '';
            if ( isset( $context['tokens'][ $selected_channel ] ) ) {
                $token_value = $context['tokens'][ $selected_channel ];
            }
            if ( '' !== trim( (string) $token_value ) ) {
                $connected_channels[] = $selected_channel;
            }
        }

        if ( empty( $selected_channels ) ) {
            $channel_status = array(
                'status'  => 'pending',
                'message' => __( 'Seleziona almeno un canale da collegare.', 'fp-publisher' ),
            );
        } elseif ( count( $connected_channels ) === count( $selected_channels ) ) {
            $channel_status = array(
                'status'  => 'complete',
                'message' => __( 'Tutti i canali selezionati risultano autorizzati.', 'fp-publisher' ),
            );
        } elseif ( ! empty( $connected_channels ) ) {
            $channel_status = array(
                'status'  => 'warning',
                'message' => __( 'Autorizza anche i canali mancanti per evitare lacune di pubblicazione.', 'fp-publisher' ),
            );
        } else {
            $channel_status = array(
                'status'  => 'pending',
                'message' => __( "Completa l'OAuth dal Client Wizard per almeno un canale.", 'fp-publisher' ),
            );
        }

        if ( isset( $validation_checks['channels'] ) ) {
            foreach ( $validation_checks['channels'] as $warning ) {
                if ( 'blocked' === $warning['status'] ) {
                    $channel_status['status'] = 'blocked';
                    break;
                }
                if ( 'warning' === $warning['status'] && 'blocked' !== $channel_status['status'] ) {
                    $channel_status['status'] = 'warning';
                }
            }
        }

        if ( ! $trello_enabled ) {
            $mapping_status = array(
                'status'  => 'skipped',
                'message' => __( 'La mappatura Trello è facoltativa perché la sincronizzazione è disattivata.', 'fp-publisher' ),
            );
        } else {
            $mapping_status = array(
                'status'  => ( ! empty( $state['mapping'] ) || $context['mapping_count'] > 0 ) ? 'complete' : 'pending',
                'message' => __( 'Associa almeno una lista Trello ai canali social.', 'fp-publisher' ),
            );
        }

        $has_blog_settings = '' !== trim( (string) $context['blog_settings'] );
        $blog_status = array(
            'status'  => $has_blog_settings ? 'complete' : 'pending',
            'message' => $has_blog_settings
                ? __( 'Impostazioni blog presenti: puoi verificare i campi SEO nel riepilogo.', 'fp-publisher' )
                : __( 'Compila le impostazioni blog per generare contenuti WordPress.', 'fp-publisher' ),
        );

        if ( isset( $validation_checks['blog'] ) ) {
            foreach ( $validation_checks['blog'] as $warning ) {
                if ( 'blocked' === $warning['status'] ) {
                    $blog_status['status'] = 'blocked';
                    $blog_status['message'] = $warning['message'];
                    break;
                }
                if ( 'warning' === $warning['status'] && 'blocked' !== $blog_status['status'] ) {
                    $blog_status['status'] = 'warning';
                    $blog_status['message'] = $warning['message'];
                }
            }
        }

        $review_status = array(
            'status'  => ! empty( $state['review'] ) ? 'complete' : 'pending',
            'message' => ! empty( $state['review'] )
                ? __( 'Riepilogo verificato: pronto alla creazione del cliente.', 'fp-publisher' )
                : __( 'Controlla i dati raccolti e conferma per creare il cliente.', 'fp-publisher' ),
        );

        $items = array(
            'trello'   => array(
                'label'       => __( 'Connessione Trello', 'fp-publisher' ),
                'description' => $trello_enabled
                    ? __( 'Inserisci API key e token e scegli la board da monitorare.', 'fp-publisher' )
                    : __( 'Trello è disabilitato nelle impostazioni generali.', 'fp-publisher' ),
                'status'      => $trello_status['status'],
                'message'     => $trello_status['message'],
                'hints'       => isset( $validation_checks['trello'] ) ? $validation_checks['trello'] : array(),
                'actions'     => $trello_enabled ? array(
                    array(
                        'label' => __( 'Gestisci integrazione Trello', 'fp-publisher' ),
                        'url'   => admin_url( 'admin.php?page=fp-publisher-connections' ),
                    ),
                ) : array(),
                'docs'        => array(
                    array(
                        'label' => __( 'Apri la Guida Quickstart', 'fp-publisher' ),
                        'url'   => admin_url( 'admin.php?page=fp-publisher-support#quickstart' ),
                    ),
                ),
            ),
            'channels' => array(
                'label'       => __( 'Selezione canali', 'fp-publisher' ),
                'description' => __( 'Indica i social da collegare e verifica le app OAuth.', 'fp-publisher' ),
                'status'      => $channel_status['status'],
                'message'     => $channel_status['message'],
                'hints'       => isset( $validation_checks['channels'] ) ? $validation_checks['channels'] : array(),
                'actions'     => array(
                    array(
                        'label' => __( 'Apri Connessioni social', 'fp-publisher' ),
                        'url'   => admin_url( 'admin.php?page=fp-publisher-connections' ),
                    ),
                    array(
                        'label' => __( 'Testa le connessioni', 'fp-publisher' ),
                        'url'   => admin_url( 'admin.php?page=fp-publisher-connection-diagnostics' ),
                    ),
                ),
                'docs'        => array(
                    array(
                        'label' => __( 'Leggi la guida alle app social', 'fp-publisher' ),
                        'url'   => admin_url( 'admin.php?page=fp-publisher-support#overview' ),
                    ),
                ),
            ),
            'mapping'  => array(
                'label'       => __( 'Mappatura Trello → canali', 'fp-publisher' ),
                'description' => __( 'Associa le liste Trello ai canali social o al blog.', 'fp-publisher' ),
                'status'      => $mapping_status['status'],
                'message'     => $mapping_status['message'],
                'hints'       => isset( $validation_checks['trello'] ) ? $validation_checks['trello'] : array(),
                'actions'     => $trello_enabled ? array(
                    array(
                        'label' => __( 'Apri passaggio mappatura', 'fp-publisher' ),
                        'url'   => add_query_arg( array( 'step' => 3 ), admin_url( 'admin.php?page=fp-publisher-client-wizard' ) ),
                    ),
                ) : array(),
                'docs'        => array(
                    array(
                        'label' => __( 'Suggerimenti di mappatura', 'fp-publisher' ),
                        'url'   => admin_url( 'admin.php?page=fp-publisher-support#quickstart' ),
                    ),
                ),
            ),
            'blog'     => array(
                'label'       => __( 'Impostazioni blog', 'fp-publisher' ),
                'description' => __( 'Definisci post type, stato, SEO e categorie del blog collegato.', 'fp-publisher' ),
                'status'      => $blog_status['status'],
                'message'     => $blog_status['message'],
                'hints'       => isset( $validation_checks['blog'] ) ? $validation_checks['blog'] : array(),
                'actions'     => array(),
                'docs'        => array(
                    array(
                        'label' => __( 'Configura blog & SEO', 'fp-publisher' ),
                        'url'   => admin_url( 'admin.php?page=fp-publisher-support#blog' ),
                    ),
                ),
            ),
            'review'   => array(
                'label'       => __( 'Riepilogo finale', 'fp-publisher' ),
                'description' => __( 'Conferma i dati e crea il cliente pronto alla pubblicazione.', 'fp-publisher' ),
                'status'      => $review_status['status'],
                'message'     => $review_status['message'],
                'hints'       => array(),
                'actions'     => array(),
                'docs'        => array(
                    array(
                        'label' => __( 'Controlla la checklist finale', 'fp-publisher' ),
                        'url'   => admin_url( 'admin.php?page=fp-publisher-support#quickstart' ),
                    ),
                ),
            ),
        );

        $status_icons = array(
            'complete' => '✅',
            'pending'  => '⬜',
            'warning'  => '⚠️',
            'blocked'  => '❌',
            'skipped'  => '➖',
        );

        $progress_items = array_filter(
            $items,
            static function ( $item ) {
                return 'skipped' !== $item['status'];
            }
        );
        $total_steps     = count( $progress_items );
        $completed_steps = count(
            array_filter(
                $progress_items,
                static function ( $item ) {
                    return 'complete' === $item['status'];
                }
            )
        );
        $progress_percent = $total_steps > 0 ? round( ( $completed_steps / $total_steps ) * 100 ) : 0;

        echo '<div class="tts-wizard-checklist">';
        echo '<h2>' . esc_html__( 'Checklist onboarding', 'fp-publisher' ) . '</h2>';
        echo '<p class="tts-checklist-progress">' . esc_html( sprintf( __( 'Avanzamento: %1$s di %2$s passaggi completati (%3$s%%).', 'fp-publisher' ), $completed_steps, $total_steps, $progress_percent ) ) . '</p>';

        if ( ! empty( $prefill['label'] ) ) {
            echo '<p class="tts-checklist-prefill">' . sprintf(
                /* translators: %s: quickstart package label. */
                esc_html__( 'Preset attivo: %s', 'fp-publisher' ),
                '<strong>' . esc_html( $prefill['label'] ) . '</strong>'
            ) . '</p>';
        }

        echo '<ul class="tts-checklist-items">';
        foreach ( $items as $key => $item ) {
            $status = $item['status'];
            $icon   = isset( $status_icons[ $status ] ) ? $status_icons[ $status ] : '⬜';
            $classes = array( 'tts-checklist-item', 'status-' . $status );
            if ( 'complete' === $status ) {
                $classes[] = 'is-complete';
            }

            echo '<li class="' . esc_attr( implode( ' ', $classes ) ) . '">';
            echo '<span class="tts-checklist-icon">' . esc_html( $icon ) . '</span>';
            echo '<span class="tts-checklist-content">';
            echo '<strong>' . esc_html( $item['label'] ) . '</strong>';
            echo '<span class="tts-checklist-description">' . esc_html( $item['description'] ) . '</span>';
            if ( '' !== $item['message'] ) {
                echo '<span class="tts-checklist-message">' . esc_html( $item['message'] ) . '</span>';
            }

            if ( ! empty( $item['hints'] ) ) {
                echo '<ul class="tts-checklist-hints">';
                foreach ( $item['hints'] as $hint ) {
                    $hint_status = isset( $hint['status'] ) && isset( $status_icons[ $hint['status'] ] ) ? $status_icons[ $hint['status'] ] : '•';
                    $hint_label  = isset( $hint['label'] ) ? $hint['label'] : '';
                    $hint_msg    = isset( $hint['message'] ) ? $hint['message'] : '';
                    echo '<li>';
                    echo '<span class="tts-checklist-hint-icon">' . esc_html( $hint_status ) . '</span>';
                    echo '<span class="tts-checklist-hint-text">';
                    if ( '' !== $hint_label ) {
                        echo '<strong>' . esc_html( $hint_label ) . '</strong> ';
                    }
                    if ( '' !== $hint_msg ) {
                        echo esc_html( $hint_msg );
                    }
                    echo '</span>';
                    echo '</li>';
                }
                echo '</ul>';
            }

            if ( ! empty( $item['actions'] ) ) {
                echo '<div class="tts-checklist-links">';
                foreach ( $item['actions'] as $action ) {
                    $label = isset( $action['label'] ) ? $action['label'] : '';
                    $url   = isset( $action['url'] ) ? $action['url'] : '';
                    if ( '' === $label || '' === $url ) {
                        continue;
                    }
                    echo '<a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
                }
                echo '</div>';
            }

            if ( ! empty( $item['docs'] ) ) {
                echo '<div class="tts-checklist-links tts-checklist-docs">';
                foreach ( $item['docs'] as $doc ) {
                    $doc_label = isset( $doc['label'] ) ? $doc['label'] : '';
                    $doc_url   = isset( $doc['url'] ) ? $doc['url'] : '';
                    if ( '' === $doc_label || '' === $doc_url ) {
                        continue;
                    }
                    echo '<a class="button-link" href="' . esc_url( $doc_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $doc_label ) . '</a>';
                }
                echo '</div>';
            }

            echo '</span>';
            echo '</li>';
        }
        echo '</ul>';

        echo '<form method="post" class="tts-checklist-actions">';
        wp_nonce_field( 'tts_reset_wizard', 'tts_reset_wizard_nonce' );
        echo '<input type="hidden" name="tts_reset_wizard_progress" value="1" />';
        echo '<button type="submit" class="button">' . esc_html__( 'Azzera checklist', 'fp-publisher' ) . '</button>';
        echo '</form>';

        if ( $validation_summary ) {
            echo '<p class="tts-validation-summary">' . esc_html( $validation_summary ) . '</p>';
        }

        if ( ! empty( $prefill['trello_guidelines'] ) && is_array( $prefill['trello_guidelines'] ) ) {
            echo '<div class="tts-checklist-guidelines">';
            echo '<strong>' . esc_html__( 'Suggerimenti dal pacchetto selezionato', 'fp-publisher' ) . '</strong>';
            echo '<ul>';
            foreach ( $prefill['trello_guidelines'] as $guideline ) {
                echo '<li>' . esc_html( $guideline ) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        echo '</div>';
    }
    /**
     * Render the client wizard page.
     */
    public function tts_render_client_wizard() {
        if ( ! session_id() ) {
            session_start();
        }

        $trello_enabled = (bool) tsap_get_option( 'tts_trello_enabled', 1 );

        $post_step = 0;
        if ( isset( $_POST['step'] ) ) {
            $post_step = absint( wp_unslash( $_POST['step'] ) );
        }

        // Security: Verify nonce for form submissions
        if ( $post_step > 1 ) {
            if ( ! isset( $_POST['tts_wizard_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['tts_wizard_nonce'] ), 'tts_client_wizard' ) ) {
                wp_die( esc_html__( 'Security verification failed. Please try again.', 'fp-publisher' ) );
            }
        }

        if ( $post_step > 0 ) {
            $step = $post_step;
        } else {
            $step = isset( $_GET['step'] ) ? absint( wp_unslash( $_GET['step'] ) ) : 1;
        }

        if ( ! $trello_enabled ) {
            if ( $step <= 1 ) {
                $step = 2;
            } elseif ( 3 === $step ) {
                $step = 4;
            }
        }

        $fb_token = get_transient( 'tts_oauth_facebook_token' );
        $ig_token = get_transient( 'tts_oauth_instagram_token' );
        $yt_token = get_transient( 'tts_oauth_youtube_token' );
        $tt_token = get_transient( 'tts_oauth_tiktok_token' );

        if ( isset( $_POST['trello_key'] ) ) {
            $trello_key = sanitize_text_field( wp_unslash( $_POST['trello_key'] ) );
        } elseif ( isset( $_GET['trello_key'] ) ) {
            $trello_key = sanitize_text_field( wp_unslash( $_GET['trello_key'] ) );
        } else {
            $trello_key = '';
        }

        if ( isset( $_POST['trello_token'] ) ) {
            $trello_token = sanitize_text_field( wp_unslash( $_POST['trello_token'] ) );
        } elseif ( isset( $_GET['trello_token'] ) ) {
            $trello_token = sanitize_text_field( wp_unslash( $_GET['trello_token'] ) );
        } else {
            $trello_token = '';
        }

        if ( isset( $_POST['trello_board'] ) ) {
            $board = sanitize_text_field( wp_unslash( $_POST['trello_board'] ) );
        } elseif ( isset( $_GET['trello_board'] ) ) {
            $board = sanitize_text_field( wp_unslash( $_GET['trello_board'] ) );
        } else {
            $board = '';
        }

        if ( isset( $_POST['channels'] ) ) {
            $channels = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['channels'] ) );
        } elseif ( isset( $_GET['channels'] ) ) {
            $channels = array_map( 'sanitize_text_field', (array) wp_unslash( $_GET['channels'] ) );
        } else {
            $channels = array();
        }

        if ( isset( $_POST['client_name'] ) ) {
            $client_name = sanitize_text_field( wp_unslash( $_POST['client_name'] ) );
        } elseif ( isset( $_GET['client_name'] ) ) {
            $client_name = sanitize_text_field( wp_unslash( $_GET['client_name'] ) );
        } else {
            $client_name = '';
        }
        $client_name = trim( $client_name );

        if ( isset( $_POST['blog_settings'] ) ) {
            $blog_settings = sanitize_textarea_field( wp_unslash( $_POST['blog_settings'] ) );
        } elseif ( isset( $_GET['blog_settings'] ) ) {
            $blog_settings = sanitize_textarea_field( wp_unslash( $_GET['blog_settings'] ) );
        } else {
            $blog_settings = '';
        }

        $checklist_notice = '';
        if ( isset( $_POST['tts_reset_wizard_progress'] ) ) {
            if ( isset( $_POST['tts_reset_wizard_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['tts_reset_wizard_nonce'] ), 'tts_reset_wizard' ) ) {
                unset( $_SESSION['tts_wizard_progress'] );
                $checklist_notice = __( 'Checklist ripristinata.', 'fp-publisher' );
            }
        }

        $prefill = isset( $_SESSION['tts_quickstart_prefill'] ) ? $_SESSION['tts_quickstart_prefill'] : array();

        if ( empty( $channels ) && ! empty( $prefill['channels'] ) && is_array( $prefill['channels'] ) ) {
            $channels = array_map( 'sanitize_text_field', $prefill['channels'] );
        }

        if ( '' === $blog_settings && ! empty( $prefill['blog_settings'] ) ) {
            $blog_settings = $prefill['blog_settings'];
        }

        $wizard_progress = isset( $_SESSION['tts_wizard_progress'] ) ? (array) $_SESSION['tts_wizard_progress'] : array(
            'trello'   => false,
            'channels' => false,
            'mapping'  => false,
            'blog'     => false,
            'review'   => false,
        );

        if ( ! $trello_enabled || ( $trello_key && $trello_token && $board ) ) {
            $wizard_progress['trello'] = true;
        }

        if ( ! empty( $channels ) ) {
            $wizard_progress['channels'] = true;
        }

        if ( '' !== trim( $blog_settings ) ) {
            $wizard_progress['blog'] = true;
        }

        $mapping_count = 0;
        if ( isset( $_POST['tts_trello_map'] ) && is_array( $_POST['tts_trello_map'] ) ) {
            foreach ( $_POST['tts_trello_map'] as $row ) {
                if ( ! empty( $row['canale_social'] ) ) {
                    $mapping_count++;
                }
            }
        } elseif ( isset( $wizard_progress['mapping_count'] ) ) {
            $mapping_count = (int) $wizard_progress['mapping_count'];
        }

        if ( ! $trello_enabled || $mapping_count > 0 ) {
            $wizard_progress['mapping'] = true;
        }

        if ( isset( $_POST['finalize'] ) ) {
            $wizard_progress['review'] = true;
        }

        $wizard_progress['mapping_count'] = $mapping_count;
        $_SESSION['tts_wizard_progress'] = $wizard_progress;

        $client_name_error = '';
        $required_step     = $trello_enabled ? 3 : 4;
        if ( $post_step >= $required_step && '' === $client_name ) {
            $client_name_error = __( 'Please provide a client name.', 'fp-publisher' );
            $step              = 2;
        }

        $wizard_context = array(
            'trello_key'      => $trello_key,
            'trello_token'    => $trello_token,
            'trello_board'    => $board,
            'channels'        => $channels,
            'blog_settings'   => $blog_settings,
            'tokens'          => array(
                'facebook'  => $fb_token,
                'instagram' => $ig_token,
                'youtube'   => $yt_token,
                'tiktok'    => $tt_token,
            ),
            'mapping_count'   => $mapping_count,
            'prefill_package' => isset( $prefill['label'] ) ? $prefill['label'] : '',
            'validation'      => isset( $prefill['validation'] ) ? $prefill['validation'] : array(),
            'usage_profile'   => $this->get_usage_profile(),
        );

        echo '<div class="wrap tts-client-wizard">';
        echo '<h1>' . esc_html__( 'Client Wizard', 'fp-publisher' ) . '</h1>';
        echo '<style>'
            . '.tts-wizard-checklist{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;margin-bottom:20px;}'
            . '.tts-checklist-progress{font-weight:600;margin-bottom:8px;color:#1d2327;}'
            . '.tts-checklist-prefill{margin-bottom:8px;color:#005ae0;}'
            . '.tts-checklist-items{list-style:none;margin:0;padding:0;}'
            . '.tts-checklist-item{display:flex;gap:12px;border-top:1px solid #ececec;padding:12px 0;}'
            . '.tts-checklist-item:first-child{border-top:0;padding-top:0;}'
            . '.tts-checklist-icon{font-size:20px;line-height:1;}'
            . '.tts-checklist-content strong{display:block;font-size:15px;margin-bottom:2px;}'
            . '.tts-checklist-description{display:block;color:#50575e;font-size:13px;margin-bottom:4px;}'
            . '.tts-checklist-message{display:block;color:#1d2327;font-size:13px;margin-bottom:4px;}'
            . '.tts-checklist-hints{list-style:none;margin:6px 0 0;padding:0;}'
            . '.tts-checklist-hints li{display:flex;gap:6px;font-size:12px;color:#50575e;margin-bottom:4px;}'
            . '.tts-checklist-hint-icon{font-size:14px;line-height:1.2;}'
            . '.tts-checklist-links{margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;}'
            . '.tts-checklist-docs a{font-size:12px;color:#2271b1;}'
            . '.tts-checklist-actions{margin-top:12px;}'
            . '.tts-inline-actions{margin:0 0 8px;}'
            . '.tts-validate-result,.tts-token-result{margin-bottom:12px;font-size:13px;}'
            . '.tts-validate-result.success,.tts-token-result.success{color:#007017;}'
            . '.tts-validate-result.error,.tts-token-result.error{color:#b52738;}'
            . '.tts-validation-summary{margin-top:12px;font-size:13px;color:#1d2327;}'
            . '</style>';

        if ( $checklist_notice ) {
            echo '<div class="notice notice-info"><p>' . esc_html( $checklist_notice ) . '</p></div>';
        }

        $this->render_client_wizard_checklist( $wizard_progress, $step, $prefill, $trello_enabled, $wizard_context );

        // Add helpful notice about social media setup.
        if ( 2 === $step ) {
            echo '<div class="notice notice-info">';
            echo '<h3>' . esc_html__( 'Social Media Setup Required', 'fp-publisher' ) . '</h3>';
            echo '<p>' . esc_html__( 'To connect social media accounts, you must first configure OAuth apps for each platform. Click "Configure App" for platforms that are not set up.', 'fp-publisher' ) . '</p>';
            echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-connections' ) ) . '" class="button">' . esc_html__( 'Manage Social Connections', 'fp-publisher' ) . '</a> ';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-support' ) ) . '" target="_blank">' . esc_html__( 'View Setup Guide', 'fp-publisher' ) . '</a></p>';
            echo '</div>';
        }

        if ( $trello_enabled && 1 === $step ) {
            echo '<form method="post" class="tts-wizard-step tts-step-1">';
            wp_nonce_field( 'tts_client_wizard', 'tts_wizard_nonce' );
            echo '<input type="hidden" name="step" value="2" />';
            echo '<input type="hidden" name="client_name" value="' . esc_attr( $client_name ) . '" />';
            echo '<input type="hidden" name="blog_settings" value="' . esc_attr( $blog_settings ) . '" />';
            echo '<p><label>' . esc_html__( 'Trello API Key', 'fp-publisher' ) . '<br />';
            echo '<input type="text" name="trello_key" value="' . esc_attr( $trello_key ) . '"' . ( $trello_enabled ? ' required' : '' ) . ' /></label></p>';
            echo '<p><label>' . esc_html__( 'Trello Token', 'fp-publisher' ) . '<br />';
            echo '<input type="text" name="trello_token" value="' . esc_attr( $trello_token ) . '"' . ( $trello_enabled ? ' required' : '' ) . ' /></label></p>';

            echo '<p class="tts-inline-actions">';
            echo '<button type="button" class="button tts-validate-trello" data-nonce="' . esc_attr( wp_create_nonce( 'tts_wizard' ) ) . '">'
                . esc_html__( 'Verifica credenziali Trello', 'fp-publisher' ) . '</button>';
            echo '</p>';
            echo '<div class="tts-validate-result" aria-live="polite"></div>';

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
            $next_step = $trello_enabled ? 3 : 4;
            echo '<input type="hidden" name="step" value="' . esc_attr( $next_step ) . '" />';
            if ( $trello_enabled ) {
                echo '<input type="hidden" name="trello_key" value="' . esc_attr( $trello_key ) . '" />';
                echo '<input type="hidden" name="trello_token" value="' . esc_attr( $trello_token ) . '" />';
                echo '<input type="hidden" name="trello_board" value="' . esc_attr( $board ) . '" />';
            }

            if ( $client_name_error ) {
                echo '<div class="notice notice-error"><p>' . esc_html( $client_name_error ) . '</p></div>';
            }
            echo '<p><label>' . esc_html__( 'Client Name', 'fp-publisher' ) . '<br />';
            echo '<input type="text" name="client_name" value="' . esc_attr( $client_name ) . '" required /></label></p>';

            echo '<p><label>' . esc_html__( 'Impostazioni blog', 'fp-publisher' ) . '<br />';
            echo '<textarea name="blog_settings" class="widefat" rows="3" placeholder="post_type:post|post_status:draft|author_id:1|category_id:1">' . esc_textarea( $blog_settings ) . '</textarea>';
            echo '<span class="description">' . esc_html__( 'Inserisci parametri separati da | per post_type, stato, autore, categorie e SEO.', 'fp-publisher' ) . '</span>';
            echo '</label></p>';

            $opts = array(
                'facebook'  => __( 'Facebook', 'fp-publisher' ),
                'instagram' => __( 'Instagram', 'fp-publisher' ),
                'youtube'   => __( 'YouTube', 'fp-publisher' ),
                'tiktok'    => __( 'TikTok', 'fp-publisher' ),
            );

            $wizard_nonce = wp_create_nonce( 'tts_wizard' );

            foreach ( $opts as $slug => $label ) {
                $token     = '';
                $connected = false;
                $app_configured = false;
                
                // Check if app is configured
                $settings = tsap_get_option( 'tts_social_apps', array() );
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
                    echo '<br><a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-connections' ) ) . '" class="button">' . esc_html__( 'Configure App', 'fp-publisher' ) . '</a>';
                } elseif ( $connected ) {
                    echo '<br><span style="color: #00a32a;">✅ ' . esc_html__( 'Connected', 'fp-publisher' ) . '</span>';
                    echo '<br><button type="button" class="button button-secondary tts-test-token" data-platform="' . esc_attr( $slug ) . '" data-nonce="' . esc_attr( $wizard_nonce ) . '">' . esc_html__( 'Test token', 'fp-publisher' ) . '</button>';
                } else {
                    $url = add_query_arg( array( 'action' => 'tts_oauth_' . $slug, 'step' => 2 ), admin_url( 'admin-post.php' ) );
                    echo '<br><span style="color: #f56e28;">🟡 ' . esc_html__( 'Ready to connect', 'fp-publisher' ) . '</span>';
                    echo '<br><a href="' . esc_url( $url ) . '" class="button button-primary">' . esc_html__( 'Connect Account', 'fp-publisher' ) . '</a>';
                }
                echo '<div class="tts-token-result" data-platform="' . esc_attr( $slug ) . '" aria-live="polite"></div>';
                echo '</div>';
            }

            echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Next', 'fp-publisher' ) . '</button></p>';
            echo '</form>';
        } elseif ( $trello_enabled && 3 === $step ) {
            echo '<form method="post" class="tts-wizard-step tts-step-3">';
            $nonce_field = wp_nonce_field( 'tts_client_wizard', 'tts_wizard_nonce', true, false );
            if ( isset( $_POST['tts_wizard_nonce'] ) ) {
                $nonce_value = wp_unslash( $_POST['tts_wizard_nonce'] );
                $nonce_field = preg_replace(
                    '/value="[^"]*"/',
                    'value="' . esc_attr( $nonce_value ) . '"',
                    $nonce_field,
                    1
                );
            }
            echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<input type="hidden" name="step" value="4" />';
            echo '<input type="hidden" name="trello_key" value="' . esc_attr( $trello_key ) . '" />';
            echo '<input type="hidden" name="trello_token" value="' . esc_attr( $trello_token ) . '" />';
            echo '<input type="hidden" name="trello_board" value="' . esc_attr( $board ) . '" />';
            echo '<input type="hidden" name="client_name" value="' . esc_attr( $client_name ) . '" />';
            echo '<input type="hidden" name="blog_settings" value="' . esc_attr( $blog_settings ) . '" />';
            foreach ( $channels as $ch ) {
                echo '<input type="hidden" name="channels[]" value="' . esc_attr( $ch ) . '" />';
            }
            if ( $trello_enabled ) {
                echo '<div id="tts-lists" data-board="' . esc_attr( $board ) . '" data-key="' . esc_attr( $trello_key ) . '" data-token="' . esc_attr( $trello_token ) . '"></div>';
            }
            echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Next', 'fp-publisher' ) . '</button></p>';
            echo '</form>';
        } else {
            if ( isset( $_POST['finalize'] ) ) {
                $final_client_name = $client_name ? $client_name : sanitize_text_field( __( 'Client', 'fp-publisher' ) );
                $post_id           = wp_insert_post(
                    array(
                        'post_type'   => 'tts_client',
                        'post_status' => 'publish',
                        'post_title'  => $final_client_name,
                    )
                );
                if ( $post_id ) {
                    if ( $trello_enabled ) {
                        update_post_meta( $post_id, '_tts_trello_key', $trello_key );
                        update_post_meta( $post_id, '_tts_trello_token', $trello_token );
                    }
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
                    if ( '' !== $blog_settings ) {
                        update_post_meta( $post_id, '_tts_blog_settings', $blog_settings );
                    }
                    if ( $trello_enabled && isset( $_POST['tts_trello_map'] ) && is_array( $_POST['tts_trello_map'] ) ) {
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

                    $_SESSION['tts_wizard_progress']['trello']   = true;
                    $_SESSION['tts_wizard_progress']['channels'] = true;
                    $_SESSION['tts_wizard_progress']['mapping']  = true;
                    $_SESSION['tts_wizard_progress']['blog']     = '' !== $blog_settings;
                    $_SESSION['tts_wizard_progress']['review']   = true;

                    echo '<p>' . esc_html__( 'Client created.', 'fp-publisher' ) . '</p>';
                }
                echo '</div>';
                return;
            }

            echo '<form method="post" class="tts-wizard-step tts-step-4">';
            $nonce_field = wp_nonce_field( 'tts_client_wizard', 'tts_wizard_nonce', true, false );
            if ( isset( $_POST['tts_wizard_nonce'] ) ) {
                $nonce_value = wp_unslash( $_POST['tts_wizard_nonce'] );
                $nonce_field = preg_replace(
                    '/value="[^"]*"/',
                    'value="' . esc_attr( $nonce_value ) . '"',
                    $nonce_field,
                    1
                );
            }
            echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<input type="hidden" name="step" value="4" />';
            echo '<input type="hidden" name="finalize" value="1" />';
            echo '<input type="hidden" name="trello_key" value="' . esc_attr( $trello_key ) . '" />';
            echo '<input type="hidden" name="trello_token" value="' . esc_attr( $trello_token ) . '" />';
            echo '<input type="hidden" name="trello_board" value="' . esc_attr( $board ) . '" />';
            echo '<input type="hidden" name="client_name" value="' . esc_attr( $client_name ) . '" />';
            echo '<input type="hidden" name="blog_settings" value="' . esc_attr( $blog_settings ) . '" />';
            foreach ( $channels as $ch ) {
                echo '<input type="hidden" name="channels[]" value="' . esc_attr( $ch ) . '" />';
            }
            if ( $trello_enabled && isset( $_POST['tts_trello_map'] ) && is_array( $_POST['tts_trello_map'] ) ) {
                foreach ( $_POST['tts_trello_map'] as $id_list => $row ) {
                    echo '<input type="hidden" name="tts_trello_map[' . esc_attr( $id_list ) . '][canale_social]" value="' . esc_attr( $row['canale_social'] ) . '" />';
                }
            }

            echo '<h2>' . esc_html__( 'Summary', 'fp-publisher' ) . '</h2>';
            echo '<p>' . esc_html__( 'Client Name:', 'fp-publisher' ) . ' ' . esc_html( $client_name ? $client_name : __( 'Client', 'fp-publisher' ) ) . '</p>';

            if ( '' !== $blog_settings ) {
                echo '<p><strong>' . esc_html__( 'Impostazioni blog', 'fp-publisher' ) . ':</strong><br><code>' . esc_html( $blog_settings ) . '</code></p>';
            }
            if ( $trello_enabled && $board ) {
                echo '<p>' . esc_html__( 'Trello Board:', 'fp-publisher' ) . ' ' . esc_html( $board ) . '</p>';
            }
            echo '<p>' . esc_html__( 'Channels:', 'fp-publisher' ) . ' ' . esc_html( implode( ', ', $channels ) ) . '</p>';
            echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Create Client', 'fp-publisher' ) . '</button></p>';
            echo '</form>';
        }

        echo '</div>';

        $ajax_url = admin_url( 'admin-ajax.php' );
        ob_start();
        ?>
        <script>
        jQuery(function($){
            var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;

            $(document).on('click', '.tts-validate-trello', function(e){
                e.preventDefault();
                var $btn = $(this);
                var nonce = $btn.data('nonce');
                var $form = $btn.closest('form');
                var apiKey = $form.find('input[name="trello_key"]').val();
                var token = $form.find('input[name="trello_token"]').val();
                var $output = $form.find('.tts-validate-result');

                $output.removeClass('success error').text(<?php echo wp_json_encode( __( 'Verifica in corso…', 'fp-publisher' ) ); ?>);
                $btn.prop('disabled', true);

                $.post(ajaxUrl, {
                    action: 'tts_validate_trello_credentials',
                    nonce: nonce,
                    api_key: apiKey,
                    token: token
                }).done(function(response){
                    if (response && response.success && response.data) {
                        var statusClass = response.data.success ? 'success' : 'error';
                        var message = response.data.message || <?php echo wp_json_encode( __( 'Risposta non disponibile.', 'fp-publisher' ) ); ?>;
                        $output.removeClass('success error').addClass(statusClass).text(message);
                    } else {
                        $output.removeClass('success').addClass('error').text(<?php echo wp_json_encode( __( 'Impossibile validare le credenziali.', 'fp-publisher' ) ); ?>);
                    }
                }).fail(function(){
                    $output.removeClass('success').addClass('error').text(<?php echo wp_json_encode( __( 'Errore di comunicazione con il server.', 'fp-publisher' ) ); ?>);
                }).always(function(){
                    $btn.prop('disabled', false);
                });
            });

            $(document).on('click', '.tts-test-token', function(e){
                e.preventDefault();
                var $btn = $(this);
                var platform = $btn.data('platform');
                var nonce = $btn.data('nonce');
                var $output = $btn.closest('div').find('.tts-token-result[data-platform="' + platform + '"]');

                if (!$output.length) {
                    return;
                }

                $output.removeClass('success error').text(<?php echo wp_json_encode( __( 'Verifica in corso…', 'fp-publisher' ) ); ?>);
                $btn.prop('disabled', true);

                $.post(ajaxUrl, {
                    action: 'tts_test_wizard_token',
                    nonce: nonce,
                    platform: platform
                }).done(function(response){
                    if (response && response.success && response.data) {
                        var statusClass = response.data.success ? 'success' : 'error';
                        var message = response.data.message || <?php echo wp_json_encode( __( 'Risposta non disponibile.', 'fp-publisher' ) ); ?>;
                        $output.removeClass('success error').addClass(statusClass).text(message);
                    } else {
                        $output.removeClass('success').addClass('error').text(<?php echo wp_json_encode( __( 'Impossibile testare il token.', 'fp-publisher' ) ); ?>);
                    }
                }).fail(function(){
                    $output.removeClass('success').addClass('error').text(<?php echo wp_json_encode( __( 'Errore di comunicazione con il server.', 'fp-publisher' ) ); ?>);
                }).always(function(){
                    $btn.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
        echo ob_get_clean();
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
    /**
     * Redirect default WordPress creation flow to the custom editor.
     */
    public function redirect_social_post_creation() {
        if ( ! is_admin() ) {
            return;
        }

        global $pagenow;

        if ( ! in_array( $pagenow, array( 'post-new.php', 'post.php' ), true ) ) {
            return;
        }

        if ( 'post-new.php' === $pagenow ) {
            if ( ! isset( $_GET['post_type'] ) || 'tts_social_post' !== $_GET['post_type'] ) {
                return;
            }

            $redirect_args = array(
                'page'            => 'fp-publisher-queue',
                'tts_open_editor' => 1,
            );

            if ( isset( $_GET['tts_client'] ) ) {
                $redirect_args['tts_client'] = absint( $_GET['tts_client'] );
            }

            if ( isset( $_GET['content_source'] ) ) {
                $redirect_args['content_source'] = sanitize_key( wp_unslash( $_GET['content_source'] ) );
            }

            wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
            exit;
        }

        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
        if ( $post_id <= 0 ) {
            return;
        }

        if ( 'tts_social_post' !== get_post_type( $post_id ) ) {
            return;
        }

        $redirect_args = array(
            'page'            => 'fp-publisher-queue',
            'action'          => 'edit',
            'post'            => $post_id,
            'tts_open_editor' => 1,
        );

        wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Handle social post creation from the custom editor.
     */
    public function handle_create_social_post() {
        $redirect_base = self::get_social_post_editor_url();

        if ( ! current_user_can( 'tts_create_social_posts' ) && ! current_user_can( 'tts_edit_social_posts' ) ) {
            wp_safe_redirect( self::get_social_post_editor_url( 0, array( 'tts_error' => 'unauthorized' ) ) );
            exit;
        }

        check_admin_referer( 'tts_create_social_post', 'tts_create_social_post_nonce' );

        $sanitized = $this->sanitize_social_post_request( $_POST, 'create' );
        if ( is_wp_error( $sanitized ) ) {
            $error_code = $sanitized->get_error_code() ? $sanitized->get_error_code() : 'invalid-request';
            wp_safe_redirect(
                self::get_social_post_editor_url(
                    0,
                    array(
                        'tts_error'       => $error_code,
                        'tts_open_editor' => 1,
                    )
                )
            );
            exit;
        }

        $post_id = wp_insert_post(
            array(
                'post_type'   => 'tts_social_post',
                'post_title'  => $sanitized['title'],
                'post_status' => 'draft',
                'post_author' => get_current_user_id(),
            ),
            true
        );

        if ( is_wp_error( $post_id ) ) {
            wp_safe_redirect(
                self::get_social_post_editor_url(
                    0,
                    array(
                        'tts_error'       => 'insert-failed',
                        'tts_open_editor' => 1,
                    )
                )
            );
            exit;
        }

        $this->persist_social_post_meta( $post_id, $sanitized );

        wp_safe_redirect( add_query_arg( 'tts_created', 1, $redirect_base ) );
        exit;
    }

    /**
     * Handle social post updates from the custom editor.
     */
    public function handle_update_social_post() {
        $redirect_base = self::get_social_post_editor_url();

        if ( ! current_user_can( 'tts_edit_social_posts' ) ) {
            wp_safe_redirect( self::get_social_post_editor_url( 0, array( 'tts_error' => 'unauthorized' ) ) );
            exit;
        }

        check_admin_referer( 'tts_update_social_post', 'tts_update_social_post_nonce' );

        $post_id = isset( $_POST['tts_post_id'] ) ? absint( wp_unslash( $_POST['tts_post_id'] ) ) : 0;
        if ( $post_id <= 0 ) {
            wp_safe_redirect(
                self::get_social_post_editor_url(
                    0,
                    array(
                        'tts_error'       => 'missing-post-id',
                        'tts_open_editor' => 1,
                    )
                )
            );
            exit;
        }

        $post = get_post( $post_id );
        if ( ! $post || 'tts_social_post' !== $post->post_type ) {
            wp_safe_redirect( self::get_social_post_editor_url( 0, array( 'tts_error' => 'not-found' ) ) );
            exit;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_safe_redirect( self::get_social_post_editor_url( 0, array( 'tts_error' => 'unauthorized' ) ) );
            exit;
        }

        $sanitized = $this->sanitize_social_post_request( $_POST, 'update' );
        if ( is_wp_error( $sanitized ) ) {
            $error_code = $sanitized->get_error_code() ? $sanitized->get_error_code() : 'invalid-request';
            wp_safe_redirect(
                self::get_social_post_editor_url(
                    $post_id,
                    array(
                        'tts_error'       => $error_code,
                        'tts_open_editor' => 1,
                    )
                )
            );
            exit;
        }

        $update = wp_update_post(
            array(
                'ID'         => $post_id,
                'post_title' => $sanitized['title'],
            ),
            true
        );

        if ( is_wp_error( $update ) ) {
            wp_safe_redirect(
                self::get_social_post_editor_url(
                    $post_id,
                    array(
                        'tts_error'       => 'update-failed',
                        'tts_open_editor' => 1,
                    )
                )
            );
            exit;
        }

        $this->persist_social_post_meta( $post_id, $sanitized );

        wp_safe_redirect( add_query_arg( 'tts_updated', 1, $redirect_base ) );
        exit;
    }

    /**
     * Sanitize and validate the social post payload coming from the custom editor.
     *
     * @param array<string,mixed> $request Raw request payload.
     * @param string              $mode    Request mode (create|update).
     *
     * @return array<string,mixed>|WP_Error
     */
    private function sanitize_social_post_request( array $request, $mode = 'create' ) {
        $title = isset( $request['tts_post_title'] ) ? sanitize_text_field( wp_unslash( $request['tts_post_title'] ) ) : '';
        $title = trim( $title );

        if ( '' === $title ) {
            return new WP_Error( 'missing-title' );
        }

        $channels = array();
        if ( isset( $request['_tts_social_channel'] ) ) {
            $raw_channels = (array) wp_unslash( $request['_tts_social_channel'] );
            foreach ( $raw_channels as $raw_channel ) {
                $channel_key = sanitize_key( $raw_channel );
                if ( in_array( $channel_key, self::$editor_social_channels, true ) ) {
                    $channels[] = $channel_key;
                }
            }
        }

        $channels = array_values( array_unique( $channels ) );

        if ( empty( $channels ) ) {
            return new WP_Error( 'missing-channels' );
        }

        $publish_at = '';
        if ( isset( $request['_tts_publish_at'] ) && '' !== $request['_tts_publish_at'] ) {
            $normalized_datetime = $this->normalize_editor_datetime( wp_unslash( $request['_tts_publish_at'] ) );
            if ( is_wp_error( $normalized_datetime ) ) {
                return $normalized_datetime;
            }

            $publish_at = $normalized_datetime;
        }

        $client_id = 0;
        if ( isset( $request['tts_client_id'] ) ) {
            $candidate_id = absint( $request['tts_client_id'] );
            if ( $candidate_id > 0 && 'tts_client' === get_post_type( $candidate_id ) ) {
                $client_id = $candidate_id;
            }
        }

        $messages = array();
        foreach ( self::$editor_social_channels as $channel_key ) {
            $meta_key            = '_tts_message_' . $channel_key;
            $messages[ $channel_key ] = '';
            if ( isset( $request[ $meta_key ] ) ) {
                $messages[ $channel_key ] = sanitize_textarea_field( wp_unslash( $request[ $meta_key ] ) );
            }
        }

        $instagram_comment = '';
        if ( isset( $request['_tts_instagram_first_comment'] ) ) {
            $instagram_comment = sanitize_textarea_field( wp_unslash( $request['_tts_instagram_first_comment'] ) );
            if ( function_exists( 'mb_substr' ) ) {
                $instagram_comment = mb_substr( $instagram_comment, 0, 2200 );
            } else {
                $instagram_comment = substr( $instagram_comment, 0, 2200 );
            }
        }

        if ( $instagram_comment && ! in_array( 'instagram', $channels, true ) ) {
            $instagram_comment = '';
        }

        $attachment_ids     = array();
        $raw_attachment_ids = isset( $request['_tts_attachment_ids'] ) ? sanitize_text_field( wp_unslash( $request['_tts_attachment_ids'] ) ) : '';
        if ( '' !== $raw_attachment_ids ) {
            $candidates  = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $raw_attachment_ids ) ) ) ) );
            $invalid_ids = array();

            foreach ( $candidates as $candidate ) {
                if ( $candidate <= 0 ) {
                    continue;
                }

                $attachment = get_post( $candidate );
                if ( ! $attachment || 'attachment' !== $attachment->post_type || ! current_user_can( 'read', $candidate ) ) {
                    $invalid_ids[] = $candidate;
                    continue;
                }

                $attachment_ids[] = $candidate;
            }

            if ( ! empty( $invalid_ids ) ) {
                return new WP_Error( 'invalid-attachments' );
            }
        }

        $manual_media = 0;
        if ( isset( $request['_tts_manual_media'] ) ) {
            $manual_candidate = absint( $request['_tts_manual_media'] );
            if ( $manual_candidate > 0 ) {
                $manual_attachment = get_post( $manual_candidate );
                if ( ! $manual_attachment || 'attachment' !== $manual_attachment->post_type || ! current_user_can( 'read', $manual_candidate ) ) {
                    return new WP_Error( 'invalid-attachments' );
                }

                $manual_media = $manual_candidate;
            }
        }

        $publish_story = isset( $request['_tts_publish_story'] ) && '1' === wp_unslash( $request['_tts_publish_story'] );
        $story_media   = 0;
        if ( $publish_story ) {
            $story_candidate = isset( $request['_tts_story_media'] ) ? absint( $request['_tts_story_media'] ) : 0;
            if ( $story_candidate > 0 ) {
                $story_attachment = get_post( $story_candidate );
                if ( ! $story_attachment || 'attachment' !== $story_attachment->post_type || ! current_user_can( 'read', $story_candidate ) ) {
                    return new WP_Error( 'invalid-story-media' );
                }

                $story_media = $story_candidate;
            } else {
                return new WP_Error( 'invalid-story-media' );
            }
        }

        $lat = '';
        if ( isset( $request['_tts_lat'] ) && '' !== $request['_tts_lat'] ) {
            $lat_candidate = trim( wp_unslash( $request['_tts_lat'] ) );
            if ( ! is_numeric( $lat_candidate ) || (float) $lat_candidate < -90 || (float) $lat_candidate > 90 ) {
                return new WP_Error( 'invalid-location' );
            }
            $lat = (string) round( (float) $lat_candidate, 6 );
        }

        $lng = '';
        if ( isset( $request['_tts_lng'] ) && '' !== $request['_tts_lng'] ) {
            $lng_candidate = trim( wp_unslash( $request['_tts_lng'] ) );
            if ( ! is_numeric( $lng_candidate ) || (float) $lng_candidate < -180 || (float) $lng_candidate > 180 ) {
                return new WP_Error( 'invalid-location' );
            }
            $lng = (string) round( (float) $lng_candidate, 6 );
        }

        $allowed_sources = array( 'manual' => 'Manual Creation' );
        if ( class_exists( 'TTS_Content_Source' ) ) {
            $allowed_sources = TTS_Content_Source::SOURCES;
        }

        $content_source = 'manual';
        if ( isset( $request['tts_content_source'] ) ) {
            $candidate_source = sanitize_key( wp_unslash( $request['tts_content_source'] ) );
            if ( '' !== $candidate_source ) {
                if ( ! isset( $allowed_sources[ $candidate_source ] ) ) {
                    return new WP_Error( 'invalid-source' );
                }
                $content_source = $candidate_source;
            }
        }

        $trello_notice_flag = '';
        $trello_enabled     = (bool) tsap_get_option( 'tts_trello_enabled', 1 );
        if ( 'trello' === $content_source && ! $trello_enabled ) {
            $trello_notice_flag = 'converted';
            $content_source     = 'manual';
        }

        $source_reference = '';
        if ( isset( $request['tts_source_reference'] ) ) {
            $source_reference = sanitize_text_field( wp_unslash( $request['tts_source_reference'] ) );
            if ( function_exists( 'mb_substr' ) ) {
                $source_reference = mb_substr( $source_reference, 0, 191 );
            } else {
                $source_reference = substr( $source_reference, 0, 191 );
            }
        }

        return array(
            'title'             => $title,
            'channels'          => $channels,
            'publish_at'        => $publish_at,
            'client_id'         => $client_id,
            'messages'          => $messages,
            'instagram_comment' => $instagram_comment,
            'attachment_ids'    => $attachment_ids,
            'manual_media'      => $manual_media,
            'publish_story'     => $publish_story,
            'story_media'       => $story_media,
            'lat'               => $lat,
            'lng'               => $lng,
            'content_source'    => $content_source,
            'source_reference'  => $source_reference,
            'trello_notice_flag'=> $trello_notice_flag,
        );
    }

    /**
     * Normalize the datetime string coming from the editor into MySQL format.
     *
     * @param string $raw_datetime Raw datetime string from the request.
     *
     * @return string|WP_Error
     */
    private function normalize_editor_datetime( $raw_datetime ) {
        $raw_datetime = trim( (string) $raw_datetime );

        if ( '' === $raw_datetime ) {
            return '';
        }

        $timezone = wp_timezone();
        $formats  = array( 'Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s' );

        foreach ( $formats as $format ) {
            $datetime = date_create_from_format( $format, $raw_datetime, $timezone );
            if ( $datetime instanceof \DateTime ) {
                return wp_date( 'Y-m-d H:i:s', $datetime->getTimestamp(), $timezone );
            }
        }

        $timestamp = strtotime( $raw_datetime );
        if ( false === $timestamp ) {
            return new WP_Error( 'invalid-publish-at' );
        }

        return wp_date( 'Y-m-d H:i:s', $timestamp, $timezone );
    }

    /**
     * Persist sanitized social post metadata.
     *
     * @param int                  $post_id  Target post identifier.
     * @param array<string,mixed> $data     Sanitized payload.
     */
    private function persist_social_post_meta( $post_id, array $data ) {
        update_post_meta( $post_id, '_tts_social_channel', array_values( $data['channels'] ) );

        if ( '' !== $data['publish_at'] ) {
            update_post_meta( $post_id, '_tts_publish_at', $data['publish_at'] );
        } else {
            delete_post_meta( $post_id, '_tts_publish_at' );
        }

        if ( $data['client_id'] > 0 ) {
            update_post_meta( $post_id, '_tts_client_id', $data['client_id'] );
        } else {
            delete_post_meta( $post_id, '_tts_client_id' );
        }

        foreach ( self::$editor_social_channels as $channel_key ) {
            $meta_key = '_tts_message_' . $channel_key;
            $message  = isset( $data['messages'][ $channel_key ] ) ? $data['messages'][ $channel_key ] : '';

            if ( '' !== $message && in_array( $channel_key, $data['channels'], true ) ) {
                update_post_meta( $post_id, $meta_key, $message );
            } else {
                delete_post_meta( $post_id, $meta_key );
            }
        }

        if ( $data['instagram_comment'] && in_array( 'instagram', $data['channels'], true ) ) {
            update_post_meta( $post_id, '_tts_instagram_first_comment', $data['instagram_comment'] );
        } else {
            delete_post_meta( $post_id, '_tts_instagram_first_comment' );
        }

        if ( ! empty( $data['attachment_ids'] ) ) {
            update_post_meta( $post_id, '_tts_attachment_ids', array_values( $data['attachment_ids'] ) );
        } else {
            delete_post_meta( $post_id, '_tts_attachment_ids' );
        }

        if ( $data['manual_media'] > 0 ) {
            update_post_meta( $post_id, '_tts_manual_media', $data['manual_media'] );
        } else {
            delete_post_meta( $post_id, '_tts_manual_media' );
        }

        if ( $data['publish_story'] ) {
            update_post_meta( $post_id, '_tts_publish_story', true );
            if ( $data['story_media'] > 0 ) {
                update_post_meta( $post_id, '_tts_story_media', $data['story_media'] );
            }
        } else {
            delete_post_meta( $post_id, '_tts_publish_story' );
            delete_post_meta( $post_id, '_tts_story_media' );
        }

        if ( '' !== $data['lat'] ) {
            update_post_meta( $post_id, '_tts_lat', $data['lat'] );
        } else {
            delete_post_meta( $post_id, '_tts_lat' );
        }

        if ( '' !== $data['lng'] ) {
            update_post_meta( $post_id, '_tts_lng', $data['lng'] );
        } else {
            delete_post_meta( $post_id, '_tts_lng' );
        }

        update_post_meta( $post_id, '_tts_content_source', $data['content_source'] );

        if ( '' !== $data['source_reference'] ) {
            update_post_meta( $post_id, '_tts_source_reference', $data['source_reference'] );
        } else {
            delete_post_meta( $post_id, '_tts_source_reference' );
        }

        if ( 'converted' === $data['trello_notice_flag'] ) {
            update_post_meta( $post_id, '_tts_trello_disabled_notice', $data['trello_notice_flag'] );
        } else {
            delete_post_meta( $post_id, '_tts_trello_disabled_notice' );
        }
    }

    /**
     * Retrieve human readable error messages for the social post editor.
     *
     * @param string $code Error code identifier.
     *
     * @return string
     */
    private function get_social_post_error_message( $code ) {
        switch ( $code ) {
            case 'missing-title':
                return __( 'Inserisci un titolo per il social post.', 'fp-publisher' );
            case 'missing-channels':
                return __( 'Seleziona almeno un canale social prima di salvare.', 'fp-publisher' );
            case 'insert-failed':
                return __( 'Si è verificato un errore durante la creazione del social post.', 'fp-publisher' );
            case 'update-failed':
                return __( 'Si è verificato un errore durante l\'aggiornamento del social post.', 'fp-publisher' );
            case 'unauthorized':
                return __( 'Non hai i permessi necessari per creare social post.', 'fp-publisher' );
            case 'not-found':
                return __( 'Il social post richiesto non è stato trovato.', 'fp-publisher' );
            case 'missing-post-id':
                return __( 'Impossibile determinare il post da modificare.', 'fp-publisher' );
            case 'invalid-publish-at':
                return __( 'Inserisci una data di pubblicazione valida.', 'fp-publisher' );
            case 'invalid-attachments':
                return __( 'Uno o più media selezionati non sono validi. Rimuovili e riprova.', 'fp-publisher' );
            case 'invalid-story-media':
                return __( 'Se desideri pubblicare anche una Story seleziona un media valido.', 'fp-publisher' );
            case 'invalid-source':
                return __( 'Seleziona un\'origine dei contenuti valida.', 'fp-publisher' );
            case 'invalid-location':
                return __( 'Controlla le coordinate inserite: latitudine e longitudine devono essere numeriche.', 'fp-publisher' );
            case 'invalid-request':
                return __( 'Impossibile elaborare i dati inviati. Riprova.', 'fp-publisher' );
            default:
                return __( 'Si è verificato un errore imprevisto. Riprova.', 'fp-publisher' );
        }
    }

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

        $channel_labels = array(
            'facebook'  => 'Facebook',
            'instagram' => 'Instagram',
            'youtube'   => 'YouTube',
            'tiktok'    => 'TikTok',
        );

        $selected_client  = isset( $_GET['tts_client'] ) ? absint( $_GET['tts_client'] ) : 0;
        $requested_source = isset( $_GET['content_source'] ) ? sanitize_key( wp_unslash( $_GET['content_source'] ) ) : '';

        $editor_post            = null;
        $editor_post_id         = 0;
        $is_editing             = false;
        $trello_converted_notice = false;

        if ( isset( $_GET['action'], $_GET['post'] ) && 'edit' === $_GET['action'] ) {
            $editor_post_id = absint( $_GET['post'] );
            $editor_post    = get_post( $editor_post_id );

            if ( ! $editor_post || 'tts_social_post' !== $editor_post->post_type ) {
                wp_safe_redirect( add_query_arg( array( 'page' => 'fp-publisher-queue', 'tts_error' => 'not-found' ), admin_url( 'admin.php' ) ) );
                exit;
            }

            if ( ! current_user_can( 'edit_post', $editor_post_id ) ) {
                wp_safe_redirect( add_query_arg( array( 'page' => 'fp-publisher-queue', 'tts_error' => 'unauthorized' ), admin_url( 'admin.php' ) ) );
                exit;
            }

            $is_editing = true;
        }

        $table = new TTS_Social_Posts_Table();
        $table->prepare_items();

        $error_code    = isset( $_GET['tts_error'] ) ? sanitize_key( wp_unslash( $_GET['tts_error'] ) ) : '';
        $error_message = $error_code ? $this->get_social_post_error_message( $error_code ) : '';
        $success       = isset( $_GET['tts_created'] );
        $updated       = isset( $_GET['tts_updated'] );

        $clients = get_posts(
            array(
                'post_type'      => 'tts_client',
                'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );

        $content_sources = array( 'manual' => 'Manual Creation' );
        if ( class_exists( 'TTS_Content_Source' ) ) {
            $content_sources = TTS_Content_Source::SOURCES;
        }

        $trello_enabled  = (bool) tsap_get_option( 'tts_trello_enabled', 1 );
        $selected_source = 'manual';

        $editor_values = array(
            'post_id'            => $editor_post_id,
            'title'              => $is_editing ? $editor_post->post_title : '',
            'client_id'          => $selected_client,
            'publish_at'         => '',
            'channels'           => array(),
            'messages'           => array(),
            'instagram_comment'  => '',
            'attachment_ids'     => array(),
            'manual_media'       => 0,
            'publish_story'      => false,
            'story_media'        => 0,
            'lat'                => '',
            'lng'                => '',
            'content_source'     => 'manual',
            'source_reference'   => '',
        );

        if ( $is_editing ) {
            $editor_values['client_id'] = (int) get_post_meta( $editor_post_id, '_tts_client_id', true );

            $stored_channels = get_post_meta( $editor_post_id, '_tts_social_channel', true );
            $channels        = array();
            if ( is_array( $stored_channels ) ) {
                foreach ( $stored_channels as $channel ) {
                    $key = sanitize_key( $channel );
                    if ( isset( $channel_labels[ $key ] ) ) {
                        $channels[] = $key;
                    }
                }
            }
            $editor_values['channels'] = array_values( array_unique( $channels ) );

            $raw_publish_at = get_post_meta( $editor_post_id, '_tts_publish_at', true );
            if ( $raw_publish_at ) {
                $timestamp = strtotime( $raw_publish_at );
                if ( $timestamp ) {
                    $editor_values['publish_at'] = date( 'Y-m-d\TH:i', $timestamp );
                }
            }

            $editor_values['instagram_comment'] = (string) get_post_meta( $editor_post_id, '_tts_instagram_first_comment', true );

            $attachments_meta = get_post_meta( $editor_post_id, '_tts_attachment_ids', true );
            if ( is_array( $attachments_meta ) ) {
                $editor_values['attachment_ids'] = array_values( array_map( 'absint', $attachments_meta ) );
            }

            $editor_values['manual_media']  = (int) get_post_meta( $editor_post_id, '_tts_manual_media', true );
            $editor_values['publish_story'] = (bool) get_post_meta( $editor_post_id, '_tts_publish_story', true );
            $editor_values['story_media']   = (int) get_post_meta( $editor_post_id, '_tts_story_media', true );
            $editor_values['lat']           = (string) get_post_meta( $editor_post_id, '_tts_lat', true );
            $editor_values['lng']           = (string) get_post_meta( $editor_post_id, '_tts_lng', true );

            $stored_source = sanitize_key( get_post_meta( $editor_post_id, '_tts_content_source', true ) );
            if ( '' !== $stored_source ) {
                $editor_values['content_source'] = $stored_source;
            }

            $editor_values['source_reference']   = (string) get_post_meta( $editor_post_id, '_tts_source_reference', true );
            $trello_converted_notice = (bool) get_post_meta( $editor_post_id, '_tts_trello_disabled_notice', true );
        }

        foreach ( $channel_labels as $key => $label ) {
            if ( $is_editing ) {
                $editor_values['messages'][ $key ] = (string) get_post_meta( $editor_post_id, '_tts_message_' . $key, true );
            } else {
                $editor_values['messages'][ $key ] = '';
            }
        }

        if ( $is_editing ) {
            $selected_source = $editor_values['content_source'];
        } elseif ( isset( $content_sources[ $requested_source ] ) ) {
            $selected_source = $requested_source;
        }

        if ( ! isset( $content_sources[ $selected_source ] ) ) {
            $selected_source = 'manual';
        }

        if ( 'trello' === $selected_source && ! $trello_enabled ) {
            $selected_source = 'manual';
        }

        $editor_values['content_source'] = $selected_source;
        $selected_client                 = $editor_values['client_id'];

        $should_open = false;
        if ( $is_editing ) {
            $should_open = true;
        } else {
            if ( $success || $updated ) {
                $should_open = false;
            } else {
                $should_open = ( ! empty( $_GET['tts_open_editor'] ) || '' !== $error_message || '' !== $requested_source || $selected_client > 0 );
            }
        }

        if ( $editor_values['manual_media'] <= 0 && ! empty( $editor_values['attachment_ids'] ) ) {
            $editor_values['manual_media'] = absint( $editor_values['attachment_ids'][0] );
        }

        $primary_media_id = absint( $editor_values['manual_media'] );
        $attachment_items = '';
        foreach ( $editor_values['attachment_ids'] as $attachment_id ) {
            $attachment_id = absint( $attachment_id );
            if ( $attachment_id <= 0 ) {
                continue;
            }

            $thumb      = wp_get_attachment_image( $attachment_id, array( 120, 120 ) );
            $title      = get_the_title( $attachment_id );
            $title      = $title ? $title : sprintf( __( 'Media #%d', 'fp-publisher' ), $attachment_id );
            $is_primary = ( $attachment_id === $primary_media_id );

            if ( $thumb ) {
                $attachment_items .= '<li class="tts-attachment-item' . ( $is_primary ? ' is-primary' : '' ) . '" data-id="' . esc_attr( $attachment_id ) . '">';
                $attachment_items .= '<div class="tts-attachment-thumb">' . $thumb . '</div>';
                $attachment_items .= '<div class="tts-attachment-meta">';
                $attachment_items .= '<span class="tts-attachment-title">' . esc_html( $title ) . '</span>';
                $attachment_items .= '<div class="tts-attachment-actions">';
                $attachment_items .= '<button type="button" class="button-link tts-attachment-make-primary" data-id="' . esc_attr( $attachment_id ) . '">' . esc_html__( 'Imposta come principale', 'fp-publisher' ) . '</button>';
                $attachment_items .= '<button type="button" class="button-link-delete tts-attachment-remove" data-id="' . esc_attr( $attachment_id ) . '">' . esc_html__( 'Rimuovi', 'fp-publisher' ) . '</button>';
                $attachment_items .= '</div>';
                $attachment_items .= '</div>';
                $attachment_items .= '<span class="tts-primary-indicator" aria-hidden="true">' . esc_html__( 'Primario', 'fp-publisher' ) . '</span>';
                $attachment_items .= '</li>';
            }
        }

        $story_media_preview = '';
        if ( $editor_values['story_media'] > 0 ) {
            $preview = wp_get_attachment_image( $editor_values['story_media'], array( 160, 160 ) );
            if ( $preview ) {
                $story_media_preview = $preview;
            }
        }

        $story_wrapper_style = $editor_values['publish_story'] ? '' : ' style="display:none;"';

        $editor_classes = 'tts-social-posts-editor';
        if ( $should_open ) {
            $editor_classes .= ' is-open';
        }
        if ( $is_editing ) {
            $editor_classes .= ' is-editing';
        }

        $data_open   = $should_open ? '1' : '0';
        $editor_mode = $is_editing ? 'edit' : 'create';

        $open_label_text  = __( 'Crea nuovo social post', 'fp-publisher' );
        $close_label_text = __( 'Nascondi editor', 'fp-publisher' );
        $open_button_aria = $should_open ? 'true' : 'false';
        $submit_label     = $is_editing ? __( 'Aggiorna social post', 'fp-publisher' ) : __( 'Salva social post', 'fp-publisher' );

        $cancel_attributes = 'type="button" class="button" id="tts-cancel-social-post-editor"';
        if ( $is_editing ) {
            $cancel_attributes .= ' data-cancel-url="' . esc_url( self::get_social_post_editor_url() ) . '"';
        }

        echo '<div class="wrap tts-social-posts-page">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Social Post', 'fp-publisher' ) . '</h1>';
        echo '<p class="description">' . esc_html__( 'Gestisci la pianificazione dei contenuti e crea nuovi post social da un’unica interfaccia.', 'fp-publisher' ) . '</p>';

        if ( $success ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Il social post è stato creato con successo.', 'fp-publisher' ) . '</p></div>';
        }

        if ( $updated ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Il social post è stato aggiornato con successo.', 'fp-publisher' ) . '</p></div>';
        }

        $deleted_posts = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0;
        if ( $deleted_posts > 0 ) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                sprintf(
                    /* translators: %s: number of deleted social posts. */
                    esc_html__( 'Moved %s social posts to the trash.', 'fp-publisher' ),
                    number_format_i18n( $deleted_posts )
                )
            );
        }

        if ( '' !== $error_message ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $error_message ) . '</p></div>';
        }

        echo '<div class="tts-social-posts-header">';
        echo '<div class="tts-social-posts-header-text">';
        echo '<h2>' . esc_html__( 'Pianifica e pubblica', 'fp-publisher' ) . '</h2>';
        echo '<p>' . esc_html__( 'Consulta lo storico dei contenuti e crea rapidamente nuovi post multi-canale.', 'fp-publisher' ) . '</p>';
        echo '</div>';
        printf(
            '<button type="button" class="button button-primary" id="tts-open-social-post-editor" data-editor-target="tts-social-post-editor" data-open-label="%1$s" data-close-label="%2$s" aria-expanded="%3$s">%4$s</button>',
            esc_attr( $open_label_text ),
            esc_attr( $close_label_text ),
            esc_attr( $open_button_aria ),
            esc_html( $open_label_text )
        );
        echo '</div>';

        echo '<div class="tts-social-posts-layout">';
        echo '<div class="tts-social-posts-table">';
        $filters_summary = $table->get_filters_summary_text();
        if ( '' !== $filters_summary ) {
            echo '<p class="description">' . esc_html( $filters_summary ) . '</p>';
        }
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="fp-publisher-queue" />';
        $table->views();
        $table->search_box( __( 'Search social posts', 'fp-publisher' ), 'fp-publisher-queue' );
        $table->display();
        echo '</form>';
        echo '</div>';

        printf(
            '<div id="tts-social-post-editor" class="%1$s" data-open="%2$s" data-mode="%3$s">',
            esc_attr( $editor_classes ),
            esc_attr( $data_open ),
            esc_attr( $editor_mode )
        );

        if ( $is_editing ) {
            echo '<h2>' . esc_html__( 'Modifica social post', 'fp-publisher' ) . '</h2>';
            echo '<div class="tts-editor-banner">';
            echo '<span class="tts-editor-badge">' . esc_html__( 'Modifica', 'fp-publisher' ) . '</span>';
            echo '<span class="tts-editor-current-title">' . esc_html( get_the_title( $editor_post ) ) . '</span>';
            echo '</div>';
            echo '<p class="description">' . esc_html__( 'Aggiorna i contenuti e riprogramma la pubblicazione del post selezionato.', 'fp-publisher' ) . '</p>';
        } else {
            echo '<h2>' . esc_html__( 'Nuovo social post', 'fp-publisher' ) . '</h2>';
            echo '<p class="description">' . esc_html__( 'Compila i campi per programmare il contenuto sui canali selezionati.', 'fp-publisher' ) . '</p>';
        }

        ob_start();
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tts-social-post-form">
            <?php
            if ( $is_editing ) {
                wp_nonce_field( 'tts_update_social_post', 'tts_update_social_post_nonce' );
                ?>
                <input type="hidden" name="action" value="tts_update_social_post" />
                <input type="hidden" name="tts_post_id" value="<?php echo esc_attr( $editor_values['post_id'] ); ?>" />
                <?php
            } else {
                wp_nonce_field( 'tts_create_social_post', 'tts_create_social_post_nonce' );
                ?>
                <input type="hidden" name="action" value="tts_create_social_post" />
                <?php
            }
            ?>

            <div class="tts-editor-grid">
                <div class="tts-editor-column tts-editor-column--primary">
                    <div class="tts-editor-card tts-editor-card--details">
                        <div class="tts-editor-card__header">
                            <h3><?php esc_html_e( 'Dettagli del contenuto', 'fp-publisher' ); ?></h3>
                            <p><?php esc_html_e( 'Imposta le informazioni chiave per programmare correttamente il post.', 'fp-publisher' ); ?></p>
                        </div>

                        <div class="tts-field-group">
                            <label for="tts_post_title"><?php esc_html_e( 'Titolo del post', 'fp-publisher' ); ?></label>
                            <input type="text" id="tts_post_title" name="tts_post_title" class="regular-text" value="<?php echo esc_attr( $editor_values['title'] ); ?>" required />
                            <p class="description"><?php esc_html_e( 'Usa un titolo descrittivo per riconoscere rapidamente il contenuto.', 'fp-publisher' ); ?></p>
                        </div>

                        <div class="tts-field-group">
                            <label for="tts_client_id"><?php esc_html_e( 'Cliente associato', 'fp-publisher' ); ?></label>
                            <select id="tts_client_id" name="tts_client_id" class="widefat">
                                <option value="0" <?php selected( 0, $selected_client ); ?>><?php esc_html_e( 'Seleziona cliente (opzionale)', 'fp-publisher' ); ?></option>
                                <?php foreach ( $clients as $client ) : ?>
                                    <option value="<?php echo esc_attr( $client->ID ); ?>" <?php selected( $selected_client, $client->ID ); ?>><?php echo esc_html( get_the_title( $client ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Collega il post a un cliente per applicare regole e credenziali dedicate.', 'fp-publisher' ); ?></p>
                        </div>

                        <div class="tts-field-group tts-field-group--inline">
                            <div class="tts-field">
                                <label for="_tts_publish_at"><?php esc_html_e( 'Data e ora di pubblicazione', 'fp-publisher' ); ?></label>
                                <input type="datetime-local" id="_tts_publish_at" name="_tts_publish_at" class="regular-text" value="<?php echo esc_attr( $editor_values['publish_at'] ); ?>" />
                            </div>
                            <div class="tts-field">
                                <label for="tts_content_source"><?php esc_html_e( 'Origine contenuto', 'fp-publisher' ); ?></label>
                                <select id="tts_content_source" name="tts_content_source" class="widefat">
                                    <option value=""><?php esc_html_e( 'Seleziona origine', 'fp-publisher' ); ?></option>
                                    <?php foreach ( $content_sources as $key => $label ) :
                                        if ( 'trello' === $key && ! $trello_enabled ) {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_source, $key ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <?php if ( isset( $content_sources['trello'] ) && ! $trello_enabled ) : ?>
                            <p class="description tts-content-source-warning"><?php esc_html_e( 'L’integrazione Trello è disabilitata: scegli un’altra origine o riattiva la connessione.', 'fp-publisher' ); ?></p>
                        <?php endif; ?>
                        <?php if ( $trello_converted_notice ) : ?>
                            <p class="description"><?php esc_html_e( 'Questo post è stato convertito in modalità manuale perché la connessione Trello non è attiva.', 'fp-publisher' ); ?></p>
                        <?php endif; ?>

                        <div class="tts-field-group">
                            <label for="tts_source_reference" class="tts-field-sub-label"><?php esc_html_e( 'Riferimento esterno (opzionale)', 'fp-publisher' ); ?></label>
                            <input type="text" id="tts_source_reference" name="tts_source_reference" class="regular-text" value="<?php echo esc_attr( $editor_values['source_reference'] ); ?>" />
                        </div>

                        <div class="tts-field-group tts-location-fields">
                            <div class="tts-location-input">
                                <label for="_tts_lat"><?php esc_html_e( 'Latitudine', 'fp-publisher' ); ?></label>
                                <input type="text" id="_tts_lat" name="_tts_lat" class="regular-text" value="<?php echo esc_attr( $editor_values['lat'] ); ?>" />
                            </div>
                            <div class="tts-location-input">
                                <label for="_tts_lng"><?php esc_html_e( 'Longitudine', 'fp-publisher' ); ?></label>
                                <input type="text" id="_tts_lng" name="_tts_lng" class="regular-text" value="<?php echo esc_attr( $editor_values['lng'] ); ?>" />
                            </div>
                        </div>
                    </div>

                    <div class="tts-editor-card tts-editor-card--media">
                        <div class="tts-editor-card__header">
                            <h3><?php esc_html_e( 'Media allegati', 'fp-publisher' ); ?></h3>
                            <p><?php esc_html_e( 'Aggiungi immagini o video, imposta l’elemento principale e gestisci l’ordine.', 'fp-publisher' ); ?></p>
                        </div>

                        <div
                            class="tts-attachments"
                            data-empty="<?php echo esc_attr( empty( $editor_values['attachment_ids'] ) ? '1' : '0' ); ?>"
                            data-make-primary-label="<?php echo esc_attr__( 'Imposta come principale', 'fp-publisher' ); ?>"
                            data-remove-label="<?php echo esc_attr__( 'Rimuovi', 'fp-publisher' ); ?>"
                            data-primary-label="<?php echo esc_attr__( 'Primario', 'fp-publisher' ); ?>"
                        >
                            <p id="tts_attachments_empty" class="tts-attachments-empty" <?php if ( ! empty( $editor_values['attachment_ids'] ) ) : ?>style="display:none"<?php endif; ?>><?php esc_html_e( 'Nessun media selezionato. Aggiungi elementi con il pulsante qui sotto.', 'fp-publisher' ); ?></p>
                            <ul id="tts_attachments_list" class="tts-attachments-list"><?php echo $attachment_items; ?></ul>
                            <input type="hidden" id="tts_attachment_ids" name="_tts_attachment_ids" value="<?php echo esc_attr( implode( ',', $editor_values['attachment_ids'] ) ); ?>" />
                            <input type="hidden" id="tts_manual_media" name="_tts_manual_media" value="<?php echo esc_attr( $editor_values['manual_media'] ); ?>" />
                            <div class="tts-attachments-actions">
                                <button type="button" class="button button-secondary tts-select-media"><?php esc_html_e( 'Seleziona/Carica file', 'fp-publisher' ); ?></button>
                                <button type="button" class="button-link tts-clear-attachments" <?php if ( empty( $editor_values['attachment_ids'] ) ) : ?>style="display:none"<?php endif; ?>><?php esc_html_e( 'Rimuovi tutti', 'fp-publisher' ); ?></button>
                            </div>
                        </div>

                        <div class="tts-field-group tts-story-toggle">
                            <label class="tts-checkbox-inline">
                                <input type="checkbox" id="tts_publish_story" name="_tts_publish_story" value="1" <?php checked( $editor_values['publish_story'], true ); ?> />
                                <?php esc_html_e( 'Pubblica anche come Story', 'fp-publisher' ); ?>
                            </label>
                            <div id="tts_story_media_wrapper" class="tts-story-media" <?php echo $story_wrapper_style; ?>>
                                <div id="tts_story_media_preview" class="tts-story-media-preview"><?php echo $story_media_preview; ?></div>
                                <input type="hidden" id="tts_story_media" name="_tts_story_media" value="<?php echo esc_attr( $editor_values['story_media'] ); ?>" />
                                <button type="button" class="button tts-select-story-media"><?php esc_html_e( 'Seleziona media Story', 'fp-publisher' ); ?></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tts-editor-column tts-editor-column--secondary">
                    <div class="tts-editor-card tts-editor-card--channels">
                        <div class="tts-editor-card__header">
                            <h3><?php esc_html_e( 'Canali e messaggi', 'fp-publisher' ); ?></h3>
                            <p><?php esc_html_e( 'Attiva i canali da coinvolgere e personalizza i messaggi per ciascuna piattaforma.', 'fp-publisher' ); ?></p>
                        </div>

                        <fieldset class="tts-channel-selector">
                            <legend class="screen-reader-text"><?php esc_html_e( 'Canali di pubblicazione', 'fp-publisher' ); ?></legend>
                            <div class="tts-channel-options">
                                <?php foreach ( $channel_labels as $key => $label ) : ?>
                                    <label class="tts-channel-option">
                                        <input type="checkbox" class="tts-channel-checkbox" name="_tts_social_channel[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $editor_values['channels'], true ) ); ?> />
                                        <span><?php echo esc_html( $label ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description"><?php esc_html_e( 'Seleziona uno o più canali: i campi messaggio si attiveranno automaticamente.', 'fp-publisher' ); ?></p>
                        </fieldset>

                        <div class="tts-channel-messages">
                            <?php foreach ( $channel_labels as $key => $label ) :
                                $message_id    = 'tts_message_' . $key;
                                $message_label = sprintf( esc_html__( 'Messaggio per %s', 'fp-publisher' ), $label );
                                ?>
                                <div class="tts-channel-message" data-channel="<?php echo esc_attr( $key ); ?>">
                                    <div class="tts-channel-message__header">
                                        <label for="<?php echo esc_attr( $message_id ); ?>"><?php echo esc_html( $message_label ); ?></label>
                                        <span class="tts-message-counter" data-counter-for="<?php echo esc_attr( $message_id ); ?>">0</span>
                                    </div>
                                    <textarea id="<?php echo esc_attr( $message_id ); ?>" name="_tts_message_<?php echo esc_attr( $key ); ?>" rows="4" class="widefat"><?php echo esc_textarea( $editor_values['messages'][ $key ] ); ?></textarea>
                                    <?php if ( 'instagram' === $key ) : ?>
                                        <label for="tts_instagram_first_comment" class="tts-field-sub-label"><?php esc_html_e( 'Commento iniziale Instagram', 'fp-publisher' ); ?></label>
                                        <textarea id="tts_instagram_first_comment" name="_tts_instagram_first_comment" rows="3" class="widefat"><?php echo esc_textarea( $editor_values['instagram_comment'] ); ?></textarea>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="tts-editor-card tts-editor-card--summary" id="tts-editor-summary">
                        <div class="tts-editor-card__header">
                            <h3><?php esc_html_e( 'Riepilogo configurazione', 'fp-publisher' ); ?></h3>
                            <p><?php esc_html_e( 'Controlla a colpo d’occhio le impostazioni principali del post.', 'fp-publisher' ); ?></p>
                        </div>
                        <ul class="tts-summary-list">
                            <li><strong><?php esc_html_e( 'Titolo:', 'fp-publisher' ); ?></strong> <span data-summary="title">—</span></li>
                            <li><strong><?php esc_html_e( 'Programmazione:', 'fp-publisher' ); ?></strong> <span data-summary="schedule" data-default-label="<?php echo esc_attr__( 'Immediata', 'fp-publisher' ); ?>"><?php esc_html_e( 'Immediata', 'fp-publisher' ); ?></span></li>
                            <li><strong><?php esc_html_e( 'Canali attivi:', 'fp-publisher' ); ?></strong> <span data-summary="channels">0</span></li>
                            <li><strong><?php esc_html_e( 'Media allegati:', 'fp-publisher' ); ?></strong> <span data-summary="attachments">0</span></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="tts-editor-actions">
                <button type="submit" class="button button-primary tts-form-save"><?php echo esc_html( $submit_label ); ?></button>
                <button <?php echo $cancel_attributes; ?>><?php esc_html_e( 'Annulla', 'fp-publisher' ); ?></button>
            </div>
        </form>
        <?php
        echo ob_get_clean();
        echo '</div>';
        echo '</div>';
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
    }    /**
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
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'fp-publisher' ) );
        }

        $heading_id     = wp_unique_id( 'fp-admin-page-title-' );
        $description_id = wp_unique_id( 'fp-admin-page-lead-' );

        ?>
        <div class="wrap fp-admin-settings">
            <div class="fp-admin-page-header" role="group" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" aria-describedby="<?php echo esc_attr( $description_id ); ?>">
                <h1 class="fp-admin-page-header__title" id="<?php echo esc_attr( $heading_id ); ?>" aria-describedby="<?php echo esc_attr( $description_id ); ?>"><?php esc_html_e( 'Social Auto Publisher Settings', 'fp-publisher' ); ?></h1>
                <p class="fp-admin-page-header__lead" id="<?php echo esc_attr( $description_id ); ?>"><?php esc_html_e( 'Adjust global behaviours for content scheduling, logging and usage profiles. Connection credentials live on the Social Connections screen.', 'fp-publisher' ); ?></p>
            </div>

            <div class="fp-admin-card">
                <?php settings_errors( 'tts_settings' ); ?>
                <p class="fp-admin-help-text"><?php esc_html_e( 'Need to update OAuth tokens or verify APIs? Head to Social Connections for per-channel tools.', 'fp-publisher' ); ?></p>

                <form action="options.php" method="post" class="fp-admin-settings__form">
                    <?php
                    settings_fields( 'tts_settings_group' );
                    do_settings_sections( 'tts_settings' );
                    submit_button();
                    ?>
                </form>
            </div>
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

        $heading_id     = wp_unique_id( 'fp-admin-page-title-' );
        $description_id = wp_unique_id( 'fp-admin-page-lead-' );

        try {
            // Handle form submissions
            if ( isset( $_POST['action'] ) && $_POST['action'] === 'save_social_apps' ) {
                if ( wp_verify_nonce( $_POST['tts_social_nonce'], 'tts_save_social_apps' ) ) {
                    $this->save_social_app_settings();
                    echo '<div class="notice notice-success"><p>' . esc_html__( 'Social media app settings saved successfully!', 'fp-publisher' ) . '</p></div>';
                }
            }

            $settings = tsap_get_option( 'tts_social_apps', array() );
        ?>
        <div class="wrap fp-admin-social-connections">
            <div class="fp-admin-page-header" role="group" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" aria-describedby="<?php echo esc_attr( $description_id ); ?>">
                <h1 class="fp-admin-page-header__title" id="<?php echo esc_attr( $heading_id ); ?>" aria-describedby="<?php echo esc_attr( $description_id ); ?>"><?php esc_html_e( 'Social Media Connections', 'fp-publisher' ); ?></h1>
                <p class="fp-admin-page-header__lead" id="<?php echo esc_attr( $description_id ); ?>"><?php esc_html_e( 'Manage OAuth credentials, run connection tests and monitor rate limits for each supported social channel.', 'fp-publisher' ); ?></p>
            </div>

            <div class="fp-admin-card fp-admin-social-connections__intro">
                <h2 class="screen-reader-text"><?php esc_html_e( 'Setup instructions', 'fp-publisher' ); ?></h2>
                <p><?php esc_html_e( 'Create an app on every platform and paste the credentials below. Use the provided redirect URL for each OAuth integration.', 'fp-publisher' ); ?></p>
                <ol>
                    <li><strong>Facebook:</strong> <?php esc_html_e( 'Create an app at', 'fp-publisher' ); ?> <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer">Facebook Developers</a></li>
                    <li><strong>Instagram:</strong> <?php esc_html_e( 'Enable Instagram Basic Display on the same Facebook app.', 'fp-publisher' ); ?></li>
                    <li><strong>YouTube:</strong> <?php esc_html_e( 'Create OAuth credentials inside Google Cloud Console.', 'fp-publisher' ); ?> <a href="https://console.developers.google.com/" target="_blank" rel="noopener noreferrer">Google Developers Console</a></li>
                    <li><strong>TikTok:</strong> <?php esc_html_e( 'Request access to TikTok for Developers and generate a web app.', 'fp-publisher' ); ?> <a href="https://developers.tiktok.com/" target="_blank" rel="noopener noreferrer">TikTok Developers</a></li>
                </ol>
                <p><strong><?php esc_html_e( 'Redirect URI:', 'fp-publisher' ); ?></strong> <code><?php echo esc_url( admin_url( 'admin-post.php' ) ); ?></code></p>
            </div>

            <form id="tts-social-connections-form" class="fp-admin-social-connections__form tts-social-apps-container" method="post" action="">
                <?php wp_nonce_field( 'tts_save_social_apps', 'tts_social_nonce' ); ?>
                <input type="hidden" name="action" value="save_social_apps" />

                <div class="fp-admin-social-connections__grid">
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
                        $platform_settings = isset( $settings[ $platform ] ) ? $settings[ $platform ] : array();
                        $connection_status = $this->check_platform_connection_status( $platform );
                    ?>
                    <section class="fp-admin-card fp-admin-social-platform tts-platform-config" data-platform="<?php echo esc_attr( $platform ); ?>">
                        <div class="fp-admin-social-platform__header">
                            <h2 class="fp-admin-social-platform__title">
                                <span class="fp-admin-social-platform__icon" aria-hidden="true"><?php echo esc_html( $config['icon'] ); ?></span>
                                <span><?php echo esc_html( $config['name'] ); ?></span>
                            </h2>
                        </div>

                        <div class="fp-admin-social-platform__fields">
                            <?php foreach ( $config['fields'] as $field ) :
                                $field_value = isset( $platform_settings[ $field ] ) ? $platform_settings[ $field ] : '';
                                $field_label = ucwords( str_replace( '_', ' ', $field ) );
                            ?>
                            <div class="fp-admin-form-row">
                                <label class="fp-admin-form-row__label" for="<?php echo esc_attr( $platform . '_' . $field ); ?>">
                                    <?php echo esc_html( $field_label ); ?>
                                </label>
                                <div class="fp-admin-form-row__control">
                                    <input type="text"
                                        id="<?php echo esc_attr( $platform . '_' . $field ); ?>"
                                        name="social_apps[<?php echo esc_attr( $platform ); ?>][<?php echo esc_attr( $field ); ?>]"
                                        value="<?php echo esc_attr( $field_value ); ?>"
                                        class="regular-text" />
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="fp-admin-social-platform__status tts-connection-status" data-platform="<?php echo esc_attr( $platform ); ?>">
                            <strong><?php esc_html_e( 'Status:', 'fp-publisher' ); ?></strong>
                            <span class="tts-status-message tts-status-<?php echo esc_attr( $connection_status['status'] ); ?>">
                                <?php echo esc_html( $connection_status['message'] ); ?>
                            </span>

                            <?php if ( $connection_status['status'] === 'configured' ) : ?>
                                <div class="fp-admin-social-platform__actions tts-platform-actions">
                                    <a href="<?php echo esc_url( $this->get_oauth_url( $platform ) ); ?>" class="button button-primary">
                                        <?php esc_html_e( 'Connect Account', 'fp-publisher' ); ?>
                                    </a>
                                    <button type="button" class="button tts-test-connection" data-platform="<?php echo esc_attr( $platform ); ?>">
                                        <?php esc_html_e( 'Test Connection', 'fp-publisher' ); ?>
                                    </button>
                                </div>
                                <div class="fp-admin-social-platform__result tts-test-result" id="test-result-<?php echo esc_attr( $platform ); ?>" aria-live="polite"></div>
                            <?php endif; ?>

                            <?php if ( $connection_status['status'] === 'connected' ) : ?>
                                <div class="fp-admin-social-platform__rate-limit tts-rate-limit-info" id="rate-limit-<?php echo esc_attr( $platform ); ?>">
                                    <button type="button" class="button tts-check-limits" data-platform="<?php echo esc_attr( $platform ); ?>">
                                        <?php esc_html_e( 'Check API Limits', 'fp-publisher' ); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                    <?php endforeach; ?>
                </div>

                <div class="fp-admin-toolbar">
                    <div class="fp-admin-toolbar__group fp-admin-toolbar__group--align-end">
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Save App Settings', 'fp-publisher' ); ?></button>
                    </div>
                </div>
            </form>
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
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-dashboard' ) ) . '" class="button button-primary">' . esc_html__( 'Return to Dashboard', 'fp-publisher' ) . '</a>';
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

        if ( ! is_array( $social_apps ) ) {
            tsap_update_option( 'tts_social_apps', array() );
            return;
        }

        $social_apps    = wp_unslash( $social_apps );
        $sanitized_apps = $this->sanitize_social_app_settings_array( $social_apps );

        tsap_update_option( 'tts_social_apps', $sanitized_apps );
    }

    /**
     * Sanitize the submitted social app credentials payload.
     *
     * @param array $social_apps Raw social app settings keyed by platform.
     * @return array Sanitized settings.
     */
    private function sanitize_social_app_settings_array( array $social_apps ) {
        $sanitized = array();

        foreach ( $social_apps as $platform => $settings ) {
            if ( ! is_string( $platform ) ) {
                continue;
            }

            $platform_key = sanitize_key( $platform );

            if ( '' === $platform_key ) {
                continue;
            }

            if ( is_object( $settings ) ) {
                $settings = (array) $settings;
            }

            if ( ! is_array( $settings ) ) {
                $sanitized[ $platform_key ] = array();
                continue;
            }

            $sanitized[ $platform_key ] = $this->sanitize_social_app_fields( $settings );
        }

        return $sanitized;
    }

    /**
     * Recursively sanitize a set of social app credential fields.
     *
     * @param array $fields Raw credential fields.
     * @return array Sanitized credential fields.
     */
    private function sanitize_social_app_fields( $fields ) {
        if ( is_object( $fields ) ) {
            $fields = (array) $fields;
        }

        if ( ! is_array( $fields ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $fields as $key => $value ) {
            if ( is_string( $key ) ) {
                $clean_key = sanitize_key( $key );

                if ( '' === $clean_key ) {
                    continue;
                }
            } else {
                $clean_key = $key;
            }

            if ( is_array( $value ) || is_object( $value ) ) {
                $clean_value = $this->sanitize_social_app_fields( $value );
            } else {
                $clean_value = sanitize_text_field( stripslashes( wp_unslash( (string) $value ) ) );
            }

            $sanitized[ $clean_key ] = $clean_value;
        }

        return $sanitized;
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
        $settings = tsap_get_option( 'tts_social_apps', array() );
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
        $settings = tsap_get_option( 'tts_social_apps', array() );
        $platform_settings = isset( $settings[$platform] ) ? $settings[$platform] : array();
        $redirect_uri = admin_url( 'admin-post.php?action=tts_oauth_' . $platform );
        $state = wp_generate_password( 20, false );
        $state_key = 'tts_oauth_state_' . sanitize_key( $platform );
        $user_id = get_current_user_id();

        if ( $user_id ) {
            update_user_meta( $user_id, $state_key, $state );
        } else {
            set_transient( $state_key . '_' . $state, $state, 15 * MINUTE_IN_SECONDS );
        }

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
                        <li><a href="#quickstart"><?php esc_html_e( 'Quickstart Packages', 'fp-publisher' ); ?></a></li>
                        <li><a href="#blog"><?php esc_html_e( 'Blog & SEO', 'fp-publisher' ); ?></a></li>
                        <li><a href="#facebook"><?php esc_html_e( 'Facebook Setup', 'fp-publisher' ); ?></a></li>
                        <li><a href="#instagram"><?php esc_html_e( 'Instagram Setup', 'fp-publisher' ); ?></a></li>
                        <li><a href="#youtube"><?php esc_html_e( 'YouTube Setup', 'fp-publisher' ); ?></a></li>
                        <li><a href="#tiktok"><?php esc_html_e( 'TikTok Setup', 'fp-publisher' ); ?></a></li>
                        <li><a href="#troubleshooting"><?php esc_html_e( 'Troubleshooting', 'fp-publisher' ); ?></a></li>
                    </ul>

                    <div class="tts-help-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-publisher-connections' ) ); ?>" class="button button-primary">
                            <?php esc_html_e( 'Configure Social Apps', 'fp-publisher' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-publisher-templates' ) ); ?>" class="button">
                            <?php esc_html_e( 'Manage Quickstart Packages', 'fp-publisher' ); ?>
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

                    <section id="quickstart">
                        <h2><?php esc_html_e( '⚙️ Quickstart Packages', 'fp-publisher' ); ?></h2>
                        <p><?php esc_html_e( 'Use curated presets to prefill the Client Wizard with Trello mappings, social templates and UTM parameters.', 'fp-publisher' ); ?></p>
                        <ol>
                            <li><?php esc_html_e( 'Open Clients → Quickstart Packages and choose the preset that matches your active usage profile.', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Click “Anteprima modifiche” to review mapping, template and UTM overrides before applying the preset.', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Resolve any blocked requirements highlighted in the readiness checklist, then apply the package.', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Launch the Client Wizard: the onboarding checklist will reuse the validation results and link back to this guide.', 'fp-publisher' ); ?></li>
                        </ol>
                        <p><?php esc_html_e( 'Tip: adjust the usage profile (Standard, Advanced, Enterprise) from Settings → Modalità di utilizzo to surface only the tools your team needs.', 'fp-publisher' ); ?></p>
                    </section>

                    <section id="blog">
                        <h2><?php esc_html_e( '📝 Blog & SEO', 'fp-publisher' ); ?></h2>
                        <p><?php esc_html_e( 'The blog prefill string accepts pipe-separated parameters such as post_type, post_status, author_id, language and SEO metadata.', 'fp-publisher' ); ?></p>
                        <ul>
                            <li><?php esc_html_e( 'Use the Quickstart preview to confirm how blog settings will be prefilled in the Client Wizard.', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Populate seo_title, meta_description and canonical_url to align WordPress articles with your editorial strategy.', 'fp-publisher' ); ?></li>
                            <li><?php esc_html_e( 'For multilingual setups include the language parameter and ensure WPML integration is configured in the publisher.', 'fp-publisher' ); ?></li>
                        </ul>
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
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        $platform = isset( $_POST['platform'] ) ? sanitize_key( wp_unslash( $_POST['platform'] ) ) : '';
        if ( empty( $platform ) ) {
            return wp_send_json_error( array( 'message' => __( 'Invalid platform specified.', 'fp-publisher' ) ), 400 );
        }

        $settings           = tsap_get_option( 'tts_social_apps', array() );
        $platform_settings  = isset( $settings[ $platform ] ) && is_array( $settings[ $platform ] ) ? $settings[ $platform ] : array();
        $result             = $this->test_platform_connection( $platform, $platform_settings );
        $sanitized_response = array( 'message' => sanitize_text_field( $result['message'] ) );

        if ( ! empty( $result['success'] ) ) {
            return wp_send_json_success( $sanitized_response );
        }

        return wp_send_json_error( $sanitized_response, 500 );
    }
    
    /**
     * AJAX handler for checking API rate limits.
     */
    public function ajax_check_rate_limits() {
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        $platform = isset( $_POST['platform'] ) ? sanitize_key( wp_unslash( $_POST['platform'] ) ) : '';
        if ( empty( $platform ) ) {
            return wp_send_json_error( __( 'Invalid platform specified.', 'fp-publisher' ), 400 );
        }

        $limits = $this->get_platform_rate_limits( $platform );

        return wp_send_json_success( $limits );
    }

    /**
     * AJAX handler for saving social media settings.
     */
    public function ajax_save_social_settings() {
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        $platform    = isset( $_POST['platform'] ) ? sanitize_key( wp_unslash( $_POST['platform'] ) ) : '';
        $credentials = isset( $_POST['credentials'] ) ? wp_unslash( $_POST['credentials'] ) : array();

        if ( empty( $platform ) ) {
            return wp_send_json_error( array( 'message' => __( 'Invalid platform specified.', 'fp-publisher' ) ), 400 );
        }

        if ( ! is_array( $credentials ) ) {
            return wp_send_json_error( array( 'message' => __( 'Invalid credentials payload.', 'fp-publisher' ) ), 400 );
        }

        $sanitized_credentials = array();

        foreach ( $credentials as $key => $value ) {
            $sanitized_key                       = sanitize_key( $key );
            $sanitized_credentials[ $sanitized_key ] = sanitize_text_field( wp_unslash( $value ) );
        }

        $social_apps                = tsap_get_option( 'tts_social_apps', array() );
        $social_apps[ $platform ]   = $sanitized_credentials;
        $previous_social_app_values = tsap_get_option( 'tts_social_apps', array() );

        $updated = tsap_update_option( 'tts_social_apps', $social_apps );

        if ( false === $updated && $previous_social_app_values !== $social_apps ) {
            return wp_send_json_error( array( 'message' => __( 'Unable to save social media credentials. Please try again.', 'fp-publisher' ) ), 500 );
        }

        return wp_send_json_success( array( 'message' => __( 'Social media credentials saved successfully.', 'fp-publisher' ) ) );
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
        $settings = tsap_get_option( 'tts_settings', array() );
        
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
        $daily_usage = tsap_get_option( 'tts_youtube_daily_usage', 0 );
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
     * Determine the maximum allowed file size for imports.
     *
     * @return int File size in bytes.
     */
    private function get_max_import_file_size() {
        $upload_limit = function_exists( 'wp_convert_hr_to_bytes' ) ? wp_convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) ) : (int) ini_get( 'upload_max_filesize' );
        $post_limit   = function_exists( 'wp_convert_hr_to_bytes' ) ? wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) ) : (int) ini_get( 'post_max_size' );

        $limits = array_filter(
            array(
                self::DEFAULT_IMPORT_MAX_FILE_SIZE,
                $upload_limit,
                $post_limit,
            ),
            function ( $value ) {
                return is_numeric( $value ) && (int) $value > 0;
            }
        );

        $limit = ! empty( $limits ) ? min( $limits ) : self::DEFAULT_IMPORT_MAX_FILE_SIZE;

        if ( function_exists( 'apply_filters' ) ) {
            /**
             * Filter the maximum allowed import file size.
             *
             * @param int $limit Maximum file size in bytes.
             */
            $limit = apply_filters( 'tts_import_max_file_size', (int) $limit );
        }

        return max( 1, (int) $limit );
    }

    public function ajax_import_data() {
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        if ( ! isset( $_FILES['import_file'] ) ) {
            return wp_send_json_error( array( 'message' => __( 'No file provided', 'fp-publisher' ) ), 400 );
        }

        $file = $_FILES['import_file'];

        $max_size = $this->get_max_import_file_size();

        if ( empty( $file['size'] ) || ! is_numeric( $file['size'] ) ) {
            return wp_send_json_error( array( 'message' => __( 'Invalid file upload.', 'fp-publisher' ) ), 400 );
        }

        if ( (int) $file['size'] > $max_size ) {
            return wp_send_json_error(
                array(
                    'message' => sprintf(
                        /* translators: %s: maximum allowed file size */
                        __( 'The uploaded file exceeds the maximum allowed size of %s.', 'fp-publisher' ),
                        size_format( $max_size )
                    ),
                ),
                400
            );
        }

        if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] ) {
            $error_message = __( 'File upload failed.', 'fp-publisher' );
            if ( isset( $file['error'] ) ) {
                switch ( $file['error'] ) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message = __( 'The uploaded file exceeds the maximum allowed size.', 'fp-publisher' );
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message = __( 'The uploaded file was only partially uploaded.', 'fp-publisher' );
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_message = __( 'No file was uploaded.', 'fp-publisher' );
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error_message = __( 'Missing a temporary folder on the server.', 'fp-publisher' );
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error_message = __( 'Failed to write the uploaded file to disk.', 'fp-publisher' );
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $error_message = __( 'File upload stopped by a PHP extension.', 'fp-publisher' );
                        break;
                    default:
                        $error_message = __( 'File upload failed due to an unknown error.', 'fp-publisher' );
                        break;
                }
            }

            return wp_send_json_error( array( 'message' => $error_message ), 400 );
        }

        if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return wp_send_json_error( array( 'message' => __( 'Invalid uploaded file.', 'fp-publisher' ) ), 400 );
        }

        if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array( 'json' => 'application/json' ) );

        if ( empty( $filetype['ext'] ) || 'json' !== $filetype['ext'] ) {
            return wp_send_json_error( array( 'message' => __( 'The uploaded file must be a valid JSON export from FP Publisher.', 'fp-publisher' ) ), 400 );
        }

        $file_contents = file_get_contents( $file['tmp_name'] );

        if ( false === $file_contents ) {
            return wp_send_json_error( array( 'message' => __( 'Unable to read the uploaded file.', 'fp-publisher' ) ), 500 );
        }

        $import_data = json_decode( $file_contents, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return wp_send_json_error( array( 'message' => __( 'Invalid JSON file', 'fp-publisher' ) ), 400 );
        }

        $import_options = array(
            'overwrite_settings'    => isset( $_POST['overwrite_settings'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['overwrite_settings'] ) ),
            'overwrite_social_apps' => isset( $_POST['overwrite_social_apps'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['overwrite_social_apps'] ) ),
            'import_clients'        => isset( $_POST['import_clients'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['import_clients'] ) ),
            'import_posts'          => isset( $_POST['import_posts'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['import_posts'] ) ),
        );

        $result = TTS_Advanced_Utils::import_data( $import_data, $import_options );

        if ( $result['success'] ) {
            return wp_send_json_success(
                array(
                    'message' => __( 'Import completed successfully', 'fp-publisher' ),
                    'log'     => $result['log'],
                )
            );
        }

        return wp_send_json_error( array( 'message' => sanitize_text_field( $result['error'] ) ), 500 );
    }

    /**
     * AJAX handler for system maintenance.
     */
    public function ajax_system_maintenance() {
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        $tasks = array(
            'optimize_database' => isset( $_POST['optimize_database'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['optimize_database'] ) ),
            'clear_cache'       => isset( $_POST['clear_cache'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['clear_cache'] ) ),
            'cleanup_logs'      => isset( $_POST['cleanup_logs'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['cleanup_logs'] ) ),
            'update_statistics' => isset( $_POST['update_statistics'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['update_statistics'] ) ),
            'check_health'      => isset( $_POST['check_health'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['check_health'] ) ),
        );

        $result = TTS_Advanced_Utils::system_maintenance( $tasks );

        if ( $result['success'] ) {
            return wp_send_json_success(
                array(
                    'message' => __( 'System maintenance completed', 'fp-publisher' ),
                    'log'     => $result['log'],
                )
            );
        }

        return wp_send_json_error( array( 'message' => __( 'Maintenance failed', 'fp-publisher' ) ), 500 );
    }
    
    /**
     * AJAX handler for system report generation.
     */
    public function ajax_generate_report() {
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        $report = TTS_Advanced_Utils::generate_system_report();

        return wp_send_json_success(
            array(
                'message' => __( 'System report generated', 'fp-publisher' ),
                'report'  => $report,
            )
        );
    }
    
    /**
     * AJAX handler for quick connection check.
     */
    public function ajax_quick_connection_check() {
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        $platform = isset( $_POST['platform'] ) ? sanitize_key( wp_unslash( $_POST['platform'] ) ) : '';

        $status_messages = array(
            'not-configured' => __( 'App credentials not configured', 'fp-publisher' ),
            'configured'     => __( 'Ready to connect accounts', 'fp-publisher' ),
            'connected'      => __( 'Account connected', 'fp-publisher' ),
            'error'          => __( 'Connection error. Please try again.', 'fp-publisher' ),
        );

        if ( empty( $platform ) ) {
            return wp_send_json_success(
                array(
                    'status'  => 'error',
                    'message' => $status_messages['error'],
                )
            );
        }

        $connection_status = $this->check_platform_connection_status( $platform );
        $status            = isset( $connection_status['status'] ) ? sanitize_key( $connection_status['status'] ) : 'error';
        $message           = isset( $connection_status['message'] ) && '' !== $connection_status['message']
            ? sanitize_text_field( $connection_status['message'] )
            : ( isset( $status_messages[ $status ] ) ? $status_messages[ $status ] : $status_messages['error'] );

        return wp_send_json_success(
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
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        $health_data = TTS_Monitoring::perform_health_check();

        return wp_send_json_success(
            array(
                'message'     => __( 'Health check completed', 'fp-publisher' ),
                'health_data' => $health_data,
            )
        );
    }
    public function ajax_show_import_modal() {
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        ob_start();
        ?>
        <div class="tts-modal-content">
            <h2><?php esc_html_e( 'Import Data', 'fp-publisher' ); ?></h2>
            <form id="tts-import-form" class="tts-ajax-form" data-ajax-action="tts_import_data" enctype="multipart/form-data">
                <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tts_ajax_nonce' ) ); ?>">
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

        return wp_send_json_success(
            array(
                'modal_html' => $modal_html,
            )
        );
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
        $stats                = TTS_Content_Source::get_source_stats();
        $trello_enabled       = tsap_get_option( 'tts_trello_enabled', 1 );
        $syncable_sources     = TTS_Content_Source::get_syncable_sources();
        $has_syncable_sources = ! empty( $syncable_sources );
        
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
        $this->render_overview_content( $stats, $has_syncable_sources );
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
     * @param bool  $has_syncable_sources Whether remote sources are available for syncing.
     */
    private function render_overview_content( $stats, $has_syncable_sources = null ) {
        $trello_enabled = tsap_get_option( 'tts_trello_enabled', 1 );

        if ( null === $has_syncable_sources ) {
            $has_syncable_sources = ! empty( TTS_Content_Source::get_syncable_sources() );
        }
        
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
        
        if ( $has_syncable_sources ) {
            echo '<button class="tts-btn primary" data-action="sync-all" data-source="all">';
            echo '<span class="dashicons dashicons-update"></span>';
            echo esc_html__( 'Sync All Sources', 'fp-publisher' );
            echo '</button>';
        } else {
            echo '<button class="tts-btn secondary" type="button" disabled aria-disabled="true">';
            echo '<span class="dashicons dashicons-lock"></span>';
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

        $is_remote_source = in_array( $source_key, array( 'trello', 'google_drive', 'dropbox' ), true );
        $sync_available   = true;

        if ( $is_remote_source ) {
            $sync_available = TTS_Content_Source::is_sync_available( $source_key );

            if ( $sync_available ) {
                echo '<button class="tts-btn primary" data-action="sync" data-source="' . esc_attr( $source_key ) . '">';
                echo '<span class="dashicons dashicons-update"></span>';
                echo esc_html__( 'Sync Now', 'fp-publisher' );
                echo '</button>';
            } else {
                echo '<button class="tts-btn secondary" type="button" disabled aria-disabled="true">';
                echo '<span class="dashicons dashicons-lock"></span>';
                echo esc_html__( 'Sync Unavailable', 'fp-publisher' );
                echo '</button>';
            }
        }

        if ( in_array( $source_key, array( 'local_upload', 'manual' ), true ) ) {
            echo '<button class="tts-btn primary" data-action="add-content" data-source="' . esc_attr( $source_key ) . '">';
            echo '<span class="dashicons dashicons-plus"></span>';
            echo esc_html__( 'Add Content', 'fp-publisher' );
            echo '</button>';
        }

        echo '</div>';
        echo '</div>';

        if ( $is_remote_source && ! $sync_available ) {
            $unavailable_message = TTS_Content_Source::get_sync_unavailable_message( $source_key );
            if ( $unavailable_message ) {
                echo '<p class="description tts-sync-disabled-note">' . esc_html( $unavailable_message ) . '</p>';
            }
        }

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
                echo '<a href="' . esc_url( self::get_social_post_editor_url( $post_id, array( 'tts_open_editor' => 1 ) ) ) . '" class="button button-small">' . esc_html__( 'Edit', 'fp-publisher' ) . '</a>';
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
                if ( TTS_Content_Source::is_sync_available( $source_key ) ) {
                    echo '<p><em>' . esc_html__( 'Try syncing to import content from this source.', 'fp-publisher' ) . '</em></p>';
                } else {
                    $message = TTS_Content_Source::get_sync_unavailable_message( $source_key );
                    if ( $message ) {
                        echo '<p><em>' . esc_html( $message ) . '</em></p>';
                    }
                }
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
        $syncable_sources = TTS_Content_Source::get_syncable_sources();

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
                window.location.href = "<?php echo esc_url( add_query_arg( array( 'page' => 'fp-publisher-queue', 'tts_open_editor' => 1, 'content_source' => 'manual' ), admin_url( 'admin.php' ) ) ); ?>";
            }

            function handleUploadFile() {
                window.location.href = "<?php echo esc_url( add_query_arg( array( 'page' => 'fp-publisher-queue', 'tts_open_editor' => 1, 'content_source' => 'local_upload' ), admin_url( 'admin.php' ) ) ); ?>";
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
            $calendar_page = $this->resolve_admin_page( 'admin.calendar_page', 'TTS_Calendar_Page' );
            if ( $calendar_page ) {
                $calendar_page->render_page();
                return;
            }
        } catch ( Exception $e ) {
            error_log( 'TTS_Admin render_calendar_page error: ' . $e->getMessage() );
        } catch ( \Throwable $throwable ) {
            error_log( 'TTS_Admin render_calendar_page error: ' . $throwable->getMessage() );
        }

        // Fallback content when calendar page class is not available
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Calendar', 'fp-publisher' ) . '</h1>';
        echo '<div class="notice notice-warning">';
        echo '<p>' . esc_html__( 'Calendar functionality is temporarily unavailable. Please refresh the page or contact support if the issue persists.', 'fp-publisher' ) . '</p>';
        echo '</div>';
        echo '<p>' . esc_html__( 'This page will display your scheduled social media posts in a calendar view.', 'fp-publisher' ) . '</p>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-dashboard' ) ) . '" class="button button-primary">' . esc_html__( 'Return to Dashboard', 'fp-publisher' ) . '</a>';
        echo '</div>';
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
            $analytics_page = $this->resolve_admin_page( 'admin.analytics_page', 'TTS_Analytics_Page' );
            if ( $analytics_page ) {
                $analytics_page->render_page();
                return;
            }
        } catch ( Exception $e ) {
            error_log( 'TTS_Admin render_analytics_page error: ' . $e->getMessage() );
        } catch ( \Throwable $throwable ) {
            error_log( 'TTS_Admin render_analytics_page error: ' . $throwable->getMessage() );
        }

        // Fallback content when analytics page class is not available
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Analytics', 'fp-publisher' ) . '</h1>';
        echo '<div class="notice notice-warning">';
        echo '<p>' . esc_html__( 'Analytics functionality is temporarily unavailable. Please refresh the page or contact support if the issue persists.', 'fp-publisher' ) . '</p>';
        echo '</div>';
        echo '<p>' . esc_html__( 'This page will display analytics and insights for your social media publishing activities.', 'fp-publisher' ) . '</p>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-dashboard' ) ) . '" class="button button-primary">' . esc_html__( 'Return to Dashboard', 'fp-publisher' ) . '</a>';
        echo '</div>';
    }

    /**
     * Delegate to health page render method.
     */
    public function render_health_page() {
        $health_page = $this->resolve_admin_page( 'admin.health_page', 'TTS_Health_Page' );
        if ( $health_page ) {
            $health_page->render_page();
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Stato', 'fp-publisher' ) . '</h1>';
        echo '<div class="notice notice-warning">';
        echo '<p>' . esc_html__( 'Il monitoraggio dello stato non è temporaneamente disponibile. Aggiorna la pagina o verifica che il plugin sia aggiornato.', 'fp-publisher' ) . '</p>';
        echo '</div>';
        echo '<p>' . esc_html__( 'Se il problema persiste contatta il supporto o controlla i log di sistema.', 'fp-publisher' ) . '</p>';
        echo '</div>';
    }

    /**
     * Delegate to log page render method.
     */
    public function render_log_page() {
        $log_page = $this->resolve_admin_page( 'admin.log_page', 'TTS_Log_Page' );
        if ( $log_page ) {
            $log_page->render_page();
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Log', 'fp-publisher' ) . '</h1>';
        echo '<div class="notice notice-warning">';
        echo '<p>' . esc_html__( 'Non è stato possibile caricare il registro eventi. Riprova più tardi o verifica i permessi del plugin.', 'fp-publisher' ) . '</p>';
        echo '</div>';
        echo '<p>' . esc_html__( 'I log recenti saranno mostrati qui per aiutarti a diagnosticare eventuali problemi di pubblicazione.', 'fp-publisher' ) . '</p>';
        echo '</div>';
    }

    /**
     * Delegate to AI features page render method.
     */
    public function render_ai_features_page() {
        $ai_page = $this->resolve_admin_page( 'admin.ai_features_page', 'TTS_AI_Features_Page' );
        if ( $ai_page ) {
            $ai_page->render_page();
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'AI & Advanced Features', 'fp-publisher' ) . '</h1>';
        echo '<div class="notice notice-warning">';
        echo '<p>' . esc_html__( 'Le funzionalità avanzate non sono disponibili in questo momento. Controlla che tutte le dipendenze siano attive e riprova.', 'fp-publisher' ) . '</p>';
        echo '</div>';
        echo '<p>' . esc_html__( 'Quando attive, qui troverai suggerimenti AI, automazioni e strumenti di ottimizzazione.', 'fp-publisher' ) . '</p>';
        echo '</div>';
    }

    /**
     * Render connection test page.
     */
    public function render_connection_test_page() {
        // Handle settings save
        if ( isset( $_POST['tts_settings_nonce'] ) && wp_verify_nonce( $_POST['tts_settings_nonce'], 'tts_settings' ) ) {
            $trello_enabled = isset( $_POST['trello_enabled'] ) ? 1 : 0;
            tsap_update_option( 'tts_trello_enabled', $trello_enabled );
            
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully.', 'fp-publisher' ) . '</p></div>';
        }
        
        $trello_enabled = tsap_get_option( 'tts_trello_enabled', 1 );
        
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
        
        $social_apps = tsap_get_option( 'tts_social_apps', array() );
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
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=fp-publisher-connections' ) ) . '" class="button">' . esc_html__( 'Configure', 'fp-publisher' ) . '</a>';
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
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        $client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;
        if ( ! $client_id ) {
            return wp_send_json_error( __( 'Invalid client ID.', 'fp-publisher' ), 400 );
        }

        $results = array();

        $trello_key   = get_post_meta( $client_id, '_tts_trello_key', true );
        $trello_token = get_post_meta( $client_id, '_tts_trello_token', true );

        if ( $trello_key && $trello_token ) {
            $results['Trello'] = $this->test_trello_connection( $trello_key, $trello_token );
        } else {
            $results['Trello'] = array(
                'success' => false,
                'message' => __( 'No Trello credentials configured', 'fp-publisher' ),
            );
        }

        $facebook_token = get_post_meta( $client_id, '_tts_fb_token', true );
        if ( $facebook_token ) {
            $results['Facebook'] = $this->test_facebook_client_connection( $facebook_token );
        } else {
            $results['Facebook'] = array(
                'success' => false,
                'message' => __( 'No Facebook token configured', 'fp-publisher' ),
            );
        }

        $instagram_token = get_post_meta( $client_id, '_tts_ig_token', true );
        if ( $instagram_token ) {
            $results['Instagram'] = $this->test_instagram_client_connection( $instagram_token );
        } else {
            $results['Instagram'] = array(
                'success' => false,
                'message' => __( 'No Instagram token configured', 'fp-publisher' ),
            );
        }

        $youtube_token = get_post_meta( $client_id, '_tts_yt_token', true );
        if ( $youtube_token ) {
            $results['YouTube'] = $this->test_youtube_client_connection( $youtube_token );
        } else {
            $results['YouTube'] = array(
                'success' => false,
                'message' => __( 'No YouTube token configured', 'fp-publisher' ),
            );
        }

        $tiktok_token = get_post_meta( $client_id, '_tts_tt_token', true );
        if ( $tiktok_token ) {
            $results['TikTok'] = $this->test_tiktok_client_connection( $tiktok_token );
        } else {
            $results['TikTok'] = array(
                'success' => false,
                'message' => __( 'No TikTok token configured', 'fp-publisher' ),
            );
        }

        return wp_send_json_success( array( 'results' => $results ) );
    }

    /**
     * AJAX handler for testing single platform connection.
     */
    public function ajax_test_single_connection() {
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        $platform = isset( $_POST['platform'] ) ? sanitize_key( wp_unslash( $_POST['platform'] ) ) : '';
        if ( ! $platform ) {
            return wp_send_json_error( __( 'Invalid platform.', 'fp-publisher' ), 400 );
        }

        $social_apps        = tsap_get_option( 'tts_social_apps', array() );
        $platform_settings  = isset( $social_apps[ $platform ] ) && is_array( $social_apps[ $platform ] ) ? $social_apps[ $platform ] : array();
        $result             = $this->test_platform_connection( $platform, $platform_settings );
        $sanitized_response = array(
            'success' => ! empty( $result['success'] ),
            'message' => isset( $result['message'] ) ? sanitize_text_field( $result['message'] ) : '',
        );

        return wp_send_json_success( $sanitized_response );
    }

    /**
     * AJAX handler to validate Trello credentials from the wizard.
     */
    public function ajax_validate_trello_credentials() {
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'tts_wizard' ) ) {
            return wp_send_json_error( __( 'Security check failed.', 'fp-publisher' ), 403 );
        }

        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
        $token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

        if ( '' === $api_key || '' === $token ) {
            return wp_send_json_success(
                array(
                    'success' => false,
                    'message' => __( 'Fornisci sia API key che token Trello.', 'fp-publisher' ),
                )
            );
        }

        $result  = $this->test_trello_connection( $api_key, $token );
        $success = ! empty( $result['success'] );
        $message = isset( $result['message'] ) ? sanitize_text_field( $result['message'] ) : '';

        if ( '' === $message ) {
            $message = $success
                ? __( 'Credenziali verificate con successo.', 'fp-publisher' )
                : __( 'Credenziali non valide. Ricontrolla key e token.', 'fp-publisher' );
        }

        return wp_send_json_success(
            array(
                'success' => $success,
                'message' => $message,
            )
        );
    }

    /**
     * AJAX handler to test OAuth tokens captured during the wizard flow.
     */
    public function ajax_test_wizard_token() {
        if ( ! $this->enforce_ajax_security( __FUNCTION__ ) ) {
            return;
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'tts_wizard' ) ) {
            return wp_send_json_error( __( 'Security check failed.', 'fp-publisher' ), 403 );
        }

        $platform = isset( $_POST['platform'] ) ? sanitize_key( wp_unslash( $_POST['platform'] ) ) : '';
        if ( '' === $platform ) {
            return wp_send_json_success(
                array(
                    'success' => false,
                    'message' => __( 'Piattaforma non valida.', 'fp-publisher' ),
                )
            );
        }

        $transient_map = array(
            'facebook'  => 'tts_oauth_facebook_token',
            'instagram' => 'tts_oauth_instagram_token',
            'youtube'   => 'tts_oauth_youtube_token',
            'tiktok'    => 'tts_oauth_tiktok_token',
        );

        if ( ! isset( $transient_map[ $platform ] ) ) {
            return wp_send_json_success(
                array(
                    'success' => false,
                    'message' => __( 'Piattaforma non supportata.', 'fp-publisher' ),
                )
            );
        }

        $token = get_transient( $transient_map[ $platform ] );
        if ( '' === trim( (string) $token ) ) {
            return wp_send_json_success(
                array(
                    'success' => false,
                    'message' => __( 'Completa prima la procedura OAuth per questo canale.', 'fp-publisher' ),
                )
            );
        }

        switch ( $platform ) {
            case 'facebook':
                $result = $this->test_facebook_client_connection( $token );
                break;
            case 'instagram':
                $result = $this->test_instagram_client_connection( $token );
                break;
            case 'youtube':
                $result = $this->test_youtube_client_connection( $token );
                break;
            case 'tiktok':
                $result = $this->test_tiktok_client_connection( $token );
                break;
            default:
                $result = array(
                    'success' => false,
                    'message' => __( 'Piattaforma non supportata.', 'fp-publisher' ),
                );
                break;
        }

        $success = ! empty( $result['success'] );
        $message = isset( $result['message'] ) ? sanitize_text_field( $result['message'] ) : '';

        if ( '' === $message ) {
            $message = $success
                ? __( 'Token valido.', 'fp-publisher' )
                : __( 'Token non valido o senza permessi sufficienti.', 'fp-publisher' );
        }

        return wp_send_json_success(
            array(
                'success' => $success,
                'message' => $message,
            )
        );
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
        $frequency_page = $this->resolve_admin_page( 'admin.frequency_status_page', 'TTS_Frequency_Status_Page' );
        if ( $frequency_page ) {
            $frequency_page->render_page();
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Publishing Status', 'fp-publisher' ) . '</h1>';
        echo '<div class="notice notice-warning">';
        echo '<p>' . esc_html__( 'Impossibile mostrare il monitor delle frequenze. Assicurati che il plugin sia aggiornato e riprova.', 'fp-publisher' ) . '</p>';
        echo '</div>';
        echo '<p>' . esc_html__( 'Quando disponibile, questa pagina indica se ogni cliente sta rispettando gli obiettivi di pubblicazione.', 'fp-publisher' ) . '</p>';
        echo '</div>';
    }

    /**
     * Resolve an admin page instance from the service container.
     *
     * @param string $service_id     Service identifier registered in the container.
     * @param string $expected_class Expected class name for validation.
     *
     * @return object|null
     */
    private function resolve_admin_page( $service_id, $expected_class ) {
        if ( ! function_exists( 'tsap_service_container' ) ) {
            return null;
        }

        if ( ! class_exists( $expected_class ) ) {
            return null;
        }

        try {
            $container = tsap_service_container();

            if ( ! $container || ! method_exists( $container, 'has' ) ) {
                return null;
            }

            if ( $container->has( $service_id ) ) {
                $instance = $container->get( $service_id );
                if ( $instance instanceof $expected_class ) {
                    return $instance;
                }
            }
        } catch ( Exception $e ) {
            error_log( sprintf( 'TTS_Admin resolve_admin_page error for %1$s: %2$s', $service_id, $e->getMessage() ) );
        } catch ( \Throwable $throwable ) {
            error_log( sprintf( 'TTS_Admin resolve_admin_page error for %1$s: %2$s', $service_id, $throwable->getMessage() ) );
        }

        return null;
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
     * Selected client filter.
     *
     * @var int
     */
    private $client_filter = 0;

    /**
     * Selected status filter.
     *
     * @var string
     */
    private $status_filter = '';

    /**
     * Search term applied to the table.
     *
     * @var string
     */
    private $search_term = '';

    /**
     * Cached list of clients for the filter dropdown.
     *
     * @var array<int, array{id:int,title:string}>
     */
    private $available_clients = array();

    /**
     * Status counts for quick views and dropdown.
     *
     * @var array<string, int>
     */
    private $status_counts = array();

    /**
     * Aggregated statuses present in the dataset.
     *
     * @var array<int, string>
     */
    private $available_statuses = array();

    /**
     * Total items matching current filters.
     *
     * @var int
     */
    private $total_items = 0;

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            array(
                'singular' => 'fp_publisher_social_post',
                'plural'   => 'fp_publisher_social_posts',
                'ajax'     => false,
            )
        );
    }

    /**
     * Shared column definitions.
     *
     * @return array<string, string>
     */
    public static function get_column_definitions() {
        return array(
            'cb'           => '<input type="checkbox" />',
            'title'        => __( 'Title', 'fp-publisher' ),
            'client'       => __( 'Client', 'fp-publisher' ),
            'channels'     => __( 'Channels', 'fp-publisher' ),
            'publish_date' => __( 'Publish At', 'fp-publisher' ),
            'status'       => __( 'Status', 'fp-publisher' ),
        );
    }

    /**
     * Retrieve table columns.
     *
     * @return array<string, string>
     */
    public function get_columns() {
        $columns = self::get_column_definitions();

        if ( ! current_user_can( 'tts_delete_social_posts' ) ) {
            unset( $columns['cb'] );
        }

        return $columns;
    }

    /**
     * Sortable columns definition.
     *
     * @return array<string, array{0:string,1:bool}>
     */
    protected function get_sortable_columns() {
        return array(
            'title'        => array( 'title', false ),
            'client'       => array( 'client', false ),
            'publish_date' => array( 'publish_date', true ),
            'status'       => array( 'status', false ),
        );
    }

    /**
     * Prepare the table items.
     */
    public function prepare_items() {
        global $wpdb;

        $this->process_bulk_action();

        $this->client_filter = isset( $_REQUEST['tts_client'] ) ? absint( $_REQUEST['tts_client'] ) : 0;
        $this->status_filter = isset( $_REQUEST['published_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['published_status'] ) ) : '';
        $this->search_term   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

        $clients = get_posts(
            array(
                'post_type'      => 'tts_client',
                'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );

        $this->available_clients = array_map(
            static function ( $client ) {
                return array(
                    'id'    => (int) $client->ID,
                    'title' => get_the_title( $client ),
                );
            },
            $clients
        );

        $this->status_counts    = $this->calculate_status_counts();
        $this->available_statuses = array_keys( array_diff_key( $this->status_counts, array( 'all' => true ) ) );
        if ( ! in_array( 'scheduled', $this->available_statuses, true ) ) {
            $this->available_statuses[] = 'scheduled';
        }
        sort( $this->available_statuses );

        $per_page     = $this->get_items_per_page( 'fp_publisher_social_posts_per_page', 20 );
        $current_page = max( 1, $this->get_pagenum() );
        $offset       = ( $current_page - 1 ) * $per_page;

        $orderby_request = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'publish_date';
        $order_request   = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';
        $order           = 'asc' === strtolower( $order_request ) ? 'ASC' : 'DESC';

        $args = array(
            'post_type'      => 'tts_social_post',
            'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
            's'              => $this->search_term,
            'fields'         => 'ids',
            'order'          => $order,
        );

        $meta_query = array();

        if ( $this->client_filter > 0 ) {
            $meta_query[] = array(
                'key'   => '_tts_client_id',
                'value' => $this->client_filter,
            );
        }

        if ( '' !== $this->status_filter ) {
            if ( 'scheduled' === $this->status_filter ) {
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_published_status',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => '_published_status',
                        'value'   => '',
                        'compare' => '=',
                    ),
                    array(
                        'key'   => '_published_status',
                        'value' => 'scheduled',
                    ),
                );
            } else {
                $meta_query[] = array(
                    'key'   => '_published_status',
                    'value' => $this->status_filter,
                );
            }
        }

        if ( ! empty( $meta_query ) ) {
            $meta_query['relation'] = 'AND';
            $args['meta_query']     = $meta_query;
        }

        switch ( $orderby_request ) {
            case 'title':
                $args['orderby'] = 'title';
                break;
            case 'client':
                $args['orderby']  = 'meta_value';
                $args['meta_key'] = '_tts_client_id';
                break;
            case 'status':
                $args['orderby']  = 'meta_value';
                $args['meta_key'] = '_published_status';
                break;
            case 'publish_date':
            default:
                $args['orderby']  = 'meta_value';
                $args['meta_key'] = '_tts_publish_at';
                $orderby_request  = 'publish_date';
                break;
        }

        $query = new WP_Query( $args );

        $this->total_items = (int) $query->found_posts;

        $items = array();
        foreach ( $query->posts as $post_id ) {
            $channels = get_post_meta( $post_id, '_tts_social_channel', true );
            $publish  = get_post_meta( $post_id, '_tts_publish_at', true );
            $status   = get_post_meta( $post_id, '_published_status', true );
            $client   = get_post_meta( $post_id, '_tts_client_id', true );

            $items[] = array(
                'ID'             => $post_id,
                'title'          => get_the_title( $post_id ),
                'client'         => $client ? get_the_title( $client ) : __( 'Unassigned', 'fp-publisher' ),
                'channels'       => $this->format_channels( $channels ),
                'publish_date'   => $publish ? $publish : '',
                'status'         => $status ? sanitize_text_field( $status ) : 'scheduled',
                'client_id'      => (int) $client,
                'raw_publish_at' => $publish,
            );
        }

        $this->items = $items;

        $columns  = $this->get_columns();
        $hidden   = array();
        if ( $this->screen ) {
            $hidden = get_hidden_columns( $this->screen );
        }
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array( $columns, $hidden, $sortable );

        $this->set_pagination_args(
            array(
                'total_items' => $this->total_items,
                'per_page'    => $per_page,
                'total_pages' => ( $per_page > 0 ) ? ceil( $this->total_items / $per_page ) : 0,
            )
        );
    }

    /**
     * Render title column with row actions.
     *
     * @param array<string, mixed> $item Current row.
     *
     * @return string
     */
    public function column_title( $item ) {
        $actions = array();

        if ( current_user_can( 'publish_posts' ) ) {
            $actions['publish'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(
                    wp_nonce_url(
                        add_query_arg(
                            array(
                                'page'   => 'fp-publisher-queue',
                                'action' => 'publish',
                                'post'   => (int) $item['ID'],
                            ),
                            admin_url( 'admin.php' )
                        ),
                        'tts_publish_social_post_' . (int) $item['ID']
                    )
                ),
                __( 'Publish Now', 'fp-publisher' )
            );
        }

        $actions['edit'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url(
                add_query_arg(
                    array(
                        'page'            => 'fp-publisher-queue',
                        'action'          => 'edit',
                        'post'            => (int) $item['ID'],
                        'tts_open_editor' => 1,
                    ),
                    admin_url( 'admin.php' )
                )
            ),
            __( 'Edit', 'fp-publisher' )
        );

        $actions['view_log'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url(
                add_query_arg(
                    array(
                        'page'   => 'fp-publisher-queue',
                        'action' => 'log',
                        'post'   => (int) $item['ID'],
                    ),
                    admin_url( 'admin.php' )
                )
            ),
            __( 'View Log', 'fp-publisher' )
        );

        if ( current_user_can( 'tts_delete_social_posts' ) ) {
            $actions['delete'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(
                    wp_nonce_url(
                        add_query_arg(
                            array(
                                'page'   => 'fp-publisher-queue',
                                'action' => 'delete',
                                'post'   => (int) $item['ID'],
                            ),
                            admin_url( 'admin.php' )
                        ),
                        'bulk-' . $this->_args['plural']
                    )
                ),
                __( 'Delete', 'fp-publisher' )
            );
        }

        return sprintf( '<strong>%1$s</strong>%2$s', esc_html( $item['title'] ), $this->row_actions( $actions ) );
    }

    /**
     * Render checkbox column for bulk actions.
     *
     * @param array<string, mixed> $item Current item.
     *
     * @return string
     */
    public function column_cb( $item ) {
        if ( ! current_user_can( 'tts_delete_social_posts' ) ) {
            return '';
        }

        return sprintf(
            '<label class="screen-reader-text" for="cb-select-%1$d">%2$s</label><input id="cb-select-%1$d" type="checkbox" name="social_post_ids[]" value="%1$d" />',
            (int) $item['ID'],
            esc_html__( 'Select social post', 'fp-publisher' )
        );
    }

    /**
     * Default column rendering.
     *
     * @param array<string, mixed> $item        Row item.
     * @param string               $column_name Column name.
     *
     * @return string
     */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'client':
                return esc_html( $item['client'] );
            case 'channels':
                return esc_html( $item['channels'] );
            case 'publish_date':
                if ( ! empty( $item['publish_date'] ) ) {
                    $timestamp = strtotime( $item['publish_date'] );
                    if ( $timestamp ) {
                        return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) );
                    }
                }
                return '&mdash;';
            case 'status':
                return esc_html( $this->format_status_label( $item['status'] ) );
            default:
                return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
        }
    }

    /**
     * Bulk actions definition.
     *
     * @return array<string, string>
     */
    protected function get_bulk_actions() {
        if ( ! current_user_can( 'tts_delete_social_posts' ) ) {
            return array();
        }

        return array(
            'delete' => __( 'Delete', 'fp-publisher' ),
        );
    }

    /**
     * Provide the list table views.
     *
     * @return array<string, string>
     */
    protected function get_views() {
        $views = array();
        $base  = remove_query_arg( array( 'published_status', 'paged' ) );

        $total = isset( $this->status_counts['all'] ) ? (int) $this->status_counts['all'] : $this->total_items;
        $views['all'] = sprintf(
            '<a href="%1$s" class="%2$s">%3$s</a>',
            esc_url( remove_query_arg( array( 'published_status' ), $base ) ),
            '' === $this->status_filter ? 'current' : '',
            sprintf(
                /* translators: %s: total number of social posts. */
                __( 'All <span class="count">(%s)</span>', 'fp-publisher' ),
                number_format_i18n( $total )
            )
        );

        $statuses = $this->status_counts;
        unset( $statuses['all'] );

        foreach ( $statuses as $status => $count ) {
            $views[ $status ] = sprintf(
                '<a href="%1$s" class="%2$s">%3$s</a>',
                esc_url( add_query_arg( 'published_status', $status, $base ) ),
                $status === $this->status_filter ? 'current' : '',
                sprintf(
                    /* translators: 1: Status label. 2: Count. */
                    __( '%1$s <span class="count">(%2$s)</span>', 'fp-publisher' ),
                    esc_html( $this->format_status_label( $status ) ),
                    number_format_i18n( (int) $count )
                )
            );
        }

        return $views;
    }

    /**
     * Render controls above the list table.
     *
     * @param string $which Top or bottom.
     */
    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }

        echo '<div class="alignleft actions">';
        echo '<label class="screen-reader-text" for="filter-by-client">' . esc_html__( 'Filter by client', 'fp-publisher' ) . '</label>';
        echo '<select name="tts_client" id="filter-by-client">';
        echo '<option value="0">' . esc_html__( 'All Clients', 'fp-publisher' ) . '</option>';
        foreach ( $this->available_clients as $client ) {
            $title = $client['title'] ? $client['title'] : sprintf( __( 'Client #%d', 'fp-publisher' ), $client['id'] );
            printf( '<option value="%1$d" %2$s>%3$s</option>', $client['id'], selected( $client['id'], $this->client_filter, false ), esc_html( $title ) );
        }
        echo '</select>';

        echo '<label class="screen-reader-text" for="filter-by-status">' . esc_html__( 'Filter by status', 'fp-publisher' ) . '</label>';
        echo '<select name="published_status" id="filter-by-status">';
        echo '<option value="">' . esc_html__( 'All Statuses', 'fp-publisher' ) . '</option>';
        foreach ( $this->available_statuses as $status ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $status ), selected( $status, $this->status_filter, false ), esc_html( $this->format_status_label( $status ) ) );
        }
        echo '</select>';

        submit_button( __( 'Filter', 'fp-publisher' ), 'secondary', 'filter_action', false );

        if ( $this->has_active_filters() ) {
            echo ' <a class="button" href="' . esc_url( remove_query_arg( array( 'tts_client', 'published_status', 's', 'paged' ) ) ) . '">' . esc_html__( 'Reset', 'fp-publisher' ) . '</a>';
        }

        echo '</div>';
    }

    /**
     * Output when the table has no items.
     */
    public function no_items() {
        esc_html_e( 'No social posts match the current filters.', 'fp-publisher' );
    }

    /**
     * Handle bulk and row actions.
     */
    public function process_bulk_action() {
        if ( 'delete' !== $this->current_action() ) {
            return;
        }

        if ( ! current_user_can( 'tts_delete_social_posts' ) ) {
            wp_die( esc_html__( 'You are not allowed to delete social posts.', 'fp-publisher' ) );
        }

        check_admin_referer( 'bulk-' . $this->_args['plural'] );

        $ids = array();

        if ( isset( $_REQUEST['post'] ) ) {
            $ids[] = absint( $_REQUEST['post'] );
        }

        if ( isset( $_REQUEST['social_post_ids'] ) && is_array( $_REQUEST['social_post_ids'] ) ) {
            foreach ( $_REQUEST['social_post_ids'] as $post_id ) {
                $ids[] = absint( $post_id );
            }
        }

        $ids = array_unique( array_filter( $ids ) );

        if ( empty( $ids ) ) {
            return;
        }

        foreach ( $ids as $post_id ) {
            wp_trash_post( $post_id );
        }

        $redirect = remove_query_arg( array( 'action', 'action2', 'social_post_ids', 'post', '_wpnonce', 'deleted' ) );
        $redirect = add_query_arg( 'deleted', count( $ids ), $redirect );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Provide a friendly summary of active filters.
     *
     * @return string
     */
    public function get_filters_summary_text() {
        $parts = array();

        if ( $this->client_filter > 0 ) {
            $client_name = '';
            foreach ( $this->available_clients as $client ) {
                if ( $client['id'] === $this->client_filter ) {
                    $client_name = $client['title'];
                    break;
                }
            }
            if ( '' === $client_name ) {
                $client_name = sprintf( __( 'Client #%d', 'fp-publisher' ), $this->client_filter );
            }

            $parts[] = sprintf(
                /* translators: %s: Client name. */
                __( 'Client: %s', 'fp-publisher' ),
                $client_name
            );
        }

        if ( '' !== $this->status_filter ) {
            $parts[] = sprintf(
                /* translators: %s: Status name. */
                __( 'Status: %s', 'fp-publisher' ),
                $this->format_status_label( $this->status_filter )
            );
        }

        if ( '' !== $this->search_term ) {
            $parts[] = sprintf(
                /* translators: %s: Search term. */
                __( 'Search: %s', 'fp-publisher' ),
                $this->search_term
            );
        }

        if ( empty( $parts ) ) {
            return __( 'No filters applied', 'fp-publisher' );
        }

        return implode( ' · ', $parts );
    }

    /**
     * Determine if filters are active.
     *
     * @return bool
     */
    private function has_active_filters() {
        return ( $this->client_filter > 0 ) || '' !== $this->status_filter || '' !== $this->search_term;
    }

    /**
     * Format stored channels into a readable string.
     *
     * @param mixed $channels Stored channels.
     *
     * @return string
     */
    private function format_channels( $channels ) {
        if ( is_array( $channels ) ) {
            $normalized = array();
            foreach ( $channels as $channel ) {
                $normalized[] = ucfirst( sanitize_text_field( $channel ) );
            }

            return implode( ', ', array_unique( $normalized ) );
        }

        return $channels ? ucfirst( sanitize_text_field( $channels ) ) : __( 'Not set', 'fp-publisher' );
    }

    /**
     * Convert a status slug into a label.
     *
     * @param string $status Status slug.
     *
     * @return string
     */
    private function format_status_label( $status ) {
        if ( '' === $status ) {
            return __( 'Scheduled', 'fp-publisher' );
        }

        $normalized = strtolower( $status );

        $labels = array(
            'scheduled' => __( 'Scheduled', 'fp-publisher' ),
            'published' => __( 'Published', 'fp-publisher' ),
            'failed'    => __( 'Failed', 'fp-publisher' ),
            'queued'    => __( 'Queued', 'fp-publisher' ),
            'draft'     => __( 'Draft', 'fp-publisher' ),
            'processing'=> __( 'Processing', 'fp-publisher' ),
        );

        if ( isset( $labels[ $normalized ] ) ) {
            return $labels[ $normalized ];
        }

        $normalized = str_replace( array( '-', '_' ), ' ', $normalized );

        return ucwords( $normalized );
    }

    /**
     * Calculate status counts for views.
     *
     * @return array<string, int>
     */
    private function calculate_status_counts() {
        global $wpdb;

        $joins  = array();
        $where  = array(
            "p.post_type = 'tts_social_post'",
            "p.post_status NOT IN ('trash', 'auto-draft')",
        );
        $params = array();

        $joins[] = "LEFT JOIN {$wpdb->postmeta} pm_status ON pm_status.post_id = p.ID AND pm_status.meta_key = '_published_status'";

        if ( $this->client_filter > 0 ) {
            $joins[] = "INNER JOIN {$wpdb->postmeta} pm_client ON pm_client.post_id = p.ID AND pm_client.meta_key = '_tts_client_id'";
            $where[] = 'pm_client.meta_value = %s';
            $params[] = (string) $this->client_filter;
        }

        if ( '' !== $this->search_term ) {
            $like   = '%' . $wpdb->esc_like( $this->search_term ) . '%';
            $where[] = '(p.post_title LIKE %s OR p.post_content LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $join_sql  = implode( ' ', $joins );
        $where_sql = 'WHERE ' . implode( ' AND ', $where );

        $sql = "SELECT CASE WHEN pm_status.meta_value IS NULL OR pm_status.meta_value = '' THEN 'scheduled' ELSE pm_status.meta_value END AS normalized_status, COUNT(DISTINCT p.ID) AS total FROM {$wpdb->posts} p {$join_sql} {$where_sql} GROUP BY normalized_status";

        $records = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

        $counts = array( 'all' => 0 );

        foreach ( (array) $records as $record ) {
            $status            = $record['normalized_status'] ? sanitize_text_field( $record['normalized_status'] ) : 'scheduled';
            $counts[ $status ] = isset( $counts[ $status ] ) ? $counts[ $status ] + (int) $record['total'] : (int) $record['total'];
            $counts['all']    += (int) $record['total'];
        }

        return $counts;
    }
}
