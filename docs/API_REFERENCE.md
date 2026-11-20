# API Reference

> Extracted from Section 9 of the [Master Technical Specification](../FashionOS_Master_Technical_Specification.md). That document remains authoritative.

Use the WordPress REST API namespace:

`/wp-json/fashionos/v1/`

## Example route groups

| Module | Example endpoints |
|---|---|
| Auth | `GET /auth/me`, `POST /auth/logout` |
| Tenant | `GET /tenants/current`, `PATCH /tenants/current` |
| Clients | `GET /clients`, `POST /clients`, `GET /clients/{id}` |
| Measurements | `GET /clients/{id}/measurements`, `POST /measurements` |
| Designs | `GET /designs`, `POST /designs` |
| Quotations | `POST /quotations`, `POST /quotations/{id}/send` |
| Orders | `GET /orders`, `POST /orders`, `PATCH /orders/{id}` |
| Production | `GET /production/jobs`, `PATCH /production/tasks/{id}` |
| Inventory | `GET /materials`, `POST /materials` |
| Finance | `GET /invoices`, `POST /invoices`, `GET /payments` |
| Appointments | `GET /appointments`, `POST /appointments` |
| Portal | `GET /portal/orders`, `GET /portal/orders/{id}` |
| Reports | `GET /reports/dashboard` |

## Response structure

**Success:**

```json
{
  "success": true,
  "data": {},
  "meta": {}
}
```

**Error:**

```json
{
  "success": false,
  "error": {
    "code": "RESOURCE_NOT_FOUND",
    "message": "The requested record could not be found."
  }
}
```

Errors raised by permission callbacks are wrapped into the same envelope by `FashionOS\API\Responses\ErrorEnvelope`, so the client never receives two different error shapes from this namespace.

## Implemented in Phase 1

All routes require an authenticated WordPress user. State-changing requests also require the `X-WP-Nonce` header; the nonce guards against CSRF and is not a permission check.

### Selecting a workspace

Send the selected workspace as an id or slug:

```http
X-FashionOS-Workspace: 12
```

The header is a **selector**, not proof of access. The server resolves it against the caller's membership and returns `403 workspace_access_denied` for a workspace they do not belong to. With no header, the user's only workspace is used; if they belong to several, the request fails with `400 workspace_required` and the client shows a chooser.

| Method | Route | Permission | Notes |
|---|---|---|---|
| `GET` | `/auth/me` | signed in | `{ user, workspaces, activeTenant, workspaceError }`; errors resolving a workspace return `activeTenant: null` instead of failing the bootstrap call |
| `GET` | `/tenants` | signed in | `{ workspaces }` |
| `POST` | `/tenants` | signed in | Body `{ name, timezone?, currency? }`; returns the new active workspace with `201` |
| `GET` | `/tenants/current` | `workspace.view` | Active workspace, role, permissions, member count, roles |
| `PATCH` | `/tenants/current` | `workspace.manage` | Body may contain `name`, `timezone`, `currency` |
| `GET` | `/team` | `team.view` | `{ members, roles }` |
| `POST` | `/team` | `team.manage` | Body `{ email, role }`; the person needs an existing WordPress account |
| `PATCH` | `/team/{user_id}` | `team.manage` | Body `{ role }` |
| `DELETE` | `/team/{user_id}` | `team.manage` | Removes the membership, not the WordPress user |
| `GET` | `/audit` | `audit.view` | `?limit=` 1–200, default 50 |
| `GET` | `/platform/tenants` | WordPress `manage_options` | Read-only; each call is audited with `tenant_id = 0` |
| `GET` | `/system/health` | signed in | Plugin, migration and build status |

### Active workspace payload

```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": 12,
      "uuid": "0f3a4a4e-9d1f-4b3d-9c2a-1a2b3c4d5e6f",
      "name": "Adaeze Atelier",
      "slug": "adaeze-atelier",
      "status": "active",
      "timezone": "Africa/Lagos",
      "currency": "NGN",
      "createdAt": "2026-11-01 10:00:00"
    },
    "role": { "slug": "owner", "name": "Owner" },
    "permissions": ["*"],
    "isOwner": true,
    "memberCount": 3
  },
  "meta": {}
}
```

