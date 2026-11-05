# Go-live gates

A release is ready only when every box is ticked by a named person.

**Engineering**
- [ ] CI green on `main`, including the RLS suite as `baree_app`
- [ ] Browser tests of the main journeys pass (invite → profile → assessment → passport → studio → lab → academy → billing)
- [ ] Load test passed (docs/LOAD_TESTING.md)
- [ ] External security test done; all high/critical findings fixed and re-tested
- [ ] Restore drill done; RTO recorded
- [ ] Monitoring and alerts live (errors, queue latency, `/health/deep`, provider failures)

**Product and content**
- [ ] Every live agent has a passing regression suite; "soon" tools are not reachable
- [ ] Assessment items reviewed by educators and piloted; level thresholds calibrated on pilot data
- [ ] English reviewed by a native speaker; Arabic reviewed for consistency
- [ ] Privacy policy, terms and data-processing agreement published; consent texts reviewed by counsel

**Operations**
- [ ] Payment provider live for each market; invoices tested end to end
- [ ] WhatsApp templates approved by Meta
- [ ] Support process and on-call rota defined
- [ ] Pilot with at least one institution for 4 weeks; feedback triaged
