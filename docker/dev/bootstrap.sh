#!/usr/bin/env bash
# One-time (resumable) app build, run INSIDE the dev container by scripts/windows-setup.ps1.
# Mirrors scripts/install.sh, with these deliberate differences (all verified against the files in this repo):
#   * seeds with --class=BareeSeeder (wire.php never calls it from DatabaseSeeder)
#   * adds league/flysystem-aws-s3-v3 (FILESYSTEM_DISK=s3 for MinIO needs it; install.sh does not require it)
#   * writes .env for container hostnames and plain-http local use
# Never uses migrate:fresh / db:wipe. Each stage writes a marker in /app/.baree-setup so re-runs skip finished work.
set -euo pipefail

APP=/app; OVERLAY=/overlay; TMP=/tmp/baree-build; MARK=$APP/.baree-setup
WEB_PORT="${BAREE_WEB_PORT:-8000}"; RV_PORT="${BAREE_REVERB_PORT:-8081}"
OWNER_PW="${BAREE_PG_OWNER_PASSWORD:-owner-secret}"; APP_PW="${BAREE_PG_APP_PASSWORD:-app}"; MINIO_PW="${BAREE_MINIO_PASSWORD:-minio-secret}"
WITH_CLAM="${BAREE_WITH_CLAMAV:-0}"

step() { printf '\n=== %s\n' "$*"; }
done_() { [ -f "$MARK/$1" ]; }
mark() { date -u +%FT%TZ > "$MARK/$1"; }
mkdir -p "$MARK"

# 20 random lowercase letters (same shape reverb:install generates). Uses PHP, not tr|head, which would trip pipefail via SIGPIPE.
rand_lower() { php -r 'echo implode("", array_map(fn () => chr(random_int(97, 122)), range(1, 20)));'; }

# Fails loudly if a package the Vue/Inertia/Echo frontend needs is missing from node_modules (run from the app root).
check_node_deps() {
  node -e '
const fs = require("fs");
const need = ["vue", "vite", "laravel-echo", "pusher-js", "@laravel/echo-vue", "vue-i18n"];
const bad = need.filter((p) => !fs.existsSync("node_modules/" + p + "/package.json"));
if (!fs.existsSync("node_modules/@inertiajs") || !fs.readdirSync("node_modules/@inertiajs").some((d) => d.startsWith("vue"))) bad.push("@inertiajs/vue*");
if (bad.length) { console.error("missing npm packages: " + bad.join(", ")); process.exit(1); }
console.log("npm packages present: " + need.join(", ") + ", @inertiajs/vue*");
'
}

step "Check PostgreSQL (isolated compose service 'pgsql', database baree)"
export PGPASSWORD="$OWNER_PW"
psql -h pgsql -U baree_owner -d baree -tAc 'select 1' >/dev/null
echo "PostgreSQL reachable"

