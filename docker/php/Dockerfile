# =============================================================
# Dockerfile pour l'application Laravel LocaDjelo
# Compétence BC02-CP7 : Gérer des containers
# =============================================================

# Image de base : PHP 8.2 avec FPM sur Alpine (légère)
FROM php:7.4-fpm-alpine

# Métadonnées
LABEL maintainer="Ange IRIE BI"
LABEL description="Image PHP-FPM pour LocaDjelo (Laravel)"

# --- Installation des dépendances système ---
# Ces paquets sont nécessaires pour compiler les extensions PHP
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    mysql-client

# --- Installation des extensions PHP nécessaires à Laravel ---
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        zip \
        exif \
        pcntl \
        gd \
        bcmath

# --- Installation de Composer (gestionnaire de dépendances PHP) ---
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# --- Création d'un utilisateur non-root pour la sécurité ---
# Ne JAMAIS faire tourner une application en root dans un container (bonne pratique sécurité)
RUN addgroup -g 1000 laravel && \
    adduser -u 1000 -G laravel -s /bin/sh -D laravel

# --- Définition du répertoire de travail ---
WORKDIR /var/www/html

# --- Copie du code source de l'application ---
COPY --chown=laravel:laravel ./src /var/www/html

# --- Installation des dépendances Laravel via Composer ---
RUN composer install --no-dev --optimize-autoloader --no-interaction || true

# --- Permissions sur les dossiers Laravel ---
RUN chown -R laravel:laravel /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# --- Basculer sur l'utilisateur non-root ---
USER laravel

# --- Exposer le port PHP-FPM ---
EXPOSE 9000

# --- Commande au démarrage du container ---
CMD ["php-fpm"]