### Error codes

| Code | Status | Meaning |
|---|---|---|
| `not_authenticated` | 401 | No authenticated WordPress user |
| `forbidden` | 403 | Authenticated but the role lacks the permission |
| `workspace_access_denied` | 403 | No active membership in the selected workspace |
| `workspace_required` | 400 | Several workspaces exist and none was selected |
| `workspace_not_found` | 404 | Unknown workspace id or slug |
| `workspace_name_required` | 422 | Empty workspace name |
| `workspace_name_too_long` | 422 | Name over 120 characters |
| `workspace_timezone_invalid` | 422 | Not an IANA timezone identifier |
| `workspace_currency_invalid` | 422 | Currency outside the supported list |
| `workspace_create_failed` | 500 | Insert or membership failure; the transaction is rolled back |
| `workspace_update_failed` | 500 | Update failure |
| `member_email_invalid` | 422 | Missing or malformed email |
| `member_account_required` | 422 | No WordPress account exists for that email |
| `member_already_exists` | 409 | Already a member of this workspace |
| `member_role_invalid` | 422 | Unknown role, or an attempt to assign `owner` |
| `member_not_found` | 404 | Target user is not on this team |
| `owner_role_protected` | 422 | Owner cannot be re-roled or removed |
| `member_create_failed` | 500 | Membership insert failure |
| `member_update_failed` | 500 | Role update failure |
| `member_remove_failed` | 500 | Membership delete failure |

## Implemented in Phase 2

Customer and measurement routes. All require an authenticated user with the active workspace resolved by the same header as Phase 1; state-changing requests also require `X-WP-Nonce`.

| Method | Route | Permission | Notes |
|---|---|---|---|
| `GET` | `/clients` | `clients.view` | `?search=` (name, email, phone or reference), `?status=active\|archived\|all`, `?page=`, `?per_page=` (1–100, default 25) |
| `POST` | `/clients` | `clients.manage` | Body `{ name, email?, phone?, notes?, preferences? }`; returns the customer with `201` |
| `GET` | `/clients/{id}` | `clients.view` | One customer with preferences |
| `PATCH` | `/clients/{id}` | `clients.manage` | Body may contain `name`, `email`, `phone`, `notes`, `status` (`active`/`archived`), `preferences` |
| `GET` | `/clients/{id}/measurements` | `measurements.view` | `{ clientId, profiles }`, each profile with `versionCount` and `latestVersion` |
| `POST` | `/clients/{id}/measurements` | `measurements.manage` | Body `{ profileName, templateId }`; the garment category comes from the template |
| `GET` | `/measurement-templates` | `measurements.view` | `{ templates }` with fields and `schemaVersion` |
| `POST` | `/measurement-templates` | `measurements.manage` | Body `{ name, garmentCategory, unit?, fields }` |
| `PATCH` | `/measurement-templates/{id}` | `measurements.manage` | Body may contain `name`, `garmentCategory`, `unit`, `status`, `fields`; changing fields bumps `schemaVersion` |
| `GET` | `/measurement-profiles/{id}` | `measurements.view` | Profile plus `history` (versions newest first) |
| `POST` | `/measurement-profiles/{id}/versions` | `measurements.manage` | Body `{ values, unit?, notes?, fitNotes?, takenAt? }`; appends an immutable version |
| `GET` | `/measurement-versions/{id}` | `measurements.view` | One version with `values` and its template snapshot |

A template field is `{ key, label, hint, required }`. `key` is generated from `label` when omitted and is what `values` refers to:

```json
{
  "values": { "chest": 96, "waist": "82.5" },
  "unit": "cm",
  "notes": "Baseline before the first fitting.",
  "fitNotes": "Left shoulder sits lower."
}
```

Values must match `0–9999.99` with at most two decimals; required fields must be present; keys that are not in the template are rejected. The unit used is recorded on the version and on every value. **There is no update or delete route for a version**: a correction is a new version.

Customer list pagination is returned inside `data`, alongside the rows:

