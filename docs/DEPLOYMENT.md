# Deployment

## Topology
| Component | Production choice | Notes |
|---|---|---|
| App | `Dockerfile` image (PHP 8.5.12+ FPM) behind nginx, ≥2 replicas | Stateless; sessions/cache in Redis |
| Queues | Horizon from the same image (`config/horizon_baree.php`) | Queues: `ai`, `media`, `files`, `exports`, `default` |
| Scheduler | One container running `schedule:work` | Exactly one instance |
| WebSockets | Reverb (sticky sessions or Redis scaling) | Private channels only |
| Database | Managed PostgreSQL 17 + pgvector, PITR on | App connects as `baree_app` (non-owner) |
| Cache/queues | Managed Redis 8 | Separate DB numbers for cache and queues |
| Files | S3-compatible bucket, private, server-side encryption | Keys per tenant prefix |
| Antivirus | ClamAV container (freshclam updates) | Uploads stay `pending` if it is down |
| PDF | Gotenberg with JavaScript disabled | Internal network only |
| CDN | In front of `/build/*` | Immutable assets |

## First deploy
1. Provision the services above. Put them on a private network; only nginx and Reverb are public.
2. Create two database users: `baree_owner` (migrations) and `baree_app` (runtime). Never run the app as owner.
3. Build and push the image (CI builds it on `main`). The image refuses to build on PHP < 8.5.12.
4. Set `.env` from `.env.example`. Required: `APP_KEY`, DB/Redis/S3, `BAREE_HEALTH_TOKEN`, provider keys, `GOTENBERG_URL`, `CLAMAV_HOST`.
5. Migrate as owner: `DB_USERNAME=baree_owner php artisan migrate --force`.
6. Grants: `psql $OWNER_URL -v ON_ERROR_STOP=1 -v app_password=… -f scripts/pg-init.sql`.
7. Seed reference data only: `php artisan db:seed --class=Database\\Seeders\\BareeSeeder --force`, then delete the demo tenant and demo users or skip `DemoTenantSeeder` in production.
8. `php artisan config:cache route:cache view:cache event:cache`.
9. Start app, Horizon, scheduler, Reverb. Check `/up` and `GET /health/deep` with the bearer token.
10. Create the first BAREE staff account, enable 2FA, then disable the seeded staff account.

## Every deploy
- CI must be green (tests on PostgreSQL, RLS suite as `baree_app`, Pint, PHPStan, vue-tsc, i18n check, audits).
- Migrations are backward compatible for one release (expand → migrate → contract).
- `php artisan horizon:terminate` after deploy so workers load the new code.
- Watch error rate and queue latency for 30 minutes; roll back by redeploying the previous image tag.

## Secrets
- Provider keys live in the database encrypted with `APP_KEY`; rotating `APP_KEY` requires re-encrypting (use `APP_PREVIOUS_KEYS`).
- Webhook secrets (Stripe, HMAC gateway, WhatsApp app secret) are environment variables; rotate twice a year.
