<?php
/**
 * Settings page for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the plugin settings.
 */
class TTS_Settings {

	/**
	 * Cached plugin settings.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Initialize hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the settings page.
	 */
	public function add_menu() {
		// We no longer add a separate settings page since it's now integrated
		// into the main plugin menu structure
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public function register_settings() {
		register_setting(
			'tts_settings_group',
			'tts_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'tts_sanitize_settings',
				'default'           => array(),
				'capability'        => 'manage_options',
			)
		);

		$channels   = array( 'facebook', 'instagram', 'youtube', 'tiktok' );
		$utm_params = array( 'utm_source', 'utm_medium', 'utm_campaign' );

		// Trello API credentials.
		add_settings_section(
			'tts_trello_api',
			__( 'Trello API Credentials', 'fp-publisher' ),
			'__return_false',
			'tts_settings'
		);

		add_settings_field(
			'trello_api_key',
			__( 'API Key', 'fp-publisher' ),
			array( $this, 'render_trello_api_key_field' ),
			'tts_settings',
			'tts_trello_api',
			array(
				'label_for' => $this->get_field_id( 'trello_api_key' ),
			)
		);

		add_settings_field(
			'trello_api_token',
			__( 'API Token', 'fp-publisher' ),
			array( $this, 'render_trello_api_token_field' ),
			'tts_settings',
			'tts_trello_api',
			array(
				'label_for' => $this->get_field_id( 'trello_api_token' ),
			)
		);

		// Column mapping.
		add_settings_section(
			'tts_column_mapping',
			__( 'Trello Column Mapping', 'fp-publisher' ),
			'__return_false',
			'tts_settings'
		);

		add_settings_field(
			'column_mapping',
			__( 'Column Mapping (JSON)', 'fp-publisher' ),
			array( $this, 'render_column_mapping_field' ),
			'tts_settings',
			'tts_column_mapping',
			array(
				'label_for' => $this->get_field_id( 'column_mapping' ),
			)
		);

		// Usage modes.
		add_settings_section(
			'tts_usage_modes',
			__( 'Modalità di utilizzo', 'fp-publisher' ),
			array( $this, 'render_usage_modes_intro' ),
			'tts_settings'
		);

		add_settings_field(
			'usage_profile',
			__( 'Interfaccia preferita', 'fp-publisher' ),
			array( $this, 'render_usage_profile_field' ),
			'tts_settings',
			'tts_usage_modes',
			array(
				'label_for' => $this->get_field_id( 'usage_profile_standard' ),
			)
		);

		// Social access token.
		add_settings_section(
			'tts_social_token',
			__( 'Social Access Token', 'fp-publisher' ),
			'__return_false',
			'tts_settings'
		);

		add_settings_field(
			'social_access_token',
			__( 'Access Token', 'fp-publisher' ),
			array( $this, 'render_social_access_token_field' ),
			'tts_settings',
			'tts_social_token',
			array(
				'label_for' => $this->get_field_id( 'social_access_token' ),
			)
		);

		// Scheduling options.
		add_settings_section(
			'tts_scheduling_options',
			__( 'Scheduling Options', 'fp-publisher' ),
			'__return_false',
			'tts_settings'
		);

		foreach ( $channels as $channel ) {
			add_settings_field(
				$channel . '_offset',
				sprintf( __( '%s Offset (minutes)', 'fp-publisher' ), ucfirst( $channel ) ),
				array( $this, 'render_offset_field' ),
				'tts_settings',
				'tts_scheduling_options',
				array(
					'channel'   => $channel,
					'label_for' => $this->get_field_id( $channel . '_offset' ),
				)
			);
		}

		// Default location options.
		add_settings_section(
			'tts_location_options',
			__( 'Default Location', 'fp-publisher' ),
			'__return_false',
			'tts_settings'
		);

		add_settings_field(
			'default_lat',
			__( 'Default Latitude', 'fp-publisher' ),
			array( $this, 'render_default_lat_field' ),
			'tts_settings',
			'tts_location_options',
			array(
				'label_for' => $this->get_field_id( 'default_lat' ),
			)
		);

		add_settings_field(
			'default_lng',
			__( 'Default Longitude', 'fp-publisher' ),
			array( $this, 'render_default_lng_field' ),
			'tts_settings',
			'tts_location_options',
			array(
				'label_for' => $this->get_field_id( 'default_lng' ),
			)
		);

		// Image size options.
		add_settings_section(
			'tts_image_sizes',
			__( 'Image Sizes', 'fp-publisher' ),
			'__return_false',
			'tts_settings'
		);

		foreach ( $channels as $channel ) {
			add_settings_field(
				$channel . '_size',
				sprintf( __( '%s Size (WxH)', 'fp-publisher' ), ucfirst( $channel ) ),
				array( $this, 'render_image_size_field' ),
				'tts_settings',
				'tts_image_sizes',
				array(
					'channel'   => $channel,
					'label_for' => $this->get_field_id( $channel . '_size' ),
				)
			);
		}

		// Media processing options.
		add_settings_section(
			'tts_media_processing',
			__( 'Media Processing', 'fp-publisher' ),
			'__return_false',
			'tts_settings'
		);

		add_settings_field(
			'media_transcoder_path',
			__( 'FFmpeg Binary Path', 'fp-publisher' ),
			array( $this, 'render_media_transcoder_path_field' ),
			'tts_settings',
			'tts_media_processing',
			array(
				'label_for' => $this->get_field_id( 'media_transcoder_path' ),
			)
		);

		// UTM options.
		add_settings_section(
			'tts_utm_options',
			__( 'UTM Options', 'fp-publisher' ),
			'__return_false',
			'tts_settings'
		);

		foreach ( $channels as $channel ) {
			foreach ( $utm_params as $param ) {
				add_settings_field(
					$channel . '_' . $param,
					sprintf( __( '%1$s UTM %2$s', 'fp-publisher' ), ucfirst( $channel ), ucfirst( str_replace( 'utm_', '', $param ) ) ),
					array( $this, 'render_utm_field' ),
					'tts_settings',
					'tts_utm_options',
					array(
						'channel'   => $channel,
						'param'     => $param,
						'label_for' => $this->get_field_id( 'utm_' . $channel . '_' . $param ),
					)
				);
			}
		}

		// Template options.
		add_settings_section(
			'tts_template_options',
			__( 'Template Options', 'fp-publisher' ),
			'__return_false',
			'tts_settings'
		);

		add_settings_field(
			'facebook_template',
			__( 'Facebook Template', 'fp-publisher' ),
			array( $this, 'render_facebook_template_field' ),
			'tts_settings',
			'tts_template_options',
			array(
				'label_for' => $this->get_field_id( 'facebook_template' ),
			)
		);

		add_settings_field(
			'instagram_template',
			__( 'Instagram Template', 'fp-publisher' ),
			array( $this, 'render_instagram_template_field' ),
			'tts_settings',
			'tts_template_options',
			array(
				'label_for' => $this->get_field_id( 'instagram_template' ),
			)
		);

		add_settings_field(
			'youtube_template',
			__( 'YouTube Template', 'fp-publisher' ),
			array( $this, 'render_youtube_template_field' ),
			'tts_settings',
			'tts_template_options',
			array(
				'label_for' => $this->get_field_id( 'youtube_template' ),
			)
		);

		add_settings_field(
			'tiktok_template',
			__( 'TikTok Template', 'fp-publisher' ),
			array( $this, 'render_tiktok_template_field' ),
			'tts_settings',
			'tts_template_options',
			array(
				'label_for' => $this->get_field_id( 'tiktok_template' ),
			)
		);

		add_settings_field(
			'labels_as_hashtags',
			__( 'Labels as Hashtags', 'fp-publisher' ),
			array( $this, 'render_labels_as_hashtags_field' ),
			'tts_settings',
			'tts_template_options',
			array(
				'label_for' => $this->get_field_id( 'labels_as_hashtags' ),
			)
		);

		// URL shortener options.
		add_settings_section(
			'tts_url_shortener',
			__( 'URL Shortener', 'fp-publisher' ),
			'__return_false',
			'tts_settings'
		);

		add_settings_field(
			'url_shortener',
			__( 'URL Shortener', 'fp-publisher' ),
			array( $this, 'render_url_shortener_field' ),
			'tts_settings',
			'tts_url_shortener',
			array(
				'label_for' => $this->get_field_id( 'url_shortener' ),
			)
		);

		add_settings_field(
			'bitly_token',
			__( 'Bitly Token', 'fp-publisher' ),
			array( $this, 'render_bitly_token_field' ),
			'tts_settings',
			'tts_url_shortener',
			array(
				'label_for' => $this->get_field_id( 'bitly_token' ),
			)
		);

		// Notification options.
		add_settings_section(
			'tts_notification_options',
			__( 'Notification Options', 'fp-publisher' ),
			'__return_false',
			'tts_settings'
		);

		add_settings_field(
			'slack_webhook',
			__( 'Slack Webhook', 'fp-publisher' ),
			array( $this, 'render_slack_webhook_field' ),
			'tts_settings',
			'tts_notification_options',
			array(
				'label_for' => $this->get_field_id( 'slack_webhook' ),
			)
		);

		add_settings_field(
			'notification_emails',
			__( 'Notification Emails', 'fp-publisher' ),
			array( $this, 'render_notification_emails_field' ),
			'tts_settings',
			'tts_notification_options',
			array(
				'label_for' => $this->get_field_id( 'notification_emails' ),
			)
		);

		// Logging options.
		add_settings_section(
			'tts_logging_options',
			__( 'Logging Options', 'fp-publisher' ),
			'__return_false',
			'tts_settings'
		);

		add_settings_field(
			'log_retention_days',
			__( 'Log Retention (days)', 'fp-publisher' ),
			array( $this, 'render_log_retention_days_field' ),
			'tts_settings',
			'tts_logging_options',
			array(
				'label_for' => $this->get_field_id( 'log_retention_days' ),
			)
		);
	}

