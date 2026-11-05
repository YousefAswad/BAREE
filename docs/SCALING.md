# Scaling notes

**What already scales horizontally:** web replicas (stateless), Horizon workers per queue, Reverb with Redis, object storage, the provider layer (any number of providers, failover, per-tenant keys).

**Where the limits are, and the plan:**
- **AI throughput** is bounded by provider rate limits, not our servers. Use BYOK for large institutions, spread traffic across providers with routing, and keep `tries=1` (no silent double charges).
- **PostgreSQL**: add a read replica for analytics (`InstitutionAnalytics`, `PlatformMetrics`) once p95 of those pages exceeds 500 ms; keep writes on the primary. Partition `ai_runs` and `audit_logs` by month above ~50M rows.
- **pgvector**: HNSW index is in place; above ~5M chunks raise `ef_search` tuning or move to a dedicated vector store behind `KnowledgeSearch`.
- **Feed**: targeting is evaluated in PHP over the latest 100 posts per request; precompute per-segment audiences if an institution publishes more than ~50 posts a week.
- **Segments**: nightly sync is chunked; for institutions over 20k users move `SegmentEvaluator` rules to SQL.
- **Octane**: `TenantContext` and `PermissionResolver` are request-scoped and cleared in `terminate()`, so Laravel Octane can be enabled once load tests show PHP-FPM is the bottleneck.

**Adding features without rewrites:** new agents, levels, competencies, roles, plans, payment gateways, media providers and languages are data or a single interface implementation (`LlmDriver`, `PaymentGateway`, `ImageGenerator`/`AsyncMediaGenerator`, `Embedder`, `Scanner`).
