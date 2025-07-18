DOCKER := "docker-compose --env-file .env -f docker/docker-compose.yml"
EXEC := DOCKER + " exec -e XDEBUG_MODE=coverage php"
JUST := just_executable() + " --justfile=" + justfile()

default:
    {{JUST}} --list

install:
    cp --no-clobber .env.dist .env
    cp config/plugins.dist.php config/plugins.php
    {{JUST}} start
    {{EXEC}} composer install
    {{EXEC}} php bin/console cache:clear
    {{EXEC}} php bin/console doctrine:migrations:migrate -q
    {{EXEC}} php bin/console doctrine:fixtures:load --append -q
    {{EXEC}} php bin/console app:translation:import 'https://dragon-descendants.de/api/translations'
    {{EXEC}} php bin/console app:event:extent

clearLogs:
    {{EXEC}} truncate -s 0 var/log/dev.log

clearCache:
    {{EXEC}} composer dump-autoload
    {{EXEC}} php bin/console cache:clear

app +parameter='':
    {{EXEC}} php bin/console {{parameter}}

do +parameter='':
    {{EXEC}} {{parameter}}

start:
	{{DOCKER}} up -d
	{{EXEC}} mkdir -p var/log
	{{EXEC}} touch var/log/dev.log
	{{EXEC}} truncate -s 0 var/log/dev.log

stop:
	{{DOCKER}} down

devMigrate:
    {{EXEC}} php bin/console doctrine:migrations:migrate

devFixtures:
    {{EXEC}} php bin/console doctrine:fixtures:load

devReset:
    {{EXEC}} php bin/console doctrine:database:drop --force
    {{EXEC}} php bin/console doctrine:database:create
    {{JUST}} install

dockerRebuild:
    {{DOCKER}} build --no-cache php

dockerEnter:
    {{DOCKER}} exec php bash

test:
    {{EXEC}} vendor/bin/phpunit -c tests/phpunit.xml

check: test checkStan checkRector checkPhpcs checkPsalm
    {{EXEC}} composer validate --strict
    echo "Did run all checks successfully"

checkStan:
    {{EXEC}} vendor/bin/phpstan analyse -c tests/phpstan.neon --memory-limit=256M

checkRector:
    {{EXEC}} vendor/bin/rector process src --dry-run -c tests/rector.php

checkPhpcs:
    {{EXEC}} vendor/bin/phpcs --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache

checkPsalm:
    {{EXEC}} vendor/bin/psalm --threads=8 --config='tests/psalm.xml' --show-info=true

checkAutoFix:
    {{EXEC}} vendor/bin/phpcbf --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache
    {{EXEC}} vendor/bin/rector process src -c tests/rector.php

update_coverage_badge: ## generate badge and add it to repo
	{{EXEC}} php tests/badgeGenerator.php
	git add tests/badge/coverage.svg
