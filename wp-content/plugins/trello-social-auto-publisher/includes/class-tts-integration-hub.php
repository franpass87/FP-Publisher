<?php
/**
 * Integration Hub System
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles third-party integrations and API connections.
 */
class TTS_Integration_Hub implements TTS_Integration_Gateway_Interface {

    /**
     * Current database schema version.
     */
    const DB_VERSION = '1.0.0';

    /**
     * Name of the option storing the schema version.
     */
    const DB_VERSION_OPTION = 'tts_integration_hub_db_version';

    /**
     * Available integrations.
     */
    private $available_integrations = array(
        'crm' => array(
            'hubspot' => array(
                'name' => 'HubSpot',
                'description' => 'Sync contacts and track social media ROI',
                'fields' => array( 'api_key', 'portal_id' ),
                'features' => array( 'contact_sync', 'lead_tracking', 'roi_analytics' )
            ),
            'salesforce' => array(
                'name' => 'Salesforce',
                'description' => 'Integrate with Salesforce CRM for lead management',
                'fields' => array( 'username', 'password', 'security_token', 'instance_url' ),
                'features' => array( 'lead_sync', 'opportunity_tracking', 'custom_objects' )
            ),
            'pipedrive' => array(
                'name' => 'Pipedrive',
                'description' => 'Connect with Pipedrive for sales pipeline tracking',
                'fields' => array( 'api_token', 'company_domain' ),
                'features' => array( 'deal_sync', 'contact_management', 'activity_tracking' )
            )
        ),
        'ecommerce' => array(
            'woocommerce' => array(
                'name' => 'WooCommerce',
                'description' => 'Promote products automatically on social media',
                'fields' => array( 'consumer_key', 'consumer_secret', 'store_url' ),
                'features' => array( 'product_sync', 'automated_promotion', 'sales_tracking' )
            ),
            'shopify' => array(
                'name' => 'Shopify',
                'description' => 'Sync Shopify products for social promotion',
                'fields' => array( 'shop_domain', 'access_token' ),
                'features' => array( 'product_import', 'inventory_sync', 'order_tracking' )
            ),
            'stripe' => array(
                'name' => 'Stripe',
                'description' => 'Track revenue attribution from social media',
                'fields' => array( 'publishable_key', 'secret_key' ),
                'features' => array( 'payment_tracking', 'revenue_attribution', 'customer_analytics' )
            )
        ),
        'email_marketing' => array(
            'mailchimp' => array(
                'name' => 'Mailchimp',
                'description' => 'Sync email subscribers with social media audiences',
                'fields' => array( 'api_key', 'server_prefix' ),
                'features' => array( 'subscriber_sync', 'campaign_promotion', 'audience_segmentation' )
            ),
            'convertkit' => array(
                'name' => 'ConvertKit',
                'description' => 'Integrate email marketing with social campaigns',
                'fields' => array( 'api_key', 'api_secret' ),
                'features' => array( 'subscriber_tagging', 'sequence_triggers', 'form_promotion' )
            ),
            'constant_contact' => array(
                'name' => 'Constant Contact',
                'description' => 'Cross-promote email and social content',
                'fields' => array( 'api_key', 'access_token' ),
                'features' => array( 'contact_sync', 'campaign_sharing', 'analytics_integration' )
            )
        ),
        'design_tools' => array(
            'canva' => array(
                'name' => 'Canva',
                'description' => 'Import designs directly from Canva',
                'fields' => array( 'api_key' ),
                'features' => array( 'design_import', 'template_sync', 'brand_kit_access' )
            ),
            'figma' => array(
                'name' => 'Figma',
                'description' => 'Access Figma designs for social media',
                'fields' => array( 'personal_access_token' ),
                'features' => array( 'design_export', 'asset_extraction', 'collaboration' )
            ),
            'adobe_creative' => array(
                'name' => 'Adobe Creative Cloud',
                'description' => 'Sync with Adobe Creative applications',
                'fields' => array( 'client_id', 'client_secret', 'redirect_uri' ),
                'features' => array( 'asset_sync', 'library_access', 'version_control' )
            )
        ),
        'analytics' => array(
            'google_analytics' => array(
                'name' => 'Google Analytics',
                'description' => 'Track social media traffic and conversions',
                'fields' => array( 'tracking_id', 'view_id', 'service_account_json' ),
                'features' => array( 'traffic_tracking', 'conversion_analytics', 'goal_measurement' )
            ),
            'google_tag_manager' => array(
                'name' => 'Google Tag Manager',
                'description' => 'Advanced tracking and event management',
                'fields' => array( 'container_id', 'api_key' ),
                'features' => array( 'event_tracking', 'custom_dimensions', 'conversion_tracking' )
            ),
            'mixpanel' => array(
                'name' => 'Mixpanel',
                'description' => 'Advanced user behavior analytics',
                'fields' => array( 'project_token', 'api_secret' ),
                'features' => array( 'user_tracking', 'funnel_analysis', 'cohort_analysis' )
            )
        ),
        'productivity' => array(
            'zapier' => array(
                'name' => 'Zapier',
                'description' => 'Connect with 3000+ apps via Zapier',
                'fields' => array( 'webhook_url', 'api_key' ),
                'features' => array( 'workflow_automation', 'trigger_actions', 'data_sync' )
            ),
            'slack' => array(
                'name' => 'Slack',
                'description' => 'Receive notifications and collaborate in Slack',
                'fields' => array( 'webhook_url', 'bot_token' ),
                'features' => array( 'notifications', 'approval_workflows', 'team_collaboration' )
            ),
            'discord' => array(
                'name' => 'Discord',
                'description' => 'Community management and notifications',
                'fields' => array( 'webhook_url', 'bot_token' ),
                'features' => array( 'community_updates', 'automated_posting', 'engagement_tracking' )
            )
        )
    );

    /**
     * Credential provisioner dependency.
     *
     * @var TTS_Credential_Provisioner_Interface|null
     */
    private $credential_provisioner;

    /**
     * Observability channel dependency.
     *
     * @var TTS_Observability_Channel_Interface|null
     */
    private $telemetry_channel;

    /**
     * Initialize integration hub.
     *
     * @param TTS_Credential_Provisioner_Interface|null $credential_provisioner Credential provisioner.
     * @param TTS_Observability_Channel_Interface|null  $telemetry_channel      Telemetry channel.
     */
    public function __construct( $credential_provisioner = null, $telemetry_channel = null ) {
        if ( $credential_provisioner instanceof TTS_Credential_Provisioner_Interface ) {
            $this->credential_provisioner = $credential_provisioner;
        } else {
            $this->credential_provisioner = null;
        }

        if ( $telemetry_channel instanceof TTS_Observability_Channel_Interface ) {
            $this->telemetry_channel = $telemetry_channel;
        } else {
            $this->telemetry_channel = null;
        }

        add_action( 'wp_ajax_tts_connect_integration', array( $this, 'ajax_connect_integration' ) );
        add_action( 'wp_ajax_tts_disconnect_integration', array( $this, 'ajax_disconnect_integration' ) );
        add_action( 'wp_ajax_tts_test_integration', array( $this, 'ajax_test_integration' ) );
        add_action( 'wp_ajax_tts_sync_integration_data', array( $this, 'ajax_sync_integration_data' ) );
        add_action( 'wp_ajax_tts_get_integration_data', array( $this, 'ajax_get_integration_data' ) );
        add_action( 'wp_ajax_tts_configure_integration', array( $this, 'ajax_configure_integration' ) );
        add_action( 'wp_ajax_tts_get_available_integrations', array( $this, 'ajax_get_available_integrations' ) );

        // Schedule sync operations
        add_action( 'init', array( $this, 'schedule_integration_sync' ) );
        add_action( 'tts_integration_sync', array( $this, 'run_integration_sync' ) );
        add_action( 'tts_integration_sync_single', array( $this, 'run_single_integration_sync' ), 10, 1 );

        $this->maybe_record_event(
            'debug',
            __( 'Integration Hub initialized.', 'fp-publisher' ),
            array(
                'hooks_registered' => true,
            )
        );
    }

