<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Services\Templates;

use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Infra\Options;
use FP\Publisher\Services\Templates\Engine;
use PHPUnit\Framework\TestCase;

final class EngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        wp_stub_reset();
    }

    public function testBaseContextUsesConfiguredTimezone(): void
    {
        Options::set('timezone', 'America/New_York');

        $plan = PostPlan::create([
            'id' => 10,
            'brand' => 'Acme',
            'channels' => ['facebook'],
            'slots' => [
                [
                    'channel' => 'facebook',
                    'scheduled_at' => '2024-01-01T12:00:00+00:00',
                ],
            ],
            'assets' => [],
            'template' => [
                'id' => 1,
                'name' => 'Default',
                'body' => 'Body {date}',
                'placeholders' => [],
                'channel_overrides' => [],
            ],
            'status' => PostPlan::STATUS_READY,
        ]);

        $rendered = Engine::render($plan, 'facebook');

        $this->assertSame('01/01/2024 07:00', $rendered['context']['date']);
    }
}
