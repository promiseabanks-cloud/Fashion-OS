# Database Schema

> Extracted from Section 8 of the [Master Technical Specification](../FashionOS_Master_Technical_Specification.md). That document remains the authoritative source.

Use custom MySQL/MariaDB tables for FashionOS business data. Use WordPress native tables for user identities, authentication and suitable WordPress infrastructure.

Use the configured WordPress database prefix. Never hardcode `wp_`.

Example: `$wpdb->prefix . 'fos_orders'`.

Use `dbDelta()` or an equivalent carefully tested migration process for table installation and schema updates.

## Database conventions

- Primary keys: `BIGINT UNSIGNED AUTO_INCREMENT`.
- Tenant foreign references: `tenant_id BIGINT UNSIGNED NOT NULL`.
- Public identifiers: UUIDs or secure non-sequential identifiers where appropriate.
- Financial values: `DECIMAL(14,2)`.
- Measurements: `DECIMAL(10,2)` with explicit units.
- Timestamps: UTC storage, tenant timezone for display.
- Index common tenant filters, status fields, dates and reference columns.
- Use transactions for operations that must succeed or fail together.
- Do not assume all hosting database engines enforce foreign keys identically; test the chosen engine and define integrity safeguards.
- Use soft deletion only where recovery or retention needs justify it.
- Preserve historical records where financial or operational traceability requires them.

## Platform and tenant tables

| Table | Important fields | Purpose |
|---|---|---|
| `fos_tenants` | id, uuid, name, slug, status, timezone, currency, logo_id, created_at | Business workspaces |
| `fos_memberships` | id, tenant_id, user_id, role_id, status | User-to-tenant membership |
| `fos_roles` | id, tenant_id, name, permissions_json | Tenant-specific roles |
| `fos_plans` | id, name, limits_json, features_json, status | Subscription plans |
| `fos_subscriptions` | id, tenant_id, plan_id, status, starts_at, ends_at | Subscription state |
| `fos_settings` | id, tenant_id, setting_key, setting_value | Tenant configuration |

## Customer and measurement tables

| Table | Important fields | Purpose |
|---|---|---|
| `fos_clients` | id, tenant_id, uuid, name, email, phone, notes, status | Customer profiles |
| `fos_client_preferences` | id, tenant_id, client_id, preferences_json | Style and communication preferences |
| `fos_measurement_templates` | id, tenant_id, name, garment_category, schema_json, status | Configurable templates |
| `fos_measurement_profiles` | id, tenant_id, client_id, profile_name, garment_category, status | Customer measurement profiles |
| `fos_measurement_versions` | id, tenant_id, profile_id, version_number, taken_at, taken_by, notes | Historical snapshots |
| `fos_measurement_values` | id, tenant_id, version_id, measurement_key, value, unit | Individual measurements |
| `fos_client_files` | id, tenant_id, client_id, file_id, file_type, access_level | Customer files |

Measurement template definitions must be versioned or snapshotted sufficiently to interpret historical measurement values correctly if a template changes.

## Design and order tables

| Table | Important fields | Purpose |
|---|---|---|
| `fos_designs` | id, tenant_id, client_id, title, category, notes, status | Design records |
| `fos_design_references` | id, tenant_id, design_id, file_id, reference_type, notes | Reference material |
| `fos_quotations` | id, tenant_id, client_id, quotation_number, subtotal, discount, total, status, expires_at | Commercial offers |
| `fos_quotation_items` | id, tenant_id, quotation_id, description, quantity, unit_price, total | Quotation lines |
| `fos_orders` | id, tenant_id, client_id, order_number, quotation_id, status, promised_date, total | Accepted orders |
| `fos_order_items` | id, tenant_id, order_id, design_id, measurement_version_id, garment_name, category, quantity, status | Individual garments |
| `fos_order_events` | id, tenant_id, order_id, event_type, description, actor_id, created_at | Order activity |

A garment/order item must be independently trackable. Do not assume one order equals one garment.

## Production and inventory tables

