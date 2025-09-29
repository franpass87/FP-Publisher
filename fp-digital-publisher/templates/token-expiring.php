<?php
/** @var array{tokens?: array<int, array<string, mixed>>} $context */
$tokens = isset($context['tokens']) && is_array($context['tokens']) ? $context['tokens'] : [];

echo "Attenzione: alcuni token FP Digital Publisher sono in scadenza entro 7 giorni.\n\n";

foreach ($tokens as $token) {
    $service = isset($token['service']) ? (string) $token['service'] : 'servizio';
    $account = isset($token['account_id']) ? (string) $token['account_id'] : 'account';
    $expires = isset($token['expires_at']) ? (string) $token['expires_at'] : 'data sconosciuta';
    $days = isset($token['days_left']) ? (int) $token['days_left'] : 0;

    echo sprintf('- %s (%s) scade il %s (%d giorni rimasti)' . "\n", $service, $account, $expires, $days);
}

echo "\nAggiornare le credenziali per evitare interruzioni della pubblicazione.";
