# check=skip=SecretsUsedInArgOrEnv

FROM dunglas/frankenphp:1.13.4-php8.5.12-bookworm@sha256:924b91db4f9aa1e6b1e40b8065ebe4ed6205cce7fe47fa49b55759143f09165d AS runtime

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

RUN set -eux; \
    for coder in MSL MVG SVG MSVG TEXT LABEL; do \
      sed -i "s|</policymap>|  <policy domain=\"coder\" rights=\"none\" pattern=\"${coder}\" />\n</policymap>|" /etc/ImageMagick-6/policy.xml; \
      grep -q "domain=\"coder\" rights=\"none\" pattern=\"${coder}\"" /etc/ImageMagick-6/policy.xml \
        || { echo "policy.xml is missing a denial for ${coder}"; exit 1; }; \
    done; \
    printf '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>' > /tmp/policy-probe.svg; \
    php -r 'try { (new Imagick())->readImage("/tmp/policy-probe.svg"); } catch (Throwable) { exit(0); } fwrite(STDERR, "ImageMagick still reads SVG - policy.xml is not in force\n"); exit(1);'; \
    rm -f /tmp/policy-probe.svg

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

COPY --from=composer:2.10.3@sha256:2cf275428757e5435a667bfc0d038b14520ca02be2b1b70d44a05c8c19e2de48 /usr/bin/composer /usr/local/bin/composer

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
    RELEASE \
    TITLE="MeetAgain" \
    DESCRIPTION="MeetAgain with every bundled plugin" \
    SOURCE="https://github.com/xuedi/meetagain" \
    LICENSES="EUPL-1.2"

ENV SENTRY_RELEASE="${RELEASE}" \
    APP_VERSION="${VERSION}" \
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
