# Docker configuration - all commands run inside containers
# Read the comments in this file to understand what each command does.
# Always use `just test` to run tests, not `just do "vendor/bin/phpunit ..."`.
set dotenv-load

DOCKER := "docker-compose --env-file .env.dist -f docker/docker-compose.yml"
PHP := DOCKER + " exec -e XDEBUG_MODE=coverage php"
DB := DOCKER + " exec mariadb"
JUST := just_executable() + " --justfile=" + justfile()

# Colors and symbols
BLUE := '\033[0;34m'
GREEN := '\033[0;32m'
YELLOW := '\033[0;33m'
RED := '\033[0;31m'
CYAN := '\033[0;36m'
BOLD := '\033[1m'
NC := '\033[0m'

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
    @printf "{{BLUE}}▸ Starting{{NC}} Docker containers...\n"
    @{{DOCKER}} up -d
    @{{PHP}} mkdir -p var/log
    @{{PHP}} touch var/log/dev.log
    @{{PHP}} truncate -s 0 var/log/dev.log
    @printf "{{GREEN}}✓ Docker{{NC}} containers started\n"

# Stop containers
[group('docker')]
dockerStop:
    @printf "{{BLUE}}▸ Stopping{{NC}} Docker containers...\n"
    @{{DOCKER}} down
    @printf "{{GREEN}}✓ Docker{{NC}} containers stopped\n"

# Restart containers
[group('docker')]
dockerRestart: dockerStop dockerStart

# Rebuild PHP image (no cache)
[group('docker')]
dockerRebuild:
    @printf "{{BLUE}}▸ Rebuilding{{NC}} PHP image (no cache)...\n"
    {{DOCKER}} build --no-cache php
    @printf "{{GREEN}}✓ PHP image{{NC}} rebuilt\n"

# Enter PHP container shell
[group('docker')]
dockerEnter:
    {{DOCKER}} exec php bash

# Run SQL query
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
    @printf "{{BLUE}}▸ Running{{NC}} cron tasks...\n"
    @{{PHP}} php bin/console app:cron
    @printf "{{GREEN}}✓ Cron{{NC}} tasks completed\n"

# Clear cache and autoload
[group('app')]
appClearCache:
    @printf "{{BLUE}}▸ Clearing{{NC}} cache and autoload...\n"
    @{{PHP}} composer dump-autoload
    @{{PHP}} php bin/console cache:clear
    @printf "{{GREEN}}✓ Cache{{NC}} cleared\n"

# Run migrations
[group('app')]
appMigrate:
    @printf "{{BLUE}}▸ Running{{NC}} migrations...\n"
    @{{PHP}} php bin/console doctrine:migrations:migrate -q
    @printf "{{GREEN}}✓ Migrations{{NC}} completed\n"

# Reset dev with fixtures
[group('development')]
devModeFixtures:
    @printf "\n{{BOLD}}{{CYAN}}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{{NC}}\n"
    @printf "{{BOLD}}{{CYAN}}  Development Mode: Reset with Fixtures{{NC}}\n"
    @printf "{{BOLD}}{{CYAN}}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{{NC}}\n\n"
    {{JUST}} dockerStop
    {{JUST}} devResetConfigs
    @printf "{{BLUE}}▸ Copying{{NC}} configuration files...\n"
    @cp .env.dist .env
    @cp config/plugins.dist.php config/plugins.php
    @touch installed.lock
    @printf "{{GREEN}}✓ Config{{NC}} files ready\n"
    {{JUST}} dockerStart
    @printf "{{BLUE}}▸ Installing{{NC}} composer dependencies...\n"
    @{{PHP}} composer install --quiet
    @printf "{{GREEN}}✓ Dependencies{{NC}} installed\n"
    {{JUST}} devResetDatabase
    {{JUST}} appMigrate
    @printf "{{BLUE}}▸ Loading{{NC}} fixtures...\n"
    @{{PHP}} php bin/console doctrine:fixtures:load -q
    @printf "{{GREEN}}✓ Fixtures{{NC}} loaded\n"
    @printf "{{BLUE}}▸ Importing{{NC}} translations...\n"
    @{{PHP}} php bin/console app:translation:import 'https://dragon-descendants.de/api/translations'
    @printf "{{GREEN}}✓ Translations{{NC}} imported\n"
    @printf "{{BLUE}}▸ Extending{{NC}} recurring events...\n"
    @{{PHP}} php bin/console app:event:extent
    @printf "{{GREEN}}✓ Events{{NC}} extended\n"
    @printf "{{BLUE}}▸ Adding{{NC}} event fixtures...\n"
    @{{PHP}} php bin/console app:event:add-fixture
    @printf "{{GREEN}}✓ Event fixtures{{NC}} added\n"
    @printf "\n{{BOLD}}{{GREEN}}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{{NC}}\n"
    @printf "{{BOLD}}{{GREEN}}  ✓ Development environment ready{{NC}}\n"
    @printf "{{BOLD}}{{GREEN}}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{{NC}}\n\n"