if ! done_ scaffold; then
  step "Scaffold Laravel 13 Vue starter kit in a temporary folder"
  [ ! -e "$APP/artisan" ] || { echo "ERROR: $APP/artisan exists but setup never finished scaffolding. Refusing to overwrite." >&2; exit 1; }
  extra="$(find "$APP" -mindepth 1 -maxdepth 1 ! -name vendor ! -name node_modules ! -name .baree-setup | head -1)"
  [ -z "$extra" ] || { echo "ERROR: $APP is not empty (found: $extra). Refusing to overwrite." >&2; exit 1; }
  rm -rf "$TMP"; mkdir -p "$TMP"; cd "$TMP"
  # During scaffolding any DB access goes to the isolated compose database (never anything else).
  export DB_CONNECTION=pgsql DB_HOST=pgsql DB_PORT=5432 DB_DATABASE=baree DB_USERNAME=baree_owner DB_PASSWORD="$OWNER_PW"
  laravel new baree-app --vue --pest --database=pgsql --no-interaction
  cd baree-app
  # -W (--with-all-dependencies): the fresh starter kit locks guzzlehttp/psr7 3.2.0 (Laravel 13 allows ^2.9||^3.0), but the latest
  # released laravel/reverb (v1.12.0) only allows ^2.6. A plain 'require' is a partial update and cannot move the locked psr7;
  # -W lets Composer pick 2.x (and Guzzle 7.x if needed) inside every declared constraint. No package is dropped or pinned.
  composer require laravel/horizon laravel/reverb laravel/pennant phpoffice/phpword phpoffice/phppresentation smalot/pdfparser "league/flysystem-aws-s3-v3:^3.0" --with-all-dependencies --no-interaction --no-progress
  # Reverb credentials MUST exist before broadcasting is enabled. install:broadcasting enables routes/channels.php and writes
  # BROADCAST_CONNECTION=reverb, and every later artisan process then builds the Reverb broadcaster at boot; with REVERB_APP_KEY
  # unset, Pusher's constructor throws ("Argument #1 ($auth_key) must be of type string, null given"). Because laravel/reverb is
  # already installed (composer require above), install:broadcasting SKIPS reverb:install (InstalledVersions::isInstalled guard),
  # so nothing else would write the keys. Seed them first, using the same format reverb:install uses; existing keys are never replaced.
  [ -z "$(tail -c1 .env)" ] || echo >> .env
  seed_env() { grep -qE "^$1=" .env || printf '%s=%s\n' "$1" "$2" >> .env; }
  seed_env REVERB_APP_ID "$(php -r 'echo random_int(100000, 999999);')"
  seed_env REVERB_APP_KEY "$(rand_lower)"
  seed_env REVERB_APP_SECRET "$(rand_lower)"
  # --without-node: install:broadcasting otherwise runs 'npm install && npm run build' via Process::tty(true), which throws
  # "TTY mode requires /dev/tty" under 'docker compose run -T'. Echo/Pusher packages are installed explicitly below and the
  # build runs in the 'built' stage. (The command's app.ts edit is not idempotent, so it only ever runs on this fresh temp tree.)
  php artisan install:broadcasting --reverb --without-node --no-interaction
  # Explicit Reverb install (publishes config/reverb.php, adds the remaining REVERB_*/VITE_REVERB_* keys, never replaces the seeded
  # ones). Safe now: the keys exist, channels.php exists (so it will not re-run install:broadcasting), and it spawns no TTY process.
  php artisan reverb:install --no-interaction
  php artisan install:api --no-interaction
  composer require laravel/mcp --no-interaction --no-progress
  php artisan vendor:publish --tag=ai-routes --force || echo "WARN: ai-routes publish failed (overlay routes/ai.php is copied next anyway)"
  npm install @laravel/echo-vue laravel-echo pusher-js vue-i18n
  check_node_deps
  composer require --dev larastan/larastan --no-interaction --no-progress

  step "Apply BAREE overlay and wire it in"
  rsync -a --exclude .git --exclude scripts/install.sh --exclude 'scripts/windows-*.ps1' \
        --exclude docker-compose.local.yml --exclude docker/dev --exclude setup.log "$OVERLAY"/ ./
  php scripts/wire.php

  step "Copy source into the (empty) app folder; vendor/ and node_modules/ stay in Docker volumes"
  rsync -rlt --no-perms --exclude /vendor --exclude /node_modules ./ "$APP"/
  cd "$APP"
  unset DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
  mark scaffold
fi

cd "$APP"
if ! done_ deps; then
  step "Install PHP and Node dependencies from the lock files"
  composer install --no-interaction --prefer-dist --no-progress
  npm ci
  check_node_deps
  mark deps
fi

step "Write .env (container hostnames, plain http, local-only secrets)"
[ -f .env ] || cp .env.example .env
[ -z "$(tail -c1 .env)" ] || echo >> .env
while IFS= read -r line; do   # add BAREE keys that are missing; never touch existing ones here
  case "$line" in [A-Za-z_]*=*) k="${line%%=*}"; grep -qE "^${k}=" .env || echo "$line" >> .env ;; esac
