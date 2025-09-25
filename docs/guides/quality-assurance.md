# Percorso guidato · Quality Assurance automatizzata

Questo percorso illustra come mantenere alta la qualità del plugin automatizzando i test e integrandoli nei flussi di lavoro quotidiani.

## Obiettivi del percorso
- Validare rapidamente le funzionalità chiave (token, API Trello, sicurezza, REST) prima di ogni rilascio.
- Offrire un flusso unificato per team di sviluppo e operations, riducendo errori manuali.
- Documentare l'integrazione con GitHub Actions e pipeline esterne.

## Prerequisiti
- PHP 8.1 o superiore disponibile in locale o nell'ambiente CI.
- [Composer](https://getcomposer.org/) installato.
- Accesso al repository FP Publisher.

## Eseguire i test in locale
1. Clona il repository e posizionati nella root del progetto.
2. Lancia il comando:

   ```bash
   composer test
   ```

3. Il runner `tools/run-tests.sh` avvia tutti i file `tests/test-*.php` e interrompe la procedura se uno dei check restituisce exit code diverso da zero.
4. In caso di fallimento, il terminale mostra quale file ha generato l'errore insieme ai messaggi restituiti dai test.

> Suggerimento: esegui `composer test` prima di aprire una pull request per intercettare regressioni senza attendere la CI.

## Monitorare i test con GitHub Actions
- Il workflow **Plugin Quality Checks** (`.github/workflows/test-suite.yml`) gira automaticamente su push, pull request e avvi manuali.
- Lo step `composer validate` assicura che la configurazione Composer sia coerente.
- Lo step `composer test` replica il comportamento locale, così lo stato della pipeline riflette esattamente ciò che avviene in sviluppo.
- Puoi ricevere notifiche automatiche (email, Slack, Teams) configurando i notificatori di GitHub Actions.

## Integrazione in pipeline esterne
1. Aggiungi uno step `composer test` ai tuoi workflow di deploy (GitLab CI, Jenkins, Bitbucket Pipelines) prima della fase di rilascio.
2. Imposta la pipeline in modo che si interrompa se il comando restituisce exit code diverso da zero.
3. Salva gli output dei test come artifact o log centralizzato per agevolare auditing e incident response.
4. Facoltativo: esegui `composer validate --no-check-publish` per assicurarti che i file Composer siano consistenti anche fuori da GitHub.

## Troubleshooting rapido
- **Errore "Test directory not found"**: assicurati di lanciare il comando dalla root del repository e che il plugin contenga la cartella `wp-content/plugins/trello-social-auto-publisher/tests`.
- **PHP non trovato**: verifica che `php` sia presente nel `PATH` della macchina o sostituiscilo con il percorso completo all'eseguibile.
- **Permessi su `tools/run-tests.sh`**: se su sistemi Unix ricevi un errore di permessi, esegui `chmod +x tools/run-tests.sh`.

Seguendo questo percorso mantieni allineati sviluppo, QA e operations, ottenendo feedback immediato sulle regressioni e aumentando la confidenza in ogni rilascio del plugin.
