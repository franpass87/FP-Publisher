<?php
/**
 * Asset manifest loader for hashed admin bundles.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class to resolve built asset URLs and versions using the generated manifest.
 */
class TTS_Asset_Manager {

	/**
	 * Relative path to the generated manifest file.
	 */
	const MANIFEST_PATH = 'admin/dist/manifest.json';

	/**
	 * Main plugin file used for URL resolution.
	 */
	const MAIN_FILE = 'trello-social-auto-publisher.php';

	/**
	 * Cached manifest contents.
	 *
	 * @var array|null
	 */
	private static $manifest = null;

	/**
	 * Register and enqueue a style using the manifest metadata.
	 *
	 * @param string $handle Handle for the stylesheet.
	 * @param string $relative_path Relative asset path (e.g. 'admin/css/tts-core.css').
	 * @param array  $deps Optional dependencies.
	 * @param string $media Media attribute value.
	 *
	 * @return bool Whether the style was enqueued.
	 */
	public static function enqueue_style( $handle, $relative_path, array $deps = array(), $media = 'all' ) {
		$asset = self::get_asset_metadata( $relative_path );

		if ( ! $asset ) {
			return false;
		}

		wp_enqueue_style( $handle, $asset['url'], $deps, $asset['version'], $media );
		return true;
	}

	/**
	 * Register a script handle using manifest metadata.
	 *
	 * @param string $handle Handle for the script.
	 * @param string $relative_path Relative asset path (e.g. 'admin/js/tts-core.js').
	 * @param array  $deps Optional dependencies.
	 * @param bool   $in_footer Whether to load the script in the footer.
	 *
	 * @return bool Whether the script was registered.
	 */
	public static function register_script( $handle, $relative_path, array $deps = array(), $in_footer = true ) {
		$asset = self::get_asset_metadata( $relative_path );

		if ( ! $asset ) {
			return false;
		}

		wp_register_script( $handle, $asset['url'], $deps, $asset['version'], $in_footer );
		return true;
	}

	/**
	 * Register and enqueue a script using manifest metadata.
	 *
	 * @param string $handle Handle for the script.
	 * @param string $relative_path Relative asset path.
	 * @param array  $deps Optional dependencies.
	 * @param bool   $in_footer Whether to load in footer.
	 *
	 * @return bool Whether the script was enqueued.
	 */
	public static function enqueue_script( $handle, $relative_path, array $deps = array(), $in_footer = true ) {
		if ( ! self::register_script( $handle, $relative_path, $deps, $in_footer ) ) {
			return false;
		}

		wp_enqueue_script( $handle );
		return true;
	}

	/**
	 * Resolve manifest metadata for a logical asset path.
	 *
	 * @param string $relative_path Relative path within the plugin.
	 *
	 * @return array|null
	 */
	public static function get_asset_metadata( $relative_path ) {
		$relative_path = self::normalize_path( $relative_path );
		if ( '' === $relative_path ) {
			return null;
		}

		$manifest  = self::load_manifest();
		$mapped    = isset( $manifest[ $relative_path ] ) ? self::normalize_path( $manifest[ $relative_path ] ) : $relative_path;
		$base_path = rtrim( TSAP_PLUGIN_DIR, '/\\' ) . '/';
		$absolute  = $base_path . $mapped;

		if ( ! file_exists( $absolute ) ) {
			$mapped   = $relative_path;
			$absolute = $base_path . $mapped;

			if ( ! file_exists( $absolute ) ) {
				return null;
			}
		}

		$url = plugins_url( $mapped, TSAP_PLUGIN_DIR . self::MAIN_FILE );

		return array(
			'relative_path' => $mapped,
			'path'          => $absolute,
			'url'           => $url,
			'version'       => self::derive_version( $mapped, $absolute ),
		);
	}

	/**
	 * Reset the cached manifest. Useful after clearing build artifacts.
	 */
	public static function reset() {
		self::$manifest = null;
	}

	/**
	 * Normalize a relative path string.
	 *
	 * @param string $path Relative path to normalize.
	 *
	 * @return string
	 */
	private static function normalize_path( $path ) {
		$path = str_replace( '\\', '/', (string) $path );
		return ltrim( $path, '/' );
	}

	/**
	 * Load manifest file from disk (if present).
	 *
	 * @return array
	 */
	private static function load_manifest() {
		if ( is_array( self::$manifest ) ) {
			return self::$manifest;
		}

		$manifest_path = TSAP_PLUGIN_DIR . self::MANIFEST_PATH;
		if ( ! file_exists( $manifest_path ) ) {
			self::$manifest = array();
			return self::$manifest;
		}

		$contents = file_get_contents( $manifest_path );
		if ( false === $contents ) {
			self::$manifest = array();
			return self::$manifest;
		}

		$decoded        = json_decode( $contents, true );
		self::$manifest = is_array( $decoded ) ? $decoded : array();
		return self::$manifest;
	}

	/**
	 * Determine a cache-busting version for an asset.
	 *
	 * @param string $relative_path Relative asset path.
	 * @param string $absolute_path Absolute filesystem path.
	 *
	 * @return string
	 */
	private static function derive_version( $relative_path, $absolute_path ) {
		if ( preg_match( '/-([A-Za-z0-9]{6,})\.[^.]+$/', $relative_path, $matches ) ) {
			return $matches[1];
		}

		$mtime = @filemtime( $absolute_path );
		if ( $mtime ) {
			return (string) $mtime;
		}

		return (string) time();
	}
}
