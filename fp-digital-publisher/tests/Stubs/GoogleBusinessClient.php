<?php

declare(strict_types=1);

namespace FP\Publisher\Api\GoogleBusiness;

if (! class_exists(__NAMESPACE__ . '\\Client', false)) {
    final class Client
    {
        /** @var array<int, array<string, mixed>> */
        public static array $calls = [];
        /** @var callable|null */
        public static $publishPostCallback = null;

        public static function reset(): void
        {
            self::$calls = [];
            self::$publishPostCallback = null;
        }

        /**
         * @param array<string, mixed> $payload
         * @return array<string, mixed>
         */
        public static function publishPost(array $payload): array
        {
            self::$calls[] = ['method' => 'publishPost', 'payload' => $payload];
            if (self::$publishPostCallback !== null) {
                return (self::$publishPostCallback)($payload);
            }

            return ['name' => 'locations/123/posts/456'];
        }
    }
}
