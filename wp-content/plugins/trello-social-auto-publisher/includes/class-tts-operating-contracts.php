<?php
/**
 * Shared contracts for the target operating model.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Value object describing a scheduling request across modules.
 */
class TTS_Schedule_Request {

    /**
     * Post identifier.
     *
     * @var int
     */
    private $post_id;

    /**
     * Client identifier associated with the post.
     *
     * @var int
     */
    private $client_id;

    /**
     * Target channels for the publication.
     *
     * @var array
     */
    private $channels = array();

    /**
     * Timestamp when the content should be published.
     *
     * @var int|null
     */
    private $publish_at;

    /**
     * Whether the workflow is approved for scheduling.
     *
     * @var bool
     */
    private $approved;

    /**
     * Additional metadata describing the request context.
     *
     * @var array
     */
    private $metadata = array();

    /**
     * Constructor.
     *
     * @param int        $post_id    Post identifier.
     * @param int        $client_id  Client identifier.
     * @param array      $channels   List of target channels.
     * @param int|string $publish_at Timestamp or parsable datetime string.
     * @param bool       $approved   Whether the post is approved.
     * @param array      $metadata   Extra metadata.
     */
    public function __construct( $post_id, $client_id, $channels, $publish_at, $approved = true, $metadata = array() ) {
        $this->post_id   = absint( $post_id );
        $this->client_id = absint( $client_id );

        $channels = is_array( $channels ) ? $channels : array();
        $channels = array_filter( array_map( 'sanitize_key', $channels ) );
        $this->channels = array_values( $channels );

        if ( is_numeric( $publish_at ) ) {
            $timestamp = (int) $publish_at;
        } else {
            $timestamp = strtotime( (string) $publish_at );
        }

        $this->publish_at = $timestamp ? $timestamp : null;
        $this->approved   = (bool) $approved;

        $this->metadata = is_array( $metadata ) ? $metadata : array();
    }

    /**
     * Get post identifier.
     *
     * @return int
     */
    public function get_post_id() {
        return $this->post_id;
    }

    /**
     * Get client identifier.
     *
     * @return int
     */
    public function get_client_id() {
        return $this->client_id;
    }

    /**
     * Get target channels.
     *
     * @return array
     */
    public function get_channels() {
        return $this->channels;
    }

    /**
     * Whether the request is approved for scheduling.
     *
     * @return bool
     */
    public function is_approved() {
        return $this->approved;
    }

    /**
     * Get publish timestamp.
     *
     * @return int|null
     */
    public function get_publish_timestamp() {
        return $this->publish_at;
    }

    /**
     * Get metadata context.
     *
     * @return array
     */
    public function get_metadata() {
        return $this->metadata;
    }

    /**
     * Export request as array for logging or serialization.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'post_id'    => $this->post_id,
            'client_id'  => $this->client_id,
            'channels'   => $this->channels,
            'publish_at' => $this->publish_at,
            'approved'   => $this->approved,
            'metadata'   => $this->metadata,
        );
    }
}

/**
 * Value object used to describe cancellation requests.
 */
class TTS_Schedule_Cancellation {

    /**
     * Post identifier.
     *
     * @var int
     */
    private $post_id;

    /**
     * Channels to cancel.
     *
     * @var array
     */
    private $channels = array();

    /**
     * Constructor.
     *
     * @param int   $post_id  Post identifier.
     * @param array $channels Channels to unschedule.
     */
    public function __construct( $post_id, $channels = array() ) {
        $this->post_id = absint( $post_id );

        $channels = is_array( $channels ) ? $channels : array();
        $channels = array_filter( array_map( 'sanitize_key', $channels ) );
        $this->channels = array_values( $channels );
    }

    /**
     * Get post identifier.
     *
     * @return int
     */
    public function get_post_id() {
        return $this->post_id;
    }

    /**
     * Get list of channels.
     *
     * @return array
     */
    public function get_channels() {
        return $this->channels;
    }

    /**
     * Export cancellation as array.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'post_id' => $this->post_id,
            'channels' => $this->channels,
        );
    }
}

/**
 * Observability payload for telemetry events.
 */
class TTS_Observability_Event {