| Table | Important fields | Purpose |
|---|---|---|
| `fos_production_stages` | id, tenant_id, name, sequence, is_active | Configurable workflow stages |
| `fos_production_jobs` | id, tenant_id, order_item_id, assigned_to, status, start_date, due_date | Garment production |
| `fos_production_tasks` | id, tenant_id, job_id, stage_id, assigned_to, status, completed_at | Production tasks |
| `fos_materials` | id, tenant_id, name, material_type, unit, quantity_on_hand, reorder_level | Inventory items |
| `fos_material_allocations` | id, tenant_id, order_item_id, material_id, quantity, source | Garment material allocation |
| `fos_material_movements` | id, tenant_id, material_id, movement_type, quantity, reference_id, created_at | Stock history |
| `fos_fittings` | id, tenant_id, order_item_id, scheduled_at, status, notes | Fitting records |
| `fos_alterations` | id, tenant_id, order_item_id, fitting_id, description, status, completed_at | Alteration records |
| `fos_quality_checks` | id, tenant_id, order_item_id, checked_by, result, notes, checked_at | Quality-control records |

Material source values: `customer_supplied`, `designer_supplied`, `shared_supply`.

## Finance tables

| Table | Important fields | Purpose |
|---|---|---|
| `fos_invoices` | id, tenant_id, client_id, order_id, reference, currency, subtotal, total, amount_paid, balance, issue_date, due_date, status | Invoices |
| `fos_invoice_items` | id, tenant_id, invoice_id, description, quantity, unit_price, line_total | Invoice lines |
| `fos_payments` | id, tenant_id, invoice_id, amount, method, provider_reference, status, idempotency_key, paid_at, verified_by, verified_at | Payment records |
| `fos_expenses` | id, tenant_id, category, description, amount, currency, spent_at, status | Studio expenses |
| `fos_financial_events` | id, tenant_id, event_type, reference_type, reference_id, amount | Financial activity (planned) |

Expense categories are a fixed list in `ExpenseService` (`materials`, `rent`, `salaries`, `utilities`, `transport`, `marketing`, `equipment`, `other`) rather than a table, so there is no category editor yet. Payment statuses are `pending`, `verified` and `rejected`; `(tenant_id, idempotency_key)` is unique. `amount_paid` and `balance` on an invoice are recomputed from the lines and verified payments whenever either changes.

Design payment allocation carefully if a payment may cover multiple invoices or an invoice may receive multiple payments. Do not hardwire a one-payment-to-one-invoice assumption if the product requirements evolve to support more complex reconciliation. Model allocations explicitly when needed.

## Automation and system tables

| Table | Important fields | Purpose |
|---|---|---|
| `fos_notifications` | id, tenant_id, recipient_id, channel, message, status, scheduled_at | Notifications |
| `fos_notification_logs` | id, tenant_id, notification_id, result, sent_at | Delivery attempts |
| `fos_automation_rules` | id, tenant_id, name, trigger_event, conditions_json, actions_json, status | Workflow rules |
| `fos_jobs` | id, tenant_id, job_type, payload_json, status, attempts, available_at, locked_at | Background queue |
| `fos_events` | id, tenant_id, event_type, entity_type, entity_id, payload_json, created_at | Business events |
| `fos_audit_logs` | id, tenant_id, actor_id, action, entity_type, entity_id, metadata_json, created_at | Audit trail |
| `fos_idempotency_keys` | id, tenant_id, idempotency_key, request_hash, response_json, created_at | Duplicate-operation protection |
| `fos_api_connections` | id, tenant_id, connection_name, public_id, secret_hash, status | External integrations |

The exact columns, indexes, nullability and constraints must be defined in migration files before module implementation. The tables above are the logical schema inventory, not permission to create vague or incomplete tables.

---

## Tenant isolation (data-layer rules)

Every tenant-owned record carries a `tenant_id`, and every tenant-owned query, update, delete, file download, background job, report and export is scoped by it. See [`SECURITY.md`](SECURITY.md) for the full isolation and security rules, and Section 7 of the Master Technical Specification.

---

## Implemented tables (migration `0001`)

> Created by `FashionOS\Database\Migrations\Migration0001Tenancy`. Column names are the actual schema; later phases add their own tables through new migrations.

