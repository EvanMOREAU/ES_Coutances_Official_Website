#!/bin/sh
# Prépare l'application au démarrage du conteneur : migrations, styles, assets, cache.
# Désactiver avec SKIP_BOOT=1 (par exemple pour le conteneur « worker » qui n'a pas à les rejouer).
set -e
cd /var/www/escoutances

if [ "${SKIP_BOOT:-0}" != "1" ] && [ "$1" = "php-fpm" ]; then
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    php bin/console tailwind:build --minify
    php bin/console asset-map:compile
    php bin/console cache:clear
    chown -R www-data:www-data var public/assets public/media public/uploads 2>/dev/null || true
fi

exec docker-php-entrypoint "$@"
