<?php
/**
 * Content Source Management System
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles content source tracking and management.
 */
class TTS_Content_Source {

    /**
     * Supported content sources.
     */
    const SOURCES = array(
        'trello'       => 'Trello',
        'google_drive' => 'Google Drive',
        'dropbox'      => 'Dropbox',
        'local_upload' => 'Local Upload',
        'manual'       => 'Manual Creation',
    );

    /**
     * Sources that support automated sync operations.
     */
    const REMOTE_SYNC_SOURCES = array( 'trello', 'google_drive', 'dropbox' );

    /**
     * Determine if a source is currently available for syncing.
     *
     * @param string $source Source key.
     * @return bool Whether the source can be synced.
     */
    public static function is_sync_available( $source ) {
        switch ( $source ) {
            case 'trello':
                if ( ! get_option( 'tts_trello_enabled', 1 ) ) {
                    return false;
                }

                $clients = get_posts(
                    array(
                        'post_type'   => 'tts_client',
                        'post_status' => 'any',
                        'meta_query'  => array(
                            'relation' => 'AND',
                            array(
                                'key'     => '_tts_trello_key',
                                'value'   => '',
                                'compare' => '!=',
                            ),
                            array(
                                'key'     => '_tts_trello_token',
                                'value'   => '',
                                'compare' => '!=',
                            ),
                        ),
                        'fields'      => 'ids',
                        'numberposts' => 1,
                    )
                );

                return ! empty( $clients );

            case 'google_drive':
                $settings = self::get_google_drive_settings();
                return ! empty( $settings['access_token'] ) && ! empty( $settings['folder_id'] );

            case 'dropbox':
                $settings = self::get_dropbox_settings();
                return ! empty( $settings['access_token'] ) && ! empty( $settings['folder_path'] );

            default:
                return true;
        }
    }

    /**
     * Retrieve sources that are currently syncable.
     *
     * @return array List of source keys.
     */
    public static function get_syncable_sources() {
        $syncable = array();

        foreach ( self::REMOTE_SYNC_SOURCES as $source ) {
            if ( self::is_sync_available( $source ) ) {
                $syncable[] = $source;
            }
        }

        return $syncable;
    }

    /**
     * Retrieve a helper message when sync is unavailable for a source.
     *
     * @param string $source Source key.
     * @return string Message describing what is missing.
     */
    public static function get_sync_unavailable_message( $source ) {
        switch ( $source ) {
            case 'trello':
                if ( ! get_option( 'tts_trello_enabled', 1 ) ) {
                    return __( 'Enable the Trello integration to sync cards.', 'fp-publisher' );
                }

                return __( 'Add a Trello client with API credentials to enable syncing.', 'fp-publisher' );

            case 'google_drive':
                $settings = self::get_google_drive_settings();
                if ( empty( $settings['access_token'] ) && empty( $settings['folder_id'] ) ) {
                    return __( 'Connect Google Drive and specify a folder ID to sync.', 'fp-publisher' );
                }

                if ( empty( $settings['access_token'] ) ) {
                    return __( 'Provide a Google Drive access token to enable syncing.', 'fp-publisher' );
                }

                if ( empty( $settings['folder_id'] ) ) {
                    return __( 'Select a Google Drive folder to sync.', 'fp-publisher' );
                }

                return '';

            case 'dropbox':
                $settings = self::get_dropbox_settings();
                if ( empty( $settings['access_token'] ) && empty( $settings['folder_path'] ) ) {
                    return __( 'Connect Dropbox and choose a folder path to sync.', 'fp-publisher' );
                }

                if ( empty( $settings['access_token'] ) ) {
                    return __( 'Provide a Dropbox access token to enable syncing.', 'fp-publisher' );
                }

                if ( empty( $settings['folder_path'] ) ) {
                    return __( 'Specify the Dropbox folder path to sync.', 'fp-publisher' );
                }

                return '';

            default:
                return '';
        }
    }

    /**
     * Initialize content source system.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_content_source_metabox' ) );
        add_action( 'save_post', array( $this, 'save_content_source_meta' ) );
        add_action( 'wp_ajax_tts_add_content_source', array( $this, 'ajax_add_content_source' ) );
        add_action( 'wp_ajax_tts_sync_content_sources', array( $this, 'ajax_sync_content_sources' ) );
    }

    /**
     * Add content source metabox to post editor.
     */
    public function add_content_source_metabox() {
        add_meta_box(
            'tts_content_source',
            __( 'Content Source', 'fp-publisher' ),
            array( $this, 'render_content_source_metabox' ),
            'tts_social_post',
            'side',
            'high'
        );
    }

