<?php

declare(strict_types=1);

namespace FP\Publisher\Infra;

use DateInterval;
use DateTimeImmutable;
use Exception;
use FP\Publisher\Support\Dates;
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
use function random_int;
use function sanitize_key;
use function sanitize_text_field;
use function substr;
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

        $channel = sanitize_key($channel);
        $idempotencyKey = sanitize_text_field($idempotencyKey);

        if ($channel === '' || $idempotencyKey === '') {
            throw new RuntimeException('Invalid channel or idempotency key provided.');
        }

        $existing = self::findByIdempotency($channel, $idempotencyKey);
        if ($existing !== null) {
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
            if ($errorMessage !== '') {
                throw new RuntimeException($errorMessage);
            }

            throw new RuntimeException('Unable to enqueue job.');
        }

        $job = self::findById((int) $wpdb->insert_id);

        if ($job !== null) {
            return $job;
        }

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

        /** @var array<int, array<string, mixed>> $results */
        $results = $wpdb->get_results($sql, ARRAY_A);

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

        $wpdb->update(
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
    }

    /**
     * @return array<string, mixed>
     */
    public static function replay(int $jobId): array
    {
        $job = self::findById($jobId);
        if ($job === null) {
            throw new RuntimeException('Job non trovato.');
        }

        if (($job['status'] ?? '') !== self::STATUS_FAILED) {
            throw new RuntimeException('Solo i job falliti possono essere ripianificati.');
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

        if ($updated === false) {
            throw new RuntimeException('Impossibile ripianificare il job.');
        }

        $reloaded = self::findById($jobId);
        if ($reloaded === null) {
            throw new RuntimeException('Job non disponibile dopo la ripianificazione.');
        }

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
        $sanitizedError = substr(wp_strip_all_tags($error), 0, 5000);

        if ($retryable && $attempts < $maxAttempts) {
            $delay = self::calculateBackoff($attempts);
            $nextRun = $now->add(new DateInterval('PT' . $delay . 'S'));

            $wpdb->update(
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

            return;
        }

        $wpdb->update(
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

        /** @var array<int, array{channel: string, total: string|int}> $rows */
        $rows = $wpdb->get_results($sql, ARRAY_A);
        $channels = [];

        foreach ($rows as $row) {
            $channel = sanitize_key((string) $row['channel']);
            $channels[$channel] = (int) $row['total'];
        }

        return $channels;
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
    public static function findByIdempotency(string $channel, string $idempotencyKey): ?array
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM " . self::table($wpdb) . " WHERE channel = %s AND idempotency_key = %s LIMIT 1",
            sanitize_key($channel),
            sanitize_text_field($idempotencyKey)
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

        return [
            'id' => (int) $row['id'],
            'status' => (string) $row['status'],
            'channel' => sanitize_key((string) $row['channel']),
            'payload' => $payload,
            'run_at' => Dates::fromString((string) $row['run_at'], 'UTC'),
            'attempts' => (int) $row['attempts'],
            'error' => isset($row['error']) ? (string) $row['error'] : null,
            'idempotency_key' => (string) $row['idempotency_key'],
            'remote_id' => (string) $row['remote_id'],
            'created_at' => Dates::fromString((string) $row['created_at'], 'UTC'),
            'updated_at' => Dates::fromString((string) $row['updated_at'], 'UTC'),
            'child_job_id' => isset($row['child_job_id']) && is_numeric($row['child_job_id'])
                ? (int) $row['child_job_id']
                : null,
        ];
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

    private static function table(wpdb $wpdb): string
    {
        return $wpdb->prefix . 'fp_pub_jobs';
    }

    private static function formatDate(DateTimeImmutable $date): string
    {
        return Dates::ensure($date, 'UTC')->format('Y-m-d H:i:s');
    }

    private static function calculateBackoff(int $attempts): int
    {
        $config = Options::get('queue.retry_backoff', []);
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
}

