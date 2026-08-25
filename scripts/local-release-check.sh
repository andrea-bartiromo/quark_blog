#!/usr/bin/env bash
#
# Missione 06 (batch autonomo KAIRUS): gate di release locale composito.
#
# GitHub Actions su questo repository non alloca runner da almeno 1335 run
# consecutivi (vedi docs/MISSION_05_CI_HEALTH_AUDIT.md) — ogni check
# completa in 2-4s senza mai eseguire nulla. Finche' non e' dimostrabilmente
# ripristinato, la verifica locale resta l'unico standard usato per decidere
# un merge. Questo script raccoglie in un solo comando la stessa sequenza
# gia' eseguita a mano prima di ogni merge in questo batch: suite PHPUnit,
# Pint (sola verifica, nessuna modifica), git diff --check, e il gate di
# drift degli asset di deploy quando configurato.
#
# Uso:
#   scripts/local-release-check.sh                 # suite completa
#   scripts/local-release-check.sh --filter=Pattern # solo un sottoinsieme
#
# Esce con codice 0 solo se OGNI controllo passa. Esegue tutti i controlli
# anche dopo un fallimento (a differenza del repeatability gate, qui
# l'obiettivo e' un riepilogo completo pre-merge, non fermarsi al primo
# rosso) e stampa un riepilogo finale PASS/FAIL per ciascuno.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

FILTER=""
for arg in "$@"; do
  case "$arg" in
    --filter=*) FILTER="${arg#--filter=}" ;;
    -h|--help)
      grep '^#' "$0" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *)
      echo "Argomento sconosciuto: $arg" >&2
      exit 2
      ;;
  esac
done

declare -A RESULT
declare -A DETAIL
OVERALL=0

section() {
  echo ""
  echo "==> $1"
}

# ---- 1. PHPUnit ----
section "PHPUnit"
if [[ -n "$FILTER" ]]; then
  PHPUNIT_LOG="$(php artisan test --filter="$FILTER" 2>&1)"
else
  PHPUNIT_LOG="$(php artisan test 2>&1)"
fi
echo "$PHPUNIT_LOG" | tail -3
if echo "$PHPUNIT_LOG" | tail -1 | grep -q '"result":"passed"'; then
  RESULT[phpunit]="PASS"
else
  RESULT[phpunit]="FAIL"
  DETAIL[phpunit]="$(echo "$PHPUNIT_LOG" | tail -1)"
  OVERALL=1
fi

# ---- 2. Pint (sola verifica) ----
section "Pint (--test)"
if PINT_LOG="$(./vendor/bin/pint --test 2>&1)"; then
  echo "$PINT_LOG" | tail -5
  RESULT[pint]="PASS"
else
  echo "$PINT_LOG" | tail -20
  RESULT[pint]="FAIL"
  OVERALL=1
fi

# ---- 3. git diff --check (whitespace/conflict markers) ----
section "git diff --check"
DIFF_LOG="$( { git diff --check; git diff --cached --check; } 2>&1)"
if [[ -z "$DIFF_LOG" ]]; then
  echo "pulito"
  RESULT[diff_check]="PASS"
else
  echo "$DIFF_LOG"
  RESULT[diff_check]="FAIL"
  OVERALL=1
fi

# ---- 4. Asset drift gate (best-effort: skippato se non configurato) ----
section "deploy:asset-drift"
DRIFT_LOG="$(php artisan deploy:asset-drift 2>&1)"
DRIFT_EXIT=$?
echo "$DRIFT_LOG" | tail -10
if [[ "$DRIFT_EXIT" -eq 0 ]]; then
  RESULT[asset_drift]="PASS"
else
  RESULT[asset_drift]="FAIL"
  OVERALL=1
fi

# ---- Riepilogo ----
section "Riepilogo"
for check in phpunit pint diff_check asset_drift; do
  printf '  %-16s %s\n' "$check" "${RESULT[$check]}"
done

if [[ "$OVERALL" -eq 0 ]]; then
  echo ""
  echo '{"tool":"local-release-check","result":"passed"}'
else
  echo ""
  echo '{"tool":"local-release-check","result":"failed"}'
fi

exit "$OVERALL"
