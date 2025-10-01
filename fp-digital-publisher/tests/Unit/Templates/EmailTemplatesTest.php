<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

final class EmailTemplatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        wp_stub_reset();
    }

    public function testFailedJobsTemplateRendersPlaceholders(): void
    {
        $context = [
            'jobs' => [
                [
                    'id' => 1,
                    'channel' => 'youtube',
                    'run_at' => '2024-01-01 10:00',
                    'attempts' => 2,
                    'error' => 'Timeout',
                ],
            ],
        ];

        ob_start();
        require __DIR__ . '/../../../templates/failed-jobs.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Failed publishing jobs from the last 7 days', $output);
        $this->assertStringContainsString('youtube', $output);
        $this->assertStringContainsString('Timeout', $output);
    }

    public function testTokenExpiringTemplateRendersPlaceholders(): void
    {
        $context = [
            'tokens' => [
                [
                    'service' => 'meta',
                    'account_id' => '123',
                    'expires_at' => '2024-01-01',
                    'days_left' => 5,
                ],
            ],
        ];

        ob_start();
        require __DIR__ . '/../../../templates/token-expiring.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('meta', $output);
        $this->assertStringContainsString('expires on', $output);
    }

    public function testWeeklyGapsTemplateRendersPlaceholders(): void
    {
        $context = [
            'gaps' => [
                [
                    'brand' => 'Brand',
                    'channel' => 'instagram',
                    'week_start' => '2024-01-01',
                    'week_end' => '2024-01-07',
                ],
            ],
        ];

        ob_start();
        require __DIR__ . '/../../../templates/weekly-gaps.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Missing schedules for next week', $output);
        $this->assertStringContainsString('instagram', $output);
    }
}
