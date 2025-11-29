# Use official PHP + Apache
FROM php:8.1-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Allow .htaccess overrides globally
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Install required extensions (mysqli + PDO MySQL)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy entire project into document root
COPY . /var/www/html/

# Fix permissions for logs folder
RUN mkdir -p /var/www/html/src/logs && \
    chown -R www-data:www-data /var/www/html/src/logs && \
    chmod -R 755 /var/www/html/src/logs

EXPOSE 80
