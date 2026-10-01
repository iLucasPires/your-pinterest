FROM php:8.5-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    zip \
    unzip \
    git \
    nodejs \
    npm

# Install PHP extensions
RUN apk add --no-cache --virtual .php-build-deps $PHPIZE_DEPS \
    && docker-php-ext-install \
    pdo_pgsql \
    mbstring \
    gd \
    zip \
    bcmath \
    opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .php-build-deps

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

EXPOSE 9000

CMD ["php-fpm"]