done < .env.example
setenv() { sed -i -E "/^$1=/d" .env; printf '%s=%s\n' "$1" "$2" >> .env; }
setenv APP_NAME BAREE;           setenv APP_ENV local;            setenv APP_DEBUG true;          setenv APP_URL "http://localhost:${WEB_PORT}"
setenv DB_CONNECTION pgsql;      setenv DB_HOST pgsql;           setenv DB_PORT 5432;      setenv DB_DATABASE baree
if ! done_ grants; then setenv DB_USERNAME baree_owner; setenv DB_PASSWORD "$OWNER_PW"; fi   # switched to baree_app after grants
setenv SESSION_DRIVER redis;     setenv CACHE_STORE redis;       setenv QUEUE_CONNECTION redis
setenv REDIS_CLIENT phpredis;    setenv REDIS_HOST redis;        setenv SESSION_SECURE_COOKIE false
setenv FILESYSTEM_DISK s3;       setenv AWS_ACCESS_KEY_ID minio; setenv AWS_SECRET_ACCESS_KEY "$MINIO_PW"
setenv AWS_DEFAULT_REGION us-east-1; setenv AWS_BUCKET baree;    setenv AWS_ENDPOINT http://minio:9000; setenv AWS_USE_PATH_STYLE_ENDPOINT true
setenv MAIL_MAILER smtp;         setenv MAIL_HOST mailpit;       setenv MAIL_PORT 1025
setenv GOTENBERG_URL http://gotenberg:3000
if [ "$WITH_CLAM" = "1" ]; then setenv BAREE_AV_SCANNER clamav; setenv CLAMAV_HOST clamav; touch "$MARK/clamav"; else setenv BAREE_AV_SCANNER none; fi
setenv EMBEDDINGS_DRIVER fake
setenv BROADCAST_CONNECTION reverb
setenv REVERB_HOST localhost;    setenv REVERB_PORT "$RV_PORT";  setenv REVERB_SCHEME http; setenv REVERB_SERVER_PORT "$RV_PORT"
setenv VITE_REVERB_APP_KEY '"${REVERB_APP_KEY}"'; setenv VITE_REVERB_HOST '"${REVERB_HOST}"'
setenv VITE_REVERB_PORT '"${REVERB_PORT}"';       setenv VITE_REVERB_SCHEME '"${REVERB_SCHEME}"'
setenv BAREE_REALTIME_ORIGIN "ws://localhost:${RV_PORT}"
grep -qE '^APP_KEY=base64:' .env || php artisan key:generate --force

if ! done_ bucket; then
  step "Create the S3 bucket 'baree' on MinIO (AWS SDK from vendor/; no mc client needed)"
  cat > /tmp/mkbucket.php <<'PHPEOF'
<?php
require '/app/vendor/autoload.php';
$s3 = new Aws\S3\S3Client(['version' => 'latest', 'region' => 'us-east-1', 'endpoint' => 'http://minio:9000',
    'use_path_style_endpoint' => true, 'credentials' => ['key' => 'minio', 'secret' => getenv('MINIO_PW')]]);
$err = '';
for ($i = 0; $i < 30; $i++) {
    try {
        if ($s3->doesBucketExistV2('baree')) { echo "bucket baree already exists\n"; exit(0); }
        $s3->createBucket(['Bucket' => 'baree']);
        echo "bucket baree created\n"; exit(0);
    } catch (Throwable $e) { $err = $e->getMessage(); sleep(2); }
}
fwrite(STDERR, "could not reach/create bucket: $err\n"); exit(1);
PHPEOF
  MINIO_PW="$MINIO_PW" php /tmp/mkbucket.php
  mark bucket
fi

if ! done_ migrated; then
  step "Migrate (additive 'migrate', as table owner, isolated database)"
  php artisan migrate --force
  mark migrated
fi

if ! done_ seeded; then
  step "Seed reference data + demo tenant (BareeSeeder only)"
  php artisan db:seed --class='Database\Seeders\BareeSeeder' --force
  mark seeded
fi

