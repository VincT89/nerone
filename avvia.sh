#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"
project_php="${PHP_BIN:-}"
if [[ -z "$project_php" ]]; then
    project_profile="${USERPROFILE:-${HOME}}"
    project_profile="${project_profile//\\//}"
    project_portable="$project_profile/.codex/visualizations/2026/11/02/01a19bcb-4666-7a50-af3c-0618940bb512/work/nerone/runtime/php/php.exe"
    if [[ -f "$project_portable" ]]; then project_php="$project_portable"; else project_php="$(command -v php || true)"; fi
fi
if [[ -z "$project_php" ]] || ! "$project_php" -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);'; then
    printf '%s\n' 'Il progetto richiede PHP 8.4.1 o successivo. Imposta PHP_BIN con il percorso del runtime aggiornato.' >&2
    exit 1
fi
project_ini="$(dirname -- "$project_php")/php.ini"
project_options=()
if [[ -f "$project_ini" ]]; then project_options=(-c "$project_ini"); fi
"$project_php" "${project_options[@]}" artisan serve --host=127.0.0.1 --port="${PORT:-8741}" "$@"
