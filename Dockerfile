# Production image for the Laravel 8 API (PHP 8.2, prebuilt — no mise/source compile).
# Railway uses this Dockerfile automatically when present (takes precedence over Railpack).
FROM php:8.2-cli-bookworm

# System libraries needed to compile the required PHP extensions.
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev libzip-dev libsodium-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mbstring exif pcntl bcmath gd zip sodium opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer binary from the official image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy the application source first: composer scripts (package:discover)
# need artisan + bootstrap/config present during install.
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Writable paths for Laravel at runtime.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY start.sh /app/start.sh
RUN chmod +x /app/start.sh

EXPOSE 8000

CMD ["/app/start.sh"]
