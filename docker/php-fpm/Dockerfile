FROM php:8.4-fpm

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions gd xdebug apcu pdo_mysql imagick intl opcache

RUN apt-get update && apt-get upgrade -y && apt-get install -y git unzip vim

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV PATH="$PATH:/usr/local/bin"

COPY ./php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html/

