<?php

declare(strict_types=1);

namespace FP\Publisher\Support\Adapters;

use DateTimeImmutable;
use FP\Publisher\Infra\Queue as ConcreteQueue;
use FP\Publisher\Support\Contracts\QueueInterface;

/**
 * Adapter that exposes the static Infra\Queue as a DI-friendly instance.
 */
final class QueueAdapter implements QueueInterface
{
    public function enqueue(string $channel, array $payload, DateTimeImmutable $runAt, string $idempotencyKey, ?int $childJobId = null): array
    {
        return ConcreteQueue::enqueue($channel, $payload, $runAt, $idempotencyKey, $childJobId);
    }

    public function runningChannels(): array
    {
        return ConcreteQueue::runningChannels();
    }

    public function dueJobs(DateTimeImmutable $now, int $limit): array
    {
        return ConcreteQueue::dueJobs($now, $limit);
    }

    public function claim(array $job, DateTimeImmutable $now): ?array
    {
        return ConcreteQueue::claim($job, $now);
    }

    public function markCompleted($jobOrId, ?string $remoteId = null): void
    {
        ConcreteQueue::markCompleted($jobOrId, $remoteId);
    }

    public function markFailed(array $job, string $message, bool $retryable): void
    {
        ConcreteQueue::markFailed($job, $message, $retryable);
    }

    public function replay(int $jobId): array
    {
        return ConcreteQueue::replay($jobId);
    }

    public function findById(int $jobId): ?array
    {
        return ConcreteQueue::findById($jobId);
    }

    public function paginate(int $page, int $perPage = 20, array $filters = []): array
    {
        return ConcreteQueue::paginate($page, $perPage, $filters);
    }
}


