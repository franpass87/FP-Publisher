<?php

declare(strict_types=1);

namespace FP\Publisher\Services\TikTok;

use FP\Publisher\Api\TikTok\Client;
use FP\Publisher\Api\TikTok\TikTokException;
use FP\Publisher\Infra\Queue;
use Throwable;

use function add_action;
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

        try {
            $result = Client::publishVideo($payload);
            $remoteId = isset($result['id']) && is_string($result['id']) ? $result['id'] : '';

            Queue::markCompleted($jobId, $remoteId !== '' ? $remoteId : null);
        } catch (TikTokException $exception) {
            Queue::markFailed($job, $exception->getMessage(), $exception->isRetryable());
        } catch (Throwable $throwable) {
            $message = wp_strip_all_tags($throwable->getMessage());
            Queue::markFailed($job, $message !== '' ? $message : 'TikTok connector error.', true);
        }
    }
}