if ! done_ grants; then
  step "Create restricted runtime role grants (scripts/pg-init.sql), then run the app as baree_app"
  psql -h pgsql -U baree_owner -d baree -v ON_ERROR_STOP=1 -v "app_password=$APP_PW" -f scripts/pg-init.sql
  setenv DB_USERNAME baree_app; setenv DB_PASSWORD "$APP_PW"
  mark grants
fi

# Overlay files that were corrected AFTER some installs had already scaffolded. The scaffold stage copies the overlay only once,
# so re-running setup would never deliver a fix. These exact files are re-copied (previous app version backed up under
# .baree-setup/backup/, nothing else touched). To ship another fix: add its path here and bump the marker name.
OVERLAY_FIXES=(resources/js/pages/Studio/Home.vue)   # i18n codemod had mangled line 34 (nested {{ $t(...) }})
if ! done_ overlay-fixes-1; then
  step "Re-sync corrected overlay files into the app (with backup)"
  for f in "${OVERLAY_FIXES[@]}"; do
    [ -f "$OVERLAY/$f" ] || { echo "ERROR: overlay file missing: $f" >&2; exit 1; }
    if [ -f "$APP/$f" ] && cmp -s "$OVERLAY/$f" "$APP/$f"; then echo "up to date: $f"; continue; fi
    if [ -f "$APP/$f" ]; then
      mkdir -p "$MARK/backup/$(dirname "$f")"; cp -p "$APP/$f" "$MARK/backup/$f.$(date -u +%Y%m%dT%H%M%SZ)"
    fi
    mkdir -p "$APP/$(dirname "$f")"; cp "$OVERLAY/$f" "$APP/$f"; echo "updated: $f"
  done
  mark overlay-fixes-1
fi

# Fix 2: login HTTP 500 (RLS on user_activity_days: tenant context was not set for the post-login activity write) and role-based
# landing (starter-kit /dashboard replaced by redirect to /platform, /admin or /home). New files are added, changed ones backed up.
OVERLAY_FIXES_2=(
  app/Http/Middleware/TrackActivity.php
  app/Providers/BareeServiceProvider.php
  app/Support/HomeRedirect.php
  app/Http/Responses/RoleAwareLoginResponse.php
  tests/Feature/Phase5/LoginTenantContextTest.php
)
if ! done_ overlay-fixes-2; then
  step "Re-sync overlay fixes #2 into the app (with backup)"
  for f in "${OVERLAY_FIXES_2[@]}"; do
    [ -f "$OVERLAY/$f" ] || { echo "ERROR: overlay file missing: $f" >&2; exit 1; }
    if [ -f "$APP/$f" ] && cmp -s "$OVERLAY/$f" "$APP/$f"; then echo "up to date: $f"; continue; fi
    if [ -f "$APP/$f" ]; then
      mkdir -p "$MARK/backup/$(dirname "$f")"; cp -p "$APP/$f" "$MARK/backup/$f.$(date -u +%Y%m%dT%H%M%SZ)"
    fi
    mkdir -p "$APP/$(dirname "$f")"; cp "$OVERLAY/$f" "$APP/$f"; echo "updated: $f"
  done
  (cd "$APP" && php artisan optimize:clear) || echo "WARN: optimize:clear failed"
  mark overlay-fixes-2
fi

# Fix 3: the test helper context() collided with Laravel's global context() -> renamed bareeTenantContext() in tests/. Every test file
# that changed is re-synced (previous versions backed up). Only files under tests/ are touched.
if ! done_ overlay-fixes-3; then
  step "Re-sync renamed test helper into tests/ (with backup)"
  (cd "$OVERLAY" && { echo tests/Pest.php; grep -rl bareeTenantContext tests --include=*.php; } | sort -u) | while read -r f; do
    if [ -f "$APP/$f" ] && cmp -s "$OVERLAY/$f" "$APP/$f"; then continue; fi
    if [ -f "$APP/$f" ]; then
      mkdir -p "$MARK/backup/$(dirname "$f")"; cp -p "$APP/$f" "$MARK/backup/$f.$(date -u +%Y%m%dT%H%M%SZ)"
    fi
    mkdir -p "$APP/$(dirname "$f")"; cp "$OVERLAY/$f" "$APP/$f"; echo "updated: $f"
  done
  mark overlay-fixes-3
