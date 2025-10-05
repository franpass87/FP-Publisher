<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use InvalidArgumentException;

use function array_values;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;
use function trim;

final class Validation
{
    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function guard(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }

    public static function string(mixed $value, string $field, bool $allowEmpty = false): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('%s must be a string.', $field));
        }

        if (! $allowEmpty && trim($value) === '') {
            throw new InvalidArgumentException(sprintf('%s cannot be empty.', $field));
        }

        return $value;
    }

    public static function nullableString(mixed $value, string $field, bool $allowEmpty = true): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::string($value, $field, $allowEmpty);
    }

    public static function int(mixed $value, string $field): int
    {
        if (! is_int($value)) {
            throw new InvalidArgumentException(sprintf('%s must be an integer.', $field));
        }

        return $value;
    }

    public static function positiveInt(mixed $value, string $field, bool $allowZero = false): int
    {
        $int = self::int($value, $field);

        if ($allowZero && $int === 0) {
            return $int;
        }

        if ($int <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $field));
        }

        return $int;
    }

    public static function array(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException(sprintf('%s must be an array.', $field));
        }

        return $value;
    }

    /**
     * @template T
     * @param array $values
     * @param callable(mixed,int|string):T $validator
     * @return array<int|string,T>
     */
    public static function arrayOf(array $values, callable $validator, string $field): array
    {
        $validated = [];

        foreach ($values as $key => $value) {
            $validated[$key] = $validator($value, $key);
        }

        return $validated;
    }

    public static function enum(string $value, array $allowed, string $field): string
    {
        if (! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('%s must be one of: %s.', $field, implode(', ', $allowed)));
        }

        return $value;
    }

    public static function arrayOfStrings(array $values, string $field, bool $allowEmpty = false): array
    {
        return array_values(self::arrayOf(
            $values,
            static function (mixed $value) use ($field, $allowEmpty): string {
                $string = self::string($value, $field, $allowEmpty);

                $trimmed = trim($string);

                if ($trimmed === '') {
                    return '';
                }

                return $trimmed;
            },
            $field
        ));
    }
}
