#!/bin/sh
#
# Runs every executable bin/commit-hooks/*.bash in alphabetical order.
# Single source of truth used by both:
#   - .githooks/pre-commit (the git dispatcher)
#   - `just test`          (full local check run)
#
# Each hook script must:
#   - be executable (chmod +x)
#   - exit 0 to pass, non-zero to abort
#
# Order is controlled by the numeric prefix (01-, 05-, 10-, 20-, 30-, ...).
#
# Public/open-source contributors typically have an empty bin/commit-hooks/ and
# this script becomes a no-op. Private tooling can drop scripts in without the
# core repo needing to know about them.
#
# Bypass the git hook once with: git commit --no-verify

set -e

dir="bin/commit-hooks"
[ -d "$dir" ] || exit 0

for hook in "$dir"/*.bash; do
    [ -x "$hook" ] || continue
    "$hook" || exit 1
done

exit 0
