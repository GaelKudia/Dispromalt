# ============================================================
# ÉTAPE 1 : BUILDER
# ============================================================
FROM dunglas/frankenphp:1.13.4-php8.4-alpine AS builder

# Extensions PHP requises par composer.lock (dont ext-gd)
RUN install-php-extensions \
    pdo_mysql intl zip opcache mbstring gd imagick apcu

# Outils uniquement nécessaires au build
RUN apk add --no-cache git unzip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dépendances
COPY composer.json composer.lock symfony.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist \
    --no-scripts

# Code source
COPY . /app

# Scripts Symfony
RUN composer run-script post-install-cmd --no-interaction

# Compilation des assets & warmup
RUN php bin/console asset-map:compile --env=prod --no-debug \
    && php bin/console pwa:compile       --env=prod --no-debug \
    && php bin/console cache:warmup      --env=prod --no-debug

# ============================================================
# ÉTAPE 2 : PRODUCTION
# ============================================================
FROM dunglas/frankenphp:1.13.4-php8.4-alpine AS production

RUN install-php-extensions \
    pdo_mysql intl zip opcache mbstring gd imagick apcu

WORKDIR /app

COPY --from=builder --chown=www-data:www-data /app /app

RUN mkdir -p var/cache var/log var/share /data /config \
    && chown -R www-data:www-data var /data /config \
    && chmod -R 775 var

# Dans l'étape "production", juste après le FROM
RUN apk upgrade --no-cache curl libcurl

ENV FRANKENPHP_CONFIG="worker /app/public/index.php" \
    SERVER_NAME=":80"

USER www-data

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget -qO- http://127.0.0.1/ || exit 1