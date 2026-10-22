#!/usr/bin/env sh
set -eu

PORT="${PORT:-8080}"
case "$PORT" in
    ''|*[!0-9]*)
        echo "PORT debe ser un número; valor recibido: $PORT" >&2
        exit 1
        ;;
esac
export PORT

echo "Configurando Nginx en el puerto ${PORT}..."
envsubst '${PORT}' < /etc/nginx/templates/adara.conf.template > /etc/nginx/conf.d/default.conf
nginx -t

php artisan storage:link --no-interaction || true

if [ -n "${DB_HOST:-}" ] || [ -n "${DB_URL:-}" ] || [ "${DB_CONNECTION:-}" = "mysql" ]; then
    echo "Ejecutando migraciones de base de datos..."
    php artisan migrate --force --no-interaction || echo "Aviso: No se pudieron ejecutar migraciones en este inicio."
fi

if [ "${APP_ENV:-}" = "production" ] && [ -n "${APP_KEY:-}" ]; then
    echo "Optimizando caches de Laravel..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

echo "Iniciando PHP-FPM y Nginx..."
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
