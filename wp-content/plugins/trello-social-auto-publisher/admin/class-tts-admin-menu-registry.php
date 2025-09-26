<?php
/**
 * Central menu registry for FP Publisher admin pages.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles canonical admin menu registration and legacy slug aliases.
 */
class TTS_Admin_Menu_Registry {

    /**
     * Admin facade reference.
     *
     * @var TTS_Admin
     */
    private $admin;

    /**
     * Map of legacy slugs to canonical slugs.
     *
     * @var array<string, string>
     */
    private $alias_map = array();

    /**
     * Canonical slugs available in the registry.
     *
     * @var array<string, bool>
     */
    private $canonical_slugs = array();

    /**
     * Constructor.
     *
     * @param TTS_Admin $admin Admin facade.
     */
    public function __construct( TTS_Admin $admin ) {
        $this->admin = $admin;
        $this->hydrate_slug_maps();
    }

    /**
     * Register the admin menu and submenus.
     *
     * @param array<string, mixed>          $top_level Top level menu definition.
     * @param array<int, array<string, mixed>> $menu_items Menu item definitions.
     */
    public function register_menus( array $top_level, array $menu_items ) {
        if ( empty( $top_level['slug'] ) || empty( $top_level['callback'] ) ) {
            return;
        }

        $slug       = (string) $top_level['slug'];
        $page_title = isset( $top_level['page_title'] ) ? (string) $top_level['page_title'] : __( 'FP Publisher Dashboard', 'fp-publisher' );
        $menu_title = isset( $top_level['menu_title'] ) ? (string) $top_level['menu_title'] : __( 'FP Publisher', 'fp-publisher' );
        $capability = isset( $top_level['capability'] ) ? (string) $top_level['capability'] : 'manage_options';
        $callback   = array( $this->admin, (string) $top_level['callback'] );
        $icon       = isset( $top_level['icon'] ) ? (string) $top_level['icon'] : 'dashicons-share-alt';
        $position   = isset( $top_level['position'] ) ? (int) $top_level['position'] : 25;

        add_menu_page(
            $page_title,
            $menu_title,
            $capability,
            $slug,
            $callback,
            $icon,
            $position
        );

        foreach ( $menu_items as $item ) {
            if ( empty( $item['slug'] ) || empty( $item['callback'] ) ) {
                continue;
            }

            add_submenu_page(
                $slug,
                isset( $item['page_title'] ) ? (string) $item['page_title'] : (string) $item['menu_title'],
                isset( $item['menu_title'] ) ? (string) $item['menu_title'] : (string) $item['page_title'],
                isset( $item['capability'] ) ? (string) $item['capability'] : 'manage_options',
                (string) $item['slug'],
                array( $this->admin, (string) $item['callback'] )
            );
        }
    }

    /**
     * Return the canonical slug that should handle a request.
     *
     * @param string $slug Requested slug.
     *
     * @return string|null Canonical slug or null when not mapped.
     */
    public function get_canonical_slug( $slug ) {
        $slug = sanitize_key( $slug );

        if ( isset( $this->canonical_slugs[ $slug ] ) ) {
            return $slug;
        }

        if ( isset( $this->alias_map[ $slug ] ) ) {
            return $this->alias_map[ $slug ];
        }

        return null;
    }

    /**
     * Return all alias mappings.
     *
     * @return array<string, string>
     */
    public function get_alias_map() {
        return $this->alias_map;
    }

    /**
     * Build the canonical and alias slug maps from the navigation blueprint.
     *
     * @return void
     */
    private function hydrate_slug_maps() {
        $blueprint = $this->admin->get_navigation_blueprint();

        $register_definition = function ( array $definition ) {
            if ( empty( $definition['slug'] ) ) {
                return;
            }

            $slug = sanitize_key( (string) $definition['slug'] );

            if ( '' === $slug ) {
                return;
            }

            $this->canonical_slugs[ $slug ] = true;

            if ( empty( $definition['aliases'] ) || ! is_array( $definition['aliases'] ) ) {
                return;
            }

            foreach ( $definition['aliases'] as $alias ) {
                $alias = sanitize_key( (string) $alias );

                if ( '' === $alias || $alias === $slug ) {
                    continue;
                }

                if ( ! isset( $this->alias_map[ $alias ] ) ) {
                    $this->alias_map[ $alias ] = $slug;
                }
            }
        };

        foreach ( $blueprint as $section ) {
            if ( isset( $section['hub'] ) && is_array( $section['hub'] ) ) {
                $register_definition( $section['hub'] );
            }

            if ( empty( $section['items'] ) || ! is_array( $section['items'] ) ) {
                continue;
            }

            foreach ( $section['items'] as $item_definition ) {
                if ( ! is_array( $item_definition ) ) {
                    continue;
                }

                $register_definition( $item_definition );
            }
        }
    }
}
