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
  [[ -n "$p" && "$p" != /* && "$p" != *$'\n'* && "$p" != *$'\r'* ]] || return 1
  [[ "/$p/" != *"/../"* && "/$p/" != *"/./"* && "$p" != ".." && "$p" != "." ]] || return 1
}
forbidden_app() {
  case "$1" in
    .env|.env/*|vendor|vendor/*|storage|storage/*|tests|tests/*|docs|docs/*|.git|.git/*|.github|.github/*) return 0;;
    *) return 1;;
  esac
}

cmd="${1:-}"; shift || true
case "$cmd" in
backup)
  manifest=""; app_root=""; public_root=""; backup_root=""; previous_sha=""; target_sha=""
  while [[ $# -gt 0 ]]; do
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
  [[ -f "$manifest" && -d "$app_root" && -d "$public_root" && -d "$backup_root" ]] || fail "missing manifest/root"
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

  while IFS=$'\t' read -r scope rel extra || [[ -n "${scope:-}" ]]; do
    [[ -n "${scope:-}" ]] || continue
    [[ "${scope:0:1}" != "#" ]] || continue
    [[ -z "${extra:-}" ]] || fail "manifest line has extra fields: $scope $rel"
    [[ "$scope" == "app" || "$scope" == "public" ]] || fail "invalid scope: $scope"
    valid_rel "${rel:-}" || fail "invalid relative path: ${rel:-<empty>}"
    if [[ "$scope" == "app" ]] && forbidden_app "$rel"; then fail "forbidden app path: $rel"; fi
    root="$app_root"; [[ "$scope" == "public" ]] && root="$public_root"
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

  cat > "$backup_dir/metadata.env" <<EOF
previous_sha=$previous_sha
target_sha=$target_sha
created_at_utc=$stamp
EOF
  printf '%s\n' "$backup_dir"
  ;;
rollback)
  backup_dir=""; app_root=""; public_root=""
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --backup-dir) backup_dir="$2"; shift 2;;
      --app-root) app_root="$2"; shift 2;;
      --public-root) public_root="$2"; shift 2;;
      *) usage;;
    esac
  done
  [[ -d "$backup_dir" && -f "$backup_dir/metadata.env" && -f "$backup_dir/backed-up-files.tsv" && -f "$backup_dir/new-files.tsv" ]] || fail "invalid backup directory"
  [[ -d "$app_root" && -d "$public_root" ]] || fail "missing destination roots"

  while IFS=$'\t' read -r scope rel extra || [[ -n "${scope:-}" ]]; do
    [[ -n "${scope:-}" && -z "${extra:-}" ]] || continue
    [[ "$scope" == "app" || "$scope" == "public" ]] || fail "invalid rollback scope"
    valid_rel "$rel" || fail "invalid rollback path"
    root="$app_root"; [[ "$scope" == "public" ]] && root="$public_root"
    src="$backup_dir/files/$scope/$rel"
    [[ -e "$src" || -L "$src" ]] || fail "missing backed up source: $scope $rel"
    mkdir -p "$(dirname "$root/$rel")"
    rm -f -- "$root/$rel"
    cp -a -- "$src" "$root/$rel"
  done < "$backup_dir/backed-up-files.tsv"

  while IFS=$'\t' read -r scope rel extra || [[ -n "${scope:-}" ]]; do
    [[ -n "${scope:-}" && -z "${extra:-}" ]] || continue
    [[ "$scope" == "app" || "$scope" == "public" ]] || fail "invalid rollback scope"
    valid_rel "$rel" || fail "invalid rollback path"
    root="$app_root"; [[ "$scope" == "public" ]] && root="$public_root"
    [[ ! -d "$root/$rel" || -L "$root/$rel" ]] || fail "refusing to remove directory introduced at file path: $scope $rel"
    rm -f -- "$root/$rel"
  done < "$backup_dir/new-files.tsv"
  ;;
*) usage;;
esac
