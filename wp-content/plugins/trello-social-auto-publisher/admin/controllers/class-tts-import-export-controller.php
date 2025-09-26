<?php
/**
 * Controller responsible for import/export admin functionality.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles import/export AJAX requests and modal rendering.
 */
class TTS_Import_Export_Controller {

    /**
     * AJAX security helper.
     *
     * @var TTS_Admin_Ajax_Security
     */
    private $ajax_security;

    /**
     * View helper used to render templates.
     *
     * @var TTS_Admin_View_Helper
     */
    private $view_helper;

    /**
     * Constructor.
     *
     * @param TTS_Admin_Ajax_Security $ajax_security Security helper.
     * @param TTS_Admin_View_Helper   $view_helper   View helper.
     */
    public function __construct( TTS_Admin_Ajax_Security $ajax_security, TTS_Admin_View_Helper $view_helper ) {
        $this->ajax_security = $ajax_security;
        $this->view_helper   = $view_helper;
    }

    /**
     * Register AJAX hooks.
     *
     * @return void
     */
    public function register_hooks() {
        add_action( 'wp_ajax_tts_export_data', array( $this, 'ajax_export_data' ) );
        add_action( 'wp_ajax_tts_import_data', array( $this, 'ajax_import_data' ) );
        add_action( 'wp_ajax_tts_show_export_modal', array( $this, 'ajax_show_export_modal' ) );
        add_action( 'wp_ajax_tts_show_import_modal', array( $this, 'ajax_show_import_modal' ) );
    }

    /**
     * AJAX handler for data export.
     *
     * @return void
     */
    public function ajax_export_data() {
        if ( ! $this->ajax_security->check( __FUNCTION__ ) ) {
            return;
        }

        $export_options = array(
            'settings'            => isset( $_POST['export_settings'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['export_settings'] ) ),
            'social_apps'         => isset( $_POST['export_social_apps'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['export_social_apps'] ) ),
            'clients'             => isset( $_POST['export_clients'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['export_clients'] ) ),
            'posts'               => isset( $_POST['export_posts'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['export_posts'] ) ),
            'logs'                => isset( $_POST['export_logs'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['export_logs'] ) ),
            'analytics'           => isset( $_POST['export_analytics'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['export_analytics'] ) ),
            'include_secrets'     => isset( $_POST['export_include_secrets'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['export_include_secrets'] ) ),
        );

        $export = TTS_Advanced_Utils::export_data( $export_options );

        if ( empty( $export['success'] ) ) {
            return wp_send_json_error(
                array(
                    'message' => isset( $export['error'] ) ? sanitize_text_field( $export['error'] ) : __( 'Export failed.', 'fp-publisher' ),
                ),
                500
            );
        }

        return wp_send_json_success(
            array(
                'filename' => $export['filename'],
                'content'  => base64_encode( $export['content'] ),
            )
        );
    }

