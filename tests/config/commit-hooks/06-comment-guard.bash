#!/bin/bash
#
# comment-guard pre-commit hook -- runs a full project scan, not just staged files.
# A full scan of ~2,000 PHP files takes under 50ms, so the robustness win (catching comments
# that slipped in via merges, --no-verify commits or piecemeal staging) costs nothing.
#
# Slot 06- puts this after mermaid-guard (05-) and before `just check` (07-), so a comment
# violation fails fast, ahead of Mago and the test suites. The same script runs from
# `just test`, which iterates bin/commit-hooks/*.bash via bin/commit-hooks.sh.
#
# Installed by `just install` (copied into bin/commit-hooks/).
#
# Run from the repo root (git invokes hooks with cwd = repo root).

set -euo pipefail

GUARD="bin/tools/bin/comment-guard"

if [ ! -x "$GUARD" ]; then
    echo "warning: $GUARD is missing - comment-guard did not run. Build it with: just buildTools" >&2
    exit 0
fi

if "$GUARD"; then
    exit 0
fi

cat <<'EOF' >&2

----------------------------------------------------------------------
comment-guard found comments that are neither allowed by shape nor
justified (see above).

Fix by either:
  - deleting the comment (the default - naming should carry it), or
  - adding one line to the repo's tests/importantCodeComments.txt:
        path/to/File.php::symbol // why this code needs prose

An entry is never added without the user's explicit approval, and the
reason must say what makes the code intricate enough to earn it.
Run `comment-guard --suggest` for ready-to-fill lines.

Commit with --no-verify only if you mean to bypass this.
----------------------------------------------------------------------
EOF

exit 1
