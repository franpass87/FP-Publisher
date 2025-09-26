<?php
/**
 * Controller responsible for admin menu registration.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the FP Publisher admin navigation.
 */
class TTS_Admin_Menu_Controller {

	/**
	 * Main admin service.
	 *
	 * @var TTS_Admin
	 */
	private $admin;

	/**
	 * Constructor.
	 *
	 * @param TTS_Admin $admin Admin facade.
	 */
	public function __construct( TTS_Admin $admin ) {
		$this->admin = $admin;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
	}
}
