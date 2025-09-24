# Resilienza della pubblicazione

Questo documento descrive i controlli di resilienza del publisher TTS e come operare in caso di limiti o guasti dei canali.

## Coda asincrona per canale

* Ogni pubblicazione approvata viene scomposta in job per canale e accodata con `TTS_Channel_Queue` sull'hook `tts_process_channel_job`.
* Il meta `_tts_channel_retry_state` traccia stato, tentativi, prossimo retry e dettagli dell'errore per ogni canale.
* Il meta `_tts_queued_channels` elenca i canali pianificati; quando tutti risultano `completed` il post viene marcato come `published` e viene eseguita la chiusura del flusso (aggiornamento Trello, notifiche, ecc.).

## Configurazione limiti per canale

Le soglie vengono lette dall'opzione `tts_channel_limits` (array serializzato). È possibile impostarla via WP-CLI o tramite hook personalizzato. Ogni voce del dizionario ha la forma:

```php
update_option( 'tts_channel_limits', array(
    'facebook' => array(
        'max_pending' => 5,
        'rate_limits' => array(
            'requests_per_hour' => 120,
            'requests_per_day'  => 2500,
            'burst_limit'       => 30,
        ),
        'retry' => array(
            'strategy'     => 'progressive',
            'global_max'   => 4,
            'jitter'       => 90,
            'delays'       => array(
                'low'      => 5,
                'medium'   => 10,
                'high'     => 30,
                'critical' => 60,
            ),
            'max_attempts' => array(
                'low'      => 4,
                'medium'   => 3,
                'high'     => 2,
                'critical' => 1,
            ),
        ),
        'circuit' => array(
            'failure_threshold' => 3,
            'cooldowns' => array(
                'medium'   => 600,
                'high'     => 900,
                'critical' => 1800,
            ),
        ),
    ),
    'fallbacks' => array(
        'manual_publish_url' => 'https://intranet.example.com/manual-publishing-playbook'
    ),
) );
```

* `max_pending` limita i job in attesa per canale.
* `rate_limits` sovrascrive i limiti del `TTS_Rate_Limiter`.
* `retry` definisce strategia, ritardi (in minuti) e tentativi massimi per severità.
* `circuit` stabilisce soglia di apertura e durata di raffreddamento (secondi).
* Il blocco `fallbacks` può contenere URL o istruzioni aggiuntive per l'operatore.

## Circuit breaker e rate limiting

* `TTS_Publisher_Guard` interroga il `TTS_Rate_Limiter` prima di ogni chiamata al publisher.
* I fallimenti severi aprono il circuito per il canale; il meta della coda viene aggiornato a `awaiting_manual` e viene inviato un allarme (Slack + e-mail).
* I retry seguono la strategia definita per severità con jitter casuale per evitare thundering herd.

## Procedure operative

1. **Verifica stato coda** – utilizzare il comando diagnostico nel backoffice che chiama `TTS_Scheduler::check_queue()`. Viene mostrato il totale pending/failed per `tts_publish_social_post` e `tts_process_channel_job`.
2. **Sblocco circuito** – controllare l'opzione `tts_channel_circuit_breakers`; è possibile eliminare la voce del canale interessato per riaprire manualmente il circuito dopo aver risolto la causa.
3. **Pubblicazione manuale** – seguire il playbook indicato in `tts_channel_limits['fallbacks']['manual_publish_url']` e registrare l'esito nel log operativo.
4. **Reset stato job** – per riprocessare un canale rimuovere il sotto-array corrispondente da `_tts_channel_retry_state` e cancellare eventuali azioni pianificate con `as_unschedule_all_actions( 'tts_process_channel_job', ... )`.

## Monitoraggio

* Ogni job invia eventi sul canale di osservabilità `scheduler` (start, retry, success, error).
* I log pubblicati sono accessibili dalla pagina `fp-publisher-log` del post; includono eventuali URL ritornati dal canale o messaggi di errore.

## FAQ

**Il canale è bloccato per "circuit breaker attivo".**
: Controllare l'opzione `tts_channel_circuit_breakers` per conoscere il `open_until` e la severità dell'ultimo errore. Attendere lo sblocco automatico oppure intervenire manualmente seguendo il playbook.

**I retry non partono.**
: Verificare che l'opzione `tts_channel_limits` non imponga `max_pending` troppo basso e che l'azione cron `tts_process_retry_queue` del modulo di error recovery sia attiva.

**Come modifico temporaneamente i limiti?**
: Applicare un filtro `tts_rate_limiter_limits` o aggiornare `tts_channel_limits` tramite WP-CLI; i nuovi valori vengono applicati al volo a rate limiter e coda.
