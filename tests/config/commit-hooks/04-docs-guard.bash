#!/bin/bash
#
# docs-guard pre-commit hook -- resolves repository paths referenced from markdown, so a stale
# path cannot outlive the refactor that moved the file.
#
# Installed by `just install` (copied into bin/commit-hooks/).
#
# Run from the repo root (git invokes hooks with cwd = repo root).

set -euo pipefail

GUARD="bin/tools/bin/docs-guard"

if [ ! -x "$GUARD" ]; then
    echo "warning: $GUARD is missing - docs-guard did not run. Build it with: just buildTools" >&2
    exit 0
fi

if "$GUARD" --staged; then
    exit 0
fi

cat <<'EOF' >&2

----------------------------------------------------------------------
docs-guard found markdown referencing paths that do not exist (see
above). A documented path that no longer resolves sends the reader to a
missing file and devalues every other path on the page.

Fix by either:
  - correcting the path to where the file actually lives now, or
  - deleting the reference if the thing it named is gone, or
  - rewriting it as a placeholder when it was always illustrative
    (`plugins/<name>/...` - see PLACEHOLDERS in config/tools/docs-guard.dist)

Commit with --no-verify only if you mean to bypass this.
----------------------------------------------------------------------
EOF

exit 1
