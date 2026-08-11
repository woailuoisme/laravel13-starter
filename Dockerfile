# Stage 1: Build dependencies
FROM jiaoio/php8.5:roadrunner-alpine AS builder

WORKDIR /var/www/html

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

RUN rm -f bootstrap/cache/packages.php \
    && composer dump-autoload --optimize --no-dev

# Stage 2: Production runtime
FROM jiaoio/php8.5:roadrunner-alpine

WORKDIR /var/www/html

COPY --chown=www-data:www-data --from=builder /var/www/html /var/www/html

RUN chmod -R 775 storage bootstrap/cache

ENV APP_PORT=8100

EXPOSE ${APP_PORT} 6001

CMD ["sh", "-c", "php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=${APP_PORT} --rpc-port=6001"]
