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

    public function testSafeSubstrPreservesNewlinesWithoutMbstring(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        $this->assertSame("Line1\n", Strings::safeSubstr("Line1\nLine2", 6));
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

    public function testTrimWidthReturnsMarkerWhenWidthSmallerThanMarker(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        $this->assertSame('…', Strings::trimWidth('example text', 1));
        $this->assertSame('…', Strings::trimWidth('example text', 0));
        $this->assertSame('--', Strings::trimWidth('example text', 1, '--'));
    }

    public function testTrimWidthReturnsEmptyWhenMarkerEmptyAndWidthZero(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        $this->assertSame('', Strings::trimWidth('example text', 0, ''));
        $this->assertSame('', Strings::trimWidth('', 0));
    }

    public function testTrimWidthCountsWideCharactersWithoutMbstring(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        $this->assertSame('古…', Strings::trimWidth('古古', 3));
        $this->assertSame('…', Strings::trimWidth('古古', 2));
    }

    public function testLengthCountsNewlinesWithoutMbstring(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        $this->assertSame(11, Strings::length("Line1\nLine2"));
    }

    public function testTailPreservesNewlinesWithoutMbstring(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        $this->assertSame("\nLine2", Strings::tail("Line1\nLine2", 6));
    }
}
