<?php

declare(strict_types=1);

namespace FP\Publisher\Api\Controllers;

use FP\Publisher\Infra\Queue;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function register_rest_route;
use function sanitize_text_field;
use function wp_unslash;

/**
 * Controller per la gestione dei job nella coda
 */
final class JobsController extends BaseController
{
    public static function register(): void
    {
        // POST /jobs - Accoda un nuovo job
        register_rest_route(
            self::NAMESPACE,
            '/jobs',
            [
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'enqueueJob'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
            ]
        );

        // POST /jobs/test - Testa un job
        register_rest_route(
            self::NAMESPACE,
            '/jobs/test',
            [
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'testJob'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
            ]
        );

        // POST /jobs/replay - Riprova un job fallito
        register_rest_route(
            self::NAMESPACE,
            '/jobs/replay',
            [
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'replayJob'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
            ]
        );
    }

    public static function enqueueJob(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $type = sanitize_text_field($request->get_param('type') ?? '');
        $payload = $request->get_param('payload') ?? [];

        if (!is_array($payload)) {
            return self::error('invalid_payload', 'Il payload deve essere un array.');
        }

        if ($type === '') {
            return self::error('missing_type', 'Il tipo di job è obbligatorio.');
        }

        $jobId = Queue::enqueue($type, $payload);
        return self::success(['job_id' => $jobId], 201);
    }

    public static function testJob(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $jobId = sanitize_text_field($request->get_param('job_id') ?? '');

        if ($jobId === '') {
            return self::error('missing_job_id', 'L\'ID del job è obbligatorio.');
        }

        $result = Queue::processJob($jobId);
        return self::success(['result' => $result]);
    }

    public static function replayJob(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $jobId = sanitize_text_field($request->get_param('job_id') ?? '');

        if ($jobId === '') {
            return self::error('missing_job_id', 'L\'ID del job è obbligatorio.');
        }

        Queue::retry($jobId);
        return self::success(['replayed' => true]);
    }
}