### `{prefix}fos_tenants`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key, auto-increment |
| `uuid` | `CHAR(36)` | Public identifier, unique |
| `name` | `VARCHAR(191)` | Display name, max 120 characters in the API |
| `slug` | `VARCHAR(100)` | Unique, generated from the name |
| `status` | `VARCHAR(20)` | `active` by default; inactive workspaces grant no permissions |
| `timezone` | `VARCHAR(64)` | IANA identifier, validated on write |
| `currency` | `CHAR(3)` | `NGN` by default; supported list lives in `TenantService::CURRENCIES` |
| `logo_id` | `BIGINT UNSIGNED` | Reserved; `0` when unset |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, unique `uuid`, unique `slug`, `status`.

### `{prefix}fos_roles`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id` | `BIGINT UNSIGNED` | Owning workspace |
| `slug` | `VARCHAR(50)` | `owner`, `manager`, `designer`, `tailor`, `finance` |
| `name` | `VARCHAR(100)` | Display name |
| `permissions_json` | `LONGTEXT` | JSON array of permission keys; `["*"]` for the owner |
| `is_system` | `TINYINT(1)` | `1` for the seeded roles |
| `created_at` | `DATETIME` | UTC |

Indexes: unique `(tenant_id, slug)`, `tenant_id`.

### `{prefix}fos_memberships`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id` | `BIGINT UNSIGNED` | Workspace |
| `user_id` | `BIGINT UNSIGNED` | WordPress user |
| `role_id` | `BIGINT UNSIGNED` | Role inside the same workspace |
| `status` | `VARCHAR(20)` | `active` |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: unique `(tenant_id, user_id)`, `user_id`, `(tenant_id, status)`.

### `{prefix}fos_audit_logs`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id` | `BIGINT UNSIGNED` | `0` for platform administration |
| `actor_id` | `BIGINT UNSIGNED` | WordPress user, `0` when not applicable |
| `action` | `VARCHAR(100)` | `tenant.created`, `membership.role_changed`, `platform.tenants_viewed`, … |
| `entity_type` | `VARCHAR(50)` | `tenant`, `membership`, `platform` |
| `entity_id` | `BIGINT UNSIGNED` | Affected record |
| `metadata_json` | `LONGTEXT` | Non-sensitive context, JSON object |
| `created_at` | `DATETIME` | UTC |

Indexes: `(tenant_id, created_at)`, `actor_id`, `action`.

No foreign keys are declared. Tenant ownership is enforced in every repository query, because the specification warns that hosting database engines may not enforce constraints identically.

## Implemented tables (migration `0002`)

> Created by `FashionOS\Database\Migrations\Migration0002ClientsMeasurements`. The same migration re-seeds the `is_system` role rows from `Roles::defaults()` so workspaces created in Phase 1 gain the customer and measurement permissions.

### `{prefix}fos_clients`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id` | `BIGINT UNSIGNED` | Owning workspace |
| `uuid` | `CHAR(36)` | Public identifier, unique |
| `reference` | `VARCHAR(30)` | Per-workspace reference such as `C-0001`, unique with `tenant_id` |
| `name` | `VARCHAR(191)` | Required |
| `email` | `VARCHAR(191)` | Lower-cased; used for duplicate detection among active customers |
| `phone` | `VARCHAR(40)` | Free text |
| `notes` | `LONGTEXT` | Internal only; never shown in a customer-facing view |
| `status` | `VARCHAR(20)` | `active` or `archived`; archiving keeps the record and its history |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, unique `uuid`, unique `(tenant_id, reference)`, `(tenant_id, status, name)`, `(tenant_id, email)`, `(tenant_id, phone)`.

### `{prefix}fos_client_preferences`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id`, `client_id` | `BIGINT UNSIGNED` | Owning workspace and customer |
| `preferences_json` | `LONGTEXT` | `{ preferredContact, styleNotes, fitNotes }` |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, unique `(tenant_id, client_id)`.

