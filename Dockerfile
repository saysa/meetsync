FROM php:8.4-fpm

# Set working directory
WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    libicu-dev \
    zlib1g-dev \
    libyaml-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        intl \
        zip \
        gd \
        opcache \
        xml \
        mbstring \
    && pecl install apcu pcov \
    && docker-php-ext-enable apcu pcov \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Symfony CLI (optional)
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer files to leverage Docker cache
COPY composer.json composer.lock* ./

# Install all dependencies (including dev)
RUN composer install --no-scripts --no-autoloader

# Copy the rest of the Symfony project
COPY . .

# Complete the Composer installation
RUN composer dump-autoload --optimize && \
    composer run-script post-install-cmd --no-interaction || true

# Set environment variables for development
ENV APP_ENV=dev
ENV APP_DEBUG=1

# Ensure correct permissions
RUN chown -R www-data:www-data /var/www

# Expose port for php-fpm
EXPOSE 9000

# Start php-fpm
CMD ["php-fpm"]
