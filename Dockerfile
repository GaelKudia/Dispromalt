# ============================================================
# ÉTAPE 1 : BUILDER
# ============================================================
FROM dunglas/frankenphp:1.13.5-php8.4-alpine AS builder

# Mise à jour des paquets système vulnérables
RUN apk update \
    && apk upgrade --no-cache curl libcurl \
    && apk info -v curl libcurl

# Extensions PHP nécessaires au projet
RUN install-php-extensions \
    pdo_mysql intl zip opcache mbstring gd imagick apcu

# Outils nécessaires au build
RUN apk add --no-cache git unzip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dépendances Composer
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

# Compilation des assets et cache Symfony
RUN php bin/console asset-map:compile --env=prod --no-debug \
    && php bin/console pwa:compile --env=prod --no-debug \
    && php bin/console cache:warmup --env=prod --no-debug


# ============================================================
# ÉTAPE 2 : PRODUCTION
# ============================================================
FROM dunglas/frankenphp:1.13.5-php8.4-alpine AS production

# Mise à jour de curl et libcurl
RUN apk update \
    && apk upgrade --no-cache curl libcurl \
    && apk info -v curl libcurl

# Extensions PHP nécessaires à l'exécution
RUN install-php-extensions \
    pdo_mysql intl zip opcache mbstring gd imagick apcu

WORKDIR /app

# Copie du projet depuis le builder
COPY --from=builder --chown=www-data:www-data /app /app

# Préparation des répertoires
RUN mkdir -p var/cache var/log var/share /data /config \
    && chown -R www-data:www-data var /data /config \
    && chmod -R 775 var

# Configuration FrankenPHP
ENV FRANKENPHP_CONFIG="worker /app/public/index.php" \
    SERVER_NAME=":80"

# Utilisateur non privilégié
USER www-data

EXPOSE 80

# Vérification de santé
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget -qO- http://127.0.0.1/ || exit 1