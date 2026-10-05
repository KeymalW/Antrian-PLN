#!/bin/sh
# Runtime startup: migrate, link public storage, then serve on Railway's $PORT.
set -e

php artisan migrate --force
php artisan storage:link || true

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
