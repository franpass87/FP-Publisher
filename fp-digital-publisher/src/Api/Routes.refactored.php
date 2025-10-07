<?php

declare(strict_types=1);

namespace FP\Publisher\Api;

use FP\Publisher\Api\Controllers\AlertsController;
use FP\Publisher\Api\Controllers\JobsController;
use FP\Publisher\Api\Controllers\LinksController;
use FP\Publisher\Api\Controllers\PlansController;
use FP\Publisher\Api\Controllers\StatusController;

use function add_action;

/**
 * ESEMPIO DI REFACTORING DI Routes.php
 * 
 * Questo file mostra come il Routes.php originale dovrebbe essere
 * ristrutturato usando i controller separati.
 * 
 * Il file originale ha 1742 righe. Questa versione refactored ne ha circa 50.
 */
final class Routes
{
    public const NAMESPACE = 'fp-publisher/v1';

    /**
     * Registra il hook per le route REST API
     */
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    /**
     * Registra tutte le route delegando ai controller specifici
     */
    public static function registerRoutes(): void
    {
        // System
        StatusController::register();
        
        // Resources
        PlansController::register();
        LinksController::register();
        AlertsController::register();
        JobsController::register();

        // TODO: Aggiungere altri controller quando vengono creati:
        // - SettingsController
        // - AccountsController
        // - TemplatesController
        // - LogsController
        // - PreflightController
        // - TrelloController
        // - ApprovalsController
        // - CommentsController
        // - AssetsController
    }
}

/**
 * VANTAGGI DI QUESTO REFACTORING:
 * 
 * 1. File principale ridotto da 1742 a ~50 righe
 * 2. Ogni controller è responsabile solo della sua risorsa
 * 3. Più facile testare (ogni controller può essere testato separatamente)
 * 4. Più facile manutenere (codice organizzato per dominio)
 * 5. Riutilizzo del codice tramite BaseController
 * 6. Facilita l'aggiunta di nuove funzionalità
 * 
 * PROSSIMI PASSI:
 * 
 * 1. Creare i controller mancanti per le altre risorse
 * 2. Migrare le route dal Routes.php originale ai nuovi controller
 * 3. Testare che tutte le route funzionino correttamente
 * 4. Sostituire Routes.php originale con questa versione
 */