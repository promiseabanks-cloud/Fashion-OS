# src/

Implementation code for FashionOS lives here.

## src/fashionos/

A single modular WordPress plugin named `fashionos`, with an independently designed React + TypeScript frontend compiled by Vite.

```text
src/fashionos/
├── fashionos.php      plugin bootstrap, autoloader, activation hooks
├── app/
│   ├── Core/          bootstrap, assets, application shell
│   ├── API/           REST namespace, controllers, response envelopes
│   ├── Tenancy/       tenant context, resolver, roles, permissions, service
│   ├── Clients/       customer workflows and response shapes
│   ├── Measurements/  templates, profiles, versioned measurements
│   ├── Designs/       design records and measurement references
│   ├── Quotations/    priced offers and line items
│   ├── Orders/        orders, garments and activity timeline
│   ├── Production/    stages, jobs, tasks, dashboard and staff checks
│   ├── Inventory/     materials, stock ledger and garment allocations
│   ├── Fittings/      fitting bookings, outcomes and alterations
│   ├── Quality/       per-garment quality checks
│   ├── Commerce/      shared money and quantity parsing
│   ├── Security/      authorization policies
│   └── Database/      migrator, migrations, tables, repositories
├── resources/         React/TypeScript studio shell, services, styles
├── templates/         application shell template
├── tests/php/         WordPress test-library suites
├── public/build/      compiled assets and Vite manifest (generated; not committed)
└── dev/               development-only preview harness
```

Phases 1–5 are implemented: workspaces, membership and tenant-scoped roles, permission policies, an audit trail, customer profiles with preferences, configurable measurement templates, append-only measurement versions, design records, quotations with server-computed totals, orders whose garments each reference a specific measurement version and carry their own production status and timeline, per-garment production jobs with a configurable stage workflow, stock-tracked materials with explicit supply sources, fittings, alterations and quality checks, and invoices with verified, idempotent payments and categorised expenses. `GET /fashionos/v1/auth/me` bootstraps the session, `X-FashionOS-Workspace` selects a workspace (validated against membership), and every tenant route is scoped and authorized on the server.

- Setup and commands: [`../docs/DEVELOPMENT.md`](../docs/DEVELOPMENT.md)
- Release steps: [`../docs/DEPLOYMENT.md`](../docs/DEPLOYMENT.md)
- Phase 0 plan and results: [`../docs/PHASE_0_PLAN.md`](../docs/PHASE_0_PLAN.md)
- Phase 1 plan and results: [`../docs/PHASE_1_PLAN.md`](../docs/PHASE_1_PLAN.md)
- Phase 2 plan and results: [`../docs/PHASE_2_PLAN.md`](../docs/PHASE_2_PLAN.md)
- Phase 3 plan and results: [`../docs/PHASE_3_PLAN.md`](../docs/PHASE_3_PLAN.md)
- Phase 4 plan and results: [`../docs/PHASE_4_PLAN.md`](../docs/PHASE_4_PLAN.md)
- Phase 5 plan and results: [`../docs/PHASE_5_PLAN.md`](../docs/PHASE_5_PLAN.md)

See Section 5 of the [Master Technical Specification](../FashionOS_Master_Technical_Specification.md) for the intended plugin and React application structure.
