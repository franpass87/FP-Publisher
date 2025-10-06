<?php

declare(strict_types=1);

namespace FP\Publisher\Services\Meta;

use FP\Publisher\Api\Meta\Client;
use FP\Publisher\Api\Meta\MetaException;
use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Monitoring\Metrics;
use FP\Publisher\Support\Channels;
use FP\Publisher\Support\CircuitBreaker;
use FP\Publisher\Support\CircuitBreakerOpenException;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\TransientErrorClassifier;
use Throwable;

use function apply_filters;
use function add_action;
use function do_action;
use function hash;
use function in_array;
use function is_array;
use function is_string;
use function sanitize_key;
use function trim;
use function wp_strip_all_tags;

final class Dispatcher
{
    private const CHANNEL_FACEBOOK = 'meta_facebook';
    private const CHANNEL_INSTAGRAM = 'meta_instagram';

    public static function register(): void
    {
        add_action('fp_publisher_process_job', [self::class, 'handle'], 10, 1);
    }

    /**
     * @param array<string, mixed> $job
     */
    public static function handle(array $job): void
    {
        $start = microtime(true);
        $channel = Channels::normalize((string) ($job['channel'] ?? ''));
        
        if (! in_array($channel, [self::CHANNEL_FACEBOOK, self::CHANNEL_INSTAGRAM], true)) {
            return;
        }

        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $filteredPayload = apply_filters('fp_pub_payload_pre_send', $payload, $job);
        if (is_array($filteredPayload)) {
            $payload = $filteredPayload;
        }
        $type = sanitize_key((string) ($payload['type'] ?? 'publish'));

        try {
            if ($type === 'ig_first_comment') {
                self::handleFirstComment($job, $payload);
                Metrics::incrementCounter('jobs_processed_total', 1, [
                    'channel' => $channel,
                    'type' => 'comment',
                    'status' => 'success'
                ]);
                return;
            }

            self::handlePublish($job, $payload, $channel);
            
            Metrics::incrementCounter('jobs_processed_total', 1, [
                'channel' => $channel,
                'type' => 'publish',
                'status' => 'success'
            ]);
        } catch (MetaException $exception) {
            $retryable = (bool) apply_filters('fp_pub_retry_decision', $exception->isRetryable(), $exception, $job);
            Queue::markFailed($job, $exception->getMessage(), $retryable);
            
            Metrics::incrementCounter('jobs_processed_total', 1, [
                'channel' => $channel,
                'type' => 'publish',
                'status' => 'error'
            ]);
            Metrics::incrementCounter('jobs_errors_total', 1, [
                'channel' => $channel,
                'error_type' => 'meta_exception',
                'retryable' => $retryable ? 'true' : 'false'
            ]);
        } catch (Throwable $throwable) {
            $retryable = TransientErrorClassifier::shouldRetry($throwable);
            $retryable = (bool) apply_filters('fp_pub_retry_decision', $retryable, $throwable, $job);
            $message = wp_strip_all_tags($throwable->getMessage());
            Queue::markFailed($job, $message !== '' ? $message : 'Meta connector error.', $retryable);
            
            Metrics::incrementCounter('jobs_processed_total', 1, [
                'channel' => $channel,
                'type' => 'publish',
                'status' => 'error'
            ]);
            Metrics::incrementCounter('jobs_errors_total', 1, [
                'channel' => $channel,
                'error_type' => 'throwable',
                'retryable' => $retryable ? 'true' : 'false'
            ]);
        } finally {
            $duration = (microtime(true) - $start) * 1000;
            Metrics::recordTiming('job_processing_duration_ms', $duration, [
                'channel' => $channel
            ]);
        }
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $payload
     */
    private static function handlePublish(array $job, array $payload, string $channel): void
    {
        $jobId = (int) ($job['id'] ?? 0);
        if ($jobId <= 0) {
            return;
        }

        if ($channel === self::CHANNEL_INSTAGRAM && isset($payload['access_token']) && is_string($payload['access_token'])) {
            $userId = sanitize_key((string) ($payload['user_id'] ?? ''));
            if ($userId !== '') {
                Client::storePageToken($userId, trim((string) $payload['access_token']));
            }
        }

        // Use circuit breaker to protect against cascading failures
        $circuitBreaker = new CircuitBreaker('meta_api', 5, 120, 60);
        
        try {
            $result = $circuitBreaker->call(function() use ($channel, $payload) {
                return $channel === self::CHANNEL_FACEBOOK
                    ? Client::publishFacebookPost($payload)
                    : Client::publishInstagramMedia($payload);
            });
        } catch (CircuitBreakerOpenException $e) {
            // Circuit breaker is open - schedule retry with longer delay
            Queue::markFailed($job, $e->getMessage(), true);
            return;
        }

        $remoteId = '';
        if (is_array($result)) {
            $remoteId = isset($result['id']) && is_string($result['id']) ? $result['id'] : '';
        }

        $remoteId = $remoteId !== '' ? $remoteId : null;

        Queue::markCompleted($jobId, $remoteId);
        do_action('fp_pub_published', $channel, $remoteId, $job);

        if ($channel === self::CHANNEL_INSTAGRAM && $remoteId !== '') {
            self::maybeEnqueueFirstComment($job, $payload, $remoteId);
        }
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $payload
     */
    private static function handleFirstComment(array $job, array $payload): void
    {
        $jobId = (int) ($job['id'] ?? 0);
        if ($jobId <= 0) {
            return;
        }

        $mediaId = sanitize_key((string) ($payload['media_id'] ?? ''));
        $message = isset($payload['message']) ? (string) $payload['message'] : '';
        $userId = sanitize_key((string) ($payload['user_id'] ?? ''));

        if ($mediaId === '' || $message === '') {
            Queue::markFailed($job, 'Invalid Instagram first comment payload.', false);
            return;
        }

        $accessToken = Client::resolveToken($payload, $userId);
        $hash = is_string($payload['comment_hash'] ?? null)
            ? (string) $payload['comment_hash']
            : Client::hashMessage($message);

        if (Client::commentExists($mediaId, $hash, $accessToken)) {
            Queue::markCompleted($jobId, null);
            return;
        }

        $result = Client::publishInstagramComment($mediaId, $message, $accessToken);
        $remoteId = isset($result['id']) && is_string($result['id']) ? $result['id'] : '';
        $remoteId = $remoteId !== '' ? $remoteId : null;

        Queue::markCompleted($jobId, $remoteId);
        do_action('fp_pub_published', Channels::normalize((string) ($job['channel'] ?? self::CHANNEL_INSTAGRAM)), $remoteId, $job);
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $payload
     */
    private static function maybeEnqueueFirstComment(array $job, array $payload, string $mediaId): void
    {
        $planComment = self::extractFirstComment($payload);
        if ($planComment === null || $planComment === '') {
            return;
        }

        $userId = sanitize_key((string) ($payload['user_id'] ?? ''));

        try {
            $accessToken = Client::resolveToken($payload, $userId);
        } catch (MetaException $exception) {
            do_action('fp_publisher_ig_first_comment_error', [
                'job_id' => (int) ($job['id'] ?? 0),
                'message' => wp_strip_all_tags($exception->getMessage()),
            ]);

            return;
        }

        $hash = Client::hashMessage($planComment);

        try {
            if (Client::commentExists($mediaId, $hash, $accessToken)) {
                return;
            }
        } catch (MetaException $exception) {
            do_action('fp_publisher_ig_first_comment_error', [
                'job_id' => (int) ($job['id'] ?? 0),
                'message' => wp_strip_all_tags($exception->getMessage()),
            ]);

            return;
        }

        $runAt = Dates::now('UTC');
        $idempotencyKey = hash('sha256', 'ig_first_comment|' . $mediaId . '|' . $hash);

        try {
            Queue::enqueue(
                Channels::normalize((string) ($job['channel'] ?? self::CHANNEL_INSTAGRAM)),
                [
                    'type' => 'ig_first_comment',
                    'media_id' => $mediaId,
                    'message' => $planComment,
                    'user_id' => $userId,
                    'comment_hash' => $hash,
                ],
                $runAt,
                $idempotencyKey,
                (int) ($job['id'] ?? 0)
            );
        } catch (Throwable $exception) {
            do_action('fp_publisher_ig_first_comment_error', [
                'job_id' => (int) ($job['id'] ?? 0),
                'message' => wp_strip_all_tags($exception->getMessage()),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function extractFirstComment(array $payload): ?string
    {
        if (isset($payload['ig_first_comment']) && is_string($payload['ig_first_comment'])) {
            $comment = trim($payload['ig_first_comment']);
            return $comment !== '' ? $comment : null;
        }

        $plan = is_array($payload['plan'] ?? null) ? $payload['plan'] : [];
        if ($plan === []) {
            return null;
        }

        try {
            $postPlan = PostPlan::create($plan);
            $comment = $postPlan->igFirstComment();

            return $comment !== null && $comment !== '' ? $comment : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