### `{prefix}fos_measurement_templates`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id` | `BIGINT UNSIGNED` | Owning workspace |
| `uuid` | `CHAR(36)` | Public identifier, unique |
| `name` | `VARCHAR(120)` | Display name |
| `garment_category` | `VARCHAR(60)` | What the template measures |
| `unit` | `CHAR(2)` | `cm` or `in`, the default capture unit |
| `schema_json` | `LONGTEXT` | `{ "fields": [{ key, label, hint, required }] }` |
| `schema_version` | `INT UNSIGNED` | Starts at `1`; incremented when fields change |
| `status` | `VARCHAR(20)` | `active` or `archived` |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, unique `uuid`, `(tenant_id, status)`, `(tenant_id, garment_category)`.

### `{prefix}fos_measurement_profiles`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id`, `client_id` | `BIGINT UNSIGNED` | Owning workspace and customer |
| `template_id` | `BIGINT UNSIGNED` | Template the profile measures against |
| `uuid` | `CHAR(36)` | Public identifier, unique |
| `profile_name` | `VARCHAR(120)` | E.g. “Wedding suit” |
| `garment_category` | `VARCHAR(60)` | Copied from the template at creation |
| `status` | `VARCHAR(20)` | `active` or `archived` |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, unique `uuid`, `(tenant_id, client_id)`, `(tenant_id, template_id)`, `(tenant_id, status)`.

### `{prefix}fos_measurement_versions`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key; stable reference for future order garments |
| `tenant_id`, `profile_id` | `BIGINT UNSIGNED` | Owning workspace and profile |
| `version_number` | `INT UNSIGNED` | Starts at `1`; unique with `(tenant_id, profile_id)` |
| `template_id`, `template_version` | `BIGINT UNSIGNED`, `INT UNSIGNED` | Template identity at capture time |
| `template_snapshot_json` | `LONGTEXT` | Full field definitions, so history stays interpretable after template edits |
| `unit` | `CHAR(2)` | Unit used for this capture |
| `taken_at` | `DATETIME` | When the measurements were taken |
| `taken_by` | `BIGINT UNSIGNED` | WordPress user id |
| `notes`, `fit_notes` | `TEXT` | Free text |
| `created_at` | `DATETIME` | UTC |

Indexes: primary key, unique `(tenant_id, profile_id, version_number)`, `(tenant_id, taken_at)`, `taken_by`. This table is append-only: no repository, service or route updates or deletes a row.

### `{prefix}fos_measurement_values`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id`, `version_id` | `BIGINT UNSIGNED` | Owning workspace and version |
| `measurement_key` | `VARCHAR(60)` | Template field key |
| `label` | `VARCHAR(120)` | Label at capture time |
| `value` | `DECIMAL(10,2)` | Value in `unit` |
| `unit` | `CHAR(2)` | `cm` or `in` |
| `sort_order` | `INT UNSIGNED` | Template order |

Indexes: primary key, unique `(tenant_id, version_id, measurement_key)`, `(tenant_id, version_id)`.

`fos_client_files` is intentionally **not** created yet: private uploads are deferred to the portal/design phase (see [PHASE_2_PLAN.md](PHASE_2_PLAN.md)).

## Implemented tables (migration `0003`)

> Created by `FashionOS\Database\Migrations\Migration0003QuotationsOrders`. The same migration re-seeds the `is_system` role rows from `Roles::defaults()` so existing workspaces gain the design, quotation and order permissions. Optional dates are `CHAR(10)` in `YYYY-MM-DD` form with `''` for “not set”, avoiding empty-string writes to `DATE` columns under MySQL strict mode.

### `{prefix}fos_designs`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id` | `BIGINT UNSIGNED` | Owning workspace |
| `uuid` | `CHAR(36)` | Public identifier, unique |
| `reference` | `VARCHAR(30)` | Per-workspace reference such as `D-0001`, unique with `tenant_id` |
| `client_id` | `BIGINT UNSIGNED` | Customer the design is for; never editable |
| `measurement_profile_id` | `BIGINT UNSIGNED` | Optional profile the design was drafted against |
| `measurement_version_id` | `BIGINT UNSIGNED` | Exact measurement version; append-only, so it cannot drift |
| `title` | `VARCHAR(120)` | Required |
| `garment_category` | `VARCHAR(60)` | Optional, defaults from the referenced profile |
| `brief` | `TEXT` | Internal design brief |
| `status` | `VARCHAR(20)` | `draft`, `approved` or `archived` |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, unique `uuid`, unique `(tenant_id, reference)`, `(tenant_id, client_id)`, `(tenant_id, status)`, `(tenant_id, measurement_version_id)`.