    /**
     * Dispatch an integration message from other modules.
     *
     * @param TTS_Integration_Message $message Integration message payload.
     */
    public function dispatch_message( TTS_Integration_Message $message ) {
        $integration_id = $message->get_integration_id();
        $operation      = $message->get_operation();
        $payload        = $message->get_payload();

        if ( 'sync_now' === $operation && $integration_id ) {
            $this->run_single_integration_sync( $integration_id );
        } elseif ( 'schedule_sync' === $operation && $integration_id ) {
            if ( function_exists( 'as_schedule_single_action' ) ) {
                as_schedule_single_action( time(), 'tts_integration_sync_single', array( $integration_id ) );
            }
        } elseif ( 'publication_sync' === $operation ) {
            if ( isset( $payload['integration_ids'] ) && is_array( $payload['integration_ids'] ) ) {
                foreach ( $payload['integration_ids'] as $id ) {
                    $id = absint( $id );
                    if ( $id ) {
                        $this->trigger_integration_sync( $id );
                    }
                }
            } elseif ( $integration_id ) {
                $this->trigger_integration_sync( $integration_id );
            }
        }

        $credential_request = $message->get_credential_request();
        if ( $credential_request instanceof TTS_Credential_Request && $this->credential_provisioner instanceof TTS_Credential_Provisioner_Interface ) {
            $this->credential_provisioner->issue_secret( $credential_request );
        }

        $this->maybe_record_event(
            'info',
            sprintf( __( 'Dispatched integration operation: %s', 'fp-publisher' ), $operation ),
            array_merge(
                array(
                    'integration_id' => $integration_id,
                    'operation'      => $operation,
                ),
                $message->get_context()
            )
        );
    }

    /**
     * Handle installation tasks.
     */
    public static function install() {
        $installed_version = get_option( self::DB_VERSION_OPTION );

        self::create_integration_tables();

        if ( false === $installed_version || version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
            // Future schema migrations should be added here when DB_VERSION increases.
            update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
        }
    }