    /**
     * Module emitting the event.
     *
     * @var string
     */
    private $module;

    /**
     * Severity level.
     *
     * @var string
     */
    private $level;

    /**
     * Message describing the event.
     *
     * @var string
     */
    private $message;

    /**
     * Structured context for the event.
     *
     * @var array
     */
    private $context = array();

    /**
     * Event timestamp.
     *
     * @var int
     */
    private $timestamp;

    /**
     * Constructor.
     *
     * @param string $module    Module name.
     * @param string $level     Severity level.
     * @param string $message   Event message.
     * @param array  $context   Additional context.
     * @param int    $timestamp Optional timestamp override.
     */
    public function __construct( $module, $level, $message, $context = array(), $timestamp = null ) {
        $this->module  = sanitize_key( $module );
        $this->level   = sanitize_key( $level );
        $this->message = wp_strip_all_tags( (string) $message );
        $this->context = is_array( $context ) ? $context : array();
        $this->timestamp = $timestamp ? (int) $timestamp : time();
    }

    /**
     * Module name.
     *
     * @return string
     */
    public function get_module() {
        return $this->module;
    }

    /**
     * Severity level.
     *
     * @return string
     */
    public function get_level() {
        return $this->level;
    }

    /**
     * Event message.
     *
     * @return string
     */
    public function get_message() {
        return $this->message;
    }

    /**
     * Event context.
     *
     * @return array
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Event timestamp.
     *
     * @return int
     */
    public function get_timestamp() {
        return $this->timestamp;
    }

    /**
     * Export event as array.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'module'    => $this->module,
            'level'     => $this->level,
            'message'   => $this->message,
            'context'   => $this->context,
            'timestamp' => $this->timestamp,
        );
    }
}

/**
 * Integration message exchanged between workflow and hub.
 */
class TTS_Integration_Message {

    /**
     * Integration identifier.
     *
     * @var int
     */
    private $integration_id;

    /**
     * Operation name (e.g., sync_now, schedule_sync).
     *
     * @var string
     */
    private $operation;

    /**
     * Payload to execute with the integration.
     *
     * @var array
     */
    private $payload = array();

    /**
     * Additional context for observability.
     *
     * @var array
     */
    private $context = array();

    /**
     * Optional credential request.
     *
     * @var TTS_Credential_Request|null
     */
    private $credential_request;

    /**
     * Constructor.
     *
     * @param int                        $integration_id     Integration identifier.
     * @param string                     $operation          Operation to perform.
     * @param array                      $payload            Integration payload.
     * @param array                      $context            Observability context.
     * @param TTS_Credential_Request|null $credential_request Optional credential request.
     */
    public function __construct( $integration_id, $operation, $payload = array(), $context = array(), $credential_request = null ) {
        $this->integration_id = absint( $integration_id );
        $this->operation      = sanitize_key( $operation );
        $this->payload        = is_array( $payload ) ? $payload : array();
        $this->context        = is_array( $context ) ? $context : array();

        if ( $credential_request instanceof TTS_Credential_Request ) {
            $this->credential_request = $credential_request;
        } else {
            $this->credential_request = null;
        }
    }

    /**
     * Integration identifier.
     *
     * @return int
     */
    public function get_integration_id() {
        return $this->integration_id;
    }

    /**
     * Operation.
     *
     * @return string
     */
    public function get_operation() {
        return $this->operation;
    }

    /**
     * Payload.
     *
     * @return array
     */
    public function get_payload() {
        return $this->payload;
    }

    /**
     * Context.
     *
     * @return array
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Credential request.
     *
     * @return TTS_Credential_Request|null
     */
    public function get_credential_request() {
        return $this->credential_request;
    }

    /**
     * Export message as array.
     *
     * @return array
     */
    public function to_array() {
        $data = array(
            'integration_id' => $this->integration_id,
            'operation'      => $this->operation,
            'payload'        => $this->payload,
            'context'        => $this->context,
        );

        if ( $this->credential_request instanceof TTS_Credential_Request ) {
            $data['credential_request'] = $this->credential_request->to_array();
        }

        return $data;
    }
}

/**
 * Credential request contract for provisioning module.
 */
class TTS_Credential_Request {

