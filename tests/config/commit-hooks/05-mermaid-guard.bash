#!/bin/bash
#
# mermaid-guard pre-commit hook -- lints staged markdown files for common mermaid syntax
# errors that would break GitHub rendering.
#
# Installed by `just install` (copied into bin/commit-hooks/).
#
# Run from the repo root (git invokes hooks with cwd = repo root).

set -euo pipefail

GUARD="bin/tools/bin/mermaid-guard"

if [ ! -x "$GUARD" ]; then
    echo "warning: $GUARD is missing - mermaid-guard did not run. Build it with: just buildTools" >&2
    exit 0
fi

if "$GUARD" --staged; then
    exit 0
fi

cat <<'EOF' >&2

----------------------------------------------------------------------
mermaid-guard found syntax errors in mermaid code blocks (see above).
Mermaid will fail to render in GitHub or any consumer of the markdown.

Common fixes:
  - Quote labels containing special chars: A["label with stuff"]
  - Avoid unescaped " inside unquoted [labels] or {labels}
  - Use a known diagram type as the first non-empty line
  - Close every triple-backtick mermaid block with a trailing fence

Fix the issue, or commit with --no-verify if intentional.
----------------------------------------------------------------------
EOF

exit 1
