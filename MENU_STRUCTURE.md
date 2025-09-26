# Menu Structure Documentation

*Autore: Francesco Passeri – [francescopasseri.com](https://francescopasseri.com) – [info@francescopasseri.com](mailto:info@francescopasseri.com)*

## Overview

Versione introdotta: **1.0.0**  
Ultimo aggiornamento documentazione: **1.1.0**

Il plugin Social Auto Publisher utilizza una struttura di menu consolidata per migliorare navigazione, performance e accessibilità. Questa pagina integra le note di rilascio presenti nel [CHANGELOG](CHANGELOG.md) e descrive in dettaglio ogni voce del menu amministratore.

## New WordPress Admin Menu Organization

### Main Menu: "Social Auto Publisher"

All plugin functionality is now organized under a single main menu item called "Social Auto Publisher" with the following submenus:

### Blueprint centralizzato

La gerarchia del menu, le quick actions della dashboard e le card degli hub sono generate da un unico blueprint (`TTS_Admin::get_navigation_blueprint()`), filtrabile tramite l’hook `tts_admin_navigation_blueprint`. Ciò garantisce che permessi, slug e destinazioni rimangano sincronizzati tra menu, navigazione rapida e pagine hub. Quando un operatore non possiede le capability richieste, la card dell’hub resta visibile con stato bloccato e messaggio esplicito, così da chiarire quali strumenti richiedono privilegi superiori.

#### 1. Dashboard (Main Page)
- **Purpose**: Overview of plugin status and quick access to all features
- **Features**:
  - Statistics cards showing total posts, active clients, scheduled posts, and posts published today
  - Recent social posts table with status and details
  - Quick action buttons with direct access to the Configurazione, Produzione and Monitoraggio hubs, now filtered by the current
    user capabilities so each operator vede solo le scorciatoie realmente utilizzabili
  - Le quick action ereditano automaticamente gli stessi permessi delle rispettive pagine del menu e possono essere estese tramite il filtro `tts_dashboard_quick_actions`
  - Responsive design with modern styling

#### 2. Macro-sezione "Configurazione"
- **Purpose**: Centralize onboarding and setup workflows
- **Hub dedicato**: **Centro Configurazione** raccoglie card operative verso tutti gli strumenti di setup e mostra note rapide su ciascuna attività. Le card non accessibili evidenziano il permesso richiesto.
- **Includes**:
  - **Clienti**: elenco completo dei clienti configurati con stato e scorciatoie operative
  - **Client Wizard**: procedura guidata passo-passo per creare nuovi clienti, collegare Trello e mappare le liste
  - **Quickstart Packages**: libreria di preset pronti per importare template e mapping
  - **Social Connections**: gestione delle connessioni alle piattaforme (Facebook, Instagram, YouTube, TikTok)
  - **Test Connections**: diagnostica rapida delle integrazioni social
  - **General Settings**: impostazioni di base del plugin
  - **Help & Onboarding**: documentazione integrata e materiali di training

#### 3. Macro-sezione "Produzione"
- **Purpose**: Gestire l'operatività quotidiana della pubblicazione
- **Hub dedicato**: **Centro Produzione** fornisce una vista compatta degli strumenti editoriali e delle risorse per il team, con link diretti a guida calendario e template briefing. Le card non accessibili mostrano un’icona lucchetto e l’autorizzazione necessaria.
- **Includes**:
  - **Social Post**: elenco completo con filtri per cliente, stato di approvazione e azioni massive
  - **Calendario**: vista mensile dei contenuti programmati con dettagli su canale e orario
  - **Content Manager**: strumenti editoriali avanzati per organizzare le bozze
  - **Publishing Status**: pannello di controllo sulle frequenze e sul carico pubblicazioni
  - **AI & Advanced Suite**: automazioni e funzionalità assistite da AI

#### 4. Macro-sezione "Monitoraggio"
- **Purpose**: Analizzare performance e salute del sistema
- **Hub dedicato**: **Centro Monitoraggio** raggruppa analytics, salute del sistema e log con card descrittive, offrendo anche scorciatoie ad audit e test connessioni. Anche qui le card rispettano i permessi e indicano quando è necessario un ruolo con privilegi maggiori.
- **Includes**:
  - **Analytics**: metriche aggregate, filtri interattivi e export CSV
  - **Stato**: controllo salute con validazione token, webhooks e requisiti WordPress
  - **Log**: storico eventi con filtri per canale/stato e strumenti di manutenzione

## Benefits of the New Structure

1. **Better Organization**: All functionality is grouped under one main menu item instead of scattered across multiple top-level menus
2. **Improved Navigation**: Logical hierarchy makes it easier to find specific features
3. **Enhanced User Experience**: Modern styling and responsive design
4. **Better Overview**: Dashboard provides quick access to key information and actions
5. **Consistent Design**: Unified styling across all pages

## CSS Styling

The plugin now includes dedicated CSS files for enhanced visual presentation:
- `tts-dashboard.css`: Dashboard page styling
- `tts-calendar.css`: Calendar page styling  
- `tts-health.css`: Health status page styling
- `tts-analytics.css`: Analytics page styling

All styles are responsive and follow WordPress admin design patterns for consistency.

## Riferimenti
- [CHANGELOG.md](CHANGELOG.md) – panoramica delle release.
- [README.md](README.md) – overview funzionale del plugin.