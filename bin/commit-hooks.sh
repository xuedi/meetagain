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
# A green run records the working tree in testPassing.lock (bin/tools/bin/test-stamp).
# With --commit, which the git dispatchers pass, a tree that still matches the stamp
# runs only the hooks the tool's ALWAYS_RUN lists; everything else already passed on
# exactly this content. Without --commit (`just test`) the whole chain always runs.
#
# Each hook runs through bin/just-shell, the same shim the justfile uses, so
# output-filter reports a labelled hook as one status line.
#
# Public/open-source contributors typically have an empty bin/commit-hooks/ and
# this script becomes a no-op. Private tooling can drop scripts in without the
# core repo needing to know about them.
#
# Bypass the git hook once with: git commit --no-verify

set -e

dir="bin/commit-hooks"
[ -d "$dir" ] || exit 0

# Pinned so the numeric-prefix order cannot drift with the developer's locale:
# collations differ on where the '-' separator sorts. Not exported, so the hook
# scripts themselves still run under the developer's own locale.
LC_ALL=C

stamp="bin/tools/bin/test-stamp"
fresh=0
before=""

if [ ! -x "$stamp" ]; then
    echo "warning: $stamp is missing - every hook runs and no stamp is written. Build it with: just buildTools" >&2
elif [ "${1:-}" = "--commit" ] && "$stamp" check; then
    fresh=1
    echo "Tests skipped - the tree matches testPassing.lock (just test to force)" >&2
else
    before=$("$stamp" fingerprint) || before=""
fi

for hook in "$dir"/*.bash; do
    [ -x "$hook" ] || continue
    if [ "$fresh" = 1 ] && ! "$stamp" always-run "$hook"; then
        continue
    fi
    bin/just-shell sh -cu "$hook" || exit 1
done

if [ -n "$before" ]; then
    "$stamp" write "$before" || true
fi

exit 0
