DOCKER := "docker-compose --env-file .env -f docker/docker-compose.yml"
PHP := DOCKER + " exec php-fpm"
JUST := just_executable() + " --justfile=" + justfile()

install:
    cp --no-clobber .env.dist .env
    {{JUST}} up
    {{PHP}} composer install
    {{PHP}} php bin/console cache:clear
    {{PHP}} php bin/console doctrine:schema:drop --force -q
    {{PHP}} php bin/console doctrine:schema:create -q
    {{PHP}} php bin/console doctrine:fixtures:load --append -q
    {{PHP}} php bin/console app:translation:import 'https://www.dragon-descendants.de/api/translations'
    {{PHP}} php bin/console app:event:extent

up:
	{{DOCKER}} up -d
	{{PHP}} truncate -s 0 var/log/dev.log

down:
	{{DOCKER}} down

clearLogs:
    {{PHP}} truncate -s 0 var/log/dev.log

clearCache:
    {{PHP}} composer dump-autoload
    {{PHP}} php bin/console cache:clear

extendEvents:
    {{PHP}} php bin/console app:event:extent

translationsExtract:
    {{PHP}} php bin/console translation:extract --force --format php de
    {{PHP}} php bin/console translation:extract --force --format php en
    {{PHP}} php bin/console translation:extract --force --format php cn

test:
    {{PHP}} XDEBUG_MODE=coverage vendor/bin/phpunit -c tests/phpunit.xml

check:
    {{PHP}} # stan & rector are disabled, since they cant deal with php8.4's: "new DateTime()->"
    {{PHP}} #vendor/bin/phpstan analyse -c tests/phpstan.neon
    {{PHP}} #vendor/bin/rector process src --dry-run -c tests/rector.php
    {{PHP}} vendor/bin/phpcs --standard=./tests/phpcs.xml --cache=var/cache/phpcs.cache
    {{PHP}} #vendor/bin/psalm --threads=8 --config='tests/psalm.xml' --show-info=true

fix:
    {{PHP}} vendor/bin/phpcbf --standard=./tests/phpcs.xml
    {{PHP}} #vendor/bin/rector process src -c tests/rector.php

update_coverage_badge: ## generate badge and add it to repo
	{{PHP}} php tests/badgeGenerator.php
	git add tests/badge/coverage.svg

rebuild:
    {{DOCKER}} build --no-cache php-fpm
