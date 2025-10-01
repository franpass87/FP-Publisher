<?php
/** @var array{jobs?: array<int, array<string, mixed>>} $context */
$jobs = isset($context['jobs']) && is_array($context['jobs']) ? $context['jobs'] : [];

printf(
    "%s\n\n",
    __('Failed publishing jobs from the last 7 days:', 'fp-publisher')
);

foreach ($jobs as $job) {
    $id = isset($job['id']) ? (int) $job['id'] : 0;
    $channel = isset($job['channel']) ? (string) $job['channel'] : __('channel', 'fp-publisher');
    $runAt = isset($job['run_at']) ? (string) $job['run_at'] : __('n/a', 'fp-publisher');
    $attempts = isset($job['attempts']) ? (int) $job['attempts'] : 0;
    $error = isset($job['error']) ? (string) $job['error'] : '';

    printf(
        /* translators: 1: job id, 2: channel, 3: run datetime, 4: attempts, 5: error */
        __('- Job #%1$d (%2$s) scheduled for %3$s · attempts: %4$d\n  Error: %5$s\n', 'fp-publisher') . "\n",
        $id,
        $channel,
        $runAt,
        $attempts,
        $error
    );
}

printf(
    "%s",
    __('Review the failures and consider replaying jobs from the admin panel.', 'fp-publisher')
);
