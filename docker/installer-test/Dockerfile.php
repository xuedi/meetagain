# PHP-FPM image for installer testing
# Simulates a typical shared hosting PHP environment

FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    imagemagick-dev \
    oniguruma-dev \
    git \
    unzip \
    bash

# Install PHP extensions
# Note: iconv, ctype are built-in to PHP 8.4 and don't need installation
RUN docker-php-ext-install \
    pdo_mysql \
    intl \
    opcache \
    zip

# Install imagick via PECL
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apk del .build-deps

# Install APCu
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure PHP
RUN echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize=32M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size=32M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/custom.ini

# Set working directory
WORKDIR /var/www/html

# Arguments for user mapping
ARG HOST_UID=1000
ARG HOST_GID=1000

# Create user with same UID/GID as host user
RUN deluser --remove-home www-data 2>/dev/null || true \
    && addgroup -g ${HOST_GID} appuser \
    && adduser -D -u ${HOST_UID} -G appuser appuser \
    && chown -R appuser:appuser /var/www

USER appuser
