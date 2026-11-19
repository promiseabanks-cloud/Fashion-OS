# Phase 2 — CRM and Measurements

> Phase 2 of the [Development Roadmap](DEVELOPMENT_ROADMAP.md), implemented on top of Phase 1. The [Master Technical Specification](../FashionOS_Master_Technical_Specification.md) remains authoritative.
>
> **Status:** implemented. Frontend typecheck, lint, formatting, unit tests and production build pass. PHP was syntax-checked with a parser; it was **not executed** — no PHP interpreter or database exists in this environment, so migrations, REST routes and the PHPUnit suites are unverified at runtime.

## 1. Fresh-eyes review of Phase 1

Phase 1 was re-read before building on it. Changes made:

| Finding | Change |
|---|---|
| `payload()` and `error()` were copy-pasted into each controller, and the copies had drifted. `TenantController::error()` read `$data['status']` before checking that the error data was an array, so a `WP_Error` without data would warn or throw instead of returning `400`; field-level details were also nested under a `details` key that carried the HTTP status. | Added `app/API/Controller.php`: one base class owns the resolver, the permission check, the resolved context, JSON-body parsing and `WP_Error` → envelope mapping, and unwraps `details` so validation errors keep the documented shape. `TenantController`, `TeamController`, `AuditController` and both Phase 2 controllers extend it. |
| Every tenant-scoped callback repeated a `null === $context` guard with its own error message. | `Controller::active()` returns the context or a `workspace_required` error; callbacks branch once. |
| `TenantRepository::create()` passed a fixed positional format list of ten `%s`/`%d` specifiers. Adding or reordering a column silently mis-formats writes. | Added `Database\ColumnFormats`; repositories derive specifiers from the data array's keys. Used by the tenant, client, template and measurement repositories. |
| `TenantService` carried a private `START TRANSACTION` helper and a `$wpdb` handle it used for nothing else. Phase 2 needs identical behaviour. | Extracted `Database\Transaction::run()`; both services share it. |
| `GET /auth/me` returned every role definition, and `GET /tenants/current` returned them again. Nothing read the first copy. | Removed `roles` from `/auth/me` and from `MeResponse`; `TenantController::present()` remains the single source. |
| The studio overview ended with a static "Coming next" roadmap panel duplicating `DEVELOPMENT_ROADMAP.md`, which would be wrong as soon as this phase shipped. | Removed the panel; the overview is now role, team, timezone, currency and permissions only. |

Kept as-is: header-based workspace selection validated against membership, one repository per table, per-view fetching with no router or query cache, the permission-catalogue-plus-seeded-roles model, and the in-memory development mock.

## 2. What Phase 2 delivers

- **Customers** (`fos_clients`): create, edit, archive/restore, per-workspace reference numbers (`C-0001`), search by name/email/phone/reference, status filter and pagination.
- **Preferences** (`fos_client_preferences`): preferred contact channel, style notes and fit observations, stored as one JSON row per customer.
- **Duplicate detection**: creating or editing a customer to an email already used by an active customer in the same workspace returns `409 client_duplicate` with the existing record's id and reference.
- **Measurement templates** (`fos_measurement_templates`): a garment category, a capture unit (`cm` or `in`) and an ordered field list with labels and hints. Editing fields bumps `schema_version`.
- **Measurement profiles** (`fos_measurement_profiles`): several per customer, each tied to a template and carrying the template's garment category.
- **Versioned measurement sessions** (`fos_measurement_versions`, `fos_measurement_values`): every capture appends a new version with its own unit, `taken_at`, `taken_by`, notes, fit observations and a **snapshot of the template fields**.
- **Immutable history**: no update or delete path exists for a version or a value, in the database access layer or the API. Corrections are new versions.
- **History and comparison UI**: each profile lists its versions; opening one shows every value, and compares it to the previous version with a change column.
- **Audit**: `client.created`, `client.updated`, `measurement.template.created`, `measurement.template.updated`, `measurement.profile.created`, `measurement.version.created`.
- **Role permissions** for customers and measurements, including a data migration that re-seeds existing system roles so workspaces created in Phase 1 gain the new keys.

