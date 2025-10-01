<?php
/** @var array{tokens?: array<int, array<string, mixed>>} $context */
$tokens = isset($context['tokens']) && is_array($context['tokens']) ? $context['tokens'] : [];

printf(
    "%s\n\n",
    __('Warning: some FP Digital Publisher tokens will expire within 7 days.', 'fp-publisher')
);

foreach ($tokens as $token) {
    $service = isset($token['service']) ? (string) $token['service'] : __('service', 'fp-publisher');
    $account = isset($token['account_id']) ? (string) $token['account_id'] : __('account', 'fp-publisher');
    $expires = isset($token['expires_at']) ? (string) $token['expires_at'] : __('unknown date', 'fp-publisher');
    $days = isset($token['days_left']) ? (int) $token['days_left'] : 0;

    printf(
        /* translators: 1: service name, 2: account identifier, 3: expiry date, 4: days left */
        __('- %1$s (%2$s) expires on %3$s (%4$d days remaining)', 'fp-publisher') . "\n",
        $service,
        $account,
        $expires,
        $days
    );
}

printf(
    "\n%s",
    __('Update the credentials to prevent publishing interruptions.', 'fp-publisher')
);
