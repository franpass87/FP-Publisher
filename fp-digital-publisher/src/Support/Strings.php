<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use function array_slice;
use function function_exists;
use function implode;
use function max;
use function preg_match_all;
use function strlen;
use function substr;

final class Strings
{
    private static ?bool $mbStringAvailableOverride = null;

    public static function safeSubstr(string $value, int $length, int $start = 0): string
    {
        if ($length <= 0) {
            return '';
        }

        if (self::mbFunctionAvailable('mb_substr')) {
            /** @psalm-suppress FalsableReturnStatement */
            return mb_substr($value, $start, $length);
        }

        if (preg_match_all('/./u', $value, $matches) > 0) {
            $slice = array_slice($matches[0], $start, $length);

            return implode('', $slice);
        }

        return substr($value, $start, $length);
    }

    public static function trimWidth(string $value, int $width, string $trimMarker = '…'): string
    {
        $width = max(0, $width);
        if ($width === 0) {
            return '';
        }

        if (self::mbFunctionAvailable('mb_strimwidth')) {
            /** @psalm-suppress FalsableReturnStatement */
            return mb_strimwidth($value, 0, $width, $trimMarker);
        }

        if (self::length($value) <= $width) {
            return $value;
        }

        $markerLength = max(0, self::length($trimMarker));
        if ($markerLength >= $width) {
            return self::safeSubstr($value, $width);
        }

        $visibleWidth = $width - $markerLength;
        $prefix = self::safeSubstr($value, $visibleWidth);

        return $prefix . $trimMarker;
    }

    public static function forceMbstringAvailabilityForTesting(?bool $available): void
    {
        self::$mbStringAvailableOverride = $available;
    }

    public static function length(string $value): int
    {
        if (self::mbFunctionAvailable('mb_strlen')) {
            /** @psalm-suppress FalsableReturnStatement */
            return mb_strlen($value);
        }

        $count = preg_match_all('/./u', $value, $matches);
        if ($count !== false && $count > 0) {
            return $count;
        }

        return strlen($value);
    }

    public static function tail(string $value, int $length): string
    {
        $length = max(0, $length);
        if ($length === 0) {
            return '';
        }

        if (self::mbFunctionAvailable('mb_substr')) {
            /** @psalm-suppress FalsableReturnStatement */
            return mb_substr($value, -$length);
        }

        $count = preg_match_all('/./u', $value, $matches);
        if ($count !== false && $count > 0) {
            $slice = array_slice($matches[0], -$length);

            return implode('', $slice);
        }

        return substr($value, -$length);
    }

    private static function mbFunctionAvailable(string $function): bool
    {
        if (self::$mbStringAvailableOverride !== null) {
            return self::$mbStringAvailableOverride;
        }

        return function_exists($function);
    }
}
