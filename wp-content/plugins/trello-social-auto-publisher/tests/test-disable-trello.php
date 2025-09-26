<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';

$tests = array(
	'metabox_excludes_trello_when_disabled'               => function () {
		tts_reset_test_state();

		$post_id = 42;
		$GLOBALS['tts_test_options']['tts_trello_enabled'] = 0;
		$GLOBALS['tts_test_post_meta'][ $post_id ] = array(
			'_tts_content_source'   => 'trello',
			'_tts_source_reference' => 'CARD-123',
		);

		$post = (object) array( 'ID' => $post_id );

		$content_source = new TTS_Content_Source();

		ob_start();
		$content_source->render_content_source_metabox( $post );
		$output = ob_get_clean();

		tts_assert_not_contains( 'value="trello"', $output, 'Trello option should be hidden when the integration is disabled.' );
		tts_assert_contains( 'value="manual"', $output, 'Manual option should remain available.' );
		tts_assert_contains( 'value="manual" selected="selected"', $output, 'Manual option should be selected when Trello meta persists.' );
		tts_assert_contains( 'Trello integration is disabled', $output, 'A notice should explain the Trello fallback.' );
	},
	'save_meta_falls_back_to_manual_when_trello_disabled' => function () {
		tts_reset_test_state();

		tsap_update_option( 'tts_trello_enabled', 0 );

		$post_id = 314;
		$GLOBALS['tts_current_user_caps'] = array(
			'edit_post_' . $post_id => true,
		);

		$_POST = array(
			'tts_content_source_nonce' => 'nonce-tts_content_source_meta',
			'tts_content_source'       => 'trello',
			'tts_source_reference'     => 'CARD-314',
		);

		$content_source = new TTS_Content_Source();
		$content_source->save_content_source_meta( $post_id );

		tts_assert_equals(
			'manual',
			get_post_meta( $post_id, '_tts_content_source', true ),
			'Trello selections should fall back to manual when the integration is disabled.'
		);

		tts_assert_equals(
			'converted',
			get_post_meta( $post_id, '_tts_trello_disabled_notice', true ),
			'Fallbacks should set a one-time notice flag so the editor can inform the user.'
		);

		$post = (object) array( 'ID' => $post_id );

		ob_start();
		$content_source->render_content_source_metabox( $post );
		$output = ob_get_clean();

		tts_assert_contains(
			'converted to Manual',
			$output,
			'The metabox should explain why the Trello selection was converted.'
		);

		tts_assert_equals(
			'',
			get_post_meta( $post_id, '_tts_trello_disabled_notice', true ),
			'Rendering the metabox should clear the notice flag.'
		);

		unset( $_POST );
	},
	'content_management_tabs_hide_trello'                 => function () {
		tts_reset_test_state();

		$GLOBALS['tts_test_options']['tts_trello_enabled']        = 0;
		$GLOBALS['tts_test_options']['tts_google_drive_settings'] = array(
			'access_token' => 'token',
			'folder_id'    => 'folder',
		);
		$GLOBALS['tts_test_options']['tts_dropbox_settings']      = array(
			'access_token' => 'token',
			'folder_path'  => '/folder',
		);

		$GLOBALS['tts_test_wpdb_results'] = array(
			(object) array(
				'source' => 'manual',
				'count'  => 3,
			),
			(object) array(
				'source' => 'local_upload',
				'count'  => 1,
			),
		);

		$admin = new TTS_Admin();

		ob_start();
		$admin->render_content_management_page();
		$output = ob_get_clean();

		tts_assert_not_contains( 'data-tab="trello"', $output, 'Trello tab should be removed when disabled.' );
		tts_assert_not_contains( 'id="trello-content"', $output, 'Trello panel should not be rendered when disabled.' );
		tts_assert_not_contains( 'data-source="trello"', $output, 'Quick action buttons should not target Trello when disabled.' );
		tts_assert_contains( 'Create Manual Content', $output, 'Manual quick action should remain available.' );
	},
	'quick_actions_syncable_sources_exclude_trello'       => function () {
		tts_reset_test_state();

		$GLOBALS['tts_test_options']['tts_trello_enabled']        = 0;
		$GLOBALS['tts_test_options']['tts_google_drive_settings'] = array(
			'access_token' => 'token',
			'folder_id'    => 'folder',
		);
		$GLOBALS['tts_test_options']['tts_dropbox_settings']      = array(
			'access_token' => 'token',
			'folder_path'  => '/folder',
		);

		$sources = TTS_Content_Source::get_syncable_sources();

		tts_assert_false( in_array( 'trello', $sources, true ), 'Syncable sources should not include Trello when disabled.' );
		tts_assert_true( in_array( 'google_drive', $sources, true ), 'Google Drive should remain syncable when configured.' );
		tts_assert_true( in_array( 'dropbox', $sources, true ), 'Dropbox should remain syncable when configured.' );
	},
	'wizard_localizes_disabled_flag'                      => function () {
		tts_reset_test_state();

		$GLOBALS['tts_test_options']['tts_trello_enabled'] = 0;

		$admin = new TTS_Admin();
		$admin->enqueue_wizard_assets( 'fp-publisher_page_fp-publisher-client-wizard' );

		tts_assert_true( isset( $GLOBALS['tts_localized_scripts']['tts-wizard']['ttsWizard'] ), 'Wizard localization data should be set.' );
		$localized = $GLOBALS['tts_localized_scripts']['tts-wizard']['ttsWizard'];
		tts_assert_equals( false, $localized['trelloEnabled'], 'Wizard data should mark Trello as disabled.' );
	},
);

$failures = 0;
$messages = array();

echo "Running Trello disablement tests\n";

foreach ( $tests as $name => $callback ) {
	try {
		$callback();
		echo '.';
	} catch ( Throwable $e ) {
		++$failures;
		$messages[] = $name . ': ' . $e->getMessage();
		echo 'F';
	}
}

echo "\n";

if ( $failures > 0 ) {
	foreach ( $messages as $message ) {
		echo $message . "\n";
	}
	exit( 1 );
}

echo "All tests passed\n";
