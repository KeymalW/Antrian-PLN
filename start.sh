#!/bin/sh
# Runtime startup for the Antrian-PLN API on Railway:
# wait for MySQL, migrate, link public storage, then serve on $PORT.
set -e

echo "=== Antrian-PLN API booting ==="
php -v | head -1
echo "--- PHP extensions ---"
php -m | grep -Ei '^(pdo_mysql|mbstring|zip|sodium|gd|opcache|curl|xml|dom|openssl|tokenizer|ctype|fileinfo|json|bcmath|exif|pcntl)$' || true
echo "--- DB config (host only, no secrets) ---"
echo "DB_CONNECTION=${DB_CONNECTION:-<unset>} DB_HOST=${DB_HOST:-<unset>} DB_PORT=${DB_PORT:-<unset>} DB_DATABASE=${DB_DATABASE:-<unset>}"

ATTEMPTS=0
until php artisan migrate --force; do
  ATTEMPTS=$((ATTEMPTS + 1))
  if [ "$ATTEMPTS" -ge 12 ]; then
    echo "Migrate failed after $ATTEMPTS attempts — giving up. Check DB_* variables and MySQL plugin."
    exit 1
  fi
  echo "Migrate attempt $ATTEMPTS failed (DB not ready?) — retrying in 5s..."
  sleep 5
done
echo "Migrations OK."

php artisan storage:link || true

echo "Serving on port ${PORT:-8000}."
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
