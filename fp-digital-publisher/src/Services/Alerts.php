<?php

declare(strict_types=1);

namespace FP\Publisher\Services;

use DateInterval;
use DateTimeInterface;
use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Infra\Options;
use FP\Publisher\Support\Dates;
use function __;
use function absint;
use function add_action;
use function add_filter;
use function array_replace_recursive;
use function array_unique;
use function esc_html__;
use function file_exists;
use function get_option;
use function is_array;
use function is_email;
use function is_scalar;
use function json_decode;
use function ob_get_clean;
use function ob_start;
use function sanitize_key;
use function sanitize_text_field;
use function substr;
use function time;
use function update_option;
use function wp_mail;
use function wp_next_scheduled;
use function wp_schedule_event;
use function wp_strip_all_tags;

final class Alerts
{
    private const OPTION_STATE = 'fp_publisher_alerts_state';
    public const DAILY_EVENT = 'fp_pub_alerts_tick';
    public const WEEKLY_EVENT = 'fp_pub_weekly_gap_check';

    public static function register(): void
    {
        add_filter('cron_schedules', [self::class, 'registerSchedules']);
        add_action('init', [self::class, 'ensureSchedules']);
        add_action(self::DAILY_EVENT, [self::class, 'runDaily']);
        add_action(self::WEEKLY_EVENT, [self::class, 'runWeekly']);
    }

    /**
     * @param array<string, array<string, mixed>> $schedules
     *
     * @return array<string, array<string, mixed>>
     */
    public static function registerSchedules(array $schedules): array
    {
        $schedules['fp_pub_daily'] = [
            'interval' => DAY_IN_SECONDS,
            'display' => __('FP Publisher (quotidiano)', 'fp_publisher'),
        ];

        $schedules['fp_pub_weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display' => __('FP Publisher (settimanale)', 'fp_publisher'),
        ];

        return $schedules;
    }

    public static function ensureSchedules(): void
    {
        if (wp_next_scheduled(self::DAILY_EVENT) === false) {
            wp_schedule_event(time() + 300, 'fp_pub_daily', self::DAILY_EVENT);
        }

        if (wp_next_scheduled(self::WEEKLY_EVENT) === false) {
            wp_schedule_event(time() + 600, 'fp_pub_weekly', self::WEEKLY_EVENT);
        }
    }

    public static function runDaily(): void
    {
        $now = Dates::now();
        $tokens = self::collectExpiringTokens();
        $failed = self::collectFailedJobs();

        self::persistState([
            'daily' => [
                'updated_at' => $now->format(DateTimeInterface::ATOM),
                'token_expiring' => $tokens,
                'failed_jobs' => $failed,
            ],
        ]);

        self::dispatchTokenAlert($tokens);
        self::dispatchFailedJobsAlert($failed);
    }

    public static function runWeekly(): void
    {
        $now = Dates::now();
        $gaps = self::collectWeeklyGaps();

        self::persistState([
            'weekly' => [
                'updated_at' => $now->format(DateTimeInterface::ATOM),
                'gaps' => $gaps,
            ],
        ]);

        self::dispatchWeeklyAlert($gaps);
    }

    public static function getState(): array
    {
        $state = get_option(self::OPTION_STATE, []);
        if (! is_array($state)) {
            $state = [];
        }

        $daily = is_array($state['daily'] ?? null) ? $state['daily'] : [];
        $weekly = is_array($state['weekly'] ?? null) ? $state['weekly'] : [];

        return [
            'daily' => [
                'updated_at' => isset($daily['updated_at']) ? (string) $daily['updated_at'] : null,
                'token_expiring' => is_array($daily['token_expiring'] ?? null) ? $daily['token_expiring'] : [],
                'failed_jobs' => is_array($daily['failed_jobs'] ?? null) ? $daily['failed_jobs'] : [],
            ],
            'weekly' => [
                'updated_at' => isset($weekly['updated_at']) ? (string) $weekly['updated_at'] : null,
                'gaps' => is_array($weekly['gaps'] ?? null) ? $weekly['gaps'] : [],
            ],
        ];
    }

    private static function dispatchTokenAlert(array $tokens): void
    {
        if ($tokens === []) {
            return;
        }

        $recipients = self::recipients();
        if ($recipients === []) {
            return;
        }

        $subject = esc_html__('Token in scadenza per FP Publisher', 'fp_publisher');
        $body = self::renderTemplate('token-expiring.php', ['tokens' => $tokens]);
        if ($body === '') {
            return;
        }

        foreach ($recipients as $recipient) {
            wp_mail($recipient, $subject, $body);
        }
    }

    private static function dispatchFailedJobsAlert(array $failed): void
    {
        if ($failed === []) {
            return;
        }

        $recipients = self::recipients();
        if ($recipients === []) {
            return;
        }

        $subject = esc_html__('Job di pubblicazione falliti', 'fp_publisher');
        $body = self::renderTemplate('failed-jobs.php', ['jobs' => $failed]);
        if ($body === '') {
            return;
        }

        foreach ($recipients as $recipient) {
            wp_mail($recipient, $subject, $body);
        }
    }

    private static function dispatchWeeklyAlert(array $gaps): void
    {
        if ($gaps === []) {
            return;
        }

        $recipients = self::recipients();
        if ($recipients === []) {
            return;
        }

        $subject = esc_html__('Pianificazioni mancanti per la prossima settimana', 'fp_publisher');
        $body = self::renderTemplate('weekly-gaps.php', ['gaps' => $gaps]);
        if ($body === '') {
            return;
        }

        foreach ($recipients as $recipient) {
            wp_mail($recipient, $subject, $body);
        }
    }