fi

# Fix 4: InvitationNotification TypeError (null locale on a freshly created, not-yet-refreshed Tenant) -> service falls back to 'ar'.
OVERLAY_FIXES_4=(app/Services/Institution/InvitationService.php)
if ! done_ overlay-fixes-4; then
  step "Re-sync overlay fixes #4 (with backup)"
  for f in "${OVERLAY_FIXES_4[@]}"; do
    [ -f "$OVERLAY/$f" ] || { echo "ERROR: overlay file missing: $f" >&2; exit 1; }
    if [ -f "$APP/$f" ] && cmp -s "$OVERLAY/$f" "$APP/$f"; then echo "up to date: $f"; continue; fi
    if [ -f "$APP/$f" ]; then
      mkdir -p "$MARK/backup/$(dirname "$f")"; cp -p "$APP/$f" "$MARK/backup/$f.$(date -u +%Y%m%dT%H%M%SZ)"
    fi
    mkdir -p "$APP/$(dirname "$f")"; cp "$OVERLAY/$f" "$APP/$f"; echo "updated: $f"
  done
  mark overlay-fixes-4
fi

# Fix 5: InvitationNotification property clash with Notification::$locale (fatal), User implements MustVerifyEmail again (the starter kit's
# email verification flow), UserFactory unverified()/withTwoFactor() states, and the starter AuthenticationTest expects the role-based landing.
OVERLAY_FIXES_5=(
  app/Notifications/InvitationNotification.php
  app/Models/User.php
  database/factories/UserFactory.php
  tests/Feature/Auth/AuthenticationTest.php
)
if ! done_ overlay-fixes-5; then
  step "Re-sync overlay fixes #5 (with backup)"
  for f in "${OVERLAY_FIXES_5[@]}"; do
    [ -f "$OVERLAY/$f" ] || { echo "ERROR: overlay file missing: $f" >&2; exit 1; }
    if [ -f "$APP/$f" ] && cmp -s "$OVERLAY/$f" "$APP/$f"; then echo "up to date: $f"; continue; fi
    if [ -f "$APP/$f" ]; then
      mkdir -p "$MARK/backup/$(dirname "$f")"; cp -p "$APP/$f" "$MARK/backup/$f.$(date -u +%Y%m%dT%H%M%SZ)"
    fi
    mkdir -p "$APP/$(dirname "$f")"; cp "$OVERLAY/$f" "$APP/$f"; echo "updated: $f"
  done
  (cd "$APP" && php artisan optimize:clear) || echo "WARN: optimize:clear failed"
  # Syntax check of every PHP file with the container's PHP (read-only)
  bad=0; while IFS= read -r -d '' f; do php -l "$f" >/dev/null 2>/tmp/lint.err || { echo "SYNTAX ERROR: $f"; cat /tmp/lint.err; bad=1; }; done < <(find "$APP/app" "$APP/database" "$APP/routes" "$APP/tests" "$APP/config" -name '*.php' -print0)
  [ "$bad" = 0 ] && echo "php -l: all project PHP files OK"
  mark overlay-fixes-5
fi

