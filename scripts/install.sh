#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

command -v php >/dev/null 2>&1 || { echo "PHP 8.4 is required." >&2; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "Composer is required." >&2; exit 1; }
php -m | grep -qi '^swoole$' || { echo "PHP Swoole extension is required." >&2; exit 1; }

cd "$root_dir"
[[ -f .env ]] || cp .env.example .env
mkdir -p bootstrap/cache storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear

chmod -R ug+rwX bootstrap/cache storage
echo "YeYing installation complete. Configure .env, then run scripts/starter.sh start."
