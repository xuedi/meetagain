# check=skip=SecretsUsedInArgOrEnv

FROM dunglas/frankenphp:1.13.5-php8.5.12-bookworm@sha256:681db339fc7751be886ac69b6daab1ac32c0a78ed8fa255abdacc75391a00480 AS runtime

WORKDIR /var/www/html

RUN set -eux; \
    install-php-extensions \
        bcmath \
        gd \
        pdo_mysql \
        imagick \
        intl \
        apcu \
        pcntl \
        zip \
        redis \
        opcache

COPY source/docker/release/imagemagick/policy.xml /etc/ImageMagick-6/policy.xml
RUN --mount=type=bind,source=source/docker/release/imagemagick/check-policy.php,target=/tmp/check-policy.php \
    php /tmp/check-policy.php

# With every capability dropped, a binary that carries file capabilities fails to start.
RUN groupadd -g 1000 meetagain \
    && useradd -M -u 1000 -g 1000 -s /usr/sbin/nologin meetagain \
    && chown -R 1000:1000 /data/caddy /config/caddy \
    && setcap -r /usr/local/bin/frankenphp

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY source/docker/release/php.ini /usr/local/etc/php/conf.d/99-meetagain.ini
COPY source/docker/release/Caddyfile /etc/frankenphp/Caddyfile

ENV XDG_CACHE_HOME=/tmp


FROM runtime AS manifests

COPY --link source/ /source/
RUN set -eux; \
    cd /source; \
    for file in composer.json composer.lock plugins/*/composer.json; do \
        if [ -f "$file" ]; then install -D -m 0644 "$file" "/manifests/$file"; fi; \
    done


FROM runtime AS builder

RUN echo 'memory_limit=1G' > "$PHP_INI_DIR/conf.d/zz-build.ini"

COPY --from=composer:2.11.2@sha256:5bcb28b8d0edc4091125f8218f9d088f998bdfc843f8fbb9a4beb4dcd8625684 /usr/bin/composer /usr/local/bin/composer

ARG DART_SASS_VERSION=1.86.3
RUN set -eux; \
    curl -fsSL "https://github.com/sass/dart-sass/releases/download/${DART_SASS_VERSION}/dart-sass-${DART_SASS_VERSION}-linux-x64.tar.gz" \
      | tar xz -C /usr/local/ \
    && ln -s /usr/local/dart-sass/sass /usr/local/bin/sass

RUN set -eux; \
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

ARG PURGECSS_VERSION=6.0.0
RUN npm install -g --ignore-scripts purgecss@${PURGECSS_VERSION} \
    && npm cache clean --force

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

COPY --link --from=manifests /manifests/ ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-progress --prefer-dist

COPY --link source/ ./

# Only build-time stand-ins: nothing here connects, and none of it is written into the image.
ARG DATABASE_URL="mysql://build:build@127.0.0.1:3306/build?serverVersion=13.0.2-MariaDB&charset=utf8mb4" \
    REDIS_URL="redis://127.0.0.1:6379" \
    MAILER_DSN="null://null" \
    APP_SECRET="build-only" \
    SENTRY_DSN=""

RUN set -eux; \
    cp assets/styles/_config.scss.dist assets/styles/_config.scss; \
    ln -s /usr/local/dart-sass bin/dart-sass; \
    rm -rf tests docs docker bin/tools plugins/*/tools plugins/*/docker plugins/*/tests modules/*/tests plugins/*/modules/*/tests \
        src/DataFixtures src/DataImportFixtures plugins/*/src/DataFixtures; \
    composer dump-autoload --no-dev --optimize --no-scripts; \
    php bin/console importmap:install; \
    php bin/console sass:build; \
    php bin/console asset-map:compile; \
    php bin/console assets:install public; \
    php bin/console app:media:compile; \
    purgecss --config assets/purgecss.config.js; \
    php bin/console cache:warmup; \
    rm -f .env .env.dist .env.local .env.local.php .env.test; \
    rm -rf var/log var/tmp node_modules bin/dart-sass

COPY source/docker/release/env.local.php .env.local.php


FROM runtime

COPY --from=builder /var/www/html /var/www/html
COPY source/docker/release/bin/ /usr/local/bin/
RUN mkdir -p var/cache/prod/pools/system data public/images/thumbnails \
    && chown 1000:1000 data public/images/thumbnails \
    && chown -R 1000:1000 var/cache/prod/pools/system

USER 1000:1000

ENTRYPOINT ["meetagain-entrypoint"]
CMD ["web"]
HEALTHCHECK NONE

ARG CREATED \
    REVISION \
    VERSION \
    DISPLAY_VERSION \
    RELEASE \
    TITLE="MeetAgain" \
    DESCRIPTION="MeetAgain with every bundled plugin" \
    SOURCE="https://github.com/xuedi/meetagain" \
    LICENSES="EUPL-1.2"

ENV SENTRY_RELEASE="${RELEASE}" \
    APP_VERSION="${VERSION}" \
    APP_DISPLAY_VERSION="${DISPLAY_VERSION}" \
    APP_BUILT="${CREATED}"

EXPOSE 8080

LABEL org.opencontainers.image.title="${TITLE}" \
      org.opencontainers.image.description="${DESCRIPTION}" \
      org.opencontainers.image.version="${VERSION}" \
      org.opencontainers.image.vendor="MeetAgain" \
      org.opencontainers.image.url="https://meetagain.org" \
      org.opencontainers.image.licenses="${LICENSES}" \
      org.opencontainers.image.created="${CREATED}" \
      org.opencontainers.image.revision="${REVISION}" \
      org.opencontainers.image.source="${SOURCE}"
