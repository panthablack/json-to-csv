# Base PHP image with extensions
FROM php:8.4-fpm as base

# Install system dependencies
RUN apt-get update && apt-get install -y \
  git \
  curl \
  libpng-dev \
  libonig-dev \
  libxml2-dev \
  zip \
  unzip \
  libzip-dev \
  && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Development stage
FROM base as development

# Install Node.js 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
  && apt-get install -y nodejs

# Copy application files
COPY --chown=www-data:www-data . .

# Install PHP dependencies
RUN composer install --no-scripts --no-autoloader

# Install Node dependencies
RUN npm install

# Generate autoloader
RUN composer dump-autoload --optimize

# Set permissions
RUN chmod -R 755 /var/www/html/storage

# Expose port
EXPOSE 8000

# Start development server
CMD ["composer", "run", "dev"]

# Production stage
FROM base as production

# Install Node.js 20 for building assets
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
  && apt-get install -y nodejs

# Copy application files
COPY --chown=www-data:www-data . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts
RUN npm install

# Build assets
RUN npm run build

# Remove Node.js and npm (not needed in production)
RUN apt-get remove -y nodejs npm && apt-get autoremove -y

# Set permissions
RUN chmod -R 755 /var/www/html/storage

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]