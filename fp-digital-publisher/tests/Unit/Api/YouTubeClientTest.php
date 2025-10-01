<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Api;

use FP\Publisher\Api\YouTube\Client;
use FP\Publisher\Support\Strings;
use PHPUnit\Framework\TestCase;

final class YouTubeClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Strings::forceMbstringAvailabilityForTesting(null);

        parent::tearDown();
    }

    public function testSanitizeTitleFallsBackWithoutMbstring(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        $method = new \ReflectionMethod(Client::class, 'sanitizeTitle');
        $method->setAccessible(true);

        $title = str_repeat('b', 150);
        $result = $method->invoke(null, $title);

        $this->assertSame(100, strlen($result));
    }

    public function testSanitizeDescriptionFallsBackWithoutMbstring(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        $method = new \ReflectionMethod(Client::class, 'sanitizeDescription');
        $method->setAccessible(true);

        $description = str_repeat('c', 5100);
        $result = $method->invoke(null, $description);

        $this->assertSame(5000, strlen($result));
    }
}
