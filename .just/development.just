# One-time setup and the dev-environment reset modes.

# Run once per clone, and again when a plugin adds recipes or commit hooks
[group('development')]
install: buildTools installPluginFiles
    git config core.hooksPath .githooks
    {{JUST}} devSecretBoxKey
    @echo "Pre-commit dispatcher activated. Scripts in bin/commit-hooks/ will run on every commit."
    @echo "Next, once per machine: just devCerts"

# Copy the commit hooks and the plugin recipes into place
[group('development')]
installPluginFiles:
    mkdir -p bin/commit-hooks
    rm -f bin/commit-hooks/*.bash
    cp tests/config/commit-hooks/*.bash bin/commit-hooks/
    for hook in plugins/*/commit-hooks/*.bash; do if [ -f "$hook" ]; then cp "$hook" bin/commit-hooks/; fi; done
    chmod +x bin/commit-hooks/*.bash
    cat /dev/null > .just/local.just
    for recipes in plugins/*/just/*.just; do if [ -f "$recipes" ]; then cat "$recipes" >> .just/local.just; fi; done

# Give this clone its own APP_SECRET_BOX_KEY in .env.local, which no dev reset overwrites
[group('development')]
devSecretBoxKey:
    #!/usr/bin/env bash
    set -euo pipefail
    if grep -q '^APP_SECRET_BOX_KEY=' .env.local 2>/dev/null; then
        echo "APP_SECRET_BOX_KEY already set in .env.local - keeping it."
        exit 0
    fi
    command -v openssl >/dev/null || { echo "openssl is not installed - cannot generate APP_SECRET_BOX_KEY"; exit 1; }
    printf 'APP_SECRET_BOX_KEY=%s\n' "$(openssl rand -base64 32)" >> .env.local
    echo "Generated APP_SECRET_BOX_KEY into .env.local - the .env.dist value is public and must never run anywhere real."

# Issue the local TLS certificate and trust it in the browser and system stores
[group('development')]
devCerts:
    @command -v mkcert >/dev/null || { echo "mkcert is not installed - see docker/certs/README.md"; exit 1; }
    mkcert -install
    mkcert -cert-file docker/certs/meetagain.local.crt -key-file docker/certs/meetagain.local.key meetagain.local "*.meetagain.local"
    {{JUST}} devCertsFirefox
    {{JUST}} dockerRestart

# Trust the mkcert CA in every Firefox profile, including the XDG paths mkcert does not scan
[group('development')]
devCertsFirefox:
    @command -v certutil >/dev/null || { echo "certutil is missing - install the nss package to trust the CA in Firefox"; exit 1; }
    @CA="$(mkcert -CAROOT)/rootCA.pem"; found=0; for p in ~/.mozilla/firefox/*/ ~/.config/mozilla/firefox/*/; do [ -f "$p/cert9.db" ] || continue; found=1; certutil -d sql:"$p" -D -n "mkcert development CA" >/dev/null 2>&1 || true; certutil -d sql:"$p" -A -t "C,," -n "mkcert development CA" -i "$CA" && echo "trusted in $p"; done; [ "$found" = 1 ] || echo "no Firefox profile found - nothing to do"
    @echo "Restart Firefox to pick this up."

# Shared reset sequence used by devModeImport and the recipes plugins contribute: switches the named plugins on
# and leaves the excluded ones out of the build
[group('development')]
devModeReset plugins='' exclude='':
    {{JUST}} dockerStop
    {{JUST}} devResetConfigs
    cp .env.dist .env
    if [ -n "{{exclude}}" ]; then echo "APP_PLUGINS_EXCLUDE={{exclude}}" >> .env; fi
    cp assets/styles/_config.scss.dist assets/styles/_config.scss
    {{JUST}} dockerStart
    {{JUST}} workerStop
    {{JUST}} do "composer install"
    {{JUST}} appAssets
    {{PHP}} php bin/console cache:clear -q
    {{JUST}} devFreshDatabaseSteps
    if [ -n "{{plugins}}" ]; then {{PHP}} php bin/console app:plugin:toggle on {{plugins}}; fi

# Rebuild only the dev database - migrated and seeded, no content - leaving containers, packages and assets alone
[group('development')]
devFreshDatabase:
    {{JUST}} workerStop
    {{JUST}} devFreshDatabaseSteps
    {{JUST}} workerStart

# The database rebuild itself, for recipes that keep the workers stopped until their own data is loaded
[private]
devFreshDatabaseSteps:
    {{JUST}} devResetDatabase
    {{JUST}} appMigrate
    {{PHP}} php bin/console app:install:seed -q
    {{PHP}} php bin/console app:data-hotfix:baseline -q
    {{PHP}} php bin/console app:security:reset-state

# Dev environment built from an archive in src/DataImportFixtures - without an argument, lists the archives
[group('development')]
devModeImport archive='':
    #!/usr/bin/env bash
    set -euo pipefail
    if [ -z "{{archive}}" ]; then
        {{PHP}} php bin/console app:import:inspect src/DataImportFixtures/*/ --format=table
        exit 0
    fi
    archive="src/DataImportFixtures/{{archive}}"
    if [ ! -f "$archive/export.json" ]; then
        echo "No archive at $archive - run 'just devModeImport' to list them"
        exit 1
    fi
    {{JUST}} devModeReset '' multisite
    plugins="$({{PHP}} php bin/console app:import:inspect "$archive" --format=plugins)"
    if [ -n "$plugins" ]; then {{PHP}} php bin/console app:plugin:toggle on $plugins; fi
    {{PHP}} php bin/console app:import "$archive" --shift-dates --site-settings --strict
    {{JUST}} appAssets
    {{JUST}} appClearCache
    {{JUST}} workerStart

# Empty instance as a fresh install starts: every plugin off, one admin, a one-time login link
[group('development')]
devModeBootstrap email='admin@meetagain.local':
    {{JUST}} devModeReset '' multisite
    {{PHP}} php bin/console app:bootstrap --email={{email}} --site-url=https://meetagain.local
    {{JUST}} workerStart

# Clean generated files
[group('development')]
devResetConfigs:
    rm -rf .env config/plugins.php var/

# Reset to fresh clone state
[group('development')]
devResetToFreshCloneState:
    rm -rf .env config/plugins.php vendor/ var/ public/bundles/ public/assets/
    cp assets/styles/_config.scss.dist assets/styles/_config.scss

# Reset database
[group('development')]
devResetDatabase:
    {{PHP}} php bin/console doctrine:database:drop --force --if-exists
    {{PHP}} php bin/console doctrine:database:create --if-not-exists

# Empty Mailpit, then queue every email template x language for a visual pass
[group('development')]
devEmailPreview *options='':
    curl -sS -X DELETE http://localhost:8025/api/v1/messages
    {{PHP}} php bin/console app:email:preview {{options}}
    @echo "Queued - the worker sends them within seconds"
    @echo "Sendlog: https://meetagain.local/en/admin/email/sendlog"
    @echo "Inbox: http://localhost:8025"