	/**
	 * Retrieve cached plugin settings.
	 *
	 * @return array
	 */
	private function get_settings() {
		if ( null === $this->settings ) {
			$stored         = tsap_get_option( 'tts_settings', array() );
			$this->settings = is_array( $stored ) ? $stored : array();
		}

		return $this->settings;
	}

	/**
	 * Retrieve a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value when the key is missing.
	 * @return mixed
	 */
	private function get_setting( $key, $default = '' ) {
		$settings = $this->get_settings();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Build a stable field identifier for markup binding.
	 *
	 * @param string $suffix Field suffix.
	 * @return string
	 */
	private function get_field_id( $suffix ) {
		return 'tts_settings_' . sanitize_key( $suffix );
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'fp-publisher' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Trello Social Settings', 'fp-publisher' ); ?></h1>
			<?php settings_errors( 'tts_settings' ); ?>
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
	 * Render field for Trello API key.
	 */
	public function render_trello_api_key_field() {
		$field_id = $this->get_field_id( 'trello_api_key' );
		$value    = $this->get_setting( 'trello_api_key', '' );

		printf(
			'<input type="text" id="%1$s" name="tts_settings[trello_api_key]" value="%2$s" class="regular-text" autocomplete="off" />',
			esc_attr( $field_id ),
			esc_attr( $value )
		);
	}

	/**
	 * Render field for Trello API token.
	 */
	public function render_trello_api_token_field() {
		$field_id = $this->get_field_id( 'trello_api_token' );
		$value    = $this->get_setting( 'trello_api_token', '' );

		printf(
			'<input type="text" id="%1$s" name="tts_settings[trello_api_token]" value="%2$s" class="regular-text" autocomplete="off" />',
			esc_attr( $field_id ),
			esc_attr( $value )
		);
	}

	/**
	 * Render field for column mapping.
	 */
	public function render_column_mapping_field() {
		$field_id       = $this->get_field_id( 'column_mapping' );
		$description_id = $field_id . '-description';
		$value          = $this->get_setting( 'column_mapping', '' );

		printf(
			'<textarea id="%1$s" name="tts_settings[column_mapping]" rows="5" cols="50" class="large-text code" aria-describedby="%2$s">%3$s</textarea>',
			esc_attr( $field_id ),
			esc_attr( $description_id ),
			esc_textarea( $value )
		);

		printf(
			'<p id="%1$s" class="description">%2$s</p>',
			esc_attr( $description_id ),
			esc_html__( 'Provide a JSON map of Trello column identifiers to readable names.', 'fp-publisher' )
		);
	}

	/**
	 * Render field for social access token.
	 */
	public function render_social_access_token_field() {
		$field_id = $this->get_field_id( 'social_access_token' );
		$value    = $this->get_setting( 'social_access_token', '' );

		printf(
			'<input type="password" id="%1$s" name="tts_settings[social_access_token]" value="%2$s" class="regular-text" autocomplete="new-password" />',
			esc_attr( $field_id ),
			esc_attr( $value )
		);
	}

	/**
	 * Render introductory copy for the usage modes section.
	 */
	public function render_usage_modes_intro() {
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Seleziona il profilo che meglio rappresenta il tuo team per mostrare solo gli strumenti necessari.', 'fp-publisher' )
		);
	}

	/**
	 * Render usage profile selector field.
	 */
	public function render_usage_profile_field() {
		$current = sanitize_key( $this->get_setting( 'usage_profile', 'standard' ) );

		$choices = array(
			'standard'   => array(
				'label'       => __( 'Profilo Standard', 'fp-publisher' ),
				'description' => __( 'Mostra un set di strumenti essenziali per piccoli team o chi è alle prime armi.', 'fp-publisher' ),
			),
			'advanced'   => array(
				'label'       => __( 'Profilo Avanzato', 'fp-publisher' ),
				'description' => __( 'Aggiunge monitoraggio approfondito, suggerimenti operativi e automazioni aggiuntive.', 'fp-publisher' ),
			),
			'enterprise' => array(
				'label'       => __( 'Profilo Enterprise', 'fp-publisher' ),
				'description' => __( 'Sblocca audit trail completi, controlli di sicurezza e preset multi-brand.', 'fp-publisher' ),
			),
		);

		echo '<fieldset class="tts-usage-profile">';
		foreach ( $choices as $key => $choice ) {
			$input_id = $this->get_field_id( 'usage_profile_' . $key );
			printf(
				'<label for="%1$s" class="tts-usage-profile__option"><input type="radio" id="%1$s" name="tts_settings[usage_profile]" value="%2$s"%3$s /> <strong>%4$s</strong><br /><span class="description">%5$s</span></label>',
				esc_attr( $input_id ),
				esc_attr( $key ),
				checked( $current, $key, false ),
				esc_html( $choice['label'] ),
				esc_html( $choice['description'] )
			);
		}
		echo '</fieldset>';
	}

	/**
	 * Render scheduling offset field for a channel.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_offset_field( $args ) {
		$channel = isset( $args['channel'] ) ? sanitize_key( $args['channel'] ) : '';

		if ( '' === $channel ) {
			return;
		}

		$key      = $channel . '_offset';
		$field_id = $this->get_field_id( $key );
		$value    = absint( $this->get_setting( $key, 0 ) );

		printf(
			'<input type="number" id="%1$s" name="tts_settings[%2$s]" value="%3$s" class="small-text" min="0" step="1" />',
			esc_attr( $field_id ),
			esc_attr( $key ),
			esc_attr( $value )
		);
	}

	/**
	 * Render field for default latitude.
	 */
	public function render_default_lat_field() {
		$field_id = $this->get_field_id( 'default_lat' );
		$value    = $this->get_setting( 'default_lat', '' );

		printf(
			'<input type="number" id="%1$s" name="tts_settings[default_lat]" value="%2$s" class="regular-text" step="any" />',
			esc_attr( $field_id ),
			esc_attr( $value )
		);
	}

	/**
	 * Render field for default longitude.
	 */
	public function render_default_lng_field() {
		$field_id = $this->get_field_id( 'default_lng' );
		$value    = $this->get_setting( 'default_lng', '' );

		printf(
			'<input type="number" id="%1$s" name="tts_settings[default_lng]" value="%2$s" class="regular-text" step="any" />',
			esc_attr( $field_id ),
			esc_attr( $value )
		);
	}

	/**
	 * Render image size field for a channel.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_image_size_field( $args ) {
		$channel = isset( $args['channel'] ) ? sanitize_key( $args['channel'] ) : '';

		if ( '' === $channel ) {
			return;
		}

		$key        = $channel . '_size';
		$field_id   = $this->get_field_id( $key );
		$defaults   = array(
			'facebook'  => '1080x1350',
			'instagram' => '1080x1350',
			'youtube'   => '1080x1920',
			'tiktok'    => '1080x1920',
		);
		$placeholder = isset( $defaults[ $channel ] ) ? $defaults[ $channel ] : '';
		$value       = $this->get_setting( $key, '' );

		printf(
			'<input type="text" id="%1$s" name="tts_settings[%2$s]" value="%3$s" class="regular-text" placeholder="%4$s" />',
			esc_attr( $field_id ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);
	}

	/**
	 * Render field for media transcoder path.
	 */
	public function render_media_transcoder_path_field() {
		$field_id       = $this->get_field_id( 'media_transcoder_path' );
		$description_id = $field_id . '-description';
		$value          = $this->get_setting( 'media_transcoder_path', '' );

		printf(
			'<input type="text" id="%1$s" name="tts_settings[media_transcoder_path]" value="%2$s" class="regular-text" placeholder="/usr/bin/ffmpeg" aria-describedby="%3$s" />',
			esc_attr( $field_id ),
			esc_attr( $value ),
			esc_attr( $description_id )
		);

		printf(
			'<p id="%1$s" class="description">%2$s</p>',
			esc_attr( $description_id ),
			esc_html__( 'Provide the absolute path to the ffmpeg binary used for video compression.', 'fp-publisher' )
		);
	}

	/**
	 * Render a UTM field for a given channel and parameter.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_utm_field( $args ) {
		$channel = isset( $args['channel'] ) ? sanitize_key( $args['channel'] ) : '';
		$param   = isset( $args['param'] ) ? sanitize_key( $args['param'] ) : '';

		if ( '' === $channel || '' === $param ) {
			return;
		}

		$field_id = $this->get_field_id( 'utm_' . $channel . '_' . $param );
		$settings = $this->get_settings();
		$value    = isset( $settings['utm'][ $channel ][ $param ] ) ? $settings['utm'][ $channel ][ $param ] : '';

		printf(
			'<input type="text" id="%1$s" name="tts_settings[utm][%2$s][%3$s]" value="%4$s" class="regular-text" />',
			esc_attr( $field_id ),
			esc_attr( $channel ),
			esc_attr( $param ),
			esc_attr( $value )
		);
	}

	/**
	 * Render field for Facebook template.
	 */
	public function render_facebook_template_field() {
		$field_id       = $this->get_field_id( 'facebook_template' );
		$description_id = $field_id . '-description';
		$value          = $this->get_setting( 'facebook_template', '' );

		printf(
			'<input type="text" id="%1$s" name="tts_settings[facebook_template]" value="%2$s" class="regular-text" placeholder="{title} {url} {due}" aria-describedby="%3$s" />',
			esc_attr( $field_id ),
			esc_attr( $value ),
			esc_attr( $description_id )
		);

		printf(
			'<p id="%1$s" class="description">%2$s</p>',
			esc_attr( $description_id ),
			esc_html__( 'Available placeholders: {title}, {url}, {due}, {labels}, {client_name}', 'fp-publisher' )
		);
	}

	/**
	 * Render field for Instagram template.
	 */
	public function render_instagram_template_field() {
		$field_id       = $this->get_field_id( 'instagram_template' );
		$description_id = $field_id . '-description';
		$value          = $this->get_setting( 'instagram_template', '' );

		printf(
			'<input type="text" id="%1$s" name="tts_settings[instagram_template]" value="%2$s" class="regular-text" placeholder="{title} {url} {due}" aria-describedby="%3$s" />',
			esc_attr( $field_id ),
			esc_attr( $value ),
			esc_attr( $description_id )
		);

		printf(
			'<p id="%1$s" class="description">%2$s</p>',
			esc_attr( $description_id ),
			esc_html__( 'Available placeholders: {title}, {url}, {due}, {labels}, {client_name}', 'fp-publisher' )
		);
	}

	/**
	 * Render field for YouTube template.
	 */
	public function render_youtube_template_field() {
		$field_id       = $this->get_field_id( 'youtube_template' );
		$description_id = $field_id . '-description';
		$value          = $this->get_setting( 'youtube_template', '' );

		printf(
			'<input type="text" id="%1$s" name="tts_settings[youtube_template]" value="%2$s" class="regular-text" placeholder="{title} {url} {due}" aria-describedby="%3$s" />',
			esc_attr( $field_id ),
			esc_attr( $value ),
			esc_attr( $description_id )
		);

		printf(
			'<p id="%1$s" class="description">%2$s</p>',
			esc_attr( $description_id ),
			esc_html__( 'Available placeholders: {title}, {url}, {due}, {labels}, {client_name}', 'fp-publisher' )
		);
	}

	/**
	 * Render field for TikTok template.
	 */
	public function render_tiktok_template_field() {
		$field_id       = $this->get_field_id( 'tiktok_template' );
		$description_id = $field_id . '-description';
		$value          = $this->get_setting( 'tiktok_template', '' );

		printf(
			'<input type="text" id="%1$s" name="tts_settings[tiktok_template]" value="%2$s" class="regular-text" placeholder="{title} {url} {due}" aria-describedby="%3$s" />',
			esc_attr( $field_id ),
			esc_attr( $value ),
			esc_attr( $description_id )
		);

		printf(
			'<p id="%1$s" class="description">%2$s</p>',
			esc_attr( $description_id ),
			esc_html__( 'Available placeholders: {title}, {url}, {due}, {labels}, {client_name}', 'fp-publisher' )
		);
	}

	/**
	 * Render field for labels-as-hashtags option.
	 */
	public function render_labels_as_hashtags_field() {
		$field_id = $this->get_field_id( 'labels_as_hashtags' );
		$checked  = ! empty( $this->get_setting( 'labels_as_hashtags', 0 ) );

		printf(
			'<label for="%1$s"><input type="checkbox" id="%1$s" name="tts_settings[labels_as_hashtags]" value="1"%2$s /> %3$s</label>',
			esc_attr( $field_id ),
			checked( $checked, true, false ),
			esc_html__( 'Append Trello labels as hashtags', 'fp-publisher' )
		);
	}

	/**
	 * Render the URL shortener select field.
	 */
	public function render_url_shortener_field() {
		$field_id = $this->get_field_id( 'url_shortener' );
		$value    = sanitize_key( $this->get_setting( 'url_shortener', 'none' ) );

		$choices = array(
			'none'  => __( 'None', 'fp-publisher' ),
			'wp'    => __( 'WordPress', 'fp-publisher' ),
			'bitly' => __( 'Bitly', 'fp-publisher' ),
		);

		printf( '<select id="%1$s" name="tts_settings[url_shortener]">', esc_attr( $field_id ) );
		foreach ( $choices as $key => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $key ),
				selected( $value, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Render field for Bitly token.
	 */
	public function render_bitly_token_field() {
		$field_id       = $this->get_field_id( 'bitly_token' );
		$description_id = $field_id . '-description';
		$value          = $this->get_setting( 'bitly_token', '' );

		printf(
			'<input type="text" id="%1$s" name="tts_settings[bitly_token]" value="%2$s" class="regular-text" aria-describedby="%3$s" />',
			esc_attr( $field_id ),
			esc_attr( $value ),
			esc_attr( $description_id )
		);

		printf(
			'<p id="%1$s" class="description">%2$s</p>',
			esc_attr( $description_id ),
			esc_html__( 'Required for Bitly shortening.', 'fp-publisher' )
		);
	}

	/**
	 * Render field for Slack webhook.
	 */
	public function render_slack_webhook_field() {
		$field_id = $this->get_field_id( 'slack_webhook' );
		$value    = $this->get_setting( 'slack_webhook', '' );

		printf(
			'<input type="url" id="%1$s" name="tts_settings[slack_webhook]" value="%2$s" class="regular-text" placeholder="https://hooks.slack.com/..." />',
			esc_attr( $field_id ),
			esc_url( $value )
		);
	}

	/**
	 * Render field for notification emails.
	 */
	public function render_notification_emails_field() {
		$field_id       = $this->get_field_id( 'notification_emails' );
		$description_id = $field_id . '-description';
		$value          = $this->get_setting( 'notification_emails', '' );

		printf(
			'<input type="text" id="%1$s" name="tts_settings[notification_emails]" value="%2$s" class="regular-text" aria-describedby="%3$s" />',
			esc_attr( $field_id ),
			esc_attr( $value ),
			esc_attr( $description_id )
		);

		printf(
			'<p id="%1$s" class="description">%2$s</p>',
			esc_attr( $description_id ),
			esc_html__( 'Comma-separated list of email addresses.', 'fp-publisher' )
		);
	}

	/**
	 * Render field for log retention period.
	 */
	public function render_log_retention_days_field() {
		$field_id = $this->get_field_id( 'log_retention_days' );
		$value    = absint( $this->get_setting( 'log_retention_days', 30 ) );

		printf(
			'<input type="number" id="%1$s" name="tts_settings[log_retention_days]" value="%2$s" class="small-text" min="1" step="1" />',
			esc_attr( $field_id ),
			esc_attr( $value )
		);
	}

}

/**
 * Sanitize settings values.
 *
 * @param array $input Raw settings input.
 * @return array Sanitized settings.
 */
function tts_sanitize_settings( $input ) {
	$output = array();

	if ( ! current_user_can( 'manage_options' ) ) {
		add_settings_error( 'tts_settings', 'tts_settings_permissions', __( 'You are not allowed to save these settings.', 'fp-publisher' ), 'error' );
		return $output;
	}

	if ( ! is_array( $input ) ) {
		add_settings_error( 'tts_settings', 'tts_settings_invalid_payload', __( 'The submitted settings payload is invalid.', 'fp-publisher' ), 'error' );
		return $output;
	}

	$input = wp_unslash( $input );

	$text_keys = array(
		'trello_api_key',
		'trello_api_token',
		'social_access_token',
		'bitly_token',
		'unsplash_access_key',
		'pexels_api_key',
		'media_transcoder_path',
	);

	foreach ( $text_keys as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$output[ $key ] = sanitize_text_field( $input[ $key ] );
		}
	}

	if ( isset( $input['column_mapping'] ) ) {
		$raw_mapping = trim( (string) $input['column_mapping'] );

		if ( '' === $raw_mapping ) {
			$output['column_mapping'] = '';
		} else {
			$decoded = json_decode( $raw_mapping, true );

			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$output['column_mapping'] = wp_json_encode( $decoded );
			} else {
				$output['column_mapping'] = '';
				add_settings_error( 'tts_settings', 'tts_settings_column_mapping', __( 'Column mapping must be valid JSON.', 'fp-publisher' ), 'error' );
			}
		}
	}

	if ( isset( $input['log_retention_days'] ) ) {
		$days = absint( $input['log_retention_days'] );
		if ( $days < 1 ) {
			$days = 30;
			add_settings_error( 'tts_settings', 'tts_settings_log_retention', __( 'Log retention must be at least one day.', 'fp-publisher' ), 'error' );
		}
		$output['log_retention_days'] = $days;
	}

	if ( isset( $input['usage_profile'] ) ) {
		$profile = sanitize_key( $input['usage_profile'] );
		$allowed = array( 'standard', 'advanced', 'enterprise' );
		$output['usage_profile'] = in_array( $profile, $allowed, true ) ? $profile : 'standard';
	}

	$offset_keys = array( 'facebook_offset', 'instagram_offset', 'youtube_offset', 'tiktok_offset' );
	foreach ( $offset_keys as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$output[ $key ] = absint( $input[ $key ] );
		}
	}

