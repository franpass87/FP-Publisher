<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Support;

use FP\Publisher\Support\Strings;
use PHPUnit\Framework\TestCase;

final class StringsTest extends TestCase
{
    protected function tearDown(): void
    {
        Strings::forceMbstringAvailabilityForTesting(null);

        parent::tearDown();
    }

    public function testSafeSubstrFallsBackWithoutMbstring(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        $this->assertSame('abc', Strings::safeSubstr('abcdef', 3));
        $this->assertSame('', Strings::safeSubstr('abcdef', 0));
    }

    public function testTrimWidthFallsBackWithoutMbstring(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        $this->assertSame('exampl…', Strings::trimWidth('example text', 7));
        $this->assertSame('short', Strings::trimWidth('short', 10));
    }

    public function testTrimWidthUsesCustomMarker(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        $this->assertSame('he--', Strings::trimWidth('hello', 4, '--'));
    }
}
