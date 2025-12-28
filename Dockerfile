# ---- Base PHP ----
FROM php:8.1-cli

# ---- System deps ----
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev \
    && docker-php-ext-install \
        intl pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# ---- Composer ----
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---- App dir ----
WORKDIR /app

# ---- Copy composer files first (cache-friendly) ----
COPY composer.json composer.lock ./

# ---- Install deps (prod only) ----
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

# ---- Copy app ----
COPY . .

# ---- Symfony prod optimisations ----
RUN php bin/console cache:clear \
 && php bin/console cache:warmup

# ---- Storage dir (for EPUBs) ----
RUN mkdir -p storage var \
 && chmod -R 775 storage var

# ---- Expose port (Dokploy will route) ----
EXPOSE 8000

# ---- Start Symfony server ----
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
