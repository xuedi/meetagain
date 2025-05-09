FROM php:8.4-fpm

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN set -eux; \
    install-php-extensions \
    gd \
    xdebug \
    acpu \
    bz2 \
    bcmath \
    geoip \
    intl \
    pdo_mysql \
    sodium \
    opcache
