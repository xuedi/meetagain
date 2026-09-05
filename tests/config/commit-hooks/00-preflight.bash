#!/bin/bash
#
# Preflight -- the first slot in the chain. Answers one question in a few
# milliseconds: will anything further down fail for a reason that has nothing
# to do with the commit? Every later hook shells out to `just`, which shells
# into the docker php container, so a stopped stack or a skipped `composer
# install` surfaces as a cryptic failure several minutes in. This reports the
# whole list up front instead.
#
# Missing guard binaries only warn, matching the per-guard hooks: bin/tools/bin/
# is gitignored, so a contributor without a Rust toolchain must still be able to
# commit. A guard that IS built but has lost its committed .dist config is a
# hard failure - it would exit 2 and the guard's own hook would report it as a
# finding rather than as breakage.
#
# Installed by `just install` (copied into bin/commit-hooks/).

set -uo pipefail

COMPOSE="docker-compose --env-file .env.dist -f docker/docker-compose.yml"
# The chain shells into php and the test database lives in mariadb; valkey is not
# required because the test env swaps every cache pool for cache.adapter.array.
REQUIRED_SERVICES="php mariadb"
GUARDS="leak-guard docs-guard mermaid-guard comment-guard"

problems=()
warnings=()

for binary in just docker-compose; do
    if ! command -v "$binary" >/dev/null 2>&1; then
        problems+=("$binary is not on PATH")
    fi
done

for path in vendor/autoload.php vendor/bin/mago vendor/bin/phpunit; do
    if [ ! -e "$path" ]; then
        problems+=("$path is missing - run: composer install")
    fi
done

if command -v docker-compose >/dev/null 2>&1; then
    if ! running=$($COMPOSE ps --services --filter status=running 2>/dev/null); then
        problems+=("docker compose could not be queried - is the docker daemon running? then: just start")
    else
        for service in $REQUIRED_SERVICES; do
            if ! grep -qx "$service" <<<"$running"; then
                problems+=("compose service '$service' is not running - run: just start")
            fi
        done
    fi
fi

for guard in $GUARDS; do
    if [ ! -x "bin/tools/bin/$guard" ]; then
        warnings+=("bin/tools/bin/$guard is missing - build it with: just buildTools")
    elif [ ! -f "config/tools/$guard.dist" ]; then
        problems+=("config/tools/$guard.dist is missing - $guard cannot run without it")
    fi
done

for warning in ${warnings+"${warnings[@]}"}; do
    echo "warning: $warning" >&2
done

if [ ${#problems[@]} -eq 0 ]; then
    exit 0
fi

{
    echo
    echo "----------------------------------------------------------------------"
    echo "preflight: the commit-hook chain cannot run as expected."
    echo
    for problem in "${problems[@]}"; do
        echo "  - $problem"
    done
    echo
    echo "Fix these first - every later hook would fail on them, not on your"
    echo "changes. Commit with --no-verify to skip the chain entirely."
    echo "----------------------------------------------------------------------"
} >&2

exit 1
