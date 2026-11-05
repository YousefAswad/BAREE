# Runbook

| Situation | Signal | First actions |
|---|---|---|
| AI provider outage | `ai.run` failures, `all_providers_unavailable` in logs | Failover is automatic. If both are down, post a banner (global post, pinned); do not raise `tries`. |
| Credits exhausted (institution) | Users see "no credits" | Check `credit_ledger`; grant from `/platform/tenants/{id}`; check monthly grant job ran. |
| Queue backlog | `/health/deep` → `queue_*_backlog=false` | Scale Horizon `ai`/`media` processes; check provider latency; never purge `ai` jobs (they are paid). |
| ClamAV down | Uploads stuck in `pending` | Restart ClamAV; jobs retry with back-off; never switch scanning off in production (the app refuses). |
| Suspected data exposure | Report, anomaly in audit logs | Revoke sessions/tokens of affected accounts, export audit logs for the window, notify the institution owner within 72h, document in incident log. |
| Wrong suspension (billing) | Owner complaint | Confirm payment, `markPaid` from `/platform/billing`; suspension lifts automatically. |
| Restore needed | Data loss | Restore to staging with `scripts/restore.sh`, verify, then promote; record RTO. |

**Targets:** RPO ≤ 5 minutes (managed PITR), RTO ≤ 2 hours, monthly restore drill, quarterly incident simulation.
