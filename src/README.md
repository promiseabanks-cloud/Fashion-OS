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
│   ├── Security/      authorization policies
│   └── Database/      migrator, migrations, tables, repositories
├── resources/         React/TypeScript studio shell, services, styles
├── templates/         application shell template
├── tests/php/         WordPress test-library suites
├── public/build/      compiled assets and Vite manifest (generated; not committed)
└── dev/               development-only preview harness
```

Phases 1 and 2 are implemented: workspaces, membership and tenant-scoped roles, permission policies, an audit trail, customer profiles with preferences, configurable measurement templates, measurement profiles, and append-only measurement versions with history comparison. `GET /fashionos/v1/auth/me` bootstraps the session, `X-FashionOS-Workspace` selects a workspace (validated against membership), and every tenant route is scoped and authorized on the server.

- Setup and commands: [`../docs/DEVELOPMENT.md`](../docs/DEVELOPMENT.md)
- Release steps: [`../docs/DEPLOYMENT.md`](../docs/DEPLOYMENT.md)
- Phase 0 plan and results: [`../docs/PHASE_0_PLAN.md`](../docs/PHASE_0_PLAN.md)
- Phase 1 plan and results: [`../docs/PHASE_1_PLAN.md`](../docs/PHASE_1_PLAN.md)
- Phase 2 plan and results: [`../docs/PHASE_2_PLAN.md`](../docs/PHASE_2_PLAN.md)

See Section 5 of the [Master Technical Specification](../FashionOS_Master_Technical_Specification.md) for the intended plugin and React application structure.
