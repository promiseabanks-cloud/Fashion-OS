# Changelog

All notable changes to FashionOS are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html) once implementation begins.

This changelog records **implementation history**. Approved changes to the foundational architecture decisions are recorded separately in [`ARCHITECTURE_DECISIONS.md`](ARCHITECTURE_DECISIONS.md).

## [Unreleased]

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
