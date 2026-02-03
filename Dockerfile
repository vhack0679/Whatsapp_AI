# Use official PHP + Apache
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (for caching)
COPY composer.json composer.lock* ./

# Install PHP dependencies (Predis)
RUN composer install --no-dev --optimize-autoloader

# Copy rest of the app
COPY . .

# Enable Apache rewrite (optional)
RUN a2enmod rewrite

# Expose port
EXPOSE 80
