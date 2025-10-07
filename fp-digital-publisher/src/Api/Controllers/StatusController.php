<?php

declare(strict_types=1);

namespace FP\Publisher\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;

use function register_rest_route;

/**
 * Controller per lo stato del sistema
 */
final class StatusController extends BaseController
{
    public static function register(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/status',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'getStatus'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
            ]
        );
    }

    public static function getStatus(): WP_REST_Response
    {
        return self::success([
            'ok' => true,
            'version' => FP_PUBLISHER_VERSION ?? '0.0.0',
        ]);
    }
}