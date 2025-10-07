# Architettura CSS Modulare

## 📁 Struttura

```
styles/
├── base/
│   ├── _variables.css      # CSS Custom Properties (Design Tokens)
│   └── _reset.css          # Reset e normalizzazione
│
├── layouts/
│   └── _shell.css          # Layout principale
│
├── components/
│   ├── _button.css         # Pulsanti
│   ├── _form.css           # Elementi form
│   ├── _widget.css         # Widget container
│   ├── _modal.css          # Dialog modale
│   ├── _calendar.css       # Calendario
│   ├── _composer.css       # Editor contenuti
│   └── _alerts.css         # Sistema alert
│
├── utilities/
│   └── _helpers.css        # Classi utility
│
├── index.css               # File principale (importa tutti i moduli)
└── README.md               # Questa documentazione
```

## 🎨 Metodologia: ITCSS + BEM

### ITCSS (Inverted Triangle CSS)

Organizziamo i CSS in ordine di **specificità crescente**:

1. **Base** - Variabili e reset (specificità bassa)
2. **Layouts** - Strutture di pagina
3. **Components** - Componenti riutilizzabili
4. **Utilities** - Helper classes (specificità alta)

### BEM (Block Element Modifier)

Convenzione di naming per le classi:

```css
/* Block */
.fp-calendar { }

/* Element */
.fp-calendar__header { }
.fp-calendar__cell { }

/* Modifier */
.fp-calendar--compact { }
.fp-calendar__cell--selected { }
```

## 🎯 Design Tokens (CSS Variables)

Tutti i valori riutilizzabili sono definiti come CSS Custom Properties:

```css
/* Colori */
--fp-color-primary: #3858e9;
--fp-color-success: #00a32a;

/* Spacing */
--fp-space-sm: 8px;
--fp-space-lg: 16px;

/* Typography */
--fp-font-size-base: 13px;
--fp-font-weight-semibold: 600;
```

### Utilizzo

```css
.my-component {
  color: var(--fp-color-primary);
  padding: var(--fp-space-lg);
  font-size: var(--fp-font-size-base);
}
```

## 🧩 Creare un Nuovo Componente

1. Crea il file in `components/_nomecomponente.css`
2. Usa la convenzione BEM per le classi
3. Usa le CSS variables per i valori
4. Importa nel file `index.css`

### Esempio

```css
/* components/_card.css */

.fp-card {
  background: var(--fp-color-white);
  border-radius: var(--fp-radius-lg);
  padding: var(--fp-space-lg);
  box-shadow: var(--fp-shadow-md);
}

.fp-card__header {
  margin-bottom: var(--fp-space-md);
  border-bottom: 1px solid var(--fp-color-gray-200);
}

.fp-card__title {
  font-size: var(--fp-font-size-lg);
  font-weight: var(--fp-font-weight-semibold);
  color: var(--fp-color-black);
}

.fp-card--elevated {
  box-shadow: var(--fp-shadow-xl);
}
```

Poi aggiungi in `index.css`:

```css
@import './components/_card.css';
```

## 🎨 Classi Utility

Usiamo utility classes per styling rapido:

```html
<!-- Spacing -->
<div class="fp-mt-4 fp-mb-2">...</div>

<!-- Flex -->
<div class="fp-flex fp-items-center fp-gap-2">...</div>

<!-- Text -->
<p class="fp-text-muted fp-text-center">...</p>
```

## 📏 Naming Convention

### Prefisso

Tutte le classi usano il prefisso `fp-` per evitare conflitti:

```css
/* ✅ Corretto */
.fp-button { }
.fp-calendar__cell { }

/* ❌ Sbagliato */
.button { }
.calendar-cell { }
```

### Variabili CSS

Le variabili usano il prefisso `--fp-`:

```css
/* ✅ Corretto */
--fp-color-primary
--fp-space-lg

/* ❌ Sbagliato */
--primary-color
--large-space
```

## 🔄 Migrazione dal CSS Monolitico

### Prima (index.css monolitico - 1898 righe)

```css
/* Tutto in un file */
.fp-calendar { }
.fp-calendar__header { }
.fp-button { }
.fp-modal { }
/* ...altre 1890 righe... */
```

### Dopo (architettura modulare)

```css
/* index.css - solo importazioni */
@import './base/_variables.css';
@import './components/_calendar.css';
@import './components/_button.css';
@import './components/_modal.css';
```

## 🎯 Best Practices

### ✅ DA FARE

- Usa le CSS variables per tutti i valori
- Segui la convenzione BEM
- Mantieni i file componenti < 200 righe
- Usa utility classes per spacing e layout comuni
- Commenta le sezioni complesse

### ❌ DA EVITARE

- Valori hardcoded (usa le variables)
- Specificità troppo alta (evita `!important`)
- Selettori troppo annidati (max 3 livelli)
- Classi generiche senza prefisso
- File componenti troppo grandi

## 📊 Vantaggi

✅ **Manutenibilità** - File piccoli e focalizzati  
✅ **Riutilizzabilità** - Componenti indipendenti  
✅ **Consistenza** - Design tokens centralizzati  
✅ **Performance** - Caricamento ottimizzato  
✅ **Scalabilità** - Facile aggiungere componenti  
✅ **Design System** - Sistema unificato  

## 📚 Risorse

- [ITCSS Methodology](https://www.xfive.co/blog/itcss-scalable-maintainable-css-architecture/)
- [BEM Methodology](http://getbem.com/)
- [CSS Custom Properties](https://developer.mozilla.org/en-US/docs/Web/CSS/--*)
- [CSS Architecture](https://www.smashingmagazine.com/2018/05/guide-css-layout/)

## 🔧 Build

Per buildare i CSS:

```bash
# Il file index.css importa automaticamente tutti i moduli
# Nessun build tool necessario per lo sviluppo

# Per produzione, usa un bundler come PostCSS o esbuild
npm run build:css
```