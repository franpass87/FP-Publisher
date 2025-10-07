<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Support;

use FP\Publisher\Support\Container;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function testInstanceAndBindResolution(): void
    {
        $container = new Container();

        $container->instance('config.value', 42);
        $this->assertTrue($container->has('config.value'));
        $this->assertSame(42, $container->get('config.value'));

        $container->bind('random.value', static function (): int {
            return 7;
        });

        $this->assertTrue($container->has('random.value'));
        $this->assertSame(7, $container->get('random.value'));

        // Singleton semantics: factory is invoked once
        $this->assertSame($container->get('random.value'), $container->get('random.value'));
    }

    public function testGetUnknownServiceThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Service not found');

        $container = new Container();
        $container->get('missing');
    }
}


