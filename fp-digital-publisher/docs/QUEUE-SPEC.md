# FP Digital Publisher — Queue Specification

## Overview

La coda di FP Digital Publisher gestisce l'esecuzione dei job di pubblicazione e
degli step di follow-up (ad esempio commenti aggiuntivi). Tutti i job sono
persistiti nella tabella `wp_fp_pub_jobs` con metadati per stato, tentativi,
chiavi di idempotenza e relazioni padre/figlio.

## Stati supportati

| Stato        | Descrizione                                                     |
|--------------|-----------------------------------------------------------------|
| `pending`    | Job pianificato, in attesa che arrivi l'orario di esecuzione.    |
| `running`    | Job preso in carico dal worker e in corso di elaborazione.      |
| `completed`  | Job eseguito con successo, eventuale `remote_id` salvato.       |
| `failed`     | Job terminato con errore non recuperabile o superati i tentativi. |

## Idempotenza

Ogni job richiede una `idempotency_key` univoca per canale. A partire dalla fase
14 la chiave viene calcolata in modo deterministico a partire dal piano:

```
hash('sha256', brand + channel + scheduled_at + media_hash + caption_hash)
```

- `media_hash` deriva da checksum/reference degli asset associati al piano.
- `caption_hash` deriva dal corpo del template (eventuali override per canale) e
  dai placeholder effettivamente passati al connettore.
- Il worker converte `scheduled_at` in UTC per evitare collisioni dovute al
  fuso orario.

Se viene inviata una richiesta senza piano (es. job di manutenzione) è possibile
passare manualmente `idempotency_key`; in assenza di valore viene generato un
UUID come fallback. Se esiste già un job per lo stesso `channel + key` viene
restituito il record corrente senza creare duplicati.

## Scheduler

- Il worker (`fp_pub_tick`) gira su cron custom ad 1 minuto (fallback 5 minuti).
- `Services\Scheduler::getRunnableJobs()` recupera i job `pending` entro l'orario
  attuale, filtra quelli in blackout e applica i limiti di concorrenza per canale.
- I blackout sono configurabili tramite le opzioni `queue.blackout_windows`,
  con supporto per giorni specifici e fuso orario.
- Per ogni job selezionato viene effettuata una chiamata `Queue::claim()` che
  imposta lo stato su `running` ed incrementa il contatore dei tentativi in modo
  atomico.

## Retry & Backoff

- Il numero massimo di tentativi è definito da `queue.max_attempts` (default 5).
- Il backoff esponenziale utilizza i parametri `queue.retry_backoff` (`base`,
  `factor`, `max`) e applica un jitter casuale per evitare burst simultanei.
- `Queue::markFailed()` decide se ripianificare il job (`pending` con nuovo
  `run_at`) o segnare lo stato finale `failed` in base al flag `retryable` e al
  numero di tentativi effettuati.

## API REST di supporto

- `POST /fp-publisher/v1/jobs` consente di accodare rapidamente un job di test
  specificando canale, payload e data di esecuzione.
- `POST /fp-publisher/v1/jobs/test` verifica se un job potrebbe essere eseguito
  in un determinato orario (collisioni o blackout) senza inserirlo in coda.

## Estensioni

Il worker emette l'azione `fp_publisher_process_job` per ogni job preso in carico.
I connettori di canale implementati nelle fasi successive dovranno agganciarsi a
questo hook per processare i payload e aggiornare lo stato tramite i metodi
`Queue::markCompleted()` e `Queue::markFailed()`.
I payload con flag `preview` devono evitare chiamate ai provider esterni e
restituire un array normalizzato con le informazioni essenziali per le UI.

### Replay

- I job con stato `failed` possono essere ripianificati tramite `POST
  /fp-publisher/v1/jobs/{id}/replay`.
- Il replay azzera i tentativi, riposiziona `run_at` sull'orario corrente e
  mantiene la chiave di idempotenza per evitare duplicazioni lato provider.
- Se la pubblicazione precedente ha già restituito un `remote_id`, l'API di
  enqueue restituisce immediatamente il job esistente preservando lo stato.

### Catena primo commento Instagram

- I job di pubblicazione Instagram che includono `ig_first_comment` generano un
  job figlio `type=ig_first_comment` con idempotency key basata su media ID e
  hash del messaggio (`hash('sha256', media_id + commento_normalizzato)`).
- Prima di pubblicare il commento, il connettore verifica se esiste già un
  commento identico interrogando `/comments` e confrontando gli hash per evitare
  duplicazioni.
- Gli errori nella creazione del job figlio non invalidano la pubblicazione
  principale ma vengono segnalati tramite l'hook
  `fp_publisher_ig_first_comment_error`.

