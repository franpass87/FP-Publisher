<?php

declare(strict_types=1);

namespace FP\Publisher\Api\YouTube;

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

            return ['id' => 'youtube_stub'];
        }

        public static function sanitizeTitle(mixed $value): string
        {
            $title = is_string($value) ? trim($value) : '';
            $title = wp_strip_all_tags($title);

            return Strings::safeSubstr($title, 100);
        }

        public static function sanitizeDescription(mixed $value): string
        {
            $description = is_string($value) ? trim($value) : '';
            $description = wp_strip_all_tags($description);

            return Strings::safeSubstr($description, 5000);
        }
    }
}
