<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/assertions.php';

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! function_exists( 'wp_salt' ) ) {
    function wp_salt( $scheme = 'auth' ) {
        return 'unit-test-salt-' . $scheme;
    }
}

require_once __DIR__ . '/../includes/class-tts-operating-contracts.php';
require_once __DIR__ . '/../includes/class-tts-integration-hub.php';

$tests = array(
    'custom_sync_handler_via_filter_is_used' => function () {
        tts_reset_test_state();

        $integration_row = array(
            'id'                => 101,
            'status'            => 'active',
            'integration_name'  => 'Custom CRM',
            'credentials'       => base64_encode(
                hash( 'sha256', wp_salt() ) . '|' . base64_encode( serialize( array( 'api_key' => 'abc123' ) ) )
            ),
            'sync_status'       => 'pending',
        );

        $GLOBALS['wpdb'] = new class( $integration_row ) extends TTS_Test_WPDB {
            private $row;
            public $updates = array();
            public $options = 'wp_options';

            public function __construct( $row ) {
                $this->row = $row;
            }

            public function get_row( $query, $output_type = ARRAY_A ) {
                unset( $query, $output_type );
                return $this->row;
            }

            public function update( $table, $data, $where, $format = null, $where_format = null ) {
                $this->updates[] = array(
                    'table' => $table,
                    'data'  => $data,
                    'where' => $where,
                );
                return true;
            }
        };

        $captured = array();

        add_filter(
            'tts_integration_hub_sync_methods',
            function ( $methods, $integration, $requested_type ) use ( &$captured ) {
                $captured['requested_type'] = $requested_type;
                $captured['integration_row'] = $integration;
                $slug = sanitize_key( $integration['integration_name'] ?? 'custom_crm' );

                $methods[ $slug ] = function ( $integration_id, $credentials, $data_type, $integration ) use ( &$captured ) {
                    $captured['handler'] = array(
                        'id'           => $integration_id,
                        'credentials'  => $credentials,
                        'data_type'    => $data_type,
                        'integration'  => $integration,
                    );

                    return array(
                        'synced_records' => 2,
                        'failed_records' => 0,
                        'data_types'     => array( $data_type ),
                        'last_sync'      => '2024-01-01 00:00:00',
                    );
                };

                return $methods;
            },
            10,
            3
        );

        $hub = new TTS_Integration_Hub();
        $reflection = new ReflectionClass( $hub );
        $method = $reflection->getMethod( 'sync_integration_data' );
        $method->setAccessible( true );

        $result = $method->invoke( $hub, 101, 'contacts' );

        if ( is_wp_error( $result ) ) {
            throw new RuntimeException( 'Handler returned WP_Error: ' . $result->get_error_message() );
        }

        tts_assert_equals( 2, $result['synced_records'], 'Custom handler should report synced records.' );
        tts_assert_equals( 'contacts', $result['data_types'][0], 'Requested data type should be forwarded to handlers.' );
        tts_assert_equals( 'contacts', $captured['requested_type'], 'Filters should receive the requested data type context.' );
        tts_assert_equals( 'Custom CRM', $captured['integration_row']['integration_name'], 'Filters should receive the raw integration row.' );
        tts_assert_equals( 'abc123', $captured['handler']['credentials']['api_key'], 'Decrypted credentials should be provided to handlers.' );
        tts_assert_equals( 'Custom CRM', $captured['handler']['integration']['integration_name'], 'Full integration context should be passed when requested by the handler.' );
    },
    'fallback_filter_handles_unknown_integrations' => function () {
        tts_reset_test_state();

        $integration_row = array(
            'id'                => 202,
            'status'            => 'active',
            'integration_name'  => 'Partner Suite',
            'credentials'       => base64_encode(
                hash( 'sha256', wp_salt() ) . '|' . base64_encode( serialize( array( 'token' => 'secret' ) ) )
            ),
            'sync_status'       => 'pending',
        );

        $GLOBALS['wpdb'] = new class( $integration_row ) extends TTS_Test_WPDB {
            private $row;
            public $options = 'wp_options';

            public function __construct( $row ) {
                $this->row = $row;
            }

            public function get_row( $query, $output_type = ARRAY_A ) {
                unset( $query, $output_type );
                return $this->row;
            }

            public function update( $table, $data, $where, $format = null, $where_format = null ) {
                unset( $table, $data, $where, $format, $where_format );
                return true;
            }
        };

        $captured = array();

        add_filter(
            'tts_integration_hub_handle_sync',
            function ( $result, $slug, $integration_id, $credentials, $data_type, $integration ) use ( &$captured ) {
                $captured = array(
                    'slug'         => $slug,
                    'id'           => $integration_id,
                    'credentials'  => $credentials,
                    'data_type'    => $data_type,
                    'integration'  => $integration,
                );

                return array(
                    'synced_records' => 1,
                    'failed_records' => 0,
                    'data_types'     => array( $data_type ),
                    'last_sync'      => '2024-02-02 10:00:00',
                );
            },
            10,
            6
        );

        $hub = new TTS_Integration_Hub();
        $reflection = new ReflectionClass( $hub );
        $method = $reflection->getMethod( 'sync_integration_data' );
        $method->setAccessible( true );

        $result = $method->invoke( $hub, 202, 'all' );

        if ( is_wp_error( $result ) ) {
            throw new RuntimeException( 'Fallback handler returned WP_Error: ' . $result->get_error_message() );
        }

        tts_assert_equals( 1, $result['synced_records'], 'Fallback filter should be able to provide sync results.' );
        tts_assert_equals( sanitize_key( 'Partner Suite' ), $captured['slug'], 'Integration slug should be provided to fallback handlers.' );
        tts_assert_equals( 'secret', $captured['credentials']['token'], 'Fallback handlers should receive decrypted credentials.' );
    },
);

$failures = 0;
$messages = array();

echo "Running integration hub extensibility tests\n";

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
