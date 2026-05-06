#!/bin/bash
#
# Ensures the test database has been initialized before functional/smoke
# tests run. Skip is gated by tests/config/.test-db.lock, a marker file
# `just testSetup` touches on success - so a present lockfile means "every
# step of testSetup ran cleanly at least once".
#
# If you change entities or fixtures and need a fresh DB, just re-run:
#   just testSetup
# (it always rebuilds and re-touches the lockfile)
#
# Installed by `just install` (copied into bin/commit-hooks/).

set -euo pipefail

if [ -f tests/config/.test-db.lock ]; then
    exit 0
fi

echo "tests/config/.test-db.lock not present - running just testSetup"
just testSetup