Deliberately deferred, with what owns it:

- **Private customer files** (`fos_client_files`): uploads need WordPress media handling, private storage rules and a download path that authorizes per request. That belongs with the client portal and design references (Phase 3/6), not a half-built uploader here. The table is not created yet.
- **Orders referencing a version**: `fos_order_items.measurement_version_id` arrives with orders in Phase 3. Version ids are already stable, and no endpoint can rewrite them.
- **Customer-specific measurement instructions** shown in a portal (Phase 6); `fitNotes` and preferences cover internal use today.
- **Bulk import, diagrams, guided capture, PDF export**: later phases.
- **Profile archiving UI**: the `status` column exists and is enforced when recording a version, but no endpoint changes it yet.

## 3. Schema

Migration `0002` (`Migration0002ClientsMeasurements`), all tables prefixed with the configured WordPress prefix via `dbDelta()`.

**`{prefix}fos_clients`** — `id`, `tenant_id`, `uuid`, `reference`, `name`, `email`, `phone`, `notes`, `status`, `created_by`, `created_at`, `updated_at`. Unique `uuid` and `(tenant_id, reference)`; indexes `(tenant_id, status, name)`, `(tenant_id, email)`, `(tenant_id, phone)`.

**`{prefix}fos_client_preferences`** — `id`, `tenant_id`, `client_id`, `preferences_json`, `created_at`, `updated_at`; unique `(tenant_id, client_id)`.

**`{prefix}fos_measurement_templates`** — `id`, `tenant_id`, `uuid`, `name`, `garment_category`, `unit`, `schema_json`, `schema_version`, `status`, `created_by`, `created_at`, `updated_at`. Unique `uuid`; indexes `(tenant_id, status)`, `(tenant_id, garment_category)`.

**`{prefix}fos_measurement_profiles`** — `id`, `tenant_id`, `client_id`, `template_id`, `uuid`, `profile_name`, `garment_category`, `status`, `created_by`, `created_at`, `updated_at`. Unique `uuid`; indexes `(tenant_id, client_id)`, `(tenant_id, template_id)`, `(tenant_id, status)`.

**`{prefix}fos_measurement_versions`** — `id`, `tenant_id`, `profile_id`, `version_number`, `template_id`, `template_version`, `template_snapshot_json`, `unit`, `taken_at`, `taken_by`, `notes`, `fit_notes`, `created_at`. Unique `(tenant_id, profile_id, version_number)`; indexes `(tenant_id, taken_at)`, `taken_by`.

**`{prefix}fos_measurement_values`** — `id`, `tenant_id`, `version_id`, `measurement_key`, `label`, `value DECIMAL(10,2)`, `unit`, `sort_order`. Unique `(tenant_id, version_id, measurement_key)`; index `(tenant_id, version_id)`.

No foreign keys, for the reason recorded in Phase 1: hosting engines may not enforce them. Every query filters by `tenant_id`, and parent records are checked in the service before a child is written.

Role permissions created by migration `0001` were fixed lists, so migration `0002` re-seeds the `is_system` roles from `Roles::defaults()` per tenant. There is no role-editing UI, so nothing user-authored is overwritten; the owner's wildcard is untouched.

## 4. Roles and permissions

Two permission pairs were added to the catalogue:

| Permission | Meaning |
|---|---|
| `clients.view` | See customer profiles, contact details and preferences |
| `clients.manage` | Create, edit and archive customers |
| `measurements.view` | See templates, profiles and recorded versions |
| `measurements.manage` | Create templates, profiles and new versions |

| Role | Phase 2 permissions |
|---|---|
| Owner | `*` (includes everything above) |
| Manager | all four |
| Designer | all four — the roadmap's designer can create customers and profiles |
| Tailor / production | `clients.view`, `measurements.view` |
| Finance | `clients.view` |

Templates are configuration, so creating or editing one requires `measurements.manage`; viewing them only requires `measurements.view`.

## 5. Measurement model

