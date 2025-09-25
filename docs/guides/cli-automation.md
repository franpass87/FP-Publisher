# Percorso guidato · Automazione da riga di comando

Questa guida raccoglie gli scenari operativi per utilizzare i comandi WP-CLI di FP Publisher in modo sicuro e ripetibile. Puoi integrare questi step nei runbook di manutenzione, nei cron server-side o in pipeline CI/CD per validare l'ambiente senza dover accedere alla dashboard WordPress.

## Prerequisiti
- Accesso SSH al server con WordPress installato.
- WP-CLI configurato e funzionante (`wp --info`).
- Utente WordPress con capability `manage_options` per eseguire i comandi.

## Controllare lo stato del sistema
Esegui il comando `health` per ottenere lo stato aggregato di database, token e API.

```bash
wp tts health --path=/var/www/html
```

Output chiave:
- **Punteggio salute** (0–100) basato sui check principali.
- Tabella con stato (`ok`, `warning`, `blocked`) e messaggio per ogni componente monitorato.
- Tabella delle **Azioni consigliate** con link diretti alle pagine WP-Admin da visitare.

> Suggerimento: schedula `wp tts health --force` ogni ora tramite cron per rigenerare lo snapshot e archivia l'output in un log centralizzato.

## Validare un pacchetto Quickstart
Prima di applicare un preset da interfaccia grafica verifica che tutti i prerequisiti siano soddisfatti:

```bash
wp tts quickstart --slug=enterprise_control --path=/var/www/html
```

Il comando restituisce:
- Profilo richiesto vs profilo attivo, con avviso se non combaciano.
- Tabella dei prerequisiti (token mancanti, Trello disattivato, mapping non configurato).
- Elenco delle modifiche che il pacchetto introdurrebbe (mapping Trello, template social, parametri UTM, prefill blog).

Per ottenere l'elenco degli slug disponibili:

```bash
wp tts quickstart --list
```

## Integrazione in pipeline CI/CD
1. Aggiungi uno step al deploy che esegue `wp tts health --force`. Fallisci la pipeline se il punteggio scende sotto una soglia (es. 70) analizzando l'output testuale o parseando la tabella con uno script dedicato.
2. Inserisci `wp tts quickstart --slug=<preset>` in stage di QA per garantire che l'ambiente di staging sia pronto prima di testare un nuovo onboarding.
3. Notifica su Slack: pipe l'output dei comandi verso uno script che invia un messaggio al canale on-call quando vengono rilevate azioni consigliate con severità `warning`.

## Best practice
- Versiona i comandi CLI nei runbook interni così da allineare il team sulle operazioni supportate.
- Esegui i comandi con il flag `--path` esplicito quando lavori su installazioni con più siti.
- Se automatizzi tramite cron, redirigi `STDOUT` e `STDERR` verso file diversi per facilitare l'analisi degli errori.
- Mantieni WP-CLI aggiornato per beneficiare delle ultime patch di sicurezza e compatibilità.

Con questi strumenti puoi monitorare lo stato della piattaforma e convalidare le configurazioni Quickstart senza accedere manualmente al backend, riducendo tempi di intervento e rischio di errore umano.
