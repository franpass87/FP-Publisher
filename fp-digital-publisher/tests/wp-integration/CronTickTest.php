<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\WpIntegration;

use FP\Publisher\Services\Worker;
use WP_UnitTestCase;

require_once __DIR__ . '/bootstrap.php';

final class CronTickTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        if (defined('FP_PUBLISHER_SKIP_WP_TESTS') && FP_PUBLISHER_SKIP_WP_TESTS) {
            $this->markTestSkipped('WordPress test suite not available.');
        }

        parent::setUp();
    }

    public function testCronEventsRegisteredOnInit(): void
    {
        wp_clear_scheduled_hook(Worker::EVENT);

        do_action('init');

        $next = wp_next_scheduled(Worker::EVENT);

        $this->assertIsInt($next);
        $this->assertGreaterThan(time(), $next);
    }

    public function testProcessActionDispatchesJobs(): void
    {
        $processed = [];
        add_action('fp_publisher_process_job', static function (array $job) use (&$processed): void {
            $processed[] = $job;
        }, 10, 1);

        update_option('fp_publisher_options', [
            'queue' => [
                'max_concurrent' => 2,
            ],
        ]);

        do_action(Worker::EVENT);

        $this->assertIsArray($processed);
    }
}
