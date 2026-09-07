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

# -z (NUL-delimited records) is deliberate, not cosmetic: plain
# --name-status output C-quotes any path containing a tab, newline or
# non-ASCII byte (e.g. `A\t"odd\tname.txt"`), and that quoted spelling
# would otherwise be written straight into the manifest — a path that
# does not exist on disk, so backup silently skips it and rollback can
# neither restore nor remove the real file. -z emits raw bytes with NUL
# terminators instead, with no quoting to get wrong.
entries=()
while IFS= read -r -d '' status && IFS= read -r -d '' rel; do
    [[ -n "$status" ]] || continue

    case "$status" in
        A|M|D) ;;
        *)
            fail "unexpected git status code '$status' for path '${rel:-<empty>}' — refusing to build a manifest from a non-deterministic change class (copies/type-changes/unmerged paths must be reviewed manually)."
            ;;
    esac

    [[ -n "${rel:-}" ]] || fail "empty path for status $status"

    case "$rel" in
        public/*)
            # Un percorso Git sotto public/ corrisponde, dopo un deploy
            # reale, a DUE copie fisiche: quella dell'albero applicativo
            # (da cui public_path()/asset() leggono, scope "app" nel
            # formato manifest consumato da selective-deploy-backup.sh) e
            # quella della radice realmente servita da Apache (scope
            # "public"). Emettere solo una delle due righe lascerebbe
            # l'altra radice fuori da backup/rollback — esattamente la
            # divergenza a due alberi che questo strumento esiste per
            # prevenire (vedi
            # SelectiveDeployBackupScriptTest::test_rollback_restores_public_premium_css_on_both_roots_and_the_revision_token_together
            # per lo scenario di riferimento).
            stripped="${rel#public/}"
            entries+=("app"$'\t'"$stripped")
            entries+=("public"$'\t'"$stripped")
            ;;
        *) entries+=("app"$'\t'"$rel") ;;
    esac
done < <(git -C "$repo" diff --no-renames --name-status -z "$from" "$to")

if [[ "${#entries[@]}" -gt 0 ]]; then
    printf '%s\n' "${entries[@]}" | sort -u
fi
