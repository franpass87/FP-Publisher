<?php

declare(strict_types=1);

namespace FP\Publisher\Services;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use FP\Publisher\Support\Dates;
use InvalidArgumentException;

use function __;
use function array_values;
use function explode;
use function get_transient;
use function is_array;
use function preg_match;
use function sprintf;
use function strtolower;
use function trim;
use function set_transient;

final class BestTime
{
    private const CACHE_KEY = 'fp_pub_besttime_cache';

    /**
     * @var array<string, array<string, array<int, array{time: string, score: int, reason: string}>>>|
     *      array<string, array<int, array{time: string, score: int, reason: string}>>
     */
    private const RULES = [
        'facebook' => [
            '1' => [
                ['time' => '09:30', 'score' => 82, 'reason' => 'High engagement after the weekend'],
                ['time' => '14:00', 'score' => 75, 'reason' => 'Lunch break audience spike'],
            ],
            '3' => [
                ['time' => '11:00', 'score' => 78, 'reason' => 'Midweek discovery window'],
            ],
            'default' => [
                ['time' => '10:30', 'score' => 70, 'reason' => 'Channel average performance'],
            ],
        ],
        'instagram' => [
            '2' => [
                ['time' => '20:30', 'score' => 84, 'reason' => 'Evening prime time'],
            ],
            '4' => [
                ['time' => '19:15', 'score' => 80, 'reason' => 'Commuter scroll window'],
            ],
            'default' => [
                ['time' => '18:45', 'score' => 74, 'reason' => 'Evening scroll habits'],
            ],
        ],
        'tiktok' => [
            '5' => [
                ['time' => '21:00', 'score' => 88, 'reason' => 'Entertainment prime time'],
            ],
            '6' => [
                ['time' => '22:00', 'score' => 85, 'reason' => 'Weekend binge window'],
            ],
            'default' => [
                ['time' => '20:00', 'score' => 78, 'reason' => 'Evening content snack'],
            ],
        ],
        'youtube' => [
            '6' => [
                ['time' => '10:00', 'score' => 83, 'reason' => 'Weekend morning binge'],
            ],
            '7' => [
                ['time' => '09:30', 'score' => 81, 'reason' => 'Weekend launch window'],
            ],
            'default' => [
                ['time' => '17:30', 'score' => 72, 'reason' => 'Post-work audience window'],
            ],
        ],
        'google_business' => [
            '1' => [
                ['time' => '08:30', 'score' => 77, 'reason' => 'Office opening hours'],
            ],
            '3' => [
                ['time' => '09:15', 'score' => 76, 'reason' => 'Service research peak'],
            ],
            'default' => [
                ['time' => '10:00', 'score' => 70, 'reason' => 'Business hours average'],
            ],
        ],
        'wordpress' => [
            '2' => [
                ['time' => '07:30', 'score' => 79, 'reason' => 'Newsletter distribution window'],
            ],
            'default' => [
                ['time' => '11:30', 'score' => 71, 'reason' => 'Editorial update window'],
            ],
        ],
    ];

    /**
     * @return array<int, array{datetime: string, score: int, reason: string}>
     */
    public static function getSuggestions(string $brand, string $channel, string $month): array
    {
        $brand = trim($brand);
        $channel = strtolower(trim($channel));
        $month = trim($month);

        if ($brand === '' || $channel === '') {
            throw new InvalidArgumentException(__('Brand and channel are required.', 'fp-publisher'));
        }

        if ($month === '') {
            $month = Dates::now()->format('Y-m');
        }

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new InvalidArgumentException(__('The month must use the YYYY-MM format.', 'fp-publisher'));
        }

        $cacheKey = sprintf('%s|%s|%s', strtolower($brand), $channel, $month);
        $cache = get_transient(self::CACHE_KEY);
        if (! is_array($cache)) {
            $cache = [];
        }

        if (isset($cache[$cacheKey]) && is_array($cache[$cacheKey])) {
            return array_values($cache[$cacheKey]);
        }

        $timezone = Dates::timezone();
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01', $timezone);
        if (! $start instanceof DateTimeImmutable) {
            throw new InvalidArgumentException(__('Unable to determine the requested month.', 'fp-publisher'));
        }

        $end = $start->modify('last day of this month')->setTime(23, 59, 59);
        $period = new DatePeriod($start, new DateInterval('P1D'), $end->add(new DateInterval('PT1S')));

        $rules = self::RULES[$channel] ?? ['default' => [['time' => '10:00', 'score' => 65, 'reason' => 'Default schedule']]];
        $suggestions = [];

        foreach ($period as $day) {
            $weekday = $day->format('N');
            $slots = $rules[$weekday] ?? ($rules['default'] ?? []);

            foreach ($slots as $slot) {
                [$hour, $minute] = explode(':', $slot['time']);
                $datetime = $day->setTime((int) $hour, (int) $minute);

                $suggestions[] = [
                    'datetime' => $datetime->format(DateTimeInterface::ATOM),
                    'score' => $slot['score'],
                    'reason' => __($slot['reason'], 'fp-publisher'),
                ];
            }
        }

        $cache[$cacheKey] = $suggestions;
        set_transient(self::CACHE_KEY, $cache, 30 * DAY_IN_SECONDS);

        return $suggestions;
    }
}
