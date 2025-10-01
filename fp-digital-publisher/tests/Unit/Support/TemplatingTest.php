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
}
