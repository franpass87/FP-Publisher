<?php

declare(strict_types=1);

namespace FP\Publisher\Services;

use DateTimeInterface;
use Exception;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Logging\Logger;
use InvalidArgumentException;
use RuntimeException;
use WP_User;
use wpdb;

use function __;
use function array_unique;
use function esc_html__;
use function get_user_by;
use function is_array;
use function json_decode;
use function is_string;
use function preg_match_all;
use function sprintf;
use function strip_tags;
use function trim;
use function wp_kses_post;
use function wp_json_encode;
use function wp_mail;

final class Comments
{
    /**
     * @return array<int, array{id:int,plan_id:int,body:string,created_at:string,author:array<string,mixed>,mentions:array<int,array<string,mixed>>}>
     */
    public static function list(int $planId): array
    {
        global $wpdb;

        if ($planId <= 0) {
            throw new InvalidArgumentException(__('Invalid plan identifier.', 'fp-publisher'));
        }

        $table = $wpdb->prefix . 'fp_pub_comments';
        $users = $wpdb->users;
        $query = $wpdb->prepare(
            "SELECT c.id, c.plan_id, c.user_id, c.body, c.mentions_json, c.created_at, u.display_name, u.user_login " .
            "FROM {$table} c INNER JOIN {$users} u ON u.ID = c.user_id WHERE c.plan_id = %d ORDER BY c.created_at ASC",
            $planId
        );
        $rows = $wpdb->get_results($query, ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        $comments = [];
        foreach ($rows as $row) {
            $mentions = [];
            if (! empty($row['mentions_json'])) {
                $decoded = json_decode((string) $row['mentions_json'], true);
                if (is_array($decoded)) {
                    $mentions = $decoded;
                }
            }

            $createdAt = null;
            try {
                $createdAt = Dates::ensure((string) $row['created_at'])->format(DateTimeInterface::ATOM);
            } catch (Exception $exception) {
                Logger::get()->warning('Skipping comment with invalid creation date.', [
                    'comment_id' => (int) ($row['id'] ?? 0),
                    'plan_id' => (int) ($row['plan_id'] ?? 0),
                    'created_at' => $row['created_at'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }

            $comments[] = [
                'id' => (int) $row['id'],
                'plan_id' => (int) $row['plan_id'],
                'body' => (string) $row['body'],
                'created_at' => $createdAt,
                'author' => [
                    'id' => (int) $row['user_id'],
                    'display_name' => (string) $row['display_name'],
                    'login' => (string) $row['user_login'],
                ],
                'mentions' => $mentions,
            ];
        }

        return $comments;
    }

    /**
     * @return array{id:int,plan_id:int,body:string,created_at:string,author:array<string,mixed>,mentions:array<int,array<string,mixed>>}
     */
    public static function add(int $planId, int $userId, string $body): array
    {
        global $wpdb;

        if ($planId <= 0) {
            throw new InvalidArgumentException(__('Invalid plan identifier.', 'fp-publisher'));
        }

        if ($userId <= 0) {
            throw new RuntimeException(__('You must be logged in to comment.', 'fp-publisher'));
        }

        $body = wp_kses_post(trim($body));
        if ($body === '') {
            throw new InvalidArgumentException(__('The comment cannot be empty.', 'fp-publisher'));
        }

        $mentions = self::resolveMentions($body);
        $timestamp = Dates::now();

        $table = $wpdb->prefix . 'fp_pub_comments';
        $mentionsPayload = array_map(static function (array $mention): array {
            return [
                'id' => $mention['id'],
                'display_name' => $mention['display_name'],
                'login' => $mention['login'],
            ];
        }, $mentions);

        $mentionsJson = wp_json_encode($mentionsPayload);
        if (! is_string($mentionsJson)) {
            throw new RuntimeException(__('Unable to encode comment mentions.', 'fp-publisher'));
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'plan_id' => $planId,
                'user_id' => $userId,
                'body' => $body,
                'mentions_json' => $mentionsJson,
                'created_at' => $timestamp->format('Y-m-d H:i:s'),
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );

        if (! $inserted) {
            throw new RuntimeException(__('Unable to save the comment.', 'fp-publisher'));
        }

        $commentId = (int) $wpdb->insert_id;
        self::notifyMentions($planId, $mentions, $body);

        return [
            'id' => $commentId,
            'plan_id' => $planId,
            'body' => $body,
            'created_at' => $timestamp->format(DateTimeInterface::ATOM),
            'author' => self::authorData($userId),
            'mentions' => array_map(static function (array $mention): array {
                return [
                    'id' => $mention['id'],
                    'display_name' => $mention['display_name'],
                    'login' => $mention['login'],
                ];
            }, $mentions),
        ];
    }

    /**
     * @return array<int, array{id:int,display_name:string,login:string,email:string}>
     */
    private static function resolveMentions(string $body): array
    {
        preg_match_all('/@([A-Za-z0-9_\-.]{3,60})/', $body, $matches);
        $logins = isset($matches[1]) ? array_unique($matches[1]) : [];

        $resolved = [];
        foreach ($logins as $login) {
            $user = get_user_by('login', $login);
            if (! $user instanceof WP_User) {
                $user = get_user_by('slug', $login);
            }

            if (! $user instanceof WP_User) {
                continue;
            }

            $resolved[] = [
                'id' => (int) $user->ID,
                'display_name' => $user->display_name,
                'login' => $user->user_login,
                'email' => $user->user_email,
            ];
        }

        return $resolved;
    }

    /**
     * @param array<int, array{id:int,display_name:string,login:string,email:string}> $mentions
     */
    private static function notifyMentions(int $planId, array $mentions, string $body): void
    {
        if ($mentions === []) {
            return;
        }

        foreach ($mentions as $mention) {
            if ($mention['email'] === '') {
                continue;
            }

            $subject = esc_html__('New FP Publisher comment', 'fp-publisher');
            $message = sprintf(
                /* translators: 1: plan identifier, 2: comment body */
                __("You were mentioned on plan #%1\\$d:\\n\\n%2\\$s\\n", 'fp-publisher'),
                $planId,
                strip_tags($body)
            );

            wp_mail($mention['email'], $subject, $message);
        }
    }

    /**
     * @return array{id:int,display_name:string,login:string}
     */
    private static function authorData(int $userId): array
    {
        $user = get_user_by('id', $userId);
        if ($user instanceof WP_User) {
            return [
                'id' => (int) $user->ID,
                'display_name' => $user->display_name,
                'login' => $user->user_login,
            ];
        }

        return [
            'id' => $userId,
            'display_name' => __('User', 'fp-publisher'),
            'login' => '',
        ];
    }
}
