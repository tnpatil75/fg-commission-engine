# syntax=docker/dockerfile:1
#
# Self-contained application image: PHP 8.4 + Apache, Composer dependencies installed at build time.
# Development dependencies (PHPUnit) are included so the test suite can run inside this container.

FROM php:8.4-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Serve Laravel's public/ directory on port 8080 so Apache can run as the unprivileged www-data user.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && a2enmod rewrite \
    && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2

COPY --from=composer:2.10 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first, so code changes do not reinstall them. Retries cover transient download failures.
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    export COMPOSER_CACHE_DIR=/tmp/composer-cache; \
    for attempt in 1 2 3 4 5; do \
      composer install --no-interaction --no-progress --no-scripts --no-autoloader && break; \
      echo "composer install failed (attempt $attempt), retrying"; sleep 5; \
    done; \
    test -d vendor/laravel/framework

COPY . .

RUN sed -i 's/\r$//' docker/entrypoint.sh \
    && install -m 0755 docker/entrypoint.sh /usr/local/bin/commission-entrypoint \
    && composer dump-autoload --optimize --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
       storage/framework/phpunit storage/logs bootstrap/cache /var/app-data \
    && chown www-data:www-data /var/www/html \
    && chown -R www-data:www-data storage bootstrap/cache /var/app-data

USER www-data

EXPOSE 8080

ENTRYPOINT ["commission-entrypoint"]
CMD ["apache2-foreground"]
