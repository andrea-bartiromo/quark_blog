#!/bin/bash
# Kairus — production deployment safety wrapper
#
# Usage: bash deploy.sh <expected-40-char-git-sha>
# Run only from the checked-out release directory after the release files and
# production .env are already in place. This script deliberately does NOT
# implement MariaDB Backup V2. If migrations are pending it fails closed.

set -euo pipefail

EXPECTED_SHA="${1:-}"

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

echo "Kairus production deploy preflight"

if ! [[ "$EXPECTED_SHA" =~ ^[0-9a-fA-F]{40}$ ]]; then
    fail "Pass the exact expected 40-character Git SHA: bash deploy.sh <sha>."
fi

command -v php >/dev/null 2>&1 || fail "php is required."
command -v git >/dev/null 2>&1 || fail "git is required to verify and record the deployed revision."

php -r "exit(version_compare(PHP_VERSION,'8.3','>=') ? 0 : 1);" || fail "PHP 8.3+ is required."

# Prompt 016 (150-prompt deploy-hardening program): refuse a release
# directory that does not look like a real, complete Laravel checkout
# BEFORE anything else runs. This script assumes it is invoked from an
# already-checked-out release directory, never from an extracted archive
# (see Prompt 015: this script never extracts anything itself) — a
# partial copy, a wrong working directory, or a checkout that lost its
# Git manifest must fail here with a clear reason, not several checks
# later with a confusing PHP or git error.
[ -f artisan ] || fail "artisan not found in the current directory. This does not look like a Laravel release directory — refusing to proceed."
[ -f composer.json ] || fail "composer.json not found in the current directory. This does not look like a Laravel release directory — refusing to proceed."
[ -d .git ] || [ -f .git ] || fail ".git not found in the current directory. Cannot verify the deployed revision against its Git manifest — refusing to proceed."

[ -f .env ] || fail ".env is missing. Provision production configuration before deployment."

ACTUAL_SHA="$(git rev-parse HEAD)"
[ "$ACTUAL_SHA" = "$EXPECTED_SHA" ] || fail "Revision mismatch: expected $EXPECTED_SHA, found $ACTUAL_SHA."

# core.fileMode=false: only this script's own later `chmod -R 755 storage
# bootstrap/cache` (below) would otherwise make a SECOND deploy.sh run
# against the same checkout spuriously fail here — mode bits on tracked
# files inside bootstrap/cache flip from the repo's 644 to 755, with zero
# actual content change. This still fails closed on any real content
# drift, which is the actual safety intent of this guard.
git -c core.fileMode=false diff --quiet --ignore-submodules -- || fail "Tracked release files differ from the expected Git revision. Refusing a dirty release artifact."
git -c core.fileMode=false diff --cached --quiet --ignore-submodules -- || fail "Tracked release files differ from the expected Git revision. Refusing a dirty release artifact."

APP_ENV_VALUE="$(php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo config("app.env");')"
[ "$APP_ENV_VALUE" = "production" ] || fail "APP_ENV must resolve to production; got '$APP_ENV_VALUE'."

APP_DEBUG_VALUE="$(php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo config("app.debug") ? "true" : "false";')"
[ "$APP_DEBUG_VALUE" = "false" ] || fail "APP_DEBUG must resolve to false."

APP_KEY_VALUE="$(php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo (string) config("app.key");')"
[ -n "$APP_KEY_VALUE" ] || fail "APP_KEY is empty. Refusing to generate or rotate a production application key."

DB_CONNECTION_VALUE="$(php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo (string) config("database.default");')"
case "$DB_CONNECTION_VALUE" in
    mysql|mariadb)
        ;;
    *)
        fail "Production DB_CONNECTION must be mysql or mariadb; got '$DB_CONNECTION_VALUE'."
        ;;
esac

echo "Revision: $ACTUAL_SHA"
echo "Database driver: $DB_CONNECTION_VALUE"

# Backup V2 for MariaDB/MySQL is intentionally outside this change. The
# existing backup:database command is SQLite-only, so this deployment wrapper
# must never call it. A schema-changing deployment requires a verified backup
# before migration; until Backup V2 exists, fail closed when any migration is
# pending rather than migrating first or pretending a SQLite copy is useful.
MIGRATION_STATUS="$(php artisan migrate:status --no-ansi)"
if printf '%s\n' "$MIGRATION_STATUS" | grep -Eq '[[:space:]]Pending[[:space:]]'; then
    fail "Pending migrations detected. Create and verify a MariaDB/MySQL backup with the approved external procedure before running migrations; this script will not migrate without Backup V2."
fi

