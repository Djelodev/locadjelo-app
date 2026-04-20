# =============================================================
# Dockerfile multi-stage pour l'application Laravel LocaDjelo
# Compétence BC02-CP7 : Gérer des containers
# Multi-stage build = image finale plus légère et sécurisée
# =============================================================

# ==================== STAGE 1 : BUILDER ====================
# Ce stage installe tout, compile les extensions, télécharge
# les dépendances. Il ne sera PAS dans l'image finale.

FROM php:7.4-fpm-alpine AS builder

# Dépendances de compilation (nécessaires uniquement ici)
RUN apk add --no-cache \
    bash curl git unzip \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    libzip-dev oniguruma-dev \
    $PHPIZE_DEPS

# Compilation des extensions PHP pour Laravel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo pdo_mysql mbstring zip exif pcntl gd bcmath

# Installation de Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copie du code source
WORKDIR /var/www/html
COPY ./src /var/www/html

# Installation des dépendances PHP (sans les dépendances de dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

# ==================== STAGE 2 : FINAL ====================
# Ce stage ne contient que le strict nécessaire pour exécuter
# l'application. Pas d'outils de compilation, pas de git, 
# pas de composer = image légère et sécurisée.

FROM php:7.4-fpm-alpine AS final

LABEL maintainer="Ange IRIE BI"
LABEL description="Image production LocaDjelo (Laravel) - multi-stage"
LABEL version="2.0"

# Uniquement les librairies d'exécution (pas les headers de dev)
RUN apk add --no-cache \
    libpng libjpeg-turbo freetype \
    libzip oniguruma \
    mysql-client

# Copie des extensions PHP compilées depuis le stage builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Création d'un utilisateur non-root (sécurité)
RUN addgroup -g 1000 laravel && \
    adduser -u 1000 -G laravel -s /bin/sh -D laravel

# Répertoire de travail
WORKDIR /var/www/html

# Copie du code source avec les dépendances installées
COPY --from=builder --chown=laravel:laravel /var/www/html /var/www/html

# Permissions sur les dossiers Laravel
RUN chown -R laravel:laravel /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Basculer sur l'utilisateur non-root
USER laravel

# Port PHP-FPM
EXPOSE 9000

# Commande de démarrage
CMD ["php-fpm"]