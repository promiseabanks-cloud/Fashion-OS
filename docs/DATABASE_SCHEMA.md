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
| `fos_invoices` | id, tenant_id, client_id, order_id, invoice_number, subtotal, total, due_date, status | Invoices |
| `fos_invoice_items` | id, tenant_id, invoice_id, description, quantity, unit_price, total | Invoice lines |
| `fos_payments` | id, tenant_id, invoice_id, amount, payment_method, reference, status, paid_at | Payment records |
| `fos_expense_categories` | id, tenant_id, name | Expense categories |
| `fos_expenses` | id, tenant_id, category_id, description, amount, expense_date | Business expenses |
| `fos_financial_events` | id, tenant_id, event_type, reference_type, reference_id, amount | Financial activity |

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
