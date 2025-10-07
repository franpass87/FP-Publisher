# Controllers API

Questa directory contiene i controller REST API suddivisi per responsabilità.

## Struttura

- **BaseController.php** - Controller base con metodi comuni (autorizzazione, risposte)
- **StatusController.php** - Gestione dello stato del sistema
- **LinksController.php** - Gestione dei short link
- **PlansController.php** - Gestione dei piani di pubblicazione
- **AlertsController.php** - Gestione degli alert di sistema
- **JobsController.php** - Gestione della coda dei job

## Pattern

Ogni controller:

1. Estende `BaseController` per ereditare metodi comuni
2. Ha un metodo `register()` per registrare le proprie route
3. Gestisce solo una risorsa specifica
4. Mantiene la logica di validazione e autorizzazione

## Utilizzo

Nel file `Routes.php` principale:

```php
<?php

namespace FP\Publisher\Api;

use FP\Publisher\Api\Controllers\StatusController;
use FP\Publisher\Api\Controllers\LinksController;
use FP\Publisher\Api\Controllers\PlansController;
use FP\Publisher\Api\Controllers\AlertsController;
use FP\Publisher\Api\Controllers\JobsController;

final class Routes
{
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        StatusController::register();
        LinksController::register();
        PlansController::register();
        AlertsController::register();
        JobsController::register();
    }
}
```

## Benefici

1. **Separazione delle responsabilità** - Ogni controller gestisce una sola risorsa
2. **Codice riutilizzabile** - Metodi comuni nel BaseController
3. **Facilità di testing** - Controller isolati e testabili
4. **Manutenibilità** - Più facile trovare e modificare il codice
5. **Scalabilità** - Facile aggiungere nuovi controller