```json
{
  "success": true,
  "data": {
    "clients": [],
    "page": 1,
    "perPage": 25,
    "total": 0,
    "totalPages": 0
  },
  "meta": {}
}
```

### Phase 2 error codes

| Code | Status | Meaning |
|---|---|---|
| `client_not_found` | 404 | No such customer in the active workspace |
| `client_name_required` | 422 | Empty customer name |
| `client_name_too_long` | 422 | Name over 191 characters |
| `client_email_invalid` | 422 | Malformed email |
| `client_phone_too_long` | 422 | Phone over 40 characters |
| `client_notes_too_long` | 422 | Notes over 5000 characters |
| `client_preferences_invalid` | 422 | Unknown contact channel or over-long preference text |
| `client_status_invalid` | 422 | Status other than `active`/`archived` |
| `client_duplicate` | 409 | Another active customer already uses that email; `details` carries `clientId` and `reference` |
| `client_create_failed` / `client_update_failed` | 500 | Write failure |
| `measurement_template_not_found` | 404 | No such template in the active workspace |
| `measurement_template_required` | 422 | Profile created without a template |
| `measurement_template_archived` | 422 | Template is archived, so new profiles/versions are refused |
| `measurement_template_name_required` / `_too_long` | 422 | Template name missing or over 120 characters |
| `measurement_template_category_required` | 422 | Garment category missing |
| `measurement_template_category_too_long` | 422 | Garment category over 60 characters |
| `measurement_template_fields_required` | 422 | No fields supplied |
| `measurement_template_too_many_fields` | 422 | More than 40 fields |
| `measurement_template_field_invalid` | 422 | Field without a label, or a label/hint that is too long; `details.index` identifies it |
| `measurement_template_field_duplicate` | 422 | Two fields resolve to the same key |
| `measurement_template_status_invalid` | 422 | Status other than `active`/`archived` |
| `measurement_template_create_failed` / `_update_failed` | 500 | Write failure |
| `measurement_profile_not_found` | 404 | No such profile in the active workspace |
| `measurement_profile_name_required` / `_too_long` | 422 | Profile name missing or over 120 characters |
| `measurement_profile_archived` | 422 | Profile is archived, so new versions are refused |
| `measurement_profile_create_failed` | 500 | Profile insert failure |
| `measurement_unit_invalid` | 422 | Unit other than `cm`/`in` |
| `measurement_values_required` | 422 | No values supplied, or every field left blank |
| `measurement_value_required` | 422 | A required field is blank; `details.key` identifies it |
| `measurement_value_invalid` | 422 | Not a number in `0–9999.99` |
| `measurement_value_unknown` | 422 | Key is not in the profile's template |
| `measurement_taken_at_invalid` | 422 | Unparseable timestamp, or more than a day in the future |
| `measurement_notes_too_long` | 422 | Notes or fit observations over 2000 characters |
| `measurement_version_not_found` | 404 | No such version in the active workspace |
| `measurement_version_create_failed` | 500 | Version or value insert failure; the transaction is rolled back |

## Implemented in Phase 3

Design, quotation and order routes. They use the same workspace header, nonce and permission checks as the earlier phases.

