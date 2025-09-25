<?php
/**
 * Custom post type for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the custom post type used by the plugin.
 */
class TTS_CPT {

    /**
     * Initialize hooks.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'register_roles' ) );
        add_action( 'init', array( $this, 'register_meta_fields' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_schedule_metabox' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_preview_metabox' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_channel_metabox' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_media_metabox' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_messages_metabox' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_approval_metabox' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_location_metabox' ) );
        add_action( 'save_post_tts_social_post', array( $this, 'save_schedule_metabox' ), 5, 3 );
        add_action( 'save_post_tts_social_post', array( $this, 'save_channel_metabox' ), 10, 3 );
        add_action( 'save_post_tts_social_post', array( $this, 'save_media_metabox' ), 15, 3 );
        add_action( 'save_post_tts_social_post', array( $this, 'save_messages_metabox' ), 20, 3 );
        add_action( 'save_post_tts_social_post', array( $this, 'save_approval_metabox' ), 1, 3 );
        add_action( 'save_post_tts_social_post', array( $this, 'save_location_metabox' ), 25, 3 );
    }

    /**
     * Register the custom post type.
     */
    public function register_post_type() {
        $args = array(
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'supports'           => array( 'title', 'editor', 'custom-fields', 'thumbnail' ),
            'show_in_rest'       => true,
            'label'              => __( 'Social Posts', 'fp-publisher' ),
            'capability_type'    => array( 'tts_social_post', 'tts_social_posts' ),
            'map_meta_cap'       => true,
            'capabilities'       => array(
                'edit_post'              => 'tts_edit_social_post',
                'read_post'              => 'tts_read_social_post',
                'delete_post'            => 'tts_delete_social_post',
                'edit_posts'             => 'tts_edit_social_posts',
                'edit_others_posts'      => 'tts_edit_others_social_posts',
                'publish_posts'          => 'tts_publish_social_posts',
                'read_private_posts'     => 'tts_read_private_social_posts',
                'delete_posts'           => 'tts_delete_social_posts',
                'delete_private_posts'   => 'tts_delete_private_social_posts',
                'delete_published_posts' => 'tts_delete_published_social_posts',
                'delete_others_posts'    => 'tts_delete_others_social_posts',
                'edit_private_posts'     => 'tts_edit_private_social_posts',
                'edit_published_posts'   => 'tts_edit_published_social_posts',
                'create_posts'           => 'tts_create_social_posts',
            ),
        );

        register_post_type( 'tts_social_post', $args );
    }

    /**
     * Ensure custom roles exist and administrators receive required capabilities.
     */
    public function register_roles() {
        $roles = array(
            'fp_publisher_manager'  => array(
                'name' => __( 'FP Publisher Manager', 'fp-publisher' ),
                'caps' => self::get_manager_capabilities(),
            ),
            'fp_publisher_editor'   => array(
                'name' => __( 'FP Publisher Editor', 'fp-publisher' ),
                'caps' => self::get_editor_capabilities(),
            ),
            'fp_publisher_reviewer' => array(
                'name' => __( 'FP Publisher Reviewer', 'fp-publisher' ),
                'caps' => self::get_reviewer_capabilities(),
            ),
        );

        foreach ( $roles as $role_key => $role_data ) {
            $role = get_role( $role_key );

            if ( ! $role ) {
                $role = add_role( $role_key, $role_data['name'], $role_data['caps'] );
            } elseif ( $role instanceof WP_Role ) {
                foreach ( $role_data['caps'] as $capability => $grant ) {
                    if ( $grant ) {
                        $role->add_cap( $capability );
                    }
                }
            }
        }

        $admin_role = get_role( 'administrator' );
        if ( $admin_role instanceof WP_Role ) {
            foreach ( array_keys( self::get_manager_capabilities() ) as $capability ) {
                $admin_role->add_cap( $capability );
            }
        }
    }