- A **template** defines *what* to measure for a garment category. It is editable, which is why it is not the source of truth for history.
- A **profile** groups measurements for one customer and one purpose ("Wedding suit", "Reception dress"). Several profiles per customer are expected.
- A **version** is one capture. It stores the unit, who took it, when, notes, fit observations, and a JSON snapshot of the template (`id`, name, category, unit, schema version, fields).
- **Values** are rows, one per field, each carrying its own label and unit. A value row is written with its version inside a transaction, so a version never exists with a partial set.

Immutability is enforced structurally rather than by convention:

1. `MeasurementRepository` has no method that updates or deletes a version or a value.
2. No REST route targets a version with `PATCH` or `DELETE`.
3. `(tenant_id, profile_id, version_number)` is unique, so numbering cannot silently repeat.
4. `MeasurementService` only inserts; corrections create the next version.

A future order garment will reference `fos_measurement_versions.id`. Because versions are append-only, that reference cannot drift.

## 6. API

| Method | Route | Permission | Purpose |
|---|---|---|---|
| `GET` | `/clients` | `clients.view` | Search (`search`), filter (`status=active\|archived\|all`), paginate (`page`, `per_page`) |
| `POST` | `/clients` | `clients.manage` | Create a customer with optional preferences |
| `GET` | `/clients/{id}` | `clients.view` | One customer with preferences |
| `PATCH` | `/clients/{id}` | `clients.manage` | Update contact details, notes, preferences or status |
| `GET` | `/clients/{id}/measurements` | `measurements.view` | Profiles with version counts and latest version |
| `POST` | `/clients/{id}/measurements` | `measurements.manage` | Create a profile (`profileName`, `templateId`) |
| `GET` | `/measurement-templates` | `measurements.view` | Templates with fields |
| `POST` | `/measurement-templates` | `measurements.manage` | Create a template |
| `PATCH` | `/measurement-templates/{id}` | `measurements.manage` | Update a template; field changes bump `schemaVersion` |
| `GET` | `/measurement-profiles/{id}` | `measurements.view` | Profile plus version history |
| `POST` | `/measurement-profiles/{id}/versions` | `measurements.manage` | Record a version (`values`, `unit?`, `notes?`, `fitNotes?`, `takenAt?`) |
| `GET` | `/measurement-versions/{id}` | `measurements.view` | One version with values and template snapshot |

Customer list responses put pagination in `data` (`page`, `perPage`, `total`, `totalPages`) so one call returns everything the list needs; the envelope's `meta` stays for future cross-cutting metadata.

Captured values are validated against the template: required fields must be present, keys must belong to the template, and each value must match `0–9999.99` with at most two decimals.

## 7. Frontend

- **Clients** (`views/ClientsView.tsx`): search form, status filter, paginated table, inline create panel. Opening a customer swaps to the detail view; returning reloads the list.
- **Customer detail** (`views/ClientDetailView.tsx`): profile and preferences (editable through the shared `ClientForm`), measurement profiles, a capture form generated from the template's fields, version history, and a version view that compares against the previous version.
- **Templates** (`views/TemplatesView.tsx`): list and editor with repeatable field rows (label, hint, required), unit and status.
- `ClientForm` is shared between create and edit so the two cannot drift.
- Navigation is permission-aware (`clients.view`, `measurements.view`); this is presentation only, and the server re-checks every request.
- `dev/mock.ts` now emulates the Phase 2 endpoints for `npm run dev`: templates, customers, profiles, immutable versions, duplicate detection and tenant scoping.

## 8. Acceptance criteria

| Criterion (from the roadmap) | Status |
|---|---|
| A designer can create a customer and multiple measurement profiles | Implemented: the designer role holds `clients.manage` and `measurements.manage`, profiles are unlimited per customer. **Not exercised against WordPress.** |
| Measurement versions remain immutable | Enforced by construction (no update/delete path, unique version number, snapshot per version). Covered by `tests/php/ClientMeasurementTest.php`. **Test not executed here.** |
| An order can later reference a specific measurement version | Version ids are stable and exposed read-only; `fos_order_items.measurement_version_id` arrives with orders in Phase 3. |

