<?php
/** @var array{jobs?: array<int, array<string, mixed>>} $context */
$jobs = isset($context['jobs']) && is_array($context['jobs']) ? $context['jobs'] : [];

echo "Riepilogo job di pubblicazione falliti negli ultimi 7 giorni:\n\n";

foreach ($jobs as $job) {
    $id = isset($job['id']) ? (int) $job['id'] : 0;
    $channel = isset($job['channel']) ? (string) $job['channel'] : 'channel';
    $runAt = isset($job['run_at']) ? (string) $job['run_at'] : 'n/d';
    $attempts = isset($job['attempts']) ? (int) $job['attempts'] : 0;
    $error = isset($job['error']) ? (string) $job['error'] : '';

    echo sprintf("- Job #%d (%s) programmato per %s · tentativi: %d\n  Errore: %s\n\n", $id, $channel, $runAt, $attempts, $error);
}

echo "Eseguire un controllo e valutare il replay dal pannello amministrativo.";