# Fix 6 (20 failing tests): Vite manifest case clash on Windows (BAREE pages/Settings vs starter pages/settings share one folder on NTFS,
# so the manifest keyed Profile.vue/Security.vue under the wrong case) -> BAREE pages moved to pages/Account and the on-disk folder is
# normalised to the starter's lowercase name; /dashboard redirect moved after the starter route; FakeEmbedder dimensions follow config;
# webhook idempotency inside a savepoint; AssignmentService accepts immutable dates; FrameworkService preview counts; test harness keeps
# the ambient tenant across HTTP calls; two test expectations aligned with the seed data / role-based landing. Frontend is rebuilt.
OVERLAY_FIXES_6=(
  app/Providers/BareeServiceProvider.php
  app/Http/Controllers/Learning/AiAppsController.php
  app/Http/Controllers/Me/PrivacyController.php
  app/Http/Controllers/Operations/NotificationController.php
  app/Http/Controllers/Operations/PaymentWebhookController.php
  app/Services/Operations/AssignmentService.php
  app/Services/Platform/FrameworkService.php
  routes/baree.php
  tests/TestCase.php
  tests/Feature/DashboardTest.php
  tests/Feature/Phase5/BillingTest.php
  resources/js/pages/Account/AiApps.vue
  resources/js/pages/Account/Notifications.vue
  resources/js/pages/Account/Privacy.vue
)
if ! done_ overlay-fixes-6; then
  step "Re-sync overlay fixes #6 (with backup) and rebuild the frontend"
  for f in "${OVERLAY_FIXES_6[@]}"; do
    [ -f "$OVERLAY/$f" ] || { echo "ERROR: overlay file missing: $f" >&2; exit 1; }
    if [ -f "$APP/$f" ] && cmp -s "$OVERLAY/$f" "$APP/$f"; then echo "up to date: $f"; continue; fi
    if [ -f "$APP/$f" ]; then
      mkdir -p "$MARK/backup/$(dirname "$f")"; cp -p "$APP/$f" "$MARK/backup/$f.$(date -u +%Y%m%dT%H%M%SZ)"
    fi
    mkdir -p "$APP/$(dirname "$f")"; cp "$OVERLAY/$f" "$APP/$f"; echo "updated: $f"
  done
  # Normalise the real on-disk case of the starter kit's settings folder (case-insensitive host filesystem). Renames only; nothing is deleted.
  if ls -1 "$APP/resources/js/pages" | grep -qx Settings; then
    echo "renaming resources/js/pages/Settings -> settings (case only)"
    mv "$APP/resources/js/pages/Settings" "$APP/resources/js/pages/.settings-case-tmp" && mv "$APP/resources/js/pages/.settings-case-tmp" "$APP/resources/js/pages/settings"
  fi
  ls -1 "$APP/resources/js/pages"
  (cd "$APP" && php artisan optimize:clear) || echo "WARN: optimize:clear failed"
  (cd "$APP" && npm run build)
  grep -o '"resources/js/pages/settings/[A-Za-z]*\.vue"' "$APP/public/build/manifest.json" | sort -u || echo "WARN: no settings/* entries in the manifest"
  mark overlay-fixes-6
fi

# Fix 7: the starter kit's Security page calls $user->passkeys(); the overlay User had dropped Fortify's passkey contract + trait (HTTP 500).
OVERLAY_FIXES_7=(app/Models/User.php)
if ! done_ overlay-fixes-7; then
  step "Re-sync overlay fixes #7 (with backup)"
  for f in "${OVERLAY_FIXES_7[@]}"; do
    [ -f "$OVERLAY/$f" ] || { echo "ERROR: overlay file missing: $f" >&2; exit 1; }
    if [ -f "$APP/$f" ] && cmp -s "$OVERLAY/$f" "$APP/$f"; then echo "up to date: $f"; continue; fi
    if [ -f "$APP/$f" ]; then
      mkdir -p "$MARK/backup/$(dirname "$f")"; cp -p "$APP/$f" "$MARK/backup/$f.$(date -u +%Y%m%dT%H%M%SZ)"
    fi
    cp "$OVERLAY/$f" "$APP/$f"; echo "updated: $f"
  done
  (cd "$APP" && php -l app/Models/User.php && php artisan optimize:clear) || echo "WARN: lint/optimize:clear failed"
  mark overlay-fixes-7
fi

