#!/bin/bash
php /var/www/html/artisan config:clear
php /var/www/html/artisan route:clear
php /var/www/html/artisan view:clear
php /var/www/html/artisan events:clear
php /var/www/html/artisan config:cache
php /var/www/html/artisan down
