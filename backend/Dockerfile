# Backend Symfony para Railway — nginx + php-fpm en un contenedor.
FROM php:8.4-fpm-alpine

# Extensiones que usan Symfony / Doctrine / PostgreSQL, y nginx + gettext (envsubst).
RUN apk add --no-cache nginx gettext icu-libs libpq \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev \
 && docker-php-ext-install pdo_pgsql intl opcache \
 && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# Permite que los plugins de Composer corran como root (symfony/runtime genera
# vendor/autoload_runtime.php, necesario para arrancar la app).
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app
COPY . .

# Instala dependencias (sin auto-scripts de Symfony) y regenera el autoload CON
# los plugins activos → así se crea vendor/autoload_runtime.php.
RUN composer install --no-dev --no-interaction --no-progress --no-scripts \
 && composer dump-autoload --no-dev --optimize \
 && mkdir -p var public/uploads && chmod -R 777 var public/uploads

COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV APP_ENV=prod
CMD ["entrypoint.sh"]
