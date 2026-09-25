# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.4
ARG K6_VERSION=2.0.0

# ---------------------------------------------------------------------------
# PHP dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --ignore-platform-reqs

# ---------------------------------------------------------------------------
# Frontend assets (no environment is baked in — websocket settings are
# rendered at runtime, so one image works on any host)
# ---------------------------------------------------------------------------
FROM node:24-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY --from=vendor /app/vendor ./vendor
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---------------------------------------------------------------------------
# k6 (pinned) — copied from the official image
# ---------------------------------------------------------------------------
FROM grafana/k6:${K6_VERSION} AS k6

# ---------------------------------------------------------------------------
# Runtime: FrankenPHP (Caddy + PHP) — the same image runs the web app,
# Reverb, the Horizon runner (which executes k6) and the scheduler.
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php${PHP_VERSION}-bookworm AS runtime

ARG K6_VERSION
LABEL org.opencontainers.image.title="Straden" \
      org.opencontainers.image.description="Self-hosted load testing platform powered by k6" \
      org.opencontainers.image.source="https://github.com/kwasii1/straden" \
      dev.straden.k6.version="${K6_VERSION}"

RUN apt-get update \
    && apt-get install -y --no-install-recommends git curl ca-certificates procps \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_pgsql redis pcntl posix intl zip bcmath opcache

COPY --from=k6 /usr/bin/k6 /usr/local/bin/k6
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && rm -f /usr/local/bin/composer \
    && ln -sfn /app/storage/app/public /app/public/storage \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && rm -rf storage/logs/* storage/framework/views/* storage/framework/cache/data bootstrap/cache/*.php \
    && cp docker/php.ini "$PHP_INI_DIR/conf.d/zz-straden.ini" \
    && cp docker/Caddyfile /etc/frankenphp/Caddyfile \
    && install -m 0755 docker/entrypoint.sh /usr/local/bin/straden-entrypoint \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /data/caddy /config/caddy

ENV STRADEN_ROLE=app \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    HOME=/app/storage/app/.home \
    XDG_CONFIG_HOME=/config \
    XDG_DATA_HOME=/data

USER www-data

EXPOSE 80 443

HEALTHCHECK --interval=10s --timeout=5s --start-period=60s --retries=6 \
    CMD straden-entrypoint healthcheck

ENTRYPOINT ["straden-entrypoint"]
