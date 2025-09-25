# Percorso onboarding clienti

Questa guida aiuta a trasformare i pacchetti Quickstart in una configurazione completa e pronta all'uso. Ogni percorso combina la board Trello consigliata, la checklist del Client Wizard e le verifiche automatiche di FP Publisher.

## Prima di iniziare

1. Apri **Clienti → Pacchetti Quickstart** e scegli il preset più vicino al tuo modo di lavorare.
2. Usa il pulsante **Scarica template Trello** per importare la board suggerita su Trello (File → Importa JSON → Modello di board).
3. Premi **Anteprima modifiche** per verificare mapping, template e UTM che verranno precompilati.
4. Dopo aver applicato il pacchetto, avvia il **Client Wizard**: ritroverai checklist, hint e sessione precompilata.

> Suggerimento: puoi riaprire il percorso guidato direttamente dalla card del pacchetto o dalla pagina **Documentazione → Percorsi guidati**.

## Starter Social

- **Obiettivo**: avviare un nuovo brand con due canali social e pubblicazione blog semplificata.
- **Importazione Trello**: il template include le liste *Idee → In approvazione → Pronto → Pubblicato* con etichette Facebook/Instagram.
- **Wizard step-by-step**:
  1. Inserisci API key/token Trello, usa **Verifica credenziali Trello** e seleziona la board appena importata.
  2. Collega Facebook e Instagram dal passo "Canali": le etichette della board aiutano a capire dove finiranno le card. Dopo l'OAuth puoi cliccare **Test token** per verificare immediatamente la connessione.
  3. Verifica la mappatura proposta (*Idee → draft*, *Pronto → scheduled* ecc.) e conferma la checklist.
  4. Rivedi i template social generati e completa il riepilogo.
- **Checklist finale**: assicurati che tutti i canali siano marcati come "connessi"; se mancano token il widget suggerirà i remediation dal monitoraggio.

## Editorial Suite

- **Obiettivo**: coordinare blog, video lunghi e short form per team editoriali.
- **Importazione Trello**: la board propone colonne *Briefing*, *Produzione*, *Revisione*, *Pronto Social*, *Pubblicato* e un archivio dedicato.
- **Wizard step-by-step**:
  1. Nella sezione Trello seleziona la board e aggiungi checklist automatiche per script, grafiche e SEO.
  2. Autorizza YouTube e TikTok oltre a Facebook/Instagram; usa **Test token** per convalidare le autorizzazioni e annota eventuali avvisi nella checklist.
  3. Nel passo "Mapping" verifica che la lista *Pronto Social* sia agganciata allo stato **scheduled** per i canali social, mentre il blog resta collegato alle altre liste.
  4. Incolla le impostazioni blog suggerite (post pending, autore dedicato, categoria editoriale) e controlla l'anteprima prima del salvataggio.
- **Ottimizzazioni**: attiva il calendario editoriale e usa il pulsante **Apri percorso guidato** per rivedere la sequenza in futuro.

## Enterprise Control

- **Obiettivo**: gestire workflow complessi con audit trail, compliance e più approvatori.
- **Importazione Trello**: la board aggiunge liste *Intake*, *Quality Assurance*, *Security Check*, *Ready to Launch*, *Live + Audit* con etichette per legal/compliance.
- **Wizard step-by-step**:
  1. Configura gli utenti WordPress richiesti dal preset (autore #5 o ruolo dedicato) prima di confermare l'anteprima.
  2. Collega tutti i canali richiesti: la checklist evidenzia quali integrazioni non hanno token o scadenze registrate e i pulsanti **Test token** aiutano a certificare gli accessi enterprise.
  3. Nel passo Trello, abbina le liste di audit agli stati **review** e **scheduled** per mantenere il controllo delle approvazioni.
  4. Completa il riepilogo e registra nel campo note i referenti compliance: verranno riutilizzati nei log e nei report.
- **Monitoraggio continuativo**: consulta il widget **System Status** per ricevere remediation automatiche e programma report giornalieri dal menu Monitoraggio.

## Collegamenti utili

- [Guida Quickstart](../guides/quick-start.md)
- [Operatività quotidiana](../guides/daily-operations.md)
- [Troubleshooting e remediation](../guides/troubleshooting.md)

Per feedback o suggerimenti sui percorsi guidati apri una issue nel repository oppure compila la sezione feedback interna del plugin.
