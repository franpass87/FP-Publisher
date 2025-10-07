# Guida alla Migrazione CSS

## 📋 Panoramica

Questa guida spiega come migrare dal file CSS monolitico (`index.css` con 1898 righe) alla nuova architettura modulare.

## 🎯 Obiettivi

- ✅ Eliminare il file monolitico
- ✅ Adottare architettura modulare ITCSS
- ✅ Utilizzare design tokens (CSS variables)
- ✅ Migliorare manutenibilità e riutilizzabilità

## 📊 Confronto

### Prima

```
assets/admin/
└── index.css (1898 righe - MONOLITICO)
```

### Dopo

```
assets/admin/
└── styles/
    ├── base/
    │   ├── _variables.css    (100 righe)
    │   └── _reset.css        (60 righe)
    ├── layouts/
    │   └── _shell.css        (40 righe)
    ├── components/
    │   ├── _button.css       (90 righe)
    │   ├── _form.css         (130 righe)
    │   ├── _widget.css       (40 righe)
    │   ├── _modal.css        (100 righe)
    │   ├── _calendar.css     (80 righe)
    │   ├── _composer.css     (70 righe)
    │   ├── _alerts.css       (90 righe)
    │   ├── _badge.css        (50 righe)
    │   └── _card.css         (60 righe)
    ├── utilities/
    │   └── _helpers.css      (80 righe)
    └── index.css             (30 righe - solo import)
```

## 🔄 Processo di Migrazione

### Step 1: Backup

```bash
# Crea backup del file originale
cp assets/admin/index.css assets/admin/index.css.backup
```

### Step 2: Aggiorna i riferimenti

Nel file PHP che carica i CSS (probabilmente in `src/Admin/`):

```php
// ❌ PRIMA
wp_enqueue_style(
    'fp-publisher-admin',
    plugins_url('assets/admin/index.css', FP_PUBLISHER_FILE)
);

// ✅ DOPO
wp_enqueue_style(
    'fp-publisher-admin',
    plugins_url('assets/admin/styles/index.css', FP_PUBLISHER_FILE)
);
```

### Step 3: Testa il Nuovo CSS

1. Apri la dashboard admin
2. Verifica che tutti gli stili siano applicati correttamente
3. Controlla con DevTools eventuali classi mancanti

### Step 4: Migrazioni Specifiche

#### 4.1 Variabili Hardcoded → CSS Variables

**Prima:**
```css
.my-component {
  color: #3858e9;
  padding: 16px;
  font-size: 14px;
}
```

**Dopo:**
```css
.my-component {
  color: var(--fp-color-primary);
  padding: var(--fp-space-lg);
  font-size: var(--fp-font-size-md);
}
```

#### 4.2 Classi Custom → Componenti Modulari

Se hai classi personalizzate, creale in un nuovo file componente:

```bash
# Crea nuovo componente
touch assets/admin/styles/components/_mycomponent.css
```

```css
/* components/_mycomponent.css */
.fp-mycomponent {
  /* ... */
}

.fp-mycomponent__element {
  /* ... */
}
```

Poi importa in `index.css`:

```css
@import './components/_mycomponent.css';
```

## 📝 Checklist Migrazione

- [ ] Backup del file originale creato
- [ ] Nuova struttura CSS creata in `styles/`
- [ ] Riferimenti PHP aggiornati
- [ ] Test su tutte le pagine admin
- [ ] Verifica responsive
- [ ] Verifica accessibility (contrasti, focus)
- [ ] Test cross-browser
- [ ] Rimozione file vecchio (dopo conferma)

## 🐛 Troubleshooting

### Problema: Stili non applicati

**Soluzione:**
```bash
# Svuota la cache di WordPress
wp cache flush

# Controlla che il path sia corretto
# DevTools > Network > Verifica che index.css venga caricato
```

### Problema: Specificity conflict

**Soluzione:**
```css
/* Se uno stile non viene applicato, aumenta la specificità */
.fp-publisher-admin__mount .fp-button {
  /* Stili con maggiore specificità */
}
```

### Problema: CSS Variables non funzionanti

**Soluzione:**
```css
/* Verifica che :root sia definito */
:root {
  --fp-color-primary: #3858e9;
}

/* Fallback per browser vecchi */
.fp-button {
  background: #3858e9; /* Fallback */
  background: var(--fp-color-primary);
}
```

## 🎨 Personalizzazione Design Tokens

Puoi sovrascrivere i design tokens per personalizzare il tema:

```css
/* Aggiungi in un file custom-theme.css */
:root {
  --fp-color-primary: #ff6b6b; /* Rosso invece di blu */
  --fp-radius-lg: 12px; /* Border radius più grande */
  --fp-space-lg: 20px; /* Spacing maggiore */
}
```

## 📊 Vantaggi della Nuova Architettura

| Aspetto | Prima | Dopo | Miglioramento |
|---------|-------|------|---------------|
| Linee per file | 1898 | 30-130 | -93% |
| Numero file | 1 | 15+ | Modulare |
| Manutenibilità | Bassa | Alta | ⬆️⬆️ |
| Riutilizzabilità | Bassa | Alta | ⬆️⬆️ |
| Design System | No | Sì | ✅ |
| Performance | OK | Migliore | ⬆️ |

## 🚀 Prossimi Passi

Dopo la migrazione:

1. ✅ Elimina il file `index.css.backup` se tutto funziona
2. ✅ Aggiungi nuovi componenti quando necessario
3. ✅ Documenta i componenti personalizzati
4. ✅ Condividi il design system con il team

## 💡 Tips & Best Practices

### Usa le Utility Classes

Invece di creare CSS custom per spacing/layout comuni:

```html
<!-- ❌ Evita -->
<div class="my-custom-margin-top"></div>

<!-- ✅ Preferisci -->
<div class="fp-mt-4"></div>
```

### Riutilizza i Componenti

Prima di creare un nuovo componente, verifica se esiste già:

```html
<!-- ✅ Riutilizza componenti esistenti -->
<button class="fp-btn fp-btn--primary">Click</button>
<span class="fp-badge fp-badge--success">Attivo</span>
```

### Estendi con Modificatori

Usa i modificatori BEM per varianti:

```css
/* Base component */
.fp-card { }

/* Modifier */
.fp-card--elevated { }
.fp-card--interactive { }
```

## 📞 Supporto

Per domande o problemi:

1. Leggi il `README.md` nella directory `styles/`
2. Controlla gli esempi nei file componenti
3. Consulta la documentazione ITCSS e BEM

---

**Buona migrazione! 🎉**