    /**
     * Client identifier requesting credentials.
     *
     * @var int
     */
    private $client_id;

    /**
     * Provider name.
     *
     * @var string
     */
    private $provider;

    /**
     * Requested scopes.
     *
     * @var array
     */
    private $scopes = array();

    /**
     * Additional hints (e.g., webhooks or integration metadata).
     *
     * @var array
     */
    private $hints = array();

    /**
     * Constructor.
     *
     * @param int    $client_id Client identifier.
     * @param string $provider  Provider name.
     * @param array  $scopes    Requested scopes.
     * @param array  $hints     Additional hints.
     */
    public function __construct( $client_id, $provider, $scopes = array(), $hints = array() ) {
        $this->client_id = absint( $client_id );
        $this->provider  = sanitize_key( $provider );

        $scopes = is_array( $scopes ) ? $scopes : array();
        $this->scopes = array_values( array_filter( array_map( 'sanitize_key', $scopes ) ) );

        $this->hints = is_array( $hints ) ? $hints : array();
    }

    /**
     * Client identifier.
     *
     * @return int
     */
    public function get_client_id() {
        return $this->client_id;
    }

    /**
     * Provider name.
     *
     * @return string
     */
    public function get_provider() {
        return $this->provider;
    }

    /**
     * Requested scopes.
     *
     * @return array
     */
    public function get_scopes() {
        return $this->scopes;
    }

    /**
     * Additional hints.
     *
     * @return array
     */
    public function get_hints() {
        return $this->hints;
    }

    /**
     * Export request as array.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'client_id' => $this->client_id,
            'provider'  => $this->provider,
            'scopes'    => $this->scopes,
            'hints'     => $this->hints,
        );
    }
}

/**
 * Credential secret contract returned by the provisioning module.
 */
class TTS_Credential_Secret {

    /**
     * Secret identifier.
     *
     * @var string
     */
    private $identifier;

    /**
     * Secret value.
     *
     * @var string
     */
    private $secret;

    /**
     * Expiration timestamp.
     *
     * @var int|null
     */
    private $expires_at;

    /**
     * Additional metadata.
     *
     * @var array
     */
    private $meta = array();

    /**
     * Constructor.
     *
     * @param string   $identifier Secret identifier.
     * @param string   $secret     Secret value.
     * @param int|null $expires_at Expiration timestamp.
     * @param array    $meta       Additional metadata.
     */
    public function __construct( $identifier, $secret, $expires_at = null, $meta = array() ) {
        $this->identifier = sanitize_key( $identifier );
        $this->secret     = (string) $secret;
        $this->expires_at = $expires_at ? (int) $expires_at : null;
        $this->meta       = is_array( $meta ) ? $meta : array();
    }

    /**
     * Secret identifier.
     *
     * @return string
     */
    public function get_identifier() {
        return $this->identifier;
    }

    /**
     * Secret value.
     *
     * @return string
     */
    public function get_secret() {
        return $this->secret;
    }

    /**
     * Expiration timestamp.
     *
     * @return int|null
     */
    public function get_expires_at() {
        return $this->expires_at;
    }

    /**
     * Whether the secret is expired.
     *
     * @return bool
     */
    public function is_expired() {
        if ( null === $this->expires_at ) {
            return false;
        }

        return time() >= $this->expires_at;
    }

    /**
     * Metadata.
     *
     * @return array
     */
    public function get_meta() {
        return $this->meta;
    }

    /**
     * Export secret as array.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'identifier' => $this->identifier,
            'secret'     => $this->secret,
            'expires_at' => $this->expires_at,
            'meta'       => $this->meta,
        );
    }
}

/**
 * Scheduler interface consumed by the core workflow.
 *
 * Implemented by {@see TTS_Scheduler}.
 */
interface TTS_Scheduler_Interface {

    /**
     * Queue a publication request.
     *
     * @param TTS_Schedule_Request $request Schedule request payload.
     */
    public function queue_from_request( TTS_Schedule_Request $request );

