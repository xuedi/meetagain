# Docker configuration - all commands run inside containers
# Read the comments in this file to understand what each command does.
# Always use `just test` to run tests, not `just do "vendor/bin/phpunit ..."`.
set dotenv-load

DOCKER := "docker-compose --env-file .env.dist -f docker/docker-compose.yml"
PHP := DOCKER + " exec -e XDEBUG_MODE=coverage php"
DB := DOCKER + " exec mariadb"
JUST := just_executable() + " --justfile=" + justfile()

# Show available commands
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

# Alias to start the docker stack
start: dockerStart

# Alias to stop the docker stack
stop: dockerStop

# Run any command inside the PHP container (e.g., just do "composer update")
do +parameter='':
    {{PHP}} {{parameter}}

# Start all Docker containers in detached mode and prepare log directory
[group('docker')]
dockerStart:
	{{DOCKER}} up -d
	{{PHP}} mkdir -p var/log
	{{PHP}} touch var/log/dev.log
	{{PHP}} truncate -s 0 var/log/dev.log

# Stop and remove all Docker containers
[group('docker')]
dockerStop:
	{{DOCKER}} down

# Restarts all Docker containers (alias for stop & start)
[group('docker')]
dockerRestart: dockerStop dockerStart

# Rebuild the PHP Docker image from scratch (no cache)
[group('docker')]
dockerRebuild:
    {{DOCKER}} build --no-cache php

# Open an interactive bash shell inside the PHP container
[group('docker')]
dockerEnter:
    {{DOCKER}} exec php bash

# Allows an AI assistant to run SQL command inside the docker container
[group('docker')]
dockerDatabase query:
    {{DB}} mariadb -u$MARIADB_USER -p$MARIADB_PASSWORD $MARIADB_DATABASE -e "{{query}}"

# Run any Symfony console command (e.g., just app cache:clear)
[group('app')]
app +parameter='':
    {{PHP}} php bin/console {{parameter}}

# Run the scheduled cron tasks
[group('app')]
appCron:
    {{PHP}} php bin/console app:cron

# Send RSVP notifications for upcoming events
[group('app')]
appRsvpNotify:
    {{PHP}} php bin/console app:rsvp:notify
    {{JUST}} appCron

# Clear the dev.log file
[group('app')]
appClearLogs:
    {{PHP}} truncate -s 0 var/log/dev.log

# Regenerate autoload files and clear Symfony cache
[group('app')]
appClearCache:
    {{PHP}} composer dump-autoload
    {{PHP}} php bin/console cache:clear

# Run pending database migrations (non-interactive)
[group('app')]
appMigrate:
    {{PHP}} php bin/console doctrine:migrations:migrate -q

# Reset to development mode with fixtures: reinstalls app, loads fixtures, imports translations, sets up test DB
[group('development')]
devModeFixtures:
    {{JUST}} dockerStop
    {{JUST}} devResetConfigs
    cp .env.dist .env
    cp config/plugins.dist.php config/plugins.php
    touch installed.lock
    {{JUST}} dockerStart
    {{JUST}} do "composer install"
    {{JUST}} devResetDatabase
    {{JUST}} appMigrate
    {{PHP}} php bin/console doctrine:fixtures:load -q
    {{PHP}} php bin/console app:translation:import 'https://dragon-descendants.de/api/translations'
    {{PHP}} php bin/console app:event:extent
    {{PHP}} php bin/console app:event:add-fixture-rsvps

# Switch to installer mode: resets database and removes .env and installed.lock
[group('development')]
devModeInstaller:
    {{JUST}} dockerStop
    {{JUST}} devResetConfigs
    {{JUST}} dockerStart
    {{JUST}} devResetDatabase
    rm -f .env installed.lock
    @echo ""
    @echo "Access: http://localhost/install/"

# Clean all generated files (runs without Docker)
[group('development')]
devResetConfigs:
    rm -rf .env installed.lock config/plugins.php var/

# Clean all generated files (runs without Docker)
[group('development')]
devResetToFreshCloneState:
    rm -rf .env installed.lock config/plugins.php vendor/ var/ public/bundles/

