<?php
/**
 * Admin page to display logs.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Log page controller.
 */
class TTS_Log_Page {

        /**
         * Initialize the log page.
         */
        public function __construct() {
                // Menu registration handled by TTS_Admin class
        }

        /**
         * Render the log page.
         */
        public function render_page() {
                if ( ! current_user_can( 'tts_manage_system' ) ) {
                        wp_die( esc_html__( 'You are not allowed to access the FP Publisher logs.', 'fp-publisher' ) );
                }

                $table = new TTS_Log_Table();
                $table->prepare_items();

                $deleted = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0;

                echo '<div class="wrap fp-publisher-log-page">';
                echo '<h1 class="wp-heading-inline">' . esc_html__( 'Activity Log', 'fp-publisher' ) . '</h1>';

                if ( $deleted > 0 ) {
                        printf(
                                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                                sprintf(
                                        /* translators: %s: number of deleted log entries. */
                                        esc_html__( 'Deleted %s log entries.', 'fp-publisher' ),
                                        number_format_i18n( $deleted )
                                )
                        );
                }

                settings_errors( 'fp-publisher-log' );

                $filters_summary = $table->get_filters_summary_text();
                if ( '' !== $filters_summary ) {
                        echo '<p class="description">' . esc_html( $filters_summary ) . '</p>';
                }

                echo '<form method="get" class="tts-log-form">';
                echo '<input type="hidden" name="page" value="fp-publisher-log" />';
                $table->views();
                $table->search_box( __( 'Search logs', 'fp-publisher' ), 'fp-publisher-log' );
                $table->display();
                echo '</form>';

                echo '</div>';
        }
}

/**
 * WP_List_Table implementation for logs.
 */
class TTS_Log_Table extends WP_List_Table {

        /**
         * Currently applied channel filter.
         *
         * @var string
         */
        private $channel_filter = '';

        /**
         * Currently applied status filter.
         *
         * @var string
         */
        private $status_filter = '';

        /**
         * Search term applied to the table.
         *
         * @var string
         */
        private $search_term = '';

        /**
         * Cached list of available channels.
         *
         * @var array<int, string>
         */
        private $available_channels = array();

        /**
         * Cached list of statuses present in the log table.
         *
         * @var array<int, string>
         */
        private $available_statuses = array();

        /**
         * Aggregated counts grouped by status for quick views.
         *
         * @var array<string, int>
         */
        private $status_counts = array();

        /**
         * Total items matching the current filters.
         *
         * @var int
         */
        private $total_items = 0;

        /**
         * Constructor.
         */
        public function __construct() {
                parent::__construct(
                        array(
                                'singular' => 'fp_publisher_log',
                                'plural'   => 'fp_publisher_logs',
                                'ajax'     => false,
                        )
                );
        }

        /**
         * Shared column definitions used across the table and Screen Options.
         *
         * @return array<string, string>
         */
        public static function get_column_definitions() {
                return array(
                        'cb'         => '<input type="checkbox" />',
                        'id'         => __( 'ID', 'fp-publisher' ),
                        'post_id'    => __( 'Post ID', 'fp-publisher' ),
                        'channel'    => __( 'Channel', 'fp-publisher' ),
                        'status'     => __( 'Status', 'fp-publisher' ),
                        'message'    => __( 'Message', 'fp-publisher' ),
                        'metrics'    => __( 'Metrics', 'fp-publisher' ),
                        'created_at' => __( 'Date', 'fp-publisher' ),
                );
        }

        /**
         * Retrieve table columns.
         *
         * @return array<string, string>
         */
        public function get_columns() {
                $columns = self::get_column_definitions();

                if ( ! current_user_can( 'tts_manage_system' ) ) {
                        unset( $columns['cb'] );
                }

                return $columns;
        }

        /**
         * Define sortable columns.
         *
         * @return array<string, array{0:string,1:bool}>
         */
        protected function get_sortable_columns() {
                return array(
                        'id'         => array( 'id', false ),
                        'post_id'    => array( 'post_id', false ),
                        'channel'    => array( 'channel', false ),
                        'status'     => array( 'status', false ),
                        'created_at' => array( 'created_at', true ),
                );
        }

        /**
         * Prepare the table items.
         */
        public function prepare_items() {
                global $wpdb;

                $this->process_bulk_action();

                $this->channel_filter = isset( $_REQUEST['channel'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['channel'] ) ) : '';
                $this->status_filter  = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
                $this->search_term    = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

                $table_name = $wpdb->prefix . 'tts_logs';

                $this->available_channels = array_filter( (array) $wpdb->get_col( "SELECT DISTINCT channel FROM {$table_name} ORDER BY channel ASC" ) );
                $this->available_statuses = array_filter( (array) $wpdb->get_col( "SELECT DISTINCT status FROM {$table_name} ORDER BY status ASC" ) );

                $this->status_counts = $this->calculate_status_counts( $table_name );

                $per_page     = $this->get_items_per_page( 'fp_publisher_logs_per_page', 20 );
                $current_page = max( 1, $this->get_pagenum() );
                $offset       = ( $current_page - 1 ) * $per_page;

                $orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
                $order   = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';

                $allowed_orderby = array( 'id', 'post_id', 'channel', 'status', 'created_at' );
                if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
                        $orderby = 'created_at';
                }

