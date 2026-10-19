# syntax=docker/dockerfile:1
#
# Build: docker build -t securities-manager-backend ./backend

FROM composer:2.8 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# The composer image lacks ext-gmp; the runtime stage below installs it.
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-req=ext-gmp

COPY . .

RUN composer install \
    --no-dev \
    --no-interaction \
    --optimize-autoloader \
    --ignore-platform-req=ext-gmp

FROM php:8.4-fpm-alpine AS runtime

# Runtime libraries stay; only the -dev packages are removed after the build.
RUN apk add --no-cache \
        nginx \
        supervisor \
        wget \
        libpq \
        icu-libs \
        oniguruma \
        gmp \
    && apk add --no-cache --virtual .build-deps \
        libpq-dev \
        icu-dev \
        oniguruma-dev \
        gmp-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        gmp \
        intl \
        mbstring \
        opcache \
        pdo_pgsql \
    && apk del --no-cache .build-deps \
    && rm -rf /var/cache/apk/*

COPY docker/php.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /run/nginx /var/log/supervisor \
    && chown -R www-data:www-data /run/nginx /var/lib/nginx

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app /var/www/html

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD wget -qO- http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
