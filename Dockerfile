FROM php:8.2-apache

# Enable Apache rewrite (safe default)
RUN a2enmod rewrite

# Copy all files to web root
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
