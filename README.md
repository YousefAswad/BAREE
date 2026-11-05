# BAREE — Phases 1–6 (Laravel 13 + Vue 3 + Inertia 3)

AI Transformation Platform for educational institutions. This package is the **foundation layer**: multi-tenant data model, dynamic RBAC, the dynamic assessment engine with the AI Passport, and a production-shaped AI gateway with Anthropic (Claude) and OpenAI-compatible providers.

> Status: written against Laravel 13 conventions but **not yet executed**. First run: `composer install`, `php artisan migrate --seed`, `php artisan test`, then fix anything the run reports. Running it inside Claude Code is the fastest path.

## Stack (verified 29 Oct 2026)

| Layer | Choice | Note |
|---|---|---|
| Runtime | **PHP 8.5.12+** | 8.5.12 (21 Oct 2026) fixes two critical unauthenticated RCE CVEs. Do not deploy below it. |
| Framework | Laravel 13 | Current major (released 17 Mar 2026). |
| Frontend | Vue 3.5 (stable) + Inertia 3 + TypeScript + Tailwind 4 | Vue 3.6 / Vapor is still RC: do not use in production yet. |
| Auth | Laravel Fortify (2FA) from the official Vue starter kit | Enforce 2FA for every admin role. |
| Data | PostgreSQL 17 + pgvector, Redis 8, S3 (MinIO locally) | Row-level security is part of the design. |
| Async | Queues + Horizon, broadcasting via Reverb | AI runs are queued, results pushed live. |
| AI | Own gateway (`app/AI`) with Anthropic + OpenAI-compatible drivers | `laravel/ai` (first-party SDK, 14 providers) can be added as another driver. |
| Tests | Pest 4 | Unit + feature suites included. |
| Later | Laravel MCP 1.0 | Expose BAREE agents/tools to external AI clients (phase 4). |

## Install

```bash
docker compose up -d                       # postgres(+pgvector), redis, minio, mailpit, clamav
./scripts/install.sh baree-app             # official Vue starter kit + this overlay
```

`scripts/install.sh` does everything below automatically (`scripts/wire.php` patches the starter kit idempotently).

Manual wiring if you apply the overlay yourself:
1. `bootstrap/providers.php` → add `App\Providers\BareeServiceProvider::class`.
2. `routes/web.php` → `require __DIR__.'/baree.php'; require __DIR__.'/platform.php'; require __DIR__.'/institution.php'; require __DIR__.'/studio.php'; require __DIR__.'/learning.php'; require __DIR__.'/operations.php';`
   `resources/js/app.ts` → `import { createBareeI18n } from './i18n'` and `.use(createBareeI18n(props.initialPage.props.locale as string))` after `.use(plugin)`.
   `routes/ai.php` (MCP) is loaded by laravel/mcp.
   `config/services.php` → `'gotenberg' => ['url' => env('GOTENBERG_URL')]`. Queues used: `ai`, `media`, `files`, `exports`, `default` (Horizon).
   `routes/console.php` → `require __DIR__.'/schedule.php';`
   `HandleInertiaRequests::share()` → add `'flash' => ['status' => fn () => $request->session()->get('status')]`.
