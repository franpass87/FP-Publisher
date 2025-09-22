<?php
/**
 * Admin page to display analytics charts.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Analytics page controller.
 */
class TTS_Analytics_Page {

    /**
     * Cached analytics data for the current request.
     *
     * @var array|null
     */
    private $current_data = null;

    /**
     * Cached, sanitized request filters.
     *
     * @var array|null
     */
    private $current_filters = null;

    /**
     * Initialize the analytics page.
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_filter( 'tts_analytics_localized_data', array( $this, 'inject_localized_data' ) );
    }

    /**
     * Remove the analytics menu registration method since it's handled by TTS_Admin.
     */

    /**
     * Enqueue Chart.js and custom scripts.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'fp-publisher_page_fp-publisher-analytics' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'tts-analytics',
            plugin_dir_url( __FILE__ ) . 'css/tts-analytics.css',
            array(),
            '1.0'
        );

        wp_enqueue_script(
            'chart.js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '4.4.0',
            true
        );

        wp_enqueue_script(
            'tts-analytics',
            plugin_dir_url( __FILE__ ) . 'js/tts-analytics.js',
            array( 'chart.js' ),
            '1.0',
            true
        );
    }

    /**
     * Inject analytics data into the localized script configuration.
     *
     * @param array $localized_data Localized data registered for the script.
     *
     * @return array
     */
    public function inject_localized_data( $localized_data ) {
        if ( ! $this->is_analytics_request() ) {
            return $localized_data;
        }

        $localized_data['data'] = $this->get_current_data();

        return $localized_data;
    }

