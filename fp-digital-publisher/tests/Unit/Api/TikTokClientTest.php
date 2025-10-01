<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Api;

use FP\Publisher\Api\TikTok\Client;
use FP\Publisher\Support\Strings;
use PHPUnit\Framework\TestCase;

final class TikTokClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Strings::forceMbstringAvailabilityForTesting(null);

        parent::tearDown();
    }

    public function testSanitizeCaptionFallsBackWithoutMbstring(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        $method = new \ReflectionMethod(Client::class, 'sanitizeCaption');
        $method->setAccessible(true);

        $caption = str_repeat('a', 2300);
        $result = $method->invoke(null, $caption);

        $this->assertSame(2200, strlen($result));
        $this->assertStringEndsWith('a', $result);
    }
}