| Method | Route | Permission | Notes |
|---|---|---|---|
| `GET` | `/designs` | `designs.view` | `?search=` (title, reference, category, customer), `?status=draft\|approved\|archived\|all`, `?client_id=`, `?page=`, `?per_page=` |
| `POST` | `/designs` | `designs.manage` | Body `{ title, clientId, garmentCategory?, brief?, measurementProfileId?, measurementVersionId? }`; returns the design with `201` |
| `GET` | `/designs/{id}` | `designs.view` | One design with the referenced profile name and version number |
| `PATCH` | `/designs/{id}` | `designs.manage` | Body may contain `title`, `garmentCategory`, `brief`, `status`, `measurementProfileId`, `measurementVersionId` (0 clears a reference) |
| `GET` | `/quotations` | `quotations.view` | `?search=` (reference, customer, design), `?status=`, `?client_id=`, `?page=`, `?per_page=` |
| `POST` | `/quotations` | `quotations.manage` | Body `{ clientId, designId?, validUntil?, notes?, items }`; totals are computed on the server |
| `GET` | `/quotations/{id}` | `quotations.view` | One quotation with `items`, `subtotal`, `total` and `itemCount` |
| `PATCH` | `/quotations/{id}` | `quotations.manage` | Body may contain `status`, `validUntil`, `notes`, `designId`, `items`; `items` is accepted only while the quotation is a draft |
| `GET` | `/orders` | `orders.view` | `?search=` (reference, customer, garment), `?status=`, `?client_id=`, `?page=`, `?per_page=` |
| `POST` | `/orders` | `orders.manage` | Body `{ clientId` or `quotationId`, `dueDate?`, `notes?`, `items? }`; omitted items copy every quotation line |
| `GET` | `/orders/{id}` | `orders.view` | Order with `items` (garments) and `timeline` |
| `PATCH` | `/orders/{id}` | `orders.manage` | Body may contain `status`, `dueDate`, `notes` |
| `POST` | `/orders/{id}/events` | `orders.manage` | Body `{ message }`; appends a timeline note |
| `PATCH` | `/order-items/{id}` | `orders.manage` | Body may contain `status` and `notes`; affects one garment only |

A quotation line is `{ description, quantity, unitPrice }` on input; responses add `id` and `lineTotal`. Amounts must be between `0` and `9,999,999.99` with at most two decimals, and quantities are whole numbers from `1` to `9999`. A maximum of 50 lines or garments applies.

A garment is `{ garmentName, quantity?, unitPrice?, designId?, measurementVersionId?, notes? }`. A garment that cites a design without an explicit version inherits the design's measurement version. Both references must belong to the order's customer.

Order statuses are `draft`, `confirmed`, `in_progress`, `ready`, `delivered` and `cancelled`. Garment statuses are `pending`, `cutting`, `sewing`, `fitting`, `ready`, `delivered` and `cancelled`. Status changes append a timeline event; the same changes are written to the audit trail as `order.status_changed` or `order.item.status_changed`.

### Phase 3 error codes

| Code | Status | Meaning |
|---|---|---|
| `design_not_found` | 404 | No such design in the active workspace |
| `design_title_required` / `_too_long` | 422 | Title missing or over 120 characters |
| `design_client_required` | 422 | No customer supplied (or the customer does not exist in this workspace) |
| `design_garment_category_too_long` | 422 | Category over 60 characters |
| `design_brief_too_long` | 422 | Brief over 5000 characters |
| `design_status_invalid` | 422 | Status other than `draft`/`approved`/`archived` |
| `design_measurement_mismatch` | 422 | The cited profile or version belongs to another customer |
| `design_create_failed` / `design_update_failed` | 500 | Write failure |
| `quotation_not_found` | 404 | No such quotation in the active workspace |
| `quotation_client_required` | 422 | No customer supplied (or the customer does not exist in this workspace) |
| `quotation_design_mismatch` | 422 | The design belongs to another customer |
| `quotation_items_required` | 422 | No lines supplied, or an empty item list |
| `quotation_item_invalid` | 422 | A line is not an object; `details.index` identifies it |
| `quotation_item_description_required` / `_too_long` | 422 | Line description missing or over 191 characters |
| `quotation_item_quantity_invalid` | 422 | Quantity not a whole number from 1 to 9999 |
| `quotation_item_price_invalid` | 422 | Unit price outside `0–9,999,999.99` |
| `quotation_too_many_items` | 422 | More than 50 lines |
| `quotation_status_invalid` | 422 | Unknown quotation status |
| `quotation_valid_until_invalid` | 422 | Unparseable date |
| `quotation_notes_too_long` | 422 | Notes over 2000 characters |
| `quotation_locked` | 409 | Lines can only be replaced while the quotation is a draft |
| `quotation_create_failed` / `quotation_update_failed` | 500 | Write failure; the transaction is rolled back |
| `order_not_found` | 404 | No such order in the active workspace |
| `order_client_required` | 422 | Neither a valid customer nor a quotation was supplied |
| `order_client_mismatch` | 422 | The supplied customer differs from the quotation's customer |
| `order_items_required` | 422 | No garments supplied and no quotation to copy |
| `order_item_invalid` | 422 | A garment is not an object; `details.index` identifies it |
| `order_item_name_required` / `_too_long` | 422 | Garment name missing or over 120 characters |
| `order_item_quantity_invalid` | 422 | Quantity not a whole number from 1 to 9999 |
| `order_item_price_invalid` | 422 | Unit price outside `0–9,999,999.99` |
| `order_too_many_items` | 422 | More than 50 garments |
| `order_design_mismatch` | 422 | The design belongs to another customer |
| `order_measurement_mismatch` | 422 | The measurement version belongs to another customer |
| `order_status_invalid` | 422 | Unknown order status |
| `order_item_status_invalid` | 422 | Unknown garment status |
| `order_due_date_invalid` | 422 | Unparseable due date |
| `order_notes_too_long` / `order_item_notes_too_long` | 422 | Notes over 2000 characters |
| `order_item_not_found` | 404 | No such garment in the active workspace |
| `order_note_required` / `order_note_too_long` | 422 | Empty note, or one over 2000 characters |
| `order_create_failed` / `order_update_failed` / `order_item_update_failed` / `order_event_failed` | 500 | Write failure; the transaction is rolled back |

