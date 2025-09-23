<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';
require_once __DIR__ . '/../includes/class-tts-security-audit.php';

class TTS_Test_REST_Request {
    /** @var string */
    private $route;

    public function __construct( string $route ) {
        $this->route = $route;
    }

    public function get_route() : string {
        return $this->route;
    }
}

class TTS_Security_Audit_Test_Double extends TTS_Security_Audit {
    /** @var array<int, array<string, mixed>> */
    public $logged_events = array();

    /** @var array<int, mixed> */
    public $audit_logs_response = array();

    public function __construct() {
        parent::__construct();
        $this->init_security_monitoring();
    }

    public function log_security_event( $event_type, $description, $risk_level = self::RISK_LOW, $additional_data = array() ) {
        $this->logged_events[] = array(
            'event_type' => $event_type,
            'description' => $description,
            'risk_level' => $risk_level,
            'data' => $additional_data,
        );

        return true;
    }

    public function get_audit_logs( $limit = 100, $offset = 0, $filters = array() ) {
        return $this->audit_logs_response;
    }
}

$tests = array(
    'ajax_security_audit_with_valid_nonce_does_not_log' => function () {
        tts_reset_test_state();

        $audit = new TTS_Security_Audit_Test_Double();
        $audit->audit_logs_response = array(
            array(
                'id' => 1,
                'event_type' => 'test_event',
            ),
        );

        $nonce = wp_create_nonce( 'tts_security_audit_nonce' );

        $_POST = array(
            'action' => 'tts_get_security_audit',
            'nonce'  => $nonce,
        );
        $_REQUEST = $_POST;

        $audit->ajax_get_security_audit();

        tts_assert_equals(
            0,
            count( $audit->logged_events ),
            'Valid plugin nonce checks should not create audit entries.'
        );

        tts_assert_true(
            ! empty( $GLOBALS['tts_json_responses'] ),
            'A successful AJAX response should be recorded for valid nonces.'
        );

        $response = end( $GLOBALS['tts_json_responses'] );
        tts_assert_true( $response['success'], 'Valid nonce should generate a success response.' );
    },
    'ajax_security_audit_with_invalid_nonce_logs_failure' => function () {
        tts_reset_test_state();

        $audit = new TTS_Security_Audit_Test_Double();

        $_POST = array(
            'action' => 'tts_get_security_audit',
            'nonce'  => 'invalid',
        );
        $_REQUEST = $_POST;

        try {
            $audit->ajax_get_security_audit();
            throw new RuntimeException( 'Expected wp_die to be triggered for an invalid nonce.' );
        } catch ( RuntimeException $e ) {
            // Expected due to wp_die stub.
        }

        tts_assert_equals(
            1,
            count( $audit->logged_events ),
            'Invalid nonce usage should create a single audit entry.'
        );

        $event = $audit->logged_events[0];
        tts_assert_equals(
            TTS_Security_Audit::EVENT_PERMISSION_VIOLATION,
            $event['event_type'],
            'Nonce failures should be tracked as permission violations.'
        );
        tts_assert_equals(
            'tts_security_audit_nonce',
            $event['data']['nonce_action'],
            'The logged nonce action should reflect the security audit nonce.'
        );
        tts_assert_equals(
            'tts_get_security_audit',
            $event['data']['request_action'],
            'The recorded request action should match the AJAX handler.'
        );
        tts_assert_true(
            ! empty( $event['data']['nonce_provided'] ),
            'Event data should indicate that a nonce value was supplied.'
        );
        tts_assert_equals(
            0,
            count( $GLOBALS['tts_json_responses'] ),
            'No success responses should be recorded when the nonce is invalid.'
        );
    },
    'rest_api_monitoring_ignores_core_routes' => function () {
        tts_reset_test_state();

        $_SERVER['REMOTE_ADDR'] = '198.51.100.10';

        $audit   = new TTS_Security_Audit_Test_Double();
        $request = new TTS_Test_REST_Request( '/wp/v2/posts' );

        $audit->monitor_api_requests( null, array(), $request );

        tts_assert_equals(
            0,
            count( $audit->logged_events ),
            'Core REST API routes should not trigger plugin abuse monitoring.'
        );

        tts_assert_equals(
            array(),
            $GLOBALS['tts_test_transients'],
            'No transient counters should be stored for non-plugin routes.'
        );
    },
    'rest_api_monitoring_skips_authenticated_editors' => function () {
        tts_reset_test_state();

        $_SERVER['REMOTE_ADDR']           = '198.51.100.20';
        $GLOBALS['tts_is_user_logged_in'] = true;
        $GLOBALS['tts_current_user_caps'] = array( 'edit_posts' => true );

        $audit   = new TTS_Security_Audit_Test_Double();
        $request = new TTS_Test_REST_Request( '/tts/v1/secure-endpoint' );

        $audit->monitor_api_requests( null, array(), $request );

        tts_assert_equals(
            0,
            count( $audit->logged_events ),
            'Authenticated editors should not be counted toward abuse detection.'
        );

        tts_assert_equals(
            array(),
            $GLOBALS['tts_test_transients'],
            'No abuse counters should be stored for trusted editor traffic.'
        );
    },
    'rest_api_monitoring_detects_abuse_for_plugin_routes' => function () {
        tts_reset_test_state();

        $_SERVER['REMOTE_ADDR'] = '198.51.100.30';

        $audit   = new TTS_Security_Audit_Test_Double();
        $request = new TTS_Test_REST_Request( '/tts/v1/overuse' );

        $reflection = new ReflectionClass( TTS_Security_Audit::class );
        $property   = $reflection->getProperty( 'api_abuse_threshold' );
        $property->setAccessible( true );
        $threshold = (int) $property->getValue( $audit );

        for ( $i = 0; $i <= $threshold; $i++ ) {
            $audit->monitor_api_requests( null, array(), $request );
        }

        tts_assert_equals(
            1,
            count( $audit->logged_events ),
            'Exceeding the abuse threshold should log a single high-risk event.'
        );

        $event = end( $audit->logged_events );

        tts_assert_equals(
            TTS_Security_Audit::EVENT_API_ABUSE,
            $event['event_type'],
            'High-volume plugin traffic should be flagged as API abuse.'
        );

        tts_assert_equals(
            '/tts/v1/overuse',
            $event['data']['endpoint'],
            'The audit entry should reference the offending REST route.'
        );

        tts_assert_equals(
            $threshold + 1,
            $event['data']['api_calls_per_hour'],
            'The stored request count should reflect the threshold breach.'
        );

        $expected_key = 'tts_api_calls_' . md5( '198.51.100.30' . '|/tts/v1/overuse' );

        tts_assert_equals(
            $threshold + 1,
            get_transient( $expected_key ),
            'The per-route counter should be tracked in a transient.'
        );
    },
    'subscriber_profile_access_is_ignored_but_restricted_menu_is_logged' => function () {
        tts_reset_test_state();

        $GLOBALS['tts_current_user_caps'] = array(
            'read' => true,
        );

        $_SERVER['PHP_SELF'] = '/wp-admin/profile.php';
        unset( $_REQUEST['page'], $_GET['page'], $_POST['page'] );

        $GLOBALS['tts_current_screen'] = (object) array(
            'id'   => 'profile',
            'base' => 'profile',
            'cap'  => array( 'read' ),
        );

        $audit = new TTS_Security_Audit_Test_Double();

        $audit->monitor_admin_access();

        tts_assert_equals(
            0,
            count( $audit->logged_events ),
            'Visiting profile.php as a subscriber should not log a permission violation.'
        );

        $_SERVER['PHP_SELF'] = '/wp-admin/admin.php';
        $_GET['page']        = 'fp-publisher-settings';
        $_REQUEST['page']    = 'fp-publisher-settings';

        $GLOBALS['tts_current_screen'] = (object) array(
            'id'   => 'toplevel_page_fp-publisher-settings',
            'base' => 'toplevel_page_fp-publisher-settings',
            'cap'  => array( 'manage_options' ),
        );

        $audit->monitor_admin_access();

        tts_assert_equals(
            1,
            count( $audit->logged_events ),
            'Attempts to open restricted FP Publisher menus should be logged.'
        );

        $event = end( $audit->logged_events );

        tts_assert_equals(
            TTS_Security_Audit::EVENT_PERMISSION_VIOLATION,
            $event['event_type'],
            'Restricted menu access should register as a permission violation.'
        );

        tts_assert_equals(
            'fp-publisher-settings',
            $event['data']['requested_page'],
            'The logged event should store the restricted menu slug.'
        );
    },
);

$failures = 0;
$messages = array();

echo "Running security audit tests\n";

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
