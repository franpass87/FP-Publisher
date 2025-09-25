# Percorso guidato · Risoluzione problemi

Utilizza questa checklist quando emergono anomalie su token, webhook o pubblicazioni fallite.

## 1. Identifica il sintomo
- Widget **System Status**: nota quali componenti risultano ⚠️/🔴 e controlla la sezione **Token social** per le scadenze.
- La nuova sintesi **Componenti da monitorare** raggruppa token, webhook, quote API e cron mancanti indicando quante integrazioni sono coinvolte e fornendo link rapidi alla pagina corretta.
- Dashboard → **Azioni consigliate**: annota le remediation suggerite (es. rinnova token in scadenza, completa credenziali mancanti).
- Log → filtra per canale e data per raccogliere errori recenti.
- In **Pacchetti Quickstart** usa **Anteprima modifiche** per verificare se un preset ha sovrascritto mapping, template o UTM prima dell'errore.

## 2. Token e autenticazioni
1. Se il suggerimento indica "Aggiorna i token scaduti" o "Completa i token mancanti":
   - Apri **Connessioni social** e ripeti l'OAuth per il canale coinvolto.
   - Dal **Client Wizard** usa il pulsante **Test token** sul canale appena riconnesso per confermare l'accesso senza uscire dal flusso di onboarding.
   - In modalità Enterprise verifica anche il job pianificato `tts_refresh_tokens` nel riepilogo attività.
2. Per errori quota API:
   - Riduci temporaneamente la frequenza in **Publishing Status**.
   - Coordina con il team per distribuire i post in fasce orarie diverse.

## 3. Webhook e Trello
- Se il widget mostra errori webhook, apri Trello e rinnova il webhook dalla board collegata. Gli avvisi includono ora la lista e il canale coinvolti.
- In **Client Wizard** rivedi la mappatura Trello → canali assicurandoti che ogni lista abbia un canale valido.
- Controlla che il cron `tts_hourly_health_check` sia pianificato (sezione attività pianificate).

## 4. Problemi di scheduler o cron
- In **System Status** verifica le attività contrassegnate come "Non pianificata".
- Se necessario, salva nuovamente le impostazioni del plugin per forzare WordPress a rigenerare gli hook.
- Su hosting gestiti abilita WP-Cron o pianifica un cron esterno che richiami `wp-cron.php` ogni 5 minuti.

## 5. Escalation verso il team tecnico
- Genera un **System Report** dagli **Advanced Tools** (profilo Enterprise).
- Allegare il file `.log` scaricato da **Log → Esporta** con il canale interessato.
- Indica le azioni già tentate (reset token, riattivazione webhook, ripianificazione job).
- Allegare all'escalation anche l'output di `wp tts health --force` (che ora include la tabella *Stato componenti critici*) o `wp tts quickstart --slug=<preset>` se il problema deriva da un nuovo pacchetto.

> Consiglio: mantieni aggiornato il documento di Runbook interno collegando questa guida ai task di on-call.
