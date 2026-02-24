#!/bin/bash

# Script per avviare il server di sviluppo Assistente Farmacia API
# con supporto .htaccess e CORS

echo "🚀 Avvio server di sviluppo Assistente Farmacia API"
echo ""

# Controlla se la porta 8000 è già in uso
if lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null ; then
    echo "⚠️  La porta 8000 è già in uso!"
    echo "   Fermando il server esistente..."
    pkill -f "php.*localhost:8000"
    sleep 2
fi

# Avvia il server
echo "✅ Avvio server su http://localhost:8000"
echo "🔧 Supporto .htaccess: ATTIVO"
echo "🌐 CORS: CONFIGURATO"
echo "⏹️  Per fermare: Ctrl+C"
echo ""

# Avvia il server PHP con router
php -S localhost:8000 -t . dev-server.php 