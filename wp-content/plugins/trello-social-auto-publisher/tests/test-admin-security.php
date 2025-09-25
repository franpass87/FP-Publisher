<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';
require_once __DIR__ . '/../includes/class-tts-advanced-utils.php';
require_once __DIR__ . '/../includes/class-tts-rest.php';
require_once __DIR__ . '/../admin/class-tts-log-page.php';

$tests = array(
    'ajax_export_rejects_invalid_nonce' => function () {
        tts_reset_test_state();

        $GLOBALS['tts_current_user_caps'] = array(
            'tts_export_data' => true,
        );

        $admin = new TTS_Admin();

        $_POST    = array(
            'nonce' => 'invalid',
        );
        $_REQUEST = $_POST;

        $admin->ajax_export_data();

        tts_assert_equals(
            1,
            count( $GLOBALS['tts_json_errors'] ),
            'Invalid nonces should trigger a JSON error response.'
        );

        $error = $GLOBALS['tts_json_errors'][0];
        tts_assert_equals(
            __( 'Invalid or missing nonce.', 'fp-publisher' ),
            $error['data']['message'],
            'The response should contain a nonce validation error.'
        );

        tts_assert_equals(
            0,
            count( $GLOBALS['tts_json_responses'] ),
            'No success payload should be recorded when nonce validation fails.'
        );
    },
    'ajax_export_requires_capability' => function () {
        tts_reset_test_state();

        $admin = new TTS_Admin();

        $_POST    = array(
            'nonce' => 'nonce-tts_ajax_nonce',
        );
        $_REQUEST = $_POST;

        $admin->ajax_export_data();

        tts_assert_equals(
            1,
            count( $GLOBALS['tts_json_errors'] ),
            'Missing export capability should generate a JSON error response.'
        );

        $error = $GLOBALS['tts_json_errors'][0];
        tts_assert_equals(
            __( 'You do not have permission to perform this action.', 'fp-publisher' ),
            $error['data']['message'],
            'The capability failure error message should be returned.'
        );
    },
    'ajax_bulk_action_denies_privilege_escalation' => function () {
        tts_reset_test_state();

        $admin = new TTS_Admin();

        $GLOBALS['tts_test_posts'][101] = (object) array(
            'ID'        => 101,
            'post_type' => 'tts_social_post',
            'post_title'=> 'Security Test',
            'post_date' => '2024-01-01 00:00:00',
        );

        $GLOBALS['tts_current_user_caps'] = array(
            'tts_edit_social_posts' => true,
            'edit_post_101'         => true,
        );

        $_POST    = array(
            'nonce'     => 'nonce-tts_dashboard',
            'bulkAction'=> 'approve',
            'postIds'   => array( 101 ),
        );
        $_REQUEST = $_POST;

        $admin->ajax_bulk_action();

        tts_assert_equals(
            1,
            count( $GLOBALS['tts_json_errors'] ),
            'Users without approval capability must receive an error.'
        );

        $error = $GLOBALS['tts_json_errors'][0];
        tts_assert_equals(
            __( 'You do not have permission to approve social posts.', 'fp-publisher' ),
            $error['data'],
            'Approval attempts without capability should be blocked.'
        );

        $approved = get_post_meta( 101, '_tts_approved', true );
        tts_assert_false(
            (bool) $approved,
            'Approval meta should remain unchanged when the action is denied.'
        );
    },
    'ajax_refresh_posts_sanitizes_output' => function () {
        tts_reset_test_state();

        $admin = new TTS_Admin();

        $GLOBALS['tts_current_user_caps'] = array(
            'tts_read_social_posts' => true,
            'tts_edit_social_posts' => true,
            'edit_post_101'         => true,
        );

        $GLOBALS['tts_test_posts'][101] = (object) array(
            'ID'        => 101,
            'post_type' => 'tts_social_post',
            'post_title'=> '<script>alert(1)</script> Title',
            'post_date' => '2024-01-02 03:04:05',
        );

        update_post_meta( 101, '_tts_social_channel', array( '<script>bad</script>channel', 'facebook' ) );
        update_post_meta( 101, '_published_status', '<strong>published</strong>' );
        update_post_meta( 101, '_tts_publish_at', '2024-01-03 05:06:07' );

        $_POST    = array(
            'nonce' => 'nonce-tts_dashboard',
        );
        $_REQUEST = $_POST;

        $admin->ajax_refresh_posts();

        tts_assert_equals(
            1,
            count( $GLOBALS['tts_json_responses'] ),
            'Successful refresh should generate a JSON response.'
        );

        $response = $GLOBALS['tts_json_responses'][0];
        tts_assert_true( $response['success'], 'Refresh operation should succeed for authorized users.' );

        $post_payload = $response['data']['posts'][0];
        tts_assert_equals( 'alert(1) Title', $post_payload['title'], 'HTML tags must be stripped from titles.' );

        foreach ( $post_payload['channel'] as $channel_value ) {
            tts_assert_false(
                false !== strpos( $channel_value, '<' ) || false !== strpos( $channel_value, '>' ),
                'Channel names should not contain HTML tags after sanitization.'
            );
        }

        tts_assert_equals( 'published', $post_payload['status'], 'Status values must be sanitized.' );

        $edit_link = $post_payload['edit_link'];
        tts_assert_true(
            0 === strpos( $edit_link, 'admin.php?page=fp-publisher-social-posts' ),
            'Edit links should route through the custom FP Publisher editor.'
        );

        $query_args = array();
        $query_url  = 'http://example.com/' . ltrim( $edit_link, '/' );
        if ( function_exists( 'wp_parse_url' ) ) {
            $query_string = (string) wp_parse_url( $query_url, PHP_URL_QUERY );
        } else {
            $query_string = (string) parse_url( $query_url, PHP_URL_QUERY );
        }

        parse_str( $query_string, $query_args );

        tts_assert_equals(
            'fp-publisher-social-posts',
            isset( $query_args['page'] ) ? $query_args['page'] : '',
            'Edit links must stay scoped to the FP Publisher editor page.'
        );
        tts_assert_equals(
            'edit',
            isset( $query_args['action'] ) ? $query_args['action'] : '',
            'Edit links should use the edit action.'
        );
        tts_assert_equals(
            '1',
            isset( $query_args['tts_open_editor'] ) ? $query_args['tts_open_editor'] : '',
            'Edit links should open the editor by default.'
        );
        tts_assert_equals(
            '101',
            isset( $query_args['post'] ) ? $query_args['post'] : '',
            'Edit links must reference the social post ID.'
        );
    },
    'rest_permissions_enforce_nonce_and_caps' => function () {
        tts_reset_test_state();

        $rest = new TTS_REST();

        $invalid_request = new WP_REST_Request( 'POST', array( 'id' => 22 ), array( 'X-WP-Nonce' => 'invalid' ) );

        $result = $rest->permissions_check( $invalid_request );
        tts_assert_true(
            $result instanceof WP_Error,
            'Invalid REST nonce should return a WP_Error.'
        );

        $valid_request = new WP_REST_Request( 'POST', array( 'id' => 22 ), array( 'X-WP-Nonce' => 'nonce-wp_rest' ) );

        $result = $rest->permissions_check( $valid_request );
        tts_assert_true(
            $result instanceof WP_Error,
            'Missing publish capability should be rejected even with a valid nonce.'
        );

        $GLOBALS['tts_current_user_caps'] = array(
            'tts_publish_social_posts' => true,
            'tts_edit_social_posts'    => true,
            'edit_post_22'             => true,
        );

        $result = $rest->permissions_check( $valid_request );
        tts_assert_true( true === $result, 'Proper capabilities and nonce should allow REST access.' );
    },
    'save_social_app_settings_sanitizes_nested_payload' => function () {
        tts_reset_test_state();

        $GLOBALS['tts_current_user_caps'] = array(
            'manage_options' => true,
        );

        $admin = new TTS_Admin();

        $_POST = array(
            'social_apps' => array(
                ' facebook ' => array(
                    'app_id'      => "  ABC\\'123  ",
                    'app_secret'  => '<strong>secret</strong>',
                    'webhooks'    => array(
                        'callback_url ' => ' https://example.com/callback ',
                        'events'        => array( ' mentions ', 'comments ' ),
                    ),
                    'invalid key!' => array( 'should_drop' => '<b>value</b>' ),
                ),
                'invalid platform!' => array(
                    'app_id' => 'ignored',
                ),
            ),
        );

        $method = new ReflectionMethod( TTS_Admin::class, 'save_social_app_settings' );
        $method->setAccessible( true );
        $method->invoke( $admin );

        $stored = get_option( 'tts_social_apps', array() );

        tts_assert_true(
            isset( $stored['facebook'] ),
            'Sanitized settings should retain the Facebook platform.'
        );

        tts_assert_false(
            isset( $stored['invalid_platform'] ),
            'Platforms with invalid keys should be discarded.'
        );

        $facebook = $stored['facebook'];

        tts_assert_equals(
            "ABC'123",
            $facebook['app_id'],
            'App IDs should be unslashed and sanitized.'
        );

        tts_assert_equals(
            'secret',
            $facebook['app_secret'],
            'Secrets should be stripped of HTML markup.'
        );

        tts_assert_true(
            isset( $facebook['webhooks']['callback_url'] ),
            'Nested webhook configuration should be preserved.'
        );

        tts_assert_equals(
            'https://example.com/callback',
            $facebook['webhooks']['callback_url'],
            'Callback URLs should be trimmed and sanitized.'
        );

        tts_assert_equals(
            array( 'mentions', 'comments' ),
            array_values( $facebook['webhooks']['events'] ),
            'Event lists should remain arrays of sanitized strings.'
        );

        $serialized = wp_json_encode( $stored );
        tts_assert_false(
            false !== strpos( $serialized, 'Array' ),
            'Nested values must not collapse into the literal string "Array".'
        );

        unset( $_POST );
    },
    'log_page_requires_manage_options' => function () {
        tts_reset_test_state();

        $log_page = new TTS_Log_Page();

        try {
            $log_page->render_page();
            tts_assert_true( false, 'Rendering the log page without capabilities should trigger wp_die.' );
        } catch ( RuntimeException $e ) {
            tts_assert_equals(
                'You are not allowed to access the FP Publisher logs.',
                $e->getMessage(),
                'The log page should block unauthorized access with a descriptive message.'
            );
        }
    },
    'log_deletion_requires_manage_options' => function () {
        tts_reset_test_state();

        $_GET = array(
            'action' => 'delete',
            'log'    => '15',
        );

        $table = new TTS_Log_Table();

        try {
            $table->process_actions();
            tts_assert_true( false, 'Deleting logs without capabilities should not be allowed.' );
        } catch ( RuntimeException $e ) {
            tts_assert_equals(
                'You are not allowed to manage FP Publisher logs.',
                $e->getMessage(),
                'Unauthorized log deletions should be blocked with a descriptive message.'
            );
        }
    },
);

foreach ( $tests as $name => $test ) {
    try {
        $test();
        echo "✅ {$name}\n";
    } catch ( Throwable $e ) {
        echo "❌ {$name}: " . $e->getMessage() . "\n";
        exit( 1 );
    }
}