# Fix 8: WhatsApp outbound log is idempotent per wa_message_id (a repeated id aborted the surrounding PostgreSQL transaction), and
# TenantContext::runAs/asPlatform no longer lets a failing DB re-sync mask the original exception.
OVERLAY_FIXES_8=(app/Services/WhatsApp/WhatsAppClient.php app/Support/Tenancy/TenantContext.php)
if ! done_ overlay-fixes-8; then
  step "Re-sync overlay fixes #8 (with backup)"
  for f in "${OVERLAY_FIXES_8[@]}"; do
    [ -f "$OVERLAY/$f" ] || { echo "ERROR: overlay file missing: $f" >&2; exit 1; }
    if [ -f "$APP/$f" ] && cmp -s "$OVERLAY/$f" "$APP/$f"; then echo "up to date: $f"; continue; fi
    if [ -f "$APP/$f" ]; then
      mkdir -p "$MARK/backup/$(dirname "$f")"; cp -p "$APP/$f" "$MARK/backup/$f.$(date -u +%Y%m%dT%H%M%SZ)"
    fi
    cp "$OVERLAY/$f" "$APP/$f"; echo "updated: $f"
  done
  (cd "$APP" && for f in "${OVERLAY_FIXES_8[@]}"; do php -l "$f"; done && php artisan optimize:clear) || echo "WARN: lint/optimize:clear failed"
  mark overlay-fixes-8
fi

# Fix 9: the BAREE frontend was never wired: app.ts did not install vue-i18n (every BAREE page threw "NOT_INSTALLED") and wrapped every page
# in the starter kit's sidebar layout. New app.ts + role-aware sidebar/shell links + CSP nonce on the inline theme script + BAREE name.
OVERLAY_FIXES_9=(
  resources/js/app.ts
  resources/js/components/AppSidebar.vue
  resources/js/components/studio/StudioShell.vue
  resources/js/components/admin/AdminShell.vue
  resources/views/app.blade.php
  app/Support/HomeRedirect.php
  app/Providers/BareeServiceProvider.php
)
if ! done_ overlay-fixes-9; then
  step "Re-sync overlay fixes #9 (with backup), set APP_NAME, rebuild the frontend"
  for f in "${OVERLAY_FIXES_9[@]}"; do
    [ -f "$OVERLAY/$f" ] || { echo "ERROR: overlay file missing: $f" >&2; exit 1; }
    if [ -f "$APP/$f" ] && cmp -s "$OVERLAY/$f" "$APP/$f"; then echo "up to date: $f"; continue; fi
    if [ -f "$APP/$f" ]; then
      mkdir -p "$MARK/backup/$(dirname "$f")"; cp -p "$APP/$f" "$MARK/backup/$f.$(date -u +%Y%m%dT%H%M%SZ)"
    fi
    mkdir -p "$APP/$(dirname "$f")"; cp "$OVERLAY/$f" "$APP/$f"; echo "updated: $f"
  done
  # Brand name: only replaces the untouched starter value
  if grep -qE '^APP_NAME=("?Laravel"?)$' "$APP/.env"; then
    mkdir -p "$MARK/backup"; cp -p "$APP/.env" "$MARK/backup/.env.$(date -u +%Y%m%dT%H%M%SZ)"
    sed -i -E 's/^APP_NAME=("?Laravel"?)$/APP_NAME=BAREE/' "$APP/.env"; echo "APP_NAME set to BAREE"
  fi
  (cd "$APP" && php -l app/Support/HomeRedirect.php && php -l app/Providers/BareeServiceProvider.php && php artisan optimize:clear) || echo "WARN: lint/optimize:clear failed"
  (cd "$APP" && node scripts/i18n-check.mjs) || echo "WARN: i18n-check reported items (informational)"
  (cd "$APP" && npm run build)
  mark overlay-fixes-9
fi

