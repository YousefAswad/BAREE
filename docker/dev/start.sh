#!/usr/bin/env bash
# Runs inside the app container: web server, queue worker, Reverb and scheduler. If any exits, the container exits (visible failure).
set -u
cd /app
WEB_PORT="${BAREE_WEB_PORT:-8000}"
RV_PORT="${BAREE_REVERB_PORT:-8081}"
pids=()
trap 'kill "${pids[@]}" 2>/dev/null; wait; exit 0' TERM INT
PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=0.0.0.0 --port="$WEB_PORT" & pids+=($!)
php artisan queue:work redis --queue=ai,media,files,exports,default --tries=1 --sleep=1 & pids+=($!)
php artisan reverb:start --host=0.0.0.0 --port="$RV_PORT" & pids+=($!)
php artisan schedule:work & pids+=($!)
wait -n
echo "A BAREE process exited; stopping so the failure is visible (docker compose logs app)." >&2
kill "${pids[@]}" 2>/dev/null
exit 1
