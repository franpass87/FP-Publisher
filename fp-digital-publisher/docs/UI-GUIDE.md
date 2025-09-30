# FP Digital Publisher – UI Guide

Questa guida riassume token, componenti e pattern introdotti nelle fasi di UI polish per l’admin SPA del plugin FP Digital Publisher.

## Design Token
- `assets/ui/tokens.css` definisce colori, radius, ombre, scala spazi e tipografia condivisa. I token sono esposti come CSS variable (`--primary`, `--radius`, `--shadow-1`, ecc.) dentro il namespace `.fp-pub-root`.
- Caricare il foglio token il prima possibile tramite `Admin\UI\Enqueue` assicura che tutte le superfici React ereditino palette e reset coerenti.
- I controlli interattivi (button, input, select, textarea) applicano outline `2px` su `:focus-visible` e rispettano `font: inherit` per uniformare la tipografia.

## Componenti Base
- **StatusBadge** (`assets/ui/components/StatusBadge.tsx`): visualizza stati standardizzati (`draft`, `ready`, `approved`, `scheduled`, `published`, `failed`, `retrying`) con pill arrotondate e tonalità AA.
- **StickyToolbar** (`StickyToolbar.tsx`): barra sticky con backdrop-blur e bordo inferiore. Usare `elevation="floating"` per aggiungere ombra quando sovrapposta a contenuti.
- **EmptyState** (`EmptyState.tsx`): layout centrato con titolo, hint, illustrazione opzionale e CTA primaria/secondaria; accetta callback o link.
- **SkeletonCard** (`SkeletonCard.tsx`): placeholder animati che rispettano `prefers-reduced-motion` e permettono di configurare righe e header.
- **Tooltip** (`Tooltip.tsx`): wrapper non intrusivo che fornisce `aria-describedby`, ritardo configurabile e posizionamenti top/bottom/left/right.
- **Modal** (`Modal.tsx`): dialogo portaled con trap focus, chiusura via ESC/overlay e host condiviso `#fp-ui-modal-host`.
- **ToastHost** (`ToastHost.tsx`): regione `aria-live="polite"` per toast; utilizzare `pushToast`/`dismissToast` per gestire notifiche e azioni.
- **DensityToggle** (`DensityToggle.tsx`): switch `compact`/`comfort` per applicare classi di densità su tabelle o liste.
- **Icon** (`Icon.tsx`): raccolta di SVG inline (`grip`, `calendar`, `clock`) per evitare asset binari.

## Pattern Consigliati
- **Layout**: utilizzare griglie CSS e spacing token (`var(--space-N)`) per distribuire card, moduli e liste; mantenere contenitori su sfondo `var(--panel)` con bordo `var(--border)`.
- **Caricamento**: preferire `SkeletonCard` su fetch asincroni; passare `showHeader={false}` per placeholder compatti.
- **Vuoti**: `EmptyState` con CTA contestuale e hint descrittivo; per import manuali aggiungere `secondaryAction` con `target="_blank"` quando necessario.
- **Feedback**: montare `ToastHost` una sola volta per pagina; chiamare `pushToast({ title, description, intent })` per annunci success/errore/warning.
- **Accessibilità**: assicurarsi che ogni bottone/anchor usi `Tooltip` solo come arricchimento, mantenendo label visibili; `Modal` deve sempre ricevere `onDismiss` collegato a ESC e overlay.

## Convenzioni UI
- Badge di stato mostrano testo maiuscolo con `font-weight-medium`; evitare stringhe custom se possibile e riutilizzare gli alias esistenti.
- Toolbar sticky mantengono padding `var(--space-3) var(--space-4)` e allineano CTA a destra; quando necessario, includere `DensityToggle` accanto a filtri/ricerche.
- Empty state e skeleton devono vivere nello stesso wrapper della tabella/lista per evitare salti di layout.
- Toast host posizionato in alto a destra (`placement="top-end"`) è la scelta predefinita; utilizzare `bottom-start` in contesti dove l’header è affollato.
- Per nuovi pattern, aggiornare questa guida e la demo `assets/ui/pages/_demo/UiShowcase.tsx` così da mantenere la documentazione sincronizzata.