	$size_keys = array( 'facebook_size', 'instagram_size', 'youtube_size', 'tiktok_size' );
	foreach ( $size_keys as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$value = strtolower( sanitize_text_field( $input[ $key ] ) );
			if ( preg_match( '/^\d+x\d+$/', $value ) ) {
				$output[ $key ] = $value;
			}
		}
	}

	if ( isset( $input['default_lat'] ) ) {
		$output['default_lat'] = sanitize_text_field( $input['default_lat'] );
	}

	if ( isset( $input['default_lng'] ) ) {
		$output['default_lng'] = sanitize_text_field( $input['default_lng'] );
	}

	if ( isset( $input['url_shortener'] ) ) {
		$shortener = sanitize_key( $input['url_shortener'] );
		$allowed   = array( 'none', 'wp', 'bitly' );

		if ( in_array( $shortener, $allowed, true ) ) {
			$output['url_shortener'] = $shortener;
		} else {
			$output['url_shortener'] = 'none';
			add_settings_error( 'tts_settings', 'tts_settings_url_shortener', __( 'Invalid URL shortener selection. Default option applied.', 'fp-publisher' ), 'error' );
		}
	}

	$output['labels_as_hashtags'] = ! empty( $input['labels_as_hashtags'] ) ? 1 : 0;

	if ( isset( $input['utm'] ) && is_array( $input['utm'] ) ) {
		foreach ( $input['utm'] as $channel => $params ) {
			$channel_key = sanitize_key( $channel );

			if ( '' === $channel_key || ! is_array( $params ) ) {
				continue;
			}

			foreach ( $params as $param_key => $param_value ) {
				$param_slug = sanitize_key( $param_key );

				if ( '' === $param_slug ) {
					continue;
				}

				$output['utm'][ $channel_key ][ $param_slug ] = sanitize_text_field( $param_value );
			}
		}
	}

	foreach ( $input as $key => $value ) {
		if ( 'utm' === $key ) {
			continue;
		} elseif ( substr( $key, -9 ) === '_template' ) {
			$output[ $key ] = sanitize_text_field( $value );
		} elseif ( substr( $key, -4 ) === '_url' ) {
			$output[ $key ] = esc_url_raw( $value );
		}
	}

	if ( isset( $input['slack_webhook'] ) ) {
		$output['slack_webhook'] = esc_url_raw( $input['slack_webhook'] );
	}

	if ( isset( $input['notification_emails'] ) ) {
		$raw_emails = array_filter( array_map( 'trim', explode( ',', $input['notification_emails'] ) ) );
		$emails     = array_filter( array_map( 'sanitize_email', $raw_emails ) );

		if ( empty( $emails ) && ! empty( $raw_emails ) ) {
			add_settings_error( 'tts_settings', 'tts_settings_notification_emails', __( 'No valid email addresses were detected. The saved list is empty.', 'fp-publisher' ), 'error' );
		}

		$output['notification_emails'] = implode( ', ', $emails );
	}

	return $output;
}

/**
 * Initialize TTS_Settings on plugins_loaded.
 */
function tts_init_settings() {
	new TTS_Settings();
}
add_action( 'plugins_loaded', 'tts_init_settings' );
