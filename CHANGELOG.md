# Changelog

All notable changes to FashionOS are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html) once implementation begins.

This changelog records **implementation history**. Approved changes to the foundational architecture decisions are recorded separately in [`ARCHITECTURE_DECISIONS.md`](ARCHITECTURE_DECISIONS.md).

## [Unreleased]

## [0.7.0] - 2026-11-22

### Added

- **Phase 6: Client portal.**
  - Migration `0006`: `fos_portal_access`, storing only a SHA-256 hash of each portal token, with optional expiry, revocation and last-use timestamps. Unique `token_hash` lets a request carrying just its token identify its workspace.
  - Customer access without WordPress accounts: a staff-issued link carries a 256-bit token; the resolver refuses unknown, revoked and expired tokens, an inactive workspace and a missing or archived customer with one `401 portal_access_invalid`.
  - Customer-scoped reads: `GET /portal/session`, `/portal/orders`, `/portal/orders/{id}`, `/portal/invoices`, `/portal/invoices/{id}`, scoped by the customer id in the token rather than by anything the caller sends.
  - Customer-visible data only: confirmed orders and per-garment statuses, issued/partially paid/paid invoices with lines, totals and balance, and verified payments. Draft orders, draft and void invoices, pending payments, order notes and timelines, staff names and expenses never reach the portal.
  - Portal `amountPaid` and `balance` are recomputed from the verified payment ledger, matching the studio's figures.
  - Staff sharing panel on the customer record: issue a link with a label and lifetime (up to 365 days, or none), see its status and last use, and revoke it. The link is displayed once because only its hash is stored.
  - Tenant-branded portal application at `[fashionos_portal]`, reachable by customers who are not signed in. The token is read once, stripped from the address bar and sent as `X-FashionOS-Portal`; portal pages are sent no-cache and no-index.
  - Audit entries `portal.access_issued` and `portal.access_revoked`; revocation keeps the row.
  - WordPress test-library suite in `tests/php/PortalTest.php` and Vitest coverage for the portal API client (written; the PHP suite is not yet executed — see `docs/PHASE_6_PLAN.md`).
  - Documentation: `docs/PHASE_6_PLAN.md`; API reference, schema, README and plugin readme updated.

## [0.6.0] - 2026-11-20

### Added

- **Phase 5: Finance.**
  - Migration `0005`: `fos_invoices`, `fos_invoice_items`, `fos_payments` and `fos_expenses`, with tenant-first indexes and no foreign keys.
  - Invoices (`I-0001`) priced either from an order's garments or from explicit lines. Line totals, the invoice total, `amountPaid` and `balance` are always computed on the server.
  - Invoice lifecycle `draft → issued → partially_paid → paid`, with `void`; `partially_paid` and `paid` are derived from payments and cannot be set by hand. Lines can only be replaced while the invoice is a draft.
  - Payments (`P-0001`) recorded against an issued invoice, starting `pending` and moving the balance only once verified. Methods are `cash`, `transfer`, `card` and `pos`, with optional provider reference fields for reconciliation.
  - Idempotent payments: every payment carries an idempotency key, unique per workspace (`auto-` generated when the caller omits one), so a repeated request returns the original payment instead of recording the money twice.
  - Balance reconciliation inside the verification transaction, with overpayment refused both when recording and when verifying.
  - Expenses with fixed categories, voiding instead of deleting, and a finance summary covering outstanding balance, overdue invoices, monthly collections, monthly expenses and payments awaiting verification.
  - Finance view with dashboard tiles, overdue list, invoice builder and detail, printable invoice document (browser print stylesheet), payment recording and verification, and expense management.
  - Permission keys `invoices.*`, `payments.*` and `expenses.*`, with migration `0005` re-seeding existing system roles.
  - WordPress test-library suite in `tests/php/FinanceTest.php` (written; not yet executed — see `docs/PHASE_5_PLAN.md`).
  - Documentation: `docs/PHASE_5_PLAN.md`; API reference, schema, README files and plugin readme updated.

## [0.5.0] - 2026-11-19

### Added

