# Docker configuration - all commands run inside containers
# Read the comments in this file to understand what each command does.
# Always use `just test` to run tests, not `just do "vendor/bin/phpunit ..."`.
set dotenv-load

DOCKER := "docker-compose --env-file .env.dist -f docker/docker-compose.yml"
PHP := DOCKER + " exec -e XDEBUG_MODE=coverage php"
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
    {{PHP}} php bin/console cache:clear

# Run migrations
[group('app')]
appMigrate:
    {{PHP}} php bin/console doctrine:migrations:migrate -q

# Reset dev with fixtures (plugins: 'no', 'all', 'multisite', or plugin name like 'dishes')
[group('development')]
devModeFixtures plugins='no':
    {{JUST}} dockerStop
    {{JUST}} devResetConfigs
    cp .env.dist .env
    touch installed.lock
    {{JUST}} dockerStart
    {{JUST}} do "composer install"
    {{PHP}} php bin/console app:plugin --mode={{plugins}}
    {{JUST}} devResetDatabase
    {{JUST}} appMigrate
    {{PHP}} php bin/console doctrine:fixtures:load -q
    {{PHP}} php bin/console app:plugin:post-fixtures
    {{PHP}} php bin/console app:translation:import 'https://dragon-descendants.de/api/translations'
    {{PHP}} php bin/console app:event:extent
    {{PHP}} php bin/console app:event:add-fixture

# Switch to installer mode
[group('development')]
devModeInstaller:
    {{JUST}} dockerStop
    {{JUST}} devResetConfigs
    {{JUST}} dockerStart
    {{JUST}} devResetDatabase
    rm -f .env installed.lock
    @echo ""
    @echo "Access: http://localhost/install/"

# Clean generated files
[group('development')]
devResetConfigs:
    rm -rf .env installed.lock config/plugins.php var/

# Reset to fresh clone state
[group('development')]
devResetToFreshCloneState:
    rm -rf .env installed.lock config/plugins.php vendor/ var/ public/bundles/

# Reset database
[group('development')]
devResetDatabase:
    {{PHP}} php bin/console doctrine:database:drop --force --if-exists
    {{PHP}} php bin/console doctrine:database:create --if-not-exists

# List available plugins with their manifest information
[group('plugins')]
plugin-list:
    {{PHP}} php bin/console app:plugin --list

# Enable a specific plugin without affecting others
[group('plugins')]
plugin-enable name:
    {{PHP}} php bin/console app:plugin --mode={{name}}
    {{PHP}} php bin/console cache:clear

# Disable a specific plugin without affecting others
[group('plugins')]
plugin-disable name:
    {{PHP}} php bin/console app:plugin --mode={{name}} --disable
    {{PHP}} php bin/console cache:clear

# Run all tests and checks
[group('testing')]
test: testSetup testUnit testFunctional checkRector checkMago checkMagoAnalyze checkMagoGuard
    {{PHP}} composer validate --strict
    echo "All tests and checks passed successfully"

# Setup test database
[group('testing')]
testSetup:
    {{PHP}} php bin/console doctrine:database:drop --env=test --force --if-exists
    {{PHP}} php bin/console doctrine:database:create --env=test
    {{PHP}} php bin/console doctrine:schema:create --env=test -q
    {{PHP}} php bin/console doctrine:fixtures:load --env=test -q

# Run unit tests
[group('testing')]
testUnit +parameter='':
    {{PHP}} vendor/bin/phpunit -c tests/config/phpunit.xml --testsuite=default --no-progress --log-junit tests/reports/junit.xml {{parameter}}

# Run functional tests
[group('testing')]
testFunctional +parameter='':
    {{PHP}} vendor/bin/phpunit -c tests/config/phpunit.xml --testsuite=functional --no-progress --log-junit tests/reports/junit.xml {{parameter}}

# Show AI-readable test results (for Haiku agent)
[group('testing')]
testResults +parameter='':
    {{PHP}} php bin/console app:test:results {{parameter}}

# Show coverage report
[group('testing')]
testCoverage +parameter='':
    {{PHP}} vendor/bin/phpunit -c tests/config/phpunit.xml
    {{PHP}} php bin/console app:test:coverage-report {{parameter}}

# Analyze route performance
[group('testing')]
testSymfony +parameter='':
    {{PHP}} php bin/console app:test:metrics {{parameter}}

# Analyze Page speed in various browsers
[group('testing')]
testPerformance:
    {{PHP}} mkdir -p tests/reports/performance
    {{DOCKER}} up -d php-bench
    {{DOCKER}} run --rm sitespeed
    {{DOCKER}} stop php-bench
    xdg-open "$(find tests/reports/performance/sitespeed-result -name 'index.html' -type f -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2)"

# Check Rector (dry-run)
[group('checks')]
checkRector:
    {{PHP}} vendor/bin/rector process src --dry-run -c tests/config/rector.php

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
checkA11y url='http://localhost/':
    {{DOCKER}} build pa11y -q
    {{DOCKER}} run --rm pa11y {{url}} --reporter cli --standard WCAG2AA

# Apply Rector fixes
[group('fixing')]
fixRector:
    {{PHP}} vendor/bin/rector process src -c tests/config/rector.php

# Format code with Mago
[group('fixing')]
fixMago:
    {{PHP}} vendor/bin/mago --config=tests/config/mago.toml format

# Generate coverage badge (CI)
[group('fixing')]
fixCoverageBadge:
	{{PHP}} vendor/bin/phpunit -c tests/config/phpunit.xml --no-progress
	{{PHP}} php bin/console app:badge:generate
	git add tests/badge/coverage.svg
