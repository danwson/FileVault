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
# para servir a aplicação.
# ------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS app

# Bibliotecas de runtime: ficam na imagem final (os pacotes "-dev"
# correspondentes, usados só para compilar as extensões abaixo, são
# instalados como grupo virtual e removidos ao fim desta mesma camada —
# não sobram headers/compiladores na imagem publicada).
RUN apk add --no-cache \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        icu-libs \
        oniguruma \
        sqlite-libs \
        fcgi \
    && apk add --no-cache --virtual .build-deps \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        sqlite-dev \
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
    && apk del .build-deps

# Habilita o endpoint de "ping" do PHP-FPM, usado pelo HEALTHCHECK abaixo
# (fala o protocolo FastCGI via cgi-fcgi, já que o FPM não expõe HTTP).
RUN { \
        echo 'pm.status_path = /status'; \
        echo 'ping.path = /ping'; \
    } >> /usr/local/etc/php-fpm.d/www.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

CMD ["php-fpm"]
