<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Services;

use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Services\Preflight;
use FP\Publisher\Support\Channels;
use PHPUnit\Framework\TestCase;

use function strlen;
use function str_repeat;

final class PreflightTest extends TestCase
{
    public function testValidateClampsChannelFallbackToPlanChannel(): void
    {
        $longChannel = 'channel' . str_repeat('abc123', 20);

        $plan = PostPlan::create([
            'brand' => 'Brand',
            'channels' => [$longChannel],
            'slots' => [
                [
                    'channel' => $longChannel,
                    'scheduled_at' => '2024-01-01T00:00:00Z',
                ],
            ],
            'assets' => [],
            'template' => [
                'id' => 1,
                'name' => 'Default',
                'body' => 'Hello {{brand}}',
                'placeholders' => [],
                'channel_overrides' => [],
            ],
            'status' => PostPlan::STATUS_DRAFT,
        ]);

        $result = Preflight::validate($plan, '', []);

        $this->assertArrayHasKey('media_ratio', $result['checks']);
        $mediaRatio = $result['checks']['media_ratio'];
        $this->assertSame('warning', $mediaRatio['status']);

        $expectedChannel = Channels::normalize($longChannel);
        $this->assertSame($expectedChannel, $mediaRatio['details']['channel']);
        $this->assertLessThanOrEqual(64, strlen($expectedChannel));
    }

    public function testMetaFacebookRequiresLink(): void
    {
        $plan = PostPlan::create([
            'brand' => 'Brand',
            'channels' => ['meta_facebook'],
            'slots' => [
                [
                    'channel' => 'meta_facebook',
                    'scheduled_at' => '2024-01-01T00:00:00Z',
                ],
            ],
            'assets' => [],
            'template' => [
                'id' => 1,
                'name' => 'Default',
                'body' => 'Hello world',
                'placeholders' => [],
                'channel_overrides' => [],
            ],
            'status' => PostPlan::STATUS_DRAFT,
        ]);

        $result = Preflight::validate($plan, 'meta_facebook', [
            'utm' => [
                'source' => 'social',
                'medium' => 'paid',
                'campaign' => 'launch',
            ],
        ]);

        $this->assertArrayHasKey('link', $result['checks']);
        $linkCheck = $result['checks']['link'];
        $this->assertSame('fail', $linkCheck['status']);
    }

    public function testMetaInstagramHashtagLimitEnforced(): void
    {
        $plan = PostPlan::create([
            'brand' => 'Brand',
            'channels' => ['meta_instagram'],
            'slots' => [
                [
                    'channel' => 'meta_instagram',
                    'scheduled_at' => '2024-01-01T00:00:00Z',
                ],
            ],
            'assets' => [],
            'template' => [
                'id' => 2,
                'name' => 'Default',
                'body' => 'Hello world',
                'placeholders' => [],
                'channel_overrides' => [],
            ],
            'status' => PostPlan::STATUS_DRAFT,
        ]);

        $hashtags = [];
        for ($i = 1; $i <= 31; $i++) {
            $hashtags[] = '#tag' . $i;
        }

        $result = Preflight::validate($plan, 'meta_instagram', [
            'url' => 'https://example.com',
            'utm' => [
                'source' => 'social',
                'medium' => 'organic',
                'campaign' => 'launch',
            ],
            'hashtags_en' => $hashtags,
        ]);

        $this->assertArrayHasKey('hashtags', $result['checks']);
        $hashtagsCheck = $result['checks']['hashtags'];
        $this->assertSame('fail', $hashtagsCheck['status']);
        $this->assertSame(31, $hashtagsCheck['details']['count']);
    }
}
