#!/bin/bash
#
# Runs the project's lint / static-analysis / architectural-guard suite
# (just check = Mago lint + analyze + guard, plus composer validate).
# Runs before tests so style/type errors surface fast.
# Installed by `just install` (copied into bin/commit-hooks/).

set -euo pipefail

just check
