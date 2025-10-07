<?php

declare(strict_types=1);

namespace FP\Publisher\Api\Controllers;

use FP\Publisher\Services\Alerts;
use WP_REST_Request;
use WP_REST_Response;

use function register_rest_route;

/**
 * Controller per la gestione degli alert
 */
final class AlertsController extends BaseController
{
    public static function register(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/alerts',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'getAlerts'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_alerts'),
                ],
            ]
        );

        // Route specifiche per tipo di alert
        register_rest_route(
            self::NAMESPACE,
            '/alerts/empty-week',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'getEmptyWeekAlerts'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_alerts'),
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/alerts/token-expiry',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'getTokenExpiryAlerts'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_alerts'),
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/alerts/failed-jobs',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'getFailedJobsAlerts'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_alerts'),
                ],
            ]
        );
    }

    public static function getAlerts(): WP_REST_Response
    {
        $alerts = Alerts::getAll();
        return self::success(['items' => $alerts]);
    }

    public static function getEmptyWeekAlerts(): WP_REST_Response
    {
        $alerts = Alerts::checkEmptyWeek();
        return self::success(['items' => $alerts]);
    }

    public static function getTokenExpiryAlerts(): WP_REST_Response
    {
        $alerts = Alerts::checkTokenExpiry();
        return self::success(['items' => $alerts]);
    }

    public static function getFailedJobsAlerts(): WP_REST_Response
    {
        $alerts = Alerts::checkFailedJobs();
        return self::success(['items' => $alerts]);
    }
}