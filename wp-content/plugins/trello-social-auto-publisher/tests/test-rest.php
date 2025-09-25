<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';
require_once __DIR__ . '/../includes/class-tts-rest.php';

$tests = array(
    'publish_returns_error_for_invalid_post' => function () {
        tts_reset_test_state();

        $rest     = new TTS_REST();
        $request  = new WP_REST_Request( 'POST', array( 'id' => 999 ) );
        $response = $rest->publish( $request );

        tts_assert_true( is_wp_error( $response ), 'Invalid IDs should return WP_Error responses.' );
        tts_assert_equals( 'invalid_post', $response->get_error_code(), 'Invalid posts should use the invalid_post error code.' );
    },
    'publish_requires_post_approval' => function () {
        tts_reset_test_state();

        $post_id = 101;
        $GLOBALS['tts_test_posts'][ $post_id ] = (object) array(
            'ID'         => $post_id,
            'post_type'  => 'tts_social_post',
            'post_status'=> 'draft',
        );
        update_post_meta( $post_id, '_tts_social_channel', array( 'facebook' ) );

        $rest     = new TTS_REST();
        $request  = new WP_REST_Request( 'POST', array( 'id' => $post_id ) );
        $response = $rest->publish( $request );

        tts_assert_true( is_wp_error( $response ), 'Unapproved posts should not be published.' );
        tts_assert_equals( 'post_not_approved', $response->get_error_code(), 'Unapproved posts should trigger post_not_approved.' );
    },
    'publish_requires_configured_channels' => function () {
        tts_reset_test_state();

        $post_id = 150;
        $GLOBALS['tts_test_posts'][ $post_id ] = (object) array(
            'ID'         => $post_id,
            'post_type'  => 'tts_social_post',
            'post_status'=> 'draft',
        );
        update_post_meta( $post_id, '_tts_approved', true );

        $rest     = new TTS_REST();
        $request  = new WP_REST_Request( 'POST', array( 'id' => $post_id ) );
        $response = $rest->publish( $request );

        tts_assert_true( is_wp_error( $response ), 'Missing channel configuration should block publishing.' );
        tts_assert_equals( 'missing_channels', $response->get_error_code(), 'Missing channels should trigger missing_channels.' );
    },
    'publish_rejects_foreign_post_types' => function () {
        tts_reset_test_state();

        $post_id = 175;
        $GLOBALS['tts_test_posts'][ $post_id ] = (object) array(
            'ID'         => $post_id,
            'post_type'  => 'post',
            'post_status'=> 'draft',
        );
        update_post_meta( $post_id, '_tts_social_channel', array( 'facebook' ) );
        update_post_meta( $post_id, '_tts_approved', true );

        $rest     = new TTS_REST();
        $request  = new WP_REST_Request( 'POST', array( 'id' => $post_id ) );
        $response = $rest->publish( $request );

        tts_assert_true( is_wp_error( $response ), 'Non-social posts should not be published through the endpoint.' );
        tts_assert_equals( 'invalid_post_type', $response->get_error_code(), 'Foreign post types should trigger invalid_post_type.' );
    },
    'publish_requires_scheduler_hook' => function () {
        tts_reset_test_state();

        $post_id = 202;
        $GLOBALS['tts_test_posts'][ $post_id ] = (object) array(
            'ID'         => $post_id,
            'post_type'  => 'tts_social_post',
            'post_status'=> 'draft',
        );
        update_post_meta( $post_id, '_tts_social_channel', array( 'facebook' ) );
        update_post_meta( $post_id, '_tts_approved', true );

        $rest     = new TTS_REST();
        $request  = new WP_REST_Request( 'POST', array( 'id' => $post_id ) );
        $response = $rest->publish( $request );

        tts_assert_true( is_wp_error( $response ), 'Publishing without scheduler hook should fail.' );
        tts_assert_equals( 'scheduler_unavailable', $response->get_error_code(), 'Missing scheduler should return scheduler_unavailable.' );
    },
    'publish_queues_post_when_requirements_met' => function () {
        tts_reset_test_state();

        $post_id = 303;
        $GLOBALS['tts_test_posts'][ $post_id ] = (object) array(
            'ID'         => $post_id,
            'post_type'  => 'tts_social_post',
            'post_status'=> 'draft',
        );
        update_post_meta( $post_id, '_tts_social_channel', array( 'Facebook ', '   ' ) );
        update_post_meta( $post_id, '_tts_approved', true );
        $GLOBALS['tts_action_callbacks'] = array();
        $dispatched = array();
        add_action( 'tts_publish_social_post', function ( $queued_post_id ) use ( &$dispatched ) {
            $dispatched[] = $queued_post_id;
        } );

        $rest     = new TTS_REST();
        $request  = new WP_REST_Request( 'POST', array( 'id' => $post_id ) );
        $response = $rest->publish( $request );

        tts_assert_true( $response instanceof WP_REST_Response, 'Successful publishes should return a WP_REST_Response.' );
        tts_assert_equals( 202, $response->get_status(), 'Successful publish requests should return HTTP 202.' );

        $data = $response->get_data();
        tts_assert_equals( array( $post_id ), $dispatched, 'The scheduler hook should receive the post ID.' );
        tts_assert_equals( array( 'facebook' ), $data['channels'], 'Channels should be sanitized and filtered.' );
        tts_assert_equals( 'queued', $data['status'], 'Default response status should be queued.' );
    },
    'publish_rejects_trashed_posts' => function () {
        tts_reset_test_state();

        $post_id = 404;
        $GLOBALS['tts_test_posts'][ $post_id ] = (object) array(
            'ID'         => $post_id,
            'post_type'  => 'tts_social_post',
            'post_status'=> 'trash',
        );
        update_post_meta( $post_id, '_tts_social_channel', array( 'facebook' ) );
        update_post_meta( $post_id, '_tts_approved', true );

        $rest     = new TTS_REST();
        $request  = new WP_REST_Request( 'POST', array( 'id' => $post_id ) );
        $response = $rest->publish( $request );

        tts_assert_true( is_wp_error( $response ), 'Trashed posts cannot be published.' );
        tts_assert_equals( 'invalid_post_status', $response->get_error_code(), 'Trashed posts should trigger invalid_post_status.' );
    },
    'status_sanitizes_log_entries' => function () {
        tts_reset_test_state();

        $post_id = 505;
        $GLOBALS['tts_test_posts'][ $post_id ] = (object) array(
            'ID'         => $post_id,
            'post_type'  => 'tts_social_post',
            'post_status'=> 'publish',
        );

        update_post_meta( $post_id, '_published_status', '<strong>sent</strong>' );

        $GLOBALS['tts_test_wpdb_results'] = array(
            array(
                'channel'    => '<script>facebook</script>',
                'status'     => '<b>success</b>',
                'message'    => '<em>Published!</em>',
                'response'   => '{"detail":"<span>ok</span>","code":200}',
                'created_at' => '2024-01-01 12:00:00<script>',
            ),
        );

        $rest     = new TTS_REST();
        $request  = new WP_REST_Request( 'GET', array( 'id' => $post_id ) );
        $response = $rest->status( $request );

        tts_assert_true( $response instanceof WP_REST_Response, 'Status endpoint should return a WP_REST_Response.' );

        $data = $response->get_data();

        tts_assert_equals( 'publish', $data['post_status'], 'Post status should be preserved after sanitization.' );
        tts_assert_equals( 'sent', $data['_published_status'], 'Published status should strip markup.' );
        tts_assert_equals( 1, count( $data['logs'] ), 'Exactly one log entry should be returned.' );

        $log = $data['logs'][0];

        tts_assert_equals( 'facebook', $log['channel'], 'Channel names should be normalized and sanitized.' );
        tts_assert_equals( 'success', $log['status'], 'Status values should be sanitized.' );
        tts_assert_equals( 'Published!', $log['message'], 'Log messages should be stripped of HTML.' );
        tts_assert_equals( '{"detail":"ok","code":200}', $log['response'], 'JSON responses should be sanitized and re-encoded.' );
        tts_assert_equals( '2024-01-01 12:00:00', $log['created_at'], 'Timestamps should not include unsafe characters.' );
    },
);

$failures = 0;
$messages = array();

echo "Running REST API tests\n";

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
