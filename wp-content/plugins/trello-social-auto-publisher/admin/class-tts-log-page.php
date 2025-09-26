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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access the FP Publisher logs.', 'fp-publisher' ) );
		}

		$table = new TTS_Log_Table();
		$table->process_actions();
		$table->prepare_items();

		$total_items   = (int) $table->get_pagination_arg( 'total_items' );
		$current_items = count( $table->items );

		$channel_filter = isset( $_GET['channel'] ) ? sanitize_text_field( wp_unslash( $_GET['channel'] ) ) : '';
		$status_filter  = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		$active_filters = array();
		if ( $channel_filter ) {
			$active_filters[] = sprintf(
				/* translators: %s: current channel filter. */
				esc_html__( 'Channel: %s', 'fp-publisher' ),
				esc_html( $channel_filter )
			);
		}

		if ( $status_filter ) {
			$active_filters[] = sprintf(
				/* translators: %s: current status filter. */
				esc_html__( 'Status: %s', 'fp-publisher' ),
				esc_html( $status_filter )
			);
		}

		$filters_summary = $active_filters ? implode( ' · ', $active_filters ) : esc_html__( 'No filters applied', 'fp-publisher' );

		echo '<div class="wrap fp-publisher-log-page">';
		echo '<div class="tts-container">';
		echo '<div class="tts-page-header">';
		echo '<h1>' . esc_html__( 'Log', 'fp-publisher' ) . '</h1>';
		echo '<p class="tts-page-subtitle">' . esc_html__( 'Monitor every publishing attempt, review API responses, and keep integrations healthy with a clear activity history.', 'fp-publisher' ) . '</p>';
		echo '</div>';

		echo '<div class="tts-grid tts-log-overview">';
		echo '<div class="tts-card tts-log-card">';
		echo '<div class="tts-stat">';
		echo '<span class="tts-stat-label">' . esc_html__( 'Entries on this page', 'fp-publisher' ) . '</span>';
		echo '<span class="tts-stat-value">' . esc_html( number_format_i18n( $current_items ) ) . '</span>';
		echo '</div>';
		echo '<div class="tts-stat">';
		echo '<span class="tts-stat-label">' . esc_html__( 'Total log entries', 'fp-publisher' ) . '</span>';
		echo '<span class="tts-stat-value">' . esc_html( number_format_i18n( $total_items ) ) . '</span>';
		echo '</div>';
		echo '<div class="tts-stat tts-log-active-filters">';
		echo '<span class="tts-stat-label">' . esc_html__( 'Active filters', 'fp-publisher' ) . '</span>';
		echo '<span class="tts-stat-value">' . $filters_summary . '</span>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div class="tts-card tts-log-table-card">';
		echo '<form method="get" class="tts-log-form">';
		echo '<input type="hidden" name="page" value="fp-publisher-log" />';
		echo '<div class="tts-log-table-wrapper">';
		$table->display();
		echo '</div>';
		echo '</form>';
		echo '</div>';

		echo '</div>';
		echo '</div>';
	}
}

/**
 * WP_List_Table implementation for logs.
 */
class TTS_Log_Table extends WP_List_Table {

	/**
	 * Retrieve table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
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
	 * Prepare the table items.
	 */
	public function prepare_items() {
		global $wpdb;

		$table_name   = $wpdb->prefix . 'tts_logs';
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$channel = isset( $_GET['channel'] ) ? sanitize_text_field( wp_unslash( $_GET['channel'] ) ) : '';
		$status  = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		$where  = ' WHERE 1=1';
		$params = array();
		if ( $channel ) {
			$where   .= ' AND channel = %s';
			$params[] = $channel;
		}
		if ( $status ) {
			$where   .= ' AND status = %s';
			$params[] = $status;
		}

		$sql_count   = "SELECT COUNT(*) FROM {$table_name}{$where}";
		$total_items = $params ? $wpdb->get_var( $wpdb->prepare( $sql_count, $params ) ) : $wpdb->get_var( $sql_count );

		$sql                = "SELECT * FROM {$table_name}{$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params_for_query   = $params;
		$params_for_query[] = $per_page;
		$params_for_query[] = $offset;
		$items              = $wpdb->get_results( $wpdb->prepare( $sql, $params_for_query ), ARRAY_A );

		$this->items = $items;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );
	}

	/**
	 * Render message column with actions.
	 *
	 * @param array $item Current item.
	 *
	 * @return string
	 */
	public function column_message( $item ) {
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'delete',
					'log'    => $item['id'],
				)
			),
			'tts_delete_log_' . $item['id']
		);

		$actions = array(
			'delete' => sprintf( '<a href="%s">%s</a>', esc_url( $delete_url ), __( 'Delete', 'fp-publisher' ) ),
		);

		return sprintf( '%1$s %2$s', esc_html( $item['message'] ), $this->row_actions( $actions ) );
	}

	/**
	 * Default column rendering.
	 *
	 * @param array  $item        Row item.
	 * @param string $column_name Column name.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		if ( 'metrics' === $column_name ) {
			$metrics = get_post_meta( $item['post_id'], '_tts_metrics', true );
			$channel = isset( $item['channel'] ) ? $item['channel'] : '';
			if ( is_array( $metrics ) && isset( $metrics[ $channel ] ) ) {
				return esc_html( wp_json_encode( $metrics[ $channel ] ) );
			}
			return '';
		}

		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
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

		global $wpdb;
		$table_name = $wpdb->prefix . 'tts_logs';
		$channels   = $wpdb->get_col( "SELECT DISTINCT channel FROM {$table_name}" );
		$statuses   = $wpdb->get_col( "SELECT DISTINCT status FROM {$table_name}" );

		$current_channel = isset( $_GET['channel'] ) ? sanitize_text_field( wp_unslash( $_GET['channel'] ) ) : '';
		$current_status  = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		echo '<div class="alignleft actions tts-log-filters">';
		echo '<label class="screen-reader-text" for="tts-log-filter-channel">' . esc_html__( 'Filter by channel', 'fp-publisher' ) . '</label>';
		echo '<select name="channel" id="tts-log-filter-channel" class="tts-select">';
		echo '<option value="">' . esc_html__( 'All Channels', 'fp-publisher' ) . '</option>';
		foreach ( $channels as $ch ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $ch ), selected( $ch, $current_channel, false ) );
		}
		echo '</select>';

		echo '<label class="screen-reader-text" for="tts-log-filter-status">' . esc_html__( 'Filter by status', 'fp-publisher' ) . '</label>';
		echo '<select name="status" id="tts-log-filter-status" class="tts-select">';
		echo '<option value="">' . esc_html__( 'All Statuses', 'fp-publisher' ) . '</option>';
		foreach ( $statuses as $st ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $st ), selected( $st, $current_status, false ) );
		}
		echo '</select>';

		submit_button( __( 'Filter', 'fp-publisher' ), 'primary tts-btn tts-btn-primary', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Handle row actions.
	 */
	public function process_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage FP Publisher logs.', 'fp-publisher' ) );
		}

		if ( isset( $_GET['action'], $_GET['log'] ) && 'delete' === $_GET['action'] ) {
			$log_id = absint( $_GET['log'] );
			check_admin_referer( 'tts_delete_log_' . $log_id );

			global $wpdb;
			$table = $wpdb->prefix . 'tts_logs';
			$wpdb->delete( $table, array( 'id' => $log_id ), array( '%d' ) );

			wp_safe_redirect( remove_query_arg( array( 'action', 'log', '_wpnonce' ) ) );
			exit;
		}
	}
}
