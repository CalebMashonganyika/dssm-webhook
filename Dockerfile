# Use official PHP + Apache
FROM php:8.1-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Allow .htaccess overrides globally
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Install MySQL extension
RUN docker-php-ext-install mysqli

# Copy whole project into Apache webroot
COPY . /var/www/html/

# Fix permissions for logs folder
RUN mkdir -p /var/www/html/src/logs && \
    chown -R www-data:www-data /var/www/html/src/logs && \
    chmod -R 755 /var/www/html/src/logs

# Expose port
EXPOSE 80
