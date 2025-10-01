<?php

declare(strict_types=1);

namespace FP\Publisher\Services;

use DateTimeImmutable;
use DateTimeZone;
use FP\Publisher\Infra\Options;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Support\Channels;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Logging\Logger;
use Throwable;

use function array_map;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function wp_strip_all_tags;

final class Scheduler
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getRunnableJobs(DateTimeImmutable $now, ?int $limit = null): array
    {
        $maxConcurrent = (int) Options::get('queue.max_concurrent', 5);
        $limit = $limit !== null ? max(1, min($limit, $maxConcurrent)) : max(1, $maxConcurrent);

        $runningChannels = Queue::runningChannels();
        $blackouts = self::blackoutWindows();

        $candidates = Queue::dueJobs($now, $limit * 5);
        $runnable = [];

        foreach ($candidates as $candidate) {
            if (count($runnable) >= $limit) {
                break;
            }

            $channel = Channels::normalize((string) $candidate['channel']);
            if ($channel === '') {
                continue;
            }
            if (($runningChannels[$channel] ?? 0) > 0) {
                continue;
            }

            if (self::inBlackout($candidate, $now, $blackouts)) {
                continue;
            }

            $claimed = Queue::claim($candidate, $now);
            if ($claimed === null) {
                continue;
            }

            $runnable[] = $claimed;
            $runningChannels[$channel] = ($runningChannels[$channel] ?? 0) + 1;
        }

        return $runnable;
    }

    /**
     * Quick evaluation helper for API testing.
     *
     * @return array{runnable: bool, in_blackout: bool, has_collision: bool}
     */
    public static function evaluate(string $channel, DateTimeImmutable $runAt): array
    {
        $channel = Channels::normalize($channel);
        if ($channel === '') {
            return [
                'runnable' => false,
                'in_blackout' => false,
                'has_collision' => false,
            ];
        }
        $blackouts = self::blackoutWindows();
        $hasCollision = (Queue::runningChannels()[$channel] ?? 0) > 0;
        $inBlackout = self::timeInBlackout($runAt, $blackouts, $channel);

        return [
            'runnable' => ! $inBlackout && ! $hasCollision,
            'in_blackout' => $inBlackout,
            'has_collision' => $hasCollision,
        ];
    }

    /**
     * @param array<string, mixed> $job
     * @param array<int, array<string, mixed>> $blackouts
     */
    private static function inBlackout(array $job, DateTimeImmutable $now, array $blackouts): bool
    {
        $channel = Channels::normalize((string) ($job['channel'] ?? ''));
        if ($channel === '') {
            return false;
        }

        return self::timeInBlackout($now, $blackouts, $channel);
    }

    /**
     * @param array<int, array<string, mixed>> $blackouts
     */
    private static function timeInBlackout(DateTimeImmutable $at, array $blackouts, ?string $channel = null): bool
    {
        foreach ($blackouts as $window) {
            if (! is_array($window)) {
                continue;
            }

            $windowChannel = isset($window['channel']) ? Channels::normalize((string) $window['channel']) : null;
            if ($windowChannel !== null && $windowChannel !== '' && $windowChannel !== $channel) {
                continue;
            }

            $timezone = isset($window['timezone']) && is_string($window['timezone']) && $window['timezone'] !== ''
                ? $window['timezone']
                : (string) Options::get('timezone', Dates::timezone()->getName());

            try {
                $zone = new DateTimeZone($timezone);
            } catch (Throwable $exception) {
                Logger::get()->warning('Skipping blackout window with invalid timezone.', [
                    'timezone' => $timezone,
                    'channel' => $windowChannel,
                    'error' => wp_strip_all_tags($exception->getMessage()),
                ]);

                continue;
            }

            $localized = $at->setTimezone($zone);
            $day = (int) $localized->format('w');

            $days = [];
            if (isset($window['days']) && is_array($window['days'])) {
                $days = array_map(static fn ($value): int => (int) $value, $window['days']);
            }

            if ($days !== [] && ! in_array($day, $days, true)) {
                continue;
            }

            $start = isset($window['start']) && is_string($window['start']) ? $window['start'] : '';
            $end = isset($window['end']) && is_string($window['end']) ? $window['end'] : '';

            if ($start === '' || $end === '') {
                continue;
            }

            $time = $localized->format('H:i');
            if (self::timeInRange($time, $start, $end)) {
                return true;
            }
        }

        return false;
    }

    private static function timeInRange(string $current, string $start, string $end): bool
    {
        if ($start === $end) {
            return true;
        }

        if ($start <= $end) {
            return $current >= $start && $current < $end;
        }

        return $current >= $start || $current < $end;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function blackoutWindows(): array
    {
        $windows = Options::get('queue.blackout_windows', []);

        if (! is_array($windows)) {
            return [];
        }

        return array_map(static function ($window): array {
            if (! is_array($window)) {
                return [];
            }

            $normalized = [
                'channel' => isset($window['channel']) && is_string($window['channel']) ? Channels::normalize($window['channel']) : null,
                'start' => isset($window['start']) && is_string($window['start']) ? $window['start'] : '',
                'end' => isset($window['end']) && is_string($window['end']) ? $window['end'] : '',
                'timezone' => isset($window['timezone']) && is_string($window['timezone']) ? $window['timezone'] : null,
                'days' => [],
            ];

            if (isset($window['days']) && is_array($window['days'])) {
                foreach ($window['days'] as $day) {
                    $normalized['days'][] = is_int($day) ? $day : (int) $day;
                }
            }

            return $normalized;
        }, $windows);
    }
}