### `{prefix}fos_quotations`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id` | `BIGINT UNSIGNED` | Owning workspace |
| `uuid` | `CHAR(36)` | Public identifier, unique |
| `reference` | `VARCHAR(30)` | Per-workspace reference such as `Q-0001`, unique with `tenant_id` |
| `client_id`, `design_id` | `BIGINT UNSIGNED` | Customer and optional design |
| `currency` | `CHAR(3)` | Copied from the workspace at creation |
| `status` | `VARCHAR(20)` | `draft`, `sent`, `accepted`, `declined` or `expired` |
| `valid_until` | `CHAR(10)` | Optional `YYYY-MM-DD` |
| `notes` | `TEXT` | Customer-facing offer notes |
| `subtotal`, `total` | `DECIMAL(12,2)` | Recomputed from the lines on every write |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, unique `uuid`, unique `(tenant_id, reference)`, `(tenant_id, client_id)`, `(tenant_id, status)`.

### `{prefix}fos_quotation_items`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id`, `quotation_id` | `BIGINT UNSIGNED` | Owning workspace and quotation |
| `description` | `VARCHAR(191)` | Required |
| `quantity` | `INT UNSIGNED` | `1–9999` |
| `unit_price`, `line_total` | `DECIMAL(12,2)` | `line_total = quantity × unit_price` |
| `sort_order` | `INT UNSIGNED` | Display order |

Indexes: primary key, `(tenant_id, quotation_id)`.

### `{prefix}fos_orders`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id` | `BIGINT UNSIGNED` | Owning workspace |
| `uuid` | `CHAR(36)` | Public identifier, unique |
| `reference` | `VARCHAR(30)` | Per-workspace reference such as `O-0001`, unique with `tenant_id` |
| `client_id` | `BIGINT UNSIGNED` | Customer |
| `quotation_id` | `BIGINT UNSIGNED` | Provenance only; the quotation keeps its own status |
| `currency` | `CHAR(3)` | Quotation currency, or the workspace currency for a direct order |
| `status` | `VARCHAR(20)` | `draft`, `confirmed`, `in_progress`, `ready`, `delivered` or `cancelled` |
| `due_date` | `CHAR(10)` | Optional `YYYY-MM-DD` |
| `notes` | `TEXT` | Internal order notes |
| `total` | `DECIMAL(12,2)` | Sum of garment line totals at creation |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, unique `uuid`, unique `(tenant_id, reference)`, `(tenant_id, client_id)`, `(tenant_id, status)`, `(tenant_id, due_date)`.

### `{prefix}fos_order_items`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key; the unit of production |
| `tenant_id`, `order_id` | `BIGINT UNSIGNED` | Owning workspace and order |
| `quotation_item_id` | `BIGINT UNSIGNED` | Source line when copied from a quotation |
| `design_id` | `BIGINT UNSIGNED` | Optional design reference |
| `measurement_profile_id`, `measurement_version_id` | `BIGINT UNSIGNED` | The exact measurements the garment is cut from |
| `garment_name` | `VARCHAR(120)` | Required; e.g. “Wedding jacket” |
| `garment_category` | `VARCHAR(60)` | Copied from the design when one is cited |
| `quantity` | `INT UNSIGNED` | `1–9999` |
| `unit_price` | `DECIMAL(12,2)` | Copied from the quotation line or supplied |
| `status` | `VARCHAR(20)` | `pending`, `cutting`, `sewing`, `fitting`, `ready`, `delivered` or `cancelled` |
| `notes` | `TEXT` | Garment-specific notes |
| `sort_order` | `INT UNSIGNED` | Display order |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, `(tenant_id, order_id)`, `(tenant_id, measurement_version_id)`, `(tenant_id, status)`. References are fixed at creation; only status and notes change afterwards.