    /**
     * Render the analytics page and output filters & chart container.
     */
    public function render_page() {
        $filters = $this->get_current_filters();
        $channel = $filters['channel'];
        $start   = $filters['start'];
        $end     = $filters['end'];
        $page    = $filters['page'] ? $filters['page'] : 'fp-publisher-analytics';

        $data = $this->get_current_data();

        $export = isset( $_GET['export'] ) ? sanitize_key( wp_unslash( $_GET['export'] ) ) : '';

        if ( 'csv' === $export ) {
            $this->export_csv( $data );
            exit;
        }

        $channels = $this->get_available_channels();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Analytics', 'fp-publisher' ) . '</h1>';
        
        // Summary stats
        $this->render_analytics_summary($data);
        
        echo '<div class="tts-analytics-content">';
        echo '<div class="tts-analytics-filters-section">';
        echo '<form method="get" class="tts-analytics-filters">';
        printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $page ) );

        echo '<div class="filter-group">';
        echo '<label for="channel">' . esc_html__( 'Channel', 'fp-publisher' ) . '</label>';
        echo '<select name="channel" id="channel">';
        echo '<option value="">' . esc_html__( 'All Channels', 'fp-publisher' ) . '</option>';
        foreach ( $channels as $ch ) {
            printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $ch ), selected( $ch, $channel, false ) );
        }
        echo '</select>';
        echo '</div>';

        echo '<div class="filter-group">';
        echo '<label for="start">' . esc_html__( 'From', 'fp-publisher' ) . '</label>';
        printf( '<input type="date" name="start" id="start" value="%s" />', esc_attr( $start ) );
        echo '</div>';
        
        echo '<div class="filter-group">';
        echo '<label for="end">' . esc_html__( 'To', 'fp-publisher' ) . '</label>';
        printf( '<input type="date" name="end" id="end" value="%s" />', esc_attr( $end ) );
        echo '</div>';

        echo '<div class="filter-actions">';
        submit_button( __( 'Filter', 'fp-publisher' ), 'primary', '', false );

        $export_args = array(
            'page'    => $page,
            'channel' => $channel,
            'start'   => $start,
            'end'     => $end,
            'export'  => 'csv',
        );

        $export_args = array_filter(
            $export_args,
            static function ( $value ) {
                return '' !== $value && null !== $value;
            }
        );

        $export_url = add_query_arg( $export_args, admin_url( 'admin.php' ) );
        echo ' <a href="' . esc_url( $export_url ) . '" class="button">' . esc_html__( 'Export CSV', 'fp-publisher' ) . '</a>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
        
        echo '<div class="tts-chart-container">';
        echo '<canvas id="tts-analytics-chart"></canvas>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Determine whether the current request targets the analytics admin page.
     *
     * @return bool
     */
    private function is_analytics_request() {
        $filters = $this->get_current_filters();

        return 'fp-publisher-analytics' === $filters['page'];
    }

    /**
     * Retrieve and cache the sanitized request filters.
     *
     * @return array
     */
    private function get_current_filters() {
        if ( null === $this->current_filters ) {
            $channel = isset( $_GET['channel'] ) ? sanitize_text_field( wp_unslash( $_GET['channel'] ) ) : '';
            $start   = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : '';
            $end     = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : '';
            $page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

            $this->current_filters = array(
                'channel' => $channel,
                'start'   => $start,
                'end'     => $end,
                'page'    => $page,
            );
        }

        return $this->current_filters;
    }

    /**
     * Get analytics data for the current request context.
     *
     * @return array
     */
    private function get_current_data() {
        if ( null === $this->current_data ) {
            $filters = $this->get_current_filters();
            $this->current_data = $this->get_metrics_data( $filters['channel'], $filters['start'], $filters['end'] );
        }

        return $this->current_data;
    }

    /**
     * Render analytics summary section
     */
    private function render_analytics_summary($data) {
        $total_interactions = 0;
        $channel_totals = array();
        $date_range = array();
        
        foreach ($data as $date => $channels) {
            $date_range[] = $date;
            foreach ($channels as $channel => $interactions) {
                $total_interactions += $interactions;
                if (!isset($channel_totals[$channel])) {
                    $channel_totals[$channel] = 0;
                }
                $channel_totals[$channel] += $interactions;
            }
        }
        
        arsort($channel_totals);
        $top_channel = !empty($channel_totals) ? array_key_first($channel_totals) : null;
        $date_range_text = '';
        if (!empty($date_range)) {
            sort($date_range);
            $date_range_text = count($date_range) > 1 ? 
                sprintf('%s - %s', reset($date_range), end($date_range)) : 
                reset($date_range);
        }

        echo '<div class="tts-analytics-summary">';
        echo '<div class="tts-summary-cards">';
        
        echo '<div class="tts-summary-card">';
        echo '<h3>' . esc_html__('Total Interactions', 'fp-publisher') . '</h3>';
        echo '<span class="tts-summary-number">' . number_format($total_interactions) . '</span>';
        echo '</div>';
        
        echo '<div class="tts-summary-card">';
        echo '<h3>' . esc_html__('Active Channels', 'fp-publisher') . '</h3>';
        echo '<span class="tts-summary-number">' . count($channel_totals) . '</span>';
        echo '</div>';
        
        echo '<div class="tts-summary-card">';
        echo '<h3>' . esc_html__('Top Channel', 'fp-publisher') . '</h3>';
        echo '<span class="tts-summary-text">' . ($top_channel ? esc_html($top_channel) : esc_html__('N/A', 'fp-publisher')) . '</span>';
        echo '</div>';
        
        echo '<div class="tts-summary-card">';
        echo '<h3>' . esc_html__('Date Range', 'fp-publisher') . '</h3>';
        echo '<span class="tts-summary-text">' . ($date_range_text ? esc_html($date_range_text) : esc_html__('No data', 'fp-publisher')) . '</span>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Retrieve unique available channels.
     *
     * @return array
     */
    private function get_available_channels() {
        $posts = get_posts(
            array(
                'post_type'      => 'tts_social_post',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            )
        );
        $channels = array();
        foreach ( $posts as $post_id ) {
            $ch = get_post_meta( $post_id, '_tts_social_channel', true );
            if ( is_array( $ch ) ) {
                $channels = array_merge( $channels, $ch );
            } elseif ( $ch ) {
                $channels[] = $ch;
            }
        }
        return array_unique( $channels );
    }

    /**
     * Get metrics data filtered by channel and date range.
     *
     * @param string $channel Channel filter.
     * @param string $start   Start date (Y-m-d).
     * @param string $end     End date (Y-m-d).
     *
     * @return array
     */
    private function get_metrics_data( $channel, $start, $end ) {
        $posts = get_posts(
            array(
                'post_type'      => 'tts_social_post',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'ASC',
            )
        );
        $data = array();

        foreach ( $posts as $post ) {
            $publish_at = get_post_meta( $post->ID, '_tts_publish_at', true );
            $date       = $publish_at ? substr( $publish_at, 0, 10 ) : get_the_date( 'Y-m-d', $post );

            if ( $start && strtotime( $date ) < strtotime( $start ) ) {
                continue;
            }
            if ( $end && strtotime( $date ) > strtotime( $end ) ) {
                continue;
            }

            $channels = get_post_meta( $post->ID, '_tts_social_channel', true );
            $channels = is_array( $channels ) ? $channels : array( $channels );
            if ( $channel && ! in_array( $channel, $channels, true ) ) {
                continue;
            }

            $metrics = get_post_meta( $post->ID, '_tts_metrics', true );
            if ( ! is_array( $metrics ) ) {
                continue;
            }

            foreach ( $metrics as $ch => $values ) {
                if ( $channel && $ch !== $channel ) {
                    continue;
                }
                $sum = $this->count_interactions( $values );
                if ( ! isset( $data[ $date ][ $ch ] ) ) {
                    $data[ $date ][ $ch ] = 0;
                }
                $data[ $date ][ $ch ] += $sum;
            }
        }

        ksort( $data );
        return $data;
    }

    /**
     * Recursively count numeric interactions in metrics array.
     *
     * @param array $data Metrics array.
     * @return int
     */
    private function count_interactions( $data ) {
        $sum = 0;
        foreach ( (array) $data as $value ) {
            if ( is_array( $value ) ) {
                $sum += $this->count_interactions( $value );
            } elseif ( is_numeric( $value ) ) {
                $sum += (int) $value;
            }
        }
        return $sum;
    }

    /**
     * Export data to CSV.
     *
     * @param array $data Metrics data.
     */
    private function export_csv( $data ) {
        nocache_headers();
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="tts-analytics.csv"' );
        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'date', 'channel', 'interactions' ) );
        foreach ( $data as $date => $channels ) {
            foreach ( $channels as $ch => $count ) {
                fputcsv( $output, array( $date, $ch, $count ) );
            }
        }
        fclose( $output );
    }
}
