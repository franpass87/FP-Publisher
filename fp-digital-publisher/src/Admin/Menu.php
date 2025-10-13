<?php

declare(strict_types=1);

namespace FP\Publisher\Admin;

use function add_action;
use function add_menu_page;
use function add_submenu_page;

final class Menu
{
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addMenuPages']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function addMenuPages(): void
    {
        // Main menu
        add_menu_page(
            'FP Publisher',                    // page_title
            'FP Publisher',                    // menu_title
            'fp_publisher_manage_plans',      // capability
            'fp-publisher',                    // menu_slug
            [self::class, 'renderApp'],        // callback
            'dashicons-megaphone',             // icon
            30                                 // position
        );

        // Dashboard submenu (same as main)
        add_submenu_page(
            'fp-publisher',
            'Dashboard',
            'Dashboard',
            'fp_publisher_manage_plans',
            'fp-publisher',
            [self::class, 'renderApp']
        );

        // Composer
        add_submenu_page(
            'fp-publisher',
            'Nuovo Post',
            'Nuovo Post',
            'fp_publisher_manage_plans',
            'fp-publisher-composer',
            [self::class, 'renderApp']
        );

        // Calendar
        add_submenu_page(
            'fp-publisher',
            'Calendario',
            'Calendario',
            'fp_publisher_manage_plans',
            'fp-publisher-calendar',
            [self::class, 'renderApp']
        );

        // Library
        add_submenu_page(
            'fp-publisher',
            'Libreria Media',
            'Libreria Media',
            'fp_publisher_manage_plans',
            'fp-publisher-library',
            [self::class, 'renderApp']
        );

        // Analytics
        add_submenu_page(
            'fp-publisher',
            'Analytics',
            'Analytics',
            'fp_publisher_manage_plans',
            'fp-publisher-analytics',
            [self::class, 'renderApp']
        );

        // Separator
        add_submenu_page(
            'fp-publisher',
            '',
            '<span style="opacity:0.3">────────</span>',
            'fp_publisher_manage_plans',
            '#',
            function() {}
        );

        // Clients
        add_submenu_page(
            'fp-publisher',
            'Gestione Clienti',
            'Clienti',
            'fp_publisher_manage_plans',
            'fp-publisher-clients',
            [self::class, 'renderApp']
        );

        // Accounts
        add_submenu_page(
            'fp-publisher',
            'Account Social',
            'Account Social',
            'fp_publisher_manage_accounts',
            'fp-publisher-accounts',
            [self::class, 'renderApp']
        );

        // Jobs
        add_submenu_page(
            'fp-publisher',
            'Cronologia Job',
            'Job',
            'fp_publisher_manage_plans',
            'fp-publisher-jobs',
            [self::class, 'renderApp']
        );

        // Settings
        add_submenu_page(
            'fp-publisher',
            'Impostazioni',
            'Impostazioni',
            'fp_publisher_manage_settings',
            'fp-publisher-settings',
            [self::class, 'renderApp']
        );
    }

    public static function renderApp(): void
    {
        echo '<div id="fp-publisher-app"></div>';
    }

    public static function enqueueAssets(string $hook): void
    {
        // Only load on our admin pages
        if (strpos($hook, 'fp-publisher') === false) {
            return;
        }

        $assetPath = plugin_dir_path(dirname(__DIR__, 2)) . 'assets/dist/admin/';
        $assetUrl = plugin_dir_url(dirname(__DIR__, 2)) . 'assets/dist/admin/';

        // Check if built assets exist
        if (file_exists($assetPath . 'index.js')) {
            wp_enqueue_script(
                'fp-publisher-admin',
                $assetUrl . 'index.js',
                ['wp-element'],
                filemtime($assetPath . 'index.js'),
                true
            );
        }

        if (file_exists($assetPath . 'index.css')) {
            wp_enqueue_style(
                'fp-publisher-admin',
                $assetUrl . 'index.css',
                [],
                filemtime($assetPath . 'index.css')
            );
        }

        // Pass data to JavaScript
        wp_localize_script('fp-publisher-admin', 'fpPublisher', [
            'apiUrl' => rest_url('fp-publisher/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentUser' => get_current_user_id(),
        ]);
    }
}
