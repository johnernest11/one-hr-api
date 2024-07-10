#!/bin/bash
commands=(
  "config:clear"
  "route:clear"
  "view:clear"
  "events:clear"
  "config:cache"
  "route:cache",
  "view:cache",
  "events:cache"
)

for command in "${commands[@]}"; do
  php /var/www/html/artisan "$command"
done
