# FP Publisher – Admin UI Release 1.2.0

## Sommario della release
- Consolidato il nuovo sistema di design tokens (`tokens.css`) e della base (`tts-foundation.css`) su tutte le schermate.
- Introdotto il bundle `tts-components` e le utility di layout per garantire coerenza tipografica e accessibile.
- Aggiornate le schermate Dashboard, Activity Log, Social Posts Queue e Settings con toolbar, tab, azioni bulk e notice standard.
- Migliorata la validazione delle impostazioni con messaggi contestuali, helper `label_for` e gestione degli errori.
- Potenziati i list table con ricerche, filtri, Screen Options e Help Tabs, mantenendo compatibilità con gli slug legacy.

## Checklist post deploy
1. Eseguire `npm run build` nella directory `wp-content/plugins/trello-social-auto-publisher/` per rigenerare gli asset hashed.
2. Eseguire `composer install` e i linters disponibili (`composer test`, `php -l`) prima di impacchettare la release.
3. Generare il pacchetto con `tools/build-release-package.sh` e copiare gli artefatti prodotti in `dist/`.
4. Aprire ogni schermata admin del plugin verificando focus state, heading, help tab e messaggistica di errore.
5. Confermare che i redirect degli slug legacy (`admin.php?page=tts_*`) puntino alle nuove pagine documentate in `docs/admin-ui/menu-registry.md`.

## Documentazione correlata
- [CHANGELOG.md](../../CHANGELOG.md)
- [UPGRADE.md](../../UPGRADE.md)
- [docs/admin-ui/components.md](components.md)
- [docs/admin-ui/menu-registry.md](menu-registry.md)
- [docs/admin-ui/qa-results.md](qa-results.md)

## Note sul supporto
- Il bundle `tts-foundation.css` deve essere caricato prima degli stili legacy per evitare regressioni di spacing.
- Il file `tts-components.css` fornisce classi helper (`tts-card`, `tts-page-header`, `tts-notice`): evitare override manuali.
- I nuovi focus ring rispettano le preferenze `prefers-reduced-motion` e `forced-colors`. Non rimuovere gli `outline` o le variabili `--fp-admin-focus-ring`.
