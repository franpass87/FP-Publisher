<?php

declare(strict_types=1);

namespace FP\Publisher\Support\Adapters;

use DateTimeImmutable;
use FP\Publisher\Services\Scheduler as ConcreteScheduler;
use FP\Publisher\Support\Contracts\SchedulerInterface;

final class SchedulerAdapter implements SchedulerInterface
{
    public function getRunnableJobs(DateTimeImmutable $now, ?int $limit = null): array
    {
        return ConcreteScheduler::getRunnableJobs($now, $limit);
    }

    public function evaluate(string $channel, DateTimeImmutable $runAt): array
    {
        return ConcreteScheduler::evaluate($channel, $runAt);
    }
}


