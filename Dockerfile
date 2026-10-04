FROM php:8.5-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    curl \
    zip \
    unzip \
    git \
    nginx \
    nodejs \
    npm \
    supervisor

# Install only PHP extensions not included in the PHP 8.5 image
COPY --from=mlocati/php-extension-installer:2.12.0 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl pdo_pgsql gd zip bcmath redis

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy composer files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application source
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Copy package files and build frontend assets
COPY package.json package-lock.json ./
RUN npm ci --prefer-offline && npm run build && rm -rf node_modules

# Set permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 10000

CMD ["supervisord", "-c", "/etc/supervisord.conf", "-n"]
