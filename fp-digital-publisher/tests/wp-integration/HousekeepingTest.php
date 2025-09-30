<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\WpIntegration;

use DateInterval;
use FP\Publisher\Infra\Options;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Services\Housekeeping;
use FP\Publisher\Support\Dates;
use WP_UnitTestCase;

use function file_exists;
use function file_put_contents;
use function is_dir;
use function is_string;
use function str_replace;
use function trailingslashit;
use function wp_mkdir_p;
use function wp_upload_dir;

require_once __DIR__ . '/bootstrap.php';

final class HousekeepingTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        if (defined('FP_PUBLISHER_SKIP_WP_TESTS') && FP_PUBLISHER_SKIP_WP_TESTS) {
            $this->markTestSkipped('WordPress test suite not available.');
        }

        parent::setUp();
    }

    public function testCleanupArchivesOldJobsAndPurgesAssets(): void
    {
        global $wpdb;

        $jobsTable = $wpdb->prefix . 'fp_pub_jobs';
        $archiveTable = $wpdb->prefix . 'fp_pub_jobs_archive';
        $assetsTable = $wpdb->prefix . 'fp_pub_assets';

        $wpdb->query("DELETE FROM {$archiveTable}");
        $wpdb->query("DELETE FROM {$jobsTable}");
        $wpdb->query("DELETE FROM {$assetsTable}");

        Options::set('cleanup.jobs_retention_days', 30);
        Options::set('cleanup.assets_retention_days', 5);

        $now = Dates::now('UTC');
        $oldTimestamp = $now->sub(new DateInterval('P60D'))->format('Y-m-d H:i:s');
        $recentTimestamp = $now->sub(new DateInterval('P2D'))->format('Y-m-d H:i:s');

        $wpdb->insert(
            $jobsTable,
            [
                'status' => Queue::STATUS_COMPLETED,
                'channel' => 'meta_facebook',
                'payload_json' => '{}',
                'run_at' => $oldTimestamp,
                'attempts' => 1,
                'error' => null,
                'idempotency_key' => 'archive-me',
                'remote_id' => '',
                'created_at' => $oldTimestamp,
                'updated_at' => $oldTimestamp,
                'child_job_id' => null,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        $wpdb->insert(
            $jobsTable,
            [
                'status' => Queue::STATUS_FAILED,
                'channel' => 'meta_instagram',
                'payload_json' => '{}',
                'run_at' => $recentTimestamp,
                'attempts' => 3,
                'error' => 'Boom',
                'idempotency_key' => 'stay-here',
                'remote_id' => 'remote-1',
                'created_at' => $recentTimestamp,
                'updated_at' => $recentTimestamp,
                'child_job_id' => null,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        $uploads = wp_upload_dir();
        $baseDir = isset($uploads['basedir']) && is_string($uploads['basedir'])
            ? trailingslashit($uploads['basedir'])
            : '';
        $this->assertNotSame('', $baseDir, 'Uploads base directory should resolve.');
        $tempDir = $baseDir . 'fp-temp';
        if (! is_dir($tempDir)) {
            wp_mkdir_p($tempDir);
        }

        $expiredPath = $tempDir . '/expired.txt';
        file_put_contents($expiredPath, 'cleanup');
        $expiredRef = str_replace($baseDir, '', $expiredPath);

        $wpdb->insert(
            $assetsTable,
            [
                'source' => 'local',
                'ref' => $expiredRef,
                'mime' => 'text/plain',
                'bytes' => 7,
                'temp_until' => $now->sub(new DateInterval('P10D'))->format('Y-m-d H:i:s'),
            ],
            ['%s', '%s', '%s', '%d', '%s']
        );

        $wpdb->insert(
            $assetsTable,
            [
                'source' => 'remote',
                'ref' => 'https://cdn.example.com/foo.jpg',
                'mime' => 'image/jpeg',
                'bytes' => 2048,
                'temp_until' => $now->add(new DateInterval('P1D'))->format('Y-m-d H:i:s'),
            ],
            ['%s', '%s', '%s', '%d', '%s']
        );

        Housekeeping::run();

        $archivedJob = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$archiveTable} WHERE idempotency_key = %s", 'archive-me'), ARRAY_A);
        $this->assertIsArray($archivedJob);
        $this->assertSame('archive-me', $archivedJob['idempotency_key']);
        $this->assertSame('meta_facebook', $archivedJob['channel']);

        $remainingJob = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$jobsTable} WHERE idempotency_key = %s", 'stay-here'), ARRAY_A);
        $this->assertIsArray($remainingJob);
        $this->assertSame('stay-here', $remainingJob['idempotency_key']);

        $this->assertNull($wpdb->get_row($wpdb->prepare("SELECT * FROM {$jobsTable} WHERE idempotency_key = %s", 'archive-me'), ARRAY_A));

        $expiredExists = file_exists($expiredPath);
        $this->assertFalse($expiredExists, 'Expected expired asset file to be removed');

        $expiredRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$assetsTable} WHERE ref = %s", $expiredRef), ARRAY_A);
        $this->assertNull($expiredRow);

        $activeAsset = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$assetsTable} WHERE ref = %s", 'https://cdn.example.com/foo.jpg'), ARRAY_A);
        $this->assertIsArray($activeAsset);
    }
}