### `{prefix}fos_order_events`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id`, `order_id` | `BIGINT UNSIGNED` | Owning workspace and order |
| `order_item_id` | `BIGINT UNSIGNED` | Garment the event concerns, or `0` for the order |
| `event_type` | `VARCHAR(40)` | `created`, `item_added`, `status`, `item_status`, `item_updated` or `note` |
| `message` | `TEXT` | Human-readable entry shown in the order timeline |
| `actor_id` | `BIGINT UNSIGNED` | WordPress user id |
| `created_at` | `DATETIME` | UTC |

Indexes: primary key, `(tenant_id, order_id, id)`.

## Phase 4 — Production and inventory

Migration `0004` adds nine tables. Optional dates are stored as `CHAR(10)` and optional completion stamps as `CHAR(19)`, both defaulting to `''` rather than `NULL`, so a strict-mode insert never fails on an omitted date. There are still no foreign keys; references are validated in the services and every query starts with `tenant_id`.

### `{prefix}fos_production_stages`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id` | `BIGINT UNSIGNED` | Owning workspace |
| `name` | `VARCHAR(80)` | Stage label shown in the studio |
| `slug` | `VARCHAR(80)` | Derived from the name; unique per workspace |
| `sequence` | `INT UNSIGNED` | Workflow order (default 0) |
| `is_active` | `TINYINT(1)` | Inactive stages stay in history but accept no new tasks |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, unique `(tenant_id, slug)`, `(tenant_id, is_active, sequence)`.

### `{prefix}fos_production_jobs`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id`, `order_item_id` | `BIGINT UNSIGNED` | Workspace and the one garment the job tracks |
| `assigned_to` | `BIGINT UNSIGNED` | WordPress user id, or 0 |
| `status` | `VARCHAR(20)` | `queued`, `in_progress`, `blocked`, `completed`, `cancelled` |
| `start_date`, `due_date` | `CHAR(10)` | `YYYY-MM-DD` or `''` |
| `notes` | `TEXT NULL` | Internal notes |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id, or 0 |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, unique `(tenant_id, order_item_id)` — one job per garment — plus `(tenant_id, status)`, `(tenant_id, assigned_to)` and `(tenant_id, due_date)`.

### `{prefix}fos_production_tasks`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id`, `job_id`, `stage_id` | `BIGINT UNSIGNED` | Workspace, job and workflow stage |
| `assigned_to` | `BIGINT UNSIGNED` | WordPress user id, or 0 |
| `status` | `VARCHAR(20)` | `pending`, `in_progress`, `done`, `cancelled` |
| `due_date` | `CHAR(10)` | `YYYY-MM-DD` or `''` |
| `completed_at` | `CHAR(19)` | `YYYY-MM-DD HH:MM:SS` or `''` |
| `notes` | `TEXT NULL` | Task notes |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, `(tenant_id, job_id)`, `(tenant_id, stage_id)`, `(tenant_id, assigned_to)`, `(tenant_id, status)`.

### `{prefix}fos_materials`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id` | `BIGINT UNSIGNED` | Owning workspace |
| `name` | `VARCHAR(120)` | Material name |
| `material_type` | `VARCHAR(30)` | `fabric`, `accessory` or `consumable` |
| `unit` | `VARCHAR(20)` | `metre`, `yard`, `piece`, `roll`, `spool`, `kilogram` or `litre` |
| `quantity_on_hand` | `DECIMAL(12,2)` | Current stock; never negative |
| `reorder_level` | `DECIMAL(12,2)` | Stock at or below this level is flagged low |
| `unit_cost` | `DECIMAL(12,2)` | Reference cost |
| `notes` | `TEXT NULL` | Internal notes |
| `is_active` | `TINYINT(1)` | Archived materials accept no movements |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id, or 0 |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, `(tenant_id, material_type)`, `(tenant_id, is_active)`.

