<?php

declare(strict_types=1);

namespace FP\Publisher\Support\Logging;

use DateTimeInterface;
use FP\Publisher\Support\Strings;
use Psr\Log\AbstractLogger;
use Stringable;
use Throwable;

use function error_log;
use function get_debug_type;
use function gmdate;
use function is_array;
use function is_object;
use function is_scalar;
use function is_string;
use function json_encode;
use function method_exists;
use function strtr;
use function strtoupper;
use const DATE_ATOM;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class StructuredLogger extends AbstractLogger
{
    private const MAX_STRING_LENGTH = 2000;
    private const MAX_DEPTH = 3;

    public function log($level, $message, array $context = []): void
    {
        $entry = [
            'timestamp' => gmdate('c'),
            'level' => strtoupper((string) $level),
            'message' => $this->interpolate((string) $message, $context),
            'context' => $this->normalizeContext($context),
        ];

        $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($encoded)) {
            $fallback = [
                'timestamp' => gmdate('c'),
                'level' => 'ERROR',
                'message' => 'Failed to encode log entry.',
            ];

            $encoded = json_encode($fallback) ?: '"Failed to encode log entry."';
        }

        error_log($encoded);
    }

    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $value) {
            if (! is_scalar($value) && ! $value instanceof Stringable) {
                continue;
            }

            $replace['{' . $key . '}'] = $this->truncate((string) $value);
        }

        return strtr($message, $replace);
    }

    private function normalizeContext(array $context, int $depth = 0): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['notice' => 'Context depth limit reached'];
        }

        $normalized = [];

        foreach ($context as $key => $value) {
            $normalized[(string) $key] = $this->normalizeValue($value, $depth);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value, int $depth): mixed
    {
        if ($depth >= self::MAX_DEPTH) {
            return '…';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof Throwable) {
            return [
                'class' => $value::class,
                'message' => $this->truncate($value->getMessage()),
            ];
        }

        if ($value instanceof Stringable) {
            return $this->truncate((string) $value);
        }

        if (is_scalar($value) || $value === null) {
            return is_string($value) ? $this->truncate($value) : $value;
        }

        if (is_array($value)) {
            $normalized = [];
            $count = 0;

            foreach ($value as $key => $item) {
                if ($count++ >= 25) {
                    $normalized['…'] = 'truncated';
                    break;
                }

                $normalized[(string) $key] = $this->normalizeValue($item, $depth + 1);
            }

            return $normalized;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return $this->truncate((string) $value);
            }

            return ['object' => $value::class];
        }

        return get_debug_type($value);
    }

    private function truncate(string $value): string
    {
        if (Strings::length($value) <= self::MAX_STRING_LENGTH) {
            return $value;
        }

        return Strings::safeSubstr($value, self::MAX_STRING_LENGTH - 1) . '…';
    }
}
