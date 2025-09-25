# Percorso guidato · Operazioni giornaliere

Questa traccia raccoglie le attività ricorrenti per mantenere operativo FP Publisher lungo la settimana. Se devi configurare un nuovo team o cliente parti dal [Percorso onboarding clienti](../journeys/client-onboarding.md) e torna qui per le attività di mantenimento.

## Ogni mattina
- Apri la **Dashboard**: controlla il widget "Stato rapido di pubblicazione", le nuove segnalazioni sui token social e le **Azioni consigliate**.
- Usa la sezione **Quick Actions** per accedere rapidamente al calendario o ai log.
- Verifica il widget **System Status** per assicurarti che i job pianificati siano ancora in stato 🟢.

> Suggerimento: adatta la UI al tuo team passando da **Impostazioni → Modalità di utilizzo**. Il profilo Standard riduce le informazioni visibili, mentre Avanzato ed Enterprise abilitano monitoraggio esteso e strumenti aggiuntivi.

## Pianificazione e produzione
1. **Calendario** → trascina i contenuti importati da Trello per assegnare data e ora.
2. **Social Posts** → approva o modifica i messaggi generati dai template.
3. Per i canali video (YouTube/TikTok) assicurati che i media rispettino i requisiti indicati in [SOCIAL_MEDIA_SETUP.md](../SOCIAL_MEDIA_SETUP.md).

## Monitoraggio
- Consulta la sezione **System Monitoring** (profilo Avanzato/Enterprise) per verificare performance DB, API e log errori.
- In modalità Standard, utilizza lo snapshot compatto e il log dei suggerimenti; i messaggi includono ora le remediation automatizzate per token mancanti, in scadenza o senza data di validità.
- Scarica i report giornalieri da **Analytics** per confrontare engagement e conversioni UTM.
- Se lavori da terminale esegui `wp tts health --force` per aggiornare lo snapshot prima della riunione mattutina e condividi le azioni consigliate con il team.

## Manutenzione settimanale
- In **Pacchetti Quickstart** esporta le impostazioni correnti prima di applicare nuove modifiche.
- Esegui il widget **Connection Testing** per rinnovare token prossimi alla scadenza. La checklist dei token dalla dashboard ti indica i clienti prioritari.
- Avvia gli strumenti in **Advanced Tools** (Enterprise) per backup, pulizia log e reportistica.

## Escalation
Se il widget System Status segnala attività non pianificate o errori ricorrenti:
1. Apri **Log** per identificare i canali coinvolti.
2. Consulta il percorso [Troubleshooting](troubleshooting.md) per le procedure di remediation.
3. Escala al team tecnico con il report generato automaticamente in **Advanced Tools → System Report**.
