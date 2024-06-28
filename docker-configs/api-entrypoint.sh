#!/bin/bash

# Wait for the container to be fully up
if read _ 2>/dev/null <&3; then
  exec 3<&-
fi

# Ensure PATH includes necessary directories
export PATH=/bin:/usr/bin:/command:$PATH  # Add your project's binary directory if needed

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan events:clear
php artisan config:cache
php artisan down
