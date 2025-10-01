<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Support;

use FP\Publisher\Support\Channels;
use PHPUnit\Framework\TestCase;

use function strlen;
use function str_repeat;

final class ChannelsTest extends TestCase
{
    public function testNormalizesWhitespaceAndHyphensToUnderscores(): void
    {
        self::assertSame('meta_facebook', Channels::normalize(' Meta Facebook '));
        self::assertSame('meta_facebook', Channels::normalize('meta-facebook'));
        self::assertSame('meta_instagram_stories', Channels::normalize('Meta__Instagram   Stories'));
    }

    public function testRemovesSpecialCharactersAndCollapsesDelimiters(): void
    {
        self::assertSame('meta_facebook', Channels::normalize('Meta & Facebook'));
        self::assertSame('meta_facebook', Channels::normalize('Meta / Facebook'));
        self::assertSame('meta_facebook', Channels::normalize('Meta__Facebook!!!'));
    }

    public function testReturnsEmptyStringWhenInputSanitizesToNothing(): void
    {
        self::assertSame('', Channels::normalize('@@@'));
        self::assertSame('', Channels::normalize('   '));
    }

    public function testClampsNormalizedValueToSixtyFourCharacters(): void
    {
        $longChannel = 'channel' . str_repeat('abc123', 20);
        $normalized = Channels::normalize($longChannel);

        self::assertLessThanOrEqual(64, strlen($normalized));
        self::assertNotSame('', $normalized);
    }

    public function testClampDoesNotLeaveTrailingDelimiters(): void
    {
        $value = str_repeat('a', 63) . '-b';

        self::assertSame(str_repeat('a', 63), Channels::normalize($value));
    }
}