### `{prefix}fos_material_allocations`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id`, `order_item_id`, `material_id` | `BIGINT UNSIGNED` | Workspace, garment and material |
| `quantity` | `DECIMAL(12,2)` | Allocated amount |
| `source` | `VARCHAR(20)` | `customer_supplied`, `designer_supplied` or `shared_supply` |
| `notes` | `TEXT NULL` | Allocation notes |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id, or 0 |
| `created_at` | `DATETIME` | UTC |

Indexes: primary key, `(tenant_id, order_item_id)`, `(tenant_id, material_id)`. Releasing an allocation deletes it and, for designer-supplied stock, returns the quantity through a `return` movement.

### `{prefix}fos_material_movements`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id`, `material_id` | `BIGINT UNSIGNED` | Workspace and material |
| `movement_type` | `VARCHAR(20)` | `in`, `out`, `adjustment`, plus internal `allocation` and `return` |
| `quantity` | `DECIMAL(12,2)` | Signed change applied to stock |
| `reference_type`, `reference_id` | `VARCHAR(20)`, `BIGINT UNSIGNED` | Allocation or garment the movement belongs to |
| `note` | `VARCHAR(191)` | Short reason |
| `actor_id` | `BIGINT UNSIGNED` | WordPress user id, or 0 |
| `created_at` | `DATETIME` | UTC |

Indexes: primary key, `(tenant_id, material_id, id)`. The ledger is append-only.

### `{prefix}fos_fittings`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id`, `order_item_id` | `BIGINT UNSIGNED` | Workspace and garment |
| `assigned_to` | `BIGINT UNSIGNED` | WordPress user id, or 0 |
| `scheduled_at` | `DATETIME` | UTC |
| `status` | `VARCHAR(20)` | `scheduled`, `completed`, `missed`, `cancelled` |
| `location` | `VARCHAR(191)` | Place, or `''` |
| `notes` | `TEXT NULL` | Booking notes |
| `outcome` | `TEXT NULL` | What was decided at the fitting |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id, or 0 |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, `(tenant_id, order_item_id)`, `(tenant_id, scheduled_at)`, `(tenant_id, status)`.

### `{prefix}fos_alterations`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id`, `order_item_id` | `BIGINT UNSIGNED` | Workspace and garment |
| `fitting_id` | `BIGINT UNSIGNED` | Source fitting, or 0 |
| `description` | `TEXT` | Required work description |
| `status` | `VARCHAR(20)` | `open`, `in_progress`, `completed`, `cancelled` |
| `due_date` | `CHAR(10)` | `YYYY-MM-DD` or `''` |
| `completed_at` | `CHAR(19)` | `YYYY-MM-DD HH:MM:SS` or `''` |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id, or 0 |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, `(tenant_id, order_item_id)`, `(tenant_id, fitting_id)`, `(tenant_id, status)`.

### `{prefix}fos_quality_checks`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id`, `order_item_id` | `BIGINT UNSIGNED` | Workspace and garment |
| `checked_by` | `BIGINT UNSIGNED` | WordPress user id, or 0 |
| `result` | `VARCHAR(20)` | `passed`, `failed` or `needs_rework` |
| `notes` | `TEXT NULL` | Defect notes |
| `checked_at` | `DATETIME` | UTC |
| `created_at` | `DATETIME` | UTC |

Indexes: primary key, `(tenant_id, order_item_id, id)`, `(tenant_id, result)`. Checks are append-only; a failed or rework result also appends an order timeline event.

Migration `0004` also seeds the nine starter stages for every existing workspace and re-seeds the system roles with the eight new `production.*`, `materials.*`, `fittings.*` and `quality.*` permissions.

## Phase 5 — Finance

Migration `0005` adds four tables. Money is `DECIMAL(14,2)`; optional dates use `CHAR(10)` and optional timestamps `CHAR(19)`, both defaulting to `''` rather than `NULL`.

