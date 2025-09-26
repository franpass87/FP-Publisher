<?php
/**
 * Simple template renderer for admin pages.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides rendering helpers for admin templates.
 */
class TTS_Admin_View_Helper {

	/**
	 * Base directory containing template files.
	 *
	 * @var string
	 */
	private $base_dir;

	/**
	 * Constructor.
	 *
	 * @param string|null $base_dir Optional base directory.
	 */
	public function __construct( $base_dir = null ) {
		if ( null === $base_dir ) {
			$base_dir = trailingslashit( TSAP_PLUGIN_DIR ) . 'admin/views/';
		}

		$this->base_dir = trailingslashit( $base_dir );
	}

	/**
	 * Render a template and return the generated HTML.
	 *
	 * @param string               $template Template relative path without extension.
	 * @param array<string, mixed> $data     Variables passed to the template.
	 *
	 * @return string Rendered HTML.
	 */
	public function render( $template, array $data = array() ) {
		$template_path = $this->base_dir . $template . '.php';

		if ( ! file_exists( $template_path ) ) {
			return '';
		}

		if ( ! empty( $data ) ) {
			extract( $data, EXTR_SKIP );
		}

		ob_start();
		include $template_path;

		return (string) ob_get_clean();
	}
}
