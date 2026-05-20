FROM php:8.4-cli-alpine AS builder

# Install build dependencies
RUN apk add --no-cache \
    linux-headers \
    curl \
    git \
    nodejs \
    npm \
    libzip-dev \
    sqlite-dev \
    icu-dev \
    oniguruma-dev

# Install PHP extensions required by Laravel and Octane
RUN docker-php-ext-install pcntl sockets pdo_mysql pdo_sqlite zip intl mbstring bcmath

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Install Node dependencies and build assets
RUN npm install && npm run build

# Download RoadRunner binary
RUN ./vendor/bin/rr get-binary

# ---------------------------------------------------------
# Final image
FROM php:8.4-cli-alpine

# Install runtime dependencies
RUN apk add --no-cache \
    libzip \
    sqlite-libs \
    icu-libs \
    oniguruma

# Install PHP extensions
RUN docker-php-ext-install pcntl sockets pdo_mysql pdo_sqlite zip intl mbstring bcmath

# Set working directory
WORKDIR /app

# Copy built application from the builder stage
COPY --from=builder /app /app

# Copy RoadRunner binary to a global location
RUN cp /app/rr /usr/local/bin/rr && chmod +x /usr/local/bin/rr

# Ensure proper permissions for Laravel
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Expose Octane port
EXPOSE 8000

# Switch to a non-root user
USER www-data

# Start Laravel Octane with RoadRunner
CMD ["php", "artisan", "octane:start", "--server=roadrunner", "--host=0.0.0.0", "--port=8000"]
