# BAREE — Complete source (phases 1–6)

This archive is the whole project: every phase is included and builds on the previous ones.

| Phase | What it adds | Main folders |
|---|---|---|
| 1 | Multi-tenant foundation, dynamic roles and permissions, assessment engine and passport, AI gateway (Claude + OpenAI-compatible) with privacy redaction, credits, audit | `app/Support/Tenancy`, `app/Authorization`, `app/Domain/Assessment`, `app/AI` |
| 2 | Super Admin portal and institution admin portal, invitations, CSV import, segments, campaigns, agent versions with tests and gradual rollout | `app/Http/Controllers/Platform`, `app/Http/Controllers/Institution`, `app/Services/Platform`, `app/Services/Institution` |
| 3 | Studio: 94-tool catalogue, lesson-pack workflow, streaming, secure uploads, workspaces, outputs, media, exports, learning scenarios, share links | `app/Services/Studio`, `app/Services/Media`, `app/Services/Export`, `app/Services/Files` |
| 4 | AI Lab with coach, XP and badges, Academy with certificates, Knowledge Hub (pgvector), feedback, MCP server | `app/Services/Learning`, `app/Services/Knowledge`, `app/Mcp` |
| 5 | Targeted feed, notifications, assignments, analytics, billing, WhatsApp, Arabic/English | `app/Services/Content`, `app/Services/Billing`, `app/Services/WhatsApp`, `resources/js/i18n` |
| 6 | Missing screens (passport, privacy, personal agent settings, editors), CI, security tests, operations, deployment | `.github/workflows`, `tests/Rls`, `docker`, `docs` |

## Run it
1. Requirements: PHP **8.5.12+**, Composer, Node 22, Docker (PostgreSQL 17 + pgvector, Redis, MinIO, ClamAV, Gotenberg, Mailpit).
2. `docker compose up -d`
3. `scripts/install.sh baree-app` — creates the Laravel 13 starter kit, copies this overlay, wires everything, migrates, seeds, sets database grants, builds, checks translations, runs the tests.
4. Fix whatever fails until `php artisan test` and CI are green. This code has not been executed yet; expect first-run errors.

Demo logins after seeding (password `password`): `staff@baree.test` → `/platform`, `admin@future.test` → `/admin`, `ahmad@future.test` → `/home`.

## Read next
`README.md` (architecture and every phase), `CHANGELOG.md`, `docs/USE_CASES.md`, `docs/SECURITY.md`, `docs/DEPLOYMENT.md`, `docs/RELEASE_CHECKLIST.md`.

UI design canvas (74 screens): https://claude.ai/artifact/KXFh9pDeBCMAez5sDFEVY6