# Delete and recreate the database
[group('development')]
devResetDatabase:
    {{PHP}} php bin/console doctrine:database:drop --force --if-exists
    {{PHP}} php bin/console doctrine:database:create --if-not-exists

# Run all tests and code quality checks
[group('testing')]
test: testSetup testUnit testFunctional checkStan checkRector checkPhpcs checkPhpCsFixer checkDeptrac
    {{PHP}} composer validate --strict
    echo "All tests and checks passed successfully"

# Initialize test database schema and load fixtures (run once or after schema changes)
[group('testing')]
testSetup:
    {{PHP}} php bin/console doctrine:database:drop --env=test --force --if-exists
    {{PHP}} php bin/console doctrine:database:create --env=test
    {{PHP}} php bin/console doctrine:schema:create --env=test -q
    {{PHP}} php bin/console doctrine:fixtures:load --env=test -q

# Run only unit tests (faster, no database required)
[group('testing')]
testUnit +parameter='':
    @{{PHP}} vendor/bin/phpunit -c tests/phpunit.xml --testsuite=default --no-progress {{parameter}}

# Run only functional tests (click no, keep thatpath / integration tests)
[group('testing')]
testFunctional +parameter='':
    @{{PHP}} vendor/bin/phpunit -c tests/phpunit.xml --testsuite=functional --no-progress {{parameter}}

# Show test coverage report in AI-readable format (runs tests first to generate coverage)
[group('testing')]
showCoverage +parameter='':
    {{PHP}} vendor/bin/phpunit -c tests/phpunit.xml
    {{PHP}} php tests/AiReadableTestCoverage.php {{parameter}}

# Analyze routes for performance metrics (SQL queries, timing, memory)
[group('testing')]
routeMetrics +parameter='':
    {{PHP}} php tests/AiReadableRouteMetrics.php {{parameter}}

# Run PHPStan static analysis for type checking and bug detection
[group('checks')]
checkStan +parameter='':
    @{{PHP}} vendor/bin/phpstan analyse -c tests/phpstan.neon --memory-limit=256M --no-progress {{parameter}}

# Run Rector in dry-run mode to check for code improvements without applying them
[group('checks')]
checkRector:
    @{{PHP}} vendor/bin/rector process src --dry-run -c tests/rector.php

# Run PHP CodeSniffer to check coding standards compliance
[group('checks')]
checkPhpcs:
    @{{PHP}} vendor/bin/phpcs --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache -q

# Run PHP-CS-Fixer to check code style (dry-run)
[group('checks')]
checkPhpCsFixer:
    @{{PHP}} vendor/bin/php-cs-fixer fix --dry-run --diff --quiet --config=tests/.php-cs-fixer.php

# Run Deptrac to check architectural layer dependencies
[group('checks')]
checkDeptrac:
    @{{PHP}} vendor/bin/deptrac analyse --config-file=tests/deptrac.yaml --no-progress

# Run Pa11y accessibility check with human-readable output
[group('checks')]
checkA11y url='http://localhost/':
    {{DOCKER}} build pa11y -q
    {{DOCKER}} run --rm pa11y {{url}} --reporter cli --standard WCAG2AA

# Automatically fix coding standards violations using PHPCBF
[group('fixing')]
fixPhpcs:
    @{{PHP}} vendor/bin/phpcbf --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache

# Automatically fix code style using PHP-CS-Fixer
[group('fixing')]
fixPhpCsFixer:
    @{{PHP}} vendor/bin/php-cs-fixer fix --verbose --config=tests/.php-cs-fixer.php

# Apply Rector refactorings to the codebase
[group('fixing')]
fixRector:
    @{{PHP}} vendor/bin/rector process src -c tests/rector.php

# Generate test coverage badge SVG and stage it for commit (CI only)
[group('fixing')]
fixCoverageBadge:
	@{{PHP}} vendor/bin/phpunit -c tests/phpunit.xml --no-progress
	@{{PHP}} php tests/badgeGenerator.php
	@git add tests/badge/coverage.svg