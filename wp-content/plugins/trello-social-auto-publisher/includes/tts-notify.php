<?php
/**
 * Notification utilities for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Send a notification when a social post is processed.
 *
 * Uses a Slack webhook if configured via the `tts_slack_webhook` option,
 * otherwise falls back to the site's admin email via `wp_mail`.
 *
 * @param int    $post_id Post ID.
 * @param string $status  Publication status.
 * @param string $channel Social channel.
 *
 * @return bool Whether at least one notification channel succeeded.
 */
function tts_notify_publication( $post_id, $status, $channel ) {
    $post_id = absint( $post_id );
    $status  = sanitize_key( $status );
    $channel = sanitize_key( $channel );

    if ( '' === $status ) {
        $status = 'unknown';
    }

    if ( '' === $channel ) {
        $channel = 'general';
    }

    $title = '';

    if ( function_exists( 'get_the_title' ) ) {
        $title = (string) get_the_title( $post_id );
    }

    if ( '' === $title ) {
        $post  = get_post( $post_id );
        $title = $post && isset( $post->post_title ) ? (string) $post->post_title : '';
    }

    $title = wp_strip_all_tags( $title );

    if ( '' === $title ) {
        $title = sprintf( __( 'Post #%d', 'fp-publisher' ), $post_id );
    }

    $message = sprintf(
        __( 'Post "%s" on %s: %s', 'fp-publisher' ),
        $title,
        $channel,
        $status
    );

    $payload = apply_filters(
        'tts_notify_publication_payload',
        array(
            'post_id' => $post_id,
            'status'  => $status,
            'channel' => $channel,
            'message' => $message,
        ),
        $post_id,
        $status,
        $channel
    );

    $sent    = false;
    $webhook = trim( (string) get_option( 'tts_slack_webhook', '' ) );

    if ( '' !== $webhook ) {
        $response = wp_remote_post(
            esc_url_raw( $webhook ),
            array(
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body'    => wp_json_encode( array( 'text' => $payload['message'] ) ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $response ) ) {
            tts_notify_record_failure(
                $post_id,
                $channel,
                $status,
                'slack_http_error',
                $response->get_error_message()
            );
        } else {
            $status_code = (int) wp_remote_retrieve_response_code( $response );
            if ( $status_code >= 200 && $status_code < 300 ) {
                $sent = true;
            } else {
                tts_notify_record_failure(
                    $post_id,
                    $channel,
                    $status,
                    'slack_unexpected_status',
                    array(
                        'status' => $status_code,
                        'body'   => wp_remote_retrieve_body( $response ),
                    )
                );
            }
        }
    }

    if ( $sent ) {
        return true;
    }

    $recipient = apply_filters( 'tts_notify_publication_email', get_option( 'admin_email' ), $post_id, $status, $channel );
    $recipient = sanitize_email( $recipient );

    if ( '' === $recipient ) {
        tts_notify_record_failure( $post_id, $channel, $status, 'email_invalid_recipient', $payload['message'] );
        return false;
    }

    $subject = sprintf(
        __( '[Social Publish] %s - %s', 'fp-publisher' ),
        $channel,
        $status
    );

    $mail_sent = wp_mail( $recipient, $subject, $payload['message'] );

    if ( ! $mail_sent ) {
        tts_notify_record_failure( $post_id, $channel, $status, 'email_send_failed', $payload['message'] );
        return false;
    }

    return true;
}

/**
 * Record a notification delivery failure for observability and audits.
 *
 * @param int          $post_id Post identifier.
 * @param string       $channel Social channel identifier.
 * @param string       $status  Publication status.
 * @param string       $code    Failure code.
 * @param string|array $details Additional context details.
 */
function tts_notify_record_failure( $post_id, $channel, $status, $code, $details ) {
    $context = array(
        'component' => 'notification',
        'post_id'   => $post_id,
        'channel'   => $channel,
        'status'    => $status,
        'code'      => $code,
    );

    if ( class_exists( 'TTS_Logger' ) ) {
        TTS_Logger::log(
            sprintf( 'Notification delivery failure: %s', $code ),
            'warning',
            array_merge( $context, array( 'details' => $details ) )
        );
    }

    if ( function_exists( 'tts_log_event' ) ) {
        tts_log_event( $post_id, $channel, 'notification_error', $code, $details );
    }
}
