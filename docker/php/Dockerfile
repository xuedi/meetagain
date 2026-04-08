FROM dunglas/frankenphp:1.9.0-php8.4.10-bookworm@sha256:0b6617c1cc1a77f49bebad093fa0d38e8a95d666facec7543f8f41e2ef17af24

WORKDIR /var/www/html

ARG UNAME=xuedi
ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
	acl \
    libnss3-tools \
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
    zip \
    redis \
	opcache

RUN groupadd -g $GID -o $UNAME
RUN useradd -m -u $UID -g $GID -o -s /bin/bash $UNAME
RUN setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp;
RUN chown -R ${UID}:${GID} /data/caddy && chown -R ${UID}:${GID} /config/caddy

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
ENV PATH="$PATH:/usr/local/bin"

COPY php.ini /usr/local/etc/php/conf.d/99-custom.ini

# --- Build tools (Dart Sass, Node.js, PurgeCSS) ---------------------------
# Dart Sass — pinned, baked into the image (replaces bin/install-dart-sass.sh)
ARG DART_SASS_VERSION=1.86.3
RUN set -eux; \
    curl -fsSL "https://github.com/sass/dart-sass/releases/download/${DART_SASS_VERSION}/dart-sass-${DART_SASS_VERSION}-linux-x64.tar.gz" \
      | tar xz -C /usr/local/ \
    && ln -s /usr/local/dart-sass/sass /usr/local/bin/sass

# Node.js 22 LTS (NodeSource) — provides node, npm, npx
RUN set -eux; \
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# PurgeCSS, installed globally so it's on PATH for all users
ARG PURGECSS_VERSION=6.0.0
RUN npm install -g purgecss@${PURGECSS_VERSION} \
    && npm cache clean --force

USER ${UNAME}
