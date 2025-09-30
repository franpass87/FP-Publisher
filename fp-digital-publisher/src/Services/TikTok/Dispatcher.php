<?php

declare(strict_types=1);

namespace FP\Publisher\Services\TikTok;

use FP\Publisher\Api\TikTok\Client;
use FP\Publisher\Api\TikTok\TikTokException;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Support\TransientErrorClassifier;
use Throwable;

use function apply_filters;
use function add_action;
use function do_action;
use function is_array;
use function is_string;
use function sanitize_key;
use function wp_strip_all_tags;

final class Dispatcher
{
    private const CHANNEL = 'tiktok';

    public static function register(): void
    {
        add_action('fp_publisher_process_job', [self::class, 'handle'], 10, 1);
    }

    /**
     * @param array<string, mixed> $job
     */
    public static function handle(array $job): void
    {
        $channel = sanitize_key((string) ($job['channel'] ?? ''));
        if ($channel !== self::CHANNEL) {
            return;
        }

        $jobId = (int) ($job['id'] ?? 0);
        if ($jobId <= 0) {
            return;
        }

        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $filteredPayload = apply_filters('fp_pub_payload_pre_send', $payload, $job);
        if (is_array($filteredPayload)) {
            $payload = $filteredPayload;
        }

        try {
            $result = Client::publishVideo($payload);
            $remoteId = isset($result['id']) && is_string($result['id']) ? $result['id'] : '';

            $remoteId = $remoteId !== '' ? $remoteId : null;

            Queue::markCompleted($jobId, $remoteId);
            do_action('fp_pub_published', $channel, $remoteId, $job);
        } catch (TikTokException $exception) {
            $retryable = (bool) apply_filters('fp_pub_retry_decision', $exception->isRetryable(), $exception, $job);
            Queue::markFailed($job, $exception->getMessage(), $retryable);
        } catch (Throwable $throwable) {
            $retryable = TransientErrorClassifier::shouldRetry($throwable);
            $retryable = (bool) apply_filters('fp_pub_retry_decision', $retryable, $throwable, $job);
            $message = wp_strip_all_tags($throwable->getMessage());
            Queue::markFailed($job, $message !== '' ? $message : 'TikTok connector error.', $retryable);
        }
    }
}
