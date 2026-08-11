# syntax=docker/dockerfile:1

#
# ---- Etapa 1: vendor -------------------------------------------------------
# Instala as dependências PHP em uma imagem isolada, usando apenas o
# composer.json/composer.lock. Isso mantém essa camada cacheada pelo Docker
# enquanto o código da aplicação muda, mas as dependências não.
# ------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /var/www/html

COPY database/ database/
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

#
# ---- Etapa 2: app -----------------------------------------------------------
# Imagem final de runtime: PHP-FPM enxuto com apenas o que é necessário
# para servir a aplicação. Otimizações adicionais (usuário não-root,
# healthcheck, tuning de opcache/php.ini) entram na próxima etapa do projeto.
# ------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        $PHPIZE_DEPS \
        linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_sqlite \
        zip \
        gd \
        bcmath \
        intl \
        opcache \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
