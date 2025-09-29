<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Domain;

use FP\Publisher\Domain\PostPlan;
use PHPUnit\Framework\TestCase;

final class PostPlanTest extends TestCase
{
    public function testItValidatesRequiredFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PostPlan::create([
            'brand' => 'test-brand',
            'channels' => [],
            'slots' => [],
            'assets' => [],
            'template' => [
                'id' => 1,
                'name' => 'Stub',
                'body' => 'Body',
            ],
        ]);
    }

    public function testItExposesIgFirstComment(): void
    {
        $plan = PostPlan::create([
            'id' => 10,
            'brand' => 'brand-a',
            'channels' => ['meta_instagram'],
            'slots' => [
                ['channel' => 'meta_instagram', 'scheduled_at' => '2024-01-01T10:00:00+00:00'],
            ],
            'assets' => [
                [
                    'id' => 1,
                    'source' => 'uploads',
                    'reference' => 'asset-1',
                    'mime_type' => 'image/jpeg',
                    'bytes' => 1024,
                ],
            ],
            'template' => [
                'id' => 1,
                'name' => 'Stub',
                'body' => 'Body',
            ],
            'ig_first_comment' => '  Primo commento  ',
        ]);

        $this->assertSame('Primo commento', $plan->igFirstComment());
        $this->assertSame('Primo commento', $plan->toArray()['ig_first_comment']);
    }
}