# Switch to installer mode
[group('development')]
devModeInstaller:
    @printf "\n{{BOLD}}{{CYAN}}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{{NC}}\n"
    @printf "{{BOLD}}{{CYAN}}  Development Mode: Installer{{NC}}\n"
    @printf "{{BOLD}}{{CYAN}}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{{NC}}\n\n"
    {{JUST}} dockerStop
    {{JUST}} devResetConfigs
    {{JUST}} dockerStart
    {{JUST}} devResetDatabase
    @rm -f .env installed.lock
    @printf "\n{{BOLD}}{{GREEN}}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{{NC}}\n"
    @printf "{{BOLD}}{{GREEN}}  ✓ Installer mode ready{{NC}}\n"
    @printf "{{BOLD}}{{GREEN}}  → Access: http://localhost/install/{{NC}}\n"
    @printf "{{BOLD}}{{GREEN}}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{{NC}}\n\n"

# Clean generated files
[group('development')]
devResetConfigs:
    @printf "{{BLUE}}▸ Cleaning{{NC}} generated files...\n"
    @rm -rf .env installed.lock config/plugins.php var/
    @printf "{{GREEN}}✓ Generated files{{NC}} cleaned\n"

# Reset to fresh clone state
[group('development')]
devResetToFreshCloneState:
    @printf "{{YELLOW}}▸ Resetting{{NC}} to fresh clone state...\n"
    @rm -rf .env installed.lock config/plugins.php vendor/ var/ public/bundles/
    @printf "{{GREEN}}✓ Reset{{NC}} to fresh clone state\n"

# Reset database
[group('development')]
devResetDatabase:
    @printf "{{BLUE}}▸ Resetting{{NC}} database...\n"
    @{{PHP}} php bin/console doctrine:database:drop --force --if-exists
    @{{PHP}} php bin/console doctrine:database:create --if-not-exists
    @printf "{{GREEN}}✓ Database{{NC}} reset\n"

# Run all tests and checks
[group('testing')]
test: _testHeader testSetup _testUnit _testFunctional _checkStan _checkRector _checkPhpcs _checkPhpCsFixer _checkDeptrac _checkComposer _testFooter

_testHeader:
    @printf "\n{{BOLD}}{{CYAN}}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{{NC}}\n"
    @printf "{{BOLD}}{{CYAN}}  Running All Tests & Checks{{NC}}\n"
    @printf "{{BOLD}}{{CYAN}}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{{NC}}\n\n"

_testFooter:
    @printf "\n{{BOLD}}{{GREEN}}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{{NC}}\n"
    @printf "{{BOLD}}{{GREEN}}  ✓ All tests and checks passed{{NC}}\n"
    @printf "{{BOLD}}{{GREEN}}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{{NC}}\n\n"

# Setup test database
[group('testing')]
testSetup:
    @printf "{{BLUE}}▸ Setting up{{NC}} test database...\n"
    @{{PHP}} php bin/console doctrine:database:drop --env=test --force --if-exists
    @{{PHP}} php bin/console doctrine:database:create --env=test
    @{{PHP}} php bin/console doctrine:schema:create --env=test -q
    @{{PHP}} php bin/console doctrine:fixtures:load --env=test -q
    @printf "{{GREEN}}✓ Test database{{NC}} ready\n\n"

# Run unit tests
[group('testing')]
testUnit +parameter='':
    {{PHP}} vendor/bin/phpunit -c tests/phpunit.xml --testsuite=default --no-progress {{parameter}}

_testUnit:
    @printf "{{BLUE}}▸ Running{{NC}} unit tests...\n"
    @{{PHP}} vendor/bin/phpunit -c tests/phpunit.xml --testsuite=default --no-progress
    @printf "{{GREEN}}✓ Unit tests{{NC}} passed\n\n"

# Run functional tests
[group('testing')]
testFunctional +parameter='':
    {{PHP}} vendor/bin/phpunit -c tests/phpunit.xml --testsuite=functional --no-progress {{parameter}}

_testFunctional:
    @printf "{{BLUE}}▸ Running{{NC}} functional tests...\n"
    @{{PHP}} vendor/bin/phpunit -c tests/phpunit.xml --testsuite=functional --no-progress
    @printf "{{GREEN}}✓ Functional tests{{NC}} passed\n\n"

# Show coverage report
[group('testing')]
showCoverage +parameter='':
    {{PHP}} vendor/bin/phpunit -c tests/phpunit.xml
    {{PHP}} php tests/AiReadableTestCoverage.php {{parameter}}

