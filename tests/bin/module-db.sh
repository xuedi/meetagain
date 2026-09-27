#!/bin/sh
#
# Builds the module test database (core and modules only, schema plus install seed) when
# `test-stamp --config test-stamp-modules` says its inputs changed, or when it is missing.
# `--rebuild` forces it. Without a built test-stamp binary - as in CI - it always rebuilds.
# PHP_RUN is the prefix that reaches PHP (the docker exec line locally, empty in CI).

set -eu

STAMP=bin/tools/bin/test-stamp
CONFIG=test-stamp-modules

console() {
    ${PHP_RUN:-} php tests/bin/module-console "$@"
}

rebuild=0
[ "${1:-}" = "--rebuild" ] && rebuild=1
[ -x "$STAMP" ] || rebuild=1

if [ "$rebuild" = 0 ] \
    && "$STAMP" --config "$CONFIG" check \
    && console dbal:run-sql 'SELECT 1 FROM `user` LIMIT 1' >/dev/null 2>&1; then
    exit 0
fi

digest=''
[ -x "$STAMP" ] && digest=$("$STAMP" --config "$CONFIG" fingerprint)

echo "Building the module test database"
console doctrine:database:drop --force --if-exists -q
console doctrine:database:create -q
console doctrine:schema:create -q
console app:install:seed -q

[ -n "$digest" ] && "$STAMP" --config "$CONFIG" write "$digest"
exit 0
