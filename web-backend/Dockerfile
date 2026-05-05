FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    gettext-base \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    nginx-light \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy dependency manifests first
COPY composer.json composer.lock ./

# Copy only the application files required at runtime.
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY docker ./docker
COPY public ./public
COPY resources ./resources
COPY routes ./routes
COPY scripts ./scripts
COPY storage ./storage
COPY artisan ./artisan

RUN chmod +x /app/scripts/start-production.sh

# Install dependencies
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Expose port
EXPOSE 10000
EXPOSE 8080

# Start nginx, PHP-FPM, Reverb, and scheduler worker
CMD ["/app/scripts/start-production.sh"]
