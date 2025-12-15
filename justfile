# Docker configuration - all commands run inside containers
# Read the comments in this file to understand what each command does.
# Always use `just test` to run tests, not `just do "vendor/bin/phpunit ..."`.
set dotenv-load

DOCKER := "docker-compose --env-file .env -f docker/docker-compose.yml"
EXEC := DOCKER + " exec -e XDEBUG_MODE=coverage php"
JUST := just_executable() + " --justfile=" + justfile()

# Show available commands
default:
    {{JUST}} --list --unsorted

# Run any command inside the PHP container (e.g., just do "composer update")
do +parameter='':
    {{EXEC}} {{parameter}}

# Initial project setup: copies config files, starts containers, installs dependencies, runs migrations and fixtures
install:
    cp --no-clobber .env.dist .env
    cp --no-clobber config/plugins.dist.php config/plugins.php
    {{JUST}} dockerStart
    {{JUST}} appClearCache
    {{JUST}} appMigrate
    {{EXEC}} php bin/console doctrine:fixtures:load -q --group=install
    {{EXEC}} php bin/console app:translation:import 'https://dragon-descendants.de/api/translations'
    {{DOCKER}} exec -T mariadb mariadb -u root -p$MARIADB_ROOT_PASSWORD < docker/mariadb/init/01-create-test-db.sql

# Start all Docker containers in detached mode and prepare log directory
[group('docker')]
dockerStart:
	{{DOCKER}} up -d
	{{EXEC}} mkdir -p var/log
	{{EXEC}} touch var/log/dev.log
	{{EXEC}} truncate -s 0 var/log/dev.log

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

# Run any Symfony console command (e.g., just app cache:clear)
[group('app')]
app +parameter='':
    {{EXEC}} php bin/console {{parameter}}

# Run the scheduled cron tasks
[group('app')]
appCron:
    {{EXEC}} php bin/console app:cron

# Clear the dev.log file
[group('app')]
appClearLogs:
    {{EXEC}} truncate -s 0 var/log/dev.log

# Regenerate autoload files and clear Symfony cache
[group('app')]
appClearCache:
    {{EXEC}} composer dump-autoload
    {{EXEC}} php bin/console cache:clear

# Run pending database migrations (interactive)
[group('app')]
appMigrate:
    {{EXEC}} php bin/console doctrine:migrations:migrate -q

# Complete database reset: drops DB, recreates it, runs install and clears cache
[group('development')]
devReset:
    {{JUST}} devResetDatabase
    {{JUST}} appMigrate
    {{EXEC}} php bin/console doctrine:fixtures:load -q
    {{EXEC}} php bin/console app:translation:import 'https://dragon-descendants.de/api/translations'
    {{EXEC}} php bin/console app:event:extent
    {{EXEC}} php bin/console doctrine:database:drop --env=test --force --if-exists
    {{JUST}} testSetup

# delete and recreate the database
[group('development')]
devResetDatabase:
    {{EXEC}} php bin/console doctrine:database:drop --force
    {{EXEC}} php bin/console doctrine:database:create

# Run all tests and code quality checks
[group('testing')]
test: testUnit testFunctional checkStan checkRector checkPhpcs checkDeptrac
    {{EXEC}} composer validate --strict
    echo "All tests and checks passed successfully"

# Initialize test database schema and load fixtures (run once or after schema changes)
[group('testing')]
testSetup:
    {{EXEC}} php bin/console doctrine:database:create --env=test --if-not-exists
    {{EXEC}} php bin/console doctrine:schema:drop --env=test --force
    {{EXEC}} php bin/console doctrine:schema:create --env=test
    {{EXEC}} php bin/console doctrine:fixtures:load --env=test -q

# Run only unit tests (faster, no database required)
[group('testing')]
testUnit +parameter='':
    {{EXEC}} vendor/bin/phpunit -c tests/phpunit.xml --testsuite=default {{parameter}}

# Run only functional tests (click path / integration tests)
[group('testing')]
testFunctional:
    {{EXEC}} vendor/bin/phpunit -c tests/phpunit.xml --testsuite=functional

# Run PHPStan static analysis for type checking and bug detection
[group('checks')]
checkStan +parameter='':
    {{EXEC}} vendor/bin/phpstan analyse -c tests/phpstan.neon --memory-limit=256M {{parameter}}

# Run Rector in dry-run mode to check for code improvements without applying them
[group('checks')]
checkRector:
    {{EXEC}} vendor/bin/rector process src --dry-run -c tests/rector.php

# Run PHP CodeSniffer to check coding standards compliance
[group('checks')]
checkPhpcs:
    {{EXEC}} vendor/bin/phpcs --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache

# Run Deptrac to check architectural layer dependencies
[group('checks')]
checkDeptrac:
    {{EXEC}} vendor/bin/deptrac analyse --config-file=tests/deptrac.yaml

# Automatically fix coding standards violations using PHPCBF
[group('fixing')]
fixPhpcs:
    {{EXEC}} vendor/bin/phpcbf --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache

# Apply Rector refactorings to the codebase
[group('fixing')]
fixRector:
    {{EXEC}} vendor/bin/rector process src -c tests/rector.php

# Generate test coverage badge SVG and stage it for commit (CI only)
[group('fixing')]
fixCoverageBadge:
	{{EXEC}} php tests/badgeGenerator.php
	git add tests/badge/coverage.svg