# ==========================================
# Fase 1: Compilar assets de Vite
# ==========================================
FROM node:22-alpine AS frontend
WORKDIR /app

COPY package*.json ./
RUN npm ci || npm install

COPY . .
RUN npm run build

# ==========================================
# Fase 2: Laravel con PHP-FPM y Nginx
# ==========================================
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    gettext-base \
    git \
    libzip-dev \
    nginx \
    supervisor \
    unzip \
    && rm -rf /var/lib/apt/lists/*

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo_mysql pdo_sqlite bcmath zip intl gd opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache && \
    rm -f /etc/nginx/sites-enabled/default && \
    printf '\nclear_env = no\n' >> /usr/local/etc/php-fpm.d/www.conf

COPY docker/nginx.conf.template /etc/nginx/templates/adara.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/adara.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["entrypoint.sh"]
