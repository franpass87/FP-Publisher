<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Services\Assets;

use FP\Publisher\Infra\Options;
use FP\Publisher\Services\Assets\Pipeline;
use FP\Publisher\Support\Dates;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function scandir;
use function sys_get_temp_dir;
use function time;
use function touch;
use function uniqid;
use function unlink;
use function wp_stub_reset;
use function wp_stub_set_upload_dir;
use function wp_upload_dir;
use const DAY_IN_SECONDS;

final class PipelineTest extends TestCase
{
    private string $uploadDir;

    protected function setUp(): void
    {
        parent::setUp();
        wp_stub_reset();
        Options::bootstrap();
        $this->uploadDir = sys_get_temp_dir() . '/fp-publisher-' . uniqid('', true);
        wp_stub_set_upload_dir($this->uploadDir);
        wp_upload_dir();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->uploadDir);
        parent::tearDown();
    }

    public function testPrepareLocalUploadUsesRetentionOption(): void
    {
        Options::set('cleanup.assets_retention_days', 2);

        $result = Pipeline::prepareUpload([
            'channel' => 'wordpress',
            'media' => [
                [
                    'filename' => 'example',
                    'extension' => 'png',
                    'mime' => 'image/png',
                    'bytes' => 1200,
                ],
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('expires_at', $result);

        $expiresAt = Dates::ensure((string) $result['expires_at']);
        $seconds = $expiresAt->getTimestamp() - Dates::now('UTC')->getTimestamp();

        $this->assertEqualsWithDelta(2 * DAY_IN_SECONDS, $seconds, 5.0);
    }

    public function testPurgeExpiredRemovesStaleFiles(): void
    {
        Options::set('cleanup.assets_retention_days', 1);

        $uploads = wp_upload_dir();
        $tempDir = $uploads['basedir'] . '/fp-temp';
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $expiredPath = $tempDir . '/expired.txt';
        file_put_contents($expiredPath, 'old');
        touch($expiredPath, time() - (3 * DAY_IN_SECONDS));

        Pipeline::purgeExpired();

        $this->assertFalse(file_exists($expiredPath));
    }

    private function removeDir(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