echo "No pending migrations. Refreshing application caches."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Incidente reale (rilascio controllato di main@0907b4e, dopo PR #540):
# `newsletter:reconfirmation-cleanup` era presente e correttamente
# registrato secondo ogni verifica statica/in-process disponibile
# (bootstrap/app.php, test PHPUnit nello stesso processo), eppure un vero
# sottoprocesso `php artisan newsletter:reconfirmation-cleanup --dry-run`
# nella release effettiva falliva con "Command is not defined". La causa
# ambientale esatta non è mai stata riprodotta né confermata: nessun test
# statico o in-process l'avrebbe mai potuta rilevare, perché entrambi
# guardano il codice sorgente o un processo PHP già bootstrappato da
# PHPUnit, mai il runtime Artisan realmente eseguito in questa release.
# deploy:verify-scheduled-commands non presume di conoscere la causa:
# verifica il SINTOMO, nel modo in cui si è manifestato — da un vero
# sottoprocesso `php artisan`, nella stessa release, dopo lo stesso ciclo
# di cache — per ogni comando che routes/console.php schedula. Se anche
# un solo comando risulta assente, il rilascio si blocca qui, invece di
# essere dichiarato riuscito e scoperto solo da un operatore che esegue
# manualmente --dry-run dopo il fatto.
echo "Verifying every scheduled command in routes/console.php is actually registered by Artisan in this exact release."
php artisan deploy:verify-scheduled-commands || fail "Scheduled command verification failed — see output above. This is the exact newsletter:reconfirmation-cleanup incident class: refusing to deploy."

# Causa reale confermata sull'host di produzione (dopo #541): la release
# in esecuzione non era un checkout Git e il suo vendor/ — con
# classmap-authoritative attivo, collegato da un'altra directory — non
# conteneva affatto CleanupExpiredNewsletterPending: generato prima che
# quella classe esistesse e mai rigenerato da allora, l'autoloader la
# rifiutava in silenzio (Kernel::load() intercetta l'eccezione via
# rescue() senza loggarla — deploy:verify-scheduled-commands sopra rileva
# l'ASSENZA dal risultato ma non la CAUSA). Questi due controlli sono
# specifici per questo comando, per il quale l'incidente si è già
# ripetuto due volte: una vera ReflectionClass sull'autoloader di QUESTA
# release isola esattamente un vendor/autoloader disallineato, con il
# messaggio di errore reale invece di un semplice "assente"; un vero
# --dry-run in sottoprocesso prova anche che il comando gira davvero (DB,
# servizio, configurazione), non solo che è elencato da Artisan.
#
# Un vendor COLLEGATO (symlink) a una directory fisicamente diversa da
# questa release è un secondo modo, distinto, in cui questo stesso
# controllo potrebbe mentire: l'autoloader ottimizzato di Composer
# calcola il proprio $baseDir da __DIR__ dentro vendor/composer/*.php, e
# PHP risolve sempre __DIR__ attraverso un symlink fino al percorso
# fisico reale — quindi un vendor collegato risolverebbe silenziosamente
# le classi rispetto alla directory FISICA in cui quel vendor è stato
# creato, non rispetto a questa release (verificato empiricamente: un
# vendor collegato da un altro checkout con lo stesso comando presente
# in entrambi supera la reflection, ma la classe risulta caricata dal
# file dell'ALTRO checkout, non da questa release). Per questo la
# reflection qui sotto non si accontenta di "riflette con successo": la
# reflection deve provenire dal file DENTRO questa esatta directory di
# rilascio.
echo "Verifying CleanupExpiredNewsletterPending is reflectable via this release's own autoloader, from this release's own file."
php -r '
require "vendor/autoload.php";
try {
    $reflection = new ReflectionClass("App\\Console\\Commands\\CleanupExpiredNewsletterPending");
} catch (\Throwable $e) {
    fwrite(STDERR, get_class($e) . ": " . $e->getMessage() . "\n");
    exit(1);
}
if (! $reflection->isSubclassOf(Illuminate\Console\Command::class) || $reflection->isAbstract()) {
    fwrite(STDERR, "CleanupExpiredNewsletterPending exists but is not a concrete Artisan Command.\n");
    exit(1);
}
$expectedFile = realpath(getcwd() . "/app/Console/Commands/CleanupExpiredNewsletterPending.php");
$actualFile = realpath($reflection->getFileName());
if ($expectedFile === false || $actualFile !== $expectedFile) {
    fwrite(STDERR, "CleanupExpiredNewsletterPending resolved from " . var_export($actualFile, true) . ", not this release own app/Console/Commands (" . var_export($expectedFile, true) . "). The autoloader is not resolving classes from this release — check whether vendor/ is a symlink to a physically different directory.\n");
    exit(1);
}
' || fail "CleanupExpiredNewsletterPending failed real reflection via this release's own vendor/autoloader (see stderr above) — this is the exact stale-vendor/classmap-authoritative incident class: refusing to deploy."

echo "Running a real newsletter:reconfirmation-cleanup --dry-run in this release."
php artisan newsletter:reconfirmation-cleanup --dry-run --no-ansi || fail "newsletter:reconfirmation-cleanup --dry-run failed in this release — refusing to deploy."

chmod -R 755 storage bootstrap/cache

php artisan about 2>&1 | grep -E "Name|Version|PHP|Database|Environment" || true

# Public asset drift gate (Missions 03-05): when DEPLOY_SERVED_PUBLIC_ROOT is
# configured, fail closed BEFORE recording success if release-managed static
# assets (CSS/JS/icons — see config/deploy.php) differ between this
# application root and the actually-served document root. When unset, the
# command exits 0 without comparing anything: it never blocks an environment
# that has not configured a separate served root. See docs/DEPLOYMENT.md for
# the incident this closes.
echo "Checking public asset consistency between the application root and the served document root (if configured)."
php artisan deploy:asset-drift || fail "Public asset drift detected between the application root and the configured served document root (DEPLOY_SERVED_PUBLIC_ROOT). Synchronize the two document roots before proceeding — see docs/DEPLOYMENT.md."

# Record the exact successful code revision only after all deploy checks and
# cache operations complete. These files contain metadata only, never secrets.
printf '%s\n' "$ACTUAL_SHA" > REVISION
{
    printf 'revision=%s\n' "$ACTUAL_SHA"
    printf 'deployed_at_utc=%s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    printf 'database_driver=%s\n' "$DB_CONNECTION_VALUE"
} > DEPLOY_INFO

echo "Deploy safety checks completed for $ACTUAL_SHA."
