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

# Reset dev with fixtures
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

# Run all tests and checks
[group('testing')]
test: testSetup testUnit testFunctional checkStan checkRector checkPhpcs checkPhpCsFixer checkDeptrac
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
    @{{PHP}} vendor/bin/phpunit -c tests/phpunit.xml --testsuite=default --no-progress {{parameter}}

# Run functional tests
[group('testing')]
testFunctional +parameter='':
    @{{PHP}} vendor/bin/phpunit -c tests/phpunit.xml --testsuite=functional --no-progress {{parameter}}

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
    @{{PHP}} vendor/bin/phpstan analyse -c tests/phpstan.neon --memory-limit=256M --no-progress {{parameter}}

# Check Rector (dry-run)
[group('checks')]
checkRector:
    @{{PHP}} vendor/bin/rector process src --dry-run -c tests/rector.php

# Check PHPCS
[group('checks')]
checkPhpcs:
    @{{PHP}} vendor/bin/phpcs --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache -q

# Check PHP-CS-Fixer (dry-run)
[group('checks')]
checkPhpCsFixer:
    @{{PHP}} vendor/bin/php-cs-fixer fix --dry-run --diff --quiet --config=tests/.php-cs-fixer.php

# Check Deptrac
[group('checks')]
checkDeptrac:
    @{{PHP}} vendor/bin/deptrac analyse --config-file=tests/deptrac.yaml --no-progress

# Check accessibility (Pa11y)
[group('checks')]
checkA11y url='http://localhost/':
    {{DOCKER}} build pa11y -q
    {{DOCKER}} run --rm pa11y {{url}} --reporter cli --standard WCAG2AA

# Fix PHPCS violations
[group('fixing')]
fixPhpcs:
    @{{PHP}} vendor/bin/phpcbf --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache

# Fix with PHP-CS-Fixer
[group('fixing')]
fixPhpCsFixer:
    @{{PHP}} vendor/bin/php-cs-fixer fix --verbose --config=tests/.php-cs-fixer.php

# Apply Rector fixes
[group('fixing')]
fixRector:
    @{{PHP}} vendor/bin/rector process src -c tests/rector.php

# Generate coverage badge (CI)
[group('fixing')]
fixCoverageBadge:
	@{{PHP}} vendor/bin/phpunit -c tests/phpunit.xml --no-progress
	@{{PHP}} php tests/badgeGenerator.php
	@git add tests/badge/coverage.svg