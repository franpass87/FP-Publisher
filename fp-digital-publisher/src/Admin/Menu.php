<?php

declare(strict_types=1);

namespace FP\Publisher\Admin;

use FP\Publisher\Infra\Capabilities;

use function add_action;
use function add_menu_page;
use function add_submenu_page;
use function esc_html__;
use function esc_html_e;
use function remove_submenu_page;
use function wp_die;

final class Menu
{
    private const MENU_SLUG = 'fp-publisher';

    /**
     * Register the admin menu hooks.
     */
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addMenu']);
        add_action('network_admin_menu', [self::class, 'addMenu']);
    }

    public static function addMenu(): void
    {
        add_menu_page(
            esc_html__('FP Publisher', 'fp_publisher'),
            esc_html__('FP Publisher', 'fp_publisher'),
            'fp_publisher_manage_plans',
            self::MENU_SLUG,
            [self::class, 'renderApp'],
            'dashicons-megaphone',
            58
        );

        self::addSubmenu(
            esc_html__('Accounts', 'fp_publisher'),
            'fp_publisher_manage_accounts',
            'accounts'
        );
        self::addSubmenu(
            esc_html__('Calendario', 'fp_publisher'),
            'fp_publisher_manage_plans',
            'calendar'
        );
        self::addSubmenu(
            esc_html__('Template', 'fp_publisher'),
            'fp_publisher_manage_templates',
            'templates'
        );
        self::addSubmenu(
            esc_html__('Avvisi', 'fp_publisher'),
            'fp_publisher_manage_alerts',
            'alerts'
        );
        self::addSubmenu(
            esc_html__('Impostazioni', 'fp_publisher'),
            'fp_publisher_manage_settings',
            'settings'
        );
        self::addSubmenu(
            esc_html__('Log', 'fp_publisher'),
            'fp_publisher_view_logs',
            'logs'
        );

        remove_submenu_page(self::MENU_SLUG, self::MENU_SLUG);
    }

    private static function addSubmenu(string $label, string $capability, string $slugSuffix): void
    {
        add_submenu_page(
            self::MENU_SLUG,
            $label,
            $label,
            $capability,
            self::MENU_SLUG . '-' . $slugSuffix,
            [self::class, 'renderApp']
        );
    }

    public static function renderApp(): void
    {
        if (! Capabilities::userCan('fp_publisher_manage_plans')) {
            wp_die(esc_html__('Non hai i permessi necessari per accedere a FP Digital Publisher.', 'fp_publisher'));
        }

        echo '<div class="wrap fp-publisher-admin"><div id="fp-publisher-admin-app" class="fp-publisher-admin__mount">';
        esc_html_e('Caricamento applicazione…', 'fp_publisher');
        echo '</div></div>';
    }
}
