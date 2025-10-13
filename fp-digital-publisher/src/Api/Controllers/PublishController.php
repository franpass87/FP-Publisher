<?php

declare(strict_types=1);

namespace FP\Publisher\Api\Controllers;

use FP\Publisher\Services\MultiChannelPublisher;
use FP\Publisher\Support\Dates;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

use function current_user_can;
use function is_array;
use function rest_ensure_response;

final class PublishController extends BaseController
{
    public static function registerRoutes(): void
    {
        // Multi-channel publishing
        register_rest_route(
            'fp-publisher/v1',
            '/publish/multi-channel',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'publishMultiChannel'],
                'permission_callback' => [self::class, 'checkPublishPermission'],
            ]
        );

        // Preview multi-channel
        register_rest_route(
            'fp-publisher/v1',
            '/publish/preview',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'previewMultiChannel'],
                'permission_callback' => [self::class, 'checkPublishPermission'],
            ]
        );
    }

    public static function publishMultiChannel(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $body = $request->get_json_params();

        if (! is_array($body)) {
            return new WP_Error('invalid_request', 'Invalid request body', ['status' => 400]);
        }

        $planData = is_array($body['plan'] ?? null) ? $body['plan'] : [];
        $selectedChannels = is_array($body['channels'] ?? null) ? $body['channels'] : [];
        $basePayload = is_array($body['payload'] ?? null) ? $body['payload'] : [];
        $publishAtStr = isset($body['publish_at']) ? (string) $body['publish_at'] : 'now';
        $clientId = isset($body['client_id']) ? (int) $body['client_id'] : null;

        if (empty($selectedChannels)) {
            return new WP_Error(
                'no_channels_selected',
                'Nessun canale selezionato per la pubblicazione',
                ['status' => 400]
            );
        }

        try {
            $publishAt = Dates::ensure($publishAtStr);

            $results = MultiChannelPublisher::publishToChannels(
                $planData,
                $selectedChannels,
                $basePayload,
                $publishAt,
                $clientId
            );

            $successCount = count(array_filter($results, fn ($r) => $r['success'] ?? false));
            $totalCount = count($results);

            return rest_ensure_response([
                'success' => true,
                'published' => $successCount,
                'total' => $totalCount,
                'results' => $results,
                'message' => sprintf(
                    'Pubblicato con successo su %d di %d canali',
                    $successCount,
                    $totalCount
                ),
            ]);
        } catch (\Throwable $e) {
            return new WP_Error(
                'publish_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public static function previewMultiChannel(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $body = $request->get_json_params();

        if (! is_array($body)) {
            return new WP_Error('invalid_request', 'Invalid request body', ['status' => 400]);
        }

        // Add preview flag to payload
        $basePayload = is_array($body['payload'] ?? null) ? $body['payload'] : [];
        $basePayload['preview'] = true;

        // Return preview data (no actual publishing)
        return rest_ensure_response([
            'success' => true,
            'preview' => true,
            'payload' => $basePayload,
        ]);
    }

    public static function checkPublishPermission(): bool
    {
        return current_user_can('fp_publisher_manage_plans');
    }
}
