# Use an official PHP runtime with FPM
FROM php:8.2-fpm-alpine

# Install system dependencies and PHP extensions required for Laravel
RUN apk update && apk add \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    nodejs \
    npm

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Set the working directory inside the container
WORKDIR /var/www/html

# Copy the entire application to the container
COPY . .

# Copy the example environment file if .env doesn't exist
RUN if [ ! -f .env ]; then cp .env.example .env; fi

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Install Node dependencies and build assets (if applicable)
RUN npm install && npm run build

# Set permissions for Laravel directories
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose the port PHP-FPM is running on
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]