    /**
     * Create integration database tables.
     */
    public static function create_integration_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        
        // Integrations table
        $integrations_table = $wpdb->prefix . 'tts_integrations';
        $sql = "CREATE TABLE $integrations_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            integration_type varchar(50) NOT NULL,
            integration_name varchar(100) NOT NULL,
            status varchar(20) DEFAULT 'inactive',
            credentials text,
            settings text,
            last_sync datetime,
            sync_status varchar(50),
            error_message text,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY integration_type (integration_type),
            KEY status (status)
        ) $charset_collate;";
        
        // Integration data table
        $data_table = $wpdb->prefix . 'tts_integration_data';
        $sql2 = "CREATE TABLE $data_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            integration_id int(11) NOT NULL,
            data_type varchar(50) NOT NULL,
            external_id varchar(255),
            local_id int(11),
            data_content longtext,
            sync_status varchar(20) DEFAULT 'pending',
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY integration_id (integration_id),
            KEY data_type (data_type),
            KEY external_id (external_id),
            KEY sync_status (sync_status)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        dbDelta( $sql2 );
    }

    /**
     * Get available integrations.
     */
    public function ajax_get_available_integrations() {
        check_ajax_referer( 'tts_integration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
        }

        try {
            wp_send_json_success( array(
                'integrations' => $this->available_integrations,
                'connected' => $this->get_connected_integrations(),
                'message' => __( 'Available integrations retrieved successfully!', 'fp-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Get Integrations Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to retrieve integrations. Please try again.', 'fp-publisher' ) ) );
        }
    }

    /**
     * Connect integration.
     */
    public function ajax_connect_integration() {
        check_ajax_referer( 'tts_integration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
        }

        $integration_type = sanitize_text_field( wp_unslash( $_POST['integration_type'] ?? '' ) );
        $integration_name = sanitize_text_field( wp_unslash( $_POST['integration_name'] ?? '' ) );
        $credentials = array_map( 'sanitize_text_field', wp_unslash( $_POST['credentials'] ?? array() ) );
        $settings = array_map( 'sanitize_text_field', wp_unslash( $_POST['settings'] ?? array() ) );

        if ( empty( $integration_type ) || empty( $integration_name ) ) {
            wp_send_json_error( array( 'message' => __( 'Integration type and name are required.', 'fp-publisher' ) ) );
        }

        try {
            $connection_result = $this->connect_integration( $integration_type, $integration_name, $credentials, $settings );

            $success_message = ! empty( $connection_result['message'] ) ? $connection_result['message'] : __( 'Integration connected successfully!', 'fp-publisher' );

            wp_send_json_success(
                array(
                    'integration_id' => $connection_result['integration_id'],
                    'message' => $success_message,
                    'test_result' => $connection_result['details'],
                )
            );
        } catch ( Exception $e ) {
            error_log( 'TTS Integration Connection Error: ' . $e->getMessage() );

            $message = $e->getMessage();
            if ( empty( $message ) ) {
                $message = __( 'Failed to connect integration. Please check your credentials and try again.', 'fp-publisher' );
            }

            wp_send_json_error( array( 'message' => $message ) );
        }
    }

    /**
     * Connect integration to system.
     *
     * @param string $integration_type Integration type.
     * @param string $integration_name Integration name.
     * @param array $credentials Credentials.
     * @param array $settings Settings.
     * @return int Integration ID.
     */
    private function connect_integration( $integration_type, $integration_name, $credentials, $settings ) {
        global $wpdb;
        
        // Validate integration exists
        if ( ! isset( $this->available_integrations[ $integration_type ][ $integration_name ] ) ) {
            throw new Exception( 'Invalid integration specified' );
        }
        
        $integration_config = $this->available_integrations[ $integration_type ][ $integration_name ];
        
        // Validate required fields
        if ( isset( $integration_config['fields'] ) && is_array( $integration_config['fields'] ) ) {
            foreach ( $integration_config['fields'] as $field ) {
                if ( ! isset( $credentials[ $field ] ) || empty( $credentials[ $field ] ) ) {
                    throw new Exception( "Missing required field: {$field}" );
                }
            }
        }
        
        // Test connection
        $test_result = $this->test_integration_connection( $integration_type, $integration_name, $credentials );
        
        if ( ! isset( $test_result['success'] ) || true !== $test_result['success'] ) {
            $error_message = isset( $test_result['error'] ) ? $test_result['error'] : __( 'Unknown connection error', 'fp-publisher' );
            throw new Exception( sprintf( __( 'Connection test failed: %s', 'fp-publisher' ), $error_message ) );
        }
        
        // Encrypt credentials for storage
        $encrypted_credentials = $this->encrypt_credentials( $credentials );
        
        $integrations_table = $wpdb->prefix . 'tts_integrations';
        
        // Check if integration already exists
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM $integrations_table WHERE integration_type = %s AND integration_name = %s",
                $integration_type,
                $integration_name
            )
        );
        
        if ( $existing ) {
            // Update existing integration
            $result = $wpdb->update(
                $integrations_table,
                array(
                    'status' => 'active',
                    'credentials' => $encrypted_credentials,
                    'settings' => maybe_serialize( $settings ),
                    'error_message' => null
                ),
                array( 'id' => $existing->id ),
                array( '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );
            
            $integration_id = $existing->id;
        } else {
            // Create new integration
            $result = $wpdb->insert(
                $integrations_table,
                array(
                    'integration_type' => $integration_type,
                    'integration_name' => $integration_name,
                    'status' => 'active',
                    'credentials' => $encrypted_credentials,
                    'settings' => maybe_serialize( $settings )
                ),
                array( '%s', '%s', '%s', '%s', '%s' )
            );
            
            $integration_id = $wpdb->insert_id;
        }
        
        if ( false === $result ) {
            throw new Exception( 'Failed to save integration to database' );
        }
        
        // Trigger initial data sync
        $this->trigger_integration_sync( $integration_id );

        $success_message = isset( $test_result['message'] ) ? $test_result['message'] : __( 'Integration connected successfully!', 'fp-publisher' );

        return array(
            'integration_id' => $integration_id,
            'message' => $success_message,
            'details' => $test_result,
        );
    }

    /**
     * Test integration connection.
     *
     * @param string $integration_type Integration type.
     * @param string $integration_name Integration name.
     * @param array $credentials Credentials.
     * @return array Test result.
     */
    private function test_integration_connection( $integration_type, $integration_name, $credentials ) {
        $test_methods = array(
            'hubspot' => array( $this, 'test_hubspot_connection' ),
            'salesforce' => array( $this, 'test_salesforce_connection' ),
            'woocommerce' => array( $this, 'test_woocommerce_connection' ),
            'mailchimp' => array( $this, 'test_mailchimp_connection' ),
            'google_analytics' => array( $this, 'test_google_analytics_connection' ),
            'zapier' => array( $this, 'test_zapier_connection' )
        );

        if ( isset( $test_methods[ $integration_name ] ) ) {
            $result = call_user_func( $test_methods[ $integration_name ], $credentials );

            if ( ! is_array( $result ) || ! array_key_exists( 'success', $result ) ) {
                return array(
                    'success' => false,
                    'error' => __( 'Unexpected response from integration validator.', 'fp-publisher' ),
                );
            }

            return $result;
        }

        return array(
            'success' => false,
            'error' => sprintf( __( 'The %s integration does not yet support connection testing.', 'fp-publisher' ), $integration_name ),
        );
    }

    /**
     * Test HubSpot connection.
     *
     * @param array $credentials Credentials.
     * @return array Test result.
     */
    private function test_hubspot_connection( $credentials ) {
        $api_key = isset( $credentials['api_key'] ) ? trim( $credentials['api_key'] ) : '';

        if ( empty( $api_key ) ) {
            return array(
                'success' => false,
                'error' => __( 'A HubSpot API key or private app token is required.', 'fp-publisher' ),
            );
        }

        $endpoint = 'https://api.hubapi.com/integrations/v1/me';
        $args = array(
            'timeout' => 20,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        );

        $use_private_token = 0 === strpos( $api_key, 'pat-' );

        if ( $use_private_token ) {
            $args['headers']['Authorization'] = 'Bearer ' . $api_key;
        } else {
            $endpoint = add_query_arg( 'hapikey', $api_key, $endpoint );
        }

        $response = wp_remote_get( $endpoint, $args );

        if ( ! $use_private_token && ! is_wp_error( $response ) && 401 === wp_remote_retrieve_response_code( $response ) ) {
            // Attempt fallback using Authorization header in case a private token was provided without the "pat-" prefix.
            $args['headers']['Authorization'] = 'Bearer ' . $api_key;
            $endpoint = 'https://api.hubapi.com/integrations/v1/me';
            $response = wp_remote_get( $endpoint, $args );
        }

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'error' => sprintf( __( 'HubSpot request failed: %s', 'fp-publisher' ), $response->get_error_message() ),
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( 200 !== $status_code ) {
            $error_message = __( 'Unexpected response from HubSpot.', 'fp-publisher' );

            if ( is_array( $data ) && isset( $data['message'] ) ) {
                $error_message = $data['message'];
            } elseif ( ! empty( $body ) ) {
                $error_message = $body;
            }

            return array(
                'success' => false,
                'error' => sprintf( __( 'HubSpot responded with HTTP %1$d: %2$s', 'fp-publisher' ), $status_code, $error_message ),
            );
        }

        $account_info = array();

        if ( is_array( $data ) ) {
            $account_info = array(
                'portal_id' => $data['portalId'] ?? ( $credentials['portal_id'] ?? '' ),
                'portal_name' => $data['portalName'] ?? '',
                'time_zone' => $data['timezone'] ?? '',
                'app_id' => $data['appId'] ?? '',
            );
        }

        return array(
            'success' => true,
            'message' => __( 'HubSpot connection successful.', 'fp-publisher' ),
            'account_info' => array_filter( $account_info ),
        );
    }

    /**
     * Test Salesforce connection.
     *
     * @param array $credentials Credentials.
     * @return array Test result.
     */
    private function test_salesforce_connection( $credentials ) {
        $instance_url = isset( $credentials['instance_url'] ) ? trim( $credentials['instance_url'] ) : '';
        $access_token = isset( $credentials['security_token'] ) ? trim( $credentials['security_token'] ) : '';

        if ( empty( $instance_url ) ) {
            return array(
                'success' => false,
                'error' => __( 'A Salesforce instance URL is required.', 'fp-publisher' ),
            );
        }

        if ( ! preg_match( '#^https?://#i', $instance_url ) ) {
            $instance_url = 'https://' . ltrim( $instance_url, '/' );
        }

        if ( ! wp_http_validate_url( $instance_url ) ) {
            return array(
                'success' => false,
                'error' => __( 'The Salesforce instance URL is not valid.', 'fp-publisher' ),
            );
        }

        if ( empty( $access_token ) ) {
            return array(
                'success' => false,
                'error' => __( 'A Salesforce security token or access token is required.', 'fp-publisher' ),
            );
        }

        $endpoint = trailingslashit( $instance_url ) . 'services/data/v57.0/limits';
        $response = wp_remote_get(
            $endpoint,
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept' => 'application/json',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'error' => sprintf( __( 'Salesforce request failed: %s', 'fp-publisher' ), $response->get_error_message() ),
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( 200 !== $status_code ) {
            $error_message = __( 'Unexpected response from Salesforce.', 'fp-publisher' );

            if ( is_array( $data ) && isset( $data[0]['message'] ) ) {
                $error_message = $data[0]['message'];
            } elseif ( is_array( $data ) && isset( $data['message'] ) ) {
                $error_message = $data['message'];
            } elseif ( ! empty( $body ) ) {
                $error_message = $body;
            }

            return array(
                'success' => false,
                'error' => sprintf( __( 'Salesforce responded with HTTP %1$d: %2$s', 'fp-publisher' ), $status_code, $error_message ),
            );
        }

        $limits_preview = array();

        if ( is_array( $data ) ) {
            $limits_preview = array_slice( $data, 0, 5, true );
        }

        return array(
            'success' => true,
            'message' => __( 'Salesforce connection successful.', 'fp-publisher' ),
            'org_info' => array(
                'instance_url' => $instance_url,
                'username' => $credentials['username'] ?? '',
                'limits' => $limits_preview,
            ),
        );
    }

    /**
     * Test WooCommerce connection.
     *
     * @param array $credentials Credentials.
     * @return array Test result.
     */
    private function test_woocommerce_connection( $credentials ) {
        $consumer_key = isset( $credentials['consumer_key'] ) ? trim( $credentials['consumer_key'] ) : '';
        $consumer_secret = isset( $credentials['consumer_secret'] ) ? trim( $credentials['consumer_secret'] ) : '';
        $store_url = isset( $credentials['store_url'] ) ? trim( $credentials['store_url'] ) : '';

        if ( empty( $consumer_key ) || empty( $consumer_secret ) ) {
            return array(
                'success' => false,
                'error' => __( 'WooCommerce consumer key and secret are required.', 'fp-publisher' ),
            );
        }

        if ( empty( $store_url ) ) {
            return array(
                'success' => false,
                'error' => __( 'A WooCommerce store URL is required.', 'fp-publisher' ),
            );
        }

        if ( ! preg_match( '#^https?://#i', $store_url ) ) {
            $store_url = 'https://' . ltrim( $store_url, '/' );
        }

        if ( ! wp_http_validate_url( $store_url ) ) {
            return array(
                'success' => false,
                'error' => __( 'The WooCommerce store URL is not valid.', 'fp-publisher' ),
            );
        }

        $endpoint = trailingslashit( $store_url ) . 'wp-json/wc/v3/system_status';
        $args = array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $consumer_key . ':' . $consumer_secret ),
                'Accept' => 'application/json',
            ),
        );

        $response = wp_remote_get( $endpoint, $args );

        if ( ! is_wp_error( $response ) ) {
            $status_code = wp_remote_retrieve_response_code( $response );

            if ( in_array( $status_code, array( 401, 403 ), true ) ) {
                // Retry using query parameters for stores that do not accept Basic auth.
                $query_endpoint = add_query_arg(
                    array(
                        'consumer_key' => $consumer_key,
                        'consumer_secret' => $consumer_secret,
                    ),
                    $endpoint
                );

                $response = wp_remote_get(
                    $query_endpoint,
                    array(
                        'timeout' => 20,
                        'headers' => array( 'Accept' => 'application/json' ),
                    )
                );
            }
        }

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'error' => sprintf( __( 'WooCommerce request failed: %s', 'fp-publisher' ), $response->get_error_message() ),
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( 200 !== $status_code ) {
            $error_message = __( 'Unexpected response from WooCommerce.', 'fp-publisher' );

            if ( is_array( $data ) && isset( $data['message'] ) ) {
                $error_message = $data['message'];
            } elseif ( ! empty( $body ) ) {
                $error_message = $body;
            }

            return array(
                'success' => false,
                'error' => sprintf( __( 'WooCommerce responded with HTTP %1$d: %2$s', 'fp-publisher' ), $status_code, $error_message ),
            );
        }

        $store_info = array();

        if ( is_array( $data ) ) {
            $store_info = array(
                'environment' => $data['environment'] ?? array(),
                'database' => $data['database'] ?? array(),
                'theme' => $data['theme'] ?? array(),
            );
        }

        return array(
            'success' => true,
            'message' => __( 'WooCommerce connection successful.', 'fp-publisher' ),
            'store_info' => $store_info,
        );
    }

    /**
     * Test Mailchimp connection.
     *
     * @param array $credentials Credentials.
     * @return array Test result.
     */
    private function test_mailchimp_connection( $credentials ) {
        $api_key = isset( $credentials['api_key'] ) ? trim( $credentials['api_key'] ) : '';
        $server_prefix = isset( $credentials['server_prefix'] ) ? trim( $credentials['server_prefix'] ) : '';

        if ( empty( $api_key ) ) {
            return array(
                'success' => false,
                'error' => __( 'A Mailchimp API key is required.', 'fp-publisher' ),
            );
        }

        if ( empty( $server_prefix ) ) {
            $parts = explode( '-', $api_key );
            if ( count( $parts ) > 1 ) {
                $server_prefix = end( $parts );
            }
        }

        if ( empty( $server_prefix ) ) {
            return array(
                'success' => false,
                'error' => __( 'Unable to determine the Mailchimp data center. Please provide the server prefix.', 'fp-publisher' ),
            );
        }

        $endpoint = sprintf( 'https://%s.api.mailchimp.com/3.0/ping', $server_prefix );
        $response = wp_remote_get(
            $endpoint,
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( 'trello:' . $api_key ),
                    'Accept' => 'application/json',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'error' => sprintf( __( 'Mailchimp request failed: %s', 'fp-publisher' ), $response->get_error_message() ),
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( 200 !== $status_code ) {
            $error_message = __( 'Unexpected response from Mailchimp.', 'fp-publisher' );

            if ( is_array( $data ) && isset( $data['detail'] ) ) {
                $error_message = $data['detail'];
            } elseif ( ! empty( $body ) ) {
                $error_message = $body;
            }

            return array(
                'success' => false,
                'error' => sprintf( __( 'Mailchimp responded with HTTP %1$d: %2$s', 'fp-publisher' ), $status_code, $error_message ),
            );
        }

        $account_info = array();

        if ( is_array( $data ) ) {
            $account_info = array(
                'health_status' => $data['health_status'] ?? '',
                'data_center' => $server_prefix,
            );
        }

        return array(
            'success' => true,
            'message' => __( 'Mailchimp connection successful.', 'fp-publisher' ),
            'account_info' => array_filter( $account_info ),
        );
    }

    /**
     * Test Google Analytics connection.
     *
     * @param array $credentials Credentials.
     * @return array Test result.
     */
    private function test_google_analytics_connection( $credentials ) {
        $service_account_json = isset( $credentials['service_account_json'] ) ? trim( $credentials['service_account_json'] ) : '';
        $tracking_id = isset( $credentials['tracking_id'] ) ? trim( $credentials['tracking_id'] ) : '';
        $view_id = isset( $credentials['view_id'] ) ? trim( $credentials['view_id'] ) : '';

        if ( empty( $service_account_json ) ) {
            return array(
                'success' => false,
                'error' => __( 'Google service account credentials are required.', 'fp-publisher' ),
            );
        }

        if ( ! function_exists( 'openssl_sign' ) ) {
            return array(
                'success' => false,
                'error' => __( 'The OpenSSL PHP extension is required to authenticate with Google Analytics.', 'fp-publisher' ),
            );
        }

        $service_account = json_decode( $service_account_json, true );

        if ( empty( $service_account ) || ! isset( $service_account['client_email'], $service_account['private_key'] ) ) {
            return array(
                'success' => false,
                'error' => __( 'Invalid Google service account JSON.', 'fp-publisher' ),
            );
        }

        $token_uri = $service_account['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $scope = 'https://www.googleapis.com/auth/analytics.readonly';

        $header_json = wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) );

        if ( false === $header_json ) {
            return array(
                'success' => false,
                'error' => __( 'Failed to encode the Google authentication header.', 'fp-publisher' ),
            );
        }

        $jwt_header = $this->base64_url_encode( $header_json );

        $now = time();
        $claims = array(
            'iss' => $service_account['client_email'],
            'scope' => $scope,
            'aud' => $token_uri,
            'exp' => $now + 3600,
            'iat' => $now,
        );

        $claims_json = wp_json_encode( $claims );

        if ( false === $claims_json ) {
            return array(
                'success' => false,
                'error' => __( 'Failed to encode the Google authentication claims.', 'fp-publisher' ),
            );
        }

        $jwt_claim = $this->base64_url_encode( $claims_json );

        $signing_input = $jwt_header . '.' . $jwt_claim;
        $signature = '';
        $private_key_resource = openssl_pkey_get_private( $service_account['private_key'] );

        if ( false === $private_key_resource ) {
            return array(
                'success' => false,
                'error' => __( 'Unable to parse the Google private key.', 'fp-publisher' ),
            );
        }

        $signature_success = openssl_sign( $signing_input, $signature, $private_key_resource, 'sha256WithRSAEncryption' );
        openssl_free_key( $private_key_resource );

        if ( ! $signature_success ) {
            return array(
                'success' => false,
                'error' => __( 'Failed to sign the Google service account JWT.', 'fp-publisher' ),
            );
        }

        $assertion = $signing_input . '.' . $this->base64_url_encode( $signature );

        $token_response = wp_remote_post(
            $token_uri,
            array(
                'timeout' => 20,
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body' => array(
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ),
            )
        );

        if ( is_wp_error( $token_response ) ) {
            return array(
                'success' => false,
                'error' => sprintf( __( 'Google token request failed: %s', 'fp-publisher' ), $token_response->get_error_message() ),
            );
        }

        $token_status = wp_remote_retrieve_response_code( $token_response );
        $token_body = wp_remote_retrieve_body( $token_response );
        $token_data = json_decode( $token_body, true );

        if ( 200 !== $token_status || empty( $token_data['access_token'] ) ) {
            $error_message = __( 'Unexpected response while obtaining a Google access token.', 'fp-publisher' );

            if ( is_array( $token_data ) && isset( $token_data['error_description'] ) ) {
                $error_message = $token_data['error_description'];
            } elseif ( is_array( $token_data ) && isset( $token_data['error'] ) ) {
                $error_message = $token_data['error'];
            } elseif ( ! empty( $token_body ) ) {
                $error_message = $token_body;
            }

            return array(
                'success' => false,
                'error' => sprintf( __( 'Google token endpoint responded with HTTP %1$d: %2$s', 'fp-publisher' ), $token_status, $error_message ),
            );
        }

        $access_token = $token_data['access_token'];

        $profiles_endpoint = add_query_arg(
            'max-results',
            1000,
            'https://analytics.googleapis.com/analytics/v3/management/accounts/~all/webproperties/~all/profiles'
        );

        $profiles_response = wp_remote_get(
            $profiles_endpoint,
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept' => 'application/json',
                ),
            )
        );

        if ( is_wp_error( $profiles_response ) ) {
            return array(
                'success' => false,
                'error' => sprintf( __( 'Google Analytics request failed: %s', 'fp-publisher' ), $profiles_response->get_error_message() ),
            );
        }

        $profiles_status = wp_remote_retrieve_response_code( $profiles_response );
        $profiles_body = wp_remote_retrieve_body( $profiles_response );
        $profiles_data = json_decode( $profiles_body, true );

        if ( 200 !== $profiles_status ) {
            $error_message = __( 'Unexpected response from the Google Analytics Management API.', 'fp-publisher' );

            if ( is_array( $profiles_data ) && isset( $profiles_data['error']['message'] ) ) {
                $error_message = $profiles_data['error']['message'];
            } elseif ( ! empty( $profiles_body ) ) {
                $error_message = $profiles_body;
            }

            return array(
                'success' => false,
                'error' => sprintf( __( 'Google Analytics responded with HTTP %1$d: %2$s', 'fp-publisher' ), $profiles_status, $error_message ),
            );
        }

        $matched_profile = null;
        $profiles = array();

        if ( is_array( $profiles_data ) && isset( $profiles_data['items'] ) && is_array( $profiles_data['items'] ) ) {
            $profiles = $profiles_data['items'];

            foreach ( $profiles as $profile ) {
                if ( isset( $profile['id'] ) && (string) $profile['id'] === (string) $view_id ) {
                    $matched_profile = $profile;
                    break;
                }
            }
        }

        if ( ! empty( $view_id ) && null === $matched_profile ) {
            return array(
                'success' => false,
                'error' => __( 'Authenticated with Google Analytics, but the specified View ID was not found.', 'fp-publisher' ),
            );
        }

        if ( ! empty( $tracking_id ) && is_array( $matched_profile ) && isset( $matched_profile['webPropertyId'] ) && (string) $matched_profile['webPropertyId'] !== (string) $tracking_id ) {
            return array(
                'success' => false,
                'error' => __( 'The provided tracking ID does not match the selected Google Analytics view.', 'fp-publisher' ),
            );
        }

        $profile_summary = array();

        if ( is_array( $matched_profile ) ) {
            $profile_summary = array(
                'profile_name' => $matched_profile['name'] ?? '',
                'web_property_id' => $matched_profile['webPropertyId'] ?? '',
                'timezone' => $matched_profile['timezone'] ?? '',
            );
        } elseif ( ! empty( $profiles ) ) {
            $first_profile = reset( $profiles );
            $profile_summary = array(
                'profile_name' => $first_profile['name'] ?? '',
                'web_property_id' => $first_profile['webPropertyId'] ?? '',
                'timezone' => $first_profile['timezone'] ?? '',
            );
        }

        return array(
            'success' => true,
            'message' => __( 'Google Analytics connection successful.', 'fp-publisher' ),
            'property_info' => array_filter( $profile_summary ),
        );
    }

    /**
     * Test Zapier connection.
     *
     * @param array $credentials Credentials.
     * @return array Test result.
     */
    private function test_zapier_connection( $credentials ) {
        $webhook_url = isset( $credentials['webhook_url'] ) ? trim( $credentials['webhook_url'] ) : '';

        if ( empty( $webhook_url ) || ! wp_http_validate_url( $webhook_url ) ) {
            return array(
                'success' => false,
                'error' => __( 'A valid Zapier webhook URL is required.', 'fp-publisher' ),
            );
        }

        $payload = array(
            'test' => true,
            'source' => 'tts_integration_hub',
            'generated_at' => gmdate( 'c' ),
        );

        $payload_json = wp_json_encode( $payload );

        if ( false === $payload_json ) {
            return array(
                'success' => false,
                'error' => __( 'Failed to encode the Zapier test payload.', 'fp-publisher' ),
            );
        }

        $request_args = array(
            'timeout' => 20,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => $payload_json,
        );

        if ( ! empty( $credentials['api_key'] ) ) {
            $request_args['headers']['X-API-Key'] = $credentials['api_key'];
        }

        $response = wp_remote_post( $webhook_url, $request_args );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'error' => sprintf( __( 'Zapier request failed: %s', 'fp-publisher' ), $response->get_error_message() ),
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $status_code < 200 || $status_code >= 300 ) {
            $error_message = __( 'Unexpected response from Zapier.', 'fp-publisher' );

            if ( ! empty( $body ) ) {
                $error_message = $body;
            }

            return array(
                'success' => false,
                'error' => sprintf( __( 'Zapier responded with HTTP %1$d: %2$s', 'fp-publisher' ), $status_code, $error_message ),
            );
        }

        $decoded_body = json_decode( $body, true );

        return array(
            'success' => true,
            'message' => __( 'Zapier webhook connection successful.', 'fp-publisher' ),
            'response' => is_array( $decoded_body ) ? $decoded_body : $body,
        );
    }

    /**
     * Encode a string using base64 URL-safe encoding without padding.
     *
     * @param string $data Data to encode.
     * @return string Encoded string.
     */
    private function base64_url_encode( $data ) {
        $encoded = base64_encode( $data );

        if ( false === $encoded ) {
            return '';
        }

        return rtrim( strtr( $encoded, '+/', '-_' ), '=' );
    }

    /**
     * Encrypt credentials for secure storage.
     *
     * @param array $credentials Credentials to encrypt.
     * @return string Encrypted credentials.
     */
    private function encrypt_credentials( $credentials ) {
        $serialized = maybe_serialize( $credentials );
        
        // Use WordPress built-in encryption if available, fallback to secured base64
        if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
            $key = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY );
            $method = 'AES-256-CBC';
            $iv_length = openssl_cipher_iv_length( $method );
            $iv = openssl_random_pseudo_bytes( $iv_length );
            
            $encrypted = openssl_encrypt( $serialized, $method, $key, 0, $iv );
            return base64_encode( $iv . $encrypted );
        }
        
        // Fallback with additional security layer
        return base64_encode( hash( 'sha256', wp_salt() ) . '|' . base64_encode( $serialized ) );
    }

    /**
     * Decrypt credentials.
     *
     * @param string $encrypted_credentials Encrypted credentials.
     * @return array Decrypted credentials.
     */
    private function decrypt_credentials( $encrypted_credentials ) {
        if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
            $key = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY );
            $method = 'AES-256-CBC';
            $iv_length = openssl_cipher_iv_length( $method );
            
            $data = base64_decode( $encrypted_credentials );
            if ( false === $data ) {
                return array(); // Invalid base64 data
            }
            
            $iv = substr( $data, 0, $iv_length );
            $encrypted = substr( $data, $iv_length );
            
            $decrypted = openssl_decrypt( $encrypted, $method, $key, 0, $iv );
            if ( $decrypted !== false ) {
                return maybe_unserialize( $decrypted );
            }
        }
        
        // Handle fallback format
        $decoded = base64_decode( $encrypted_credentials );
        if ( false === $decoded ) {
            return array(); // Invalid base64 data
        }
        
        if ( strpos( $decoded, '|' ) !== false ) {
            list( $hash, $encoded_data ) = explode( '|', $decoded, 2 );
            if ( hash_equals( $hash, hash( 'sha256', wp_salt() ) ) ) {
                $decoded_data = base64_decode( $encoded_data );
                if ( false !== $decoded_data ) {
                    return maybe_unserialize( $decoded_data );
                }
            }
        }
        
        return array();
    }

    /**
     * Disconnect integration.
     */
    public function ajax_disconnect_integration() {
        check_ajax_referer( 'tts_integration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
        }

        $integration_id = intval( $_POST['integration_id'] ?? 0 );

        if ( empty( $integration_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Integration ID is required.', 'fp-publisher' ) ) );
        }

        try {
            $this->disconnect_integration( $integration_id );
            
            wp_send_json_success( array(
                'message' => __( 'Integration disconnected successfully!', 'fp-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Integration Disconnection Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to disconnect integration. Please try again.', 'fp-publisher' ) ) );
        }
    }

    /**
     * Disconnect integration.
     *
     * @param int $integration_id Integration ID.
     */
    private function disconnect_integration( $integration_id ) {
        global $wpdb;
        
        $integrations_table = $wpdb->prefix . 'tts_integrations';
        
        $result = $wpdb->update(
            $integrations_table,
            array( 'status' => 'inactive' ),
            array( 'id' => $integration_id ),
            array( '%s' ),
            array( '%d' )
        );
        
        if ( false === $result ) {
            throw new Exception( 'Failed to disconnect integration' );
        }
    }

    /**
     * Test integration connection.
     */
    public function ajax_test_integration() {
        check_ajax_referer( 'tts_integration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
        }

        $integration_id = intval( $_POST['integration_id'] ?? 0 );

        if ( empty( $integration_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Integration ID is required.', 'fp-publisher' ) ) );
        }

        try {
            $test_result = $this->test_existing_integration( $integration_id );
            
            wp_send_json_success( array(
                'test_result' => $test_result,
                'message' => __( 'Integration tested successfully!', 'fp-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Integration Test Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to test integration. Please try again.', 'fp-publisher' ) ) );
        }
    }

    /**
     * Test existing integration.
     *
     * @param int $integration_id Integration ID.
     * @return array Test result.
     */
    private function test_existing_integration( $integration_id ) {
        global $wpdb;
        
        $integrations_table = $wpdb->prefix . 'tts_integrations';
        
        $integration = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $integrations_table WHERE id = %d", $integration_id ),
            ARRAY_A
        );
        
        if ( ! $integration ) {
            throw new Exception( 'Integration not found' );
        }
        
        $credentials = $this->decrypt_credentials( $integration['credentials'] );
        
        return $this->test_integration_connection( 
            $integration['integration_type'],
            $integration['integration_name'],
            $credentials
        );
    }

    /**
     * Sync integration data.
     */
    public function ajax_sync_integration_data() {
        check_ajax_referer( 'tts_integration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'fp-publisher' ) );
        }

        $integration_id = intval( $_POST['integration_id'] ?? 0 );
        $data_type = sanitize_text_field( wp_unslash( $_POST['data_type'] ?? 'all' ) );

        if ( empty( $integration_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Integration ID is required.', 'fp-publisher' ) ) );
        }

        try {
            $sync_result = $this->sync_integration_data( $integration_id, $data_type );
            
            wp_send_json_success( array(
                'sync_result' => $sync_result,
                'message' => __( 'Integration data synced successfully!', 'fp-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Integration Sync Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to sync integration data. Please try again.', 'fp-publisher' ) ) );
        }
    }

    /**
     * Sync data from integration.
     *
     * @param int $integration_id Integration ID.
     * @param string $data_type Data type to sync.
     * @return array Sync result.
     */
    private function sync_integration_data( $integration_id, $data_type ) {
        global $wpdb;
        
        $integrations_table = $wpdb->prefix . 'tts_integrations';
        
        $integration = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $integrations_table WHERE id = %d", $integration_id ),
            ARRAY_A
        );
        
        if ( ! $integration || $integration['status'] !== 'active' ) {
            throw new Exception( 'Integration not found or inactive' );
        }
        
        $sync_methods = array(
            'hubspot' => array( $this, 'sync_hubspot_data' ),
            'salesforce' => array( $this, 'sync_salesforce_data' ),
            'woocommerce' => array( $this, 'sync_woocommerce_data' ),
            'mailchimp' => array( $this, 'sync_mailchimp_data' ),
            'google_analytics' => array( $this, 'sync_google_analytics_data' )
        );
        
        $sync_result = array(
            'synced_records' => 0,
            'failed_records' => 0,
            'data_types' => array(),
            'last_sync' => current_time( 'mysql' )
        );
        
        if ( isset( $sync_methods[ $integration['integration_name'] ] ) ) {
            $credentials = $this->decrypt_credentials( $integration['credentials'] );
            $sync_result = call_user_func(
                $sync_methods[ $integration['integration_name'] ],
                $integration_id,
                $credentials,
                $data_type
            );
        } else {
            // Return error for unsupported integrations
            $sync_result = new WP_Error( 'unsupported_integration', 'Integration sync method not implemented' );
        }
        
        // Update integration sync status
        $wpdb->update(
            $integrations_table,
            array(
                'last_sync' => current_time( 'mysql' ),
                'sync_status' => 'completed'
            ),
            array( 'id' => $integration_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
        
        return $sync_result;
    }

    /**
     * Sync HubSpot data.
     *
     * @param int    $integration_id Integration ID.
     * @param array  $credentials Credentials.
     * @param string $data_type Data type.
     * @return array Sync result.
     */
    private function sync_hubspot_data( $integration_id, $credentials, $data_type ) {
        $data_types = array( 'contacts', 'companies', 'deals', 'campaigns' );
        
        if ( $data_type === 'all' ) {
            $types_to_sync = $data_types;
        } else {
            $types_to_sync = array( $data_type );
        }
        
        $total_synced = 0;
        $total_failed = 0;
        
        foreach ( $types_to_sync as $type ) {
            $sync_result = $this->fetch_hubspot_data( $credentials, $type );
            
            if ( is_wp_error( $sync_result ) ) {
                $total_failed++;
                continue;
            }
            
            $synced_count = count( $sync_result['data'] );
            $total_synced += $synced_count;
            
            // Store synced data in database
            if ( $synced_count > 0 ) {
                $this->store_integration_data(
                    $integration_id,
                    $type,
                    $sync_result['data']
                );
            }
        }
        
        return array(
            'synced_records' => $total_synced,
            'failed_records' => $total_failed,
            'data_types' => $types_to_sync,
            'last_sync' => current_time( 'mysql' )
        );
    }

    /**
     * Fetch actual HubSpot data via API.
     *
     * @param array $credentials HubSpot credentials.
     * @param string $data_type Data type to fetch.
     * @return array|WP_Error API response or error.
     */
    private function fetch_hubspot_data( $credentials, $data_type ) {
        if ( empty( $credentials['api_key'] ) || empty( $credentials['portal_id'] ) ) {
            return new WP_Error( 'missing_credentials', 'Missing HubSpot API credentials' );
        }
        
        $api_key = $credentials['api_key'];
        $portal_id = $credentials['portal_id'];
        
        // HubSpot API endpoints
        $endpoints = array(
            'contacts' => "https://api.hubapi.com/crm/v3/objects/contacts?limit=100&hapikey={$api_key}",
            'companies' => "https://api.hubapi.com/crm/v3/objects/companies?limit=100&hapikey={$api_key}",
            'deals' => "https://api.hubapi.com/crm/v3/objects/deals?limit=100&hapikey={$api_key}",
            'campaigns' => "https://api.hubapi.com/email/public/v1/campaigns?limit=100&hapikey={$api_key}"
        );
        
        if ( ! isset( $endpoints[ $data_type ] ) ) {
            return new WP_Error( 'invalid_data_type', 'Invalid data type specified' );
        }
        
        $response = wp_remote_get( $endpoints[ $data_type ], array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
            )
        ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_decode_error', 'Failed to decode HubSpot API response' );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            return new WP_Error( 
                'api_error', 
                'HubSpot API error: ' . ( isset( $data['message'] ) ? $data['message'] : 'Unknown error' ),
                $response_code 
            );
        }
        
        // Process and normalize the data
        $processed_data = array();
        if ( isset( $data['results'] ) && is_array( $data['results'] ) ) {
            foreach ( $data['results'] as $item ) {
                $processed_data[] = $this->normalize_hubspot_data( $item, $data_type );
            }
        }
        
        return array(
            'data' => $processed_data,
            'total' => $data['total'] ?? count( $processed_data ),
            'has_more' => $data['paging']['next']['link'] ?? false
        );
    }

    /**
     * Normalize HubSpot data for consistent storage.
     *
     * @param array $item Raw HubSpot item.
     * @param string $data_type Data type.
     * @return array Normalized data.
     */
    private function normalize_hubspot_data( $item, $data_type ) {
        $normalized = array(
            'id' => $item['id'] ?? '',
            'created_date' => current_time( 'mysql' ),
            'source' => 'hubspot',
            'type' => $data_type
        );
        
        switch ( $data_type ) {
            case 'contacts':
                $properties = $item['properties'] ?? array();
                $normalized['email'] = $properties['email'] ?? '';
                $normalized['name'] = trim( ( $properties['firstname'] ?? '' ) . ' ' . ( $properties['lastname'] ?? '' ) );
                $normalized['company'] = $properties['company'] ?? '';
                break;
                
            case 'companies':
                $properties = $item['properties'] ?? array();
                $normalized['name'] = $properties['name'] ?? '';
                $normalized['domain'] = $properties['domain'] ?? '';
                $normalized['industry'] = $properties['industry'] ?? '';
                break;
                
            case 'deals':
                $properties = $item['properties'] ?? array();
                $normalized['name'] = $properties['dealname'] ?? '';
                $normalized['amount'] = $properties['amount'] ?? 0;
                $normalized['stage'] = $properties['dealstage'] ?? '';
                break;
                
            case 'campaigns':
                $normalized['name'] = $item['name'] ?? '';
                $normalized['subject'] = $item['subject'] ?? '';
                $normalized['status'] = $item['state'] ?? '';
                break;
        }
        
        return $normalized;
    }

    /**
     * Sync WooCommerce data.
     *
     * @param int    $integration_id Integration ID.
     * @param array  $credentials Credentials.
     * @param string $data_type Data type.
     * @return array Sync result.
     */
    private function sync_woocommerce_data( $integration_id, $credentials, $data_type ) {
        // Check if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            return new WP_Error( 'woocommerce_inactive', 'WooCommerce plugin is not active' );
        }
        
        $data_types = array( 'products', 'orders', 'customers', 'categories' );
        
        if ( $data_type === 'all' ) {
            $types_to_sync = $data_types;
        } else {
            $types_to_sync = array( $data_type );
        }
        
        $total_synced = 0;
        $total_failed = 0;
        
        foreach ( $types_to_sync as $type ) {
            $sync_result = $this->fetch_woocommerce_data( $type );
            
            if ( is_wp_error( $sync_result ) ) {
                $total_failed++;
                continue;
            }
            
            $synced_count = count( $sync_result );
            $total_synced += $synced_count;
            
            // Store synced data
            if ( $synced_count > 0 ) {
                $this->store_integration_data(
                    $integration_id,
                    $type,
                    $sync_result
                );
            }
        }
        
        return array(
            'synced_records' => $total_synced,
            'failed_records' => $total_failed,
            'data_types' => $types_to_sync,
            'last_sync' => current_time( 'mysql' )
        );
    }
    
    /**
     * Fetch WooCommerce data.
     *
     * @param string $data_type Data type to fetch.
     * @return array WooCommerce data.
     */
    private function fetch_woocommerce_data( $data_type ) {
        $data = array();
        $limit = 100; // Limit results for performance
        
        switch ( $data_type ) {
            case 'products':
                $products = wc_get_products( array( 'limit' => $limit, 'status' => 'publish' ) );
                foreach ( $products as $product ) {
                    $data[] = array(
                        'id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'price' => $product->get_price(),
                        'sku' => $product->get_sku(),
                        'stock_status' => $product->get_stock_status(),
                        'categories' => wp_list_pluck( $product->get_category_ids(), 'name' ),
                        'created_date' => $product->get_date_created()->date( 'Y-m-d H:i:s' )
                    );
                }
                break;
                
            case 'orders':
                $orders = wc_get_orders( array( 'limit' => $limit ) );
                foreach ( $orders as $order ) {
                    $data[] = array(
                        'id' => $order->get_id(),
                        'status' => $order->get_status(),
                        'total' => $order->get_total(),
                        'customer_id' => $order->get_customer_id(),
                        'billing_email' => $order->get_billing_email(),
                        'created_date' => $order->get_date_created()->date( 'Y-m-d H:i:s' )
                    );
                }
                break;
                
            case 'customers':
                $customer_query = new WP_User_Query( array(
                    'role' => 'customer',
                    'number' => $limit
                ) );
                
                foreach ( $customer_query->get_results() as $customer ) {
                    $wc_customer = new WC_Customer( $customer->ID );
                    $data[] = array(
                        'id' => $customer->ID,
                        'email' => $customer->user_email,
                        'first_name' => $wc_customer->get_first_name(),
                        'last_name' => $wc_customer->get_last_name(),
                        'total_spent' => $wc_customer->get_total_spent(),
                        'order_count' => $wc_customer->get_order_count(),
                        'created_date' => $customer->user_registered
                    );
                }
                break;
                
            case 'categories':
                $categories = get_terms( array(
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false,
                    'number' => $limit
                ) );
                
                foreach ( $categories as $category ) {
                    $data[] = array(
                        'id' => $category->term_id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'description' => $category->description,
                        'product_count' => $category->count,
                        'parent' => $category->parent
                    );
                }
                break;
        }
        
        return $data;
    }

    /**
     * Sync Mailchimp data.
     *
     * @param int    $integration_id Integration ID.
     * @param array  $credentials Credentials.
     * @param string $data_type Data type.
     * @return array Sync result.
     */
    private function sync_mailchimp_data( $integration_id, $credentials, $data_type ) {
        if ( empty( $credentials['api_key'] ) ) {
            return new WP_Error( 'missing_credentials', 'Missing Mailchimp API key' );
        }
        
        $data_types = array( 'subscribers', 'campaigns', 'lists', 'segments' );
        
        if ( $data_type === 'all' ) {
            $types_to_sync = $data_types;
        } else {
            $types_to_sync = array( $data_type );
        }
        
        $total_synced = 0;
        $total_failed = 0;
        
        foreach ( $types_to_sync as $type ) {
            $sync_result = $this->fetch_mailchimp_data( $credentials, $type );
            
            if ( is_wp_error( $sync_result ) ) {
                $total_failed++;
                continue;
            }
            
            $synced_count = count( $sync_result['data'] );
            $total_synced += $synced_count;
            
            // Store synced data
            if ( $synced_count > 0 ) {
                $this->store_integration_data(
                    $integration_id,
                    $type,
                    $sync_result['data']
                );
            }
        }
        
        return array(
            'synced_records' => $total_synced,
            'failed_records' => $total_failed,
            'data_types' => $types_to_sync,
            'last_sync' => current_time( 'mysql' )
        );
    }
    
    /**
     * Fetch Mailchimp data via API.
     *
     * @param array $credentials Mailchimp credentials.
     * @param string $data_type Data type to fetch.
     * @return array|WP_Error API response or error.
     */
    private function fetch_mailchimp_data( $credentials, $data_type ) {
        $api_key = $credentials['api_key'];
        $datacenter = substr( $api_key, strpos( $api_key, '-' ) + 1 );
        $base_url = "https://{$datacenter}.api.mailchimp.com/3.0";
        
        $endpoints = array(
            'lists' => "/lists?count=100",
            'campaigns' => "/campaigns?count=100",
            'subscribers' => "/lists/{list_id}/members?count=100",
            'segments' => "/lists/{list_id}/segments?count=100"
        );
        
        if ( ! isset( $endpoints[ $data_type ] ) ) {
            return new WP_Error( 'invalid_data_type', 'Invalid Mailchimp data type' );
        }
        
        $endpoint = $endpoints[ $data_type ];
        
        // For subscribers and segments, we need a list ID
        if ( in_array( $data_type, array( 'subscribers', 'segments' ) ) ) {
            $list_id = $credentials['list_id'] ?? '';
            if ( empty( $list_id ) ) {
                return new WP_Error( 'missing_list_id', 'List ID required for this data type' );
            }
            $endpoint = str_replace( '{list_id}', $list_id, $endpoint );
        }
        
        $response = wp_remote_get( $base_url . $endpoint, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            )
        ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            return new WP_Error( 
                'api_error', 
                'Mailchimp API error: ' . ( $data['title'] ?? 'Unknown error' ),
                $response_code 
            );
        }
        
        // Process the response based on data type
        $processed_data = array();
        if ( isset( $data['lists'] ) ) {
            $processed_data = $data['lists'];
        } elseif ( isset( $data['campaigns'] ) ) {
            $processed_data = $data['campaigns'];
        } elseif ( isset( $data['members'] ) ) {
            $processed_data = $data['members'];
        } elseif ( isset( $data['segments'] ) ) {
            $processed_data = $data['segments'];
        }
        
        return array(
            'data' => $processed_data,
            'total' => $data['total_items'] ?? count( $processed_data )
        );
    }

    /**
     * Store integration data.
     *
     * @param int    $integration_id Integration ID.
     * @param string $data_type Data type.
     * @param array $data Data to store.
     */
    private function store_integration_data( $integration_id, $data_type, $data ) {
        global $wpdb;

        $data_table = $wpdb->prefix . 'tts_integration_data';

        $validated_integration_id = filter_var(
            $integration_id,
            FILTER_VALIDATE_INT,
            array(
                'options' => array(
                    'min_range' => 1,
                ),
            )
        );

        if ( false === $validated_integration_id ) {
            $message = sprintf(
                'Invalid integration ID provided for data storage: %s',
                is_scalar( $integration_id ) ? (string) $integration_id : gettype( $integration_id )
            );

            if ( class_exists( 'TTS_Logger' ) ) {
                TTS_Logger::log(
                    $message,
                    'error',
                    array(
                        'integration_id' => $integration_id,
                        'data_type'      => $data_type,
                    )
                );
            } else {
                $context         = array( 'data_type' => $data_type );
                $encoded_context = function_exists( 'wp_json_encode' )
                    ? wp_json_encode( $context )
                    : json_encode( $context );

                error_log( '[TTS][ERROR][integration_hub] ' . $message . ' | Context: ' . $encoded_context );
            }

            return;
        }

        if ( empty( $data ) || ! is_array( $data ) ) {
            return;
        }

        foreach ( $data as $record ) {
            if ( empty( $record ) || ! is_array( $record ) ) {
                continue;
            }

            $external_id = isset( $record['id'] ) ? (string) $record['id'] : null;

            $wpdb->replace(
                $data_table,
                array(
                    'integration_id' => $validated_integration_id,
                    'data_type' => $data_type,
                    'external_id' => $external_id,
                    'data_content' => maybe_serialize( $record ),
                    'sync_status' => 'completed'
                ),
                array( '%d', '%s', '%s', '%s', '%s' )
            );
        }
    }

    /**
     * Get connected integrations.
     *
     * @return array Connected integrations.
     */
    private function get_connected_integrations() {
        global $wpdb;
        
        $integrations_table = $wpdb->prefix . 'tts_integrations';
        
        return $wpdb->get_results(
            "SELECT integration_type, integration_name, status, last_sync, sync_status 
            FROM $integrations_table 
            WHERE status = 'active' 
            ORDER BY integration_type, integration_name",
            ARRAY_A
        );
    }

    /**
     * Schedule integration sync.
     */
    public function schedule_integration_sync() {
        if ( ! wp_next_scheduled( 'tts_integration_sync' ) ) {
            wp_schedule_event( time(), 'hourly', 'tts_integration_sync' );
        }
    }

    /**
     * Run single integration sync event.
     *
     * @param int $integration_id Integration ID.
     */
    public function run_single_integration_sync( int $integration_id ) {
        $original_integration_id = $integration_id;
        $integration_id          = absint( $integration_id );

        if ( empty( $integration_id ) ) {
            $message = 'TTS: Invalid integration ID provided for single sync event.';
            error_log( $message );

            if ( class_exists( 'TTS_Logger' ) ) {
                TTS_Logger::log(
                    $message,
                    'warning',
                    array(
                        'integration_id'          => $integration_id,
                        'provided_integration_id' => $original_integration_id,
                    )
                );
            }

            $timestamp = wp_next_scheduled( 'tts_integration_sync_single', array( $original_integration_id ) );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, 'tts_integration_sync_single', array( $original_integration_id ) );
            }

            return;
        }

        try {
            $this->sync_integration_data( $integration_id, 'all' );
        } catch ( \Throwable $exception ) {
            $message = sprintf( 'TTS: Single integration sync failed for ID %d: %s', $integration_id, $exception->getMessage() );
            error_log( $message );

            if ( class_exists( 'TTS_Logger' ) ) {
                TTS_Logger::log(
                    $message,
                    'error',
                    array(
                        'integration_id' => $integration_id,
                        'exception'      => array(
                            'code'    => $exception->getCode(),
                            'message' => $exception->getMessage(),
                            'file'    => $exception->getFile(),
                            'line'    => $exception->getLine(),
                        ),
                    )
                );
            }
        }
    }

    /**
     * Run scheduled integration sync.
     */
    public function run_integration_sync() {
        global $wpdb;
        
        $integrations_table = $wpdb->prefix . 'tts_integrations';
        
        $active_integrations = $wpdb->get_results(
            "SELECT id FROM $integrations_table WHERE status = 'active'",
            ARRAY_A
        );
        
        foreach ( $active_integrations as $integration ) {
            try {
                $this->sync_integration_data( $integration['id'], 'all' );
            } catch ( Exception $e ) {
                error_log( 'Scheduled integration sync failed for ID ' . $integration['id'] . ': ' . $e->getMessage() );
            }
        }
        
        error_log( 'TTS: Integration sync completed for ' . count( $active_integrations ) . ' integrations' );
    }

    /**
     * Trigger integration sync.
     *
     * @param int $integration_id Integration ID.
     */
    private function trigger_integration_sync( $integration_id ) {
        // Schedule immediate sync
        wp_schedule_single_event( time() + 60, 'tts_integration_sync_single', array( $integration_id ) );
    }

    /**
     * Emit an observability event if telemetry is configured.
     *
     * @param string $level   Severity level.
     * @param string $message Message body.
     * @param array  $context Context payload.
     */
    private function maybe_record_event( $level, $message, $context = array() ) {
        if ( ! $this->telemetry_channel instanceof TTS_Observability_Channel_Interface ) {
            return;
        }

        $event = new TTS_Observability_Event( 'integration-hub', $level, $message, $context );
        $this->telemetry_channel->record_event( $event );
    }
}

// Initialize Integration Hub
new TTS_Integration_Hub();