### `{prefix}fos_invoices`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id` | `BIGINT UNSIGNED` | Workspace |
| `uuid` | `VARCHAR(36)` | Unique public identifier |
| `reference` | `VARCHAR(20)` | `I-0001`, unique per workspace |
| `client_id` | `BIGINT UNSIGNED` | Customer |
| `order_id` | `BIGINT UNSIGNED` | Source order, or 0 |
| `currency` | `VARCHAR(3)` | Copied from the workspace at creation |
| `status` | `VARCHAR(20)` | `draft`, `issued`, `partially_paid`, `paid`, `void` |
| `issue_date`, `due_date` | `CHAR(10)` | `YYYY-MM-DD` or `''` |
| `subtotal`, `total` | `DECIMAL(14,2)` | Computed from the lines |
| `amount_paid` | `DECIMAL(14,2)` | Sum of verified payments |
| `balance` | `DECIMAL(14,2)` | `total - amount_paid` |
| `notes` | `TEXT NULL` | Customer-facing notes |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id, or 0 |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, unique `uuid`, unique `(tenant_id, reference)`, `(tenant_id, client_id)`, `(tenant_id, status)`, `(tenant_id, due_date)`, `(tenant_id, order_id)`.

### `{prefix}fos_invoice_items`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id`, `invoice_id` | `BIGINT UNSIGNED` | Workspace and invoice |
| `description` | `VARCHAR(191)` | Line description |
| `quantity` | `INT UNSIGNED` | Whole units, 1–9999 |
| `unit_price`, `line_total` | `DECIMAL(14,2)` | Server-computed |
| `sort_order` | `INT UNSIGNED` | Display order |

Index: `(tenant_id, invoice_id)`.

### `{prefix}fos_payments`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id` | `BIGINT UNSIGNED` | Workspace |
| `uuid` | `VARCHAR(36)` | Unique public identifier |
| `reference` | `VARCHAR(20)` | `P-0001`, unique per workspace |
| `invoice_id`, `client_id` | `BIGINT UNSIGNED` | Invoice and its customer |
| `amount` | `DECIMAL(14,2)` | Amount received |
| `method` | `VARCHAR(20)` | `cash`, `transfer`, `card`, `pos` |
| `provider`, `provider_reference` | `VARCHAR(40)` / `VARCHAR(191)` | Bank or POS reconciliation, or `''` |
| `status` | `VARCHAR(20)` | `pending`, `verified`, `rejected` |
| `idempotency_key` | `VARCHAR(191)` | Unique per workspace; generated when omitted |
| `paid_at` | `DATETIME` | When the money was received |
| `verified_by`, `verified_at` | `BIGINT UNSIGNED` / `CHAR(19)` | Verification stamp |
| `notes` | `TEXT NULL` | Free text |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id, or 0 |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, unique `uuid`, unique `(tenant_id, reference)`, unique `(tenant_id, idempotency_key)`, `(tenant_id, invoice_id, id)`, `(tenant_id, status)`. The idempotency index is what makes a repeated payment callback return the original row instead of recording the money twice.

### `{prefix}fos_expenses`

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `tenant_id` | `BIGINT UNSIGNED` | Workspace |
| `uuid` | `VARCHAR(36)` | Unique public identifier |
| `reference` | `VARCHAR(20)` | `E-0001`, unique per workspace |
| `category` | `VARCHAR(30)` | `materials`, `rent`, `salaries`, `utilities`, `transport`, `marketing`, `equipment`, `other` |
| `description` | `VARCHAR(191)` | What was bought |
| `amount` | `DECIMAL(14,2)` | Amount spent |
| `currency` | `VARCHAR(3)` | Copied from the workspace at creation |
| `spent_at` | `CHAR(10)` | `YYYY-MM-DD` |
| `status` | `VARCHAR(20)` | `recorded`, `void` |
| `notes` | `TEXT NULL` | Free text |
| `created_by` | `BIGINT UNSIGNED` | WordPress user id, or 0 |
| `created_at`, `updated_at` | `DATETIME` | UTC |

Indexes: primary key, unique `uuid`, unique `(tenant_id, reference)`, `(tenant_id, category)`, `(tenant_id, spent_at)`. Voided expenses keep their row and drop out of every total.

Migration `0005` also re-seeds the system roles with the six new `invoices.*`, `payments.*` and `expenses.*` permissions.
