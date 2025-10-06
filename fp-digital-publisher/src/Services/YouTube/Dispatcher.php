<?php

declare(strict_types=1);

namespace FP\Publisher\Services\YouTube;

use FP\Publisher\Api\YouTube\Client;
use FP\Publisher\Api\YouTube\YouTubeException;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Monitoring\Metrics;
use FP\Publisher\Support\Channels;
use FP\Publisher\Support\CircuitBreaker;
use FP\Publisher\Support\CircuitBreakerOpenException;
use FP\Publisher\Support\TransientErrorClassifier;
use Throwable;

use function apply_filters;
use function add_action;
use function do_action;
use function is_array;
use function is_string;
use function wp_strip_all_tags;

final class Dispatcher
{
    private const CHANNEL = 'youtube';

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

        $circuitBreaker = new CircuitBreaker('youtube_api', 5, 120, 60);

        try {
            $result = $circuitBreaker->call(function() use ($payload) {
                return Client::publishVideo($payload);
            });
            
            $remoteId = isset($result['id']) && is_string($result['id']) ? $result['id'] : '';
            $remoteId = $remoteId !== '' ? $remoteId : null;

            Queue::markCompleted($jobId, $remoteId);
            do_action('fp_pub_published', $channel, $remoteId, $job);
            
            Metrics::incrementCounter('jobs_processed_total', 1, [
                'channel' => $channel,
                'status' => 'success'
            ]);
        } catch (CircuitBreakerOpenException $e) {
            Queue::markFailed($job, $e->getMessage(), true);
            Metrics::incrementCounter('jobs_errors_total', 1, [
                'channel' => $channel,
                'error_type' => 'circuit_breaker_open'
            ]);
        } catch (YouTubeException $exception) {
            $retryable = (bool) apply_filters('fp_pub_retry_decision', $exception->isRetryable(), $exception, $job);
            Queue::markFailed($job, $exception->getMessage(), $retryable);
            
            Metrics::incrementCounter('jobs_errors_total', 1, [
                'channel' => $channel,
                'error_type' => 'youtube_exception',
                'retryable' => $retryable ? 'true' : 'false'
            ]);
        } catch (Throwable $throwable) {
            $retryable = TransientErrorClassifier::shouldRetry($throwable);
            $retryable = (bool) apply_filters('fp_pub_retry_decision', $retryable, $throwable, $job);
            $message = wp_strip_all_tags($throwable->getMessage());
            Queue::markFailed($job, $message !== '' ? $message : 'YouTube connector error.', $retryable);
            
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
}
