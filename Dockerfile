FROM serversideup/php:8.2-fpm-nginx

LABEL authors="jegramos"

# Copy source code to the created directory
COPY . /var/www/html

# Setup working directory
WORKDIR /var/www/html

# Switch to root so we can perform actions that require root priviledges
USER root

# Install the intl extension with root permissions
RUN install-php-extensions intl

# Change the permission for all the files and dir inside /var/www/html
RUN chown -R www-data /var/www/html

# Drop back to our unprivileged user
USER www-data

# Install App Dependencies via Composer
RUN composer install --no-dev --optimize-autoloader
