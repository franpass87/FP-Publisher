<?php

declare(strict_types=1);

namespace FP\Publisher\Infra;

use DateTimeImmutable;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Logging\Logger;
use wpdb;

use function array_map;
use function is_array;
use function is_numeric;
use function json_decode;
use function wp_json_encode;

/**
 * Dead Letter Queue for permanently failed jobs
 * Jobs that exceed max retry attempts or fail non-retryably are moved here
 */
final class DeadLetterQueue
{
    /**
     * Move a failed job to DLQ
     *
     * @param array<string, mixed> $job
     * @param string $finalError
     * @param int $totalAttempts
     */
    public static function moveJob(array $job, string $finalError, int $totalAttempts): void
    {
        global $wpdb;

        $dlqTable = $wpdb->prefix . 'fp_pub_jobs_dlq';
        $jobId = (int) ($job['id'] ?? 0);

        if ($jobId <= 0) {
            return;
        }

        // Check if DLQ table exists (graceful degradation for tests/old installations)
        if (!self::tableExists($wpdb, $dlqTable)) {
            Logger::get()->warning('Dead Letter Queue table does not exist, skipping DLQ move', [
                'job_id' => $jobId,
                'channel' => $job['channel'] ?? null
            ]);
            return;
        }

        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $createdAt = $job['created_at'] ?? null;
        $runAt = $job['run_at'] ?? null;

        $data = [
            'original_job_id' => $jobId,
            'channel' => (string) ($job['channel'] ?? ''),
            'payload_json' => wp_json_encode($payload),
            'final_error' => $finalError,
            'total_attempts' => $totalAttempts,
            'first_attempt_at' => $createdAt instanceof DateTimeImmutable 
                ? $createdAt->format('Y-m-d H:i:s') 
                : Dates::now('UTC')->format('Y-m-d H:i:s'),
            'moved_to_dlq_at' => Dates::now('UTC')->format('Y-m-d H:i:s'),
            'metadata_json' => wp_json_encode([
                'idempotency_key' => $job['idempotency_key'] ?? null,
                'remote_id' => $job['remote_id'] ?? null,
                'last_run_at' => $runAt instanceof DateTimeImmutable 
                    ? $runAt->format(DATE_ATOM) 
                    : null
            ])
        ];

        $inserted = $wpdb->insert($dlqTable, $data);

        if ($inserted !== false) {
            Logger::get()->error('Job moved to Dead Letter Queue', [
                'job_id' => $jobId,
                'channel' => $data['channel'],
                'final_error' => $finalError,
                'total_attempts' => $totalAttempts
            ]);

            // Emit action for monitoring
            do_action('fp_publisher_job_moved_to_dlq', $job, $finalError, $totalAttempts);
        }
    }

    /**
     * Get DLQ items with pagination
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public static function paginate(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        global $wpdb;

        $dlqTable = $wpdb->prefix . 'fp_pub_jobs_dlq';
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = ['1=1'];
        $params = [];

        // Filter by channel
        if (isset($filters['channel']) && $filters['channel'] !== '') {
            $conditions[] = 'channel = %s';
            $params[] = (string) $filters['channel'];
        }

        // Filter by date range
        if (isset($filters['from_date'])) {
            $conditions[] = 'moved_to_dlq_at >= %s';
            $params[] = $filters['from_date'];
        }

        if (isset($filters['to_date'])) {
            $conditions[] = 'moved_to_dlq_at <= %s';
            $params[] = $filters['to_date'];
        }

        // Search in error message
        if (isset($filters['search']) && $filters['search'] !== '') {
            $conditions[] = 'final_error LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['search']) . '%';
        }

        $where = implode(' AND ', $conditions);

        // Get items
        $query = "SELECT * FROM {$dlqTable} WHERE {$where} ORDER BY moved_to_dlq_at DESC LIMIT %d OFFSET %d";
        $queryParams = array_merge($params, [$perPage, $offset]);
        
        /** @var array<int, array<string, mixed>>|null $rows */
        $rows = $wpdb->get_results(
            $wpdb->prepare($query, ...$queryParams),
            ARRAY_A
        );

        if (!is_array($rows)) {
            $rows = [];
        }

        // Get total count
        $countQuery = "SELECT COUNT(*) FROM {$dlqTable} WHERE {$where}";
        $totalRaw = $params !== [] 
            ? $wpdb->get_var($wpdb->prepare($countQuery, ...$params))
            : $wpdb->get_var($countQuery);
        
        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;

