<?php
/** @var array{gaps?: array<int, array<string, string>>} $context */
$gaps = isset($context['gaps']) && is_array($context['gaps']) ? $context['gaps'] : [];

echo "Pianificazioni mancanti per la prossima settimana:\n\n";

foreach ($gaps as $gap) {
    $brand = isset($gap['brand']) ? (string) $gap['brand'] : 'brand';
    $channel = isset($gap['channel']) ? (string) $gap['channel'] : 'channel';
    $start = isset($gap['week_start']) ? (string) $gap['week_start'] : 'n/d';
    $end = isset($gap['week_end']) ? (string) $gap['week_end'] : 'n/d';

    echo sprintf('- %s · %s — intervallo %s → %s' . "\n", $brand, $channel, $start, $end);
}

echo "\nSuggerimento: pianificare almeno un contenuto per ciascun brand/canale nel periodo indicato.";
