# Docker configuration - all commands run inside containers
# Read the comments in this file to understand what each command does.
# Always use `just test` to run tests, not `just do "vendor/bin/phpunit ..."`.
DOCKER := "docker-compose --env-file .env -f docker/docker-compose.yml"
EXEC := DOCKER + " exec -e XDEBUG_MODE=coverage php"
JUST := just_executable() + " --justfile=" + justfile()

# Show available commands
default:
    {{JUST}} --list

# Initial project setup: copies config files, starts containers, installs dependencies, runs migrations and fixtures
install:
    cp --no-clobber .env.dist .env
    cp --no-clobber config/plugins.dist.php config/plugins.php
    {{JUST}} start
    {{JUST}} clearCache
    {{JUST}} migrate
    {{EXEC}} php bin/console doctrine:fixtures:load -q --group=install
    {{EXEC}} php bin/console app:translation:import 'https://dragon-descendants.de/api/translations'

# Start all Docker containers in detached mode and prepare log directory
start:
	{{DOCKER}} up -d
	{{EXEC}} mkdir -p var/log
	{{EXEC}} touch var/log/dev.log
	{{EXEC}} truncate -s 0 var/log/dev.log

# Stop and remove all Docker containers
stop:
	{{DOCKER}} down

# Rebuild the PHP Docker image from scratch (no cache)
dockerRebuild:
    {{DOCKER}} build --no-cache php

# Open an interactive bash shell inside the PHP container
dockerEnter:
    {{DOCKER}} exec php bash

# Run any Symfony console command (e.g., just app cache:clear)
app +parameter='':
    {{EXEC}} php bin/console {{parameter}}

# Run any command inside the PHP container (e.g., just do "composer update")
do +parameter='':
    {{EXEC}} {{parameter}}

# Run the scheduled cron tasks
cron:
    {{EXEC}} php bin/console app:cron

# Clear the dev.log file
clearLogs:
    {{EXEC}} truncate -s 0 var/log/dev.log

# Regenerate autoload files and clear Symfony cache
clearCache:
    {{EXEC}} composer dump-autoload
    {{EXEC}} php bin/console cache:clear

# Run pending database migrations (interactive)
migrate:
    {{EXEC}} php bin/console doctrine:migrations:migrate -q

# Complete database reset: drops DB, recreates it, runs install and clears cache
devReset:
    {{JUST}} clearCache
    {{JUST}} devResetDatabase
    {{JUST}} migrate
    {{EXEC}} php bin/console doctrine:fixtures:load -q
    {{EXEC}} php bin/console app:translation:import 'https://dragon-descendants.de/api/translations'
    {{EXEC}} php bin/console app:event:extent

# delete and recreate the database
devResetDatabase:
    {{EXEC}} php bin/console doctrine:database:drop --force
    {{EXEC}} php bin/console doctrine:database:create

# Run all tests and code quality checks
test: test-unit test-functional checkStan checkRector checkPhpcs checkPsalm
    {{EXEC}} composer validate --strict
    echo "All tests and checks passed successfully"

# Run only unit tests (faster, no database required)
test-unit +parameter='':
    {{EXEC}} vendor/bin/phpunit -c tests/phpunit.xml --testsuite=default {{parameter}}

# Run only functional tests (click path / integration tests)
test-functional:
    {{EXEC}} vendor/bin/phpunit -c tests/phpunit.xml --testsuite=functional

# Run PHPStan static analysis for type checking and bug detection
checkStan +parameter='':
    {{EXEC}} vendor/bin/phpstan analyse -c tests/phpstan.neon --memory-limit=256M {{parameter}}

# Run Rector in dry-run mode to check for code improvements without applying them
checkRector:
    {{EXEC}} vendor/bin/rector process src --dry-run -c tests/rector.php

# Run PHP CodeSniffer to check coding standards compliance
checkPhpcs:
    {{EXEC}} vendor/bin/phpcs --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache

# Run Psalm static analysis for finding type errors (currently disabled - no Symfony 8 support)
checkPsalm:
#    {{EXEC}} vendor/bin/psalm --threads=8 --config='tests/psalm.xml' --show-info=true

# Automatically fix code style issues (PHPCBF) and apply Rector refactorings
checkAutoFix:
    {{EXEC}} vendor/bin/phpcbf --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache
    {{EXEC}} vendor/bin/rector process src -c tests/rector.php

# Generate test coverage badge SVG and stage it for commit (CI only)
update_coverage_badge:
	{{EXEC}} php tests/badgeGenerator.php
	git add tests/badge/coverage.svg