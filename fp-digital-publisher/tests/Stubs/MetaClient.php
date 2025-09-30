<?php

declare(strict_types=1);

namespace FP\Publisher\Api\Meta;

if (! class_exists(__NAMESPACE__ . '\\Client', false)) {
    final class Client
    {
        /** @var array<int, array<string, mixed>> */
        public static array $calls = [];
        /** @var callable|null */
        public static $publishFacebookPostCallback = null;
        /** @var callable|null */
        public static $publishInstagramMediaCallback = null;
        /** @var callable|null */
        public static $publishInstagramCommentCallback = null;
        /** @var callable|null */
        public static $resolveTokenCallback = null;
        /** @var callable|null */
        public static $commentExistsCallback = null;
        /** @var array<string, string|null> */
        public static array $storedTokens = [];

        public static function reset(): void
        {
            self::$calls = [];
            self::$publishFacebookPostCallback = null;
            self::$publishInstagramMediaCallback = null;
            self::$publishInstagramCommentCallback = null;
            self::$resolveTokenCallback = null;
            self::$commentExistsCallback = null;
            self::$storedTokens = [];
        }

        public static function storePageToken(string $pageId, ?string $token): void
        {
            self::$calls[] = ['method' => 'storePageToken', 'pageId' => $pageId, 'token' => $token];
            self::$storedTokens[$pageId] = $token;
        }

        /**
         * @param array<string, mixed> $payload
         * @return array<string, mixed>
         */
        public static function publishFacebookPost(array $payload): array
        {
            self::$calls[] = ['method' => 'publishFacebookPost', 'payload' => $payload];
            if (self::$publishFacebookPostCallback !== null) {
                return (self::$publishFacebookPostCallback)($payload);
            }

            return ['id' => 'fb_stub'];
        }

        /**
         * @param array<string, mixed> $payload
         * @return array<string, mixed>
         */
        public static function publishInstagramMedia(array $payload): array
        {
            self::$calls[] = ['method' => 'publishInstagramMedia', 'payload' => $payload];
            if (self::$publishInstagramMediaCallback !== null) {
                return (self::$publishInstagramMediaCallback)($payload);
            }

            return ['id' => 'ig_stub'];
        }

        public static function resolveToken(array $payload, string $userId): string
        {
            self::$calls[] = ['method' => 'resolveToken', 'payload' => $payload, 'userId' => $userId];
            if (self::$resolveTokenCallback !== null) {
                return (self::$resolveTokenCallback)($payload, $userId);
            }

            return 'token_' . ($userId !== '' ? $userId : 'default');
        }

        public static function hashMessage(string $message): string
        {
            return sha1($message);
        }

        public static function commentExists(string $mediaId, string $hash, string $token): bool
        {
            self::$calls[] = [
                'method' => 'commentExists',
                'mediaId' => $mediaId,
                'hash' => $hash,
                'token' => $token,
            ];

            if (self::$commentExistsCallback !== null) {
                return (self::$commentExistsCallback)($mediaId, $hash, $token);
            }

            return false;
        }

        /**
         * @return array<string, mixed>
         */
        public static function publishInstagramComment(string $mediaId, string $message, string $token): array
        {
            self::$calls[] = [
                'method' => 'publishInstagramComment',
                'mediaId' => $mediaId,
                'message' => $message,
                'token' => $token,
            ];

            if (self::$publishInstagramCommentCallback !== null) {
                return (self::$publishInstagramCommentCallback)($mediaId, $message, $token);
            }

            return ['id' => 'ig_comment_stub'];
        }
    }
}