## Implemented in Phase 4

Production, material, fitting and quality routes. Every route uses the same workspace header, nonce and permission checks as the earlier phases, and every garment reference is validated against the active workspace.

| Method | Route | Permission | Notes |
|---|---|---|---|
| `GET` | `/production/stages` | `production.view` | Seeded starter workflow and custom stages, in sequence order |
| `POST` | `/production/stages` | `production.manage` | Body `{ name, sequence?, isActive? }`; the slug is derived from the name |
| `PATCH` | `/production/stages/{id}` | `production.manage` | Body may contain `name`, `sequence`, `isActive` |
| `GET` | `/production/jobs` | `production.view` | `?search=` (reference, customer, garment), `?status=queued\|in_progress\|blocked\|completed\|cancelled\|all`, `?assigned_to=`, `?order_item_id=`, `?page=`, `?per_page=` |
| `POST` | `/production/jobs` | `production.manage` | Body `{ orderItemId, assignedTo?, startDate?, dueDate?, notes? }`; one job per garment, returns `201` |
| `GET` | `/production/jobs/{id}` | `production.view` | Job with garment, customer, order, stage tasks, materials, fittings and checks |
| `PATCH` | `/production/jobs/{id}` | `production.manage` | Body may contain `status`, `assignedTo` (`0` clears), `startDate`, `dueDate`, `notes` |
| `POST` | `/production/jobs/{id}/tasks` | `production.manage` | Body `{ stageId, assignedTo?, dueDate?, notes? }`; returns the job with its tasks |
| `PATCH` | `/production/tasks/{id}` | `production.manage` | Body may contain `status`, `assignedTo`, `dueDate`, `notes`; completing a task stamps `completedAt` |
| `GET` | `/production/dashboard` | `production.view` | Counts, jobs by status, upcoming due dates, failed checks, low stock and unplanned garments |
| `GET` | `/materials` | `materials.view` | `?search=`, `?type=fabric\|accessory\|consumable\|all`, `?low_stock=1`, `?include_inactive=1`, `?page=`, `?per_page=` |
| `POST` | `/materials` | `materials.manage` | Body `{ name, materialType?, unit, quantityOnHand?, reorderLevel?, unitCost?, notes? }` |
| `GET` | `/materials/{id}` | `materials.view` | Material with recent stock movements |
| `PATCH` | `/materials/{id}` | `materials.manage` | Body may contain `name`, `materialType`, `unit`, `reorderLevel`, `unitCost`, `notes`, `isActive` |
| `GET` | `/materials/{id}/movements` | `materials.view` | Paginated stock ledger |
| `POST` | `/materials/{id}/movements` | `materials.manage` | Body `{ type: in\|out\|adjustment, quantity, note? }`; `adjustment` accepts a signed quantity |
| `GET` | `/order-items/{id}/materials` | `materials.view` | Active allocations and the material totals for one garment |
| `POST` | `/order-items/{id}/materials` | `materials.manage` | Body `{ materialId, quantity, source: customer_supplied\|designer_supplied\|shared_supply, notes? }` |
| `DELETE` | `/material-allocations/{id}` | `materials.manage` | Releases the allocation; designer-supplied stock returns to inventory |
| `GET` | `/fittings` | `fittings.view` | `?status=`, `?order_item_id=`, `?upcoming=1`, `?page=`, `?per_page=` |
| `POST` | `/fittings` | `fittings.manage` | Body `{ orderItemId, scheduledAt, location?, assignedTo?, notes? }` |
| `PATCH` | `/fittings/{id}` | `fittings.manage` | Body may contain `status`, `scheduledAt`, `location`, `assignedTo`, `outcome`, `notes` |
| `GET` | `/alterations` | `fittings.view` | `?status=`, `?order_item_id=`, `?page=`, `?per_page=` |
| `POST` | `/alterations` | `fittings.manage` | Body `{ orderItemId, fittingId?, description, dueDate? }`; a fitting must belong to the same garment |
| `PATCH` | `/alterations/{id}` | `fittings.manage` | Body may contain `status`, `description`, `dueDate`, `notes`; completion stamps `completedAt` |
| `GET` | `/quality-checks` | `quality.view` | `?result=passed\|failed\|needs_rework\|all`, `?order_item_id=`, `?page=`, `?per_page=` |
| `POST` | `/quality-checks` | `quality.manage` | Body `{ orderItemId, result, notes?, checkedAt? }`; failed and rework results append to the order timeline |

