<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';
require_once __DIR__ . '/../includes/class-tts-security-audit.php';

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