    /**
     * Render content source metabox.
     *
     * @param WP_Post $post The post object.
     */
    public function render_content_source_metabox( $post ) {
        wp_nonce_field( 'tts_content_source_meta', 'tts_content_source_nonce' );
        
        $source = get_post_meta( $post->ID, '_tts_content_source', true );

        if ( empty( $source ) ) {
            $requested_source = $this->get_requested_source();
            if ( '' !== $requested_source ) {
                $source = $requested_source;
            }
        }

        $trello_enabled = (bool) get_option( 'tts_trello_enabled', 1 );
        $show_trello_disabled_notice = false;

        if ( ! $trello_enabled && 'trello' === $source ) {
            $source                     = 'manual';
            $show_trello_disabled_notice = true;
        }

        $source_reference = get_post_meta( $post->ID, '_tts_source_reference', true );
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="tts_content_source">' . esc_html__( 'Source', 'fp-publisher' ) . '</label></th>';
        echo '<td>';
        echo '<select name="tts_content_source" id="tts_content_source" class="widefat">';
        echo '<option value="">' . esc_html__( 'Select Source', 'fp-publisher' ) . '</option>';

        foreach ( self::SOURCES as $key => $label ) {
            if ( ! $trello_enabled && 'trello' === $key ) {
                continue;
            }

            $selected = selected( $source, $key, false );
            echo '<option value="' . esc_attr( $key ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
        }

        echo '</select>';

        if ( $show_trello_disabled_notice ) {
            echo '<p class="description tts-content-source-warning">' . esc_html__( 'Trello integration is disabled. Manual creation will be used for this post unless you choose another source.', 'fp-publisher' ) . '</p>';
        }
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="tts_source_reference">' . esc_html__( 'Source Reference', 'fp-publisher' ) . '</label></th>';
        echo '<td>';
        echo '<input type="text" name="tts_source_reference" id="tts_source_reference" value="' . esc_attr( $source_reference ) . '" class="widefat" placeholder="' . esc_attr__( 'e.g., Trello Card ID, Drive File ID', 'fp-publisher' ) . '">';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
    }

    /**
     * Save content source metadata.
     *
     * @param int $post_id The post ID.
     */
    public function save_content_source_meta( $post_id ) {
        // Verify nonce.
        if ( ! isset( $_POST['tts_content_source_nonce'] ) ||
             ! wp_verify_nonce( $_POST['tts_content_source_nonce'], 'tts_content_source_meta' ) ) {
            return;
        }

        // Check user permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save content source.
        $source = null;
        if ( isset( $_POST['tts_content_source'] ) ) {
            $source = sanitize_key( wp_unslash( $_POST['tts_content_source'] ) );
        }

        if ( null === $source || '' === $source ) {
            $requested_source = $this->get_requested_source();
            if ( '' !== $requested_source ) {
                $source = $requested_source;
            }
        }

        if ( null !== $source && ( '' === $source || array_key_exists( $source, self::SOURCES ) ) ) {
            update_post_meta( $post_id, '_tts_content_source', $source );
        }

        // Save source reference.
        if ( isset( $_POST['tts_source_reference'] ) ) {
            $reference = sanitize_text_field( $_POST['tts_source_reference'] );
            update_post_meta( $post_id, '_tts_source_reference', $reference );
        }
    }

    /**
     * Retrieve the requested content source from the query string when valid.
     *
     * @return string The requested source key or an empty string when invalid.
     */
    private function get_requested_source() {
        if ( ! isset( $_GET['content_source'] ) ) {
            return '';
        }

        $requested_source = sanitize_key( wp_unslash( $_GET['content_source'] ) );

        if ( ! array_key_exists( $requested_source, self::SOURCES ) ) {
            return '';
        }

        return $requested_source;
    }

    /**
     * Get posts by content source.
     *
     * @param string $source The content source.
     * @param array  $args Additional query arguments.
     * @return WP_Query The query object.
     */
    public static function get_posts_by_source( $source, $args = array() ) {
        $default_args = array(
            'post_type'      => 'tts_social_post',
            'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
            'meta_query'     => array(
                array(
                    'key'     => '_tts_content_source',
                    'value'   => $source,
                    'compare' => '=',
                ),
            ),
            'posts_per_page' => -1,
        );

        $args = wp_parse_args( $args, $default_args );
        return new WP_Query( $args );
    }

    /**
     * Get content source statistics.
     *
     * @return array Source statistics.
     */
    public static function get_source_stats() {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT meta_value as source, COUNT(*) as count
             FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = '_tts_content_source' 
             AND p.post_type = 'tts_social_post' 
             AND p.post_status != 'trash'
             GROUP BY meta_value"
        );

        $stats = array();
        foreach ( $results as $result ) {
            $source_name = isset( self::SOURCES[ $result->source ] ) 
                         ? self::SOURCES[ $result->source ] 
                         : $result->source;
            $stats[ $result->source ] = array(
                'name'  => $source_name,
                'count' => intval( $result->count ),
            );
        }

        return $stats;
    }

