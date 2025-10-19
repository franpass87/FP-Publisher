#!/usr/bin/env php
<?php
/**
 * Script di verifica dell'installazione del plugin FP Digital Publisher
 * 
 * Uso: php verify-installation.php
 */

declare(strict_types=1);

echo "🔍 Verifica Installazione FP Digital Publisher\n";
echo "============================================\n\n";

$errors = [];
$warnings = [];

// 1. Verifica autoloader
echo "1. Verifica autoloader...\n";
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $errors[] = "❌ vendor/autoload.php non trovato!";
    echo "   ❌ vendor/autoload.php NON trovato\n";
} else {
    echo "   ✅ vendor/autoload.php trovato\n";
    require_once $autoloadPath;
}

// 2. Verifica file principale plugin
echo "\n2. Verifica file principale plugin...\n";
$mainFile = __DIR__ . '/fp-digital-publisher.php';
if (!file_exists($mainFile)) {
    $errors[] = "❌ fp-digital-publisher.php non trovato!";
    echo "   ❌ fp-digital-publisher.php NON trovato\n";
} else {
    echo "   ✅ fp-digital-publisher.php trovato\n";
}

// 3. Verifica classi principali
echo "\n3. Verifica classi principali...\n";
$classes = [
    'FP\\Publisher\\Loader' => 'Loader (gestione inizializzazione)',
    'FP\\Publisher\\Admin\\Menu' => 'Menu (gestione menu admin)',
    'FP\\Publisher\\Admin\\Assets' => 'Assets (gestione risorse)',
    'FP\\Publisher\\Api\\Routes' => 'Routes (gestione API REST)',
    'FP\\Publisher\\Infra\\Options' => 'Options (gestione opzioni)',
    'FP\\Publisher\\Infra\\Capabilities' => 'Capabilities (gestione permessi)',
];

foreach ($classes as $class => $description) {
    if (class_exists($class)) {
        echo "   ✅ $description\n";
    } else {
        $errors[] = "❌ Classe $class non trovata!";
        echo "   ❌ $description NON trovata\n";
    }
}

// 4. Verifica dipendenze
echo "\n4. Verifica dipendenze...\n";
if (interface_exists('Psr\\Log\\LoggerInterface')) {
    echo "   ✅ PSR-3 Logger Interface\n";
} else {
    $warnings[] = "⚠️  PSR-3 Logger Interface non trovata (potrebbe causare problemi)";
    echo "   ⚠️  PSR-3 Logger Interface NON trovata\n";
}

// 5. Verifica struttura directory
echo "\n5. Verifica struttura directory...\n";
$directories = [
    'src' => 'Codice sorgente',
    'assets' => 'Asset frontend',
    'vendor' => 'Dipendenze',
];

foreach ($directories as $dir => $description) {
    $dirPath = __DIR__ . '/' . $dir;
    if (is_dir($dirPath)) {
        echo "   ✅ $description ($dir/)\n";
    } else {
        $warnings[] = "⚠️  Directory $dir/ non trovata";
        echo "   ⚠️  $description ($dir/) NON trovata\n";
    }
}

// 6. Verifica assets compilati
echo "\n6. Verifica assets compilati...\n";
$assetFiles = [
    'assets/dist/admin/index.js' => 'JavaScript Admin',
    'assets/dist/admin/index.css' => 'CSS Admin',
];

foreach ($assetFiles as $file => $description) {
    $filePath = __DIR__ . '/' . $file;
    if (file_exists($filePath)) {
        echo "   ✅ $description\n";
    } else {
        $warnings[] = "⚠️  Asset $file non compilato (la UI admin potrebbe non funzionare)";
        echo "   ⚠️  $description NON compilato\n";
    }
}

// 7. Verifica versione PHP
echo "\n7. Verifica versione PHP...\n";
$phpVersion = PHP_VERSION;
$requiredVersion = '8.1.0';
if (version_compare($phpVersion, $requiredVersion, '>=')) {
    echo "   ✅ PHP $phpVersion (richiesto >= $requiredVersion)\n";
} else {
    $errors[] = "❌ PHP $phpVersion non soddisfa il requisito minimo >= $requiredVersion";
    echo "   ❌ PHP $phpVersion (richiesto >= $requiredVersion)\n";
}

// Riepilogo finale
echo "\n============================================\n";
echo "📊 RIEPILOGO\n";
echo "============================================\n\n";

if (count($errors) === 0 && count($warnings) === 0) {
    echo "🎉 TUTTO OK! Il plugin è pronto per essere utilizzato.\n\n";
    echo "Per attivarlo in WordPress:\n";
    echo "1. Vai in Dashboard → Plugin\n";
    echo "2. Cerca 'FP Digital Publisher'\n";
    echo "3. Clicca su 'Attiva'\n";
    echo "4. Verifica che il menu 'FP Publisher' compaia nella sidebar\n\n";
    exit(0);
} else {
    if (count($errors) > 0) {
        echo "❌ ERRORI CRITICI:\n";
        foreach ($errors as $error) {
            echo "   $error\n";
        }
        echo "\n";
    }
    
    if (count($warnings) > 0) {
        echo "⚠️  AVVISI:\n";
        foreach ($warnings as $warning) {
            echo "   $warning\n";
        }
        echo "\n";
    }
    
    if (count($errors) > 0) {
        echo "❌ Il plugin NON è pronto. Risolvi gli errori critici prima di attivarlo.\n\n";
        echo "Suggerimenti:\n";
        echo "- Esegui: composer install --no-dev --optimize-autoloader\n";
        echo "- Verifica che tutti i file siano presenti\n\n";
        exit(1);
    } else {
        echo "⚠️  Il plugin è funzionante ma presenta alcuni avvisi.\n";
        echo "   Puoi attivarlo, ma alcune funzionalità potrebbero non essere disponibili.\n\n";
        exit(0);
    }
}
