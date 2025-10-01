<?php

declare(strict_types=1);

namespace FP\Publisher\Infra;

use DateTimeZone;
use FP\Publisher\Support\Channels;
use FP\Publisher\Support\Logging\Logger;
use RuntimeException;
use Throwable;
use function add_option;
use function array_key_exists;
use function array_replace_recursive;
use function base64_decode;
use function base64_encode;
use function defined;
use function explode;
use function filter_var;
use function function_exists;
use function get_option;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;
use function preg_match;
use function preg_replace;
use function random_bytes;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function str_starts_with;
use function strlen;
use function substr;
use function update_option;
use function wp_strip_all_tags;

final class Options
{
    private const OPTION_KEY = 'fp_publisher_options';
    private const DEFAULT_TIMEZONE = 'Europe/Rome';
    private const DEFAULT_HTTP_TIMEOUT = 15;
    private const DEFAULT_RETRY_BACKOFF = [
        'base' => 60,
        'factor' => 2.0,
        'max' => 3600,
    ];

    public static function bootstrap(): void
    {
        $existing = get_option(self::OPTION_KEY, null);

        if ($existing === null) {
            add_option(self::OPTION_KEY, self::getDefaults(), '', 'no');

            return;
        }

        update_option(self::OPTION_KEY, $existing, false);
    }

    public static function all(): array
    {
        $stored = self::getRaw();
        $options = array_replace_recursive(self::getDefaults(), $stored);
        $options['tokens'] = self::decryptTokens($stored['tokens'] ?? []);

        return $options;
    }

