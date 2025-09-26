<?php
/**
 * AJAX security policy helper for admin controllers.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides shared nonce and capability checks for admin AJAX handlers.
 */
class TTS_Admin_Ajax_Security {

    /**
     * Map of AJAX handlers and their security requirements.
     *
     * @var array<string, array{nonce_action: string, capabilities: array<int, string>, nonce_field?: string}>
     */
    private $rules = array();

    /**
     * Constructor.
     *
     * @param array<string, array{nonce_action: string, capabilities: array<int, string>, nonce_field?: string}> $rules Security map.
     */
    public function __construct( array $rules ) {
        $this->rules = $rules;
    }

    /**
     * Validate nonce and capabilities for a given AJAX context.
     *
     * @param string               $context   AJAX handler context identifier.
     * @param array<string, mixed> $overrides Optional overrides for nonce and capability evaluation.
     *
     * @return bool True when the request is authorised.
     */
    public function check( $context, array $overrides = array() ) {
        if ( ! isset( $this->rules[ $context ] ) ) {
            return true;
        }

        $config       = $this->rules[ $context ];
        $nonce_action = isset( $overrides['nonce_action'] ) ? $overrides['nonce_action'] : $config['nonce_action'];
        $nonce_field  = isset( $config['nonce_field'] ) ? $config['nonce_field'] : 'nonce';

        if ( ! check_ajax_referer( $nonce_action, $nonce_field, false ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Invalid or missing nonce.', 'fp-publisher' ) ),
                403
            );
            return false;
        }

        $capabilities = array();
        if ( isset( $config['capabilities'] ) ) {
            $capabilities = (array) $config['capabilities'];
        }
        if ( isset( $overrides['capabilities'] ) ) {
            $capabilities = array_merge( $capabilities, (array) $overrides['capabilities'] );
        }

        foreach ( $capabilities as $capability ) {
            if ( ! current_user_can( $capability ) ) {
                wp_send_json_error(
                    array( 'message' => __( 'You do not have permission to perform this action.', 'fp-publisher' ) ),
                    403
                );
                return false;
            }
        }

        return true;
    }

    /**
     * Replace the current ruleset.
     *
     * @param array<string, array{nonce_action: string, capabilities: array<int, string>, nonce_field?: string}> $rules Security map.
     *
     * @return void
     */
    public function set_rules( array $rules ) {
        $this->rules = $rules;
    }
}
