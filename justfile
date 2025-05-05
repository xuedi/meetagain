reset:
    php bin/console doctrine:schema:drop --force -q
    php bin/console doctrine:schema:create -q
    php bin/console doctrine:fixtures:load --append -q
    php bin/console app:translation:import 'https://www.dragon-descendants.de/api/translations'
    just extendEvents

deploy:
    git pull
    composer dump-env prod
    composer install --no-dev --optimize-autoloader
    php bin/console asset-map:compile
    php bin/console doctrine:migrations:migrate --allow-no-migration --no-interaction
    php bin/console cache:clear --env=prod
    php bin/console cache:warmup --env=prod

install:
    cp -n .env.dist .env
    mkdir -p var/log/
    touch var/log/dev.log
    composer install

run:
    truncate -s 0 var/log/dev.log
    symfony server:start --no-tls

make:
    php bin/console make

emailTest:
    php bin/console mailer:test xuedi.beijing@gmail.com --from="dev@dragon-descendants.de" --subject="TestEmailFromDev" --body="emailBody"

extendEvents:
    php bin/console app:event:extent

translationsExtract:
    php bin/console translation:extract --force --format php de
    php bin/console translation:extract --force --format php en
    php bin/console translation:extract --force --format php cn

clearLogs:
    truncate -s 0 var/log/dev.log

clearCache:
    composer dump-autoload
    php bin/console cache:clear

test:
    XDEBUG_MODE=coverage vendor/bin/phpunit -c tests/phpunit.xml

check:
    vendor/bin/rector process src --dry-run -c tests/rector.php
    vendor/bin/phpstan analyse -c tests/phpstan.neon
    #vendor/bin/psalm --threads=8 --config='tests/psalm.xml' --show-info=true
    #phpcs --_all inline since cant give config as parameter

fix:
    vendor/bin/rector process src -c tests/rector.php