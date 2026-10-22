FROM dunglas/frankenphp:latest-php8.2.35-alpine


# ============================================================
# 1. Extensions PHP nécessaires
# ============================================================
RUN install-php-extensions \
    pdo_mysql \
    intl \
    zip \
    opcache \
    mbstring \
    gd \
    imagick \
    apcu

# ============================================================
# 2. Composer (depuis l'image officielle)
# ============================================================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ============================================================
# 3. Répertoire de travail
# ============================================================
WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

# ============================================================
# 4. Copier UNIQUEMENT les fichiers Composer (cache Docker)
# ============================================================
COPY composer.json composer.lock symfony.lock ./

# ============================================================
# 5. Installer les vendors SANS scripts
#    (--no-scripts car bin/console n'existe pas encore)
# ============================================================
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist \
    --no-scripts

# ============================================================
# 6. Copier TOUT le code maintenant (.env inclus)
# ============================================================
COPY . /app

# ============================================================
# 7. Variables temporaires requises au build
#    (Coolify les écrase au runtime avec les vraies valeurs)
# ============================================================
ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV APP_SECRET=build_time_dummy_secret_change_me
ENV APP_DEFAULT_URI=http://localhost

# ============================================================
# 8. Exécuter les scripts Symfony MAINTENANT que bin/console existe
# ============================================================
RUN composer run-script post-install-cmd --no-interaction || true

# ============================================================
# 9. Compiler les assets AssetMapper (CSS, JS)
# ============================================================
RUN php bin/console asset-map:compile --env=prod --no-debug

# ============================================================
# 10. Compiler les assets PWA (manifest, SW, favicons, icônes)
# ============================================================
RUN php bin/console pwa:compile --env=prod --no-debug

# ============================================================
# 11. Warmup du cache pour la production
# ============================================================
RUN php bin/console cache:warmup --env=prod --no-debug

# ============================================================
# 12. Permissions
# ============================================================
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var public \
    && chmod -R 775 var

# ============================================================
# 13. Configuration FrankenPHP
# ============================================================
ENV FRANKENPHP_CONFIG="worker /app/public/index.php"
ENV SERVER_NAME=":80"

# ============================================================
# 14. Port HTTP
# ============================================================
EXPOSE 80
