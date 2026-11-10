#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
 
# 1. файл настроек
[ -f .env ] || cp .env.example .env
 
# 2. каталоги Laravel и права (от вашего пользователя, sudo не нужен)
mkdir -p storage/app/public storage/framework/{cache/data,sessions,views,testing} storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
 
# 3. зависимости (если ещё не установлены)
if [ ! -f vendor/autoload.php ]; then
  docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer \
    -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs --prefer-dist --no-interaction
fi
 
# 4. контейнеры (ждём, пока MySQL и Redis станут healthy)
./vendor/bin/sail up -d --wait
 
# 5. ключ приложения и миграции
grep -q '^APP_KEY=.\+' .env || ./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --force
 
echo "Готово: http://localhost"
