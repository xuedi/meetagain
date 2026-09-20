#!/bin/sh
#
# Runs one mago subcommand over core and then over every plugin, each with its own config.
#
#   bin/mago.sh lint
#   bin/mago.sh analyze
#   bin/mago.sh guard
#   bin/mago.sh lint --fix --format-after-fix
#
# Core's config covers src/, tests/ and modules/ and names no plugin. Each plugin owns a
# tests/config/mago.toml, discovered here by glob, so the open-source tree never has to name a
# commercial plugin. Separate runs also mean separate rule sets: a rule one plugin relaxes in its
# own config stays relaxed for that plugin alone.
#
# Every config is run even after one fails, so a single failure does not hide the rest; the exit
# status is non-zero if any of them failed.

set -u

[ $# -ge 1 ] || { echo "usage: $0 <lint|analyze|guard|format> [mago options...]" >&2; exit 2; }

command="$1"
shift

status=0

# `guard` enforces the module perimeter, which only core declares. Running it against a config
# with no `[guard]` section prints four "checks were skipped" warnings and checks nothing, so the
# run is skipped instead - and picked up automatically if a plugin ever declares its own rules.
has_guard_rules() {
    grep -qE '^\[+guard' "$1" && return 0
    sed -n 's/^extends *= *\[\{0,1\}"\([^"]*\)".*/\1/p' "$1" | while read -r parent; do
        [ -n "$parent" ] || continue
        case "$parent" in
            /*) resolved="$parent" ;;
            *)  resolved="$(dirname "$1")/$parent" ;;
        esac
        [ -f "$resolved" ] && grep -qE '^\[+guard' "$resolved" && exit 0
    done
    [ $? -eq 0 ] && return 0
    return 1
}

run() {
    config="$1"
    shift
    if [ "$command" = 'guard' ] && ! has_guard_rules "$config"; then
        printf '\n\033[1m> mago %s (%s)\033[0m\n  no [guard] rules - skipped\n' "$command" "$config"
        return
    fi
    printf '\n\033[1m> mago %s (%s)\033[0m\n' "$command" "$config"
    vendor/bin/mago --config="$config" "$command" "$@" || status=1
}

run tests/config/mago.toml "$@"

# A plugin with source but no config would be silently unlinted - the way plugins/ drifted out of
# coverage before. Fail loudly instead.
missing=''
for src in plugins/*/src; do
    [ -d "$src" ] || continue
    plugin=$(dirname "$src")
    [ -f "$plugin/tests/config/mago.toml" ] || missing="$missing $plugin"
done

for config in plugins/*/tests/config/mago.toml; do
    [ -f "$config" ] || continue
    run "$config" "$@"
done

if [ -n "$missing" ]; then
    printf '\n\033[31mNo tests/config/mago.toml in:%s\033[0m\n' "$missing" >&2
    echo "Every plugin needs one, or its code is never checked. Copy one from another plugin." >&2
    status=1
fi

exit $status
