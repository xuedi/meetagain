#!/bin/bash
#
# Runs the project's unit test suite. Aborts the commit on failure.
# Installed by `just install` (copied into bin/commit-hooks/).

set -euo pipefail

just testUnit