    public static function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return self::all();
        }

        $options = self::all();
        $segments = explode('.', $key);
        $value = $options;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }

            return $default;
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        if ($segments[0] === 'tokens') {
            if (count($segments) === 1 && is_array($value)) {
                foreach ($value as $service => $token) {
                    self::setToken((string) $service, is_string($token) ? $token : null);
                }
                return;
            }

            if (count($segments) === 2) {
                self::setToken($segments[1], is_string($value) ? $value : null);
                return;
            }
        }

        $stored = self::getRaw();
        $ref =& $stored;

        foreach ($segments as $index => $segment) {
            if (! is_array($ref)) {
                $ref = [];
            }

            if ($index === count($segments) - 1) {
                $ref[$segment] = self::sanitizeValue($segments, $value);
                continue;
            }

            if (! array_key_exists($segment, $ref) || ! is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref =& $ref[$segment];
        }

        $updated = update_option(self::OPTION_KEY, $stored, false);
        if ($updated === false) {
            Logger::get()->error('Unable to update FP Publisher options.', [
                'key' => $key,
            ]);

            throw new RuntimeException('Unable to persist configuration changes.');
        }
    }

    public static function getTokens(): array
    {
        return self::decryptTokens(self::getRaw()['tokens'] ?? []);
    }

    public static function setToken(string $service, ?string $token): void
    {
        $sanitizedService = self::sanitizeTokenService($service);

        $stored = self::getRaw();
        $stored['tokens'] ??= [];

        if ($token === null || $token === '') {
            unset($stored['tokens'][$sanitizedService]);
        } else {
            $stored['tokens'][$sanitizedService] = self::encodeToken($token, $sanitizedService);
        }

        $updated = update_option(self::OPTION_KEY, $stored, false);
        if ($updated === false) {
            Logger::get()->error('Unable to update token configuration.', [
                'service' => $sanitizedService,
            ]);

            throw new RuntimeException('Unable to persist token changes.');
        }
    }

    private static function sanitizeTokenService(string $service): string
    {
        $normalized = preg_replace('/\s+/', '_', $service);
        $normalized = is_string($normalized) ? $normalized : '';
        $sanitized = sanitize_key($normalized);

        if ($sanitized === '' || preg_match('/[a-z0-9]/', $sanitized) !== 1) {
            throw new RuntimeException('Invalid token service identifier.');
        }

        return $sanitized;
    }

    public static function hasTokens(): bool
    {
        foreach (self::getTokens() as $token) {
            if (is_string($token) && $token !== '') {
                return true;
            }
        }

        return false;
    }

    private static function sanitizeValue(array $segments, mixed $value): mixed
    {
        return match ($segments[0] ?? '') {
            'brands' => array_values(array_filter(array_map('sanitize_text_field', (array) $value))),
            'channels' => self::sanitizeChannelList($value),
            'alert_emails' => array_values(array_filter(array_map(
                static fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) ? sanitize_email($email) : null,
                (array) $value
            ))),
            'timezone' => self::sanitizeTimezone($value),
            'queue' => self::sanitizeQueueValue($segments, $value),
            'integrations' => self::sanitizeIntegrationsValue($segments, $value),
            'cleanup' => self::sanitizeCleanupValue($segments, $value),
            default => $value,
        };
    }

    private static function sanitizeCleanupValue(array $segments, mixed $value): mixed
    {
        $field = $segments[1] ?? '';

        if (in_array($field, ['jobs_retention_days', 'assets_retention_days'], true)) {
            $days = (int) $value;

            return $days > 0 ? $days : 1;
        }

        if ($field === 'terms_cache_ttl_minutes') {
            $minutes = (int) $value;

            return $minutes > 0 ? $minutes : 60;
        }

        return $value;
    }

    private static function sanitizeChannelList(mixed $value): array
    {
        $normalized = [];

        foreach ((array) $value as $channel) {
            $slug = Channels::normalize(is_scalar($channel) ? (string) $channel : '');
            if ($slug === '') {
                continue;
            }

            if (! isset($normalized[$slug])) {
                $normalized[$slug] = $slug;
            }
        }

        return array_values($normalized);
    }

    private static function sanitizeQueueValue(array $segments, mixed $value): mixed
    {
        if (($segments[1] ?? '') === 'retry_backoff') {
            if (count($segments) === 2) {
                return self::sanitizeRetryBackoffArray($value);
            }

            $field = $segments[2] ?? '';
            return match ($field) {
                'base' => max(10, (int) $value),
                'factor' => max(1.0, (float) $value),
                'max' => max(60, (int) $value),
                default => $value,
            };
        }

        if (($segments[1] ?? '') === 'blackout_windows') {
            return self::sanitizeBlackoutWindows($value);
        }

        $field = $segments[1] ?? '';
        return match ($field) {
            'max_concurrent' => max(1, (int) $value),
            'max_attempts' => max(1, (int) $value),
            default => $value,
        };
    }

    private static function sanitizeBlackoutWindows(mixed $value): array
    {
        $timezone = self::sanitizeTimezone(self::get('timezone'));
        $windows = [];

        foreach ((array) $value as $window) {
            if (! is_array($window)) {
                continue;
            }

            $start = self::sanitizeTime($window['start'] ?? null);
            $end = self::sanitizeTime($window['end'] ?? null);

            if ($start === '' || $end === '') {
                continue;
            }

            $channel = isset($window['channel']) ? Channels::normalize((string) $window['channel']) : '';
            $tz = isset($window['timezone']) && is_string($window['timezone']) && $window['timezone'] !== ''
                ? self::sanitizeBlackoutWindowTimezone($window['timezone'], $timezone, $channel !== '' ? $channel : null)
                : $timezone;

            $days = [];
            if (isset($window['days'])) {
                foreach ((array) $window['days'] as $day) {
                    $day = (int) $day;
                    if ($day >= 0 && $day <= 6) {
                        $days[] = $day;
                    }
                }
            }

            $windows[] = [
                'channel' => $channel !== '' ? $channel : null,
                'start' => $start,
                'end' => $end,
                'timezone' => $tz,
                'days' => array_values(array_unique($days)),
            ];
        }

        return $windows;
    }

    private static function sanitizeTime(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return preg_match('/^\d{2}:\d{2}$/', $value) === 1 ? $value : '';
    }

    private static function sanitizeBlackoutWindowTimezone(string $value, string $fallback, ?string $channel): string
    {
        $timezone = sanitize_text_field($value);

        if ($timezone === '') {
            return $fallback;
        }

        try {
            new DateTimeZone($timezone);

            return $timezone;
        } catch (Throwable $exception) {
            Logger::get()->warning('Invalid blackout window timezone provided, using plugin default.', [
                'timezone' => $timezone,
                'channel' => $channel,
                'error' => wp_strip_all_tags($exception->getMessage()),
            ]);

            return $fallback;
        }
    }

    private static function sanitizeTimezone(mixed $value): string
    {
        $timezone = '';
        if (is_string($value) && $value !== '') {
            $timezone = sanitize_text_field($value);
        }

        if ($timezone === '') {
            return self::DEFAULT_TIMEZONE;
        }

        try {
            new DateTimeZone($timezone);

            return $timezone;
        } catch (Throwable $exception) {
            Logger::get()->warning('Invalid timezone configured, falling back to default.', [
                'timezone' => $timezone,
                'error' => wp_strip_all_tags($exception->getMessage()),
            ]);

            return self::DEFAULT_TIMEZONE;
        }
    }

    private static function getDefaults(): array
    {
        return [
            'brands' => [],
            'channels' => [],
            'alert_emails' => [],
            'timezone' => self::DEFAULT_TIMEZONE,
            'queue' => [
                'max_concurrent' => 5,
                'max_attempts' => 5,
                'retry_backoff' => self::DEFAULT_RETRY_BACKOFF,
                'blackout_windows' => [],
            ],
            'cleanup' => [
                'jobs_retention_days' => 180,
                'assets_retention_days' => 7,
                'terms_cache_ttl_minutes' => 1440,
            ],
            'integrations' => [
                'http' => [
                    'default_timeout' => self::DEFAULT_HTTP_TIMEOUT,
                    'channels' => [],
                ],
                'queue' => [
                    'default_retry_backoff' => self::DEFAULT_RETRY_BACKOFF,
                    'channels' => [],
                ],
            ],
            'tokens' => [],
        ];
    }

    private static function sanitizeIntegrationsValue(array $segments, mixed $value): mixed
    {
        $section = $segments[1] ?? '';

        if ($section === 'http') {
            $field = $segments[2] ?? '';

            if ($field === 'default_timeout') {
                return max(1, (int) $value);
            }

            if ($field === 'channels') {
                return self::sanitizeHttpChannels($value);
            }

            if ($field !== '') {
                return $value;
            }

            if (! is_array($value)) {
                return [
                    'default_timeout' => self::DEFAULT_HTTP_TIMEOUT,
                    'channels' => [],
                ];
            }

            return [
                'default_timeout' => max(1, (int) ($value['default_timeout'] ?? self::DEFAULT_HTTP_TIMEOUT)),
                'channels' => self::sanitizeHttpChannels($value['channels'] ?? []),
            ];
        }

        if ($section === 'queue') {
            $field = $segments[2] ?? '';

            if ($field === 'default_retry_backoff') {
                return self::sanitizeRetryBackoffArray($value);
            }

            if ($field === 'channels') {
                return self::sanitizeQueueChannels($value);
            }

            if ($field !== '') {
                return $value;
            }

            if (! is_array($value)) {
                return [
                    'default_retry_backoff' => self::sanitizeRetryBackoffArray([]),
                    'channels' => [],
                ];
            }

            return [
                'default_retry_backoff' => self::sanitizeRetryBackoffArray($value['default_retry_backoff'] ?? []),
                'channels' => self::sanitizeQueueChannels($value['channels'] ?? []),
            ];
        }

        return $value;
    }

    private static function sanitizeHttpChannels(mixed $value): array
    {
        $channels = [];

        foreach ((array) $value as $channel => $config) {
            $key = Channels::normalize((string) $channel);
            if ($key === '') {
                continue;
            }

            $channelConfig = [];

            if (is_array($config) && isset($config['timeout'])) {
                $channelConfig['timeout'] = max(1, (int) $config['timeout']);
            } elseif (is_numeric($config)) {
                $channelConfig['timeout'] = max(1, (int) $config);
            }

            if ($channelConfig !== []) {
                $channels[$key] = $channelConfig;
            }
        }

        return $channels;
    }

    private static function sanitizeQueueChannels(mixed $value): array
    {
        $channels = [];

        foreach ((array) $value as $channel => $config) {
            $key = Channels::normalize((string) $channel);
            if ($key === '' || ! is_array($config)) {
                continue;
            }

            $channelConfig = [];

            if (isset($config['retry_backoff'])) {
                $channelConfig['retry_backoff'] = self::sanitizeRetryBackoffArray($config['retry_backoff']);
            }

            if ($channelConfig !== []) {
                $channels[$key] = $channelConfig;
            }
        }

        return $channels;
    }

    private static function sanitizeRetryBackoffArray(mixed $value): array
    {
        $config = is_array($value) ? $value : [];

        return [
            'base' => max(10, (int) ($config['base'] ?? self::DEFAULT_RETRY_BACKOFF['base'])),
            'factor' => max(1.0, (float) ($config['factor'] ?? self::DEFAULT_RETRY_BACKOFF['factor'])),
            'max' => max(60, (int) ($config['max'] ?? self::DEFAULT_RETRY_BACKOFF['max'])),
        ];
    }

    private static function getRaw(): array
    {
        $stored = get_option(self::OPTION_KEY, []);

        return is_array($stored) ? $stored : [];
    }

    private static function decryptTokens(array $tokens): array
    {
        $decrypted = [];

        foreach ($tokens as $service => $payload) {
            $decoded = self::decodeToken(is_string($payload) ? $payload : null);
            if ($decoded !== null) {
                $decrypted[$service] = $decoded;
            }
        }

        return $decrypted;
    }

    private static function encodeToken(string $token, string $service): string
    {
        if (! self::sodiumAvailable()) {
            return 'plain:' . base64_encode($token);
        }

        try {
            $nonceLength = self::nonceLength();
            $nonce = random_bytes($nonceLength);
            $ciphertext = sodium_crypto_secretbox($token, $nonce, self::encryptionKey());

            return 'sodium:' . base64_encode($nonce . $ciphertext);
        } catch (Throwable $exception) {
            Logger::get()->warning('Token encryption failed, storing plain value.', [
                'service' => sanitize_key($service),
                'option_key' => 'tokens.' . sanitize_key($service),
                'error' => wp_strip_all_tags($exception->getMessage()),
            ]);

            return 'plain:' . base64_encode($token);
        }
    }

    private static function decodeToken(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        if (str_starts_with($payload, 'sodium:') && self::sodiumAvailable()) {
            $decoded = base64_decode(substr($payload, 7), true);
            $nonceLength = self::nonceLength();
            if ($decoded === false || strlen($decoded) <= $nonceLength) {
                return null;
            }

            $nonce = substr($decoded, 0, $nonceLength);
            $ciphertext = substr($decoded, $nonceLength);
            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, self::encryptionKey());

            return $plaintext === false ? null : $plaintext;
        }

        if (str_starts_with($payload, 'plain:')) {
            $decoded = base64_decode(substr($payload, 6), true);
            return $decoded === false ? null : $decoded;
        }

        return null;
    }

    private static function sodiumAvailable(): bool
    {
        return function_exists('sodium_crypto_secretbox')
            && function_exists('sodium_crypto_secretbox_open')
            && function_exists('sodium_crypto_generichash')
            && defined('SODIUM_CRYPTO_SECRETBOX_KEYBYTES');
    }

    private static function encryptionKey(): string
    {
        $siteKey = defined('AUTH_KEY') && AUTH_KEY !== '' ? AUTH_KEY : (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'fp-publisher');
        $keyLength = self::keyLength();

        return sodium_crypto_generichash($siteKey, '', $keyLength);
    }

    private static function nonceLength(): int
    {
        return defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES') ? SODIUM_CRYPTO_SECRETBOX_NONCEBYTES : 24;
    }

    private static function keyLength(): int
    {
        return defined('SODIUM_CRYPTO_SECRETBOX_KEYBYTES') ? SODIUM_CRYPTO_SECRETBOX_KEYBYTES : 32;
    }
}
