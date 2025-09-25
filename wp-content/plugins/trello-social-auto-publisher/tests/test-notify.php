<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';
require_once __DIR__ . '/../includes/tts-logger.php';
require_once __DIR__ . '/../includes/tts-notify.php';

$tests = array(
    'falls_back_to_email_when_slack_fails' => function () {
        tts_reset_test_state();

        $post_id = 501;
        $GLOBALS['tts_test_posts'][ $post_id ] = (object) array(
            'ID'         => $post_id,
            'post_title' => 'Example Post',
        );

        update_option( 'admin_email', 'admin@example.com' );
        update_option( 'tts_slack_webhook', 'https://hooks.slack.com/services/test' );

        $GLOBALS['tts_http_responses']['https://hooks.slack.com/services/test'] = new WP_Error(
            'slack_error',
            'Connection refused'
        );

        $result = tts_notify_publication( $post_id, ' SUCCESS ', ' Facebook  ' );

        tts_assert_true( $result, 'Email fallback should report success when Slack delivery fails.' );
        tts_assert_equals( 1, count( $GLOBALS['tts_sent_emails'] ), 'Email fallback should send one message.' );
        tts_assert_equals( 'admin@example.com', $GLOBALS['tts_sent_emails'][0]['to'], 'Fallback email should use admin address.' );
        tts_assert_contains( 'facebook', strtolower( $GLOBALS['tts_sent_emails'][0]['subject'] ), 'Subject should include sanitized channel.' );
        tts_assert_contains( 'success', strtolower( $GLOBALS['tts_sent_emails'][0]['subject'] ), 'Subject should include sanitized status.' );
        tts_assert_contains( 'facebook', strtolower( $GLOBALS['tts_sent_emails'][0]['message'] ), 'Message should include sanitized channel.' );

        tts_assert_equals( 1, count( $GLOBALS['tts_recorded_http_posts'] ), 'Slack request should be attempted once.' );
        $payload = json_decode( $GLOBALS['tts_recorded_http_posts'][0]['args']['body'], true );
        tts_assert_equals(
            'Post "Example Post" on facebook: success',
            $payload['text'],
            'Slack payload should contain sanitized message.'
        );
    },
    'returns_true_when_slack_succeeds_without_email' => function () {
        tts_reset_test_state();

        $post_id = 777;
        $GLOBALS['tts_test_posts'][ $post_id ] = (object) array(
            'ID'         => $post_id,
            'post_title' => 'Story Title',
        );

        update_option( 'tts_slack_webhook', 'https://hooks.slack.com/services/success' );

        $GLOBALS['tts_http_responses']['https://hooks.slack.com/services/success'] = array(
            'response' => array( 'code' => 200 ),
            'body'     => 'ok',
        );

        $result = tts_notify_publication( $post_id, 'queued', ' instagram ' );

        tts_assert_true( $result, 'Successful Slack delivery should report success.' );
        tts_assert_equals( 0, count( $GLOBALS['tts_sent_emails'] ), 'Email fallback should not run when Slack succeeds.' );
        tts_assert_equals( 1, count( $GLOBALS['tts_recorded_http_posts'] ), 'Exactly one Slack request should be recorded.' );

        $payload = json_decode( $GLOBALS['tts_recorded_http_posts'][0]['args']['body'], true );
        tts_assert_equals(
            'Post "Story Title" on instagram: queued',
            $payload['text'],
            'Slack payload should include sanitized status and channel.'
        );
    },
);

$failures = 0;
$messages = array();

echo "Running notification tests\n";

foreach ( $tests as $name => $callback ) {
    try {
        $callback();
        echo '.';
    } catch ( Throwable $exception ) {
        $failures++;
        $messages[] = $name . ': ' . $exception->getMessage();
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