                $order = 'asc' === strtolower( $order ) ? 'ASC' : 'DESC';

                $where_clauses = array( '1=1' );
                $params        = array();

                if ( '' !== $this->channel_filter ) {
                        $where_clauses[] = 'channel = %s';
                        $params[]        = $this->channel_filter;
                }

                if ( '' !== $this->status_filter ) {
                        $where_clauses[] = 'status = %s';
                        $params[]        = $this->status_filter;
                }

                if ( '' !== $this->search_term ) {
                        $like            = '%' . $wpdb->esc_like( $this->search_term ) . '%';
                        $where_clauses[] = '(message LIKE %s OR CAST(metrics AS CHAR) LIKE %s)';
                        $params[]        = $like;
                        $params[]        = $like;
                }

                $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

                $sql_count   = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
                $this->total_items = $params ? (int) $wpdb->get_var( $wpdb->prepare( $sql_count, $params ) ) : (int) $wpdb->get_var( $sql_count );

                $sql = "SELECT * FROM {$table_name} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

                $params_for_query   = $params;
                $params_for_query[] = $per_page;
                $params_for_query[] = $offset;

                $results = $params_for_query ? $wpdb->get_results( $wpdb->prepare( $sql, $params_for_query ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

                $this->items = array_map( array( $this, 'format_item' ), (array) $results );

                $columns  = $this->get_columns();
                $hidden   = array();
                if ( $this->screen ) {
                        $hidden = get_hidden_columns( $this->screen );
                }
                $sortable = $this->get_sortable_columns();

                $this->_column_headers = array( $columns, $hidden, $sortable );

                $this->set_pagination_args(
                        array(
                                'total_items' => $this->total_items,
                                'per_page'    => $per_page,
                                'total_pages' => ( $per_page > 0 ) ? ceil( $this->total_items / $per_page ) : 0,
                        )
                );
        }

        /**
         * Normalize items before rendering.
         *
         * @param array<string, mixed> $item Raw item from the database.
         *
         * @return array<string, mixed>
         */
        private function format_item( $item ) {
                $item['channel']    = isset( $item['channel'] ) ? (string) $item['channel'] : '';
                $item['status']     = isset( $item['status'] ) ? (string) $item['status'] : '';
                $item['message']    = isset( $item['message'] ) ? (string) $item['message'] : '';
                $item['created_at'] = isset( $item['created_at'] ) ? (string) $item['created_at'] : '';

                return $item;
        }

        /**
         * Build aggregated counts per status to power the view links.
         *
         * @param string $table_name Database table name.
         *
         * @return array<string, int>
         */
        private function calculate_status_counts( $table_name ) {
                global $wpdb;

                $clauses = array( '1=1' );
                $params  = array();

                if ( '' !== $this->channel_filter ) {
                        $clauses[] = 'channel = %s';
                        $params[]  = $this->channel_filter;
                }

                if ( '' !== $this->search_term ) {
                        $like      = '%' . $wpdb->esc_like( $this->search_term ) . '%';
                        $clauses[] = '(message LIKE %s OR CAST(metrics AS CHAR) LIKE %s)';
                        $params[]  = $like;
                        $params[]  = $like;
                }

                $where_sql = 'WHERE ' . implode( ' AND ', $clauses );

                $sql      = "SELECT status, COUNT(*) AS total FROM {$table_name} {$where_sql} GROUP BY status";
                $records  = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
                $counts   = array( 'all' => 0 );

                foreach ( (array) $records as $row ) {
                        $status                = isset( $row['status'] ) && '' !== $row['status'] ? (string) $row['status'] : 'unknown';
                        $counts[ $status ]     = isset( $counts[ $status ] ) ? (int) $counts[ $status ] + (int) $row['total'] : (int) $row['total'];
                        $counts['all']        += (int) $row['total'];
                }

                return $counts;
        }

        /**
         * Render message column with actions.
         *
         * @param array<string, mixed> $item Current item.
         *
         * @return string
         */
        public function column_message( $item ) {
                $actions = array();

                if ( current_user_can( 'tts_manage_system' ) ) {
                        $actions['delete'] = sprintf(
                                '<a href="%s">%s</a>',
                                esc_url(
                                        wp_nonce_url(
                                                add_query_arg(
                                                        array(
                                                                'page'   => 'fp-publisher-log',
                                                                'action' => 'delete',
                                                                'log'    => (int) $item['id'],
                                                        ),
                                                        admin_url( 'admin.php' )
                                                ),
                                                'bulk-' . $this->_args['plural']
                                        )
                                ),
                                __( 'Delete', 'fp-publisher' )
                        );
                }

                return sprintf( '%1$s %2$s', esc_html( $item['message'] ), $this->row_actions( $actions ) );
        }

        /**
         * Render the checkbox column for bulk actions.
         *
         * @param array<string, mixed> $item Current item.
         *
         * @return string
         */
        public function column_cb( $item ) {
                if ( ! current_user_can( 'tts_manage_system' ) ) {
                        return '';
                }

                return sprintf(
                        '<label class="screen-reader-text" for="cb-select-%1$d">%2$s</label><input id="cb-select-%1$d" type="checkbox" name="log_ids[]" value="%1$d" />',
                        (int) $item['id'],
                        esc_html__( 'Select log entry', 'fp-publisher' )
                );
        }

        /**
         * Default column rendering.
         *
         * @param array<string, mixed> $item        Row item.
         * @param string               $column_name Column name.
         *
         * @return string
         */
        public function column_default( $item, $column_name ) {
                if ( 'metrics' === $column_name ) {
                        $metrics = get_post_meta( (int) $item['post_id'], '_tts_metrics', true );
                        $channel = isset( $item['channel'] ) ? $item['channel'] : '';
                        if ( is_array( $metrics ) && isset( $metrics[ $channel ] ) ) {
                                return esc_html( wp_json_encode( $metrics[ $channel ] ) );
                        }
                        return '';
                }

                if ( 'created_at' === $column_name && ! empty( $item['created_at'] ) ) {
                        $timestamp = strtotime( $item['created_at'] );
                        if ( $timestamp ) {
                                return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) );
                        }
                }

