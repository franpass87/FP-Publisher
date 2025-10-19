<?php

declare(strict_types=1);

namespace FP\Publisher\Api;

use DateTimeImmutable;
use DateTimeInterface;
use FP\Publisher\Api\Controllers\ClientsController;
use FP\Publisher\Api\Controllers\PublishController;
use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Infra\Capabilities;
use FP\Publisher\Infra\Options;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Services\Alerts;
use FP\Publisher\Services\Approvals;
use FP\Publisher\Services\Assets\Pipeline;
use FP\Publisher\Services\BestTime;
use FP\Publisher\Services\Comments;
use FP\Publisher\Services\Exceptions\PlanPermissionDenied;
use FP\Publisher\Services\Links;
use FP\Publisher\Services\Preflight;
use FP\Publisher\Services\Scheduler;
use FP\Publisher\Services\Trello\Ingestor as TrelloIngestor;
use FP\Publisher\Support\Channels;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\RateLimiter;
use InvalidArgumentException;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function add_action;
use function array_filter;
use function array_map;
use function array_values;
use function count;
use function esc_html__;
use function hash;
use function get_current_user_id;
use function get_user_by;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_scalar;
use function is_string;
use function json_decode;
use function register_rest_route;
use function sanitize_key;
use function sanitize_title;
use function sanitize_text_field;
use function preg_match;
use function str_contains;
use function trim;
use function usort;
use function strtolower;
use function wp_generate_uuid4;
use function wp_json_encode;
use function wp_unslash;
use function wp_verify_nonce;

final class Routes
{
    public const NAMESPACE = 'fp-publisher/v1';

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        self::registerReadRoute('status', 'fp_publisher_manage_plans', [self::class, 'getStatus']);
        self::registerCrudRoutes('plans', 'fp_publisher_manage_plans', [self::class, 'listPlans']);
        self::registerCrudRoutes('jobs', 'fp_publisher_manage_plans', null, [self::class, 'enqueueJob']);
        self::registerCrudRoutes('accounts', 'fp_publisher_manage_accounts');
        self::registerCrudRoutes('templates', 'fp_publisher_manage_templates');
        self::registerCrudRoutes('alerts', 'fp_publisher_manage_alerts', [self::class, 'getAlerts']);
        self::registerCrudRoutes('settings', 'fp_publisher_manage_settings', [self::class, 'getSettings'], [self::class, 'updateSettings']);
        self::registerCrudRoutes('logs', 'fp_publisher_view_logs');
        self::registerCrudRoutes('links', 'fp_publisher_manage_links', [self::class, 'getLinks'], [self::class, 'saveLink']);
        self::registerReadRoute('besttime', 'fp_publisher_manage_plans', [self::class, 'getBestTime']);

        ClientsController::registerRoutes();
        PublishController::registerRoutes();

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

