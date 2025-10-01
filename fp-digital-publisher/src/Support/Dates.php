<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

use function function_exists;
use function is_string;
use function wp_timezone;
use function wp_timezone_string;

final class Dates
{
    public const DEFAULT_TZ = 'UTC';

    public static function timezone(string|DateTimeZone|null $timezone = null): DateTimeZone
    {
        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }

        if ($timezone === null) {
            if (function_exists('wp_timezone')) {
                $wpTimezone = wp_timezone();

                if ($wpTimezone instanceof DateTimeZone) {
                    return $wpTimezone;
                }
            }

            if (function_exists('wp_timezone_string')) {
                $siteTimezone = wp_timezone_string();

                if (is_string($siteTimezone) && $siteTimezone !== '') {
                    return new DateTimeZone($siteTimezone);
                }
            }

            return new DateTimeZone(self::DEFAULT_TZ);
        }

        if (! is_string($timezone)) {
            throw new InvalidArgumentException('Invalid timezone provided.');
        }

        return new DateTimeZone($timezone);
    }

    public static function now(string|DateTimeZone|null $timezone = null): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::timezone($timezone));
    }

    public static function fromString(string $value, string|DateTimeZone|null $timezone = null): DateTimeImmutable
    {
        return new DateTimeImmutable($value, self::timezone($timezone));
    }

    public static function ensure(DateTimeInterface|string $value, string|DateTimeZone|null $timezone = null): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $timezone === null ? $value : $value->setTimezone(self::timezone($timezone));
        }

        if ($value instanceof DateTimeInterface) {
            return (new DateTimeImmutable($value->format(DateTimeInterface::ATOM), $value->getTimezone()))
                ->setTimezone(self::timezone($timezone));
        }

        return self::fromString($value, $timezone);
    }

    public static function toUtc(DateTimeInterface $value): DateTimeImmutable
    {
        return self::ensure($value, 'UTC');
    }

    public static function add(DateTimeInterface $value, DateInterval|string $interval): DateTimeImmutable
    {
        $date = self::ensure($value);
        $interval = is_string($interval) ? new DateInterval($interval) : $interval;

        return $date->add($interval);
    }

    public static function sub(DateTimeInterface $value, DateInterval|string $interval): DateTimeImmutable
    {
        $date = self::ensure($value);
        $interval = is_string($interval) ? new DateInterval($interval) : $interval;

        return $date->sub($interval);
    }

    public static function format(DateTimeInterface $value, string $format = DateTimeInterface::ATOM): string
    {
        return self::ensure($value)->format($format);
    }
}
