#!/bin/sh
set -e

# 1) Claves JWT: si no existen, se generan usando JWT_PASSPHRASE (variable de Railway).
if [ ! -f config/jwt/private.pem ]; then
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction || true
fi

# 2) Migraciones + caché en runtime (ya con las variables de entorno de Railway).
php bin/console doctrine:migrations:migrate --no-interaction || true
php bin/console cache:clear || true
# Precalienta la caché de Symfony para que las primeras peticiones no salgan en frío.
php bin/console cache:warmup || true

# 2b) Asegura que la carpeta de subidas sea escribible. Necesario cuando Railway
# monta un Volume en /app/public/uploads (el montaje reinicia los permisos y
# php-fpm corre como www-data, que si no, no puede guardar imágenes → 500).
mkdir -p public/uploads var
chmod -R 777 public/uploads var || true

# 3) Puerto que asigna Railway en la config de nginx.
export PORT="${PORT:-8080}"
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# 4) Arranca php-fpm (en segundo plano) y nginx (en primer plano).
php-fpm -D
exec nginx -g 'daemon off;'