    /**
     * Retrieve stored Google Drive credentials.
     *
     * @return array{
     *     access_token:string,
     *     folder_id:string
     * }
     */
    private static function get_google_drive_settings() {
        $settings = get_option( 'tts_google_drive_settings', array() );

        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $access_token = isset( $settings['access_token'] ) ? trim( $settings['access_token'] ) : '';
        $folder_id    = isset( $settings['folder_id'] ) ? trim( $settings['folder_id'] ) : '';

        if ( '' === $access_token ) {
            $access_token = trim( (string) get_option( 'tts_google_drive_access_token', '' ) );
        }

        if ( '' === $folder_id ) {
            $folder_id = trim( (string) get_option( 'tts_google_drive_folder_id', '' ) );
        }

        return array(
            'access_token' => $access_token,
            'folder_id'    => $folder_id,
        );
    }

    /**
     * Retrieve stored Dropbox credentials.
     *
     * @return array{
     *     access_token:string,
     *     folder_path:string
     * }
     */
    private static function get_dropbox_settings() {
        $settings = get_option( 'tts_dropbox_settings', array() );

        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $access_token = isset( $settings['access_token'] ) ? trim( $settings['access_token'] ) : '';
        $folder_path  = isset( $settings['folder_path'] ) ? trim( $settings['folder_path'] ) : '';

        if ( '' === $access_token ) {
            $access_token = trim( (string) get_option( 'tts_dropbox_access_token', '' ) );
        }

        if ( '' === $folder_path ) {
            $folder_path = trim( (string) get_option( 'tts_dropbox_folder_path', '' ) );
        }

        return array(
            'access_token' => $access_token,
            'folder_path'  => $folder_path,
        );
    }

    /**
     * Determine whether a post already exists for a remote reference.
     *
     * @param string $source    Source key.
     * @param string $reference Reference identifier.
     * @return int Post ID when found, 0 otherwise.
     */
    private function post_exists_for_reference( $source, $reference ) {
        if ( empty( $reference ) ) {
            return 0;
        }

        $existing = get_posts(
            array(
                'post_type'   => 'tts_social_post',
                'post_status' => 'any',
                'meta_query'  => array(
                    'relation' => 'AND',
                    array(
                        'key'   => '_tts_content_source',
                        'value' => $source,
                    ),
                    array(
                        'key'   => '_tts_source_reference',
                        'value' => $reference,
                    ),
                ),
                'fields'      => 'ids',
                'numberposts' => 1,
            )
        );

        return ! empty( $existing ) ? (int) $existing[0] : 0;
    }

    /**
     * Create a draft post for remote content.
     *
     * @param array $args Post arguments.
     * @return int|WP_Error Post ID on success.
     */
    private function create_post_from_remote_source( $args ) {
        $defaults = array(
            'title'      => __( 'Remote Content', 'fp-publisher' ),
            'content'    => '',
            'source'     => '',
            'reference'  => '',
            'published'  => '',
            'extra_meta' => array(),
        );

        $args = wp_parse_args( $args, $defaults );

        $post_data = array(
            'post_title'   => sanitize_text_field( $args['title'] ),
            'post_content' => wp_kses_post( $args['content'] ),
            'post_type'    => 'tts_social_post',
            'post_status'  => 'draft',
        );

        if ( ! empty( $args['published'] ) ) {
            $timestamp = strtotime( $args['published'] );
            if ( $timestamp ) {
                $post_data['post_date'] = gmdate( 'Y-m-d H:i:s', $timestamp );
            }
        }

        $meta_input = array(
            '_tts_content_source'   => $args['source'],
            '_tts_source_reference' => $args['reference'],
        );

        if ( ! empty( $args['extra_meta'] ) && is_array( $args['extra_meta'] ) ) {
            foreach ( $args['extra_meta'] as $meta_key => $meta_value ) {
                $meta_input[ $meta_key ] = $meta_value;
            }
        }

        $post_data['meta_input'] = $meta_input;

        return wp_insert_post( $post_data, true );
    }

