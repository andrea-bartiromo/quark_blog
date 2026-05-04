#!/bin/bash
# ════════════════════════════════════════════════════════════════
# Il Laboratorio — Script di deploy per il server di produzione
# Fondatore: Andrea Bartiromo
#
# UTILIZZO: bash deploy.sh
# Eseguire dopo aver copiato i file sul server
# ════════════════════════════════════════════════════════════════

set -e  # Interrompe lo script se un comando fallisce

echo ""
echo "🚀 IL LABORATORIO — Deploy produzione"
echo "════════════════════════════════════"
echo ""

# 1. Verifica prerequisiti
echo "1. Verifica prerequisiti..."
php -v | head -1
if ! php -r "exit(version_compare(PHP_VERSION,'8.3','>=') ? 0 : 1);"; then
    echo "❌ PHP 8.3+ richiesto"
    exit 1
fi
echo "   ✅ PHP OK"

# 2. Verifica .env
if [ ! -f .env ]; then
    echo "❌ File .env non trovato. Copia .env.production.example in .env e compilalo."
    exit 1
fi

# Verifica che APP_DEBUG sia false
if grep -q "APP_DEBUG=true" .env; then
    echo "❌ APP_DEBUG=true trovato nel .env! Impostare APP_DEBUG=false prima di continuare."
    exit 1
fi
echo "   ✅ .env OK"

# 3. Genera chiave app (solo se non già impostata)
if grep -q "APP_KEY=$" .env || grep -q "APP_KEY= $" .env; then
    echo "3. Generazione chiave applicazione..."
    php artisan key:generate --force
    echo "   ✅ Chiave generata"
fi

# 4. Migrazione database
echo "4. Migrazione database..."
php artisan migrate --force
echo "   ✅ Migrazioni eseguite"

# 5. Cache configurazione
echo "5. Ottimizzazione cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "   ✅ Cache ottimizzata"

# 6. Permessi cartelle
echo "6. Impostazione permessi..."
chmod -R 755 storage bootstrap/cache
chmod 644 database/database.sqlite 2>/dev/null || true
echo "   ✅ Permessi OK"

# 7. Backup immediato
echo "7. Backup database..."
php artisan backup:database
echo "   ✅ Backup eseguito"

# 8. Test finale
echo "8. Test finale..."
php artisan about 2>&1 | grep -E "Name|Version|PHP|Database|Environment"

echo ""
echo "════════════════════════════════════"
echo "✅ Deploy completato con successo!"
echo ""
echo "📋 Cose ancora da fare manualmente:"
echo "   • Aggiornare robots.txt con il dominio reale"
echo "   • Decommentare redirect HTTPS in public/.htaccess"
echo "   • Impostare il cron job: * * * * * php /path/to/artisan schedule:run"
echo "   • Caricare le immagini vere degli articoli in public/assets/img/"
echo "   • Aggiornare P.IVA nel footer"
echo ""
