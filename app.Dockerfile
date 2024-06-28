FROM serversideup/php:8.2-fpm-nginx

LABEL authors="jegramos"

# Switch to root so we can perform actions that require root priviledges
USER root

# Install the intl extension with root permissions
RUN install-php-extensions intl gd

# We'll run our own custom entry point
COPY --chmod=755 docker-configs/99-api-entrypoint.sh/ /etc/entrypoint.d/

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

# As root, run the docker-php-serversideup-s6-init script
RUN docker-php-serversideup-s6-init

# Drop back to our unprivileged user
USER www-data
