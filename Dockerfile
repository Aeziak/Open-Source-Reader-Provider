FROM php:8.1-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev \
    && docker-php-ext-install intl pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# 1) Copier les fichiers composer en premier (cache)
COPY composer.json composer.lock ./

# 2) Copier le minimum pour que bin/console existe pendant les auto-scripts
COPY bin/ bin/
COPY config/ config/
COPY public/ public/
COPY src/ src/
COPY migrations/ migrations/
COPY symfony.lock ./
COPY .env .env

# 3) Installer dépendances (les auto-scripts peuvent maintenant s'exécuter)
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

# 4) Copier le reste (README, etc.)
COPY . .

# 5) Warmup (safe)
RUN php bin/console cache:clear --env=prod || true \
 && php bin/console cache:warmup --env=prod || true

RUN mkdir -p /data/storage && chmod -R 775 /data/storage


EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
