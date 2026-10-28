# Image de production : PHP-FPM + code et dépendances (sans outils de développement).
#   docker build -t escoutances .
# Voir compose.prod.example.yaml (nginx + MariaDB + worker des e-mails) et docs/EXPLOITATION.md.

# --- 1. Dépendances Composer ---------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

# --- 2. Image d'exécution --------------------------------------------------------
FROM php:8.4-fpm-alpine AS runtime
COPY --from=ghcr.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl pdo_mysql zip gd opcache curl mbstring redis exif \
    && apk add --no-cache git mariadb-client
COPY --from=vendor /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/escoutances

COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && mkdir -p var/cache var/log var/backups public/uploads public/media \
    && chown -R www-data:www-data var public/uploads public/media

# Réglages PHP de production (OPcache, sessions, limites) : voir deploy/php-prod.ini.
COPY deploy/php-prod.ini /usr/local/etc/php/conf.d/90-escoutances.ini

# Les styles et assets sont compilés au démarrage du conteneur si besoin (voir deploy/docker/entrypoint.sh).
COPY deploy/docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
