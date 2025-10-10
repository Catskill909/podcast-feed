FROM php:8.1-apache

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application files
COPY . /var/www/html/

# Set correct ownership and permissions for writable directories
RUN chown -R www-data:www-data /var/www/html/data \
    /var/www/html/uploads \
    /var/www/html/logs && \
    chmod -R 755 /var/www/html/data \
    /var/www/html/uploads \
    /var/www/html/logs

# Set working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80
