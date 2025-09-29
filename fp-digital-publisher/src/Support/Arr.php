<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use function array_key_exists;
use function array_values;
use function count;
use function explode;
use function is_array;
use function is_int;
use function is_string;
use function trim;

final class Arr
{
    public static function get(array $data, string|int|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $data;
        }

        if (is_int($key) && array_key_exists($key, $data)) {
            return $data[$key];
        }

        if (! is_string($key)) {
            return $default;
        }

        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        return self::dotGet($data, $key, $default);
    }

    public static function dotGet(array $data, string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $data;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function dotSet(array &$data, string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref =& $data;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $ref[$segment] = $value;
                return;
            }

            if (! isset($ref[$segment]) || ! is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref =& $ref[$segment];
        }
    }

    /**
     * @param array<int|string, mixed> $data
     * @param array<int, string> $keys
     * @return array<int|string, mixed>
     */
    public static function only(array $data, array $keys): array
    {
        $filtered = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $filtered[$key] = $data[$key];
            }
        }

        return $filtered;
    }

    /**
     * @param array<int|string, mixed> $data
     * @param callable(mixed, int|string): mixed $callback
     * @return array<int|string, mixed>
     */
    public static function map(array $data, callable $callback): array
    {
        $mapped = [];

        foreach ($data as $key => $value) {
            $mapped[$key] = $callback($value, $key);
        }

        return $mapped;
    }

    /**
     * @param array<int|string, array<string, mixed>> $data
     * @return array<int|string, mixed>
     */
    public static function pluck(array $data, string $field): array
    {
        return self::map(
            $data,
            static fn (array $item): mixed => self::get($item, $field)
        );
    }

    public static function filterStringList(array $values): array
    {
        $filtered = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            $filtered[] = $trimmed;
        }

        return array_values($filtered);
    }
}
