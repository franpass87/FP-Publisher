<?php

declare(strict_types=1);

namespace FP\Publisher\Admin;

use FP\Publisher\Infra\Capabilities;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Support\ContainerRegistry;
use FP\Publisher\Support\Channels;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Strings;

use function add_query_arg;
use function admin_url;
use function ceil;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function implode;
use function is_array;
use function number_format_i18n;
use function paginate_links;
use function selected;
use function submit_button;
use function sanitize_key;
use function sanitize_text_field;
use function wp_die;
use function wp_unslash;

final class QueuePage
{
    private const PER_PAGE = 20;

    public static function render(): void
    {
        if (! Capabilities::userCan('fp_publisher_view_logs')) {
            wp_die(esc_html__('You do not have permission to access the FP Publisher queue.', 'fp-publisher'));
        }

        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $channelParam = isset($_GET['channel']) ? sanitize_text_field($_GET['channel']) : '';
        $channel = Channels::normalize($channelParam);
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        /** @var \FP\Publisher\Support\Contracts\QueueInterface $queue */
        $queue = ContainerRegistry::get()->get(\FP\Publisher\Support\Contracts\QueueInterface::class);
        $result = $queue->paginate($page, self::PER_PAGE, [
            'status' => $status !== '' ? $status : null,
            'channel' => $channel !== '' ? $channel : null,
            'search' => $search !== '' ? $search : null,
        ]);

        $statuses = [
            '' => esc_html__('All statuses', 'fp-publisher'),
            Queue::STATUS_PENDING => esc_html__('Pending', 'fp-publisher'),
            Queue::STATUS_RUNNING => esc_html__('Running', 'fp-publisher'),
            Queue::STATUS_COMPLETED => esc_html__('Completed', 'fp-publisher'),
            Queue::STATUS_FAILED => esc_html__('Failed', 'fp-publisher'),
        ];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Queue overview', 'fp-publisher') . '</h1>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="fp-publisher-queue" />';
        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions">';
        echo '<label class="screen-reader-text" for="fp-publisher-queue-status">' . esc_html__('Filter by status', 'fp-publisher') . '</label>';
        echo '<select name="status" id="fp-publisher-queue-status">';
        foreach ($statuses as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($status, $value, false), esc_html($label));
        }
        echo '</select>';
        echo '<label class="screen-reader-text" for="fp-publisher-queue-channel">' . esc_html__('Filter by channel', 'fp-publisher') . '</label>';
        printf('<input type="text" name="channel" id="fp-publisher-queue-channel" value="%s" placeholder="%s" />', esc_attr($channel), esc_attr__('Channel slug…', 'fp-publisher'));
        submit_button(esc_html__('Filter'), '', 'filter_action', false);
        echo '</div>';
        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="fp-publisher-queue-search">' . esc_html__('Search jobs', 'fp-publisher') . '</label>';
        printf('<input type="search" id="fp-publisher-queue-search" name="s" value="%s" />', esc_attr($search));
        submit_button(esc_html__('Search'), '', 'search_action', false);
        echo '</p>';
        echo '</div>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        $headers = [
            esc_html__('ID', 'fp-publisher'),
            esc_html__('Status', 'fp-publisher'),
            esc_html__('Channel', 'fp-publisher'),
            esc_html__('Run at (UTC)', 'fp-publisher'),
            esc_html__('Attempts', 'fp-publisher'),
            esc_html__('Error', 'fp-publisher'),
        ];
        foreach ($headers as $header) {
            printf('<th scope="col">%s</th>', esc_html($header));
        }
        echo '</tr></thead><tbody>';

        if ($result['items'] === []) {
            echo '<tr><td colspan="6" class="no-items">' . esc_html__('No jobs found.', 'fp-publisher') . '</td></tr>';
        } else {
            foreach ($result['items'] as $job) {
                $runAt = Dates::format($job['run_at'], 'Y-m-d H:i');
                $error = $job['error'] !== null ? Strings::trimWidth((string) $job['error'], 120) : '';
                printf(
                    '<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    (int) $job['id'],
                    esc_html(ucfirst((string) $job['status'])),
                    esc_html((string) $job['channel']),
                    esc_html($runAt),
                    esc_html(number_format_i18n((int) $job['attempts'])),
                    esc_html($error)
                );
            }
        }

        echo '</tbody></table>';

        $totalPages = $result['per_page'] > 0 ? (int) ceil($result['total'] / $result['per_page']) : 1;
        if ($totalPages > 1) {
            $baseUrl = add_query_arg(
                [
                    'page' => 'fp-publisher-queue',
                    'status' => $status,
                    'channel' => $channel,
                    's' => $search,
                    'paged' => '%#%',
                ],
                admin_url('admin.php')
            );

            $links = paginate_links([
                'base' => $baseUrl,
                'format' => '',
                'current' => $result['page'],
                'total' => max(1, $totalPages),
                'type' => 'array',
            ]);

            if (is_array($links)) {
                echo '<div class="tablenav bottom"><div class="tablenav-pages">' . implode(' ', $links) . '</div></div>';
            }
        }

        echo '</form>';
        echo '</div>';
    }
}
