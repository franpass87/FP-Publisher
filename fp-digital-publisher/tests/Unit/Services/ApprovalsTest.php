<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Services;

use FP\Publisher\Services\Approvals;
use FP\Publisher\Tests\Fixtures\FakePlansWpdb;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApprovalsTest extends TestCase
{
    private FakePlansWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        wp_stub_reset();

        $this->wpdb = new class() extends FakePlansWpdb {
            public bool $forceZero = false;

            public function update(string $table, array $data, array $where, array $format = [], array $whereFormat = []): int|false
            {
                if ($this->forceZero) {
                    return 0;
                }

                return parent::update($table, $data, $where, $format, $whereFormat);
            }
        };

        global $wpdb;
        $wpdb = $this->wpdb;

        $this->wpdb->setPlan([
            'id' => 1,
            'brand' => 'brand-a',
            'status' => 'draft',
            'approvals_json' => '[]',
        ]);

        $GLOBALS['wp_stub_current_user_caps']['fp_publisher_manage_plans'] = true;
        $GLOBALS['wp_stub_current_user_id'] = 99;
    }

    protected function tearDown(): void
    {
        wp_stub_set_json_encode_failure(false);
        unset($GLOBALS['wp_stub_current_user_caps']['fp_publisher_manage_plans']);

        parent::tearDown();
    }

    public function testTransitionFailsWhenJsonEncodingFails(): void
    {
        wp_stub_set_json_encode_failure(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to store the approval history.');

        try {
            Approvals::transition(1, 'ready');
        } finally {
            wp_stub_set_json_encode_failure(false);
        }
    }

    public function testTransitionFailsWhenNoRowsAreUpdated(): void
    {
        $this->wpdb->forceZero = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to update the plan status.');

        Approvals::transition(1, 'ready');
    }
}
