# Guida all'aggiornamento – FP Publisher 1.2.0

Questa guida descrive il passaggio dalla versione 1.1.0 (o precedenti) alla 1.2.0 del plugin FP Publisher.

## Prerequisiti
- WordPress 6.0 o superiore.
- PHP 8.1 con `curl`, `json`, `mbstring` e `openssl` abilitati.
- Backup recente del database e del file system.
- Possibilità di eseguire comandi WP-CLI (consigliato) o accesso all'area di amministrazione.

## 1. Prepara l'ambiente
1. Metti il sito in modalità manutenzione se necessario.
2. Esegui un backup completo del database e della directory `wp-content/uploads`.
3. Verifica di avere spazio sufficiente per generare i log runtime (`wp-content/uploads/tts-runtime-logs/`).

## 2. Aggiorna i file del plugin
1. Scarica il pacchetto [`dist/fp-publisher-1.2.0.zip`](dist/fp-publisher-1.2.0.zip).
2. Confronta il checksum con:
   ```bash
   sha256sum dist/fp-publisher-1.2.0.zip
   cat dist/fp-publisher-1.2.0.zip.sha256
   ```
   I valori devono coincidere.
3. In WordPress vai su **Plugin → Aggiungi nuovo → Carica plugin** e carica lo ZIP, oppure estrai il contenuto e sovrascrivi la cartella `wp-content/plugins/trello-social-auto-publisher/` via SFTP/SSH.

## 3. Esegui l'upgrade delle opzioni
1. Accedi alla dashboard WordPress e visita qualsiasi pagina amministrativa del plugin.
2. FP Publisher esegue automaticamente `TTS_Plugin_Upgrades`, che:
   - Mantiene l'allineamento delle opzioni multisite e aggiorna la chiave `tts_plugin_version` alla 1.2.0.
   - Pulisce le cache runtime (Object Cache, OPcache, transients delle statistiche) per caricare il nuovo bundle admin.
   - Convalida la nuova IA dei menu tramite `TTS_Admin_Menu_Registry` e registra eventuali avvisi se gli slug legacy vengono usati.
3. Se utilizzi WP-CLI, puoi forzare l'esecuzione con:
   ```bash
   wp eval 'TTS_Plugin_Bootstrap::get_instance()->maybe_run_upgrades();'
   ```

## 4. Verifica post-aggiornamento
1. Apri **Dashboard → FP Publisher → Runtime Logs** e assicurati che il file JSON venga aggiornato senza errori.
2. Verifica le schermate refittate (Dashboard, Registri attività, Social Posts, Impostazioni) controllando notice, tab e bulk action.
3. Esegui i test automatici se disponibili:
   ```bash
   composer install
   composer test
   ```
4. Controlla la pagina **FP Publisher → Impostazioni** per verificare che tutte le credenziali siano presenti e, in multisite, condivise tra i siti.
5. Ripristina il sito dalla modalità manutenzione.

## 5. Rollback (facoltativo)
- Per tornare alla 1.1.0 reinstalla il pacchetto precedente e ripristina il backup del database.
- Elimina eventuali file `wp-content/uploads/tts-runtime-logs/*.json` generati dalla 1.2.0 se non desideri mantenerli.

## Note importanti
- Il logger runtime può essere disabilitato tramite il filtro `tsap_runtime_logger_enabled` se non necessario in produzione.
- I vecchi script `tests/test-*.php` rimangono supportati ma i nuovi test risiedono in `tests/phpunit/` e richiedono PHPUnit ≥9.
- Durante l'upgrade vengono ricalcolati i conteggi delle sorgenti di contenuto e aggiornate le cache: su installazioni con molti post l'operazione può richiedere alcuni minuti.
- Il registry dei menu mantiene compatibilità con gli slug storici (`admin.php?page=tts_*`); aggiorna eventuali segnalibri o documentazione interna per puntare ai nuovi identificatori descritti in `docs/admin-ui/menu-registry.md`.
- Gli stili admin includono indicatori di focus ad alto contrasto e il nuovo layer di componenti. Se il tema applica override personalizzati, assicurati di non rimuovere `outline`, `box-shadow` o le classi `tts-components` caricate dal bundle `tts-components.css`.
