FROM php:8.2-fpm-alpine

RUN apk add --no-cache icu-dev libzip-dev zip unzip git

RUN docker-php-ext-install pdo pdo_mysql intl zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/dispromalt

COPY . .

EXPOSE 9000

CMD ["php-fpm"]
