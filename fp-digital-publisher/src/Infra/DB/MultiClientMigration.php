<?php

declare(strict_types=1);

namespace FP\Publisher\Infra\DB;

use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Logging\Logger;

use function dbDelta;

final class MultiClientMigration
{
    public static function install(): void
    {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Table: fp_clients
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fp_clients (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            slug VARCHAR(200) NOT NULL UNIQUE,
            logo_url VARCHAR(500),
            website VARCHAR(500),
            industry VARCHAR(100),
            timezone VARCHAR(50) DEFAULT 'UTC',
            color VARCHAR(7) DEFAULT '#666666',
            status VARCHAR(20) DEFAULT 'active',
            billing_plan VARCHAR(20) DEFAULT 'free',
            billing_cycle_start DATE,
            billing_cycle_end DATE,
            meta LONGTEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_status (status),
            INDEX idx_slug (slug),
            INDEX idx_billing_plan (billing_plan)
        ) $charset;";

        dbDelta($sql);

        // Table: fp_client_accounts
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fp_client_accounts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            channel VARCHAR(50) NOT NULL,
            account_identifier VARCHAR(200) NOT NULL,
            account_name VARCHAR(200),
            account_avatar VARCHAR(500),
            status VARCHAR(20) DEFAULT 'active',
            connected_at DATETIME NOT NULL,
            last_synced_at DATETIME,
            tokens LONGTEXT,
            meta LONGTEXT,
            INDEX idx_client_channel (client_id, channel),
            INDEX idx_status (status),
            INDEX idx_account_identifier (account_identifier)
        ) $charset;";

        dbDelta($sql);

        // Table: fp_client_members
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fp_client_members (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL,
            invited_by BIGINT UNSIGNED,
            invited_at DATETIME NOT NULL,
            accepted_at DATETIME,
            status VARCHAR(20) DEFAULT 'pending',
            permissions LONGTEXT,
            UNIQUE KEY unique_client_user (client_id, user_id),
            INDEX idx_user (user_id),
            INDEX idx_status (status),
            INDEX idx_role (role)
        ) $charset;";

        dbDelta($sql);

        // Table: fp_plans (persistent PostPlan storage)
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fp_plans (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            brand VARCHAR(200) NOT NULL,
            status VARCHAR(50) DEFAULT 'draft',
            plan_data LONGTEXT NOT NULL,
            created_by BIGINT UNSIGNED,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_client_status (client_id, status),
            INDEX idx_created_by (created_by)
        ) $charset;";

        dbDelta($sql);

        // Table: fp_client_analytics
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fp_client_analytics (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            channel VARCHAR(50) NOT NULL,
            date DATE NOT NULL,
            posts_published INT DEFAULT 0,
            reach INT DEFAULT 0,
            impressions INT DEFAULT 0,
            engagement INT DEFAULT 0,
            clicks INT DEFAULT 0,
            followers_gained INT DEFAULT 0,
            metrics LONGTEXT,
            UNIQUE KEY unique_client_channel_date (client_id, channel, date),
            INDEX idx_date (date),
            INDEX idx_client_id (client_id)
        ) $charset;";

        dbDelta($sql);

        // Alter existing jobs table to add client_id
        $existingColumns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}fp_jobs LIKE 'client_id'");
        
        if (empty($existingColumns)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}fp_jobs ADD COLUMN client_id BIGINT UNSIGNED AFTER id");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}fp_jobs ADD INDEX idx_client_status (client_id, status)");
            
            Logger::get()->info('Added client_id column to fp_jobs table');
        }

        Logger::get()->info('Multi-client tables created/updated successfully');
    }

    public static function createDefaultClient(): ?int
    {
        global $wpdb;

        // Check if any clients exist
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fp_clients");
        
        if ($count > 0) {
            return null;
        }

        // Create default client
        $now = Dates::now('UTC')->format('Y-m-d H:i:s');
        
        $wpdb->insert(
            $wpdb->prefix . 'fp_clients',
            [
                'name' => 'Default Client',
                'slug' => 'default-client',
                'status' => 'active',
                'billing_plan' => 'free',
                'timezone' => 'UTC',
                'color' => '#666666',
                'meta' => '{}',
                'created_at' => $now,
                'updated_at' => $now
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        $clientId = (int) $wpdb->insert_id;

        // Add current user as owner
        $currentUserId = get_current_user_id();
        if ($currentUserId > 0) {
            $wpdb->insert(
                $wpdb->prefix . 'fp_client_members',
                [
                    'client_id' => $clientId,
                    'user_id' => $currentUserId,
                    'role' => 'owner',
                    'invited_by' => $currentUserId,
                    'invited_at' => $now,
                    'accepted_at' => $now,
                    'status' => 'active',
                    'permissions' => '{}'
                ],
                ['%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s']
            );
        }

        Logger::get()->info('Default client created', ['client_id' => $clientId]);

        return $clientId;
    }

    private static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'fp_clients';
    }
}
