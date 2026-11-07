# php/Dockerfile — multi-stage per Docker standard (PROMPT.md §5)
# Stages: base -> deps -> build -> test -> runtime

# ---- base: pinned PHP runtime + extensions ----
FROM php:8.5.11-fpm-alpine@sha256:placeholder AS base

# NOTE: replace `sha256:placeholder` with the real digest from
# `docker pull php:8.5.11-fpm-alpine --quiet` before first build.
# Pinning by digest is required (Phase 5/6 standard); left as placeholder
# because Docker is unavailable on this host for the digest lookup.

# System packages needed by the PHP extensions we install.
RUN apk add --no-cache \
        acl \
        bash \
        fcgi \
        file \
        gettext \
        git \
    ;

# PHP extensions: pdo pdo_mysql (required by App\Database\Connection),
# intl/zip/apcu/opcache (carried from the original image), xdebug only in dev.
RUN install-php-extensions \
        intl \
        zip \
        apcu \
        opcache \
        pdo \
        pdo_mysql \
    ;

ENV COMPOSER_ALLOW_SUPERUSER=1

# ---- deps: Composer install (cache mount, layer before app copy) ----
FROM base AS deps

COPY --from=composer:2.10@sha256:placeholder /usr/bin/composer /usr/bin/composer

# NOTE: composer digest placeholder — replace with real digest from
# `docker pull composer:2.10 --quiet` before first build.

WORKDIR /var/www/html

# Copy only the manifest first to leverage Docker layer caching.
COPY app/composer.json app/composer.json

# composer.lock is not tracked in this repo (deliberately deleted per history),
# so composer install resolves fresh each build. Restoring composer.lock is a
# Phase 6 item; until then builds are non-reproducible (documented in TD-6).
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --prefer-dist --no-dev --no-scripts --no-progress --no-interaction;

# ---- build: copy application code ----
FROM deps AS build

WORKDIR /var/www/html

COPY app/ .

# Optimize autoloader for production.
RUN composer dump-autoload --optimize;

# ---- test: run the test suite ----
FROM build AS test

# Dev dependencies are needed to run PHPUnit.
RUN composer install --prefer-dist --no-scripts --no-progress --no-interaction;

# Run the suite; `docker build --target test` fails if tests fail.
RUN vendor/bin/phpunit --configuration phpunit.xml;

# ---- runtime: slim production image, non-root ----
FROM base AS runtime

WORKDIR /var/www/html

# Copy only the optimized autoloader + source from the build stage.
COPY --from=build /var/www/html/vendor ./vendor
COPY --from=build /var/www/html/src ./src
COPY --from=build /var/www/html/public ./public

# Runtime needs no Composer, no dev tools, no git.
RUN apk del --no-cache git 2>/dev/null || true;

# Run as non-root (PHP-FPM user exists in the official image).
USER www-data

# HEALTHCHECK: PHP-FPM listens on 9000; a simple `php -v` confirms the runtime works.
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php -r 'exit(0);' || exit 1

# Exec-form entrypoint; handles SIGTERM for graceful shutdown.
ENTRYPOINT ["php-fpm"]
EXPOSE 9000

# ---- dev: extends app with Xdebug + Symfony CLI ----
FROM app AS app_dev

ENV XDEBUG_MODE=off
COPY php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

RUN install-php-extensions xdebug; \
    curl -sS https://get.symfony.com/cli/installer | bash; \
    mv /root/.symfony*/bin/symfony /usr/local/bin/symfony
