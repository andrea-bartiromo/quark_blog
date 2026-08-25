#!/usr/bin/env bash
#
# Missione 04 (batch autonomo KAIRUS): esegue una porzione di test — o
# l'intera suite — piu' volte di seguito nello stesso modo in cui li
# eseguirebbe un contributore prima di un merge, per catturare in locale un
# test flaky/order-dependent PRIMA che raggiunga origin/main, invece di
# scoprirlo per caso durante una missione non correlata (com'e' successo per
# PublicSurfaceResponsiveImageTest).
#
# Uso:
#   scripts/test-repeatability-gate.sh --filter=NomeClasseOMetodo [--times=N]
#   scripts/test-repeatability-gate.sh --full [--times=N]
#
# Esempi:
#   scripts/test-repeatability-gate.sh --filter=PublicSurfaceResponsiveImageTest --times=30
#   scripts/test-repeatability-gate.sh --full --times=3
#
# Esce con codice 0 solo se OGNI run e' passata. Al primo fallimento stampa
# il log completo di quel run e si ferma (fail-fast: un run fallito e' gia'
# prova sufficiente di flakiness, continuare non aggiunge informazione).

set -euo pipefail

FILTER=""
TIMES=20
FULL=0

for arg in "$@"; do
  case "$arg" in
    --filter=*) FILTER="${arg#--filter=}" ;;
    --times=*) TIMES="${arg#--times=}" ;;
    --full) FULL=1 ;;
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

if [[ "$FULL" -eq 0 && -z "$FILTER" ]]; then
  echo "Serve --filter=<pattern> oppure --full. Vedi --help." >&2
  exit 2
fi

if ! [[ "$TIMES" =~ ^[0-9]+$ ]] || [[ "$TIMES" -lt 1 ]]; then
  echo "--times deve essere un intero positivo, ricevuto: $TIMES" >&2
  exit 2
fi

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT

if [[ "$FULL" -eq 1 ]]; then
  DESC="l'intera suite"
else
  DESC="--filter=$FILTER"
fi

echo "Repeatability gate: $DESC, $TIMES run consecutivi."

FAILED_RUN=0
for i in $(seq 1 "$TIMES"); do
  LOG="$WORKDIR/run-$i.log"

  if [[ "$FULL" -eq 1 ]]; then
    php artisan test > "$LOG" 2>&1 || true
  else
    php artisan test --filter="$FILTER" > "$LOG" 2>&1 || true
  fi

  RESULT_LINE="$(tail -1 "$LOG")"
  echo "  run $i/$TIMES: $RESULT_LINE"

  if ! grep -q '"result":"passed"' "$LOG"; then
    FAILED_RUN="$i"
    echo ""
    echo "FALLITO al run $i/$TIMES — output completo:"
    echo "----------------------------------------"
    cat "$LOG"
    echo "----------------------------------------"
    break
  fi
done

if [[ "$FAILED_RUN" -ne 0 ]]; then
  echo ""
  echo "{\"tool\":\"repeatability-gate\",\"result\":\"failed\",\"target\":\"$([[ $FULL -eq 1 ]] && echo full-suite || echo "$FILTER")\",\"runs_attempted\":$FAILED_RUN,\"runs_requested\":$TIMES}"
  exit 1
fi

echo ""
echo "{\"tool\":\"repeatability-gate\",\"result\":\"passed\",\"target\":\"$([[ $FULL -eq 1 ]] && echo full-suite || echo "$FILTER")\",\"runs\":$TIMES}"
