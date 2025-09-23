<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * Basic assertion helpers for the lightweight test harness.
 */
function tts_assert_true( $condition, string $message ) : void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

function tts_assert_false( $condition, string $message ) : void {
    tts_assert_true( ! $condition, $message );
}

function tts_assert_contains( string $needle, string $haystack, string $message ) : void {
    if ( false === strpos( $haystack, $needle ) ) {
        throw new RuntimeException( $message );
    }
}

function tts_assert_not_contains( string $needle, string $haystack, string $message ) : void {
    if ( false !== strpos( $haystack, $needle ) ) {
        throw new RuntimeException( $message );
    }
}

function tts_assert_equals( $expected, $actual, string $message ) : void {
    if ( $expected !== $actual ) {
        $details = sprintf( ' Expected %s but received %s.', var_export( $expected, true ), var_export( $actual, true ) );
        throw new RuntimeException( $message . $details );
    }
}

$tests = array(
    'metabox_excludes_trello_when_disabled' => function () {
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
    'content_management_tabs_hide_trello' => function () {
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
            (object) array( 'source' => 'manual', 'count' => 3 ),
            (object) array( 'source' => 'local_upload', 'count' => 1 ),
        );

        $table = new TTS_Social_Posts_Table();

        ob_start();
        $table->render_content_management_page();
        $output = ob_get_clean();

        tts_assert_not_contains( 'data-tab="trello"', $output, 'Trello tab should be removed when disabled.' );
        tts_assert_not_contains( 'id="trello-content"', $output, 'Trello panel should not be rendered when disabled.' );
        tts_assert_not_contains( 'data-source="trello"', $output, 'Quick action buttons should not target Trello when disabled.' );
        tts_assert_contains( 'Create Manual Content', $output, 'Manual quick action should remain available.' );
    },
    'quick_actions_syncable_sources_exclude_trello' => function () {
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
    'wizard_localizes_disabled_flag' => function () {
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
        $failures++;
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
