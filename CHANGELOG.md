# Changelog

## Phase 6
- New screens: `/passport` (radar now vs first assessment, level ladder, blockers, evidence, badges, CPD), `/settings/privacy` (consents, data export, account erasure), `/studio/tools/{key}/settings` (layer-4 instructions and files), `/platform/prompts`, `/platform/questions`, `/platform/plans`, `/platform/authoring/*` (courses, modules, lessons, missions).
- Context: personal agent files (layer 4) are included when the agent allows them.
- Hardening: `.github/workflows/ci.yml`, `phpstan.neon`, `pint.json`, `phpunit.rls.xml` + `tests/Rls`, `tests/Feature/Phase6`, `AssignRequestId`, `HealthController`, `RetentionJob`, performance index migration, `baree:load-seed`, `load/k6/journey.js`, `Dockerfile`, `docker/*`, `docker-compose.prod.yml`, `config/horizon_baree.php`, `scripts/backup.sh`, `scripts/restore.sh`, `scripts/wire.php`, idempotent `scripts/pg-init.sql`.
- i18n: codemod handles template literals and component props; the checker now also fails on unwrapped Arabic. 790 UI strings translated.
- Docs: DEPLOYMENT, SCALING, PENTEST_CHECKLIST, RUNBOOK, LOAD_TESTING, RELEASE_CHECKLIST.

## Phase 5
- New: `/home` feed, `/notifications`, `/settings/notifications` (+ WhatsApp linking), `/admin/content`, `/admin/assignments`, `/admin/analytics`, `/admin/billing`, `/platform/billing`, `/platform/content`, `/webhooks/payments/{provider}`, `/webhooks/whatsapp`, `/locale`.
- New services: TargetingMatcher, FeedService, PostService, NotificationPreferences (+ PreferenceAware trait, WhatsApp channel), AssignmentService, InstitutionAnalytics, BillingService, InvoiceService, Stripe/HMAC/manual gateways, WhatsAppClient, PhoneLinkService; jobs BillingCycleJob, SnapshotReadinessJob, ProcessWhatsAppInboundJob; middleware SetLocale, TrackActivity.
- Changed: all Vue screens bilingual (codemod `scripts/i18n-wrap.py`, check `scripts/i18n-check.mjs`); seat guard on invitations; feedback can be converted to draft test cases; users have a preferred locale for notifications.
- Migration: `2026_11_02_000001_create_phase5_operations_tables.php` (+ notifications table if missing).
- Tests: 5 feature suites in `tests/Feature/Phase5`.

## Phase 4
- New: `/lab` (paths, missions with coach), `/academy` (courses, lessons, quizzes, certificates), `/certificates/{serial}` public verification, `/hub` (ask + resources), `/admin/knowledge`, `/platform/learning`, `/platform/feedback`, `/settings/ai-apps`, MCP server at `/mcp/baree`.
- New services: MissionService + CoachResponseParser, GamificationService, CourseService, CertificateService, Chunker, Embedder (OpenAI/fake), KnowledgeIndexer, KnowledgeSearch (pgvector + PHP fallback), HubService, FeedbackService, ArabicNormalizer.
- Changed: AiGateway accepts retrieved documents; OutputService records implicit edit feedback; User uses Sanctum tokens.
- Seeders: LearningSeeder (2 paths, 2 missions with planted errors, 4 badges, 1 course, 3 global hub documents, coach and hub agents).
- Migration: `2026_11_01_000001_create_phase4_learning_knowledge_tables.php` (pgvector + HNSW, RLS).
- Tests: 4 feature suites in `tests/Feature/Phase4`, 1 unit suite.

## Phase 3
- New: studio (`/studio`), tool pages with guided forms, workflow runs with live progress, workspaces (`/workspaces`), outputs with versions/refine/export/share, scenario composer, public share pages (`/s/{token}`).
- New services: FileIntakeService + ClamAV scanner + TextExtractor, WorkflowRunner, OutputService, WorkspaceService (+ policy), ScenarioService, ShareLinkService, ToolCatalog, MediaService + MediaRegistry + ModerationGate + drivers (OpenAI images/speech, ElevenLabs, async HTTP, fake), ExportService + DOCX/PPTX/PDF/HTML renderers.
- Changed: AiGateway has one execute path (runs, workflow steps, documents, streaming); drivers implement StreamingLlmDriver; SharedOrTenantOwned fills tenant_id for institution-created records; AgentVersion casts rollout/workflow (fixes rollout reads from phase 2).
- Seeders: StudioSeeder (94-tool catalogue, lesson-pack workflow, 9 working tools).
- Migration: `2026_10_31_000001_create_phase3_studio_tables.php`.
- Tests: 6 feature suites in `tests/Feature/Phase3`, 3 unit suites.

## Phase 2
- New: Super Admin portal (`/platform`): dashboard, institutions + provisioning, support sessions, providers + keys + connection test, agents with versions, regression suite, gradual rollout and rollback, competency framework editor with live impact preview, audit log.
- New: Institution portal (`/admin`): dashboard with k-anonymity, users, invitations, CSV import, structure, roles matrix, dynamic segments, assessment campaigns, agent access + institution rules + BYOK, privacy settings + protected names.
- New: invitation acceptance, onboarding profile, assessment intro that states who can see results.
- Changed: `AiGateway` resolves versions per tenant (rollouts), merges encrypted protected names into redaction, exposes `runSandbox()` for tests; `ContextBuilder` never reads institution data in sandbox runs.
- Migration: `2026_10_30_000001_create_phase2_tables.php` (+ RLS for new tenant tables).
- Tests: 9 new suites under `tests/Feature/Phase2` and `tests/Unit/VersionResolverTest.php`.

## Phase 1
- Multi-tenant foundation, dynamic RBAC, assessment engine + passport, AI gateway (Claude + OpenAI-compatible) with privacy redaction, credits ledger, audit log, first agent, tests.