    /**
     * Store a downloaded file as a WordPress attachment.
     *
     * @param int    $post_id      Post ID.
     * @param string $filename     Original filename.
     * @param string $content      File contents.
     * @param string $content_type MIME type.
     * @return int|WP_Error Attachment ID on success.
     */
    private function import_attachment_from_stream( $post_id, $filename, $content, $content_type = '' ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = wp_tempnam( $filename );
        if ( ! $tmp ) {
            return new WP_Error( 'temp_file_creation_failed', __( 'Unable to create a temporary file for the download.', 'fp-publisher' ) );
        }

        file_put_contents( $tmp, $content );

        $file_array = array(
            'name'     => sanitize_file_name( $filename ),
            'tmp_name' => $tmp,
        );

        if ( ! empty( $content_type ) ) {
            $file_array['type'] = $content_type;
        }

        $media_id = media_handle_sideload( $file_array, $post_id );
        @unlink( $tmp );

        if ( is_wp_error( $media_id ) ) {
            return $media_id;
        }

        $attachment_ids = get_post_meta( $post_id, '_tts_attachment_ids', true );
        if ( ! is_array( $attachment_ids ) ) {
            $attachment_ids = array();
        }

        $attachment_ids[] = (int) $media_id;
        $attachment_ids   = array_values( array_unique( array_filter( $attachment_ids ) ) );
        update_post_meta( $post_id, '_tts_attachment_ids', $attachment_ids );

        if ( ! has_post_thumbnail( $post_id ) ) {
            set_post_thumbnail( $post_id, $media_id );
        }

        return (int) $media_id;
    }

    /**
     * Download a file from Google Drive.
     *
     * @param string $file_id      File identifier.
     * @param string $access_token OAuth access token.
     * @return array|WP_Error Body and content type on success.
     */
    private function download_google_drive_file( $file_id, $access_token ) {
        $response = wp_remote_get(
            sprintf( 'https://www.googleapis.com/drive/v3/files/%s?alt=media', rawurlencode( $file_id ) ),
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                ),
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            $body    = wp_remote_retrieve_body( $response );
            $message = $body;
            $decoded = json_decode( $body, true );

            if ( isset( $decoded['error']['message'] ) ) {
                $message = $decoded['error']['message'];
            }

            return new WP_Error(
                'google_drive_download_failed',
                sprintf( __( 'Unable to download Google Drive file: %s', 'fp-publisher' ), $message ),
                array( 'status' => $code )
            );
        }

