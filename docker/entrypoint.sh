#!/usr/bin/env bash
set -e

# Configurar el puerto dinámico asignado por Railway
PORT="${PORT:-80}"
echo "Configurando Apache en el puerto ${PORT}..."
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Crear enlace simbólico para storage si no existe
php artisan storage:link --no-interaction || true

# Ejecutar migraciones si la base de datos está configurada
if [ -n "$DB_HOST" ] || [ -n "$DB_URL" ] || [ "$DB_CONNECTION" = "mysql" ]; then
    echo "Ejecutando migraciones de base de datos..."
    php artisan migrate --force --no-interaction || echo "Aviso: No se pudieron ejecutar migraciones en este inicio."
fi

# Optimizar Laravel si estamos en entorno productivo y hay APP_KEY
if [ "$APP_ENV" = "production" ] && [ -n "$APP_KEY" ]; then
    echo "Optimizando caches de Laravel..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

echo "Iniciando Apache..."
exec apache2-foreground
