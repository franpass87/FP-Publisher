# Changelog

Tutte le modifiche rilevanti al plugin FP Digital Publisher saranno documentate in questo file.

## [Unreleased]
- Avviata implementazione playbook Codex con tracciamento stato e branch dedicato.
- Ampliate suite di test PHPUnit con stub WordPress e client fittizi per coprire coda, scheduler, dispatcher canali e supporto.
- Preparata suite d'integrazione WordPress con script di bootstrap, test su attivazione/cron/capabilities/rewrite e workflow CI dedicato.
- Completata l'esecuzione locale della suite WP con installer basato su sorgenti GitHub, configurazione MariaDB e callback plugin compatibili con l'ambiente di test.
- Introdotto `TransientErrorClassifier` per distinguere errori transitori WP/API e aggiornati i dispatcher con test su deadlock, timeout e codici HTTP 4xx/5xx.
- Consolidato l'housekeeping del database con tabella archivio, indici ottimizzati, retention configurabile e cron giornaliero che archivia job e ripulisce asset scaduti.
- Evoluto l'ingest Trello con selezione multipla delle card, supporto OAuth facoltativo, anteprima via REST e modal dedicata che importa le bozze aggiornando automaticamente il calendario.
- Completata la localizzazione: dominio testuale `fp-publisher`, stringhe admin/SPA in inglese, tooling WP-CLI per generare POT/PO e pacchetto `it_IT` mantenuto sui sorgenti (POT/PO) con compilazione MO demandata alla fase di build.
- Aggiunta documentazione strutturata: guide utente per calendario/approvazioni/alert, guide developer su architettura/DB/QA e FAQ di supporto collegate al README.
- Inseriti i nuovi hook `fp_pub_payload_pre_send`, `fp_pub_retry_decision` e l'azione `fp_pub_published`, con documentazione aggiornata e test a copertura dell'estendibilità.
- Reso l'asset pipeline più resiliente con cron orario che elimina file e record scaduti e cache dei termini di categorie/tag con TTL configurabile per ridurre le query WordPress.
- Rimossi artefatti generati dal controllo versione (es. `composer.lock`) e aggiornate le regole di ignore per evitare il tracciamento di file binari.

- Automatizzato il rilascio con workflow GitHub Actions che copre test PHP 8.1-8.3, lint PHPCS e crea lo ZIP tramite script dedicato.

- Verificato il completamento del playbook Codex: tutti gli step risultano chiusi e pronti per la PR finale.
## [0.1.0] - In sviluppo
- Impostazione iniziale del progetto.
- Connettore Meta con pubblicazione Facebook/Instagram, anteprima payload e primo commento automatico.
- Connettore Google Business Profile con gestione OAuth, elenco sedi e pubblicazione post con CTA e media.
- Publisher WordPress con gestione multisite, template dinamici, categorie/tag/featured e builder UTM.
- Motore di templating avanzato con preset UTM per canale, servizio di preflight qualità e API REST di verifica pianificazioni.
- Asset pipeline con upload diretto Meta/TikTok/YouTube o fallback locale con pulizia programmata, validatori media e ingest Trello per creare piani draft da board/list.
- Interfaccia calendario/kanban con suggerimenti best-time, workflow approvazioni commentabile e nuove rotte REST per status e commenti.
- Smart alerts quotidiani/settimanali, servizio short link con rewrite `/go/`, replay job falliti via REST e documentazione connettori/UTM aggiornata.