## 9. Verification

Executed in this environment:

- `npm run typecheck` — passes (strict TypeScript).
- `npm run lint` — passes.
- `npm run format:check` — passes.
- `npm test` — 6 API-client tests pass, including customer query building and version creation.
- `npm run build` — succeeds; manifest and hashed assets emitted.
- PHP syntax check across every PHP file — passes (parser-based, not execution).

Not executable here, therefore unverified:

- Migration `0002` against MySQL/MariaDB: `dbDelta()` output, indexes, the role-permission re-seed.
- Every Phase 2 REST route, including permission callbacks and error envelopes.
- `tests/php/ClientMeasurementTest.php` and the Phase 1 suite.
- PHP_CodeSniffer / WordPress Coding Standards (`composer lint:php`).

## 10. Known limitations

- **No file uploads.** Customer photos, references and measurement diagrams are deferred with an explicit plan rather than shipped half-secured.
- **Duplicate detection is email-based.** Phone numbers are free text (formats vary), so matching on them would produce false positives; name-only customers are not compared.
- **No unit conversion.** A version records the unit it was captured in; switching a template between `cm` and `in` does not convert existing values, and nothing presents a converted equivalent.
- **Version numbering is read-then-insert.** Concurrent captures for the same profile can collide; the unique key rejects the second write and the API returns `measurement_version_create_failed` rather than producing a duplicate number. A retry succeeds.
- **Search is `LIKE`-based** over four columns. It is adequate for a studio's customer list and will need a different index or search strategy at much larger scale.
- **Profiles cannot be archived yet** (schema and enforcement exist; no endpoint or UI).
- **No exports or printing.** Measurement sheets and PDFs arrive with quotations in Phase 3.

## 11. Follow-up review

After the first pass, the customer and measurement code was re-read with the tests in mind. Changes made:

| Finding | Change |
|---|---|
| `GET /clients/{id}` reported `profileCount: 0` for every customer: the list query selected the count as a subquery, the single-record query did not, and the presenter defaulted the missing column to zero. | `ClientRepository::find()` now selects the same `profile_count` subquery. Covered by `test_customer_detail_reports_real_profile_count`, which checks both the detail and list payloads. |
| The list count query carried the page query's `SELECT` list and `ORDER BY`, so pagination offsets were computed over an ordered, joined result for no reason. | Count and page queries now share one `FROM … WHERE` string; only the page query selects rows and orders them. |
| `ClientRepository::reference_exists()` was left over from a reference-number scheme that was replaced by deriving the reference from the row id. | Removed; nothing called it. |
| `MeasurementPresenter::version()` assigned `notes` and `fitNotes` that `version_summary()` had already set. | Removed the duplicate assignments. |
| `MeasurementService::normalise_notes()` took an error code that was the same at both call sites, and `template()` was public although only the service used it. | Dropped the parameter; made the helper private. |
| Updating a template with a 61-character garment category returned “Name the garment category”, the message for a missing one. | Split the validation and added `measurement_template_category_too_long`. |
| Template updates recorded `fields: unchanged` in the audit metadata when only the name changed. | The audit entry now records the list of changed columns. |
| The preference validator accepted a non-string `preferredContact` and coerced it to “no preference”. | Non-string values are rejected with `client_preferences_invalid`. |
| `TemplatesView` tracked `mode` (`list`/`create`/`edit`) and `editing` separately, so the two could disagree. | One `editor` state (`null` for the list, `{}` for a new template, `{ template }` for an edit). |
| Returning from a customer detail view triggered two list fetches: `onBack` reloaded explicitly and the effect reloaded when the selection cleared. | `onBack` only clears the selection. |
| `Permissions::labels()` was unused (the studio keeps its display labels in `OverviewView`), and Phase 2 added four entries to it. | Removed. The permission keys themselves and `Permissions::all()` stay. |

## 12. Next

Phase 3 (designs, quotations and orders) builds on these records: designs reference customers and measurement versions, and order garments reference one measurement version each so a fitting change is always a new version, never an edit.
