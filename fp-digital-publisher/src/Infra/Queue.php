<?php

declare(strict_types=1);

namespace FP\Publisher\Infra;

use DateInterval;
use DateTimeImmutable;
use Exception;
use FP\Publisher\Infra\DeadLetterQueue;
use FP\Publisher\Support\Channels;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Strings;
use FP\Publisher\Support\Logging\Logger;
use RuntimeException;
use wpdb;

use const JSON_THROW_ON_ERROR;

use function abs;
use function array_map;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function max;
use function preg_replace;
use function __;
use function random_int;
use function str_contains;
use function sanitize_key;
use function sanitize_text_field;
use function trim;
use function wp_json_encode;
use function wp_strip_all_tags;

final class Queue
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * @param array<string, mixed> $payload
     */
    public static function enqueue(
        string $channel,
        array $payload,
        DateTimeImmutable $runAt,
        string $idempotencyKey,
        ?int $childJobId = null
    ): array {
        global $wpdb;

        $channel = self::normalizeChannel($channel);
        $idempotencyKey = self::normalizeIdempotencyKey($idempotencyKey);

        if ($channel === '' || $idempotencyKey === '') {
            throw new RuntimeException('Invalid channel or idempotency key provided.');
        }

        $existing = self::findByIdempotency($idempotencyKey, $channel);
        if ($existing !== null) {
            Logger::get()->debug('Queue returning existing job for idempotency key.', [
                'job_id' => (int) ($existing['id'] ?? 0),
                'channel' => $existing['channel'] ?? $channel,
                'idempotency_key' => $idempotencyKey,
            ]);
            return $existing;
        }

        $payloadJson = self::encodePayload($payload);
        $now = Dates::now('UTC');
        $data = [
            'status' => self::STATUS_PENDING,
            'channel' => $channel,
            'payload_json' => $payloadJson,
            'run_at' => self::formatDate($runAt),
            'attempts' => 0,
            'error' => null,
            'idempotency_key' => $idempotencyKey,
            'remote_id' => '',
            'created_at' => self::formatDate($now),
            'updated_at' => self::formatDate($now),
            'child_job_id' => $childJobId,
        ];

        $inserted = $wpdb->insert(self::table($wpdb), $data);
        if ($inserted === false) {
            $errorMessage = wp_strip_all_tags((string) $wpdb->last_error);

            $existingJob = null;
            if (self::isDuplicateKeyError($errorMessage)) {
                $existingJob = self::findByIdempotency($idempotencyKey, $channel);
            }

            if ($existingJob !== null) {
                Logger::get()->debug('Queue returning existing job after duplicate insert attempt.', [
                    'job_id' => (int) ($existingJob['id'] ?? 0),
                    'channel' => $channel,
                    'idempotency_key' => $idempotencyKey,
                ]);

                return $existingJob;
            }

            Logger::get()->error('Unable to enqueue job.', [
                'channel' => $channel,
                'idempotency_key' => $idempotencyKey,
                'error' => $errorMessage !== '' ? $errorMessage : 'Unknown database error.',
            ]);
            if ($errorMessage !== '') {
                throw new RuntimeException($errorMessage);
            }

            throw new RuntimeException('Unable to enqueue job.');
        }

        $job = self::findById((int) $wpdb->insert_id);

        if ($job !== null) {
            Logger::get()->info('Job enqueued.', [
                'job_id' => (int) $job['id'],
                'channel' => $job['channel'],
                'idempotency_key' => $idempotencyKey,
                'run_at' => $job['run_at'],
            ]);
            return $job;
        }

        Logger::get()->info('Job enqueued without hydration.', [
            'job_id' => (int) $wpdb->insert_id,
            'channel' => $channel,
            'idempotency_key' => $idempotencyKey,
            'run_at' => $runAt,
        ]);

        return [
            'id' => (int) $wpdb->insert_id,
            'status' => self::STATUS_PENDING,
            'channel' => $channel,
            'payload' => $payload,
            'run_at' => $runAt,
            'attempts' => 0,
            'error' => null,
            'idempotency_key' => $idempotencyKey,
            'remote_id' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'child_job_id' => $childJobId,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function dueJobs(DateTimeImmutable $now, int $limit): array
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM " . self::table($wpdb) . " WHERE status = %s AND run_at <= %s ORDER BY run_at ASC, id ASC LIMIT %d",
            self::STATUS_PENDING,
            self::formatDate($now),
            max(1, $limit)
        );

        /** @var array<int, array<string, mixed>>|null $results */
        $results = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($results)) {
            Logger::get()->warning('Unable to retrieve due queue jobs.', [
                'error' => wp_strip_all_tags((string) $wpdb->last_error),
            ]);

            return [];
        }

        return array_map(self::hydrate(...), $results);
    }

    public static function claim(array $job, DateTimeImmutable $now): ?array
    {
        global $wpdb;

        $attempts = (int) ($job['attempts'] ?? 0) + 1;
        $updated = $wpdb->update(
            self::table($wpdb),
            [
                'status' => self::STATUS_RUNNING,
                'attempts' => $attempts,
                'updated_at' => self::formatDate($now),
            ],
            [
                'id' => (int) $job['id'],
                'status' => self::STATUS_PENDING,
            ],
            ['%s', '%d', '%s'],
            ['%d', '%s']
        );

        if ($updated === false || $updated === 0) {
            Logger::get()->warning('Unable to claim queue job.', [
                'job_id' => (int) ($job['id'] ?? 0),
                'status' => $job['status'] ?? null,
                'channel' => $job['channel'] ?? null,
                'error' => wp_strip_all_tags((string) $wpdb->last_error),
            ]);

            return null;
        }

        $job['attempts'] = $attempts;
        $job['status'] = self::STATUS_RUNNING;
        $job['updated_at'] = $now;

        return $job;
    }

    public static function markCompleted(int $jobId, ?string $remoteId = null): void
    {
        global $wpdb;

        $updated = $wpdb->update(
            self::table($wpdb),
            [
                'status' => self::STATUS_COMPLETED,
                'remote_id' => $remoteId !== null ? sanitize_text_field($remoteId) : '',
                'error' => null,
                'updated_at' => self::formatDate(Dates::now('UTC')),
            ],
            ['id' => $jobId],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($updated === false || $updated <= 0) {
            self::handleUpdateFailure(
                $wpdb,
                'Unable to mark job as completed.',
                [
                    'job_id' => $jobId,
                    'remote_id' => $remoteId,
                ],
                'Unable to mark job as completed.'
            );
        }

        Logger::get()->info('Job completed.', [
            'job_id' => $jobId,
            'remote_id' => $remoteId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function replay(int $jobId): array
    {
        $job = self::findById($jobId);
        if ($job === null) {
            throw new RuntimeException(__('Job not found.', 'fp-publisher'));
        }

        if (($job['status'] ?? '') !== self::STATUS_FAILED) {
            throw new RuntimeException(__('Only failed jobs can be replayed.', 'fp-publisher'));
        }

        global $wpdb;

        $now = Dates::now('UTC');
        $updated = $wpdb->update(
            self::table($wpdb),
            [
                'status' => self::STATUS_PENDING,
                'run_at' => self::formatDate($now),
                'updated_at' => self::formatDate($now),
                'error' => null,
                'attempts' => 0,
            ],
            ['id' => $jobId],
            ['%s', '%s', '%s', '%s', '%d'],
            ['%d']
        );

        if ($updated === false || $updated <= 0) {
            self::handleUpdateFailure(
                $wpdb,
                'Unable to reschedule the job.',
                [
                    'job_id' => $jobId,
                    'channel' => $job['channel'] ?? null,
                ],
                'Unable to reschedule the job.'
            );
        }

        $reloaded = self::findById($jobId);
        if ($reloaded === null) {
            throw new RuntimeException('Job unavailable after rescheduling.');
        }

        Logger::get()->info('Job replayed and scheduled immediately.', [
            'job_id' => $jobId,
            'channel' => $reloaded['channel'] ?? null,
            'run_at' => $reloaded['run_at'] ?? null,
        ]);

        return $reloaded;
    }

    /**
     * @param array<string, mixed> $job
     */
    public static function markFailed(array $job, string $error, bool $retryable = false): void
    {
        global $wpdb;

        $jobId = (int) ($job['id'] ?? 0);
        if ($jobId <= 0) {
            return;
        }

        $attempts = (int) ($job['attempts'] ?? 1);
        $maxAttempts = (int) Options::get('queue.max_attempts', 5);
        $now = Dates::now('UTC');
        $sanitizedError = Strings::trimWidth(wp_strip_all_tags($error), 5000, '');

        $channel = isset($job['channel']) ? self::normalizeChannel((string) $job['channel']) : '';
        $context = [
            'job_id' => $jobId,
            'channel' => $channel !== '' ? $channel : null,
            'attempts' => $attempts,
            'max_attempts' => $maxAttempts,
            'retryable' => $retryable,
            'error' => $sanitizedError,
        ];

        if ($retryable && $attempts < $maxAttempts) {
            $delay = self::calculateBackoff($attempts, self::backoffConfig($channel));
            $nextRun = $now->add(new DateInterval('PT' . $delay . 'S'));

            $updated = $wpdb->update(
                self::table($wpdb),
                [
                    'status' => self::STATUS_PENDING,
                    'error' => $sanitizedError,
                    'run_at' => self::formatDate($nextRun),
                    'updated_at' => self::formatDate($now),
                ],
                ['id' => $jobId],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );

            if ($updated === false || $updated <= 0) {
                self::handleUpdateFailure(
                    $wpdb,
                    'Unable to mark job as retrying after failure.',
                    $context + [
                        'next_run' => $nextRun,
                        'delay_seconds' => $delay,
                    ],
                    'Unable to schedule job retry.'
                );
            }

            Logger::get()->warning('Job failed and scheduled for retry.', $context + [
                'next_run' => $nextRun,
                'delay_seconds' => $delay,
            ]);

            return;
        }

        $updated = $wpdb->update(
            self::table($wpdb),
            [
                'status' => self::STATUS_FAILED,
                'error' => $sanitizedError,
                'updated_at' => self::formatDate($now),
            ],
            ['id' => $jobId],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($updated === false || $updated <= 0) {
            self::handleUpdateFailure(
                $wpdb,
                'Unable to mark job as failed.',
                $context,
                'Unable to mark job as failed.'
            );
        }

        Logger::get()->error('Job failed permanently.', $context);

        // Move to Dead Letter Queue
        DeadLetterQueue::moveJob($job, $sanitizedError, $attempts);
    }

    /**
     * @return array<string, int>
     */
    public static function runningChannels(): array
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT channel, COUNT(*) as total FROM " . self::table($wpdb) . " WHERE status = %s GROUP BY channel",
            self::STATUS_RUNNING
        );

        /** @var array<int, array{channel: string, total: string|int}>|null $rows */
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            Logger::get()->warning('Unable to retrieve running queue channels.', [
                'error' => wp_strip_all_tags((string) $wpdb->last_error),
            ]);

            return [];
        }
        $channels = [];

        foreach ($rows as $row) {
            $channel = self::normalizeChannel((string) $row['channel']);
            if ($channel === '') {
                continue;
            }

            $channels[$channel] = (int) $row['total'];
        }

        return $channels;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public static function paginate(int $page, int $perPage, array $filters = []): array
    {
        global $wpdb;

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = ['1=1'];
        $params = [];

        if (isset($filters['status'])) {
            $status = sanitize_key((string) $filters['status']);
            if ($status !== '') {
                $conditions[] = 'status = %s';
                $params[] = $status;
            }
        }

        if (isset($filters['channel'])) {
            $channel = self::normalizeChannel((string) $filters['channel']);
            if ($channel !== '') {
                $conditions[] = 'channel = %s';
                $params[] = $channel;
            }
        }

        if (isset($filters['search'])) {
            $search = sanitize_text_field((string) $filters['search']);
            if ($search !== '') {
                $conditions[] = '(idempotency_key LIKE %s OR error LIKE %s)';
                $like = '%' . $wpdb->esc_like($search) . '%';
                $params[] = $like;
                $params[] = $like;
            }
        }

        $where = implode(' AND ', $conditions);
        $querySql = 'SELECT * FROM ' . self::table($wpdb) . ' WHERE ' . $where
            . ' ORDER BY run_at DESC, id DESC LIMIT %d OFFSET %d';
        $queryParams = array_merge($params, [$perPage, $offset]);

        $prepared = $wpdb->prepare($querySql, $queryParams);

        /** @var array<int, array<string, mixed>>|null $rows */
        $rows = $wpdb->get_results($prepared, ARRAY_A);
        if (! is_array($rows)) {
            Logger::get()->warning('Unable to paginate queue jobs.', [
                'filters' => $filters,
                'error' => wp_strip_all_tags((string) $wpdb->last_error),
            ]);

            $rows = [];
        }

        $countSql = 'SELECT COUNT(*) FROM ' . self::table($wpdb) . ' WHERE ' . $where;
        $countQuery = $params !== [] ? $wpdb->prepare($countSql, $params) : $countSql;
        $totalRaw = $wpdb->get_var($countQuery);
        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;

        return [
            'items' => array_map(self::hydrate(...), $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM " . self::table($wpdb) . " WHERE id = %d LIMIT 1",
            $id
        );

        /** @var array<string, mixed>|null $row */
        $row = $wpdb->get_row($sql, ARRAY_A);

        return $row !== null ? self::hydrate($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByIdempotency(string $idempotencyKey, ?string $channel = null): ?array
    {
        global $wpdb;

        $normalizedKey = self::normalizeIdempotencyKey($idempotencyKey);
        if ($normalizedKey === '') {
            return null;
        }

        if ($channel === null) {
            return null;
        }

        $sanitizedChannel = self::normalizeChannel($channel);

        if ($sanitizedChannel === '') {
            return null;
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM " . self::table($wpdb) . " WHERE idempotency_key = %s AND channel = %s LIMIT 1",
            $normalizedKey,
            $sanitizedChannel
        );

        /** @var array<string, mixed>|null $row */
        $row = $wpdb->get_row($sql, ARRAY_A);

        return $row !== null ? self::hydrate($row) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function hydrate(array $row): array
    {
        $payload = [];
        if (isset($row['payload_json']) && is_string($row['payload_json']) && $row['payload_json'] !== '') {
            try {
                $decoded = json_decode($row['payload_json'], true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            } catch (Exception) {
                $payload = [];
            }
        }

        $jobId = (int) ($row['id'] ?? 0);

        return [
            'id' => (int) $row['id'],
            'status' => (string) $row['status'],
            'channel' => self::normalizeChannel((string) $row['channel']),
            'payload' => $payload,
            'run_at' => self::parseDateField((string) ($row['run_at'] ?? ''), 'run_at', $jobId),
            'attempts' => (int) $row['attempts'],
            'error' => isset($row['error']) ? (string) $row['error'] : null,
            'idempotency_key' => (string) $row['idempotency_key'],
            'remote_id' => (string) $row['remote_id'],
            'created_at' => self::parseDateField((string) ($row['created_at'] ?? ''), 'created_at', $jobId),
            'updated_at' => self::parseDateField((string) ($row['updated_at'] ?? ''), 'updated_at', $jobId),
            'child_job_id' => isset($row['child_job_id']) && is_numeric($row['child_job_id'])
                ? (int) $row['child_job_id']
                : null,
        ];
    }

    private static function parseDateField(string $value, string $field, int $jobId): DateTimeImmutable
    {
        try {
            return Dates::fromString($value, 'UTC');
        } catch (Exception $exception) {
            Logger::get()->warning('Unable to parse queue timestamp, using current time instead.', [
                'job_id' => $jobId,
                'field' => $field,
                'value' => $value,
                'error' => $exception->getMessage(),
            ]);

            return Dates::now('UTC');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function encodePayload(array $payload): string
    {
        $encoded = wp_json_encode($payload);

        if (! is_string($encoded)) {
            throw new RuntimeException('Unable to encode job payload.');
        }

        return $encoded;
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function handleUpdateFailure(wpdb $wpdb, string $logMessage, array $context, string $fallbackExceptionMessage): void
    {
        $errorMessage = wp_strip_all_tags((string) $wpdb->last_error);
        $context['error'] = $errorMessage !== '' ? $errorMessage : 'Unknown database error.';

        Logger::get()->error($logMessage, $context);

        throw new RuntimeException($errorMessage !== '' ? $errorMessage : $fallbackExceptionMessage);
    }

    private static function normalizeChannel(string $channel): string
    {
        return Channels::normalize($channel);
    }

    private static function normalizeIdempotencyKey(string $key): string
    {
        $trimmed = trim($key);
        $clean = preg_replace('/[^\x20-\x7E]/', '', $trimmed);
        $clean = is_string($clean) ? $clean : '';

        return Strings::trimWidth($clean, 191, '');
    }

    private static function isDuplicateKeyError(string $message): bool
    {
        if ($message === '') {
            return false;
        }

        $normalized = strtolower($message);

        return str_contains($normalized, 'duplicate entry')
            || str_contains($normalized, 'duplicate key value')
            || str_contains($normalized, 'unique constraint');
    }

    private static function table(wpdb $wpdb): string
    {
        return $wpdb->prefix . 'fp_pub_jobs';
    }

    private static function formatDate(DateTimeImmutable $date): string
    {
        return Dates::ensure($date, 'UTC')->format('Y-m-d H:i:s');
    }

    private static function calculateBackoff(int $attempts, array $config): int
    {
        $base = (int) ($config['base'] ?? 60);
        $factor = (float) ($config['factor'] ?? 2.0);
        $maxDelay = (int) ($config['max'] ?? 3600);

        $power = max(0, $attempts - 1);
        $delay = (int) round($base * ($factor ** $power));
        $delay = max($base, $delay);
        $delay = min($delay, $maxDelay);

        try {
            $jitter = random_int(0, (int) max(1, $base / 2));
        } catch (Exception) {
            $jitter = (int) max(1, $base / 2);
        }

        return min($maxDelay, $delay + abs($jitter));
    }

    private static function backoffConfig(?string $channel): array
    {
        $normalizedChannel = $channel !== null ? self::normalizeChannel($channel) : '';

        if ($normalizedChannel !== '') {
            $configured = Options::get('integrations.queue.channels.' . $normalizedChannel . '.retry_backoff');
            if (is_array($configured)) {
                return $configured;
            }
        }

        $default = Options::get('integrations.queue.default_retry_backoff');
        if (is_array($default)) {
            return $default;
        }

        $fallback = Options::get('queue.retry_backoff', []);

        return is_array($fallback) ? $fallback : [];
    }
}

