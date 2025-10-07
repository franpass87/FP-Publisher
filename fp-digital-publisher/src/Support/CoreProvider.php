<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use FP\Publisher\Support\Adapters\QueueAdapter;
use FP\Publisher\Support\Adapters\SchedulerAdapter;
use FP\Publisher\Support\Contracts\QueueInterface;
use FP\Publisher\Support\Contracts\SchedulerInterface;

final class CoreProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        $container->bind(QueueInterface::class, static function (): QueueInterface {
            return new QueueAdapter();
        });

        $container->bind(SchedulerInterface::class, static function (): SchedulerInterface {
            return new SchedulerAdapter();
        });
    }

    public function boot(Container $container): void
    {
        // No boot-time actions needed for core services currently
    }
}


