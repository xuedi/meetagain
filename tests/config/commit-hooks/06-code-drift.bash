#!/bin/bash
#
# code-drift pre-commit hook -- runs a full project scan in guard mode, not just staged files.
# A full scan of ~2,000 PHP files takes well under a second, and every counted detector stands
# at 0, so any finding is new drift. `fat` and the judgement tier of `tiny` are informational and
# never fail the hook. A stale allow entry fails it, so a renamed or deleted file cannot leave its
# entry behind.
#
# Slot 06- shares the PHP guard slot with comment-guard: both run after mermaid-guard (05-) and
# before `just check` (07-), so drift fails fast, ahead of Mago and the test suites. The same
# script runs from `just test`, which iterates bin/commit-hooks/*.bash via bin/commit-hooks.sh.
#
# Installed by `just install` (copied into bin/commit-hooks/).
#
# Run from the repo root (git invokes hooks with cwd = repo root).

set -euo pipefail

GUARD="bin/tools/bin/code-drift"

if [ ! -x "$GUARD" ]; then
    echo "warning: $GUARD is missing - code-drift did not run. Build it with: just buildTools" >&2
    exit 0
fi

if "$GUARD" --check; then
    exit 0
fi

cat <<'EOF2' >&2

----------------------------------------------------------------------
code-drift found code that drifted from the coding standards, or an
allow entry that no longer matches anything (see above).

Fix by either:
  - fixing the code (the default), or
  - adding one line to the repo's tests/codeDriftAllow.txt:
        <detector> <path>[::<symbol>] // why the finding stays

An entry is never added without the user's explicit approval. A stale
entry is deleted, or moved with the file it names.
Run `bin/tools/bin/code-drift` for the full report.

Commit with --no-verify only if you mean to bypass this.
----------------------------------------------------------------------
EOF2

exit 1
