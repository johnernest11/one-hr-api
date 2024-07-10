#!/bin/bash
commands=(
  "config:clear"
  "route:clear"
  "view:clear"
  "event:clear"
  "config:cache"
  "route:cache",
  "view:cache",
  "event:cache"
)

for command in "${commands[@]}"; do
  php /var/www/html/artisan "$command"
done
