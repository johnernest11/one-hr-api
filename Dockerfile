FROM serversideup/php:8.2-fpm-nginx as build-server

LABEL authors="jegramos"

# Switch to root so we can install extra PHP extensions
USER root

# Install the intl extension with root permissions
RUN install-php-extensions intl

# Drop back to our unprivileged user
USER www-data

FROM build-server as build-app

# Copy source code to the created directory
COPY . /var/www/html

# Setup working directory
WORKDIR /var/www/html

# Grant Permissions
USER root
RUN chown www-data /var/www/html/ # Parent directory permissions
RUN chown -R www-data /var/www/html # Permissions of all the files inside
USER www-data

# Install App Dependencies via Composer
RUN composer install --no-dev --optimize-autoloader
