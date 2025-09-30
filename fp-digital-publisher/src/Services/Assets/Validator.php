<?php

declare(strict_types=1);

namespace FP\Publisher\Services\Assets;

use function __;
use function abs;
use function is_numeric;
use function is_string;
use function max;
use function round;

final class Validator
{
    /**
     * @var array<string, array<string, float|int>>
     */
    private const RULES = [
        'youtube' => [
            'ratio_min' => 0.1,
            'ratio_max' => 4.0,
            'duration_max' => 43200.0,
            'bitrate_max' => 85_000_000.0,
            'size_max' => 274_877_906_944.0, // 256 GiB
        ],
        'tiktok' => [
            'ratio_min' => 0.5,
            'ratio_max' => 2.0,
            'duration_max' => 600.0,
            'bitrate_max' => 50_000_000.0,
            'size_max' => 4_294_967_296.0, // 4 GiB
        ],
        'meta' => [
            'ratio_min' => 0.5,
            'ratio_max' => 2.0,
            'duration_max' => 3600.0,
            'bitrate_max' => 35_000_000.0,
            'size_max' => 2_147_483_648.0, // 2 GiB
        ],
        'default' => [
            'ratio_min' => 0.4,
            'ratio_max' => 2.4,
            'duration_max' => 7200.0,
            'bitrate_max' => 35_000_000.0,
            'size_max' => 1_610_612_736.0, // 1.5 GiB
        ],
    ];

    /**
     * @param array<string, mixed> $media
     * @return array{blocking: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>}
     */
    public static function validate(string $channel, array $media): array
    {
        $rules = self::RULES[$channel] ?? self::RULES['default'];
        $blocking = [];
        $warnings = [];

        $width = self::toInt($media['width'] ?? $media['video_width'] ?? null);
        $height = self::toInt($media['height'] ?? $media['video_height'] ?? null);
        if ($width > 0 && $height > 0) {
            $ratio = $height > 0 ? $width / $height : 0.0;
            if ($ratio < (float) $rules['ratio_min'] || $ratio > (float) $rules['ratio_max']) {
                $blocking[] = [
                    'check' => 'ratio',
                    'message' => __(
                        'The video aspect ratio is not supported for the selected channel.',
                        'fp-publisher'
                    ),
                    'value' => round($ratio, 3),
                    'expected' => [$rules['ratio_min'], $rules['ratio_max']],
                ];
            }
        } else {
            $warnings[] = [
                'check' => 'ratio',
                'message' => __('Unable to validate the media ratio: missing dimensions.', 'fp-publisher'),
            ];
        }

        $duration = self::toFloat($media['duration'] ?? $media['seconds'] ?? null);
        if ($duration > 0.0 && $duration > (float) $rules['duration_max']) {
            $blocking[] = [
                'check' => 'duration',
                'message' => __('The duration exceeds the maximum allowed for the channel.', 'fp-publisher'),
                'value' => $duration,
                'expected' => $rules['duration_max'],
            ];
        }

        $size = self::toFloat($media['bytes'] ?? $media['size'] ?? null);
        if ($size > 0.0 && $size > (float) $rules['size_max']) {
            $blocking[] = [
                'check' => 'size',
                'message' => __('The file exceeds the maximum allowed size.', 'fp-publisher'),
                'value' => $size,
                'expected' => $rules['size_max'],
            ];
        }

        $bitrate = self::toFloat($media['bitrate'] ?? null);
        if ($bitrate <= 0.0 && $duration > 0.0 && $size > 0.0) {
            $bitrate = ($size * 8) / max($duration, 1.0);
        }
        if ($bitrate > 0.0 && $bitrate > (float) $rules['bitrate_max']) {
            $blocking[] = [
                'check' => 'bitrate',
                'message' => __('The video bitrate is too high for the channel.', 'fp-publisher'),
                'value' => $bitrate,
                'expected' => $rules['bitrate_max'],
            ];
        }

        return [
            'blocking' => $blocking,
            'warnings' => $warnings,
        ];
    }

    private static function toInt(mixed $value): int
    {
        if (is_numeric($value)) {
            return abs((int) $value);
        }

        if (is_string($value) && $value !== '') {
            return abs((int) $value);
        }

        return 0;
    }

    private static function toFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return abs((float) $value);
        }

        if (is_string($value) && $value !== '') {
            return abs((float) $value);
        }

        return 0.0;
    }
}
