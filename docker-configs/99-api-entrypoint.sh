#!/bin/bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan events:clear
php artisan config:cache
php artisan down
