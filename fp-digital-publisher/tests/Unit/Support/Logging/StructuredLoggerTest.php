<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Support\Logging;

use FP\Publisher\Support\Logging\StructuredLogger;
use FP\Publisher\Support\Strings;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function preg_match;
use function str_repeat;

final class StructuredLoggerTest extends TestCase
{
    protected function tearDown(): void
    {
        Strings::forceMbstringAvailabilityForTesting(null);

        parent::tearDown();
    }

    public function testTruncateIsUtf8SafeWithoutMbstring(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        $logger = new StructuredLogger();
        $method = new ReflectionMethod(StructuredLogger::class, 'truncate');
        $method->setAccessible(true);

        $result = $method->invoke($logger, str_repeat('€', 2001));

        $this->assertSame(2000, Strings::length($result));
        $this->assertSame(1, preg_match('//u', $result));
        $this->assertStringEndsWith('…', $result);
    }
}