        register_rest_route(
            self::NAMESPACE,
            '/preflight',
            [
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'preflight'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/assets/uploads',
            [
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'requestAssetUpload'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/ingest/trello',
            [
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'ingestTrello'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/ingest/trello/cards',
            [
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'previewTrello'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/jobs/bulk',
            [
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'bulkJobAction'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                    'args' => [
                        'action' => [
                            'required' => true,
                            'type' => 'string',
                            'enum' => ['replay', 'cancel', 'delete']
                        ],
                        'job_ids' => [
                            'required' => true,
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'maxItems' => 100
                        ]
                    ]
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/dlq',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'listDLQ'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/dlq/(?P<id>\\d+)/retry',
            [
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'retryFromDLQ'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/plans/(?P<id>\\d+)/approvals',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'getPlanApprovals'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/plans/(?P<id>\\d+)/status',
            [
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'transitionPlanStatus'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/plans/(?P<id>\\d+)/comments',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'getPlanComments'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'postPlanComment'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_comment_plans'),
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/links/(?P<slug>[A-Za-z0-9\-_%]+)',
            [
                [
                    'methods' => 'DELETE',
                    'callback' => [self::class, 'deleteLink'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_links'),
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/jobs/(?P<id>\\d+)/replay',
            [
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'replayJob'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, 'fp_publisher_manage_plans'),
                ],
            ]
        );
    }

    private static function registerReadRoute(string $path, string $capability, callable $callback): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/' . $path,
            [
                [
                    'methods' => 'GET',
                    'callback' => $callback,
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, $capability),
                ],
            ]
        );
    }

    private static function registerCrudRoutes(string $path, string $capability, ?callable $getCallback = null, ?callable $postCallback = null): void
    {
        $routes = [[
            'methods' => 'GET',
            'callback' => $getCallback ?? [self::class, 'emptyCollection'],
            'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, $capability),
        ]];

        if ($postCallback !== null) {
            $routes[] = [
                'methods' => 'POST',
                'callback' => $postCallback,
                'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, $capability),
            ];
        }

        register_rest_route(
            self::NAMESPACE,
            '/' . $path,
            $routes
        );
    }

    public static function getStatus(): WP_REST_Response
    {
        $now = new DateTimeImmutable('now');

        return new WP_REST_Response([
            'version' => FP_PUBLISHER_VERSION,
            'timestamp' => $now->format(DateTimeInterface::ATOM),
            'status' => 'ok',
        ]);
    }

    public static function getSettings(): WP_REST_Response
    {
        return new WP_REST_Response([
            'options' => Options::all(),
        ]);
    }

    public static function updateSettings(WP_REST_Request $request)
    {
        $payload = self::extractPayload($request);
        
        if (!isset($payload['key']) || !is_string($payload['key'])) {
            return new WP_Error(
                'fp_publisher_settings_invalid',
                esc_html__('Missing or invalid setting key.', 'fp-publisher'),
                ['status' => 400]
            );
        }
        
        $key = sanitize_text_field($payload['key']);
        $value = $payload['value'] ?? null;
        
        try {
            Options::set($key, $value);
        } catch (RuntimeException $exception) {
            return new WP_Error(
                'fp_publisher_settings_error',
                esc_html__('Unable to update settings.', 'fp-publisher'),
                ['status' => 500, 'detail' => $exception->getMessage()]
            );
        }
        
        return new WP_REST_Response([
            'success' => true,
            'key' => $key,
        ], 200);
    }

    public static function getAlerts(): WP_REST_Response
    {
        return new WP_REST_Response([
            'alerts' => Alerts::getState(),
        ]);
    }

    public static function getLinks(): WP_REST_Response
    {
        return new WP_REST_Response([
            'items' => Links::all(),
        ]);
    }

    public static function saveLink(WP_REST_Request $request)
    {
        $payload = self::extractPayload($request);

        try {
            $link = Links::createOrUpdate($payload);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error(
                'fp_publisher_link_invalid',
                esc_html__('Invalid parameters for the short link.', 'fp-publisher'),
                ['status' => 422, 'detail' => $exception->getMessage()]
            );
        } catch (RuntimeException $exception) {
            return new WP_Error(
                'fp_publisher_link_error',
                esc_html__('Unable to save the requested short link.', 'fp-publisher'),
                ['status' => 500, 'detail' => $exception->getMessage()]
            );
        }

        return new WP_REST_Response([
            'link' => $link,
        ], 201);
    }

    public static function deleteLink(WP_REST_Request $request)
    {
        $slug = sanitize_title((string) $request->get_param('slug'));
        if ($slug === '') {
            return new WP_Error(
                'fp_publisher_link_not_found',
                esc_html__('Short link not found.', 'fp-publisher'),
                ['status' => 404]
            );
        }

        if (! Links::delete($slug)) {
            return new WP_Error(
                'fp_publisher_link_not_found',
                esc_html__('Short link not found.', 'fp-publisher'),
                ['status' => 404]
            );
        }

        return new WP_REST_Response(null, 204);
    }

    public static function getBestTime(WP_REST_Request $request)
    {
        $brand = sanitize_text_field((string) $request->get_param('brand'));
        $channel = Channels::normalize((string) $request->get_param('channel'));
        $monthParam = $request->get_param('month');
        $month = is_string($monthParam) ? sanitize_text_field($monthParam) : Dates::now()->format('Y-m');

        if ($brand === '' || $channel === '') {
            return new WP_Error(
                'fp_publisher_besttime_missing_params',
                esc_html__('Brand and channel are required for recommendations.', 'fp-publisher'),
                ['status' => 400]
            );
        }

        try {
            $suggestions = BestTime::getSuggestions($brand, $channel, $month);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error(
                'fp_publisher_besttime_invalid',
                esc_html__('Unable to calculate suggestions for the requested period.', 'fp-publisher'),
                ['status' => 422, 'detail' => $exception->getMessage()]
            );
        }

        return new WP_REST_Response([
            'brand' => $brand,
            'channel' => $channel,
            'month' => $month,
            'suggestions' => $suggestions,
        ]);
    }

    public static function listPlans(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $brandFilter = $request->get_param('brand');
        $brand = is_string($brandFilter) ? trim(sanitize_text_field($brandFilter)) : '';

        $channelFilter = $request->get_param('channel');
        $channel = Channels::normalize(is_string($channelFilter) ? $channelFilter : '');

        $monthStart = null;
        $monthEnd = null;
        $monthParam = $request->get_param('month');
        if (is_string($monthParam) && preg_match('/^\d{4}-\d{2}$/', $monthParam) === 1) {
            try {
                $monthStart = Dates::ensure($monthParam . '-01T00:00:00');
                $monthEnd = Dates::add($monthStart, 'P1M');
            } catch (InvalidArgumentException $exception) {
                $monthStart = null;
                $monthEnd = null;
            }
        }

        $pageParam = $request->get_param('page');
        $page = is_numeric($pageParam) ? max(1, (int) $pageParam) : 1;

        $perPageParam = $request->get_param('per_page');
        $perPageValue = is_numeric($perPageParam) ? (int) $perPageParam : 0;
        $perPage = $perPageValue > 0 ? min(100, $perPageValue) : 20;
        $offset = ($page - 1) * $perPage;

        if (! isset($wpdb)) {
            return new WP_REST_Response([
                'items' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
            ]);
        }

        $conditions = ['1=1'];
        $params = [];

        if ($brand !== '') {
            $conditions[] = 'brand = %s';
            $params[] = $brand;
        }

        if ($channel !== '') {
            $conditions[] = 'channel_set_json LIKE %s';
            $params[] = '%"' . $wpdb->esc_like($channel) . '"%';
        }

        $monthKey = null;
        if ($monthStart !== null && $monthEnd !== null) {
            $monthKey = $monthStart->format('Y-m');
        }

        if ($monthKey !== null) {
            $conditions[] = 'slots_json LIKE %s';
            $params[] = '%"scheduled_at":"' . $wpdb->esc_like($monthKey) . '%';
        }

        $where = implode(' AND ', $conditions);
        $table = $wpdb->prefix . 'fp_pub_plans';

        $items = [];
        $chunkSize = max($perPage * $page, $perPage);
        $batchOffset = 0;

        $selectSql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";

        while (true) {
            $queryParams = array_merge($params, [$chunkSize, $batchOffset]);
            $prepared = $wpdb->prepare($selectSql, ...$queryParams);

            /** @var array<int, array<string, mixed>>|null $rows */
            $rows = $prepared !== false ? $wpdb->get_results($prepared, ARRAY_A) : null;
            if (! is_array($rows) || $rows === []) {
                break;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $plan = self::formatPlanRow($row);
                if ($plan === null) {
                    continue;
                }

                if ($brand !== '' && strtolower($plan['brand']) !== strtolower($brand)) {
                    continue;
                }

                if ($channel !== '' && ! self::planMatchesChannel($plan, $channel)) {
                    continue;
                }

                if ($monthStart !== null && $monthEnd !== null && ! self::planMatchesMonth($plan, $monthStart, $monthEnd)) {
                    continue;
                }

                $items[] = $plan;
            }

            if (count($items) >= $offset + $perPage) {
                break;
            }

            if (count($rows) < $chunkSize) {
                break;
            }

            $batchOffset += $chunkSize;
        }

        usort(
            $items,
            static function (array $left, array $right): int {
                return self::planPrimaryTimestamp($left) <=> self::planPrimaryTimestamp($right);
            }
        );

        $pagedItems = array_slice($items, $offset, $perPage);

        $countSql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        $countQuery = $params !== [] ? $wpdb->prepare($countSql, ...$params) : $countSql;
        $totalRaw = $countQuery !== false ? $wpdb->get_var($countQuery) : null;
        $minimumTotal = ($page - 1) * $perPage + count($pagedItems);
        if (is_numeric($totalRaw)) {
            $total = (int) $totalRaw;
            if ($total < $minimumTotal && count($pagedItems) > 0) {
                $total = $minimumTotal;
            }
        } else {
            $total = $minimumTotal;
        }

        return new WP_REST_Response([
            'items' => $pagedItems,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    public static function preflight(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();
        if (! is_array($payload)) {
            $payload = $request->get_params();
        }

        $planPayload = self::extractPlanPayload($request, is_array($payload) ? $payload : []);
        if ($planPayload === null) {
            return new WP_Error(
                'fp_publisher_missing_plan',
                esc_html__('Unable to find the requested plan for preflight.', 'fp-publisher'),
                ['status' => 400, 'plan_id' => $request->get_param('plan_id')]
            );
        }

        try {
            $plan = PostPlan::create($planPayload);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error(
                'fp_publisher_invalid_plan',
                esc_html__('The provided plan is not valid for preflight.', 'fp-publisher'),
                ['status' => 422, 'detail' => $exception->getMessage()]
            );
        }

        $channelParam = $request->get_param('channel');
        $channel = is_string($channelParam) ? Channels::normalize($channelParam) : '';
        if ($channel === '' && $plan->channels() !== []) {
            $channel = Channels::normalize((string) $plan->channels()[0]);
        }

        if ($channel === '') {
            return new WP_Error(
                'fp_publisher_invalid_channel',
                esc_html__('The provided channel is not valid.', 'fp-publisher'),
                ['status' => 400]
            );
        }

        $context = self::extractContext($request, is_array($payload) ? $payload : []);
        $result = Preflight::validate($plan, $channel, $context);

        return new WP_REST_Response([
            'plan' => $plan->toArray(),
            'plan_id' => $plan->id(),
            'channel' => $channel,
            'result' => $result,
        ]);
    }

    public static function enqueueJob(WP_REST_Request $request)
    {
        $channel = Channels::normalize((string) $request->get_param('channel'));

        if ($channel === '') {
            return new WP_Error(
                'fp_publisher_invalid_channel',
                esc_html__('The provided channel is not valid.', 'fp-publisher'),
                ['status' => 400]
            );
        }

        $payload = self::extractPayload($request);
        $context = self::extractContext($request, $payload);
        $planPayload = self::extractPlanPayload($request, $payload);
        $plan = null;

        if ($planPayload !== null) {
            try {
                $plan = PostPlan::create($planPayload);
            } catch (InvalidArgumentException $exception) {
                return new WP_Error(
                    'fp_publisher_invalid_plan',
                    esc_html__('The provided plan is not valid for scheduling.', 'fp-publisher'),
                    ['status' => 422, 'detail' => $exception->getMessage()]
                );
            }

            $preflight = Preflight::validate($plan, $channel, $context);
            $override = self::overridePreflight($request);

            if ($preflight['blocking'] !== [] && ! $override) {
                return new WP_Error(
                    'fp_publisher_preflight_blocked',
                    esc_html__('Preflight failed: resolve the errors before scheduling.', 'fp-publisher'),
                    ['status' => 422, 'preflight' => $preflight]
                );
            }

            $payload['plan'] = $plan->toArray();
            $payload['preflight'] = $preflight;
            if ($override) {
                $payload['preflight']['override'] = true;
            }
        }

        $runAt = self::extractRunAt($request);
        $providedId = $request->get_param('idempotency_key');
        $idempotencyKey = self::buildIdempotencyKey(
            $channel,
            $runAt,
            $plan,
            $payload,
            is_string($providedId) ? sanitize_text_field($providedId) : null
        );

        try {
            $container = \FP\Publisher\Support\ContainerRegistry::get();
            /** @var \FP\Publisher\Support\Contracts\QueueInterface $queue */
            $queue = $container->get(\FP\Publisher\Support\Contracts\QueueInterface::class);
            $job = $queue->enqueue($channel, $payload, $runAt, $idempotencyKey);
        } catch (RuntimeException $exception) {
            return new WP_Error(
                'fp_publisher_queue_error',
                esc_html__('Unable to enqueue the requested job.', 'fp-publisher'),
                ['status' => 500, 'detail' => $exception->getMessage()]
            );
        }

        return new WP_REST_Response([
            'job' => self::formatJob($job),
        ], 201);
    }

    public static function testJob(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $channel = Channels::normalize((string) $request->get_param('channel'));
        if ($channel === '') {
            return new WP_Error(
                'fp_publisher_invalid_channel',
                esc_html__('The provided channel is not valid.', 'fp-publisher'),
                ['status' => 400]
            );
        }

        $runAt = self::extractRunAt($request);
        $container = \FP\Publisher\Support\ContainerRegistry::get();
        /** @var \FP\Publisher\Support\Contracts\SchedulerInterface $scheduler */
        $scheduler = $container->get(\FP\Publisher\Support\Contracts\SchedulerInterface::class);
        $result = $scheduler->evaluate($channel, $runAt);

        return new WP_REST_Response([
            'channel' => $channel,
            'run_at' => $runAt->format(DateTimeInterface::ATOM),
            'evaluation' => $result,
        ]);
    }

    public static function replayJob(WP_REST_Request $request)
    {
        $jobId = (int) $request->get_param('id');
        if ($jobId <= 0) {
            return new WP_Error(
                'fp_publisher_invalid_job',
                esc_html__('Invalid job identifier.', 'fp-publisher'),
                ['status' => 400]
            );
        }

        try {
            $container = \FP\Publisher\Support\ContainerRegistry::get();
            /** @var \FP\Publisher\Support\Contracts\QueueInterface $queue */
            $queue = $container->get(\FP\Publisher\Support\Contracts\QueueInterface::class);
            $job = $queue->replay($jobId);
        } catch (RuntimeException $exception) {
            return new WP_Error(
                'fp_publisher_replay_failed',
                esc_html__('Unable to replay the requested job.', 'fp-publisher'),
                ['status' => 422, 'detail' => $exception->getMessage()]
            );
        }

        return new WP_REST_Response([
            'job' => self::formatJob($job),
        ]);
    }

    public static function requestAssetUpload(WP_REST_Request $request)
    {
        $payload = self::extractPayload($request);

        try {
            $result = Pipeline::prepareUpload($payload);
        } catch (RuntimeException $exception) {
            return new WP_Error(
                'fp_publisher_upload_failed',
                $exception->getMessage(),
                ['status' => 500]
            );
        }

        if ($result instanceof WP_Error) {
            return $result;
        }

        return new WP_REST_Response($result);
    }

    public static function ingestTrello(WP_REST_Request $request)
    {
        $payload = self::extractPayload($request);

        try {
            $plans = TrelloIngestor::ingest($payload);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error(
                'fp_publisher_ingest_invalid',
                esc_html__('Invalid Trello parameters for ingestion.', 'fp-publisher'),
                ['status' => 400, 'detail' => $exception->getMessage()]
            );
        } catch (RuntimeException $exception) {
            return new WP_Error(
                'fp_publisher_ingest_failed',
                esc_html__('Unable to import the selected Trello list.', 'fp-publisher'),
                ['status' => 502, 'detail' => $exception->getMessage()]
            );
        }

        return new WP_REST_Response([
            'plans' => array_map(
                static fn (PostPlan $plan): array => $plan->toArray(),
                $plans
            ),
        ]);
    }

    public static function previewTrello(WP_REST_Request $request)
    {
        $payload = self::extractPayload($request);

        try {
            $cards = TrelloIngestor::preview($payload);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error(
                'fp_publisher_ingest_invalid',
                esc_html__('Invalid Trello parameters for ingestion.', 'fp-publisher'),
                ['status' => 400, 'detail' => $exception->getMessage()]
            );
        } catch (RuntimeException $exception) {
            return new WP_Error(
                'fp_publisher_ingest_failed',
                esc_html__('Unable to import the selected Trello list.', 'fp-publisher'),
                ['status' => 502, 'detail' => $exception->getMessage()]
            );
        }

        return new WP_REST_Response([
            'cards' => $cards,
        ]);
    }

    public static function getPlanApprovals(WP_REST_Request $request)
    {
        $planId = (int) $request->get_param('id');
        if ($planId <= 0) {
            return new WP_Error(
                'fp_publisher_invalid_plan',
                esc_html__('Invalid plan identifier.', 'fp-publisher'),
                ['status' => 400]
            );
        }

        global $wpdb;

        if (! isset($wpdb)) {
            return new WP_REST_Response([
                'plan_id' => $planId,
                'status' => PostPlan::STATUS_DRAFT,
                'items' => [],
            ]);
        }

        $table = $wpdb->prefix . 'fp_pub_plans';
        $row = $wpdb->get_row($wpdb->prepare("SELECT approvals_json, status FROM {$table} WHERE id = %d", $planId), ARRAY_A);

        if (! is_array($row)) {
            return new WP_Error(
                'fp_publisher_invalid_plan',
                esc_html__('Invalid plan identifier.', 'fp-publisher'),
                ['status' => 404]
            );
        }

        $events = self::formatApprovalEvents((string) ($row['approvals_json'] ?? '[]'));

        return new WP_REST_Response([
            'plan_id' => $planId,
            'status' => self::normalizePlanStatus((string) ($row['status'] ?? PostPlan::STATUS_DRAFT)),
            'items' => $events,
        ]);
    }

    public static function transitionPlanStatus(WP_REST_Request $request)
    {
        $planId = (int) $request->get_param('id');
        if ($planId <= 0) {
            return new WP_Error(
                'fp_publisher_invalid_plan',
                esc_html__('Invalid plan identifier.', 'fp-publisher'),
                ['status' => 400]
            );
        }

        $params = $request->get_json_params();
        if (! is_array($params)) {
            $params = $request->get_params();
        }

        $status = '';
        if (isset($params['status']) && is_string($params['status'])) {
            $status = sanitize_key($params['status']);
        } elseif (isset($params['payload']['status']) && is_string($params['payload']['status'])) {
            $status = sanitize_key($params['payload']['status']);
        }

        if ($status === '') {
            return new WP_Error(
                'fp_publisher_invalid_status',
                esc_html__('The requested status is not valid.', 'fp-publisher'),
                ['status' => 400]
            );
        }

        try {
            $result = Approvals::transition($planId, $status);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error(
                'fp_publisher_transition_invalid',
                esc_html__('Transition not allowed for the selected plan.', 'fp-publisher'),
                ['status' => 422, 'detail' => $exception->getMessage()]
            );
        } catch (PlanPermissionDenied $exception) {
            return new WP_Error(
                'fp_publisher_transition_forbidden',
                esc_html__('You do not have permission to change this plan status.', 'fp-publisher'),
                ['status' => 403, 'detail' => $exception->getMessage()]
            );
        } catch (RuntimeException $exception) {
            return new WP_Error(
                'fp_publisher_transition_failed',
                esc_html__('Unable to update the plan status.', 'fp-publisher'),
                ['status' => 500, 'detail' => $exception->getMessage()]
            );
        }

        return new WP_REST_Response([
            'plan_id' => $planId,
            'status' => $result['status'],
            'approvals' => $result['approvals'],
            'updated_at' => $result['updated_at'],
        ]);
    }

    public static function getPlanComments(WP_REST_Request $request)
    {
        $planId = (int) $request->get_param('id');
        if ($planId <= 0) {
            return new WP_Error(
                'fp_publisher_invalid_plan',
                esc_html__('Invalid plan identifier.', 'fp-publisher'),
                ['status' => 400]
            );
        }

        try {
            $comments = Comments::list($planId);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error(
                'fp_publisher_comments_invalid',
                esc_html__('Unable to fetch the requested comments.', 'fp-publisher'),
                ['status' => 422, 'detail' => $exception->getMessage()]
            );
        }

        return new WP_REST_Response([
            'plan_id' => $planId,
            'items' => $comments,
        ]);
    }

    public static function postPlanComment(WP_REST_Request $request)
    {
        $planId = (int) $request->get_param('id');
        if ($planId <= 0) {
            return new WP_Error(
                'fp_publisher_invalid_plan',
                esc_html__('Invalid plan identifier.', 'fp-publisher'),
                ['status' => 400]
            );
        }

        $params = $request->get_json_params();
        if (! is_array($params)) {
            $params = $request->get_params();
        }

        $body = '';
        if (isset($params['body']) && is_string($params['body'])) {
            $body = wp_unslash($params['body']);
        } elseif (isset($params['payload']['body']) && is_string($params['payload']['body'])) {
            $body = wp_unslash($params['payload']['body']);
        }

        if ($body === '') {
            return new WP_Error(
                'fp_publisher_comment_missing_body',
                esc_html__('The comment content is required.', 'fp-publisher'),
                ['status' => 400]
            );
        }

        try {
            $comment = Comments::add($planId, get_current_user_id(), $body);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error(
                'fp_publisher_comment_invalid',
                esc_html__('Unable to save the requested comment.', 'fp-publisher'),
                ['status' => 422, 'detail' => $exception->getMessage()]
            );
        } catch (RuntimeException $exception) {
            return new WP_Error(
                'fp_publisher_comment_failed',
                esc_html__('Error while saving the comment.', 'fp-publisher'),
                ['status' => 500, 'detail' => $exception->getMessage()]
            );
        }

        return new WP_REST_Response([
            'plan_id' => $planId,
            'comment' => $comment,
        ], 201);
    }

    public static function emptyCollection(): WP_REST_Response
    {
        return new WP_REST_Response([
            'items' => [],
        ]);
    }

    private static function authorize(WP_REST_Request $request, string $capability)
    {
        // Rate limiting check (before authentication to prevent brute force)
        // Skip in unit tests when methods are not available
        if (method_exists($request, 'get_route') && method_exists($request, 'get_method')) {
            $userId = get_current_user_id();
            $route = $request->get_route();
            $method = $request->get_method();
            $rateLimitKey = sprintf('user:%d:%s:%s', $userId, $route, $method);

            // Different limits based on method
            $maxRequests = match($method) {
                'GET' => 300,    // 300 GET requests per minute
                'POST' => 60,    // 60 POST requests per minute
                'PUT', 'PATCH' => 60,  // 60 PUT/PATCH per minute
                'DELETE' => 30,  // 30 DELETE per minute
                default => 60
            };

            if (!RateLimiter::check($rateLimitKey, $maxRequests, 60)) {
                return new WP_Error(
                    'fp_publisher_rate_limit_exceeded',
                    esc_html__('Too many requests. Please wait a moment and try again.', 'fp-publisher'),
                    ['status' => 429]
                );
            }
        }

        if (self::requiresNonce($request) && ! self::verifyNonce($request)) {
            return new WP_Error(
                'fp_publisher_invalid_nonce',
                esc_html__('Invalid nonce for the REST request.', 'fp-publisher'),
                ['status' => 403]
            );
        }

        if (! Capabilities::userCan($capability)) {
            return new WP_Error(
                'fp_publisher_forbidden',
                esc_html__('You do not have permission to access this resource.', 'fp-publisher'),
                ['status' => 403]
            );
        }

        return true;
    }

    private static function verifyNonce(WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (! is_string($nonce) || $nonce === '') {
            $param = $request->get_param('_wpnonce');
            $nonce = is_string($param) ? $param : '';
        }

        if ($nonce === '') {
            return false;
        }

        $result = wp_verify_nonce($nonce, 'wp_rest');

        return $result === 1 || $result === 2;
    }

    private static function requiresNonce(WP_REST_Request $request): bool
    {
        $authHeaders = [
            $request->get_header('Authorization'),
            $request->get_header('Proxy-Authorization'),
        ];

        foreach ($authHeaders as $header) {
            if (is_string($header) && trim($header) !== '') {
                return false;
            }
        }

        return get_current_user_id() > 0;
    }

    private static function extractRunAt(WP_REST_Request $request): DateTimeImmutable
    {
        $param = $request->get_param('run_at');
        if (is_string($param) && $param !== '') {
            try {
                return Dates::ensure($param, 'UTC');
            } catch (\Throwable) {
                // Fallback to now if invalid string provided.
            }
        }

        return Dates::now('UTC');
    }

    private static function extractPayload(WP_REST_Request $request): array
    {
        $params = $request->get_json_params();
        if (! is_array($params)) {
            $params = $request->get_params();
        }

        $payload = $params['payload'] ?? [];

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function extractPlanPayload(WP_REST_Request $request, array $payload = []): ?array
    {
        if (isset($payload['plan']) && is_array($payload['plan'])) {
            return $payload['plan'];
        }

        $params = $request->get_json_params();
        if (! is_array($params)) {
            $params = $request->get_params();
        }

        if (isset($params['plan']) && is_array($params['plan'])) {
            return $params['plan'];
        }

        if (isset($params['payload']) && is_array($params['payload']) && isset($params['payload']['plan']) && is_array($params['payload']['plan'])) {
            return $params['payload']['plan'];
        }

        $planParam = $request->get_param('plan');
        if (is_array($planParam)) {
            return $planParam;
        }

        $planIdParam = $request->get_param('plan_id');
        if (is_numeric($planIdParam)) {
            return self::loadPlanFromDb((int) $planIdParam);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function extractContext(WP_REST_Request $request, array $payload = []): array
    {
        if (isset($payload['context']) && is_array($payload['context'])) {
            return $payload['context'];
        }

        $params = $request->get_json_params();
        if (! is_array($params)) {
            $params = $request->get_params();
        }

        if (isset($params['context']) && is_array($params['context'])) {
            return $params['context'];
        }

        if (isset($params['payload']) && is_array($params['payload']) && isset($params['payload']['context']) && is_array($params['payload']['context'])) {
            return $params['payload']['context'];
        }

        $contextParam = $request->get_param('context');
        if (is_array($contextParam)) {
            return $contextParam;
        }

        return [];
    }

    private static function overridePreflight(WP_REST_Request $request): bool
    {
        return self::hasOverrideFlag($request) && Capabilities::userCan('fp_publisher_manage_settings');
    }

    private static function buildIdempotencyKey(
        string $channel,
        DateTimeImmutable $runAt,
        ?PostPlan $plan,
        array $payload,
        ?string $provided
    ): string {
        $channelKey = Channels::normalize($channel);

        if ($plan instanceof PostPlan) {
            $brand = sanitize_text_field($plan->brand());
            $scheduledAt = self::scheduledAtForChannel($plan, $channelKey) ?? $runAt;
            $mediaFingerprints = [];

            foreach ($plan->assets() as $asset) {
                $fingerprint = $asset->checksum() ?? $asset->reference();
                if ($fingerprint !== '') {
                    $mediaFingerprints[] = $fingerprint;
                }
            }

            if ($mediaFingerprints === []) {
                $mediaFingerprints[] = 'media:none';
            }

            $captionSeed = self::captionSeedFromPlan($plan, $channelKey, $payload);
            if ($captionSeed === '') {
                $captionSeed = 'caption:none';
            }

            $mediaHash = hash('sha256', implode('|', $mediaFingerprints));
            $captionHash = hash('sha256', $captionSeed);
            $scheduledKey = Dates::toUtc($scheduledAt)->format(DateTimeInterface::ATOM);

            return hash('sha256', implode('|', [$brand, $channelKey, $scheduledKey, $mediaHash, $captionHash]));
        }

        if ($provided !== null && $provided !== '') {
            return sanitize_text_field($provided);
        }

        $seed = [
            $channelKey,
            $runAt->format(DateTimeInterface::ATOM),
            isset($payload['summary']) && is_scalar($payload['summary']) ? (string) $payload['summary'] : '',
            wp_generate_uuid4(),
        ];

        return hash('sha256', implode('|', $seed));
    }

    private static function scheduledAtForChannel(PostPlan $plan, string $channel): ?DateTimeImmutable
    {
        foreach ($plan->slots() as $slot) {
            if (Channels::normalize($slot->channel()) === $channel) {
                return Dates::toUtc($slot->scheduledAt());
            }
        }

        return null;
    }

    private static function captionSeedFromPlan(PostPlan $plan, string $channel, array $payload): string
    {
        if (isset($payload['caption']) && is_scalar($payload['caption'])) {
            return sanitize_text_field((string) $payload['caption']);
        }

        $template = $plan->template();
        $overrides = $template->channelOverrides();
        if (isset($overrides[$channel]) && is_array($overrides[$channel])) {
            $override = $overrides[$channel];
            if (isset($override['body']) && is_scalar($override['body'])) {
                return sanitize_text_field((string) $override['body']);
            }
        }

        $body = $template->body();
        $placeholders = [];
        if (isset($payload['plan']['template']['placeholders']) && is_array($payload['plan']['template']['placeholders'])) {
            $placeholders = $payload['plan']['template']['placeholders'];
        }

        $encoded = '';
        if ($placeholders !== []) {
            $json = wp_json_encode($placeholders);
            if (is_string($json)) {
                $encoded = $json;
            }
        }

        return $body . '|' . $encoded;
    }

    private static function hasOverrideFlag(WP_REST_Request $request): bool
    {
        $param = $request->get_param('override_preflight');

        if (is_bool($param)) {
            return $param;
        }

        if (is_string($param)) {
            return in_array(strtolower($param), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private static function formatPlanRow(array $row): ?array
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $channelsRaw = self::decodeJsonList((string) ($row['channel_set_json'] ?? '[]'));
        $slotsRaw = self::decodeJsonList((string) ($row['slots_json'] ?? '[]'));

        if ($channelsRaw === [] || $slotsRaw === []) {
            return null;
        }

        $channels = array_values(array_filter(array_map(
            static fn (mixed $channel): string => Channels::normalize(is_string($channel) ? $channel : ''),
            $channelsRaw
        ), static fn (string $channel): bool => $channel !== ''));

        $slots = self::normalizeSlots($slotsRaw, $channels);

        if ($channels === [] || $slots === []) {
            return null;
        }

        $assets = self::decodeJsonList((string) ($row['assets_json'] ?? '[]'));
        $template = self::decodeTemplate((string) ($row['template_json'] ?? '{}'));

        return [
            'id' => $id,
            'brand' => trim((string) ($row['brand'] ?? '')),
            'channels' => $channels,
            'slots' => $slots,
            'assets' => $assets,
            'template' => $template,
            'status' => self::normalizePlanStatus((string) ($row['status'] ?? PostPlan::STATUS_DRAFT)),
            'ig_first_comment' => null,
            'created_at' => self::formatDateField($row['created_at'] ?? null),
            'updated_at' => self::formatDateField($row['updated_at'] ?? null),
        ];
    }

    private static function formatDateField(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Dates::ensure($value)->format(DateTimeInterface::ATOM);
        } catch (InvalidArgumentException $exception) {
            return null;
        }
    }

    /**
     * @param array<int, mixed> $slots
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeSlots(array $slots, array $planChannels = []): array
    {
        $normalized = [];
        $fallbackChannel = '';
        foreach ($planChannels as $planChannel) {
            if ($planChannel !== '') {
                $fallbackChannel = $planChannel;
                break;
            }
        }

        foreach ($slots as $slot) {
            if (! is_array($slot)) {
                continue;
            }

            $channel = Channels::normalize(isset($slot['channel']) ? (string) $slot['channel'] : '');
            if ($channel === '' && $fallbackChannel !== '') {
                $channel = $fallbackChannel;
            }
            $scheduledAt = isset($slot['scheduled_at']) ? (string) $slot['scheduled_at'] : '';

            if ($channel === '' || $scheduledAt === '') {
                continue;
            }

            try {
                $scheduled = Dates::ensure($scheduledAt);
            } catch (InvalidArgumentException $exception) {
                continue;
            }

            $entry = [
                'channel' => $channel,
                'scheduled_at' => $scheduled->format(DateTimeInterface::ATOM),
            ];

            if (isset($slot['publish_until']) && is_string($slot['publish_until']) && $slot['publish_until'] !== '') {
                try {
                    $entry['publish_until'] = Dates::ensure($slot['publish_until'])->format(DateTimeInterface::ATOM);
                } catch (InvalidArgumentException $exception) {
                    // Ignore invalid publish_until values.
                }
            }

            if (isset($slot['duration_minutes']) && is_numeric($slot['duration_minutes'])) {
                $entry['duration_minutes'] = (int) $slot['duration_minutes'];
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    private static function normalizePlanStatus(string $status): string
    {
        $status = strtolower(trim($status));

        foreach (PostPlan::statuses() as $candidate) {
            if ($status === $candidate) {
                return $candidate;
            }
        }

        return PostPlan::STATUS_DRAFT;
    }

    /**
     * @param array<string, mixed> $plan
     */
    private static function planMatchesChannel(array $plan, string $channel): bool
    {
        $channels = isset($plan['channels']) && is_array($plan['channels']) ? $plan['channels'] : [];
        if (in_array($channel, $channels, true)) {
            return true;
        }

        $slots = isset($plan['slots']) && is_array($plan['slots']) ? $plan['slots'] : [];
        foreach ($slots as $slot) {
            if (is_array($slot) && isset($slot['channel']) && Channels::normalize((string) $slot['channel']) === $channel) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $plan
     */
    private static function planMatchesMonth(array $plan, DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        $slots = isset($plan['slots']) && is_array($plan['slots']) ? $plan['slots'] : [];
        foreach ($slots as $slot) {
            if (! is_array($slot) || empty($slot['scheduled_at'])) {
                continue;
            }

            try {
                $scheduled = Dates::ensure((string) $slot['scheduled_at'], 'UTC');
            } catch (InvalidArgumentException $exception) {
                continue;
            }

            if ($scheduled >= $start && $scheduled < $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $plan
     */
    private static function planPrimaryTimestamp(array $plan): int
    {
        $slots = isset($plan['slots']) && is_array($plan['slots']) ? $plan['slots'] : [];
        $timestamps = [];

        foreach ($slots as $slot) {
            if (! is_array($slot) || empty($slot['scheduled_at'])) {
                continue;
            }

            try {
                $scheduled = Dates::ensure((string) $slot['scheduled_at']);
            } catch (InvalidArgumentException $exception) {
                continue;
            }

            $timestamps[] = $scheduled->getTimestamp();
        }

        if ($timestamps === []) {
            return PHP_INT_MAX;
        }

        sort($timestamps);

        return $timestamps[0];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function formatApprovalEvents(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }

        $events = [];

        foreach ($decoded as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $status = self::normalizePlanStatus((string) ($entry['to'] ?? PostPlan::STATUS_DRAFT));
            $userId = isset($entry['user_id']) ? (int) $entry['user_id'] : 0;
            $user = $userId > 0 ? get_user_by('id', (string) $userId) : null;
            $displayName = $user && isset($user->display_name)
                ? $user->display_name
                : esc_html__('System', 'fp-publisher');

            $occurredAt = null;
            if (isset($entry['at']) && is_string($entry['at']) && $entry['at'] !== '') {
                try {
                    $occurredAt = Dates::ensure($entry['at'])->format(DateTimeInterface::ATOM);
                } catch (InvalidArgumentException $exception) {
                    $occurredAt = null;
                }
            }

            if ($occurredAt === null) {
                continue;
            }

            $events[] = [
                'id' => (int) $index,
                'status' => $status,
                'from' => self::normalizePlanStatus((string) ($entry['from'] ?? PostPlan::STATUS_DRAFT)),
                'note' => isset($entry['note']) && is_string($entry['note']) ? $entry['note'] : null,
                'actor' => [
                    'id' => $userId,
                    'display_name' => $displayName,
                ],
                'occurred_at' => $occurredAt,
            ];
        }

        return $events;
    }

    private static function loadPlanFromDb(int $planId): ?array
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return null;
        }

        $table = $wpdb->prefix . 'fp_pub_plans';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $planId), ARRAY_A);

        if (! is_array($row)) {
            return null;
        }

        return self::formatPlanRow($row);
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function decodeJsonList(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeTemplate(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $overrides = isset($decoded['channel_overrides']) && is_array($decoded['channel_overrides'])
            ? $decoded['channel_overrides']
            : [];

        $placeholders = isset($decoded['placeholders']) && is_array($decoded['placeholders'])
            ? $decoded['placeholders']
            : [];

        return [
            'id' => isset($decoded['id']) && is_scalar($decoded['id']) ? (int) $decoded['id'] : 1,
            'name' => isset($decoded['name']) && is_scalar($decoded['name']) ? (string) $decoded['name'] : 'Preflight Template',
            'body' => isset($decoded['body']) && is_scalar($decoded['body']) ? (string) $decoded['body'] : '',
            'placeholders' => $placeholders,
            'channel_overrides' => $overrides,
        ];
    }

    private static function formatJob(array $job): array
    {
        return [
            'id' => (int) ($job['id'] ?? 0),
            'status' => (string) ($job['status'] ?? ''),
            'channel' => (string) ($job['channel'] ?? ''),
            'run_at' => isset($job['run_at']) && $job['run_at'] instanceof DateTimeImmutable
                ? $job['run_at']->format(DateTimeInterface::ATOM)
                : null,
            'attempts' => (int) ($job['attempts'] ?? 0),
            'idempotency_key' => (string) ($job['idempotency_key'] ?? ''),
            'child_job_id' => $job['child_job_id'] ?? null,
        ];
    }

    /**
     * Bulk operations on jobs
     */
    private static function bulkJobAction(WP_REST_Request $request): WP_REST_Response
    {
        $action = sanitize_key((string) $request->get_param('action'));
        $jobIds = $request->get_param('job_ids');

        if (!is_array($jobIds)) {
            return new WP_REST_Response([
                'error' => 'job_ids must be an array'
            ], 400);
        }

        $jobIds = array_map('intval', array_slice($jobIds, 0, 100));

        $results = [
            'success' => [],
            'failed' => []
        ];

        $container = \FP\Publisher\Support\ContainerRegistry::get();
        /** @var \FP\Publisher\Support\Contracts\QueueInterface $queue */
        $queue = $container->get(\FP\Publisher\Support\Contracts\QueueInterface::class);

        foreach ($jobIds as $jobId) {
            try {
                match($action) {
                    'replay' => $queue->replay($jobId),
                    'cancel' => self::cancelJob($jobId),
                    'delete' => self::deleteJob($jobId),
                    default => throw new InvalidArgumentException('Invalid action')
                };
                
                $results['success'][] = $jobId;
            } catch (Throwable $e) {
                $results['failed'][] = [
                    'id' => $jobId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return new WP_REST_Response([
            'action' => $action,
            'processed' => count($jobIds),
            'results' => $results
        ], 200);
    }

    /**
     * Cancel a job (mark as failed without retry)
     */
    private static function cancelJob(int $jobId): void
    {
        $container = \FP\Publisher\Support\ContainerRegistry::get();
        /** @var \FP\Publisher\Support\Contracts\QueueInterface $queue */
        $queue = $container->get(\FP\Publisher\Support\Contracts\QueueInterface::class);
        $job = $queue->findById($jobId);
        
        if ($job === null) {
            throw new RuntimeException(__('Job not found.', 'fp-publisher'));
        }

        if (!in_array($job['status'], [\FP\Publisher\Infra\Queue::STATUS_PENDING, \FP\Publisher\Infra\Queue::STATUS_RUNNING], true)) {
            throw new RuntimeException(__('Only pending or running jobs can be cancelled.', 'fp-publisher'));
        }

        $queue->markFailed($job, 'Cancelled by user', false);
    }

    /**
     * Delete a job permanently
     */
    private static function deleteJob(int $jobId): void
    {
        global $wpdb;

        $container = \FP\Publisher\Support\ContainerRegistry::get();
        /** @var \FP\Publisher\Support\Contracts\QueueInterface $queue */
        $queue = $container->get(\FP\Publisher\Support\Contracts\QueueInterface::class);
        $job = $queue->findById($jobId);
        
        if ($job === null) {
            throw new RuntimeException(__('Job not found.', 'fp-publisher'));
        }

        if ($job['status'] === \FP\Publisher\Infra\Queue::STATUS_RUNNING) {
            throw new RuntimeException(__('Cannot delete running jobs.', 'fp-publisher'));
        }

        $deleted = $wpdb->delete(
            $wpdb->prefix . 'fp_pub_jobs',
            ['id' => $jobId],
            ['%d']
        );

        if ($deleted === false || $deleted === 0) {
            throw new RuntimeException(__('Unable to delete job.', 'fp-publisher'));
        }
    }

    /**
     * List Dead Letter Queue items
     */
    private static function listDLQ(WP_REST_Request $request): WP_REST_Response
    {
        $page = max(1, (int) $request->get_param('page'));
        $perPage = max(1, min(100, (int) $request->get_param('per_page')));

        $filters = [];
        
        if ($request->has_param('channel')) {
            $filters['channel'] = sanitize_key((string) $request->get_param('channel'));
        }

        if ($request->has_param('search')) {
            $filters['search'] = sanitize_text_field((string) $request->get_param('search'));
        }

        $result = \FP\Publisher\Infra\DeadLetterQueue::paginate($page, $perPage, $filters);

        return new WP_REST_Response([
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total_pages' => (int) ceil($result['total'] / $result['per_page'])
        ], 200);
    }

    /**
     * Retry job from DLQ
     */
    private static function retryFromDLQ(WP_REST_Request $request): WP_REST_Response
    {
        $dlqId = (int) $request->get_param('id');

        $job = \FP\Publisher\Infra\DeadLetterQueue::retry($dlqId);

        if ($job === null) {
            return new WP_REST_Response([
                'error' => 'Unable to retry job from DLQ'
            ], 400);
        }

        return new WP_REST_Response([
            'message' => 'Job successfully moved from DLQ back to queue',
            'job' => self::formatJob($job)
        ], 200);
    }
}