# Fix 10: BAREE design tokens were never loaded (app.css did not import baree.css) and text-muted collided with the starter's --color-muted,
# so bg-ink/bg-sand/... did not exist and secondary text was near-invisible. Tokens loaded, text-muted -> text-subtle, institution dashboard
# rebuilt with real empty states, accessible sidebar. EVERY file below was changed by this fix (the list is generated from the overlay).
if ! done_ overlay-fixes-10; then
  step "Re-sync design fixes #10 (with backup) and rebuild the frontend"
  (cd "$OVERLAY" && { printf '%s\n' resources/css/app.css resources/css/baree.css resources/views/app.blade.php resources/js/i18n/en.json app/Services/Institution/AnalyticsService.php resources/js/components/admin/EmptyState.vue; grep -rlE 'text-subtle' resources/js --include=*.vue --include=*.ts; } | sort -u) | while read -r f; do
    [ -f "$OVERLAY/$f" ] || continue
    if [ -f "$APP/$f" ] && cmp -s "$OVERLAY/$f" "$APP/$f"; then continue; fi
    if [ -f "$APP/$f" ]; then
      mkdir -p "$MARK/backup/$(dirname "$f")"; cp -p "$APP/$f" "$MARK/backup/$f.$(date -u +%Y%m%dT%H%M%SZ)"
    fi
    mkdir -p "$APP/$(dirname "$f")"; cp "$OVERLAY/$f" "$APP/$f"; echo "updated: $f"
  done
  (cd "$APP" && php -l app/Services/Institution/AnalyticsService.php && php artisan optimize:clear) || echo "WARN: lint/optimize:clear failed"
  (cd "$APP" && node scripts/i18n-check.mjs) || echo "WARN: i18n-check reported items (informational)"
  (cd "$APP" && npm run build)
  mark overlay-fixes-10
fi

# Fix 12 (supersedes 11, whose build failed on a wrong icon package: the starter kit uses @lucide/vue, not lucide-vue-next): secure logout everywhere (shared LogoutButton in both shells, Fortify LogoutResponse -> /login, no-store on authenticated responses).
if ! done_ overlay-fixes-12; then
  step "Re-sync secure-logout changes #12 (with backup) and rebuild the frontend"
  for f in resources/js/components/LogoutButton.vue resources/js/components/studio/StudioShell.vue resources/js/components/admin/AdminShell.vue resources/js/i18n/en.json app/Http/Responses/LogoutToLoginResponse.php app/Providers/BareeServiceProvider.php app/Http/Middleware/SecurityHeaders.php tests/Feature/Auth/AuthenticationTest.php tests/Feature/Auth/SecureLogoutTest.php resources/js/pages/Feed/Index.vue resources/js/pages/Platform/Dashboard.vue resources/css/baree.css resources/js/i18n/index.ts resources/js/pages/Institution/Analytics/Index.vue resources/js/pages/Institution/Billing/Index.vue resources/js/pages/Platform/Billing/Index.vue resources/js/pages/Platform/Tenants/Show.vue; do
    [ -f "$OVERLAY/$f" ] || continue
    if [ -f "$APP/$f" ] && cmp -s "$OVERLAY/$f" "$APP/$f"; then continue; fi
    if [ -f "$APP/$f" ]; then
      mkdir -p "$MARK/backup/$(dirname "$f")"; cp -p "$APP/$f" "$MARK/backup/$f.$(date -u +%Y%m%dT%H%M%SZ)"
    fi
    mkdir -p "$APP/$(dirname "$f")"; cp "$OVERLAY/$f" "$APP/$f"; echo "updated: $f"
  done
  (cd "$APP" && php -l app/Http/Responses/LogoutToLoginResponse.php && php -l app/Http/Middleware/SecurityHeaders.php && php -l app/Providers/BareeServiceProvider.php && php artisan optimize:clear) || echo "WARN: lint/optimize:clear failed"
  (cd "$APP" && node scripts/i18n-check.mjs) || echo "WARN: i18n-check reported items (informational)"
  if (cd "$APP" && npm run build); then mark overlay-fixes-12; else echo "ERROR: frontend build failed; stage overlay-fixes-12 NOT marked, re-run setup after fixing"; fi
fi

if ! done_ built; then
  step "Build frontend assets"
  php artisan storage:link --force || echo "WARN: storage:link failed on this filesystem (public disk link not needed for s3 disk)"
  node /overlay/docker/dev/vue-lint.mjs || echo "WARN: vue-lint could not run (informational only)"
  npm run build
  node scripts/i18n-check.mjs
  php artisan config:clear
  mark built
fi

step "Setup complete"
