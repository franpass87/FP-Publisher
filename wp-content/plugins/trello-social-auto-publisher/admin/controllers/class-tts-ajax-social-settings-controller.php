<?php
/**
 * Controller responsible for AJAX actions related to social settings.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Delegate social settings AJAX requests to the main admin service.
 */
class TTS_Ajax_Social_Settings_Controller {

    /**
     * Admin facade.
     *
     * @var TTS_Admin
     */
    private $admin;

    /**
     * Shared AJAX security helper.
     *
     * @var TTS_Admin_Ajax_Security
     */
    private $ajax_security;

    /**
     * Constructor.
     *
     * @param TTS_Admin               $admin         Admin facade.
     * @param TTS_Admin_Ajax_Security $ajax_security AJAX security helper.
     */
    public function __construct( TTS_Admin $admin, TTS_Admin_Ajax_Security $ajax_security ) {
        $this->admin         = $admin;
        $this->ajax_security = $ajax_security;
    }

    /**
     * Register AJAX hooks.
     *
     * @return void
     */
    public function register_hooks() {
        add_action( 'wp_ajax_tts_test_connection', array( $this->admin, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_tts_check_rate_limits', array( $this->admin, 'ajax_check_rate_limits' ) );
        add_action( 'wp_ajax_tts_save_social_settings', array( $this->admin, 'ajax_save_social_settings' ) );
        add_action( 'wp_ajax_tts_quick_connection_check', array( $this->admin, 'ajax_quick_connection_check' ) );
        add_action( 'wp_ajax_tts_refresh_health', array( $this->admin, 'ajax_refresh_health' ) );
        add_action( 'wp_ajax_tts_test_client_connections', array( $this->admin, 'ajax_test_client_connections' ) );
        add_action( 'wp_ajax_tts_test_single_connection', array( $this->admin, 'ajax_test_single_connection' ) );
        add_action( 'wp_ajax_tts_validate_trello_credentials', array( $this->admin, 'ajax_validate_trello_credentials' ) );
        add_action( 'wp_ajax_tts_test_wizard_token', array( $this->admin, 'ajax_test_wizard_token' ) );
    }

    /**
     * Expose the shared security helper for downstream tests.
     *
     * @return TTS_Admin_Ajax_Security
     */
    public function get_ajax_security() {
        return $this->ajax_security;
    }
}
