<?php

declare(strict_types=1);

namespace FP\Publisher\Infra;

use WP_Role;

use function __;
use function add_action;
use function add_role;
use function apply_filters;
use function current_user_can;
use function get_role;

final class Capabilities
{
    public const ROLE_ADMIN = 'fp_publisher_admin';
    public const ROLE_EDITOR = 'fp_publisher_editor';

    private const CAPABILITIES = [
        'fp_publisher_manage_settings',
        'fp_publisher_manage_accounts',
        'fp_publisher_manage_plans',
        'fp_publisher_comment_plans',
        'fp_publisher_approve_plans',
        'fp_publisher_schedule_plans',
        'fp_publisher_manage_templates',
        'fp_publisher_manage_alerts',
        'fp_publisher_manage_links',
        'fp_publisher_view_logs',
    ];

    public static function register(): void
    {
        // Ensure roles are synced early and on every init
        add_action('init', [self::class, 'ensureRoles'], 1);
        
        // Also sync immediately when register is called
        // This ensures capabilities are available when the plugin loads
        if (did_action('init')) {
            self::ensureRoles();
        }
    }

    public static function activate(): void
    {
        self::ensureRoles();
    }

    public static function ensureRoles(): void
    {
        $adminCaps = self::getRoleCapabilities(self::ROLE_ADMIN);
        $editorCaps = self::getRoleCapabilities(self::ROLE_EDITOR);

        add_role(
            self::ROLE_ADMIN,
            __('FP Publisher Admin', 'fp-publisher'),
            $adminCaps
        );

        add_role(
            self::ROLE_EDITOR,
            __('FP Publisher Editor', 'fp-publisher'),
            $editorCaps
        );

        // Always sync core roles to ensure capabilities are up-to-date
        // This is crucial for administrators to see the admin menu
        self::syncCoreRole('administrator', $adminCaps);
        self::syncCoreRole('editor', $editorCaps);
    }

    private static function getRoleCapabilities(string $role): array
    {
        $caps = match ($role) {
            self::ROLE_ADMIN => self::CAPABILITIES,
            self::ROLE_EDITOR => [
                'fp_publisher_manage_accounts',
                'fp_publisher_manage_plans',
                'fp_publisher_comment_plans',
                'fp_publisher_manage_templates',
                'fp_publisher_manage_alerts',
                'fp_publisher_manage_links',
                'fp_publisher_view_logs',
            ],
            default => [],
        };

        $capabilities = ['read' => true];
        foreach ($caps as $cap) {
            $capabilities[$cap] = true;
        }

        return apply_filters('fp_publisher_role_capabilities', $capabilities, $role);
    }

    private static function syncCoreRole(string $coreRole, array $capabilities): void
    {
        $role = get_role($coreRole);
        if (! $role instanceof WP_Role) {
            return;
        }

        foreach ($capabilities as $capability => $granted) {
            if ($granted) {
                $role->add_cap($capability);
            }
        }
    }

    public static function userCan(string $capability): bool
    {
        return current_user_can($capability) || current_user_can('manage_options');
    }
}