- **Phase 4: Production and Inventory.**
  - Migration `0004`: `fos_production_stages`, `fos_production_jobs`, `fos_production_tasks`, `fos_materials`, `fos_material_allocations`, `fos_material_movements`, `fos_fittings`, `fos_alterations` and `fos_quality_checks`, with tenant-first indexes and no foreign keys.
  - One production job per garment (unique per workspace), with an optional assignee limited to active members, start/due dates, notes and status transitions from `queued` through `in_progress`/`blocked` to `completed` or `cancelled`.
  - Configurable stage workflow: nine starter stages are seeded per workspace on first use and seeded for existing workspaces by the migration; stages can be added, renamed, reordered or deactivated.
  - Stage tasks per job with assignee, due date and completion stamps.
  - Materials with type, unit, stock on hand, reorder level and unit cost, plus an append-only stock ledger (`in`, `out`, `adjustment`, and internal `allocation`/`return` entries). Stock can never go negative.
  - Garment allocations that record their supply source. Only `designer_supplied` material decrements stock; `customer_supplied` and `shared_supply` allocations never touch it. Releasing a designer allocation returns the quantity.
  - Per-garment fittings (`scheduled`, `completed`, `missed`, `cancelled`), alterations with an optional source fitting, and append-only quality checks (`passed`, `failed`, `needs_rework`) that surface failures on the order timeline.
  - Production dashboard covering active jobs, status counts, upcoming due dates, recent failed checks and low-stock materials, plus garments that have no job yet.
  - Studio views for production (dashboard, job list, per-garment workflow) and materials (stock, movements, allocations).
  - Permission keys `production.*`, `materials.*`, `fittings.*` and `quality.*`, with migration `0004` re-seeding existing system roles.
  - WordPress test-library suite in `tests/php/ProductionInventoryTest.php` (written; not yet executed — see `docs/PHASE_4_PLAN.md`).
  - Documentation: `docs/PHASE_4_PLAN.md`; API, schema, READMEs and plugin readme updated.

### Fixed

- Quotation lines are now inserted with their `tenant_id` and `quotation_id`, so a saved quotation can always be read back with its lines.
- A design can no longer reference a measurement profile and a version from different customers.
- An order created from a quotation now validates the client-supplied `quotationItemId` against the active workspace and the chosen quotation.

## [0.4.0] - 2026-11-19

### Added

- **Phase 3: Designs, Quotations and Orders.**
  - Migration `0003`: `fos_designs`, `fos_quotations`, `fos_quotation_items`, `fos_orders`, `fos_order_items` and `fos_order_events`, with tenant-first indexes and no foreign keys.
  - Design records with per-workspace references (`D-0001`), an optional garment category and brief, and optional references to one measurement profile and one exact measurement version validated against the design's own customer.
  - Quotations (`Q-0001`) with line items, validity date, notes and status (`draft`, `sent`, `accepted`, `declined`, `expired`). Line totals and the subtotal are computed on the server; lines may only be replaced while the quotation is a draft.
  - Orders (`O-0001`) with multiple independently tracked garments, each storing its own design and measurement-version reference and its own production status (`pending`, `cutting`, `sewing`, `fitting`, `ready`, `delivered`, `cancelled`).
  - Orders created from a quotation copy its lines (keeping the source line id) and leave the quotation untouched, so the two records keep separate lifecycles.
  - Order activity timeline covering creation, garment additions, order and garment status changes and free-text notes; status changes are also written to the workspace audit trail.
  - Studio views for designs (with profile/version picker), quotations (repeatable priced lines) and orders (per-garment status and timeline).
  - Permission keys `designs.*`, `quotations.*` and `orders.*`, with migration `0003` re-seeding existing system roles.
  - WordPress test-library suite in `tests/php/QuotationOrderTest.php` (written; not yet executed — see `docs/PHASE_3_PLAN.md`).
  - Documentation: `docs/PHASE_3_PLAN.md`; API, schema, READMEs and plugin readme updated.

## [0.3.0] - 2026-11-19

### Added

- **Phase 2: CRM and Measurements.**
  - Migration `0002`: `fos_clients`, `fos_client_preferences`, `fos_measurement_templates`, `fos_measurement_profiles`, `fos_measurement_versions` and `fos_measurement_values`, with tenant-first indexes and no foreign keys.
  - Customer profiles: per-workspace reference numbers, contact details, internal notes, archive/restore, search, status filtering and pagination.
  - Customer preferences: preferred contact channel, style notes and fit observations.
  - Duplicate detection on email for active customers, returning `409 client_duplicate` with the existing record.
  - Configurable measurement templates: garment category, `cm`/`in` unit, ordered fields with labels and hints; editing fields bumps a schema version.
  - Measurement profiles per customer and garment, with version counts and latest-version summaries.
  - Append-only measurement versions: unit, capture time, taker, notes, fit observations, a template snapshot and one value row per field, written in a transaction.
  - Studio views for customers, customer detail (profiles, capture form, version history with comparison against the previous version) and measurement templates.
  - Audit actions for customers, templates, profiles and versions.
  - WordPress test-library suite in `tests/php/ClientMeasurementTest.php` (written; not yet executed — see `docs/PHASE_2_PLAN.md`).
  - Documentation: `docs/PHASE_2_PLAN.md`; API, schema, development guide and READMEs updated.

### Fixed

- `GET /clients/{id}` always reported `profileCount: 0`; the single-record query now selects the same profile count as the list query, and a test covers both payloads.
- A template updated with a too-long garment category reported the “missing category” message; it now returns `measurement_template_category_too_long`.

### Changed

- Simplified the Phase 2 code after a follow-up review: shared the customer list's count and page queries, removed the unused `ClientRepository::reference_exists()` and `Permissions::labels()`, dropped a duplicate assignment in the version presenter and an unused helper parameter, and collapsed the template editor's two state variables into one.