3. `resources/css/app.css` → `@import './baree.css';` and set `<html dir="rtl" lang="ar">` in `app.blade.php`.
4. `database/seeders/DatabaseSeeder.php` → `$this->call(BareeSeeder::class);`
5. Copy `bootstrap/app.php` (keeps the starter kit middleware and adds BAREE's).

Demo logins after seeding (password `password`): `staff@baree.test` (BAREE Super Admin → `/platform`), `admin@future.test` (institution admin → `/admin`), `ahmad@future.test` (teacher).

## Architecture in one page

```
Request ─► SecurityHeaders ─► Auth ─► SetTenantContext ─► route-model binding (tenant-scoped) ─► Gate (dynamic permissions) ─► Controller
                                         │
                                         └─► PostgreSQL session vars app.tenant_id  →  RLS policies (2nd wall)

AI run:  guided form ─► AiGateway
           1. authorise (permission, institution toggle, department, role template)
           2. validate inputs against the agent's input_schema
           3. credits check (ledger balance + per-agent monthly cap)
           4. ContextBuilder: L1 BAREE training + prompt blocks → L2 institution rules → L3 project → L4 personal → L5 task
           5. PiiRedactor: names/emails/phones/IDs → [PERSON_1]… (map stored encrypted)
           6. Provider routing with failover (anthropic → openai → …), retries on 429/529/5xx only
           7. Meter tokens + debit credits (append-only ledger, locked per tenant) + audit log
           8. Output stored redacted; restored only for its owner at render time
```

Key folders: `app/Support/Tenancy` (isolation), `app/Authorization` (scoped RBAC), `app/Domain/Assessment` (scoring engine, passport), `app/AI` (gateway, drivers, privacy, context).

## Security controls in this phase

- Tenant isolation, three layers: fail-closed Eloquent scope, write guards (no cross-tenant create/update, `tenant_id` immutable), PostgreSQL RLS with `FORCE` and a non-owner runtime role.
- Public ULIDs in every URL; sequential IDs never exposed.
- Dynamic RBAC with platform / tenant / department scopes; institution roles can never hold platform permissions; department roles are bound to a department.
- Correct answers never leave the server (`QuestionOption::$hidden`), answers validated against the attempt's own questions.
- PII redaction before any provider call; redaction map encrypted; outputs restored only for the owner.
- Provider keys encrypted at rest (`encrypted` cast), per-institution BYOK supported, never serialised.
- Agent Layer-1 prompts hidden from tenants; layers 2–5 wrapped as data with an explicit injection guard.
- Append-only audit log and credit ledger (model guards + PostgreSQL trigger).
- Strict CSP with Vite nonces, HSTS, frame denial, COOP, permissions policy; rate limits per user and per tenant for AI and assessments.
- Queue job for AI runs with `tries = 1` so failures never double-charge.

See `docs/SECURITY.md` for the threat model and the remaining work.

## Roadmap

| Phase | Scope |
|---|---|
| **1 ✓** | Tenancy, RBAC, assessment engine + passport, AI gateway + first agent, tests |
| **2 ✓** | Super Admin portal (institutions, support sessions, providers, agent versions + test suite + gradual rollout, competency framework with live preview, audit) and Institution portal (dashboard, users, invitations, CSV import, structure, roles, segments, campaigns, agent access + BYOK, privacy + protected names), onboarding |
| **3 ✓** | Studio: 94-tool catalogue by department, guided forms, multi-step lesson pack, streaming, secure uploads (MIME sniffing, macro block, ClamAV, extraction), documents in context, workspaces with inherited sharing and memory, versioned outputs, refine, scenario composer, media (image, speech, async video/3D) with moderation, RTL exports (Word, PowerPoint, PDF, HTML, Markdown), read-only share links |
| **4 ✓** | AI Lab with coach + objective checks, XP and badges, passport evidence; Academy with server-graded quizzes, lab lessons and verifiable certificates; Knowledge Hub with hybrid pgvector + Arabic keyword retrieval and cited answers; institution documents; feedback signals and the Super Admin feedback centre; MCP server with personal tokens |
| **5 ✓** | Targeted feed and content, notification centre with per-type/per-channel preferences, mandatory assignments with reminders and compliance, adoption and impact analytics, seat-based billing (Stripe / regional HMAC gateway / bank transfer) with dunning, WhatsApp channel (OTP linking, templates, hub Q&A, STOP), full Arabic/English interface with RTL/LTR, feedback → test cases |
| 6 | Hardening: pen-test, load test, SOC 2-style controls, data residency options |

## Adding a provider or an agent

- Provider: insert into `ai_providers` (`driver` = `anthropic` or `openai_compatible`), add its key (platform or tenant) in `ai_provider_credentials`, list it in an agent version's `routing.providers`.
- Agent: create an `agents` row and an `agent_versions` row (system prompt, prompt blocks, `input_schema`, routing, customization policy, credit cost), test it, then set `published_version_id`.

## Phase 2 additions

**Agent release pipeline.** `draft → testing → rolling_out → published → retired`. Only drafts are editable. Each agent has a fixed regression suite (`agent_test_cases`) with deterministic assertions; blocking cases must pass before any rollout. Rollouts target pilot institutions or a stable percentage bucket (`VersionResolver`), then promote to everyone; one click rolls back to any retired version. Sandbox runs use platform keys and never touch institution data or credits.

**Institution onboarding.** Invitations store only a SHA-256 of a 64-char token, expire after 7 days, are single-use under row locks, respect an e-mail domain allow-list, and can never grant platform roles. CSV import is two-step (validate every row, then queue invitations), accepts Arabic or English headers, strips Excel BOMs and neutralises formula injection.

**Segments and audiences.** Rule-based segments (role template, department, campus, grades, subjects, level, readiness, competency) with nested `all/any`, live member counts, nightly re-sync. Audiences: OR inside a key, AND across keys.

**Privacy by default.** k-anonymity (groups under 5 are suppressed) in every institution report and campaign progress; three privacy modes; individual results require both permission and the employee's consent in consent mode. Protected names (e.g. the student roster) are encrypted at rest, never sent to the browser, and redacted from every provider call, including names with attached Arabic prefixes ("لرهف").

**BAREE staff controls.** The platform area returns 404 to anyone else, requires 2FA in production and runs in explicit platform mode. Support sessions need a written reason, last at most 30 minutes, are read-only, notify the institution owner and are audited.

## Phase 3 additions

**Studio.** The catalogue lists all 94 tools across 13 departments; each person sees only tools allowed for their role, institution and department, ranked by their weakest passport competencies. Nine tools are live (lesson pack, worksheet, question bank, parent message, meeting minutes, official letter, survey analyser, job description, plus internal scene/refine helpers); the rest show as "قريبًا" until BAREE trains and tests them. Sensitive tools are marked "بإشراف بشري".

**Lesson pack workflow.** Six steps (plan → slides → activity → worksheet → quiz → homework), each seeing earlier components, charged per step, saved as separate versioned outputs. A failed step stops the chain (status `partial`); any component can be regenerated with an instruction.

**Streaming.** Claude and OpenAI-compatible drivers stream over SSE; text is pushed to the owner's private Reverb channel ~4×/s. Deltas are redacted text; names are restored only in the final fetch. Failover never switches provider after text has reached the user.

**Uploads.** Real MIME from bytes, macro-enabled Office files refused, random names under the tenant prefix, ClamAV INSTREAM scan (fail closed), zip-bomb guard, text extraction. Agents read only `indexed` files, within a character budget, wrapped as `<document>` data and redacted like everything else.

**Media.** Image and speech generate synchronously; video and 3D use a config-driven async driver (submit → poll with back-off → download from allow-listed HTTPS hosts only). Every prompt is redacted and moderated; anything that would depict a real student is blocked. Credits are charged only on success. Advanced media follows the institution plan.

**Exports.** Word and PowerPoint right-to-left (PhpWord / PhpPresentation), PDF through Gotenberg (Chromium shaping for Arabic), HTML and Markdown. Every file carries an AI-use disclosure with the reviewer's name. Download links are private and expire after 7 days.

**Sharing.** Read-only links for a class (hashed token, 1–30 days, revocable, view counter). Shared pages render Markdown with raw HTML escaped and serve only the media of that scenario.

## Phase 4 additions

**AI Lab.** Paths and missions with synthetic, anonymised practice data. Each step is judged twice: an objective check (e.g. did the learner find the planted error?) and an AI coach scoring the rubric as JSON. A failed objective check can never be overruled by the coach. Submissions are redacted before the coach sees them. Completion gives idempotent XP, writes `lab` evidence into the passport (40% weight) and evaluates badges.

**Academy.** Courses → modules → lessons (reading, video, quiz, lab). Quizzes are graded on the server and answer keys never reach the browser. A lab lesson counts only after its mission is completed. At 100% the learner gets XP, badges and a certificate whose fields are HMAC-signed: anyone can verify it at `/certificates/{serial}` and any change to the record breaks the signature.

**Knowledge Hub.** Institutions publish policies and guides (text or scanned uploads); BAREE publishes global guides. Documents are chunked on paragraph boundaries, embedded, and searched with pgvector (HNSW, cosine) plus Arabic-normalised keyword overlap. Retrieval is limited to the user's institution + BAREE documents + the user's audience. Answers must cite numbered sources; below the relevance threshold the hub says it does not know and no model is called. Unanswered questions are shown to the institution as gaps in its documents.

**Feedback.** Ratings with reasons and redacted comments, plus an implicit signal whenever a person edits an AI output by hand (similarity-based change %). Only users who keep "help improve the agents" on are counted. The Super Admin feedback centre aggregates by agent with no user or institution identifiers.

**MCP.** `/mcp/baree` (laravel/mcp) exposes four tools — list tools, run a single-step tool, ask the knowledge hub, my passport — for Claude Desktop and other MCP clients. Personal Sanctum tokens with the `mcp` ability, 90-day expiry, revocable; every call runs as the user with the same permissions, rules, redaction and credits.

## Phase 5 additions

**Feed and content.** Institution and BAREE posts targeted by role, level, competency gap, department, segment or campus (OR inside a key, AND across keys), scheduled or pinned, with views/clicks. Call-to-action links must be https or internal paths. The home feed also shows mandatory items due and one tool suggestion based on the passport.

**Notifications.** Every notification declares a type (assignment, achievement, institution, tools, billing, security); channels come from the user's preferences (in-app, e-mail, WhatsApp). Security notices cannot be switched off. Queued notifications render in the recipient's language.

**Assignments.** Courses and lab missions assigned to an audience, optionally mandatory with a due date. Reminders 3 days before and weekly when overdue, each sent once per period. Compliance for mandatory training lists who finished (never scores).

**Analytics.** Daily-activity rows (no page tracking), monthly actives, outputs by type, estimated hours saved (clearly labelled), CPD hours, department usage with k-anonymity, monthly readiness snapshots.

**Billing.** Per-seat plans, subscriptions with renewal, plan changes at renewal, immediate seat additions with a prorated invoice, sequential invoice numbers, tax, monthly credit grants (once per month), seat limits enforced on invitations. Payment: Stripe Checkout where available, a generic HMAC-signed gateway for regional providers, or bank transfer confirmed by BAREE finance. Webhooks are signature-verified and idempotent. Overdue invoices move the subscription to past-due, remind the owners weekly, and suspend the institution after the grace period; payment reactivates it automatically.

**WhatsApp.** Cloud API with approved templates for business-initiated messages, OTP-verified phone linking (hashed code, 5 attempts, 10-minute expiry, numbers encrypted and looked up by HMAC), explicit opt-in, STOP to opt out, signed webhooks, delivery statuses. Verified users can ask the knowledge hub over WhatsApp (daily limit); unknown numbers get no reply. Message bodies are not stored.

**Languages.** Arabic (RTL) and English (LTR) across every screen: Arabic is the source text and translation key, English lives in `resources/js/i18n/en.json` and `lang/en.json`. `scripts/i18n-check.mjs` fails CI if a screen has untranslated text. Content fields are `{ar, en}` and fall back to Arabic.

## Phase 6: from "written" to "proven"

Everything needed to prove the system works is now in the repository; proving it requires running it:

1. `scripts/install.sh` → fresh starter kit + overlay + automatic wiring + migrations + grants + build + i18n check + tests.
2. Fix what fails, until `php artisan test` and the CI workflow are green (PostgreSQL, RLS as `baree_app`, static analysis, type check, audits).
3. Browser tests of the main journeys, then `docs/LOAD_TESTING.md`, then an external test against `docs/PENTEST_CHECKLIST.md`.
4. Pilot, then `docs/RELEASE_CHECKLIST.md`.

Operations: `docs/DEPLOYMENT.md`, `docs/SCALING.md`, `docs/RUNBOOK.md`, `scripts/backup.sh`, `scripts/restore.sh`, `GET /health/deep` (bearer `BAREE_HEALTH_TOKEN`).
