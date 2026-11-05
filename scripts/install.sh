#!/usr/bin/env bash
# Creates a fresh Laravel 13 + Vue (Inertia) app from the official starter kit and applies the BAREE overlay.
set -euo pipefail
APP=${1:-baree-app}
OVERLAY="$(cd "$(dirname "$0")/.." && pwd)"

php -r 'exit(version_compare(PHP_VERSION, "8.5.12", ">=") ? 0 : 1);' \
  || { echo "PHP 8.5.12+ required (8.5.12 fixes critical RCE CVEs released 21 Oct 2026)"; exit 1; }

laravel new "$APP" --vue --pest --database=pgsql --no-interaction
cd "$APP"

composer require laravel/horizon laravel/reverb laravel/pennant phpoffice/phpword phpoffice/phppresentation smalot/pdfparser
php artisan install:broadcasting --reverb --no-interaction
php artisan install:api --no-interaction            # Sanctum personal tokens (MCP)
composer require laravel/mcp
php artisan vendor:publish --tag=ai-routes --force  # replaced by our routes/ai.php below
npm install @laravel/echo-vue laravel-echo pusher-js vue-i18n
composer require --dev larastan/larastan
# Optional first-party multi-provider SDK; our gateway works without it:
# composer require laravel/ai

rsync -a --exclude .git --exclude scripts/install.sh "$OVERLAY"/ ./
php scripts/wire.php                 # providers, routes, schedule, Inertia flash, i18n (idempotent)
php artisan key:generate
php artisan migrate --seed           # as the table owner (DB_USERNAME=baree_owner)
psql "${DATABASE_OWNER_URL:-postgresql://baree_owner:owner@127.0.0.1:5432/baree}" -v ON_ERROR_STOP=1 -v app_password="${APP_DB_PASSWORD:-app}" -f scripts/pg-init.sql
echo "Now switch .env to DB_USERNAME=baree_app so row-level security applies to the running app."
npm run build
node scripts/i18n-check.mjs
php artisan test
