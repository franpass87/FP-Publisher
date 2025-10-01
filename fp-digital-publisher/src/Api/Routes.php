<?php

declare(strict_types=1);

namespace FP\Publisher\Api;

use DateTimeImmutable;
use DateTimeInterface;
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
use FP\Publisher\Support\Dates;
use InvalidArgumentException;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function add_action;
use function array_map;
use function esc_html__;
use function hash;
use function get_current_user_id;
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
use function str_contains;
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
        self::registerCrudRoutes('plans', 'fp_publisher_manage_plans');
        self::registerCrudRoutes('jobs', 'fp_publisher_manage_plans', null, [self::class, 'enqueueJob']);
        self::registerCrudRoutes('accounts', 'fp_publisher_manage_accounts');
        self::registerCrudRoutes('templates', 'fp_publisher_manage_templates');
        self::registerCrudRoutes('alerts', 'fp_publisher_manage_alerts', [self::class, 'getAlerts']);
        self::registerCrudRoutes('settings', 'fp_publisher_manage_settings', [self::class, 'getSettings']);
        self::registerCrudRoutes('logs', 'fp_publisher_view_logs');
        self::registerCrudRoutes('links', 'fp_publisher_manage_links', [self::class, 'getLinks'], [self::class, 'saveLink']);
        self::registerReadRoute('besttime', 'fp_publisher_manage_plans', [self::class, 'getBestTime']);

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
        register_rest_route(
            self::NAMESPACE,
            '/' . $path,
            [
                [
                    'methods' => 'GET',
                    'callback' => $getCallback ?? [self::class, 'emptyCollection'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, $capability),
                ],
                [
                    'methods' => 'POST',
                    'callback' => $postCallback ?? [self::class, 'notImplemented'],
                    'permission_callback' => static fn (WP_REST_Request $request) => self::authorize($request, $capability),
                ],
            ]
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
        $channel = sanitize_key((string) $request->get_param('channel'));
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
        $channel = is_string($channelParam) ? sanitize_key($channelParam) : '';
        if ($channel === '' && $plan->channels() !== []) {
            $channel = sanitize_key((string) $plan->channels()[0]);
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
        $channel = sanitize_key((string) $request->get_param('channel'));

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
            $job = Queue::enqueue($channel, $payload, $runAt, $idempotencyKey);
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
        $channel = sanitize_key((string) $request->get_param('channel'));
        if ($channel === '') {
            return new WP_Error(
                'fp_publisher_invalid_channel',
                esc_html__('The provided channel is not valid.', 'fp-publisher'),
                ['status' => 400]
            );
        }

        $runAt = self::extractRunAt($request);
        $result = Scheduler::evaluate($channel, $runAt);

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
            $job = Queue::replay($jobId);
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

    public static function notImplemented(): WP_REST_Response
    {
        return new WP_REST_Response(
            [
                'message' => esc_html__('Endpoint not implemented yet.', 'fp-publisher'),
            ],
            202
        );
    }

    private static function authorize(WP_REST_Request $request, string $capability)
    {
        if (! self::verifyNonce($request)) {
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
        if ($nonce === '') {
            $nonce = (string) $request->get_param('_wpnonce');
        }

        if ($nonce === '') {
            return false;
        }

        $result = wp_verify_nonce($nonce, 'wp_rest');

        return $result === 1 || $result === 2;
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
        $channelKey = sanitize_key($channel);

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
            if (sanitize_key($slot->channel()) === $channel) {
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

        $channels = self::decodeJsonList((string) ($row['channel_set_json'] ?? '[]'));
        $slots = self::decodeJsonList((string) ($row['slots_json'] ?? '[]'));

        if ($channels === [] || $slots === []) {
            return null;
        }

        $assets = self::decodeJsonList((string) ($row['assets_json'] ?? '[]'));
        $template = self::decodeTemplate((string) ($row['template_json'] ?? '{}'));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'brand' => (string) ($row['brand'] ?? ''),
            'channels' => $channels,
            'slots' => $slots,
            'assets' => $assets,
            'template' => $template,
            'status' => (string) ($row['status'] ?? PostPlan::STATUS_DRAFT),
            'ig_first_comment' => null,
        ];
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
}