- Extracted `FashionOS\API\Controller`, a shared base for tenant-scoped controllers (resolver, permission check, payload parsing, error envelope), removing duplicated and divergent copies from each controller.
- Extracted `FashionOS\Database\Transaction` and `FashionOS\Database\ColumnFormats` so services share transaction handling and repositories derive `$wpdb` formats from the data they write.
- Added `clients.view`/`clients.manage` and `measurements.view`/`measurements.manage` to the permission catalogue and the seeded roles; migration `0002` re-seeds existing system roles.
- `GET /auth/me` no longer returns role definitions; `GET /tenants/current` remains their only source.
- Removed the static roadmap panel from the studio overview.

## [0.2.0] - 2026-11-19

### Added

- **Phase 1: Core Platform and Tenancy.**
  - First business migration (`0001`): `fos_tenants`, `fos_roles`, `fos_memberships` and `fos_audit_logs`, with tenant-first indexes and no hardcoded table prefix.
  - Workspace creation in a transaction: unique slug, seeded per-workspace roles, owner membership and an audit entry.
  - Tenant resolver and `TenantContext`: the `X-FashionOS-Workspace` header selects a workspace and the server verifies membership before any tenant-scoped work.
  - Roles and permissions: a permission catalogue, five seeded roles, `Policy` checks in every REST permission callback, and owner protection against re-roling or removal.
  - REST surface: `GET /auth/me`, `GET|POST /tenants`, `GET|PATCH /tenants/current`, `GET|POST /team`, `PATCH|DELETE /team/{user_id}`, `GET /audit` and the audited, read-only `GET /platform/tenants`.
  - Audit trail for workspace, membership and platform actions; platform entries use tenant id `0` so they never appear in a tenant's own history.
  - Studio shell: onboarding and workspace chooser, workspace switcher, overview, team management, workspace settings, audit trail and a read-only platform view, with loading, empty, error and success states.
  - WordPress test-library suite for cross-tenant isolation in `tests/php/` (written; not yet executed — see `docs/PHASE_1_PLAN.md`).
  - Documentation: `docs/PHASE_1_PLAN.md`; API, schema, development guide and READMEs updated.

### Changed

- Removed the Phase 0 `Container`; `Plugin` constructs repositories, services and controllers explicitly.
- Added `FashionOS\API\Rest` for the shared namespace, workspace header and error constructors, and an `ErrorEnvelope` filter so permission errors use the documented envelope.
- Moved the missing-build admin notice from `Assets` to `AppShell` and simplified manifest caching to a per-instance property.
- Migrations now run automatically after a file-only deploy when `fashionos_db_version` differs from the registry.
- The Phase 0 status-page shell was replaced by the studio shell; `GET /system/health` remains for operators.

## [0.1.0] - 2026-11-19

### Added

- **Phase 0: Environment and Repository Setup.**
  - `src/fashionos/` WordPress plugin skeleton: guarded bootstrap, PSR-4 autoloader, activation and deactivation hooks, uninstall routine.
  - Database migration framework: `Migration` contract, ordered registry, `Migrator` with a `{prefix}fos_migrations` version table, activation-time migration and `fashionos_db_version` tracking. No business tables yet.
  - REST namespace `fashionos/v1` with `GET /system/health`, the consistent success/error response envelope and a server-side permission callback.
  - Application shell integration: `[fashionos_app]` shortcode, sign-in gate, manifest-driven asset loading, `type="module"` handling, injected `window.FashionOS` configuration and an optional Vite dev-server mode.
  - React 19 + TypeScript + Vite shell with design tokens, responsive layout and API health reporting; typed central API client with unit tests.
  - Tooling: TypeScript strict mode, ESLint, Prettier, Vitest, PHP_CodeSniffer/WordPress Coding Standards configuration and deployment exclusions.
  - Documentation: `docs/PHASE_0_PLAN.md`, `docs/DEVELOPMENT.md` and `docs/DEPLOYMENT.md`; README and `src/README.md` updated.
- Initial repository documentation set:
  - `README.md` — project overview and documentation index.
  - `FashionOS_Master_Technical_Specification.md` — the complete foundational specification and single source of truth.
  - `ARCHITECTURE_DECISIONS.md` — foundational decisions and the change log for approved changes.
  - `CHANGELOG.md` — this file.
  - `docs/DATABASE_SCHEMA.md` — data model, conventions and isolation rules.
  - `docs/API_REFERENCE.md` — REST API architecture and response contracts.
  - `docs/SECURITY.md` — security, privacy and data-integrity requirements.
  - `docs/DEVELOPMENT_ROADMAP.md` — phased roadmap, testing strategy and definition of done.
  - `src/` — placeholder for implementation code.

### Changed

- Frontend build output (`src/fashionos/public/build/`) is generated before deployment rather than committed; see `docs/DEPLOYMENT.md`.

### Notes

- No business modules are implemented yet. The next step is **Phase 1: Core platform and tenancy** as described in `docs/DEVELOPMENT_ROADMAP.md`.
