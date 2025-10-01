<?php
/** @var array{gaps?: array<int, array<string, string>>} $context */
$gaps = isset($context['gaps']) && is_array($context['gaps']) ? $context['gaps'] : [];

printf(
    "%s\n\n",
    __('Missing schedules for next week:', 'fp-publisher')
);

foreach ($gaps as $gap) {
    $brand = isset($gap['brand']) ? (string) $gap['brand'] : __('brand', 'fp-publisher');
    $channel = isset($gap['channel']) ? (string) $gap['channel'] : __('channel', 'fp-publisher');
    $start = isset($gap['week_start']) ? (string) $gap['week_start'] : __('n/a', 'fp-publisher');
    $end = isset($gap['week_end']) ? (string) $gap['week_end'] : __('n/a', 'fp-publisher');

    printf(
        /* translators: 1: brand name, 2: channel, 3: start date, 4: end date */
        __('- %1$s · %2$s — range %3$s → %4$s', 'fp-publisher') . "\n",
        $brand,
        $channel,
        $start,
        $end
    );
}

printf(
    "\n%s",
    __('Tip: schedule at least one post for each brand/channel in the indicated period.', 'fp-publisher')
);
