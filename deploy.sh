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

[ -f .env ] || fail ".env is missing. Provision production configuration before deployment."

ACTUAL_SHA="$(git rev-parse HEAD)"
[ "$ACTUAL_SHA" = "$EXPECTED_SHA" ] || fail "Revision mismatch: expected $EXPECTED_SHA, found $ACTUAL_SHA."

git diff --quiet --ignore-submodules -- || fail "Tracked release files differ from the expected Git revision. Refusing a dirty release artifact."
git diff --cached --quiet --ignore-submodules -- || fail "Tracked release files differ from the expected Git revision. Refusing a dirty release artifact."

APP_ENV_VALUE="$(php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo config("app.env");')"
[ "$APP_ENV_VALUE" = "production" ] || fail "APP_ENV must resolve to production; got '$APP_ENV_VALUE'."

APP_DEBUG_VALUE="$(php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo config("app.debug") ? "true" : "false";')"
[ "$APP_DEBUG_VALUE" = "false" ] || fail "APP_DEBUG must resolve to false."

APP_KEY_VALUE="$(php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo (string) config("app.key");')"
[ -n "$APP_KEY_VALUE" ] || fail "APP_KEY is empty. Refusing to generate or rotate a production application key."

DB_CONNECTION_VALUE="$(php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo (string) config("database.default");')"
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

chmod -R 755 storage bootstrap/cache

php artisan about 2>&1 | grep -E "Name|Version|PHP|Database|Environment" || true

# Record the exact successful code revision only after all deploy checks and
# cache operations complete. These files contain metadata only, never secrets.
printf '%s\n' "$ACTUAL_SHA" > REVISION
{
    printf 'revision=%s\n' "$ACTUAL_SHA"
    printf 'deployed_at_utc=%s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    printf 'database_driver=%s\n' "$DB_CONNECTION_VALUE"
} > DEPLOY_INFO

echo "Deploy safety checks completed for $ACTUAL_SHA."
