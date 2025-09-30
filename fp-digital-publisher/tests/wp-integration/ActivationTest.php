<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\WpIntegration;

use FP\Publisher\Infra\DB\Migrations;
use WP_UnitTestCase;

require_once __DIR__ . '/bootstrap.php';

final class ActivationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        if (defined('FP_PUBLISHER_SKIP_WP_TESTS') && FP_PUBLISHER_SKIP_WP_TESTS) {
            $this->markTestSkipped('WordPress test suite not available.');
        }

        parent::setUp();
    }

    public function testActivationCreatesTablesAndOptions(): void
    {
        global $wpdb;

        Migrations::uninstall();

        do_action('activate_' . FP_PUBLISHER_BASENAME);

        $tables = [
            $wpdb->prefix . 'fp_pub_jobs',
            $wpdb->prefix . 'fp_pub_jobs_archive',
            $wpdb->prefix . 'fp_pub_assets',
            $wpdb->prefix . 'fp_pub_plans',
            $wpdb->prefix . 'fp_pub_tokens',
            $wpdb->prefix . 'fp_pub_comments',
            $wpdb->prefix . 'fp_pub_links',
        ];

        foreach ($tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            $this->assertSame($table, $exists, "Failed asserting that {$table} exists after activation");
        }

        $this->assertSame('2024093001', get_option('fp_publisher_db_version'));
    }
}
