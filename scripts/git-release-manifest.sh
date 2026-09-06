#!/usr/bin/env bash
# Prompt 017 (150-prompt deploy-hardening program): deterministic A/M/D
# manifest between two known-good commits, in the exact TSV format
# scripts/selective-deploy-backup.sh already consumes (scope<TAB>relative
# path, scope in {app,public}). "Deterministic" here means specifically:
# no implicit renames. git's default rename detection would otherwise
# collapse an old path removed + a new path added into a single "R" line
# — silently dropping the old path from the generated manifest, so a
# rollback could never restore what used to be there. --no-renames forces
# every rename to surface as its own D and A lines instead.
#
# Any other change class (copy, type-change, unmerged, unknown) is
# refused rather than guessed at: this script only ever emits a manifest
# it is certain about.
set -euo pipefail

usage() {
    echo "Usage: $0 --from SHA --to SHA [--repo DIR]" >&2
    exit 2
}

fail() { echo "ERROR: $*" >&2; exit 1; }
valid_sha() { [[ "$1" =~ ^[0-9a-fA-F]{40}$ ]]; }

repo="."
from=""
to=""

while [[ $# -gt 0 ]]; do
    [[ $# -ge 1 ]] || usage
    case "$1" in
        --from) [[ $# -ge 2 ]] || usage; from="$2"; shift 2;;
        --to) [[ $# -ge 2 ]] || usage; to="$2"; shift 2;;
        --repo) [[ $# -ge 2 ]] || usage; repo="$2"; shift 2;;
        *) usage;;
    esac
done

valid_sha "$from" || fail "invalid --from SHA: ${from:-<empty>}"
valid_sha "$to" || fail "invalid --to SHA: ${to:-<empty>}"
[[ "$from" != "$to" ]] || fail "--from and --to must differ"

[[ -d "$repo" ]] || fail "repository directory does not exist: $repo"
git -C "$repo" cat-file -e "${from}^{commit}" 2>/dev/null || fail "--from SHA not found in repository: $from"
git -C "$repo" cat-file -e "${to}^{commit}" 2>/dev/null || fail "--to SHA not found in repository: $to"

diff_output="$(git -C "$repo" diff --no-renames --name-status "$from" "$to")"

[[ -n "$diff_output" ]] || exit 0

while IFS=$'\t' read -r status rel extra || [[ -n "${status:-}" ]]; do
    [[ -n "${status:-}" ]] || continue

    case "$status" in
        A|M|D) ;;
        *)
            fail "unexpected git status code '$status' for path '${rel:-<empty>}' — refusing to build a manifest from a non-deterministic change class (copies/type-changes/unmerged paths must be reviewed manually)."
            ;;
    esac

    [[ -n "${rel:-}" ]] || fail "empty path for status $status"
    [[ -z "${extra:-}" ]] || fail "unexpected extra field for path: $rel"

    case "$rel" in
        public/*) printf 'public\t%s\n' "${rel#public/}" ;;
        *) printf 'app\t%s\n' "$rel" ;;
    esac
done <<< "$diff_output" | sort -u
