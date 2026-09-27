#!/bin/bash
#
# Runs the module suites: core and modules only, on their own schema-only database. Aborts the
# commit on failure. Installed by `just install` (copied into bin/commit-hooks/).

set -euo pipefail

just testModules
