# Use official PHP + Apache image
FROM php:8.1-apache

# Enable rewrite (not strictly required, but useful)
RUN a2enmod rewrite

# Install common extensions (mysqli, etc.)
RUN docker-php-ext-install mysqli

# Copy source into webroot
COPY src/ /var/www/html/src/

# Ensure logs directory exists and is writable
RUN mkdir -p /var/www/html/src/logs \
 && chown -R www-data:www-data /var/www/html/src/logs \
 && chmod -R 755 /var/www/html/src/logs

# Expose port 80
EXPOSE 80

# Default command provided by base image (Apache)