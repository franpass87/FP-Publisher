<?php

declare(strict_types=1);

namespace FP\Publisher\Services\Assets;

use DateInterval;
use DateTimeImmutable;
use DirectoryIterator;
use FP\Publisher\Api\Meta\Client as MetaClient;
use FP\Publisher\Api\TikTok\Client as TikTokClient;
use FP\Publisher\Api\YouTube\Client as YouTubeClient;
use FP\Publisher\Support\Dates;
use RuntimeException;
use WP_Error;

use function __;
use function abs;
use function add_action;
use function apply_filters;
use function file_exists;
use function in_array;
use function is_array;
use function is_dir;
use function is_string;
use function sanitize_file_name;
use function sanitize_key;
use function sanitize_text_field;
use function time;
use function trailingslashit;
use function unlink;
use function wp_mkdir_p;
use function wp_next_scheduled;
use function wp_schedule_event;
use function wp_unique_filename;
use function wp_upload_dir;

final class Pipeline
{
    private const CLEANUP_HOOK = 'fp_pub_assets_cleanup';
    private const TEMP_SUBDIR = 'fp-temp';
    private const DEFAULT_TTL_MINUTES = 360;

    /**
     * @var array<int, string>
     */
    private const DIRECT_CHANNELS = ['youtube', 'tiktok', 'meta'];

    public static function register(): void
    {
        add_action('init', [self::class, 'scheduleCleanup']);
        add_action(self::CLEANUP_HOOK, [self::class, 'purgeExpired']);
    }

    public static function scheduleCleanup(): void
    {
        if (wp_next_scheduled(self::CLEANUP_HOOK)) {
            return;
        }

        wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::CLEANUP_HOOK);
    }

    public static function purgeExpired(): void
    {
        $uploadDir = wp_upload_dir();
        if (! empty($uploadDir['error'])) {
            return;
        }

        $baseDir = trailingslashit($uploadDir['basedir']) . self::TEMP_SUBDIR;
        if (! is_dir($baseDir)) {
            return;
        }

        $lifetime = (int) apply_filters('fp_publisher_assets_ttl', self::DEFAULT_TTL_MINUTES);
        if ($lifetime <= 0) {
            $lifetime = self::DEFAULT_TTL_MINUTES;
        }

        $expiry = Dates::now('UTC')->sub(new DateInterval('PT' . $lifetime . 'M'));
        $iterator = new DirectoryIterator($baseDir);

        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDot() || ! $fileinfo->isFile()) {
                continue;
            }

            $modified = DateTimeImmutable::createFromFormat('U', (string) $fileinfo->getMTime()) ?: null;
            if ($modified === null || $modified >= $expiry) {
                continue;
            }

            $path = $fileinfo->getPathname();
            if ($path !== '' && file_exists($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public static function prepareUpload(array $payload)
    {
        $channel = sanitize_key((string) ($payload['channel'] ?? ''));
        if ($channel === '') {
            return new WP_Error('fp_publisher_invalid_channel', __('Canale non valido per l\'upload.', 'fp_publisher'));
        }

        $media = is_array($payload['media'] ?? null) ? $payload['media'] : [];
        $mediaMeta = $media !== [] && is_array($media[0]) ? (array) $media[0] : [];

        $validation = Validator::validate($channel, $mediaMeta);
        if ($validation['blocking'] !== []) {
            return new WP_Error(
                'fp_publisher_invalid_media',
                __('Il media non soddisfa i requisiti per il canale selezionato.', 'fp_publisher'),
                ['issues' => $validation]
            );
        }

        if (in_array($channel, self::DIRECT_CHANNELS, true)) {
            return self::prepareDirectUpload($channel, $payload);
        }

        return self::prepareLocalUpload($mediaMeta);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    private static function prepareDirectUpload(string $channel, array $payload)
    {
        try {
            if ($channel === 'youtube') {
                return YouTubeClient::createResumableTicket($payload);
            }

            if ($channel === 'tiktok') {
                return TikTokClient::createResumableTicket($payload);
            }

            return MetaClient::createDirectUploadTicket($payload);
        } catch (RuntimeException $exception) {
            return new WP_Error(
                'fp_publisher_upload_failed',
                $exception->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * @param array<string, mixed> $media
     * @return array<string, mixed>
     */
    private static function prepareLocalUpload(array $media): array
    {
        $uploadDir = wp_upload_dir();
        if (! empty($uploadDir['error'])) {
            throw new RuntimeException(sanitize_text_field((string) $uploadDir['error']));
        }

        $baseDir = trailingslashit($uploadDir['basedir']) . self::TEMP_SUBDIR;
        $baseUrl = trailingslashit($uploadDir['baseurl']) . self::TEMP_SUBDIR;

        if (! is_dir($baseDir) && ! wp_mkdir_p($baseDir)) {
            throw new RuntimeException(__('Impossibile preparare la directory temporanea.', 'fp_publisher'));
        }

        $filename = isset($media['filename']) && is_string($media['filename'])
            ? sanitize_file_name($media['filename'])
            : 'asset-' . time();
        $extension = isset($media['extension']) && is_string($media['extension']) ? sanitize_key($media['extension']) : '';
        if ($extension !== '') {
            $filename .= '.' . $extension;
        }

        $unique = wp_unique_filename($baseDir, $filename);
        $path = $baseDir . '/' . $unique;

        return [
            'strategy' => 'local',
            'path' => $path,
            'url' => trailingslashit($baseUrl) . $unique,
            'expires_at' => Dates::now('UTC')
                ->add(new DateInterval('PT' . self::DEFAULT_TTL_MINUTES . 'M'))
                ->format(DateTimeImmutable::ATOM),
            'metadata' => [
                'mime' => isset($media['mime']) && is_string($media['mime']) ? sanitize_text_field($media['mime']) : '',
                'bytes' => isset($media['bytes']) ? abs((int) $media['bytes']) : 0,
            ],
        ];
    }
}
