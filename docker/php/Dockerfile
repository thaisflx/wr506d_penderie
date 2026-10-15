FROM php:8.3-fpm-alpine

RUN apk add --no-cache git unzip icu-dev libpq-dev libzip-dev \
    && docker-php-ext-install intl pdo pdo_pgsql zip

# PCOV : couverture de code pour PHPUnit (dépendances de build supprimées ensuite)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend
