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

# 👇 Créer un .env temporaire COMPLET pour le build Symfony
# (Coolify ne fournit pas de .env dans le contexte de build)
# Ce fichier écrase le .env du repo — les vraies valeurs viendront
# des variables d'environnement Coolify au runtime.
RUN touch .env \
    && echo "APP_ENV=prod" > .env \
    && echo "APP_DEBUG=0" >> .env \
    && echo "APP_SECRET=build_placeholder_change_at_runtime" >> .env \
    && echo "DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db" >> .env \
    && echo "MAILER_DSN=null://null" >> .env \
    && echo "MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0" >> .env \
    && echo "DEFAULT_URI=http://localhost" >> .env \
    && echo "TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR" >> .env \
    && echo "TRUSTED_HEADERS=x-forwarded-for,x-forwarded-proto,x-forwarded-host,x-forwarded-port" >> .env \
    && echo "WEBPUSH_PUBLIC_KEY=" >> .env \
    && echo "WEBPUSH_PRIVATE_KEY=" >> .env \
    && echo "WEBPUSH_SUBJECT=mailto:admin@example.com" >> .env

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

RUN apk update \
    && apk upgrade --no-cache curl libcurl \
    && apk info -v curl libcurl

RUN install-php-extensions \
    pdo_mysql intl zip opcache mbstring gd imagick apcu

WORKDIR /app

COPY --from=builder --chown=www-data:www-data /app /app

RUN mkdir -p var/cache var/log var/share /data /config \
    && chown -R www-data:www-data var /data /config \
    && chmod -R 775 var

ENV FRANKENPHP_CONFIG="worker /app/public/index.php" \
    SERVER_NAME=":80"

USER www-data

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget -qO- http://127.0.0.1/ || exit 1