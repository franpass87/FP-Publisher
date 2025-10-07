<?php

declare(strict_types=1);

namespace FP\Publisher\Support\Contracts;

use DateTimeImmutable;

/**
 * Minimal queue contract used by services and dispatchers.
 */
interface QueueInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function enqueue(string $channel, array $payload, DateTimeImmutable $runAt, string $idempotencyKey, ?int $childJobId = null): array;

    /**
     * @return array<string, int>
     */
    public function runningChannels(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function dueJobs(DateTimeImmutable $now, int $limit): array;

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>|null
     */
    public function claim(array $job, DateTimeImmutable $now): ?array;

    /**
     * @param array<string, mixed>|int $jobOrId
     */
    public function markCompleted($jobOrId, ?string $remoteId = null): void;

    /**
     * @param array<string, mixed> $job
     */
    public function markFailed(array $job, string $message, bool $retryable): void;

    /**
     * @return array<string, mixed>
     */
    public function replay(int $jobId): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $jobId): ?array;

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function paginate(int $page, int $perPage = 20, array $filters = []): array;
}