    /**
     * Remove custom roles and capabilities during plugin deactivation.
     */
    public static function remove_roles() {
        $roles = array(
            'fp_publisher_manager',
            'fp_publisher_editor',
            'fp_publisher_reviewer',
        );

        foreach ( $roles as $role_key ) {
            if ( function_exists( 'remove_role' ) ) {
                remove_role( $role_key );
            }
        }

        $admin_role = get_role( 'administrator' );
        if ( $admin_role instanceof WP_Role ) {
            foreach ( array_keys( self::get_manager_capabilities() ) as $capability ) {
                $admin_role->remove_cap( $capability );
            }
        }
    }

    /**
     * Core capabilities shared between roles that manage social posts.
     *
     * @return array<string, bool>
     */
    private static function get_post_management_capabilities() {
        return array(
            'tts_read_social_post'              => true,
            'tts_read_social_posts'             => true,
            'tts_read_private_social_posts'     => true,
            'tts_edit_social_post'              => true,
            'tts_edit_social_posts'             => true,
            'tts_edit_private_social_posts'     => true,
            'tts_edit_published_social_posts'   => true,
            'tts_edit_others_social_posts'      => true,
            'tts_create_social_posts'           => true,
            'tts_publish_social_posts'          => true,
            'tts_delete_social_post'            => true,
            'tts_delete_social_posts'           => true,
            'tts_delete_private_social_posts'   => true,
            'tts_delete_published_social_posts' => true,
            'tts_delete_others_social_posts'    => true,
        );
    }

    /**
     * Capabilities granted to manager role.
     *
     * @return array<string, bool>
     */
    private static function get_manager_capabilities() {
        return array_merge(
            array(
                'read'                  => true,
                'tts_manage_clients'    => true,
                'tts_manage_integrations'=> true,
                'tts_manage_system'     => true,
                'tts_manage_health'     => true,
                'tts_view_reports'      => true,
                'tts_export_data'       => true,
                'tts_import_data'       => true,
                'tts_approve_posts'     => true,
            ),
            self::get_post_management_capabilities()
        );
    }

    /**
     * Capabilities for editors handling social posts.
     *
     * @return array<string, bool>
     */
    private static function get_editor_capabilities() {
        return array_merge(
            array(
                'read'             => true,
                'tts_view_reports' => true,
                'tts_approve_posts'=> true,
            ),
            self::get_post_management_capabilities()
        );
    }

    /**
     * Capabilities for reviewers who approve content.
     *
     * @return array<string, bool>
     */
    private static function get_reviewer_capabilities() {
        return array(
            'read'                          => true,
            'tts_read_social_post'          => true,
            'tts_read_social_posts'         => true,
            'tts_read_private_social_posts' => true,
            'tts_view_reports'              => true,
            'tts_approve_posts'             => true,
        );
    }

    /**
     * Determine if the current user can manage post meta with the provided capabilities.
     *
     * @param array<int, string> $required_capabilities Capabilities that must be granted.
     * @param int                $post_id               Optional post context.
     *
     * @return bool
     */
    private function user_can_manage_meta( array $required_capabilities, $post_id ) {
        foreach ( $required_capabilities as $capability ) {
            if ( ! current_user_can( $capability ) ) {
                return false;
            }
        }

        $post_id = absint( $post_id );
        if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
            return false;
        }