Each garment has at most one production job. Jobs move `queued → in_progress → completed` (or through `blocked`); `completed` and `cancelled` are terminal. Task statuses are `pending`, `in_progress`, `done` and `cancelled`. Fittings move `scheduled → completed|missed|cancelled`, with `missed` and `cancelled` rebookable; alteration statuses are `open`, `in_progress`, `completed` and `cancelled`.

Materials are `fabric`, `accessory` or `consumable`, measured in `metre`, `yard`, `piece`, `roll`, `spool`, `kilogram` or `litre`. Opening stock is recorded as an `in` movement. Allocating `designer_supplied` material decrements stock inside the same transaction as the allocation and writes an `allocation` movement; `customer_supplied` and `shared_supply` allocations never change stock. Releasing a designer allocation writes a `return` movement. Stock can never go below zero. Allocations and stock movements are written to the audit trail.

Dates use `YYYY-MM-DD`; timestamps use `YYYY-MM-DD HH:MM:SS` in UTC.

### Phase 4 error codes

| Code | Status | Meaning |
|---|---|---|
| `production_stage_not_found` | 404 | No such stage in the active workspace |
| `production_stage_name_required` / `_too_long` | 422 | Stage name missing or over 80 characters |
| `production_stage_slug_exists` | 409 | A stage with the derived slug already exists |
| `production_stage_sequence_invalid` | 422 | Sequence not a whole number from 0 to 9999 |
| `production_stage_inactive` | 422 | Task cites a stage that is switched off |
| `production_stage_create_failed` / `production_stage_update_failed` | 500 | Write failure |
| `production_garment_required` | 422 | No garment supplied, or it does not exist in this workspace |
| `production_job_not_found` | 404 | No such job in the active workspace |
| `production_job_exists` | 409 | The garment already has a production job |
| `production_assignee_invalid` | 422 | The assignee is not an active member of the workspace |
| `production_job_status_invalid` / `production_task_status_invalid` | 422 | Unknown status |
| `production_job_transition_invalid` | 409 | The requested job status move is not allowed |
| `production_start_date_invalid` / `production_due_date_invalid` | 422 | Unparseable date |
| `production_notes_too_long` | 422 | Notes over 2000 characters |
| `production_task_not_found` | 404 | No such task in the active workspace |
| `production_job_create_failed` / `production_job_update_failed` / `production_task_create_failed` / `production_task_update_failed` | 500 | Write failure; the transaction is rolled back |
| `material_not_found` | 404 | No such material in the active workspace |
| `material_name_required` / `_too_long` | 422 | Material name missing or over 120 characters |
| `material_type_invalid` / `material_unit_invalid` | 422 | Unknown material type or unit |
| `material_quantity_invalid` / `material_reorder_level_invalid` / `material_unit_cost_invalid` | 422 | Non-negative amount outside the allowed range |
| `material_notes_too_long` / `material_movement_note_too_long` | 422 | Notes over 2000 characters |
| `material_movement_type_invalid` | 422 | Movement type other than `in`, `out` or `adjustment` |
| `material_movement_quantity_invalid` | 422 | Zero, non-numeric or non-negative `in`/`out` quantity |
| `material_insufficient_stock` | 409 | The movement or allocation would take stock below zero |
| `material_inactive` | 422 | The material is archived |
| `material_allocation_quantity_invalid` | 422 | Allocation quantity not greater than zero |
| `material_allocation_source_invalid` | 422 | Unknown supply source |
| `material_allocation_notes_too_long` | 422 | Allocation notes over 2000 characters |
| `material_allocation_not_found` | 404 | No such active allocation in this workspace |
| `material_create_failed` / `material_update_failed` / `material_movement_failed` / `material_allocation_failed` / `material_release_failed` | 500 | Write failure; the transaction is rolled back |
| `fitting_garment_required` | 422 | No garment supplied, or it does not exist in this workspace |
| `fitting_not_found` | 404 | No such fitting in the active workspace |
| `fitting_scheduled_at_invalid` | 422 | Unparseable fitting time |
| `fitting_assignee_invalid` | 422 | The assignee is not an active member of the workspace |
| `fitting_location_too_long` / `fitting_notes_too_long` / `fitting_outcome_too_long` | 422 | Text over the allowed length |
| `fitting_status_invalid` | 422 | Unknown fitting status |
| `fitting_transition_invalid` | 409 | The requested fitting status move is not allowed |
| `fitting_create_failed` / `fitting_update_failed` | 500 | Write failure; the transaction is rolled back |
| `alteration_garment_required` | 422 | No garment supplied, or it does not exist in this workspace |
| `alteration_not_found` | 404 | No such alteration in the active workspace |
| `alteration_garment_mismatch` | 422 | The cited fitting belongs to another garment |
| `alteration_description_required` / `_too_long` | 422 | Description missing or over 500 characters |
| `alteration_due_date_invalid` | 422 | Unparseable due date |
| `alteration_status_invalid` | 422 | Unknown alteration status |
| `alteration_create_failed` / `alteration_update_failed` | 500 | Write failure; the transaction is rolled back |
| `quality_garment_required` | 422 | No garment supplied, or it does not exist in this workspace |
| `quality_check_not_found` | 404 | No such check in the active workspace |
| `quality_result_invalid` | 422 | Result other than `passed`, `failed` or `needs_rework` |
| `quality_checked_at_invalid` | 422 | Unparseable check time |
| `quality_notes_too_long` | 422 | Notes over 2000 characters |
| `quality_create_failed` | 500 | Write failure |

## Route requirements

Every route must define:

- Authentication requirements.
- Permission callback.
- Tenant resolution.
- Input schema and validation.
- Pagination rules where applicable.
- Response shape.
- Error behaviour.
- Audit/event requirements.

## Authentication

For the same-site React application, use WordPress authenticated session cookies and REST API nonces appropriately. **A nonce is not a permission check.**

Use server-to-server authentication for external WordPress connectors. Never put permanent integration secrets in browser JavaScript.

---

## Related

- Request flow and the rule that React must never connect directly to the database: Section 4 of the Master Technical Specification.
- Application routes and experiences (frontend): Section 10.
- Security and authorization requirements: [`SECURITY.md`](SECURITY.md).
- Phase 1 implementation notes and limitations: [`PHASE_1_PLAN.md`](PHASE_1_PLAN.md).
- Phase 2 implementation notes and limitations: [`PHASE_2_PLAN.md`](PHASE_2_PLAN.md).
- Phase 3 implementation notes and limitations: [`PHASE_3_PLAN.md`](PHASE_3_PLAN.md).
- Phase 4 implementation notes and limitations: [`PHASE_4_PLAN.md`](PHASE_4_PLAN.md).