                if ( 'status' === $column_name ) {
                        return esc_html( $this->format_status_label( (string) $item['status'] ) );
                }

                return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
        }

        /**
         * Output filters above the table.
         *
         * @param string $which Top or bottom.
         */
        protected function extra_tablenav( $which ) {
                if ( 'top' !== $which ) {
                        return;
                }

                echo '<div class="alignleft actions">';
                echo '<label class="screen-reader-text" for="tts-log-filter-channel">' . esc_html__( 'Filter by channel', 'fp-publisher' ) . '</label>';
                echo '<select name="channel" id="tts-log-filter-channel">';
                echo '<option value="">' . esc_html__( 'All Channels', 'fp-publisher' ) . '</option>';
                foreach ( $this->available_channels as $channel ) {
                        printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $channel ), selected( $channel, $this->channel_filter, false ), esc_html( $channel ) );
                }
                echo '</select>';

                echo '<label class="screen-reader-text" for="tts-log-filter-status">' . esc_html__( 'Filter by status', 'fp-publisher' ) . '</label>';
                echo '<select name="status" id="tts-log-filter-status">';
                echo '<option value="">' . esc_html__( 'All Statuses', 'fp-publisher' ) . '</option>';

                $statuses = $this->available_statuses;
                if ( isset( $this->status_counts['unknown'] ) ) {
                        $statuses[] = 'unknown';
                }

                $statuses = array_unique( array_filter( $statuses ) );
                foreach ( $statuses as $status ) {
                        printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $status ), selected( $status, $this->status_filter, false ), esc_html( $this->format_status_label( $status ) ) );
                }
                echo '</select>';

                submit_button( __( 'Filter', 'fp-publisher' ), 'secondary', 'filter_action', false );

                if ( $this->has_active_filters() ) {
                        echo ' <a class="button" href="' . esc_url( remove_query_arg( array( 'channel', 'status', 's', 'paged' ) ) ) . '">' . esc_html__( 'Reset', 'fp-publisher' ) . '</a>';
                }

                echo '</div>';
        }

        /**
         * Provide bulk actions for the list table.
         *
         * @return array<string, string>
         */
        protected function get_bulk_actions() {
                if ( ! current_user_can( 'tts_manage_system' ) ) {
                        return array();
                }

                return array(
                        'delete' => __( 'Delete', 'fp-publisher' ),
                );
        }

        /**
         * Display when the table has no items.
         */
        public function no_items() {
                esc_html_e( 'No log entries found for the selected filters.', 'fp-publisher' );
        }

        /**
         * Primary column name.
         *
         * @return string
         */
        protected function get_primary_column_name() {
                return 'message';
        }

        /**
         * Handle bulk and row actions for the table.
         */
        public function process_bulk_action() {
                if ( 'delete' !== $this->current_action() ) {
                        return;
                }

                if ( ! current_user_can( 'tts_manage_system' ) ) {
                        wp_die( esc_html__( 'You are not allowed to manage FP Publisher logs.', 'fp-publisher' ) );
                }

                check_admin_referer( 'bulk-' . $this->_args['plural'] );

                $ids = array();

                if ( isset( $_REQUEST['log'] ) ) {
                        $ids[] = absint( $_REQUEST['log'] );
                }

                if ( isset( $_REQUEST['log_ids'] ) && is_array( $_REQUEST['log_ids'] ) ) {
                        foreach ( $_REQUEST['log_ids'] as $log_id ) {
                                $ids[] = absint( $log_id );
                        }
                }

                $ids = array_unique( array_filter( $ids ) );

                if ( empty( $ids ) ) {
                        return;
                }

                global $wpdb;
                $table_name = $wpdb->prefix . 'tts_logs';

                foreach ( $ids as $id ) {
                        $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );
                }

                $redirect = remove_query_arg( array( 'action', 'action2', 'log', 'log_ids', '_wpnonce', 'deleted' ) );
                $redirect = add_query_arg( 'deleted', count( $ids ), $redirect );

                wp_safe_redirect( $redirect );
                exit;
        }

        /**
         * Build the views displayed above the table.
         *
         * @return array<string, string>
         */
        protected function get_views() {
                $views = array();
                $base  = remove_query_arg( array( 'status', 'paged' ) );

                $total = isset( $this->status_counts['all'] ) ? (int) $this->status_counts['all'] : $this->total_items;
                $views['all'] = sprintf(
                        '<a href="%1$s" class="%2$s">%3$s</a>',
                        esc_url( $base ),
                        '' === $this->status_filter ? 'current' : '',
                        sprintf(
                                /* translators: %s: total number of log entries. */
                                __( 'All <span class="count">(%s)</span>', 'fp-publisher' ),
                                number_format_i18n( $total )
                        )
                );

                $statuses = $this->status_counts;
                unset( $statuses['all'] );

                foreach ( $statuses as $status => $count ) {
                        $label = $this->format_status_label( $status );
                        $views[ $status ] = sprintf(
                                '<a href="%1$s" class="%2$s">%3$s</a>',
                                esc_url( add_query_arg( 'status', $status, $base ) ),
                                $status === $this->status_filter ? 'current' : '',
                                sprintf(
                                        /* translators: 1: Status label. 2: Count. */
                                        __( '%1$s <span class="count">(%2$s)</span>', 'fp-publisher' ),
                                        esc_html( $label ),
                                        number_format_i18n( (int) $count )
                                )
                        );
                }

                return $views;
        }

        /**
         * Determine whether any filters or search terms are active.
         *
         * @return bool
         */
        private function has_active_filters() {
                return '' !== $this->channel_filter || '' !== $this->status_filter || '' !== $this->search_term;
        }

        /**
         * Provide a human-friendly summary of the active filters.
         *
         * @return string
         */
        public function get_filters_summary_text() {
                $parts = array();

                if ( '' !== $this->channel_filter ) {
                        $parts[] = sprintf(
                                /* translators: %s: Channel name. */
                                __( 'Channel: %s', 'fp-publisher' ),
                                $this->channel_filter
                        );
                }

                if ( '' !== $this->status_filter ) {
                        $parts[] = sprintf(
                                /* translators: %s: Status name. */
                                __( 'Status: %s', 'fp-publisher' ),
                                $this->format_status_label( $this->status_filter )
                        );
                }

                if ( '' !== $this->search_term ) {
                        $parts[] = sprintf(
                                /* translators: %s: Search term. */
                                __( 'Search: %s', 'fp-publisher' ),
                                $this->search_term
                        );
                }

                if ( empty( $parts ) ) {
                        return __( 'No filters applied', 'fp-publisher' );
                }

                return implode( ' · ', $parts );
        }

        /**
         * Convert a status slug into a human-readable label.
         *
         * @param string $status Status slug.
         *
         * @return string
         */
        private function format_status_label( $status ) {
                if ( '' === $status ) {
                        return __( 'Unknown', 'fp-publisher' );
                }

                $normalized = strtolower( $status );

                $labels = array(
                        'success'   => __( 'Success', 'fp-publisher' ),
                        'failed'    => __( 'Failed', 'fp-publisher' ),
                        'error'     => __( 'Error', 'fp-publisher' ),
                        'warning'   => __( 'Warning', 'fp-publisher' ),
                        'scheduled' => __( 'Scheduled', 'fp-publisher' ),
                        'queued'    => __( 'Queued', 'fp-publisher' ),
                        'processing'=> __( 'Processing', 'fp-publisher' ),
                        'unknown'   => __( 'Unknown', 'fp-publisher' ),
                );

                if ( isset( $labels[ $normalized ] ) ) {
                        return $labels[ $normalized ];
                }

                $normalized = str_replace( array( '-', '_' ), ' ', $normalized );

                return ucwords( $normalized );
        }
}