# Analyze route performance
[group('testing')]
routeMetrics +parameter='':
    {{PHP}} php tests/AiReadableRouteMetrics.php {{parameter}}

# Run PHPStan
[group('checks')]
checkStan +parameter='':
    {{PHP}} vendor/bin/phpstan analyse -c tests/phpstan.neon --memory-limit=256M --no-progress {{parameter}}

_checkStan:
    @printf "{{BLUE}}▸ Running{{NC}} PHPStan...\n"
    @{{PHP}} vendor/bin/phpstan analyse -c tests/phpstan.neon --memory-limit=256M --no-progress
    @printf "{{GREEN}}✓ PHPStan{{NC}} passed\n\n"

# Check Rector (dry-run)
[group('checks')]
checkRector:
    {{PHP}} vendor/bin/rector process src --dry-run -c tests/rector.php

_checkRector:
    @printf "{{BLUE}}▸ Running{{NC}} Rector...\n"
    @{{PHP}} vendor/bin/rector process src --dry-run -c tests/rector.php
    @printf "{{GREEN}}✓ Rector{{NC}} passed\n\n"

# Check PHPCS
[group('checks')]
checkPhpcs:
    {{PHP}} vendor/bin/phpcs --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache -q

_checkPhpcs:
    @printf "{{BLUE}}▸ Running{{NC}} PHPCS...\n"
    @{{PHP}} vendor/bin/phpcs --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache -q
    @printf "{{GREEN}}✓ PHPCS{{NC}} passed\n\n"

# Check PHP-CS-Fixer (dry-run)
[group('checks')]
checkPhpCsFixer:
    {{PHP}} vendor/bin/php-cs-fixer fix --dry-run --diff --quiet --config=tests/.php-cs-fixer.php

_checkPhpCsFixer:
    @printf "{{BLUE}}▸ Running{{NC}} PHP-CS-Fixer...\n"
    @{{PHP}} vendor/bin/php-cs-fixer fix --dry-run --diff --quiet --config=tests/.php-cs-fixer.php
    @printf "{{GREEN}}✓ PHP-CS-Fixer{{NC}} passed\n\n"

# Check Deptrac
[group('checks')]
checkDeptrac:
    {{PHP}} vendor/bin/deptrac analyse --config-file=tests/deptrac.yaml --no-progress

_checkDeptrac:
    @printf "{{BLUE}}▸ Running{{NC}} Deptrac...\n"
    @{{PHP}} vendor/bin/deptrac analyse --config-file=tests/deptrac.yaml --no-progress
    @printf "{{GREEN}}✓ Deptrac{{NC}} passed\n\n"

_checkComposer:
    @printf "{{BLUE}}▸ Validating{{NC}} composer.json...\n"
    @{{PHP}} composer validate --strict
    @printf "{{GREEN}}✓ Composer{{NC}} valid\n"

# Check accessibility (Pa11y)
[group('checks')]
checkA11y url='http://localhost/':
    @printf "{{BLUE}}▸ Running{{NC}} accessibility check...\n"
    @{{DOCKER}} build pa11y -q
    @{{DOCKER}} run --rm pa11y {{url}} --reporter cli --standard WCAG2AA
    @printf "{{GREEN}}✓ Accessibility{{NC}} check passed\n"

# Fix PHPCS violations
[group('fixing')]
fixPhpcs:
    @printf "{{YELLOW}}▸ Fixing{{NC}} PHPCS violations...\n"
    @{{PHP}} vendor/bin/phpcbf --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache
    @printf "{{GREEN}}✓ PHPCS{{NC}} violations fixed\n"

# Fix with PHP-CS-Fixer
[group('fixing')]
fixPhpCsFixer:
    @printf "{{YELLOW}}▸ Fixing{{NC}} with PHP-CS-Fixer...\n"
    @{{PHP}} vendor/bin/php-cs-fixer fix --verbose --config=tests/.php-cs-fixer.php
    @printf "{{GREEN}}✓ PHP-CS-Fixer{{NC}} fixes applied\n"

# Apply Rector fixes
[group('fixing')]
fixRector:
    @printf "{{YELLOW}}▸ Applying{{NC}} Rector fixes...\n"
    @{{PHP}} vendor/bin/rector process src -c tests/rector.php
    @printf "{{GREEN}}✓ Rector{{NC}} fixes applied\n"

# Generate coverage badge (CI)
[group('fixing')]
fixCoverageBadge:
    @printf "{{BLUE}}▸ Generating{{NC}} coverage badge...\n"
    @{{PHP}} vendor/bin/phpunit -c tests/phpunit.xml --no-progress
    @{{PHP}} php tests/badgeGenerator.php
    @git add tests/badge/coverage.svg
    @printf "{{GREEN}}✓ Coverage badge{{NC}} generated\n"
