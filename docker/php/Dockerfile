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

USER ${UNAME}