    /**
     * Release scheduled actions.
     *
     * @param TTS_Schedule_Cancellation $cancellation Cancellation payload.
     */
    public function release_schedule( TTS_Schedule_Cancellation $cancellation );
}

/**
 * Integration gateway abstraction for synchronization drivers.
 *
 * Implemented by {@see TTS_Integration_Hub}.
 */
interface TTS_Integration_Gateway_Interface {

    /**
     * Dispatch an integration message.
     *
     * @param TTS_Integration_Message $message Integration message.
     */
    public function dispatch_message( TTS_Integration_Message $message );
}

/**
 * Credential provisioning abstraction.
 */
interface TTS_Credential_Provisioner_Interface {

    /**
     * Issue a secret for the given request.
     *
     * @param TTS_Credential_Request $request Credential request.
     *
     * @return TTS_Credential_Secret|null
     */
    public function issue_secret( TTS_Credential_Request $request );

    /**
     * Revoke credentials associated with the request.
     *
     * @param TTS_Credential_Request $request Credential request.
     *
     * @return bool
     */
    public function revoke_secret( TTS_Credential_Request $request );
}

/**
 * Observability channel abstraction.
 */
interface TTS_Observability_Channel_Interface {

    /**
     * Record a telemetry event.
     *
     * @param TTS_Observability_Event $event Event payload.
     */
    public function record_event( TTS_Observability_Event $event );
}

/**
 * Default credential provisioner storing secrets in WordPress options.
 */
class TTS_Option_Credential_Provisioner implements TTS_Credential_Provisioner_Interface {

    /**
     * Option name where managed credentials are stored.
     */
    const OPTION_KEY = 'tts_managed_credentials';

    /**
     * Issue credential secret.
     *
     * @param TTS_Credential_Request $request Credential request.
     *
     * @return TTS_Credential_Secret|null
     */
    public function issue_secret( TTS_Credential_Request $request ) {
        $storage = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $storage ) ) {
            $storage = array();
        }

        $key = $this->build_storage_key( $request );

        $secret_value = function_exists( 'wp_generate_password' )
            ? wp_generate_password( 32, false )
            : md5( wp_json_encode( array( $key, microtime( true ) ) ) );

        $expires_at = time() + DAY_IN_SECONDS;

        $storage[ $key ] = array(
            'secret'     => $secret_value,
            'expires_at' => $expires_at,
            'meta'       => $request->get_hints(),
        );

        update_option( self::OPTION_KEY, $storage, false );

        return new TTS_Credential_Secret( $key, $secret_value, $expires_at, $request->get_hints() );
    }

    /**
     * Revoke credential secret.
     *
     * @param TTS_Credential_Request $request Credential request.
     *
     * @return bool
     */
    public function revoke_secret( TTS_Credential_Request $request ) {
        $storage = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $storage ) ) {
            return false;
        }

        $key = $this->build_storage_key( $request );

        if ( ! isset( $storage[ $key ] ) ) {
            return false;
        }

        unset( $storage[ $key ] );
        update_option( self::OPTION_KEY, $storage, false );

        return true;
    }

    /**
     * Build option key for the request.
     *
     * @param TTS_Credential_Request $request Credential request.
     *
     * @return string
     */
    private function build_storage_key( TTS_Credential_Request $request ) {
        return implode( ':', array( $request->get_client_id(), $request->get_provider() ) );
    }
}

/**
 * Observability channel implementation using the legacy logger.
 */
class TTS_Logger_Observability_Channel implements TTS_Observability_Channel_Interface {

    /**
     * Record telemetry event through the logger.
     *
     * @param TTS_Observability_Event $event Event payload.
     */
    public function record_event( TTS_Observability_Event $event ) {
        if ( ! function_exists( 'tts_log_event' ) ) {
            return;
        }

        $context = $event->get_context();
        $post_id = isset( $context['post_id'] ) ? absint( $context['post_id'] ) : 0;

        tts_log_event(
            $post_id,
            $event->get_module(),
            $event->get_level(),
            $event->get_message(),
            wp_json_encode( $context )
        );
    }
}
