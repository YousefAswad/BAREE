# Security model

## Assets
Student-related data inside uploads and prompts, employee assessment results, institution documents, provider API keys, BAREE agent prompts (IP), credits.

## Threats and controls

| Threat | Control (status) |
|---|---|
| One institution reads another's data | Fail-closed scope, write guards, RLS with non-owner DB role (done) |
| IDOR via guessable URLs | ULID route keys + owner checks in controllers (done) |
| Privilege escalation via custom roles | RoleService guards: no platform perms in tenant roles, department roles need scope, only staff grant platform roles (done) |
| Student data sent to model providers | PiiRedactor on every prompt + protected names list per institution (done); upload scanning + DLP on file text (phase 3) |
| Prompt injection from institution/user text or files | Layer ordering + data wrapping + explicit guard; files treated as data (done); output filters for tool-using agents (phase 3) |
| Leaking correct assessment answers | Hidden scores, server-side scoring only (done) |
| Key theft | Encrypted at rest, hidden from serialisation, BYOK per tenant, rotation timestamp (done); KMS-backed encryption key (phase 6) |
| Cost abuse / denial of wallet | Credits ledger + per-agent caps + rate limits per user and tenant (done) |
| Tampering with history | Append-only audit and ledger + DB trigger (done) |
| XSS / clickjacking | CSP with nonces, frame-ancestors none, Vue auto-escaping (done) |
| Malicious uploads | MIME allow-list + ClamAV scanning gate before indexing (phase 3) |
| Support staff overreach | Time-boxed, reason-required support sessions, tenant notified, audited (phase 2) |
| Vulnerable runtime | PHP 8.5.12+ enforced in install script; Dependabot + `composer audit` in CI (phase 2) |

## Operational rules
- App connects as `baree_app` (no table ownership). Migrations run as `baree_owner`.
- Never call `TenantContext::asPlatform()` from tenant-facing code paths; every call in staff tooling must write an audit entry.
- Treat model output as untrusted content in the UI (no `v-html` without sanitising).
