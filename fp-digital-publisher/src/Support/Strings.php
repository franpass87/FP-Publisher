<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use IntlChar;

use function array_slice;
use function class_exists;
use function function_exists;
use function implode;
use function is_int;
use function max;
use function preg_match_all;
use function str_split;
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

        if (preg_match_all('/./us', $value, $matches) > 0) {
            $slice = array_slice($matches[0], $start, $length);

            return implode('', $slice);
        }

        return substr($value, $start, $length);
    }

    public static function trimWidth(string $value, int $width, string $trimMarker = '…'): string
    {
        $width = max(0, $width);

        if ($width === 0 && $trimMarker === '') {
            return '';
        }

        if (self::mbFunctionAvailable('mb_strimwidth')) {
            /** @psalm-suppress FalsableReturnStatement */
            return mb_strimwidth($value, 0, $width, $trimMarker);
        }

        if (self::width($value) <= $width) {
            return $value;
        }

        $markerWidth = max(0, self::width($trimMarker));
        if ($markerWidth === 0) {
            return self::trimToWidth($value, $width);
        }

        if ($markerWidth >= $width) {
            return $trimMarker;
        }

        $visibleWidth = $width - $markerWidth;
        $prefix = self::trimToWidth($value, $visibleWidth);

        if ($prefix === '') {
            return $trimMarker;
        }

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

        $count = preg_match_all('/./us', $value, $matches);
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

        $count = preg_match_all('/./us', $value, $matches);
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

    private static function trimToWidth(string $value, int $width): string
    {
        if ($width <= 0) {
            return '';
        }

        $currentWidth = 0;
        $result = '';

        foreach (self::splitCharacters($value) as $character) {
            $characterWidth = self::characterWidth($character);

            if ($currentWidth + $characterWidth > $width) {
                break;
            }

            $result .= $character;
            $currentWidth += $characterWidth;
        }

        return $result;
    }

    private static function width(string $value): int
    {
        $width = 0;

        foreach (self::splitCharacters($value) as $character) {
            $width += self::characterWidth($character);
        }

        return $width;
    }

    /**
     * @return string[]
     */
    private static function splitCharacters(string $value): array
    {
        if (preg_match_all('/./us', $value, $matches) > 0) {
            return $matches[0];
        }

        return str_split($value);
    }

    private static function characterWidth(string $character): int
    {
        if (class_exists(IntlChar::class)) {
            $codePoint = IntlChar::ord($character);

            if (is_int($codePoint)) {
                $eastAsianWidth = IntlChar::getIntPropertyValue($codePoint, IntlChar::PROPERTY_EAST_ASIAN_WIDTH);

                if ($eastAsianWidth === 3 || $eastAsianWidth === 5) {
                    return 2;
                }
            }
        }

        return 1;
    }
}
