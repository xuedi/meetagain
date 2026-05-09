# Docker configuration - all commands run inside containers
# Read the comments in this file to understand what each command does.
# Always use `just test` to run tests, not `just do "vendor/bin/phpunit ..."`.
set dotenv-load

DOCKER := "docker-compose --env-file .env.dist -f docker/docker-compose.yml"
PHP := DOCKER + " exec -e XDEBUG_MODE=off php"
PHP_COVERAGE := DOCKER + " exec -e XDEBUG_MODE=coverage php"
DB := DOCKER + " exec mariadb"
JUST := just_executable() + " --justfile=" + justfile()

# Show commands
default:
    @echo ""
    @echo "  ███╗   ███╗███████╗███████╗████████╗     █████╗  ██████╗  █████╗ ██╗███╗   ██╗"
    @echo "  ████╗ ████║██╔════╝██╔════╝╚══██╔══╝    ██╔══██╗██╔════╝ ██╔══██╗██║████╗  ██║"
    @echo "  ██╔████╔██║█████╗  █████╗     ██║       ███████║██║  ███╗███████║██║██╔██╗ ██║"
    @echo "  ██║╚██╔╝██║██╔══╝  ██╔══╝     ██║       ██╔══██║██║   ██║██╔══██║██║██║╚██╗██║"
    @echo "  ██║ ╚═╝ ██║███████╗███████╗   ██║       ██║  ██║╚██████╔╝██║  ██║██║██║ ╚████║"
    @echo "  ╚═╝     ╚═╝╚══════╝╚══════╝   ╚═╝       ╚═╝  ╚═╝ ╚═════╝ ╚═╝  ╚═╝╚═╝╚═╝  ╚═══╝"
    @echo ""
    @{{JUST}} --list --unsorted





# Start docker
start: dockerStart

# Stop docker
stop: dockerStop

# Run command in PHP container
do +parameter='':
    {{PHP}} {{parameter}}





# Start containers and prepare logs
[group('docker')]
dockerStart:
	{{DOCKER}} up -d
	{{PHP}} mkdir -p var/log
	{{PHP}} touch var/log/dev.log
	{{PHP}} truncate -s 0 var/log/dev.log

# Stop containers
[group('docker')]
dockerStop:
	{{DOCKER}} down

# Restart containers
[group('docker')]
dockerRestart: dockerStop dockerStart

# Rebuild PHP image (no cache)
[group('docker')]
dockerRebuild:
    {{DOCKER}} build --no-cache php

# Enter PHP container shell
[group('docker')]
dockerEnter:
    {{DOCKER}} exec php bash

# Run SQL command with the parameter: query
[group('docker')]
dockerDatabase query:
    {{DB}} mariadb -u$MARIADB_USER -p$MARIADB_PASSWORD $MARIADB_DATABASE -e "{{query}}"





# Run Symfony console command
[group('app')]
app +parameter='':
    {{PHP}} php bin/console {{parameter}}

# Run cron tasks
[group('app')]
appCron:
    {{PHP}} php bin/console app:cron

# Clear cache and autoload
[group('app')]
appClearCache:
    {{PHP}} composer dump-autoload
    {{JUST}} app cache:pool:clear --all -q
    {{PHP}} php bin/console cache:clear -q

# Run migrations
[group('app')]
appMigrate:
    {{PHP}} php bin/console doctrine:migrations:migrate -n -q

# Check for upgradable dependencies
[group('app')]
appUpgrade:
    clear
    {{JUST}} do 'composer show --outdated'

# Compile SCSS and version all assets into public/assets/
[group('app')]
appAssets:
    rm -rf public/assets/ public/media/
    {{PHP}} php bin/console sass:build
    {{PHP}} php bin/console asset-map:compile
    {{PHP}} php bin/console app:media:compile
    {{PHP}} purgecss --config assets/purgecss.config.js
    {{PHP}} php bin/console cache:clear -q
    {{PHP}} php bin/console cache:pool:clear cache.cms_page_cache -q

# Watch SCSS for changes during development
[group('app')]
appAssetsWatch:
    {{PHP}} php bin/console sass:build --watch

# Download/update Bulma SCSS source (run when updating Bulma version)
[group('app')]
appUpdateBulma version='latest':
    bin/update-bulma.sh "{{PHP}}" "{{version}}"





