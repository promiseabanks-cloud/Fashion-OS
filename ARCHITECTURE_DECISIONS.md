# Architecture Decisions

This file records the foundational architecture decisions for FashionOS and the log of any approved changes to them.

The decisions below are drawn from Section 20 of the [Master Technical Specification](FashionOS_Master_Technical_Specification.md), **"Architecture Decisions That Must Not Be Changed Casually."** They are the load-bearing choices of the product. They should not be reversed informally or for development convenience.

---

## Decisions that must not be changed casually

| Decision | Rationale |
|---|---|
| WordPress is the backend runtime, not the UI design system | Allows a fully independent React experience |
| One central installation initially | Lower maintenance and simpler SaaS operations |
| Custom tenant layer, not Multisite | Better control over shared application data |
| Custom business tables | Structured transactional data and query control |
| React + TypeScript | Rich, maintainable application UI |
| Vite build assets | No production Node server requirement |
| Garment is a first-class order item | Different garments have different measurements and workflows |
| Measurement versioning | Protects historical accuracy |
| Server-side authorization | Frontend visibility is not security |
| Event/job architecture | Enables automation without always-on infrastructure |
| External connector plugin | Keeps connected websites lightweight |
| Service interfaces around infrastructure | Supports future hosting migration |

---

## Change process

Any proposed change to a decision above must include:

1. The problem with the current decision.
2. The alternative.
3. Benefits and trade-offs.
4. Migration impact.
5. Hosting and cost impact.
6. Security and data implications.
7. A clear recommendation.

Record each approved change in the log below, and update the Master Technical Specification when product scope or architecture materially changes.

---

## Change log

Approved changes to the foundational decisions are recorded here, newest first.

<!--
Template for a new entry:

### YYYY-MM-DD — <short title>

- **Decision affected:** <which row above>
- **Problem:** …
- **Alternative chosen:** …
- **Trade-offs:** …
- **Migration impact:** …
- **Hosting / cost impact:** …
- **Security / data implications:** …
- **Decision:** <approved by whom, date>
-->

_No approved changes yet. The foundational decisions above stand as originally specified._
