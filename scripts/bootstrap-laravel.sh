#!/usr/bin/env bash
set -euo pipefail

if [ -d "app" ]; then
  echo "app/ bestaat al; bootstrap wordt overgeslagen."
  exit 0
fi

docker compose run --rm composer create-project laravel/laravel app
docker compose run --rm node npm --prefix app install
docker compose run --rm node npm --prefix app install vue @vitejs/plugin-vue

cp app/.env.example app/.env

docker compose run --rm app php artisan key:generate

echo "Laravel/Vue basis staat in app/."
echo "Controleer app/.env en start daarna: docker compose up --build"

