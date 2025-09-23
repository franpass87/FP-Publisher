# Target Operating Model

The FP Publisher plugin is organized into four modular capabilities that cooperate through explicit contracts.
Each capability is driven by a dedicated PHP interface and exchanges typed value objects so the modules remain
loosely coupled and testable outside of WordPress.

## Core Workflow
- **Responsabilità principali**: riceve istruzioni di pubblicazione dal backoffice, calcola gli offset per canale,
  orchestra la coda di Action Scheduler e notifica la riuscita dei job.
- **Interfacce chiave**: `TTS_Scheduler_Interface` espone `queue_from_request()` e `release_schedule()` per gestire
  creazione/annullamento dei job usando il value object `TTS_Schedule_Request` e le cancellazioni
  `TTS_Schedule_Cancellation`.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-operating-contracts.php†L135-L190】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-scheduler.php†L26-L89】
- **Adattatore concreto**: `TTS_Scheduler` collega l'interfaccia all'ecosistema WordPress (hook `save_post`, Action Scheduler)
  e inoltra la telemetria quando disponibile.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-scheduler.php†L17-L126】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-scheduler.php†L428-L436】

## Integration Hub
- **Responsabilità principali**: normalizza la connessione verso CRM, e-commerce, strumenti di analytics e automazioni; pianifica e
  avvia sincronizzazioni; espone endpoint AJAX per configurare e testare le integrazioni.
- **Interfacce chiave**: `TTS_Integration_Gateway_Interface` definisce `dispatch_message()` per accodare o eseguire operazioni
  di sincronizzazione descritti dal value object `TTS_Integration_Message` che trasporta il contesto operativo e le
  richieste di credenziali opzionali.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-operating-contracts.php†L192-L248】
- **Adattatore concreto**: `TTS_Integration_Hub` implementa l'interfaccia orchestrando cron interni, coordinandosi con il
  provisioning credenziali e inviando eventi di osservabilità per ogni dispatch.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-integration-hub.php†L17-L77】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-integration-hub.php†L207-L240】

## Credential Provisioning
- **Responsabilità principali**: fornisce token o segreti da utilizzare durante sincronizzazioni e pubblicazioni, gestisce la
  rotazione dei segreti e la revoca quando richiesto.
- **Interfacce chiave**: `TTS_Credential_Provisioner_Interface` espone `issue_secret()` e `revoke_secret()` per ottenere
  segreti (`TTS_Credential_Secret`) a partire da una richiesta tipizzata (`TTS_Credential_Request`).【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-operating-contracts.php†L250-L327】
- **Adattatore concreto**: `TTS_Option_Credential_Provisioner` salva i segreti gestiti in `wp_options`, generando placeholder
  sicuri e ripulendo lo storage alla revoca.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-operating-contracts.php†L329-L396】

## Observability
- **Responsabilità principali**: centralizza metriche e log operativi provenienti dagli altri moduli e li mette a disposizione
  per dashboard e alerting.
- **Interfacce chiave**: `TTS_Observability_Channel_Interface` offre `record_event()` con payload `TTS_Observability_Event`
  che standardizza modulo, livello, messaggio e contesto degli eventi di telemetria.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-operating-contracts.php†L103-L133】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-operating-contracts.php†L398-L429】
- **Adattatore concreto**: `TTS_Logger_Observability_Channel` converte gli eventi in chiamate a `tts_log_event()` mantenendo la
  compatibilità con l'attuale sistema di log del plugin.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-operating-contracts.php†L398-L429】

## Interazioni tra moduli
| Origine | Contratto dati | Destinazione | Descrizione |
| --- | --- | --- | --- |
| Core Workflow | `TTS_Schedule_Request` | Observability | Emissione di eventi "queue" o "release" con dettagli su post e canali.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-scheduler.php†L91-L126】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-operating-contracts.php†L66-L133】 |
| Core Workflow | `TTS_Schedule_Cancellation` | Core Workflow | Permette la rimozione idempotente di job già programmati per specifici canali.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-scheduler.php†L107-L126】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-operating-contracts.php†L135-L190】 |
| Core Workflow | `TTS_Integration_Message` | Integration Hub | Richiede sincronizzazioni puntuali quando la pubblicazione deve propagarsi a sistemi esterni.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-scheduler.php†L428-L436】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-operating-contracts.php†L192-L248】 |
| Integration Hub | `TTS_Credential_Request` | Credential Provisioning | Prepara le credenziali necessarie prima di avviare chiamate a sistemi terzi.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-integration-hub.php†L207-L240】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-operating-contracts.php†L250-L327】 |
| Tutti i moduli | `TTS_Observability_Event` | Observability | Normalizza il tracciamento operativo per log e dashboard.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-operating-contracts.php†L66-L133】 |

Questa struttura consente di introdurre nuovi servizi (es. ulteriori canali social o nuove suite CRM) implementando semplicemente
un adattatore che soddisfi l'interfaccia del modulo destinatario, mantenendo invariati i contratti di comunicazione tra componenti.
