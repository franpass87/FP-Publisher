<?php

declare(strict_types=1);

namespace FP\Publisher\Infra\DB;

use wpdb;

use function dbDelta;
use function delete_option;
use function get_option;
use function implode;
use function update_option;

final class Migrations
{
    private const OPTION_KEY = 'fp_publisher_db_version';
    private const VERSION = '2024041501';

    public static function install(): void
    {
        self::migrate();
    }

    public static function maybeUpgrade(): void
    {
        $installed = get_option(self::OPTION_KEY);

        if ($installed === self::VERSION) {
            return;
        }

        self::migrate();
    }

    public static function uninstall(): void
    {
        global $wpdb;

        foreach (self::tableNames($wpdb) as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option(self::OPTION_KEY);
    }

    private static function migrate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        $statements = [
            "CREATE TABLE {$prefix}fp_pub_jobs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                status VARCHAR(32) NOT NULL,
                channel VARCHAR(64) NOT NULL,
                payload_json LONGTEXT NULL,
                run_at DATETIME NOT NULL,
                attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                error TEXT NULL,
                idempotency_key VARCHAR(191) NOT NULL,
                remote_id VARCHAR(191) DEFAULT '',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                child_job_id BIGINT UNSIGNED DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY idempotency_channel (channel, idempotency_key),
                KEY status (status),
                KEY run_at (run_at),
                KEY child_job (child_job_id)
            ) {$charsetCollate};",
            "CREATE TABLE {$prefix}fp_pub_assets (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                source VARCHAR(64) NOT NULL,
                ref VARCHAR(191) NOT NULL,
                mime VARCHAR(191) NOT NULL,
                bytes BIGINT UNSIGNED DEFAULT 0,
                temp_until DATETIME NULL,
                PRIMARY KEY  (id),
                KEY source_ref (source, ref),
                KEY temp_until (temp_until)
            ) {$charsetCollate};",
            "CREATE TABLE {$prefix}fp_pub_plans (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                brand VARCHAR(191) NOT NULL,
                channel_set_json LONGTEXT NOT NULL,
                slots_json LONGTEXT NOT NULL,
                owner BIGINT UNSIGNED NOT NULL,
                status ENUM('draft','ready','approved','scheduled','published','failed') NOT NULL DEFAULT 'draft',
                approvals_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY brand (brand),
                KEY owner (owner),
                KEY status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$prefix}fp_pub_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                service VARCHAR(64) NOT NULL,
                account_id VARCHAR(191) NOT NULL,
                token_enc LONGTEXT NOT NULL,
                expires_at DATETIME NULL,
                scopes TEXT NULL,
                PRIMARY KEY  (id),
                KEY service_account (service, account_id),
                KEY expires_at (expires_at)
            ) {$charsetCollate};",
            "CREATE TABLE {$prefix}fp_pub_comments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                plan_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                body TEXT NOT NULL,
                mentions_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY plan (plan_id),
                KEY user (user_id)
            ) {$charsetCollate};",
            "CREATE TABLE {$prefix}fp_pub_links (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                slug VARCHAR(80) NOT NULL,
                target_url TEXT NOT NULL,
                utm_json LONGTEXT NULL,
                clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
                last_click_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY slug (slug),
                KEY clicks (clicks)
            ) {$charsetCollate};"
        ];

        dbDelta(implode("\n", $statements));

        update_option(self::OPTION_KEY, self::VERSION);
    }

    /**
     * @return list<string>
     */
    private static function tableNames(wpdb $wpdb): array
    {
        $prefix = $wpdb->prefix;

        return [
            "{$prefix}fp_pub_jobs",
            "{$prefix}fp_pub_assets",
            "{$prefix}fp_pub_plans",
            "{$prefix}fp_pub_tokens",
            "{$prefix}fp_pub_comments",
            "{$prefix}fp_pub_links",
        ];
    }
}