        return true;
    }

    /**
     * Check if the current user can manage editor-level meta fields.
     *
     * @param int $post_id Post context.
     *
     * @return bool
     */
    private function can_manage_editor_meta( $post_id ) {
        return $this->user_can_manage_meta( array( 'tts_edit_social_posts' ), $post_id );
    }

    /**
     * Check if the current user can manage approval-specific meta fields.
     *
     * @param int $post_id Post context.
     *
     * @return bool
     */
    private function can_manage_approval_meta( $post_id ) {
        return $this->user_can_manage_meta( array( 'tts_approve_posts' ), $post_id );
    }

    /**
     * Check if the current user can manage client specific meta fields.
     *
     * @param int $post_id Post context.
     *
     * @return bool
     */
    private function can_manage_client_meta( $post_id ) {
        return $this->user_can_manage_meta( array( 'tts_manage_clients', 'tts_edit_social_posts' ), $post_id );
    }

    /**
     * Authorization callback for editor-level post meta.
     *
     * @param bool   $allowed  Whether the meta operation is currently allowed.
     * @param string $meta_key Meta key being modified.
     * @param int    $post_id  Post identifier.
     *
     * @return bool
     */
    public function authorize_editor_meta( $allowed, $meta_key, $post_id, $user_id = 0, $cap = '', $caps = array() ) {
        unset( $meta_key, $user_id, $cap, $caps );

        if ( ! $this->can_manage_editor_meta( $post_id ) ) {
            return false;
        }

        return (bool) $allowed;
    }

    /**
     * Authorization callback for approval-specific post meta.
     *
     * @param bool   $allowed  Whether the meta operation is currently allowed.
     * @param string $meta_key Meta key being modified.
     * @param int    $post_id  Post identifier.
     *
     * @return bool
     */
    public function authorize_approval_meta( $allowed, $meta_key, $post_id, $user_id = 0, $cap = '', $caps = array() ) {
        unset( $meta_key, $user_id, $cap, $caps );

        if ( ! $this->can_manage_approval_meta( $post_id ) ) {
            return false;
        }

        return (bool) $allowed;
    }

    /**
     * Authorization callback for client assignment meta.
     *
     * @param bool   $allowed  Whether the meta operation is currently allowed.
     * @param string $meta_key Meta key being modified.
     * @param int    $post_id  Post identifier.
     *
     * @return bool
     */
    public function authorize_client_meta( $allowed, $meta_key, $post_id, $user_id = 0, $cap = '', $caps = array() ) {
        unset( $meta_key, $user_id, $cap, $caps );

        if ( ! $this->can_manage_client_meta( $post_id ) ) {
            return false;
        }

        return (bool) $allowed;
    }

    /**
     * Register custom meta fields.
     */
    public function register_meta_fields() {
        register_post_meta(
            'tts_social_post',
            '_tts_client_id',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'integer',
                'auth_callback' => array( $this, 'authorize_client_meta' ),
            )
        );

        register_post_meta(
            'tts_social_post',
            '_tts_social_channel',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'array',
                'items'        => array(
                    'type' => 'string',
                ),
                'default'      => array(),
                'auth_callback' => array( $this, 'authorize_editor_meta' ),
            )
        );

        register_post_meta(
            'tts_social_post',
            '_tts_approved',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'boolean',
                'default'      => false,
                'auth_callback' => array( $this, 'authorize_approval_meta' ),
            )
        );

        register_post_meta(
            'tts_social_post',
            '_tts_publish_story',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'boolean',
                'default'      => false,
                'auth_callback' => array( $this, 'authorize_editor_meta' ),
            )
        );

        register_post_meta(
            'tts_social_post',
            '_tts_story_media',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'integer',
                'default'      => 0,
                'auth_callback' => array( $this, 'authorize_editor_meta' ),
            )
        );

        $channels = array( 'facebook', 'instagram', 'youtube', 'tiktok' );
        foreach ( $channels as $ch ) {
            register_post_meta(
                'tts_social_post',
                '_tts_message_' . $ch,
                array(
                    'show_in_rest' => true,
                    'single'       => true,
                    'type'         => 'string',
                    'default'      => '',
                    'auth_callback' => array( $this, 'authorize_editor_meta' ),
                )
            );
        }

        register_post_meta(
            'tts_social_post',
            '_tts_lat',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
                'default'      => '',
                'auth_callback' => array( $this, 'authorize_editor_meta' ),
            )
        );

        register_post_meta(
            'tts_social_post',
            '_tts_lng',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
                'default'      => '',
                'auth_callback' => array( $this, 'authorize_editor_meta' ),
            )
        );
    }

    /**
     * Register the scheduling meta box.
     */
    public function add_schedule_metabox() {
        add_meta_box(
            'tts_programmazione',
            __( 'Programmazione', 'fp-publisher' ),
            array( $this, 'render_schedule_metabox' ),
            'tts_social_post',
            'side'
        );
    }

    /**
     * Register the preview meta box.
     */
    public function add_preview_metabox() {
        add_meta_box(
            'tts_anteprima',
            __( 'Preview', 'fp-publisher' ),
            array( $this, 'render_preview_metabox' ),
            'tts_social_post',
            'normal'
        );
    }

    /**
     * Register the social channels meta box.
     */
    public function add_channel_metabox() {
        add_meta_box(
            'tts_social_channel',
            __( 'Channels', 'fp-publisher' ),
            array( $this, 'render_channel_metabox' ),
            'tts_social_post',
            'side'
        );
    }

    /**
     * Register the manual media meta box.
     */
    public function add_media_metabox() {
        add_meta_box(
            'tts_manual_media',
            __( 'Media', 'fp-publisher' ),
            array( $this, 'render_media_metabox' ),
            'tts_social_post',
            'side'
        );
    }

    /**
     * Register the per-channel messages meta box.
     */
    public function add_messages_metabox() {
        add_meta_box(
            'tts_messages',
            __( 'Messaggi per canale', 'fp-publisher' ),
            array( $this, 'render_messages_metabox' ),
            'tts_social_post',
            'normal'
        );
    }

    /**
     * Register the location meta box.
     */
    public function add_location_metabox() {
        add_meta_box(
            'tts_location',
            __( 'Localizzazione', 'fp-publisher' ),
            array( $this, 'render_location_metabox' ),
            'tts_social_post',
            'side'
        );
    }

    /**
     * Register the approval status meta box.
     */
    public function add_approval_metabox() {
        if ( ! current_user_can( 'tts_approve_posts' ) ) {
            return;
        }

        add_meta_box(
            'tts_approval_status',
            __( 'Stato di approvazione', 'fp-publisher' ),
            array( $this, 'render_approval_metabox' ),
            'tts_social_post',
            'side'
        );
    }

    /**
     * Render the scheduling meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_schedule_metabox( $post ) {
        wp_nonce_field( 'tts_schedule_metabox', 'tts_schedule_nonce' );
        $value     = get_post_meta( $post->ID, '_tts_publish_at', true );
        $formatted = $value ? date( 'Y-m-d\\TH:i', strtotime( $value ) ) : '';

        echo '<label for="_tts_publish_at">' . esc_html__( 'Data di pubblicazione', 'fp-publisher' ) . '</label>';
        echo '<input type="datetime-local" id="_tts_publish_at" name="_tts_publish_at" value="' . esc_attr( $formatted ) . '" class="widefat" />';

        $channels = get_post_meta( $post->ID, '_tts_social_channel', true );
        $channel  = is_array( $channels ) ? reset( $channels ) : $channels;
        if ( $channel && class_exists( 'TTS_Timing' ) ) {
            $suggested = TTS_Timing::suggest_time( $channel );
            if ( $suggested ) {
                echo '<p class="description">' . sprintf( esc_html__( 'Orario suggerito: %s', 'fp-publisher' ), esc_html( $suggested ) ) . '</p>';
            }
        }
    }

    /**
     * Render the preview meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_preview_metabox( $post ) {
        $options   = get_option( 'tts_settings', array() );
        $templates = array(
            'facebook'  => isset( $options['facebook_template'] ) ? $options['facebook_template'] : '',
            'instagram' => isset( $options['instagram_template'] ) ? $options['instagram_template'] : '',
            'youtube'   => isset( $options['youtube_template'] ) ? $options['youtube_template'] : '',
        );

        echo '<div class="tts-preview">';
        foreach ( $templates as $network => $template ) {
            if ( empty( $template ) ) {
                continue;
            }
            $preview = tts_apply_template( $template, $post->ID, $network );
            echo '<p><strong>' . esc_html( ucfirst( $network ) ) . ':</strong> ' . esc_html( $preview ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Render the channels meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_channel_metabox( $post ) {
        wp_nonce_field( 'tts_channel_metabox', 'tts_channel_nonce' );
        $value    = get_post_meta( $post->ID, '_tts_social_channel', true );
        $value    = is_array( $value ) ? $value : array();
        $channels = array(
            'facebook'  => 'Facebook',
            'instagram' => 'Instagram',
            'youtube'   => 'YouTube',
            'tiktok'    => 'TikTok',
        );

        $options = get_option( 'tts_settings', array() );

        foreach ( $channels as $key => $label ) {
            $offset  = isset( $options[ $key . '_offset' ] ) ? intval( $options[ $key . '_offset' ] ) : 0;
            $display = sprintf( __( '%1$s (%2$d min)', 'fp-publisher' ), $label, $offset );
            printf(
                '<p><label><input type="checkbox" name="_tts_social_channel[]" value="%1$s" %2$s /> %3$s</label></p>',
                esc_attr( $key ),
                checked( in_array( $key, $value, true ), true, false ),
                esc_html( $display )
            );
        }
    }

    /**
     * Render the manual media meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_media_metabox( $post ) {
        wp_nonce_field( 'tts_media_metabox', 'tts_media_nonce' );
        wp_enqueue_media();
        wp_enqueue_script( 'jquery-ui-sortable' );
        $attachments = get_post_meta( $post->ID, '_tts_attachment_ids', true );
        $attachments = is_array( $attachments ) ? $attachments : array();
        $manual_media = (int) get_post_meta( $post->ID, '_tts_manual_media', true );

        if ( $manual_media <= 0 && ! empty( $attachments ) ) {
            $manual_media = (int) $attachments[0];
        }

        $attachment_items = '';
        foreach ( $attachments as $id ) {
            $id        = (int) $id;
            $thumb     = wp_get_attachment_image( $id, array( 120, 120 ) );
            $title     = get_the_title( $id );
            $title     = $title ? $title : sprintf( __( 'Media #%d', 'fp-publisher' ), $id );
            $is_primary = ( $id === $manual_media );

            if ( $thumb ) {
                $attachment_items .= '<li class="tts-attachment-item' . ( $is_primary ? ' is-primary' : '' ) . '" data-id="' . esc_attr( $id ) . '">';
                $attachment_items .= '<div class="tts-attachment-thumb">' . $thumb . '</div>';
                $attachment_items .= '<div class="tts-attachment-meta">';
                $attachment_items .= '<span class="tts-attachment-title">' . esc_html( $title ) . '</span>';
                $attachment_items .= '<div class="tts-attachment-actions">';
                $attachment_items .= '<button type="button" class="button-link tts-attachment-make-primary" data-id="' . esc_attr( $id ) . '">' . esc_html__( 'Imposta come principale', 'fp-publisher' ) . '</button>';
                $attachment_items .= '<button type="button" class="button-link-delete tts-attachment-remove" data-id="' . esc_attr( $id ) . '">' . esc_html__( 'Rimuovi', 'fp-publisher' ) . '</button>';
                $attachment_items .= '</div>';
                $attachment_items .= '</div>';
                $attachment_items .= '<span class="tts-primary-indicator" aria-hidden="true">' . esc_html__( 'Primario', 'fp-publisher' ) . '</span>';
                $attachment_items .= '</li>';
            }
        }

        echo '<div class="tts-attachments" data-empty="' . esc_attr( empty( $attachments ) ? '1' : '0' ) . '" data-make-primary-label="' . esc_attr__( 'Imposta come principale', 'fp-publisher' ) . '" data-remove-label="' . esc_attr__( 'Rimuovi', 'fp-publisher' ) . '" data-primary-label="' . esc_attr__( 'Primario', 'fp-publisher' ) . '">';
        echo '<p id="tts_attachments_empty" class="tts-attachments-empty"' . ( empty( $attachments ) ? '' : ' style="display:none"' ) . '>' . esc_html__( 'Nessun media selezionato. Aggiungi elementi con il pulsante qui sotto.', 'fp-publisher' ) . '</p>';
        echo '<ul id="tts_attachments_list" class="tts-attachments-list">' . $attachment_items . '</ul>';
        echo '<input type="hidden" id="tts_attachment_ids" name="_tts_attachment_ids" value="' . esc_attr( implode( ',', $attachments ) ) . '" />';
        echo '<input type="hidden" id="tts_manual_media" name="_tts_manual_media" value="' . esc_attr( $manual_media ) . '" />';
        echo '<div class="tts-attachments-actions">';
        echo '<button type="button" class="button button-secondary tts-select-media">' . esc_html__( 'Seleziona/Carica file', 'fp-publisher' ) . '</button>';
        echo '<button type="button" class="button-link tts-clear-attachments"' . ( empty( $attachments ) ? ' style="display:none"' : '' ) . '>' . esc_html__( 'Rimuovi tutti', 'fp-publisher' ) . '</button>';
        echo '</div>';
        echo '</div>';

        $story_enabled = (bool) get_post_meta( $post->ID, '_tts_publish_story', true );
        $story_media   = (int) get_post_meta( $post->ID, '_tts_story_media', true );
        $story_thumb   = $story_media ? wp_get_attachment_image( $story_media, array( 80, 80 ) ) : '';
        echo '<p><label><input type="checkbox" id="tts_publish_story" name="_tts_publish_story" value="1" ' . checked( $story_enabled, true, false ) . ' /> ' . esc_html__( 'Pubblica come Story', 'fp-publisher' ) . '</label></p>';
        echo '<div id="tts_story_media_wrapper"' . ( $story_enabled ? '' : ' style="display:none;"' ) . '>';
        echo '<div id="tts_story_media_preview">' . $story_thumb . '</div>';
        echo '<input type="hidden" id="tts_story_media" name="_tts_story_media" value="' . esc_attr( $story_media ) . '" />';
        echo '<button type="button" class="button tts-select-story-media">' . esc_html__( 'Seleziona media Story', 'fp-publisher' ) . '</button>';
        echo '</div>';
    }

    /**
     * Render per-channel messages meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_messages_metabox( $post ) {
        wp_nonce_field( 'tts_messages_metabox', 'tts_messages_nonce' );
        $channels = array(
            'facebook'  => 'Facebook',
            'instagram' => 'Instagram',
            'youtube'   => 'YouTube',
            'tiktok'    => 'TikTok',
        );

        foreach ( $channels as $key => $label ) {
            $value = get_post_meta( $post->ID, '_tts_message_' . $key, true );
            echo '<p><label for="tts_message_' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label>';
            echo '<textarea id="tts_message_' . esc_attr( $key ) . '" name="_tts_message_' . esc_attr( $key ) . '" rows="3" class="widefat">' . esc_textarea( $value ) . '</textarea></p>';

            if ( 'instagram' === $key ) {
                $comment = get_post_meta( $post->ID, '_tts_instagram_first_comment', true );
                echo '<p><label for="tts_instagram_first_comment">' . esc_html__( 'Commento iniziale Instagram', 'fp-publisher' ) . '</label>';
                echo '<textarea id="tts_instagram_first_comment" name="_tts_instagram_first_comment" rows="3" class="widefat">' . esc_textarea( $comment ) . '</textarea></p>';
            }
        }
    }

    /**
     * Render location meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_location_metabox( $post ) {
        wp_nonce_field( 'tts_location_metabox', 'tts_location_nonce' );
        $lat     = get_post_meta( $post->ID, '_tts_lat', true );
        $lng     = get_post_meta( $post->ID, '_tts_lng', true );
        $options = get_option( 'tts_settings', array() );

        if ( '' === $lat && isset( $options['default_lat'] ) ) {
            $lat = $options['default_lat'];
        }
        if ( '' === $lng && isset( $options['default_lng'] ) ) {
            $lng = $options['default_lng'];
        }

        echo '<p><label for="_tts_lat">' . esc_html__( 'Latitude', 'fp-publisher' ) . '</label>';
        echo '<input type="text" id="_tts_lat" name="_tts_lat" value="' . esc_attr( $lat ) . '" class="widefat" /></p>';
        echo '<p><label for="_tts_lng">' . esc_html__( 'Longitude', 'fp-publisher' ) . '</label>';
        echo '<input type="text" id="_tts_lng" name="_tts_lng" value="' . esc_attr( $lng ) . '" class="widefat" /></p>';
    }

    /**
     * Save scheduling meta box data.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function save_schedule_metabox( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! $this->can_manage_editor_meta( $post_id ) ) {
            return;
        }

        if ( isset( $_POST['tts_schedule_nonce'] ) && wp_verify_nonce( $_POST['tts_schedule_nonce'], 'tts_schedule_metabox' ) ) {
            if ( isset( $_POST['_tts_publish_at'] ) && '' !== $_POST['_tts_publish_at'] ) {
                update_post_meta( $post_id, '_tts_publish_at', sanitize_text_field( wp_unslash( $_POST['_tts_publish_at'] ) ) );
            } else {
                delete_post_meta( $post_id, '_tts_publish_at' );
            }
        }
    }

    /**
     * Save manual media meta box data.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function save_media_metabox( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! $this->can_manage_editor_meta( $post_id ) ) {
            return;
        }

        if ( isset( $_POST['tts_media_nonce'] ) && wp_verify_nonce( $_POST['tts_media_nonce'], 'tts_media_metabox' ) ) {
            if ( isset( $_POST['_tts_attachment_ids'] ) ) {
                $ids = array_filter( array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_POST['_tts_attachment_ids'] ) ) ) ) );
                update_post_meta( $post_id, '_tts_attachment_ids', $ids );
            }
            if ( isset( $_POST['_tts_manual_media'] ) && '' !== $_POST['_tts_manual_media'] ) {
                update_post_meta( $post_id, '_tts_manual_media', (int) $_POST['_tts_manual_media'] );
            } else {
                delete_post_meta( $post_id, '_tts_manual_media' );
            }

            $is_story = isset( $_POST['_tts_publish_story'] ) && '1' === $_POST['_tts_publish_story'];
            if ( $is_story ) {
                update_post_meta( $post_id, '_tts_publish_story', true );
                if ( isset( $_POST['_tts_story_media'] ) && '' !== $_POST['_tts_story_media'] ) {
                    update_post_meta( $post_id, '_tts_story_media', (int) $_POST['_tts_story_media'] );
                }
            } else {
                delete_post_meta( $post_id, '_tts_publish_story' );
                delete_post_meta( $post_id, '_tts_story_media' );
            }
        }
    }

    /**
     * Save channel selection meta box.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function save_channel_metabox( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! $this->can_manage_editor_meta( $post_id ) ) {
            return;
        }

        if ( isset( $_POST['tts_channel_nonce'] ) && wp_verify_nonce( $_POST['tts_channel_nonce'], 'tts_channel_metabox' ) ) {
            if ( isset( $_POST['_tts_social_channel'] ) && is_array( $_POST['_tts_social_channel'] ) ) {
                $channels = array_map( 'sanitize_text_field', wp_unslash( $_POST['_tts_social_channel'] ) );
                update_post_meta( $post_id, '_tts_social_channel', $channels );
            } else {
                delete_post_meta( $post_id, '_tts_social_channel' );
            }
        }
    }

    /**
     * Save per-channel messages meta box data.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function save_messages_metabox( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! $this->can_manage_editor_meta( $post_id ) ) {
            return;
        }

        if ( isset( $_POST['tts_messages_nonce'] ) && wp_verify_nonce( $_POST['tts_messages_nonce'], 'tts_messages_metabox' ) ) {
            $channels = array( 'facebook', 'instagram', 'youtube', 'tiktok' );
            foreach ( $channels as $ch ) {
                $field = '_tts_message_' . $ch;
                if ( isset( $_POST[ $field ] ) && '' !== $_POST[ $field ] ) {
                    update_post_meta( $post_id, $field, sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) );
                } else {
                    delete_post_meta( $post_id, $field );
                }
            }

            $comment_field = '_tts_instagram_first_comment';
            if ( isset( $_POST[ $comment_field ] ) && '' !== $_POST[ $comment_field ] ) {
                update_post_meta( $post_id, $comment_field, sanitize_textarea_field( wp_unslash( $_POST[ $comment_field ] ) ) );
            } else {
                delete_post_meta( $post_id, $comment_field );
            }
        }
    }

    /**
     * Save location meta box data.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function save_location_metabox( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! $this->can_manage_editor_meta( $post_id ) ) {
            return;
        }

        if ( isset( $_POST['tts_location_nonce'] ) && wp_verify_nonce( $_POST['tts_location_nonce'], 'tts_location_metabox' ) ) {
            if ( isset( $_POST['_tts_lat'] ) && '' !== $_POST['_tts_lat'] ) {
                update_post_meta( $post_id, '_tts_lat', sanitize_text_field( wp_unslash( $_POST['_tts_lat'] ) ) );
            } else {
                delete_post_meta( $post_id, '_tts_lat' );
            }
            if ( isset( $_POST['_tts_lng'] ) && '' !== $_POST['_tts_lng'] ) {
                update_post_meta( $post_id, '_tts_lng', sanitize_text_field( wp_unslash( $_POST['_tts_lng'] ) ) );
            } else {
                delete_post_meta( $post_id, '_tts_lng' );
            }
        }
    }

    /**
     * Render approval status meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_approval_metabox( $post ) {
        if ( ! current_user_can( 'tts_approve_posts' ) ) {
            echo '<p>' . esc_html__( 'You do not have permission to change approval status.', 'fp-publisher' ) . '</p>';
            return;
        }

        wp_nonce_field( 'tts_approval_metabox', 'tts_approval_nonce' );
        $approved = (bool) get_post_meta( $post->ID, '_tts_approved', true );
        echo '<label><input type="checkbox" name="_tts_approved" value="1" ' . checked( $approved, true, false ) . ' /> ';
        echo esc_html__( 'Approvato', 'fp-publisher' ) . '</label>';
    }

    /**
     * Save approval status meta box.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function save_approval_metabox( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! $this->can_manage_approval_meta( $post_id ) ) {
            return;
        }

        if ( isset( $_POST['tts_approval_nonce'] ) && wp_verify_nonce( $_POST['tts_approval_nonce'], 'tts_approval_metabox' ) ) {
            $old = (bool) get_post_meta( $post_id, '_tts_approved', true );
            $new = isset( $_POST['_tts_approved'] ) ? (bool) $_POST['_tts_approved'] : false;

            if ( $new ) {
                update_post_meta( $post_id, '_tts_approved', true );
                if ( ! $old ) {
                    do_action( 'tts_post_approved', $post_id );
                }
            } else {
                delete_post_meta( $post_id, '_tts_approved' );
            }
        }
    }
}

new TTS_CPT();
