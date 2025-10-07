<?php

declare(strict_types=1);

namespace FP\Publisher\Support\Contracts;

use DateTimeImmutable;

interface SchedulerInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRunnableJobs(DateTimeImmutable $now, ?int $limit = null): array;

    /**
     * @return array{runnable: bool, in_blackout: bool, has_collision: bool}
     */
    public function evaluate(string $channel, DateTimeImmutable $runAt): array;
}


