<?php

declare(strict_types=1);

namespace FP\Publisher\Services\WordPress;

use FP\Publisher\Infra\Queue;
use FP\Publisher\Support\Channels;
use FP\Publisher\Support\TransientErrorClassifier;
use Throwable;

use function apply_filters;
use function add_action;
use function do_action;
use function is_array;
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
        $channel = Channels::normalize((string) ($job['channel'] ?? ''));
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
            $result = Publisher::process($job, $payload);
            if ($result['preview'] ?? false) {
                Queue::markCompleted($jobId, null);
                return;
            }

            $remoteId = isset($result['post_id']) ? (string) $result['post_id'] : '';
            $remoteId = $remoteId !== '' ? $remoteId : null;

            Queue::markCompleted($jobId, $remoteId);
            do_action('fp_pub_published', $channel, $remoteId, $job);
        } catch (Throwable $throwable) {
            $retryable = TransientErrorClassifier::shouldRetry($throwable);
            $retryable = (bool) apply_filters('fp_pub_retry_decision', $retryable, $throwable, $job);
            $message = wp_strip_all_tags($throwable->getMessage());
            Queue::markFailed(
                $job,
                $message !== '' ? $message : 'Errore nella pubblicazione del blog WordPress.',
                $retryable
            );
        }
    }
}
