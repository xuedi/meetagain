FROM dunglas/frankenphp:1.8.0-php8.4.10-bookworm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
	acl \
	file \
	gettext \
	git \
	&& rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    install-php-extensions \
    gd \
    xdebug-3.4.0 \
	pdo_mysql \
	imagick \
	intl \
	apcu \
	opcache

