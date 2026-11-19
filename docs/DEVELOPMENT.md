# Development Guide

How to build, run and verify FashionOS locally. Release steps are in [DEPLOYMENT.md](DEPLOYMENT.md); phase scope is in [DEVELOPMENT_ROADMAP.md](DEVELOPMENT_ROADMAP.md).

## Requirements

- **Node.js 20+** and npm — only for building the frontend.
- **WordPress 6.4+** and **PHP 8.1+** (8.3 preferred) — the application runtime.
- MySQL or MariaDB for the WordPress database.

No Node.js server is required at runtime, and no Composer install is required in production: the plugin autoloads its own classes.

## Frontend

```bash
cd src/fashionos
npm install
npm run dev        # standalone shell preview at http://localhost:5173
npm run build      # compiled assets + manifest in public/build/
```

`npm run dev` serves `index.html` with `dev/mock.ts`, which supplies the configuration WordPress normally injects and emulates the Phase 1 and 2 endpoints in memory (two seeded workspaces, team, audit trail, customers, measurement templates, profiles and immutable versions). It is a preview harness, not a WordPress integration test: it shares no code with the PHP implementation.

### Checks

```bash
cd src/fashionos
npm run typecheck     # TypeScript, strict
npm run lint          # ESLint
npm run format:check  # Prettier
npm test              # Vitest
npm run build         # typecheck + production build
```

## Running inside WordPress

1. Link or copy `src/fashionos` into `wp-content/plugins/fashionos`.
2. Run `npm install && npm run build` inside the plugin (or the copy).
3. Activate **FashionOS** in Plugins.
4. Create a page containing the `[fashionos_app]` shortcode and open it while signed in.

For live reload during development, add to `wp-config.php`:

```php
define( 'FASHIONOS_VITE_DEV', 'http://localhost:5173' );
```

With that constant defined, WordPress enqueues the Vite client and entry instead of the built manifest. Remove it for any environment that is not your own machine.

### Verifying the backend

- `GET /wp-json/fashionos/v1/system/health` requires a signed-in user with `read`; anonymous requests return 401.
- Activation creates `{prefix}fos_migrations` and the Phase 1 and 2 tables, and records `fashionos_db_version`. The health response reports applied and pending migrations. Pending migrations also run automatically after a file-only deploy, because `Plugin::boot()` compares the stored version with the registry.
- `GET /wp-json/fashionos/v1/auth/me` lists the signed-in user's workspaces and the active one. With several workspaces and no selection it returns `activeTenant: null` and `workspaceError: "workspace_required"`; send `X-FashionOS-Workspace: <id or slug>` to select one.
- A workspace the user does not belong to must answer `403 workspace_access_denied`, and an unknown one `404 workspace_not_found`.
- Error responses use the same `{ success: false, error }` envelope as successful ones, including errors raised by permission callbacks. Validation errors from `FashionOS\API\Rest::invalid()` put field-level context under `error.details`.
- Customers and measurements: create a template, create a customer, add a profile and record a version twice with `POST /clients/{id}/measurements`, `POST /measurement-profiles/{id}/versions` and `GET /measurement-versions/{id}`. The first version must keep its original values and template snapshot after the template is edited; a second capture must be numbered `2`.

Create two users in two workspaces, then repeat each tenant-scoped request with the other user's cookie: workspace, team and audit requests must fail with 403/404 and never return the other tenant's rows.

### PHP tests

The suites in `tests/php/` use the WordPress test library: tenant isolation in `TenancyIsolationTest` and customers/measurements in `ClientMeasurementTest`.

```bash
cd src/fashionos
composer install
WP_TESTS_DIR=/path/to/wordpress-tests-lib vendor/bin/phpunit
```

These tests are written but have **not** been executed in the development environment used to build this phase (no PHP runtime or database). Run them before any pilot release.

## PHP standards

```bash
cd src/fashionos
composer install
composer lint:php      # PHP_CodeSniffer with WordPress Coding Standards
composer lint:php:fix  # automatic fixes
```

The ruleset is `phpcs.xml.dist`. Class files use PSR-4 names (`app/Core/Plugin.php`) rather than WordPress file naming; that rule is excluded deliberately.

## Conventions

- **Structure**: controllers handle HTTP and validation, services hold business rules, repositories own SQL, migrations own schema. A controller must not contain complex business logic or raw SQL for another module.
- **Security**: guard every PHP file with `ABSPATH`, escape output in context, validate input at the boundary, enforce permissions server-side, and scope every tenant-owned query by `tenant_id`. Frontend visibility is never authorization.
- **API**: namespace `fashionos/v1`, consistent `{ success, data, meta }` / `{ success, error }` envelopes via `FashionOS\API\Responses\Response`.
- **Database**: use `$wpdb->prefix`, never hardcode `wp_`; money is `DECIMAL(14,2)`, measurements `DECIMAL(10,2)` with units, timestamps stored in UTC; index tenant filters and status/date columns; wrap operations that must succeed together in transactions.
- **Migrations**: add a class implementing `FashionOS\Database\Migration` and register it in `app/Database/Migrations/Migrations.php` in ascending version order. Never edit an applied migration; ship a new one.
- **Frontend**: React + TypeScript strict, no business logic in components, all requests through `resources/js/services/api.ts`, consistent loading/empty/error/success states, mobile-first layouts, motion that explains state and respects reduced-motion.
- **Docs**: update the relevant file in `docs/` and add a `CHANGELOG.md` entry with each module.

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| Page shows "interface has not been built" | Run `npm run build`, or define `FASHIONOS_VITE_DEV` for local development. |
| Health endpoint returns 401 | Sign in; the endpoint requires an authenticated user. |
| Assets 404 after deploy | Upload `public/build/` with the plugin and confirm file permissions. |
| Vite dev styles missing in WordPress | The Vite server must be running and reachable from the browser at the configured origin. |