        return array(
            'body'         => wp_remote_retrieve_body( $response ),
            'content_type' => wp_remote_retrieve_header( $response, 'content-type' ),
        );
    }

    /**
     * Download a file from Dropbox.
     *
     * @param string $path  File path.
     * @param string $token Access token.
     * @return array|WP_Error Body and content type on success.
     */
    private function download_dropbox_file( $path, $token ) {
        $response = wp_remote_post(
            'https://content.dropboxapi.com/2/files/download',
            array(
                'headers' => array(
                    'Authorization'   => 'Bearer ' . $token,
                    'Dropbox-API-Arg' => wp_json_encode( array( 'path' => $path ) ),
                ),
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            $body    = wp_remote_retrieve_body( $response );
            $message = $body;
            $decoded = json_decode( $body, true );

            if ( isset( $decoded['error_summary'] ) ) {
                $message = $decoded['error_summary'];
            }

            return new WP_Error(
                'dropbox_download_failed',
                sprintf( __( 'Unable to download Dropbox file: %s', 'fp-publisher' ), $message ),
                array( 'status' => $code )
            );
        }

        return array(
            'body'         => wp_remote_retrieve_body( $response ),
            'content_type' => wp_remote_retrieve_header( $response, 'content-type' ),
        );
    }

    /**
     * Resolve the configured social channel for a Trello list mapping.
     *
     * @param mixed  $mapping Mapping data.
     * @param string $list_id List identifier.
     * @return string Social channel value or empty string.
     */
    private function resolve_trello_channel( $mapping, $list_id ) {
        if ( empty( $list_id ) ) {
            return '';
        }

        if ( is_string( $mapping ) ) {
            $decoded = json_decode( $mapping, true );
            if ( is_array( $decoded ) ) {
                $mapping = $decoded;
            }
        }

        if ( is_array( $mapping ) ) {
            foreach ( $mapping as $row ) {
                if ( isset( $row['idList'] ) && $row['idList'] === $list_id ) {
                    return isset( $row['canale_social'] ) ? $row['canale_social'] : '';
                }
            }
        }

        return '';
    }

    /**
     * AJAX handler for adding content source.
     */
    public function ajax_add_content_source() {
        check_ajax_referer( 'tts_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'fp-publisher' ) );
        }

        $source = sanitize_text_field( $_POST['source'] ?? '' );
        $reference = sanitize_text_field( $_POST['reference'] ?? '' );
        $title = sanitize_text_field( $_POST['title'] ?? '' );
        $content = wp_kses_post( $_POST['content'] ?? '' );

        if ( empty( $source ) || ! array_key_exists( $source, self::SOURCES ) ) {
            wp_send_json_error( __( 'Invalid content source', 'fp-publisher' ) );
        }

        // Create new social post.
        $post_data = array(
            'post_title'   => $title ?: __( 'New Content', 'fp-publisher' ),
            'post_content' => $content,
            'post_type'    => 'tts_social_post',
            'post_status'  => 'draft',
            'meta_input'   => array(
                '_tts_content_source'   => $source,
                '_tts_source_reference' => $reference,
            ),
        );

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( $post_id->get_error_message() );
        }

        wp_send_json_success( array(
            'post_id' => $post_id,
            'message' => __( 'Content source added successfully', 'fp-publisher' ),
        ) );
    }

    /**
     * AJAX handler for syncing content sources.
     */
    public function ajax_sync_content_sources() {
        check_ajax_referer( 'tts_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'fp-publisher' ) );
        }

        $source = sanitize_text_field( $_POST['source'] ?? '' );
        
        if ( empty( $source ) || ! array_key_exists( $source, self::SOURCES ) ) {
            wp_send_json_error( __( 'Invalid content source', 'fp-publisher' ) );
        }

        // Trigger sync based on source type.
        $sync_result = 0;
        switch ( $source ) {
            case 'trello':
                $sync_result = $this->sync_trello_content();
                break;
            case 'google_drive':
                $sync_result = $this->sync_google_drive_content();
                break;
            case 'dropbox':
                $sync_result = $this->sync_dropbox_content();
                break;
            default:
                wp_send_json_error( __( 'Sync not supported for this source', 'fp-publisher' ) );
        }

        if ( is_wp_error( $sync_result ) ) {
            wp_send_json_error(
                array(
                    'code'    => $sync_result->get_error_code(),
                    'message' => $sync_result->get_error_message(),
                )
            );
        }

        $synced_count = (int) $sync_result;

        wp_send_json_success( array(
            'synced_count' => $synced_count,
            'message'      => sprintf(
                /* translators: %d: number of synced items */
                __( 'Synced %d items from %s', 'fp-publisher' ),
                $synced_count, 
                self::SOURCES[ $source ] 
            ),
        ) );
    }

    /**
     * Sync Trello content.
     *
     * @return int Number of synced items.
     */
    private function sync_trello_content() {
        if ( ! get_option( 'tts_trello_enabled', 1 ) ) {
            return new WP_Error( 'trello_disabled', __( 'Trello integration is disabled. Enable it from the settings page before syncing.', 'fp-publisher' ) );
        }

        $clients = get_posts(
            array(
                'post_type'   => 'tts_client',
                'post_status' => 'any',
                'meta_query'  => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_tts_trello_key',
                        'value'   => '',
                        'compare' => '!=',
                    ),
                    array(
                        'key'     => '_tts_trello_token',
                        'value'   => '',
                        'compare' => '!=',
                    ),
                ),
                'fields'      => 'ids',
                'numberposts' => -1,
            )
        );

        if ( empty( $clients ) ) {
            return new WP_Error( 'trello_not_configured', __( 'Add a Trello client with API credentials to enable syncing.', 'fp-publisher' ) );
        }

        $synced_count = 0;

        foreach ( $clients as $client_id ) {
            $client_id    = (int) $client_id;
            $trello_key   = trim( (string) get_post_meta( $client_id, '_tts_trello_key', true ) );
            $trello_token = trim( (string) get_post_meta( $client_id, '_tts_trello_token', true ) );
            $boards       = get_post_meta( $client_id, '_tts_trello_boards', true );
            $boards       = is_array( $boards ) ? array_filter( $boards ) : array();

            if ( empty( $trello_key ) || empty( $trello_token ) || empty( $boards ) ) {
                continue;
            }

            $list_mapping = get_post_meta( $client_id, '_tts_trello_map', true );
            $list_mapping = maybe_unserialize( $list_mapping );

            foreach ( $boards as $board_id ) {
                $board_id = trim( (string) $board_id );
                if ( '' === $board_id ) {
                    continue;
                }

                $endpoint   = sprintf( 'https://api.trello.com/1/boards/%s/cards', rawurlencode( $board_id ) );
                $query_args = array(
                    'key'               => $trello_key,
                    'token'             => $trello_token,
                    'attachments'       => 'true',
                    'attachment_fields' => 'url,isUpload,mimeType',
                    'fields'            => 'id,name,desc,idList,idBoard,due,labels,shortUrl',
                    'limit'             => 500,
                );

                $response = wp_remote_get(
                    add_query_arg( $query_args, $endpoint ),
                    array(
                        'timeout' => 30,
                    )
                );

                if ( is_wp_error( $response ) ) {
                    return new WP_Error(
                        'trello_request_failed',
                        sprintf( __( 'Unable to fetch Trello cards: %s', 'fp-publisher' ), $response->get_error_message() )
                    );
                }

                $status = (int) wp_remote_retrieve_response_code( $response );
                $body   = wp_remote_retrieve_body( $response );

                if ( 200 !== $status ) {
                    $message = $body;
                    $decoded = json_decode( $body, true );
                    if ( isset( $decoded['message'] ) ) {
                        $message = $decoded['message'];
                    } elseif ( isset( $decoded['error'] ) ) {
                        $message = $decoded['error'];
                    }

                    return new WP_Error(
                        'trello_http_error',
                        sprintf( __( 'Trello returned HTTP %1$d: %2$s', 'fp-publisher' ), $status, $message )
                    );
                }

                $cards = json_decode( $body, true );

                if ( ! is_array( $cards ) ) {
                    return new WP_Error( 'trello_invalid_response', __( 'Unexpected Trello API response.', 'fp-publisher' ) );
                }

                foreach ( $cards as $card ) {
                    if ( empty( $card['id'] ) ) {
                        continue;
                    }

                    $card_data = array(
                        'idCard'        => $card['id'],
                        'name'          => isset( $card['name'] ) ? $card['name'] : '',
                        'desc'          => isset( $card['desc'] ) ? $card['desc'] : '',
                        'labels'        => isset( $card['labels'] ) ? $card['labels'] : array(),
                        'attachments'   => isset( $card['attachments'] ) ? $card['attachments'] : array(),
                        'due'           => isset( $card['due'] ) ? $card['due'] : '',
                        'idList'        => isset( $card['idList'] ) ? $card['idList'] : '',
                        'idBoard'       => isset( $card['idBoard'] ) ? $card['idBoard'] : $board_id,
                        'canale_social' => $this->resolve_trello_channel( $list_mapping, $card['idList'] ?? '' ),
                    );

                    $import = TTS_Webhook::import_card_for_client( $card_data, $client_id );
                    if ( is_wp_error( $import ) ) {
                        $code = $import->get_error_code();
                        if ( in_array( $code, array( 'trello_card_exists', 'tts_unmapped_list' ), true ) ) {
                            continue;
                        }

                        return $import;
                    }

                    if ( isset( $import['post_id'] ) ) {
                        tts_log_event( $import['post_id'], 'sync', 'success', __( 'Imported from Trello sync', 'fp-publisher' ), $card_data['idCard'] );
                    }

                    $synced_count++;
                }
            }
        }

        return $synced_count;
    }

    /**
     * Sync Google Drive content.
     *
     * @return int Number of synced items.
     */
    private function sync_google_drive_content() {
        $settings = self::get_google_drive_settings();

        if ( empty( $settings['access_token'] ) ) {
            return new WP_Error( 'google_drive_missing_token', __( 'Google Drive access token is missing. Connect the integration before syncing.', 'fp-publisher' ) );
        }

        if ( empty( $settings['folder_id'] ) ) {
            return new WP_Error( 'google_drive_missing_folder', __( 'Google Drive folder ID is not configured.', 'fp-publisher' ) );
        }

        $synced_count = 0;
        $page_token   = '';
        $endpoint     = 'https://www.googleapis.com/drive/v3/files';

        do {
            $query_args = array(
                'q'        => sprintf( "'%s' in parents and trashed = false", $settings['folder_id'] ),
                'fields'   => 'files(id,name,mimeType,description,modifiedTime,webViewLink),nextPageToken',
                'pageSize' => 50,
            );

            if ( ! empty( $page_token ) ) {
                $query_args['pageToken'] = $page_token;
            }

            $response = wp_remote_get(
                add_query_arg( $query_args, $endpoint ),
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $settings['access_token'],
                    ),
                    'timeout' => 30,
                )
            );

            if ( is_wp_error( $response ) ) {
                return new WP_Error(
                    'google_drive_request_failed',
                    sprintf( __( 'Google Drive request failed: %s', 'fp-publisher' ), $response->get_error_message() )
                );
            }

            $status = (int) wp_remote_retrieve_response_code( $response );
            $body   = wp_remote_retrieve_body( $response );

            if ( 200 !== $status ) {
                $message = $body;
                $decoded = json_decode( $body, true );
                if ( isset( $decoded['error']['message'] ) ) {
                    $message = $decoded['error']['message'];
                }

                return new WP_Error(
                    'google_drive_http_error',
                    sprintf( __( 'Google Drive returned HTTP %1$d: %2$s', 'fp-publisher' ), $status, $message )
                );
            }

            $data = json_decode( $body, true );
            if ( ! is_array( $data ) || ! isset( $data['files'] ) || ! is_array( $data['files'] ) ) {
                return new WP_Error( 'google_drive_invalid_response', __( 'Unexpected Google Drive API response.', 'fp-publisher' ) );
            }

            foreach ( $data['files'] as $file ) {
                if ( empty( $file['id'] ) ) {
                    continue;
                }

                if ( $this->post_exists_for_reference( 'google_drive', $file['id'] ) ) {
                    continue;
                }

                if ( isset( $file['mimeType'] ) && 0 === strpos( $file['mimeType'], 'application/vnd.google-apps' ) ) {
                    // Skip Google Docs formats that require export conversions.
                    continue;
                }

                $post_id = $this->create_post_from_remote_source(
                    array(
                        'title'      => isset( $file['name'] ) ? $file['name'] : __( 'Google Drive File', 'fp-publisher' ),
                        'content'    => isset( $file['description'] ) ? $file['description'] : '',
                        'source'     => 'google_drive',
                        'reference'  => $file['id'],
                        'published'  => isset( $file['modifiedTime'] ) ? $file['modifiedTime'] : '',
                        'extra_meta' => array(
                            '_tts_google_drive_link' => isset( $file['webViewLink'] ) ? $file['webViewLink'] : '',
                            '_tts_google_drive_mime' => isset( $file['mimeType'] ) ? $file['mimeType'] : '',
                        ),
                    )
                );

                if ( is_wp_error( $post_id ) ) {
                    return $post_id;
                }

                $download = $this->download_google_drive_file( $file['id'], $settings['access_token'] );
                if ( is_wp_error( $download ) ) {
                    wp_delete_post( $post_id, true );
                    return $download;
                }

                $filename = isset( $file['name'] ) ? $file['name'] : $file['id'];
                $media    = $this->import_attachment_from_stream( $post_id, $filename, $download['body'], $download['content_type'] );

                if ( is_wp_error( $media ) ) {
                    wp_delete_post( $post_id, true );
                    return $media;
                }

                tts_log_event( $post_id, 'sync', 'success', __( 'Imported from Google Drive', 'fp-publisher' ), $file['id'] );
                $synced_count++;
            }

            $page_token = isset( $data['nextPageToken'] ) ? $data['nextPageToken'] : '';
        } while ( ! empty( $page_token ) );

        return $synced_count;
    }

    /**
     * Sync Dropbox content.
     *
     * @return int Number of synced items.
     */
    private function sync_dropbox_content() {
        $settings = self::get_dropbox_settings();

        if ( empty( $settings['access_token'] ) ) {
            return new WP_Error( 'dropbox_missing_token', __( 'Dropbox access token is missing. Connect the integration before syncing.', 'fp-publisher' ) );
        }

        $folder_path = isset( $settings['folder_path'] ) ? trim( $settings['folder_path'] ) : '';

        if ( '' === $folder_path ) {
            return new WP_Error( 'dropbox_missing_path', __( 'Dropbox folder path is not configured.', 'fp-publisher' ) );
        }

        if ( '/' === $folder_path ) {
            $folder_path = '';
        } elseif ( '/' !== substr( $folder_path, 0, 1 ) ) {
            $folder_path = '/' . ltrim( $folder_path, '/' );
        }

        $synced_count = 0;
        $cursor       = '';
        $has_more     = true;

        $request_args = array(
            'path'               => $folder_path,
            'recursive'          => false,
            'include_media_info' => true,
        );

        while ( $has_more ) {
            $endpoint = $cursor
                ? 'https://api.dropboxapi.com/2/files/list_folder/continue'
                : 'https://api.dropboxapi.com/2/files/list_folder';

            $body = $cursor ? array( 'cursor' => $cursor ) : $request_args;

            $response = wp_remote_post(
                $endpoint,
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $settings['access_token'],
                        'Content-Type'  => 'application/json',
                    ),
                    'body'    => wp_json_encode( $body ),
                    'timeout' => 30,
                )
            );

            if ( is_wp_error( $response ) ) {
                return new WP_Error(
                    'dropbox_request_failed',
                    sprintf( __( 'Dropbox request failed: %s', 'fp-publisher' ), $response->get_error_message() )
                );
            }

            $status   = (int) wp_remote_retrieve_response_code( $response );
            $body_raw = wp_remote_retrieve_body( $response );

            if ( 200 !== $status ) {
                $message = $body_raw;
                $decoded = json_decode( $body_raw, true );
                if ( isset( $decoded['error_summary'] ) ) {
                    $message = $decoded['error_summary'];
                }

                return new WP_Error(
                    'dropbox_http_error',
                    sprintf( __( 'Dropbox returned HTTP %1$d: %2$s', 'fp-publisher' ), $status, $message )
                );
            }

            $data = json_decode( $body_raw, true );
            if ( ! is_array( $data ) || ! isset( $data['entries'] ) || ! is_array( $data['entries'] ) ) {
                return new WP_Error( 'dropbox_invalid_response', __( 'Unexpected Dropbox API response.', 'fp-publisher' ) );
            }

            foreach ( $data['entries'] as $entry ) {
                if ( ! isset( $entry['.tag'] ) || 'file' !== $entry['.tag'] || empty( $entry['id'] ) ) {
                    continue;
                }

                if ( $this->post_exists_for_reference( 'dropbox', $entry['id'] ) ) {
                    continue;
                }

                $path = isset( $entry['path_lower'] ) ? $entry['path_lower'] : ( $entry['path_display'] ?? '' );
                if ( empty( $path ) ) {
                    continue;
                }

                $post_id = $this->create_post_from_remote_source(
                    array(
                        'title'      => isset( $entry['name'] ) ? $entry['name'] : __( 'Dropbox File', 'fp-publisher' ),
                        'content'    => '',
                        'source'     => 'dropbox',
                        'reference'  => $entry['id'],
                        'extra_meta' => array(
                            '_tts_dropbox_path' => $path,
                        ),
                    )
                );

                if ( is_wp_error( $post_id ) ) {
                    return $post_id;
                }

                $download = $this->download_dropbox_file( $path, $settings['access_token'] );

                if ( is_wp_error( $download ) ) {
                    wp_delete_post( $post_id, true );
                    return $download;
                }

                $filename = isset( $entry['name'] ) ? $entry['name'] : $entry['id'];
                $media    = $this->import_attachment_from_stream( $post_id, $filename, $download['body'], $download['content_type'] );

                if ( is_wp_error( $media ) ) {
                    wp_delete_post( $post_id, true );
                    return $media;
                }

                tts_log_event( $post_id, 'sync', 'success', __( 'Imported from Dropbox', 'fp-publisher' ), $entry['id'] );
                $synced_count++;
            }

            $cursor   = isset( $data['cursor'] ) ? $data['cursor'] : '';
            $has_more = ! empty( $data['has_more'] ) && ! empty( $cursor );
        }

        return $synced_count;
    }
}
