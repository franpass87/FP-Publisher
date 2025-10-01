<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Infra;

use PHPUnit\Framework\TestCase;

final class MigrationsTest extends TestCase
{
    public function testJobsTableUsesCompositeIdempotencyIndex(): void
    {
        $path = __DIR__ . '/../../../src/Infra/DB/Migrations.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read migrations definition.');
        $this->assertStringContainsString(
            'UNIQUE KEY idempotency (idempotency_key, channel)',
            $contents
        );
    }
}
