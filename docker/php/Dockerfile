FROM php:8.4-fpm

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/root/.composer

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        zip \
        procps \
        libfcgi-bin \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        exif \
        pcntl \
        gd \
        zip \
        intl \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY fpm.conf /usr/local/etc/php-fpm.d/zzz-app.conf
COPY start.sh /usr/local/bin/start.sh
COPY worker-start.sh /usr/local/bin/worker-start.sh

RUN chmod +x /usr/local/bin/start.sh /usr/local/bin/worker-start.sh \
    && git config --global --add safe.directory /var/www/html

WORKDIR /var/www/html

EXPOSE 9000

CMD ["start.sh"]