        return [
            'items' => array_map([self::class, 'hydrate'], $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * Retry a job from DLQ (move back to main queue)
     *
     * @return array<string, mixed>|null
     */
    public static function retry(int $dlqId): ?array
    {
        global $wpdb;

        $dlqTable = $wpdb->prefix . 'fp_pub_jobs_dlq';
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$dlqTable} WHERE id = %d", $dlqId),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        $item = self::hydrate($row);
        
        // Enqueue back to main queue with new idempotency key
        $payload = $item['payload'] ?? [];
        $channel = (string) ($item['channel'] ?? '');
        $idempotencyKey = 'dlq_retry_' . $dlqId . '_' . time();

        try {
            $job = Queue::enqueue(
                $channel,
                $payload,
                Dates::now('UTC'),
                $idempotencyKey
            );

            // Delete from DLQ
            $wpdb->delete($dlqTable, ['id' => $dlqId]);

            Logger::get()->info('Job retried from Dead Letter Queue', [
                'dlq_id' => $dlqId,
                'new_job_id' => $job['id'] ?? null,
                'channel' => $channel
            ]);

            return $job;
        } catch (\Throwable $e) {
            Logger::get()->error('Failed to retry job from DLQ', [
                'dlq_id' => $dlqId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Delete old DLQ entries
     *
     * @param int $olderThanDays Delete entries older than this many days
     * @return int Number of deleted entries
     */
    public static function cleanup(int $olderThanDays = 90): int
    {
        global $wpdb;

        $dlqTable = $wpdb->prefix . 'fp_pub_jobs_dlq';
        $threshold = Dates::now('UTC')
            ->sub(new \DateInterval('P' . $olderThanDays . 'D'))
            ->format('Y-m-d H:i:s');

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$dlqTable} WHERE moved_to_dlq_at < %s",
                $threshold
            )
        );

        if ($deleted > 0) {
            Logger::get()->info('Dead Letter Queue cleanup completed', [
                'deleted_count' => $deleted,
                'older_than_days' => $olderThanDays
            ]);
        }

        return max(0, (int) $deleted);
    }

    /**
     * Get DLQ statistics
     *
     * @return array{total: int, by_channel: array<string, int>, recent_24h: int}
     */
    public static function getStats(): array
    {
        global $wpdb;

        $dlqTable = $wpdb->prefix . 'fp_pub_jobs_dlq';
        
        // Total count
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$dlqTable}");

        // By channel
        $byChannelRaw = $wpdb->get_results(
            "SELECT channel, COUNT(*) as count FROM {$dlqTable} GROUP BY channel",
            ARRAY_A
        );

        $byChannel = [];
        if (is_array($byChannelRaw)) {
            foreach ($byChannelRaw as $row) {
                $byChannel[$row['channel']] = (int) $row['count'];
            }
        }

        // Recent 24h
        $yesterday = Dates::now('UTC')
            ->sub(new \DateInterval('P1D'))
            ->format('Y-m-d H:i:s');
        
        $recent24h = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$dlqTable} WHERE moved_to_dlq_at >= %s",
                $yesterday
            )
        );

        return [
            'total' => $total,
            'by_channel' => $byChannel,
            'recent_24h' => $recent24h
        ];
    }

    /**
     * Hydrate DLQ row
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrate(array $row): array
    {
        $payload = [];
        if (isset($row['payload_json']) && is_string($row['payload_json'])) {
            $decoded = json_decode($row['payload_json'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $metadata = [];
        if (isset($row['metadata_json']) && is_string($row['metadata_json'])) {
            $decoded = json_decode($row['metadata_json'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        return [
            'id' => (int) $row['id'],
            'original_job_id' => (int) $row['original_job_id'],
            'channel' => (string) $row['channel'],
            'payload' => $payload,
            'final_error' => (string) $row['final_error'],
            'total_attempts' => (int) $row['total_attempts'],
            'first_attempt_at' => (string) $row['first_attempt_at'],
            'moved_to_dlq_at' => (string) $row['moved_to_dlq_at'],
            'metadata' => $metadata
        ];
    }

    /**
     * Check if DLQ table exists
     */
    private static function tableExists(wpdb $wpdb, string $table): bool
    {
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            )
        );

        return $result === $table;
    }
}
