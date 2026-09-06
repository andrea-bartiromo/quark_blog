#!/usr/bin/env bash
# Prompt 027-029 (150-prompt deploy-hardening program): a repeatable,
# read-mostly rollback drill against the REAL php artisan deploy:asset-drift
# gate — not a mock, not a unit test fixture — with a deliberately injected
# fault, run entirely inside a temporary directory. It never touches this
# checkout's own public/ files (only reads one, to copy it), never touches
# production, and never requires a database.
#
# What it proves, with real command exit codes as evidence:
#   1. A clean served root passes the gate (exit 0).
#   2. An injected fault (a release-managed file surviving on the served
#      root with a restrictive permission — the exact class of risk
#      Prompt 011-014 closed) makes the SAME gate fail closed (non-zero).
#   3. Remediating the fault makes the gate pass again (exit 0) — the
#      drill's "rollback" step.
#
# Usage: bash scripts/staging-rollback-drill.sh   (run from the repo root)
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

FAULT_FILE="robots.txt"
SOURCE_FILE="public/${FAULT_FILE}"

[[ -f "$SOURCE_FILE" ]] || {
    echo "ERROR: expected fixture file not found: $SOURCE_FILE" >&2
    exit 1
}

SERVED_ROOT="$(mktemp -d)"
cleanup() { rm -rf "$SERVED_ROOT"; }
trap cleanup EXIT

echo "== Staging rollback drill =="
echo "Served root (temporary, never production): $SERVED_ROOT"

# Mirror EVERY release-managed path (config('deploy.asset_drift_scan_paths'),
# read live rather than hardcoded here so this drill can never silently go
# stale against that config) into the served root, exactly like a real
# (external, undocumented today) deploy-time sync would. Only after the
# served root genuinely mirrors the app root does injecting a single fault
# mean anything — otherwise every unmirrored path would itself report as
# "missing_on_webroot" noise unrelated to the fault under test.
SCAN_PATHS="$(php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (config("deploy.asset_drift_scan_paths") as $p) { echo $p, PHP_EOL; }
')"

while IFS= read -r target; do
    [[ -n "$target" ]] || continue
    src="public/$target"
    dest="$SERVED_ROOT/$target"
    if [[ -d "$src" ]]; then
        mkdir -p "$dest"
        cp -r "$src/." "$dest/"
    elif [[ -f "$src" ]]; then
        mkdir -p "$(dirname "$dest")"
        cp -p "$src" "$dest"
    fi
done <<< "$SCAN_PATHS"

find "$SERVED_ROOT" -type f -exec chmod 0644 {} +
find "$SERVED_ROOT" -type d -exec chmod 0755 {} +

echo
echo "-- Step 1: clean served root must pass the gate --"
if DEPLOY_SERVED_PUBLIC_ROOT="$SERVED_ROOT" php artisan deploy:asset-drift; then
    echo "PASS (exit 0, as expected)"
else
    echo "DRILL FAILED: the gate rejected a clean served root." >&2
    exit 1
fi

echo
echo "-- Step 2: inject a fault (restrictive permission on the served copy) --"
chmod 0600 "$SERVED_ROOT/$FAULT_FILE"
echo "Injected: $FAULT_FILE on the served root is now $(stat -c '%a' "$SERVED_ROOT/$FAULT_FILE" 2>/dev/null || stat -f '%Lp' "$SERVED_ROOT/$FAULT_FILE")."

if DEPLOY_SERVED_PUBLIC_ROOT="$SERVED_ROOT" php artisan deploy:asset-drift; then
    echo "DRILL FAILED: the gate passed despite the injected permission fault." >&2
    exit 1
else
    echo "PASS (non-zero exit, fault correctly detected)"
fi

echo
echo "-- Step 3: remediate (rollback) the fault --"
chmod 0644 "$SERVED_ROOT/$FAULT_FILE"
echo "Remediated: $FAULT_FILE on the served root is now $(stat -c '%a' "$SERVED_ROOT/$FAULT_FILE" 2>/dev/null || stat -f '%Lp' "$SERVED_ROOT/$FAULT_FILE")."

if DEPLOY_SERVED_PUBLIC_ROOT="$SERVED_ROOT" php artisan deploy:asset-drift; then
    echo "PASS (exit 0, gate recovered after remediation)"
else
    echo "DRILL FAILED: the gate still fails after remediation." >&2
    exit 1
fi

echo
echo "== Drill complete: all three steps behaved as expected. =="
echo "Scope: filesystem-only, temporary directory, no database, no production host touched."
