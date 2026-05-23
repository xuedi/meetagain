#!/bin/sh
#
# Clones the canonical test database (meetAgain_test) into per-process clones
# (meetAgain_test1, meetAgain_test2, ...) for paratest's TEST_TOKEN.
#
# Strategy: dump the template DB once via mariadb-dump, then restore it into
# N=nproc clones in parallel. Much faster than re-running the full fixture
# pipeline once per worker.
#
# Idempotent: drops and recreates each clone every run, so a stale
# meetAgain_testN never lingers when nproc changes.

set -eu

DOCKER='docker-compose --env-file .env.dist -f docker/docker-compose.yml'

# shellcheck disable=SC1091
. ./.env

procs=$(nproc)
template="${MARIADB_DATABASE}_test"
dump_path="/tmp/${template}-template.sql"

printf 'Dumping template DB %s ...\n' "$template"
$DOCKER exec mariadb sh -c "mariadb-dump --user='${MARIADB_USER}' --password='${MARIADB_PASSWORD}' --no-tablespaces --single-transaction --quick '${template}' > '${dump_path}'"

printf 'Cloning into %d per-process DBs ...\n' "$procs"
i=1
while [ "$i" -le "$procs" ]; do
    clone="${template}${i}"
    $DOCKER exec mariadb sh -c "
        mariadb --user='${MARIADB_USER}' --password='${MARIADB_PASSWORD}' -e \"DROP DATABASE IF EXISTS \\\`${clone}\\\`; CREATE DATABASE \\\`${clone}\\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\" &&
        mariadb --user='${MARIADB_USER}' --password='${MARIADB_PASSWORD}' '${clone}' < '${dump_path}'
    " &
    i=$((i + 1))
done
wait

$DOCKER exec mariadb sh -c "rm -f '${dump_path}'"

printf 'Per-process test DBs ready (%s1..%s%d)\n' "$template" "$template" "$procs"
