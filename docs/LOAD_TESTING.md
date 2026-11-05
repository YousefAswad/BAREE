# Load testing

1. Staging with production-like sizes; set every AI provider to the fake driver (`AI_FAKE=1` or routing to `fake`) so we test our system, not the provider.
2. `php artisan baree:load-seed --tenants=20 --users=250` (refuses production) → writes `load/k6/users.json`.
3. `k6 run -e BASE=https://staging… -e PASSWORD=LoadTest-2026 load/k6/journey.js`
4. Pass criteria (school-morning peak, 500 concurrent teachers): error rate < 1%, p95 feed/studio < 600 ms, hub < 1.5 s, AI queue wait p95 < 10 s, DB CPU < 70%.
5. Record results and the bottleneck found in this file; fix, re-run.
