#!/bin/bash
# Script di verifica per FP Digital Publisher
# Esegui questo script prima di committare su GitHub

set -e

echo "=========================================="
echo "  VERIFICA STRUTTURA PLUGIN"
echo "=========================================="
echo ""

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

errors=0
warnings=0

# Funzione per verificare file
check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} $1"
    else
        echo -e "${RED}✗${NC} MANCANTE: $1"
        ((errors++))
    fi
}

# Funzione per verificare directory
check_dir() {
    if [ -d "$1" ]; then
        echo -e "${GREEN}✓${NC} $1/"
    else
        echo -e "${RED}✗${NC} MANCANTE: $1/"
        ((errors++))
    fi
}

echo "1. Verifica file principale:"
check_file "fp-digital-publisher.php"
echo ""

echo "2. Verifica autoloader custom:"
check_file "includes/autoloader.php"
echo ""

echo "3. Verifica PSR/Log incluso:"
check_file "includes/psr-log/LoggerInterface.php"
check_file "includes/psr-log/AbstractLogger.php"
check_file "includes/psr-log/LogLevel.php"
check_file "includes/psr-log/InvalidArgumentException.php"
echo ""

echo "4. Verifica classi principali:"
check_file "src/Loader.php"
check_file "src/Admin/Menu.php"
check_file "src/Infra/Options.php"
check_file "src/Infra/Capabilities.php"
echo ""

echo "5. Verifica vendor/ NON committato:"
if [ -d "vendor" ]; then
    # Controlla se è in Git
    if git ls-files vendor/ --error-unmatch > /dev/null 2>&1; then
        echo -e "${RED}✗${NC} vendor/ è tracciato da Git (ERRORE!)"
        ((errors++))
    else
        echo -e "${YELLOW}⚠${NC} vendor/ esiste localmente (OK, ma non su Git)"
        ((warnings++))
    fi
else
    echo -e "${GREEN}✓${NC} vendor/ non presente (corretto per GitHub)"
fi
echo ""

echo "6. Verifica .gitignore:"
if grep -q "^/vendor/" .gitignore 2>/dev/null; then
    echo -e "${GREEN}✓${NC} vendor/ è in .gitignore"
else
    echo -e "${YELLOW}⚠${NC} vendor/ potrebbe non essere in .gitignore"
    ((warnings++))
fi
echo ""

echo "7. Verifica sintassi PHP (se disponibile):"
if command -v php > /dev/null 2>&1; then
    php_errors=0
    
    # Verifica autoloader
    if php -l includes/autoloader.php > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} Sintassi autoloader corretta"
    else
        echo -e "${RED}✗${NC} Errore sintassi in autoloader"
        ((errors++))
        ((php_errors++))
    fi
    
    # Verifica file principale
    if php -l fp-digital-publisher.php > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} Sintassi file principale corretta"
    else
        echo -e "${RED}✗${NC} Errore sintassi in file principale"
        ((errors++))
        ((php_errors++))
    fi
    
    # Test caricamento autoloader
    if [ $php_errors -eq 0 ]; then
        if php -r "define('ABSPATH', '/tmp/'); require 'includes/autoloader.php'; echo 'OK';" > /dev/null 2>&1; then
            echo -e "${GREEN}✓${NC} Autoloader si carica senza errori"
        else
            echo -e "${RED}✗${NC} Errore nel caricamento autoloader"
            ((errors++))
        fi
    fi
else
    echo -e "${YELLOW}⚠${NC} PHP non disponibile per test sintassi"
    ((warnings++))
fi
echo ""

echo "=========================================="
echo "  RIEPILOGO"
echo "=========================================="
echo ""

if [ $errors -eq 0 ]; then
    echo -e "${GREEN}✓ TUTTO OK!${NC} Il plugin è pronto per GitHub"
    echo ""
    echo "Prossimi passi:"
    echo "  1. git add fp-digital-publisher.php includes/"
    echo "  2. git commit -m 'Fix: Aggiunto autoloader per funzionare senza Composer'"
    echo "  3. git push"
    echo ""
    exit 0
else
    echo -e "${RED}✗ ERRORI TROVATI: $errors${NC}"
    [ $warnings -gt 0 ] && echo -e "${YELLOW}⚠ Avvisi: $warnings${NC}"
    echo ""
    echo "Correggi gli errori prima di committare!"
    echo ""
    exit 1
fi
