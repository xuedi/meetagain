#!/bin/bash
#
# leak-guard pre-commit hook -- runs a full project scan, not just staged files.
# Catches credentials and forbidden words that slipped in via earlier merges, --no-verify
# commits, or files staged piecemeal. The full scan is fast (Rust + EXCLUDE_PATHS
# short-circuit), so the robustness win is worth more than the milliseconds.
#
# Installed by `just install` (copied into bin/commit-hooks/).
#
# Run from the repo root (git invokes hooks with cwd = repo root, so this is fine).

set -euo pipefail

GUARD="bin/tools/bin/leak-guard"

if [ ! -x "$GUARD" ]; then
    echo "warning: $GUARD is missing - leak-guard did not run. Build it with: just buildTools" >&2
    exit 0
fi

if "$GUARD"; then
    exit 0
fi

cat <<'EOF' >&2

----------------------------------------------------------------------
leak-guard found something that must not leave this repository (see above).

  [pattern-name]  a credential-shaped string. The matched text is withheld
                  on purpose. Remove it and rotate the key - a value that
                  reached the working tree is already compromised.
  [word]          vocabulary this repository does not publish.

Fix the references, or commit with --no-verify if intentional.
----------------------------------------------------------------------
EOF

exit 1
