# syntax=docker/dockerfile:1
#
# Imagem de produção do painel (Laravel + Nginx + PHP-FPM + Supervisor).
# Um único artefato serve todos os papéis via CONTAINER_ROLE:
#   web (padrão), worker, scheduler, reverb.
#
# Build (contexto = raiz do repositório):
#   docker build -f docker/prod/Dockerfile -t local/webraster-api:latest .

FROM composer:2 AS vendor
WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs
COPY api/ .
RUN composer install --no-dev --optimize-autoloader --classmap-authoritative --ignore-platform-reqs

FROM php:8.4-fpm-alpine
RUN apk add --no-cache \
    nginx \
    supervisor \
    su-exec \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    wget \
    $PHPIZE_DEPS \
  && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
  && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring exif pcntl bcmath intl zip opcache gd \
  && apk del $PHPIZE_DEPS \
  && rm -rf /tmp/pear

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html

COPY docker/prod/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/prod/supervisord.conf /etc/supervisord.conf
COPY docker/prod/php-fpm-www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/prod/php-custom.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/prod/docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh \
  && mkdir -p \
      storage/app/public \
      storage/app/private \
      storage/framework/cache/data \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
      bootstrap/cache \
  && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80 8080
ENTRYPOINT ["/docker-entrypoint.sh"]
