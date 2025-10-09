#!/bin/bash

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║          VERIFICA FILE CREATI - Modularizzazione              ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Contatori
total_files=0
missing_files=0

# Funzione per verificare file
check_file() {
    if [ -f "$1" ]; then
        echo "  ✅ $1"
        ((total_files++))
    else
        echo "  ❌ MANCANTE: $1"
        ((missing_files++))
    fi
}

# Componente Calendar
echo "📅 CALENDAR:"
check_file "fp-digital-publisher/assets/admin/components/Calendar/types.ts"
check_file "fp-digital-publisher/assets/admin/components/Calendar/utils.ts"
check_file "fp-digital-publisher/assets/admin/components/Calendar/CalendarService.ts"
check_file "fp-digital-publisher/assets/admin/components/Calendar/CalendarRenderer.ts"
check_file "fp-digital-publisher/assets/admin/components/Calendar/index.ts"
check_file "fp-digital-publisher/assets/admin/components/Calendar/README.md"
echo ""

# Componente Composer
echo "✍️  COMPOSER:"
check_file "fp-digital-publisher/assets/admin/components/Composer/types.ts"
check_file "fp-digital-publisher/assets/admin/components/Composer/validation.ts"
check_file "fp-digital-publisher/assets/admin/components/Composer/ComposerState.ts"
check_file "fp-digital-publisher/assets/admin/components/Composer/ComposerRenderer.ts"
check_file "fp-digital-publisher/assets/admin/components/Composer/index.ts"
check_file "fp-digital-publisher/assets/admin/components/Composer/README.md"
echo ""

# Componente Kanban
echo "📋 KANBAN:"
check_file "fp-digital-publisher/assets/admin/components/Kanban/types.ts"
check_file "fp-digital-publisher/assets/admin/components/Kanban/utils.ts"
check_file "fp-digital-publisher/assets/admin/components/Kanban/KanbanRenderer.ts"
check_file "fp-digital-publisher/assets/admin/components/Kanban/index.ts"
check_file "fp-digital-publisher/assets/admin/components/Kanban/README.md"
echo ""

# API Service
echo "🌐 API SERVICE:"
check_file "fp-digital-publisher/assets/admin/services/api/client.ts"
check_file "fp-digital-publisher/assets/admin/services/api/index.ts"
echo ""

# Documentazione
echo "📚 DOCUMENTAZIONE:"
check_file "START_HERE.md"
check_file "LEGGIMI.md"
check_file "README_MODULARIZZAZIONE.md"
check_file "QUICK_START_MODULARIZZAZIONE.md"
check_file "ANALISI_MODULARIZZAZIONE.md"
check_file "GUIDA_REFACTORING_PRATICA.md"
check_file "REFACTORING_COMPLETATO.md"
check_file "RIEPILOGO_MODULARIZZAZIONE.md"
check_file "PROGRESSO_REFACTORING.md"
check_file "REPORT_FINALE_REFACTORING.md"
check_file "STATO_REFACTORING_AGGIORNATO.md"
check_file "RIEPILOGO_SESSIONE_COMPLETO.md"
check_file "SINTESI_FINALE.md"
check_file "INDICE_DOCUMENTI_CREATI.md"
check_file "CONCLUSIONE.md"
echo ""

# Riepilogo
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📊 RIEPILOGO:"
echo "  Totale file verificati: $total_files"
echo "  File mancanti: $missing_files"
echo ""

if [ $missing_files -eq 0 ]; then
    echo "  ✅ TUTTI I FILE CREATI CORRETTAMENTE!"
    echo ""
    echo "  Puoi procedere con:"
    echo "  1. cat START_HERE.md"
    echo "  2. Leggere la documentazione"
    echo "  3. Esaminare i componenti creati"
else
    echo "  ⚠️  Alcuni file sono mancanti. Verifica l'output sopra."
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
