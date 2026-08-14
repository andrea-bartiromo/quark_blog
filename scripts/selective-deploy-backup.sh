#!/usr/bin/env bash
set -euo pipefail

usage() {
  echo "Usage: $0 backup --manifest FILE --app-root DIR --public-root DIR --backup-root DIR --previous-sha SHA --target-sha SHA" >&2
  echo "       $0 rollback --backup-dir DIR --app-root DIR --public-root DIR" >&2
  exit 2
}

fail() { echo "ERROR: $*" >&2; exit 1; }
valid_sha() { [[ "$1" =~ ^[0-9a-fA-F]{40}$ ]]; }
valid_rel() {
  local p="$1"
  [[ -n "$p" && "$p" != /* && "$p" != *$'\n'* && "$p" != *$'\r'* && "$p" != *$'\t'* ]] || return 1
  [[ "/$p/" != *"/../"* && "/$p/" != *"/./"* && "$p" != ".." && "$p" != "." ]] || return 1
}
forbidden_any() {
  case "$1" in
    .env|.env.*|.env/*|*/.env|*/.env.*|*/.env/*) return 0;;
    *) return 1;;
  esac
}
forbidden_app() {
  case "$1" in
    vendor|vendor/*|storage|storage/*|tests|tests/*|docs|docs/*|.git|.git/*|.github|.github/*) return 0;;
    *) return 1;;
  esac
}
canonical_dir() {
  [[ -d "$1" ]] || fail "directory does not exist: $1"
  realpath -e -- "$1" 2>/dev/null || fail "cannot resolve directory: $1"
}
path_within_root() {
  local root="$1" rel="$2" resolved
  resolved="$(realpath -m -- "$root/$rel" 2>/dev/null)" || return 1
  [[ "$resolved" == "$root"/* ]]
}
validate_entry() {
  local scope="$1" rel="$2" root="$3"
  [[ "$scope" == "app" || "$scope" == "public" ]] || fail "invalid scope: $scope"
  valid_rel "$rel" || fail "invalid relative path: ${rel:-<empty>}"
  forbidden_any "$rel" && fail "forbidden environment path: $rel"
  if [[ "$scope" == "app" ]] && forbidden_app "$rel"; then fail "forbidden app path: $rel"; fi
  path_within_root "$root" "$rel" || fail "path escapes destination root: $scope $rel"
}

cmd="${1:-}"; shift || true
case "$cmd" in
backup)
  manifest=""; app_root=""; public_root=""; backup_root=""; previous_sha=""; target_sha=""
  while [[ $# -gt 0 ]]; do
    [[ $# -ge 2 ]] || usage
    case "$1" in
      --manifest) manifest="$2"; shift 2;;
      --app-root) app_root="$2"; shift 2;;
      --public-root) public_root="$2"; shift 2;;
      --backup-root) backup_root="$2"; shift 2;;
      --previous-sha) previous_sha="$2"; shift 2;;
      --target-sha) target_sha="$2"; shift 2;;
      *) usage;;
    esac
  done
  [[ -f "$manifest" ]] || fail "missing manifest"
  app_root="$(canonical_dir "$app_root")"
  public_root="$(canonical_dir "$public_root")"
  backup_root="$(canonical_dir "$backup_root")"
  valid_sha "$previous_sha" || fail "invalid previous SHA"
  valid_sha "$target_sha" || fail "invalid target SHA"
  [[ "$previous_sha" != "$target_sha" ]] || fail "previous and target SHA must differ"

  stamp="$(date -u +%Y%m%dT%H%M%SZ)"
  backup_dir="$backup_root/pre-${previous_sha:0:8}-to-${target_sha:0:8}-$stamp"
  [[ ! -e "$backup_dir" ]] || fail "backup path already exists"
  mkdir -p "$backup_dir/files/app" "$backup_dir/files/public"
  : > "$backup_dir/backed-up-files.tsv"
  : > "$backup_dir/new-files.tsv"
  cp -p "$manifest" "$backup_dir/manifest.tsv"
  printf 'app\t%s\npublic\t%s\n' "$app_root" "$public_root" > "$backup_dir/roots.tsv"
  declare -A seen=()
  entry_count=0

  while IFS=$'\t' read -r scope rel extra || [[ -n "${scope:-}" ]]; do
    [[ -n "${scope:-}" ]] || continue
    [[ "${scope:0:1}" != "#" ]] || continue
    [[ -z "${extra:-}" ]] || fail "manifest line has extra fields: $scope ${rel:-}"
    root="$app_root"; [[ "$scope" == "public" ]] && root="$public_root"
    validate_entry "$scope" "${rel:-}" "$root"
    key="$scope"$'\t'"$rel"
    [[ -z "${seen[$key]+x}" ]] || fail "duplicate manifest entry: $scope $rel"
    seen[$key]=1
    entry_count=$((entry_count + 1))

    src="$root/$rel"
    if [[ -e "$src" || -L "$src" ]]; then
      [[ ! -d "$src" || -L "$src" ]] || fail "manifest entries must be files/symlinks: $scope $rel"
      dest="$backup_dir/files/$scope/$rel"
      mkdir -p "$(dirname "$dest")"
      cp -a -- "$src" "$dest"
      printf '%s\t%s\n' "$scope" "$rel" >> "$backup_dir/backed-up-files.tsv"
    else
      printf '%s\t%s\n' "$scope" "$rel" >> "$backup_dir/new-files.tsv"
    fi
  done < "$manifest"

  [[ "$entry_count" -gt 0 ]] || fail "manifest has no release entries"
  cat > "$backup_dir/metadata.env" <<EOF
previous_sha=$previous_sha
target_sha=$target_sha
created_at_utc=$stamp
EOF
  : > "$backup_dir/.complete"
  printf '%s\n' "$backup_dir"
  ;;
rollback)
  backup_dir=""; app_root=""; public_root=""
  while [[ $# -gt 0 ]]; do
    [[ $# -ge 2 ]] || usage
    case "$1" in
      --backup-dir) backup_dir="$2"; shift 2;;
      --app-root) app_root="$2"; shift 2;;
      --public-root) public_root="$2"; shift 2;;
      *) usage;;
    esac
  done
  [[ -d "$backup_dir" && -f "$backup_dir/.complete" && -f "$backup_dir/metadata.env" && -f "$backup_dir/manifest.tsv" && -f "$backup_dir/roots.tsv" && -f "$backup_dir/backed-up-files.tsv" && -f "$backup_dir/new-files.tsv" ]] || fail "backup is incomplete or invalid"
  [[ ! -e "$backup_dir/.rollback-complete" ]] || fail "rollback already completed"
  app_root="$(canonical_dir "$app_root")"
  public_root="$(canonical_dir "$public_root")"
  expected_roots="$(printf 'app\t%s\npublic\t%s\n' "$app_root" "$public_root")"
  [[ "$(cat "$backup_dir/roots.tsv")" == "$expected_roots" ]] || fail "destination roots do not match backup metadata"

  previous_sha="$(sed -n 's/^previous_sha=//p' "$backup_dir/metadata.env")"
  target_sha="$(sed -n 's/^target_sha=//p' "$backup_dir/metadata.env")"
  [[ "$(grep -c '^previous_sha=' "$backup_dir/metadata.env")" -eq 1 && "$(grep -c '^target_sha=' "$backup_dir/metadata.env")" -eq 1 ]] || fail "invalid SHA metadata"
  valid_sha "$previous_sha" || fail "invalid previous SHA metadata"
  valid_sha "$target_sha" || fail "invalid target SHA metadata"
  [[ "$previous_sha" != "$target_sha" ]] || fail "invalid identical SHA metadata"

  declare -A manifest_entries=()
  manifest_count=0
  while IFS=$'\t' read -r scope rel extra || [[ -n "${scope:-}" ]]; do
    [[ -n "${scope:-}" ]] || continue
    [[ "${scope:0:1}" != "#" ]] || continue
    [[ -z "${extra:-}" ]] || fail "stored manifest is malformed"
    root="$app_root"; [[ "$scope" == "public" ]] && root="$public_root"
    validate_entry "$scope" "${rel:-}" "$root"
    key="$scope"$'\t'"$rel"
    [[ -z "${manifest_entries[$key]+x}" ]] || fail "stored manifest contains duplicates"
    manifest_entries[$key]=1
    manifest_count=$((manifest_count + 1))
  done < "$backup_dir/manifest.tsv"
  [[ "$manifest_count" -gt 0 ]] || fail "stored manifest has no entries"

  declare -A rollback_entries=()
  rollback_count=0
  for list in backed-up-files.tsv new-files.tsv; do
    while IFS=$'\t' read -r scope rel extra || [[ -n "${scope:-}" ]]; do
      [[ -n "${scope:-}" ]] || continue
      [[ -z "${extra:-}" ]] || fail "invalid rollback manifest entry"
      root="$app_root"; [[ "$scope" == "public" ]] && root="$public_root"
      validate_entry "$scope" "${rel:-}" "$root"
      key="$scope"$'\t'"$rel"
      [[ -n "${manifest_entries[$key]+x}" ]] || fail "rollback entry is not in stored manifest: $scope $rel"
      [[ -z "${rollback_entries[$key]+x}" ]] || fail "duplicate rollback entry: $scope $rel"
      rollback_entries[$key]=1
      rollback_count=$((rollback_count + 1))
      target="$root/$rel"
      [[ ! -d "$target" || -L "$target" ]] || fail "destination is a directory at file path: $scope $rel"
      if [[ "$list" == "backed-up-files.tsv" ]]; then
        src="$backup_dir/files/$scope/$rel"
        [[ -e "$src" || -L "$src" ]] || fail "missing backed up source: $scope $rel"
      fi
    done < "$backup_dir/$list"
  done
  [[ "$rollback_count" -eq "$manifest_count" ]] || fail "rollback lists do not exactly cover stored manifest"

  while IFS=$'\t' read -r scope rel extra || [[ -n "${scope:-}" ]]; do
    [[ -n "${scope:-}" ]] || continue
    root="$app_root"; [[ "$scope" == "public" ]] && root="$public_root"
    src="$backup_dir/files/$scope/$rel"
    mkdir -p "$(dirname "$root/$rel")"
    rm -f -- "$root/$rel"
    cp -a -- "$src" "$root/$rel"
  done < "$backup_dir/backed-up-files.tsv"

  while IFS=$'\t' read -r scope rel extra || [[ -n "${scope:-}" ]]; do
    [[ -n "${scope:-}" ]] || continue
    root="$app_root"; [[ "$scope" == "public" ]] && root="$public_root"
    rm -f -- "$root/$rel"
  done < "$backup_dir/new-files.tsv"

  : > "$backup_dir/.rollback-complete"
  ;;
*) usage;;
esac
