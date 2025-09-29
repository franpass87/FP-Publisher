<?php

declare(strict_types=1);

namespace FP\Publisher\Services\WordPress;

use FP\Publisher\Infra\Queue;
use Throwable;

use function add_action;
use function is_array;
use function sanitize_key;
use function wp_strip_all_tags;

final class Dispatcher
{
    public const CHANNEL = 'wordpress_blog';

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
            $result = Publisher::process($job, $payload);
            if ($result['preview'] ?? false) {
                Queue::markCompleted($jobId, null);
                return;
            }

            $remoteId = isset($result['post_id']) ? (string) $result['post_id'] : null;
            Queue::markCompleted($jobId, $remoteId !== '' ? $remoteId : null);
        } catch (Throwable $throwable) {
            $message = wp_strip_all_tags($throwable->getMessage());
            Queue::markFailed(
                $job,
                $message !== '' ? $message : 'Errore nella pubblicazione del blog WordPress.',
                false
            );
        }
    }
}