# Run once per clone
[group('development')]
install:
    git config core.hooksPath .githooks
    mkdir -p bin/commit-hooks
    cp tests/config/commit-hooks/*.bash bin/commit-hooks/
    chmod +x bin/commit-hooks/*.bash
    @echo "Pre-commit dispatcher activated. Scripts in bin/commit-hooks/ will run on every commit."

# Shared reset sequence used by devModeFixtures and devModeMinimal
[group('development')]
devModeReset plugins='':
    {{JUST}} dockerStop
    {{JUST}} devResetConfigs
    cp .env.dist .env
    cp assets/styles/_config.scss.dist assets/styles/_config.scss
    touch installed.lock
    {{JUST}} dockerStart
    {{JUST}} do "composer install"
    {{JUST}} appAssets
    {{PHP}} php bin/console app:plugin disable all
    {{PHP}} php bin/console app:plugin enable {{plugins}}
    {{PHP}} php bin/console cache:clear -q
    {{JUST}} devResetDatabase
    {{JUST}} appMigrate
    {{PHP}} php bin/console doctrine:fixtures:load -q --group=install
    {{PHP}} php bin/console app:security:reset-state

# Full dev environment with demo data (base + plugin fixtures) — complete sample content for testing
[group('development')]
devModeFixtures plugins='':
    {{JUST}} devModeReset {{plugins}}
    {{PHP}} php bin/console doctrine:fixtures:load -q --append --group=base
    {{PHP}} php bin/console app:plugin:pre-fixtures
    {{PHP}} php bin/console app:fixtures:load -q --append --group=plugin
    {{PHP}} php bin/console app:plugin:post-fixtures
    {{JUST}} appCron
    {{PHP}} php bin/console app:event:add-fixture
    {{JUST}} appClearCache

# Full dev environment with ALL plugins active — ideal for testing plugin interactions
[group('development')]
devModeMax:
    {{JUST}} devModeFixtures all

# Minimal dev environment with install fixtures only (no sample content) — ideal for testing imports
[group('development')]
devModeMinimal plugins='':
    {{JUST}} devModeReset {{plugins}}
    {{PHP}} php bin/console doctrine:fixtures:load -q --append --group=minimal
    {{JUST}} appClearCache

# Switch to installer mode
[group('development')]
devModeInstaller:
    {{JUST}} dockerStop
    {{JUST}} devResetConfigs
    {{JUST}} dockerStart
    {{JUST}} devResetDatabase
    rm -f .env installed.lock
    @echo ""
    @echo "Access: https://meetagain.local/install/"

# Clean generated files
[group('development')]
devResetConfigs:
    rm -rf .env installed.lock config/plugins.php var/

# Reset to fresh clone state
[group('development')]
devResetToFreshCloneState:
    rm -rf .env installed.lock config/plugins.php vendor/ var/ public/bundles/ public/assets/
    cp assets/styles/_config.scss.dist assets/styles/_config.scss

# Reset database
[group('development')]
devResetDatabase:
    {{PHP}} php bin/console doctrine:database:drop --force --if-exists
    {{PHP}} php bin/console doctrine:database:create --if-not-exists





# List available plugins with their manifest information
[group('plugins')]
plugin-list:
    {{PHP}} php bin/console app:plugin:list

# Enable a specific plugin without affecting others
[group('plugins')]
plugin-enable name:
    {{PHP}} php bin/console app:plugin enable {{name}}

# Disable a specific plugin without affecting others
[group('plugins')]
plugin-disable name:
    {{PHP}} php bin/console app:plugin disable {{name}}





# Run all tests and checks (same chain as the pre-commit hook)
[group('testing')]
test:
    @{{PHP}} composer validate --strict --quiet
    @bin/commit-hooks.sh
    @echo "All tests and checks passed successfully"

# Setup test database
[group('testing')]
testSetup:
    {{PHP}} php bin/console doctrine:database:drop --env=test --force --if-exists
    {{PHP}} php bin/console doctrine:database:create --env=test
    {{PHP}} php bin/console doctrine:schema:create --env=test -q
    {{PHP}} php bin/console doctrine:fixtures:load --env=test -q --group=install
    {{PHP}} php bin/console doctrine:fixtures:load --env=test -q --append --group=base
    {{PHP}} php bin/console app:plugin:pre-fixtures --env=test
    {{PHP}} php bin/console app:fixtures:load --env=test -q --append --group=plugin
    {{PHP}} php bin/console app:plugin:post-fixtures --env=test
    touch tests/config/.test-db.lock

# Run unit tests
[group('testing')]
testUnit +parameter='':
    @{{PHP}} vendor/bin/phpunit -c tests/config/phpunit.xml --testsuite=default --no-coverage --log-junit tests/reports/junit.xml {{parameter}}
    @echo
    @echo

# Run functional tests
[group('testing')]
testFunctional +parameter='':
    @{{PHP}} vendor/bin/phpunit -c tests/config/phpunit.xml --testsuite=functional --no-coverage --log-junit tests/reports/junit.xml {{parameter}}
    @echo
    @echo

# Run smoke tests - hits every discovered GET route, asserts no 5xx (always run last)
[group('testing')]
testSmoke +parameter='':
    @{{PHP}} php bin/console cache:warmup --env=test --quiet
    @{{PHP}} vendor/bin/paratest -c tests/config/phpunit.xml --testsuite=smoke --processes=4 --functional --no-coverage --log-junit tests/reports/junit.xml {{parameter}}
    @echo
    @echo

# Print AI-readable test results (for Haiku agent)
[group('testing')]
testPrintResults +parameter='':
    {{PHP}} php bin/console app:test:results {{parameter}}

# Show coverage report
[group('testing')]
testCoverage +parameter='':
    {{PHP_COVERAGE}} vendor/bin/phpunit -c tests/config/phpunit.xml
    {{PHP}} php bin/console app:badge:generate
    {{PHP}} php bin/console app:test:coverage-report {{parameter}}

# Analyze Page speed in various browsers
[group('testing')]
testPerformance:
    {{PHP}} mkdir -p tests/reports/performance
    {{DOCKER}} up -d php-bench
    {{DOCKER}} run --rm sitespeed
    {{DOCKER}} stop php-bench
    xdg-open "$(find tests/reports/performance/sitespeed-result -name 'index.html' -type f -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2)"

# Run a load-test scenario (small | medium | cliff). Targets the running dev stack.
[group('testing')]
testLoad scenario='small':
    mkdir -p tests/reports/load
    {{DOCKER}} run --rm -e K6_WEB_DASHBOARD_EXPORT=/reports/load/{{scenario}}.html loadtest run /scripts/load/{{scenario}}.js
    @echo "Report: tests/reports/load/{{scenario}}.html"
    -xdg-open tests/reports/load/{{scenario}}.html

# Run small + medium back to back. cliff is opt-in via testLoad cliff.
[group('testing')]
testLoadAll:
    {{JUST}} testLoad small
    {{JUST}} testLoad medium

# Run an attack-test scenario (notFoundProbe | rateLimitLogin | accessDeniedScript | fuseCookieRotation).
[group('testing')]
testAttack scenario='notFoundProbe':
    mkdir -p tests/reports/attack
    {{PHP}} php bin/console app:security:reset-state
    {{DOCKER}} run --rm -e K6_WEB_DASHBOARD_EXPORT=/reports/attack/{{scenario}}.html loadtest run /scripts/attack/{{scenario}}.js
    {{PHP}} php bin/console app:security:assert-state tests/reports/attack/{{scenario}}.expectations.json --scenario={{scenario}} --output-dir=tests/reports/attack
    @echo "Report: tests/reports/attack/{{scenario}}.html"
    -xdg-open tests/reports/attack/{{scenario}}.html

# Run all four attack scenarios sequentially and produce a combined HTML index.
[group('testing')]
testAttackAll:
    {{JUST}} testAttack notFoundProbe
    {{JUST}} testAttack rateLimitLogin
    {{JUST}} testAttack accessDeniedScript
    {{JUST}} testAttack fuseCookieRotation
    {{PHP}} php bin/console app:security:report-combined --input-dir=tests/reports/attack --output=tests/reports/attack/index.html
    -xdg-open tests/reports/attack/index.html





# Run all tests and checks
[group('testing')]
check: checkMago checkMagoAnalyze checkMagoGuard
    {{PHP}} composer validate --strict
    echo "All tests and checks passed successfully"

# Check Mago (linter)
[group('checks')]
checkMago:
    {{PHP}} vendor/bin/mago --config=tests/config/mago.toml lint

# Analyze code with Mago
[group('checks')]
checkMagoAnalyze:
    {{PHP}} vendor/bin/mago --config=tests/config/mago.toml analyze

# Check architectural rules with Mago
[group('checks')]
checkMagoGuard:
    {{PHP}} vendor/bin/mago --config=tests/config/mago.toml guard

# Run all Mago checks (lint + analyze + guard)
[group('checks')]
checkMagoAll: checkMago checkMagoAnalyze checkMagoGuard
    echo "All Mago checks complete"

# Check accessibility (Pa11y)
[group('checks')]
checkA11y url='http://meetagain.local/':
    {{DOCKER}} build pa11y -q
    {{DOCKER}} run --rm pa11y {{url}} --reporter cli --standard WCAG2AA





# Format code with Mago
[group('fixing')]
fixMago:
    {{PHP}} vendor/bin/mago --config=tests/config/mago.toml lint --fix --format-after-fix

# Generate coverage badge (CI)
[group('fixing')]
fixCoverageBadge:
	{{PHP}} vendor/bin/phpunit -c tests/config/phpunit.xml --no-progress
	{{PHP}} php bin/console app:badge:generate
	git add tests/badge/coverage.svg

# Build static developer docs to docs/site/
[group('fixing')]
fixDocumentation:
    docker run --rm --user "$(id -u):$(id -g)" -v "$PWD":/docs zensical/zensical build --config-file docs/mkdocs.yml





# Extract translation keys from templates into YAML files (run after adding new trans keys)
[group('translations')]
translationExtract:
    @{{PHP}} php bin/console translation:extract --force de --format yaml
    @{{PHP}} php bin/console translation:extract --force en --format yaml
    @{{PHP}} php bin/console translation:extract --force cn --format yaml

# Check that no template references a translation key missing from en/de/zh catalogues
[group('translations')]
checkTranslations:
    @echo "Checking translations for missing keys in en, de, zh..."
    @for locale in en de zh; do \
        echo ""; \
        echo "=== $locale ==="; \
        {{PHP}} php bin/console debug:translation --only-missing $locale 2>&1 | tail -n +1; \
    done
