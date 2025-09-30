<?php

declare(strict_types=1);

namespace FP\Publisher\Api\TikTok;

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
    }
}
