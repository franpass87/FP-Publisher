<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Support;

use FP\Publisher\Support\Strings;
use FP\Publisher\Support\Templating;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class TemplatingTest extends TestCase
{
    public function testRenderForMetaInstagramAnnotatesLinks(): void
    {
        $content = 'Visit https://example.com for more.';

        $rendered = Templating::renderForChannel($content, [], 'meta_instagram');

        $this->assertSame('Visit https://example.com (example.com) for more.', $rendered);
    }

    public function testMetaFacebookLengthLimitApplied(): void
    {
        $content = str_repeat('a', 63210);

        $rendered = Templating::renderForChannel($content, [], 'meta_facebook');

        $this->assertSame(63206, Strings::length($rendered));
        $this->assertStringEndsWith('…', $rendered);
    }

    public function testMetaFacebookLengthLimitAppliedWithoutMbstring(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        try {
            $content = str_repeat('€', 63210);

            $rendered = Templating::renderForChannel($content, [], 'meta_facebook');

            $this->assertSame(63206, Strings::length($rendered));
            $this->assertStringEndsWith('…', $rendered);
            $this->assertSame(1, preg_match('//u', $rendered));
        } finally {
            Strings::forceMbstringAvailabilityForTesting(null);
        }
    }

    public function testTwitterTruncationPreservesTrailingNewlines(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        try {
            $content = str_repeat('x', 278) . "\n" . str_repeat('y', 50);

            $rendered = Templating::renderForChannel($content, [], 'twitter');

            $this->assertSame(280, Strings::length($rendered));
            $this->assertStringContainsString("\n…", $rendered);
        } finally {
            Strings::forceMbstringAvailabilityForTesting(null);
        }
    }

    public function testChannelTransformPreservesTrailingNewlinesWithoutTruncation(): void
    {
        $content = "Hello world\n";

        $rendered = Templating::renderForChannel($content, [], 'twitter');

        $this->assertSame($content, $rendered);
        $this->assertStringEndsWith("\n", $rendered);
    }

    public function testChannelTransformPreservesLeadingNewlines(): void
    {
        $content = "\nHello world";

        $rendered = Templating::renderForChannel($content, [], 'twitter');

        $this->assertSame($content, $rendered);
        $this->assertStringStartsWith("\n", $rendered);
    }

    public function testChannelTransformTrimsLeadingSpacesButKeepsNewlines(): void
    {
        $content = "  \nHello world";

        $rendered = Templating::renderForChannel($content, [], 'twitter');

        $this->assertSame("\nHello world", $rendered);
    }

    public function testChannelTransformTreatsWhitespaceOnlyAsEmpty(): void
    {
        $rendered = Templating::renderForChannel("  \n\t", [], 'twitter');

        $this->assertSame('', $rendered);
    }
}
