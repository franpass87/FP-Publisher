<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Services;

use FP\Publisher\Infra\Options;
use FP\Publisher\Services\Alerts;
use FP\Publisher\Tests\Fixtures\FakeAlertsWpdb;
use PHPUnit\Framework\TestCase;

final class AlertsTest extends TestCase
{
    private FakeAlertsWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        wp_stub_reset();

        $this->wpdb = new FakeAlertsWpdb();
        global $wpdb;
        $wpdb = $this->wpdb;

        Options::bootstrap();
        Options::set('alert_emails', ['ops@example.com']);
        Options::set('brands', ['BrandA']);
        Options::set('channels', ['instagram']);
    }

    public function testRunDailySkipsRowsWithInvalidTimestamps(): void
    {
        $this->wpdb->tokenRows = [
            [
                'service' => 'meta',
                'account_id' => 'acc-1',
                'expires_at' => 'not-a-date',
            ],
        ];

        $this->wpdb->failedJobRows = [
            [
                'id' => 10,
                'channel' => 'meta',
                'run_at' => 'invalid',
                'attempts' => 3,
                'error' => 'Fatal',
            ],
        ];

        Alerts::runDaily();

        $state = Alerts::getState();
        $this->assertSame([], $state['daily']['token_expiring']);
        $this->assertSame([], $state['daily']['failed_jobs']);
    }

    public function testRunWeeklySkipsInvalidSlots(): void
    {
        $this->wpdb->planRows = [
            [
                'brand' => 'BrandA',
                'channel_set_json' => json_encode(['instagram']),
                'slots_json' => json_encode([
                    [
                        'channel' => 'instagram',
                        'scheduled_at' => 'not-a-date',
                    ],
                ]),
            ],
        ];

        Alerts::runWeekly();

        $state = Alerts::getState();
        $this->assertNotEmpty($state['weekly']['gaps']);
    }
}
