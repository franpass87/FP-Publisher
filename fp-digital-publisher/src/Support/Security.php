<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use RuntimeException;

use function base64_decode;
use function base64_encode;
use function function_exists;
use function hash_equals;
use function max;
use function random_bytes;
use function sodium_crypto_aead_xchacha20poly1305_ietf_decrypt;
use function sodium_crypto_aead_xchacha20poly1305_ietf_encrypt;
use function sodium_crypto_aead_xchacha20poly1305_ietf_keygen;
use function sodium_memzero;
use function str_repeat;
use function strlen;
use function substr;

final class Security
{
    public static function supportsSodium(): bool
    {
        return function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt');
    }

    public static function generateKey(): string
    {
        if (! self::supportsSodium()) {
            throw new RuntimeException('Sodium is required to generate encryption keys.');
        }

        return sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
    }

    public static function encrypt(string $plaintext, string $key): string
    {
        if (! self::supportsSodium()) {
            return $plaintext;
        }

        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, '', $nonce, $key);

        return base64_encode($nonce . $cipher);
    }

    public static function decrypt(string $payload, string $key): string
    {
        if (! self::supportsSodium()) {
            return $payload;
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            throw new RuntimeException('Unable to decode secure payload.');
        }

        $nonceSize = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        $nonce = substr($decoded, 0, $nonceSize);
        $cipher = substr($decoded, $nonceSize);

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, '', $nonce, $key);
        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt secure payload.');
        }

        sodium_memzero($cipher);

        return $plaintext;
    }

    public static function redact(string $value, int $visible = 4): string
    {
        if ($value === '') {
            return '';
        }

        $length = strlen($value);
        $visible = max(0, $visible);

        if ($length <= $visible) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - $visible) . substr($value, -$visible);
    }

    public static function constantTimeEquals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }
}
