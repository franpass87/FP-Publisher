<?php

declare(strict_types=1);

namespace FP\Publisher\Api\Controllers;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function current_user_can;
use function wp_verify_nonce;

/**
 * Base controller con metodi comuni
 */
abstract class BaseController
{
    protected const NAMESPACE = 'fp-publisher/v1';

    /**
     * Autorizza una richiesta verificando capacità e nonce
     */
    protected static function authorize(WP_REST_Request $request, string $capability): bool|WP_Error
    {
        if (!current_user_can($capability)) {
            return new WP_Error(
                'rest_forbidden',
                'Non hai i permessi per eseguire questa azione.',
                ['status' => 403]
            );
        }

        $nonce = $request->get_header('X-WP-Nonce');
        if ($nonce && !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'rest_cookie_invalid_nonce',
                'Nonce non valido.',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Restituisce una risposta di collezione vuota
     */
    protected static function emptyCollection(): WP_REST_Response
    {
        return new WP_REST_Response(['items' => []], 200);
    }

    /**
     * Restituisce una risposta di successo
     */
    protected static function success(array $data = [], int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response($data, $status);
    }

    /**
     * Restituisce un errore
     */
    protected static function error(string $code, string $message, int $status = 400): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status]);
    }
}