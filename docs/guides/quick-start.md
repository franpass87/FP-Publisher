# Percorso guidato · Setup rapido

Questa guida riassume i passaggi indispensabili per portare FP Publisher dallo stato "nuova installazione" alla pubblicazione del primo contenuto.

## 1. Preparazione account e credenziali
- Attiva gli account richiesti (Trello, Meta, Google, TikTok) e raccogli API key/token.
- Accedi a **Impostazioni → Connessioni social** per registrare app ID e secret.
- Verifica i permessi OAuth consigliati (Facebook/Instagram `pages_manage_posts`, YouTube `youtube.upload`, ecc.).

## 2. Scegli un pacchetto Quickstart
1. Vai in **Clienti → Pacchetti Quickstart**.
2. Seleziona il profilo più adatto (Starter Social, Editorial Suite, Enterprise Control). Puoi modificare la modalità attiva in **Impostazioni → Modalità di utilizzo** scegliendo tra Standard, Avanzato o Enterprise.
3. Usa il pulsante **Anteprima modifiche** per confrontare mapping Trello, template e parametri UTM con la configurazione corrente prima di applicare il preset.
4. Se il pacchetto mette a disposizione risorse aggiuntive, scarica il **template Trello** e apri il **percorso guidato** per seguire passo passo l'onboarding suggerito.
5. Apri il pannello **Validazione ambiente**: ogni preset esegue controlli automatici su credenziali, webhook e configurazione blog. Gli stati possibili sono:
   - 🟢 **Pronto**: puoi applicare il pacchetto senza ulteriori azioni.
   - 🟠 **Attenzione**: alcuni elementi opzionali mancano ma non bloccano l'import.
   - 🔴 **Bloccato**: risolvi i prerequisiti indicati prima di procedere.
6. Conferma l'applicazione per precompilare mapping Trello, template copy e parametri UTM.

> Suggerimento: dopo la validazione il pacchetto salva i risultati nella sessione. Il Client Wizard userà queste informazioni per mostrarti solo i passi che richiedono intervento.

## 3. Configura il primo cliente
1. Apri **Client Wizard**: la checklist ora evidenzia i blocchi critici, mostra percentuale di completamento e suggerisce le azioni da compiere in base alla validazione eseguita.
2. Inserisci API Trello (se abilitate) e scegli la board da sincronizzare. Usa il pulsante **Verifica credenziali Trello** per testare key/token prima di salvare: il risultato viene mostrato in tempo reale.
3. Seleziona i canali social, quindi compila le **Impostazioni blog**. Per ogni canale con OAuth completato puoi cliccare **Test token** e ricevere conferma immediata dell'accesso.
4. Completa la mappatura Trello → canali e conferma il riepilogo. Puoi azzerare il progresso con **Reset checklist** in caso di errori.

## 4. Esegui i controlli iniziali
- Dalla **Dashboard** verifica lo snapshot "Stato rapido di pubblicazione".
- Esegui **Test connessioni** per ogni canale con il widget dedicato.
- Consulta il pannello **System Status** per assicurarti che cron e webhook siano pianificati e controlla la sezione **Token social** per eventuali scadenze imminenti.

## 5. Pubblica il primo contenuto
1. Apri **Social Posts → Nuovo** per creare un post utilizzando i template preimpostati.
2. Carica media o allega contenuti Trello già mappati.
3. Salva, approva e pianifica la pubblicazione.

Con questi passaggi il team dispone di un flusso minimale ma funzionante. Per passare a scenari più complessi consulta il percorso "Operazioni giornaliere".
