<?php

declare(strict_types=1);

namespace FP\Publisher\Api\Controllers;

use DateTimeImmutable;
use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Services\Scheduler;
use FP\Publisher\Support\Dates;
use WP_REST_Request;
use WP_REST_Response;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function register_rest_route;
use function sanitize_key;
use function sanitize_text_field;
use function usort;
use function wp_unslash;

/**
 * Controller per la gestione dei piani di pubblicazione
 */
final class PlansController extends BaseController
{
    public static function register(): void
    {
        // GET /plans - Lista tutti i piani
        register_rest_route(
            self::NAMESPACE,
            '/plans',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'listPlans'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
            ]
        );
    }

    public static function listPlans(WP_REST_Request $request): WP_REST_Response
    {
        $brand = sanitize_key($request->get_param('brand') ?? '');
        $channel = sanitize_key($request->get_param('channel') ?? '');
        $month = sanitize_text_field($request->get_param('month') ?? '');
        $status = sanitize_text_field($request->get_param('status') ?? '');

        $start = null;
        $end = null;
        if ($month !== '') {
            [$start, $end] = self::parseMonthRange($month);
        }

        $plans = Scheduler::getPlans($brand, $channel, $start, $end);

        if ($status !== '' && $status !== 'all') {
            $plans = array_filter($plans, static fn (PostPlan $plan) => $plan->status === $status);
        }

        $items = array_map([self::class, 'serializePlan'], array_values($plans));

        usort($items, static function (array $a, array $b) {
            return ($a['_sort_timestamp'] ?? 0) <=> ($b['_sort_timestamp'] ?? 0);
        });

        foreach ($items as &$item) {
            unset($item['_sort_timestamp']);
        }

        return self::success(['items' => $items]);
    }

    private static function parseMonthRange(string $month): array
    {
        // Validate format YYYY-MM
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return [null, null];
        }

        $parts = explode('-', $month);
        if (count($parts) !== 2) {
            return [null, null];
        }

        [$year, $monthNum] = $parts;
        if ($year === '' || $monthNum === '') {
            return [null, null];
        }

        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', "{$year}-{$monthNum}-01 00:00:00");
        if (!$start) {
            return [null, null];
        }

        $end = $start->modify('+1 month');
        return [$start, $end];
    }

    private static function serializePlan(PostPlan $plan): array
    {
        $slots = array_map(static function (array $slot) {
            return [
                'channel' => $slot['channel'] ?? '',
                'scheduled_at' => isset($slot['scheduled_at']) ? Dates::toIso($slot['scheduled_at']) : null,
                'publish_until' => isset($slot['publish_until']) ? Dates::toIso($slot['publish_until']) : null,
                'duration_minutes' => $slot['duration_minutes'] ?? null,
            ];
        }, $plan->slots);

        $minTimestamp = PHP_INT_MAX;
        foreach ($slots as $slot) {
            if ($slot['scheduled_at'] !== null) {
                $timestamp = strtotime($slot['scheduled_at']);
                if ($timestamp < $minTimestamp) {
                    $minTimestamp = $timestamp;
                }
            }
        }

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'status' => $plan->status,
            'brand' => $plan->brand,
            'channels' => is_array($plan->channels) ? $plan->channels : [],
            'slots' => $slots,
            'template' => $plan->template,
            'created_at' => Dates::toIso($plan->createdAt),
            'updated_at' => Dates::toIso($plan->updatedAt),
            '_sort_timestamp' => $minTimestamp !== PHP_INT_MAX ? $minTimestamp : PHP_INT_MAX,
        ];
    }
}