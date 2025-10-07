<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Support;

use FP\Publisher\Support\Container;
use FP\Publisher\Support\CoreProvider;
use FP\Publisher\Support\Contracts\QueueInterface;
use FP\Publisher\Support\Contracts\SchedulerInterface;
use PHPUnit\Framework\TestCase;

final class CoreProviderTest extends TestCase
{
    public function testRegistersCoreServices(): void
    {
        $container = new Container();
        $provider = new CoreProvider();
        $provider->register($container);
        $provider->boot($container);

        $this->assertTrue($container->has(QueueInterface::class));
        $this->assertTrue($container->has(SchedulerInterface::class));

        $queue = $container->get(QueueInterface::class);
        $scheduler = $container->get(SchedulerInterface::class);

        $this->assertInstanceOf(QueueInterface::class, $queue);
        $this->assertInstanceOf(SchedulerInterface::class, $scheduler);
    }
}