    private static function persistState(array $partial): void
    {
        $state = self::getState();
        $merged = array_replace_recursive($state, $partial);
        update_option(self::OPTION_STATE, $merged, false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function collectExpiringTokens(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'fp_pub_tokens';
        $now = Dates::now('UTC');
        $threshold = $now->add(new DateInterval('P7D'));

        $query = $wpdb->prepare(
            "SELECT service, account_id, expires_at FROM {$table} WHERE expires_at IS NOT NULL AND expires_at <= %s",
            $threshold->format('Y-m-d H:i:s')
        );

        /** @var array<int, array<string, mixed>>|null $rows */
        $rows = $wpdb->get_results($query, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        $tokens = [];
        foreach ($rows as $row) {
            $expiresAt = (string) ($row['expires_at'] ?? '');
            if ($expiresAt === '') {
                continue;
            }

            $expiry = Dates::fromString($expiresAt, 'UTC');
            if ($expiry < $now) {
                $days = 0;
            } else {
                $days = (int) $expiry->diff($now)->format('%a');
            }

            $tokens[] = [
                'service' => sanitize_key((string) ($row['service'] ?? '')),
                'account_id' => sanitize_text_field((string) ($row['account_id'] ?? '')),
                'expires_at' => $expiry->setTimezone(Dates::timezone())->format(DateTimeInterface::ATOM),
                'days_left' => max(0, $days),
            ];
        }

        return $tokens;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function collectFailedJobs(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'fp_pub_jobs';
        $now = Dates::now('UTC');
        $threshold = $now->sub(new DateInterval('P7D'));

        $query = $wpdb->prepare(
            "SELECT id, channel, run_at, attempts, error FROM {$table} WHERE status = %s AND updated_at >= %s",
            Queue::STATUS_FAILED,
            $threshold->format('Y-m-d H:i:s')
        );

        /** @var array<int, array<string, mixed>>|null $rows */
        $rows = $wpdb->get_results($query, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        $failed = [];
        foreach ($rows as $row) {
            $failed[] = [
                'id' => absint($row['id'] ?? 0),
                'channel' => sanitize_key((string) ($row['channel'] ?? '')),
                'run_at' => Dates::fromString((string) ($row['run_at'] ?? ''), 'UTC')->setTimezone(Dates::timezone())->format(DateTimeInterface::ATOM),
                'attempts' => absint($row['attempts'] ?? 0),
                'error' => substr(wp_strip_all_tags((string) ($row['error'] ?? '')), 0, 500),
            ];
        }

        return $failed;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function collectWeeklyGaps(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'fp_pub_plans';
        $now = Dates::now();
        $end = $now->add(new DateInterval('P7D'));
        $statuses = [
            PostPlan::STATUS_READY,
            PostPlan::STATUS_APPROVED,
            PostPlan::STATUS_SCHEDULED,
            PostPlan::STATUS_PUBLISHED,
        ];

        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $query = $wpdb->prepare(
            "SELECT brand, channel_set_json, slots_json FROM {$table} WHERE status IN ({$placeholders})",
            ...$statuses
        );

        /** @var array<int, array<string, mixed>>|null $rows */
        $rows = $wpdb->get_results($query, ARRAY_A);
        $brands = Options::get('brands', []);
        $channels = Options::get('channels', []);
        $scheduled = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $brand = sanitize_text_field((string) ($row['brand'] ?? ''));
                $slotsJson = (string) ($row['slots_json'] ?? '[]');
                $slots = json_decode($slotsJson, true);
                if (! is_array($slots)) {
                    continue;
                }

                foreach ($slots as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $channel = sanitize_key((string) ($slot['channel'] ?? ''));
                    $scheduledAt = isset($slot['scheduled_at']) ? (string) $slot['scheduled_at'] : '';
                    if ($channel === '' || $scheduledAt === '') {
                        continue;
                    }

                    $date = Dates::ensure($scheduledAt);
                    if ($date < $now || $date > $end) {
                        continue;
                    }

                    $key = $brand . '|' . $channel;
                    $scheduled[$key] ??= [];
                    $scheduled[$key][] = $date->format('Y-m-d');
                }
            }
        }

        $gaps = [];
        foreach ((array) $brands as $brand) {
            $brandName = sanitize_text_field((string) $brand);
            if ($brandName === '') {
                continue;
            }

            foreach ((array) $channels as $channel) {
                $channelKey = sanitize_key((string) $channel);
                if ($channelKey === '') {
                    continue;
                }

                $key = $brandName . '|' . $channelKey;
                if (! isset($scheduled[$key]) || $scheduled[$key] === []) {
                    $gaps[] = [
                        'brand' => $brandName,
                        'channel' => $channelKey,
                        'week_start' => $now->format('Y-m-d'),
                        'week_end' => $end->format('Y-m-d'),
                    ];
                }
            }
        }

        return $gaps;
    }

    /**
     * @return list<string>
     */
    private static function recipients(): array
    {
        $emails = Options::get('alert_emails', []);
        if (! is_array($emails)) {
            return [];
        }

        $recipients = [];
        foreach ($emails as $email) {
            $value = is_scalar($email) ? sanitize_text_field((string) $email) : '';
            if ($value === '' || ! is_email($value)) {
                continue;
            }

            $recipients[] = $value;
        }

        return array_values(array_unique($recipients));
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function renderTemplate(string $template, array $context): string
    {
        $path = FP_PUBLISHER_PATH . 'templates/' . $template;
        if (! file_exists($path)) {
            return '';
        }

        ob_start();
        /** @psalm-suppress UnresolvableInclude */
        include $path;

        return (string) ob_get_clean();
    }
}
