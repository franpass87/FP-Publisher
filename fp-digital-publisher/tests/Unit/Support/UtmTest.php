<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Support;

use FP\Publisher\Support\Utm;
use PHPUnit\Framework\TestCase;

final class UtmTest extends TestCase
{
    public function testBuildParamsMergesDefaultsAndCustom(): void
    {
        $params = Utm::buildParams(
            [
                'source' => 'Newsletter ',
                'medium' => ' Email',
                'custom' => [
                    'Extra ' => ' Value ',
                ],
            ],
            ['campaign' => 'Spring']
        );

        $this->assertSame(
            [
                'utm_source' => 'Newsletter',
                'utm_medium' => 'Email',
                'utm_campaign' => 'Spring',
                'extra' => 'Value',
            ],
            $params
        );
    }

    public function testAppendToUrlAddsParameters(): void
    {
        $url = Utm::appendToUrl('https://example.com/post', ['source' => 'social']);
        $this->assertStringContainsString('utm_source=social', $url);
        $this->assertStringContainsString('https://example.com/post?', $url);

        $this->assertSame('', Utm::appendToUrl('not-a-url', ['source' => 'x']));
    }

    public function testChannelDefaultsProvideReasonableFallbacks(): void
    {
        $defaults = Utm::channelDefaults('google_business', ['brand' => 'My Brand']);
        $this->assertSame('google_business', $defaults['source']);
        $this->assertSame('local', $defaults['medium']);
        $this->assertSame('my-brand', $defaults['campaign']);
    }
}
