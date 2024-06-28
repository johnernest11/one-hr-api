FROM serversideup/php:8.2-fpm-nginx

LABEL authors="jegramos"

# Switch to root so we can perform actions that require root priviledges
USER root

# Install the intl extension with root permissions
RUN install-php-extensions intl gd

# Copy source code to the created directory
COPY . /var/www/html

# Setup working directory
WORKDIR /var/www/html

# Mount the .env as a secret. See https://docs.render.com/docker-secrets
# DOCKER_BUILDKIT=1 docker build -t jegramos/webkit-api -f app.Dockerfile --secret id=_env,source=.env .
RUN --mount=type=secret,id=_env,dst=/var/www/html/.env  \
    composer install --no-dev --optimize-autoloader

# Change the permission for all the files and dir inside /var/www/html
RUN chown -R www-data:www-data /var/www/html

# Drop back to our unprivileged user
USER www-data
