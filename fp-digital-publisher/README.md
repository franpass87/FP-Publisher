# FP Digital Publisher

FP Digital Publisher è un plugin WordPress progettato per orchestrare campagne editoriali su canali social e owned media con un approccio modulare e scalabile.

## Table of contents

- [Requisiti](#requisiti)
- [Installazione](#installazione)
- [Delta della fase](#delta-della-fase)
- [Sviluppo locale](#sviluppo-locale)
- [Testing](#testing)
- [UI Polish Delta](#ui-polish-delta)
- [UI Kit](#ui-kit)
- [Documentazione](#documentazione)
- [FAQ](#faq)
- [Release process](#release-process)

## Requisiti
- WordPress >= 6.4
- PHP >= 8.1

## Installazione
1. Clonare il repository nella directory `wp-content/plugins/`.
2. Installare le dipendenze PHP tramite Composer (in fasi successive).
3. Attivare il plugin dalla dashboard di WordPress.

## Delta della fase
- Fase 0: bootstrap del plugin, struttura iniziale del progetto e autoloader PSR-4.
- Fase 1: gestione opzioni sicure, ruoli/capacità dedicate e infrastruttura i18n con avvisi critici.
- Fase 2: migrazioni database per coda, asset, piani, token, commenti e short-link con documentazione schema.
- Fase 3: shell SPA amministrativa con menu dedicato, asset sorgente e namespace REST iniziale.
- Fase 4: modelli dominio pianificazione, utility di supporto (array/date/validazione/sicurezza/http) e stub test unitari.
- Fase 5: servizio scheduler con coda resilienti, worker cron, API REST di enqueue/test e specifica tecnica della queue.
- Fase 6: connettore Meta (Facebook/Instagram) con modalità anteprima e catena primo commento IG.
- Fase 7: connettore TikTok con upload chunked, gestione token/refresh e pubblicazione via coda.
- Fase 8: connettore YouTube con upload resumable, gestione Shorts, scheduling tramite publishAt ed errori normalizzati.
- Fase 9: connettore Google Business Profile con post WHAT'S NEW/EVENT/OFFER, gestione media e CTA, elenco sedi e token refresh.
- Fase 10: publisher WordPress con templating titolo/slug/estratto, UTM builder, categorie/tag/featured e supporto multisite.
- Fase 11: motore template con placeholder contestuali, preset UTM per canale, servizio di preflight con punteggio qualità e blocco scheduling da REST.
- Fase 12: asset pipeline con upload diretto verso Meta/YouTube/TikTok o fallback locale sicuro con TTL, validatori media per ratio/durata/bitrate e ingest Trello per generare piani draft da board/list selezionate.
- Fase 13: interfaccia calendario/kanban con suggerimenti orari, workflow approvazioni con commenti menzionabili e nuove API REST dedicate.
- Fase 14: smart alerts giornalieri/settimanali, servizio short link con rewrite `/go/`, replay job falliti con idempotenza estesa e nuova documentazione di hardening.

## Sviluppo locale
- Eseguire `composer install` per predisporre il bootstrap dei test PHP.
- Configurare un ambiente WordPress >= 6.4 e attivare il plugin dalla dashboard.
- Per l'ambiente JavaScript sono disponibili esclusivamente asset sorgente in `assets/`; eventuali build devono restare locali (non committate).

## Testing
- `composer validate` per verificare la correttezza del `composer.json`.
- `composer test` (dopo `composer install`) per avviare gli stub di test PHP con bootstrap e output TestDox (`./vendor/bin/phpunit --bootstrap tests/bootstrap.php --testdox tests`).

## Operatività
- Logging strutturato: tutte le code e i connettori utilizzano un logger PSR-3 centralizzato (`Support\Logging\Logger`) che arricchisce i messaggi con contesto (ID job, canale, stato HTTP) instradandoli verso `error_log`.
- WP-CLI: disponibili i comandi `wp fp-publisher queue list` (filtri per stato, canale, ricerca) e `wp fp-publisher queue run --limit=<n>` per processare manualmente i job schedulati.
- Dashboard coda: voce di menu **Queue** con tabella paginata, filtri e ricerca per indagare rapidamente job falliti o in attesa.
- Timeout e retry configurabili: nuove chiavi `integrations.http` e `integrations.queue` nelle opzioni permettono override per canale di timeout, backoff e strategie di retry.

## UI Polish Delta
- Fase U0: introdotti design token condivisi, palette e reset controlli di base per l'SPA amministrativa.
- Fase U1: aggiunti componenti riutilizzabili (badge di stato, toolbar sticky, empty state, skeleton, tooltip, modal e toast host) con styling coerente e bus notifiche minimale.
- Fase U2: migliorata la pagina Calendario con toggle densità, schede drag con handle, skeleton di caricamento, azione "Suggerisci orario" e CTA "Importa da Trello" per le giornate vuote.
- Fase U3: arricchito il Composer con stepper di avanzamento, chip Preflight interattivo, anteprima hashtag nel primo commento e validazioni inline con tooltip bloccanti.
- Fase U4: introdotto workflow approvazioni con timeline, CTA "Approva e invia", gestione richieste modifiche e commenti con menzioni @ e annunci aria-live.
- Fase U5: rifinite le viste Alert & Log con tab tematiche, filtri brand/canale, azioni contestuali e copia rapida di payload e stack con badge di stato.
- Fase U6: rinnovata la gestione Short Link con tabella compatta, menu azioni (apri/copia/modifica/disattiva) e modal di creazione/modifica con validazione URL e anteprima UTM.
- Fase U7: introdotto focus outline coerente, attributi aria-expanded/controls sui toggle, annunci polite per Preflight e Toast, oltre a centralizzare le stringhe UI Short Link/Composer nelle funzioni di traduzione.
- Fase U8: aggiunta vetrina demo dei componenti UI, guida documentale e sezione README “UI Kit” con snippet di utilizzo.

## UI Kit

La libreria UI dell’admin SPA offre token condivisi e componenti accessibili riutilizzabili. Di seguito alcuni esempi rapidi.

### StatusBadge

```tsx
import StatusBadge from 'assets/ui/components/StatusBadge';

export const Example = () => <StatusBadge status="approved" />;
```

_Screenshot: badge di stato nelle varianti principali_

### StickyToolbar + DensityToggle

```tsx
import { useState } from 'react';
import StickyToolbar from 'assets/ui/components/StickyToolbar';
import DensityToggle from 'assets/ui/components/DensityToggle';

export const ToolbarExample = () => {
  const [mode, setMode] = useState<'compact' | 'comfort'>('comfort');

  return (
    <StickyToolbar>
      <h2>Elenco contenuti</h2>
      <DensityToggle mode={mode} onChange={setMode} />
    </StickyToolbar>
  );
};
```

_Screenshot: toolbar con toggle densità sopra un elenco_

### EmptyState, SkeletonCard e ToastHost

```tsx
import EmptyState from 'assets/ui/components/EmptyState';
import SkeletonCard from 'assets/ui/components/SkeletonCard';
import ToastHost, { pushToast } from 'assets/ui/components/ToastHost';

export const FeedbackExample = () => (
  <>
    <ToastHost placement="top-end" />
    <SkeletonCard lines={3} />
    <EmptyState
      title="Nessun elemento"
      primaryAction={{
        label: 'Crea',
        onClick: () => pushToast({ title: 'Creato!' }),
      }}
    />
  </>
);
```

_Screenshot: skeleton, empty state e toast host in pagina_

### Modal e Tooltip

```tsx
import { useState } from 'react';
import Tooltip from 'assets/ui/components/Tooltip';
import Modal from 'assets/ui/components/Modal';

export const ModalExample = () => {
  const [open, setOpen] = useState(false);

  return (
    <>
      <Tooltip content="Apri dettaglio">
        <button type="button" onClick={() => setOpen(true)}>
          Dettagli
        </button>
      </Tooltip>
      <Modal isOpen={open} onDismiss={() => setOpen(false)} title="Anteprima">
        …
      </Modal>
    </>
  );
};
```

_Screenshot: bottone con tooltip e modale aperta_

## Documentazione

- [Guide utente](docs/user/) – configurazione connettori, calendario editoriale, workflow approvazioni, replay, short link e alert.
- [Guide developer](docs/dev/) – panoramica architetturale, schema database, QA checklist e catalogo hook.
- [FAQ](docs/faq.md) – risposte rapide a problemi comuni su token, pubblicazioni e permessi.

## Release process

1. Aggiorna la versione del plugin con `bash build.sh --bump=patch` oppure imposta manualmente con `bash build.sh --set-version=1.2.3`.
2. Recupera lo ZIP generato in `build/` (nome: `fp-digital-publisher-<timestamp>.zip`) e caricalo nell'installazione WordPress.
3. In alternativa, crea un tag `vX.Y.Z` su GitHub per ottenere automaticamente lo ZIP tramite l'artifact `plugin-zip` del workflow *Build plugin ZIP*.

## FAQ

Consulta la [FAQ dedicata](docs/faq.md) per approfondimenti su token scaduti, errori di pubblicazione, replay e permessi mancanti.
