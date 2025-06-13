DOCKER := "docker-compose --env-file .env -f docker/docker-compose.yml"
PHP := DOCKER + " exec -e XDEBUG_MODE=coverage php-fpm"
JUST := just_executable() + " --justfile=" + justfile()

install:
    cp --no-clobber .env.dist .env
    {{JUST}} start
    {{PHP}} composer install
    {{PHP}} php bin/console cache:clear
    {{PHP}} php bin/console doctrine:schema:drop --force -q
    {{PHP}} php bin/console doctrine:schema:create -q
    {{PHP}} php bin/console doctrine:fixtures:load --append -q
    {{PHP}} php bin/console app:translation:import 'https://www.dragon-descendants.de/api/translations'
    {{PHP}} php bin/console app:event:extent

clearLogs:
    {{PHP}} truncate -s 0 var/log/dev.log

clearCache:
    {{PHP}} composer dump-autoload
    {{PHP}} php bin/console cache:clear

app +parameter='':
    {{PHP}} php bin/console {{parameter}}

do +parameter='':
    {{PHP}} {{parameter}}

start:
	{{DOCKER}} up -d
	{{PHP}} truncate -s 0 var/log/dev.log

stop:
	{{DOCKER}} down

dockerRebuild:
    {{DOCKER}} build --no-cache php-fpm

dockerEnter:
    {{DOCKER}} exec php-fpm bash

test:
    {{PHP}} vendor/bin/phpunit -c tests/phpunit.xml

check: test checkStan checkRector checkPhpcs checkPsalm
    {{PHP}} composer validate --strict
    echo "Did run all checks successfully"

checkStan:
    {{PHP}} vendor/bin/phpstan analyse -c tests/phpstan.neon --memory-limit=256M

checkRector:
    {{PHP}} vendor/bin/rector process src --dry-run -c tests/rector.php

checkPhpcs:
    {{PHP}} vendor/bin/phpcs --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache

checkPsalm:
    {{PHP}} vendor/bin/psalm --threads=8 --config='tests/psalm.xml' --show-info=true

checkAutoFix:
    {{PHP}} vendor/bin/phpcbf --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache
    {{PHP}} vendor/bin/rector process src -c tests/rector.php

update_coverage_badge: ## generate badge and add it to repo
	{{PHP}} php tests/badgeGenerator.php
	git add tests/badge/coverage.svg