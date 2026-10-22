# ==========================================
# Fase 1: Compilar Assets de Frontend (Vite)
# ==========================================
FROM node:22-alpine AS frontend
WORKDIR /app

COPY package*.json ./
RUN npm ci || npm install

COPY . .
RUN npm run build

# ==========================================
# Fase 2: Aplicación PHP 8.4 + Apache
# ==========================================
FROM php:8.4-apache

# Dependencias del sistema necesarias
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Instalador de extensiones PHP para Docker
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo_mysql pdo_sqlite bcmath zip intl gd opcache

# Composer oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuración del virtual host de Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN set -eu; \
    for module in mpm_event mpm_worker mpm_prefork; do \
        a2dismod "$module" || true; \
    done; \
    a2enmod mpm_prefork rewrite headers; \
    apache2ctl -M 2>&1 | grep -q 'mpm_prefork_module'

WORKDIR /var/www/html

# Instalar dependencias de Composer con cache de capas
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copiar el código del proyecto
COPY . .

# Copiar los assets compilados por Vite desde la fase de Node
COPY --from=frontend /app/public/build ./public/build

# Generar el autoloader optimizado para producción
RUN composer dump-autoload --optimize --no-dev

# Permisos para storage y bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Configurar el script de inicio (removiendo posibles retornos CRLF de Windows)
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