    /**
     * AJAX handler for data import.
     *
     * @return void
     */
    public function ajax_import_data() {
        if ( ! $this->ajax_security->check( __FUNCTION__ ) ) {
            return;
        }

        if ( ! isset( $_FILES['import_file'] ) ) {
            return wp_send_json_error( array( 'message' => __( 'No file provided', 'fp-publisher' ) ), 400 );
        }

        $file = $_FILES['import_file'];

        $max_size = $this->get_max_import_file_size();

        if ( empty( $file['size'] ) || ! is_numeric( $file['size'] ) ) {
            return wp_send_json_error( array( 'message' => __( 'Invalid file upload.', 'fp-publisher' ) ), 400 );
        }

        if ( (int) $file['size'] > $max_size ) {
            return wp_send_json_error(
                array(
                    'message' => sprintf(
                        /* translators: %s: maximum allowed file size */
                        __( 'The uploaded file exceeds the maximum allowed size of %s.', 'fp-publisher' ),
                        size_format( $max_size )
                    ),
                ),
                400
            );
        }

        if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] ) {
            $error_message = __( 'File upload failed.', 'fp-publisher' );
            if ( isset( $file['error'] ) ) {
                switch ( $file['error'] ) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message = __( 'The uploaded file exceeds the maximum allowed size.', 'fp-publisher' );
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message = __( 'The uploaded file was only partially uploaded.', 'fp-publisher' );
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_message = __( 'No file was uploaded.', 'fp-publisher' );
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error_message = __( 'Missing a temporary folder on the server.', 'fp-publisher' );
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error_message = __( 'Failed to write the uploaded file to disk.', 'fp-publisher' );
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $error_message = __( 'File upload stopped by a PHP extension.', 'fp-publisher' );
                        break;
                    default:
                        $error_message = __( 'File upload failed due to an unknown error.', 'fp-publisher' );
                        break;
                }
            }

            return wp_send_json_error( array( 'message' => $error_message ), 400 );
        }

        if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return wp_send_json_error( array( 'message' => __( 'Invalid uploaded file.', 'fp-publisher' ) ), 400 );
        }

        if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array( 'json' => 'application/json' ) );

        if ( empty( $filetype['ext'] ) || 'json' !== $filetype['ext'] ) {
            return wp_send_json_error( array( 'message' => __( 'The uploaded file must be a valid JSON export from FP Publisher.', 'fp-publisher' ) ), 400 );
        }

        $file_contents = file_get_contents( $file['tmp_name'] );

        if ( false === $file_contents ) {
            return wp_send_json_error( array( 'message' => __( 'Unable to read the uploaded file.', 'fp-publisher' ) ), 500 );
        }

        $import_data = json_decode( $file_contents, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return wp_send_json_error( array( 'message' => __( 'Invalid JSON file', 'fp-publisher' ) ), 400 );
        }

        $import_options = array(
            'overwrite_settings'    => isset( $_POST['overwrite_settings'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['overwrite_settings'] ) ),
            'overwrite_social_apps' => isset( $_POST['overwrite_social_apps'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['overwrite_social_apps'] ) ),
            'import_clients'        => isset( $_POST['import_clients'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['import_clients'] ) ),
            'import_posts'          => isset( $_POST['import_posts'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['import_posts'] ) ),
        );

        $result = TTS_Advanced_Utils::import_data( $import_data, $import_options );

        if ( $result['success'] ) {
            return wp_send_json_success(
                array(
                    'message' => __( 'Import completed successfully', 'fp-publisher' ),
                    'log'     => $result['log'],
                )
            );
        }

        return wp_send_json_error( array( 'message' => sanitize_text_field( $result['error'] ) ), 500 );
    }

    /**
     * Render the export modal markup.
     *
     * @return void
     */
    public function ajax_show_export_modal() {
        if ( ! $this->ajax_security->check( __FUNCTION__ ) ) {
            return;
        }

        $modal_html = $this->view_helper->render(
            'modals/export',
            array(
                'nonce' => wp_create_nonce( 'tts_ajax_nonce' ),
            )
        );

        return wp_send_json_success(
            array(
                'modal_html' => $modal_html,
            )
        );
    }

    /**
     * Render the import modal markup.
     *
     * @return void
     */
    public function ajax_show_import_modal() {
        if ( ! $this->ajax_security->check( __FUNCTION__ ) ) {
            return;
        }

        $modal_html = $this->view_helper->render(
            'modals/import',
            array(
                'nonce' => wp_create_nonce( 'tts_ajax_nonce' ),
            )
        );

        return wp_send_json_success(
            array(
                'modal_html' => $modal_html,
            )
        );
    }

    /**
     * Determine the maximum allowed import file size.
     *
     * @return int Maximum file size in bytes.
     */
    private function get_max_import_file_size() {
        $upload_limit = wp_convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) );
        $post_limit   = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );

        $limits = array_filter(
            array(
                TTS_Admin::DEFAULT_IMPORT_MAX_FILE_SIZE,
                $upload_limit,
                $post_limit,
            ),
            function ( $value ) {
                return is_numeric( $value ) && $value > 0;
            }
        );

        $limit = ! empty( $limits ) ? min( $limits ) : TTS_Admin::DEFAULT_IMPORT_MAX_FILE_SIZE;

        /**
         * Filter the maximum allowed import file size.
         *
         * @param int $limit Maximum file size in bytes.
         */
        $limit = apply_filters( 'tts_import_max_file_size', (int) $limit );

        return max( 1, (int) $limit );
    }
}
