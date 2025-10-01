<?php

declare(strict_types=1);

namespace FP\Publisher\Support\Cli;

use FP\Publisher\Infra\Queue;
use FP\Publisher\Services\Scheduler;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Strings;
use WP_CLI;
use WP_CLI_Command;

use function array_filter;
use function __;
use function ceil;
use function do_action;
use function is_string;
use function sprintf;

final class QueueCommand extends WP_CLI_Command
{
    public static function register(): void
    {
        if (! class_exists('\\WP_CLI')) {
            return;
        }

        WP_CLI::add_command('fp-publisher queue', static::class);
    }

    /**
     * List FP Publisher queue jobs.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter by job status (pending, running, completed, failed).
     *
     * [--channel=<channel>]
     * : Filter by channel slug (e.g. youtube, meta, wordpress).
     *
     * [--search=<term>]
     * : Search in the idempotency_key or error columns.
     *
     * [--page=<page>]
     * : Page to display (default 1).
     *
     * [--per-page=<per-page>]
     * : Number of items per page (default 20, maximum 100).
     */
    public function list_(array $args, array $assocArgs): void
    {
        $status = \WP_CLI\Utils\get_flag_value($assocArgs, 'status');
        $channel = \WP_CLI\Utils\get_flag_value($assocArgs, 'channel');
        $search = \WP_CLI\Utils\get_flag_value($assocArgs, 'search');
        $page = (int) \WP_CLI\Utils\get_flag_value($assocArgs, 'page', 1);
        $perPage = (int) \WP_CLI\Utils\get_flag_value($assocArgs, 'per-page', 20);

        $filters = array_filter([
            'status' => is_string($status) && $status !== '' ? $status : null,
            'channel' => is_string($channel) && $channel !== '' ? $channel : null,
            'search' => is_string($search) && $search !== '' ? $search : null,
        ]);

        $result = Queue::paginate($page, $perPage, $filters);
        $items = $result['items'];

        if ($items === []) {
            WP_CLI::log(__('No jobs matched the provided filters.', 'fp-publisher'));
            return;
        }

        $rows = [];
        foreach ($items as $job) {
            $rows[] = [
                'id' => $job['id'],
                'status' => $job['status'],
                'channel' => $job['channel'],
                'run_at' => Dates::format($job['run_at'], 'Y-m-d H:i'),
                'attempts' => $job['attempts'],
                'error' => $job['error'] !== null ? Strings::trimWidth((string) $job['error'], 80) : '',
        ];
        }

        \WP_CLI\Utils\format_items('table', $rows, ['id', 'status', 'channel', 'run_at', 'attempts', 'error']);

        $totalPages = $result['per_page'] > 0 ? (int) ceil($result['total'] / $result['per_page']) : 1;
        WP_CLI::log(sprintf(
            /* translators: 1: current page number, 2: total pages, 3: total jobs */
            __('Page %1$d of %2$d • Total jobs: %3$d', 'fp-publisher'),
            $result['page'],
            max(1, $totalPages),
            $result['total']
        ));
    }

    /**
     * Run due queue jobs immediately.
     *
     * ## OPTIONS
     *
     * [--limit=<limit>]
     * : Maximum number of jobs to process (default 10).
     */
    public function run(array $args, array $assocArgs): void
    {
        $limit = (int) \WP_CLI\Utils\get_flag_value($assocArgs, 'limit', 10);
        $limit = $limit > 0 ? $limit : 1;

        $now = Dates::now('UTC');
        $jobs = Scheduler::getRunnableJobs($now, $limit);

        if ($jobs === []) {
            WP_CLI::success(__('No runnable jobs found.', 'fp-publisher'));
            return;
        }

        $processed = 0;
        foreach ($jobs as $job) {
            /** @var array<string, mixed> $job */
            do_action('fp_publisher_process_job', $job);
            $processed++;
        }

        WP_CLI::success(sprintf(
            /* translators: %d: number of processed jobs */
            __('%d jobs processed.', 'fp-publisher'),
            $processed
        ));
    }
}
