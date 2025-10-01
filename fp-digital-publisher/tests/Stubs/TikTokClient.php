<?php

declare(strict_types=1);

namespace FP\Publisher\Api\TikTok;

use FP\Publisher\Support\Strings;

if (! class_exists(__NAMESPACE__ . '\\Client', false)) {

    final class Client
    {
        /** @var array<int, array<string, mixed>> */
        public static array $calls = [];
        /** @var callable|null */
        public static $publishVideoCallback = null;

        public static function reset(): void
        {
            self::$calls = [];
            self::$publishVideoCallback = null;
        }

        /**
         * @param array<string, mixed> $payload
         * @return array<string, mixed>
         */
        public static function publishVideo(array $payload): array
        {
            self::$calls[] = ['method' => 'publishVideo', 'payload' => $payload];
            if (self::$publishVideoCallback !== null) {
                return (self::$publishVideoCallback)($payload);
            }

            return ['id' => 'tiktok_stub'];
        }

        public static function sanitizeCaption(mixed $value): string
        {
            $caption = is_string($value) ? trim($value) : '';
            if ($caption === '') {
                return '';
            }

            $caption = wp_strip_all_tags($caption);

            return Strings::safeSubstr($caption, 2200);
        }
    }
}
