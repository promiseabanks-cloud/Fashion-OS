# Phase 4 — Production and Inventory

Status: **implemented** in `src/fashionos/` (backend, studio views, tests and documentation). The WordPress test suite has been written but not executed — see section 8.

## 1. What Phase 4 delivers

Phase 4 takes a confirmed order and tracks how each garment is actually made:

- **A production job per garment.** A job can only be created for a garment in the active workspace, and a garment can have at most one job. Jobs carry an assignee, start/due dates, notes and a status.
- **A configurable stage workflow.** Nine starter stages are seeded once per workspace (design confirmation, material preparation, cutting, sewing, finishing, fitting, alterations, quality control, ready for collection). A studio can rename, reorder, deactivate or add stages; stages are labels plus order, not hard-coded process steps.
- **Stage tasks.** Each job gets tasks against stages, with an optional assignee, due date and completion stamp. Task completion stamps `completedAt`; the job's own status is moved deliberately, not inferred.
- **Materials and stock.** Materials (fabric, accessory, consumable) with units, stock on hand, reorder level, unit cost and an append-only movement ledger (`in`, `out`, `adjustment`, plus internal `allocation` and `return`). Stock can never go below zero.
- **Material ownership.** Every allocation to a garment records its source: `designer_supplied`, `customer_supplied` or `shared_supply`. Only designer-supplied stock is the studio's property and only it decrements stock. Releasing such an allocation returns the quantity with a `return` movement, so the ledger explains the balance.
- **Fittings and alterations.** Fittings belong to a garment and move through `scheduled`, `completed`, `missed` and `cancelled`. Alterations can cite the fitting that produced them and move through `open`, `in_progress`, `completed` and `cancelled`.
- **Quality checks.** Append-only checks per garment with `passed`, `failed` or `needs_rework`; failures and rework requests also append an order timeline event.
- **A production dashboard.** Counts of active jobs, jobs by status, upcoming due dates, failed checks, low-stock materials and garments with no job yet, so nothing silently falls out of production.
- **Permissions and audit.** Eight new keys: `production.view`, `production.manage`, `materials.view`, `materials.manage`, `fittings.view`, `fittings.manage`, `quality.view`, `quality.manage`, granted to the existing system roles and re-seeded by migration `0004`. Every write is audited.

Phase 4 exits when each garment can move through its own workflow, material ownership and source are clear, and staff permissions are enforced on the server.

## 2. Schema

Migration `0004` adds nine tenant-scoped tables and no foreign keys, matching the earlier phases:

- `fos_production_stages` — unique `(tenant_id, slug)`, ordered by `sequence`.
- `fos_production_jobs` — unique `(tenant_id, order_item_id)`, so a garment cannot be planned twice.
- `fos_production_tasks` — one row per stage task, with `completed_at`.
- `fos_materials` — stock on hand, reorder level, unit cost, active flag.
- `fos_material_allocations` — garment, material, quantity and source.
- `fos_material_movements` — signed ledger entries with a reference to the allocation or garment.
- `fos_fittings` — scheduled time, assignee, status, outcome.
- `fos_alterations` — optional fitting, required description, status, completion stamp.
- `fos_quality_checks` — result, checker, notes and check time.

Optional dates use `CHAR(10)` and optional timestamps `CHAR(19)`, both defaulting to `''` instead of `NULL`; see [`DATABASE_SCHEMA.md`](DATABASE_SCHEMA.md) for every column and index.

## 3. Roles and permissions

| Permission | Owner | Manager | Designer | Tailor / production | Finance |
|---|---|---|---|---|---|
| `production.view` | yes | yes | yes | yes | yes |
| `production.manage` | yes | yes | yes | yes | no |
| `materials.view` | yes | yes | yes | yes | no |
| `materials.manage` | yes | yes | yes | yes | no |
| `fittings.view` | yes | yes | yes | yes | no |
| `fittings.manage` | yes | yes | yes | yes | no |
| `quality.view` | yes | yes | yes | yes | no |
| `quality.manage` | yes | yes | yes | yes | no |

The assignment check is separate from the permission check: an assignee must be an active member of the workspace, so a tailor cannot be assigned work in another studio.

## 4. Models and rules

### Stages

Starter stages are inserted for a workspace the first time it asks for them, and migration `0004` seeds every existing workspace. Names are sanitised and capped at 80 characters; the slug is derived from the name and must be unique per workspace (`409 production_stage_slug_exists`). Inactive stages remain visible in history but cannot receive new tasks (`422 production_stage_inactive`).

### Jobs and tasks

- Creating a job requires a garment in the workspace (`production_garment_required`) and refuses a second job for the same garment (`409 production_job_exists`).
- Job statuses are `queued`, `in_progress`, `blocked`, `completed`, `cancelled`. Allowed moves are `queued → in_progress|blocked|cancelled`, `in_progress → blocked|completed|cancelled`, `blocked → in_progress|cancelled`; `completed` and `cancelled` are terminal. Reopening a finished garment means a new job, not a silent status edit (`409 production_job_transition_invalid`).
- Task statuses are `pending`, `in_progress`, `done`, `cancelled`. Moving to `done` stamps `completedAt`; moving away clears it. Tasks may only cite an active stage of the same workspace.
- Assignees must be active members (`422 production_assignee_invalid`).
- The dashboard reports `unplannedGarments`: active garments with no job.

### Materials

- Types: `fabric`, `accessory`, `consumable`. Units: `metre`, `yard`, `piece`, `roll`, `spool`, `kilogram`, `litre`.
- Creating a material with opening stock writes an `in` movement in the same transaction, so the ledger always reconciles with `quantity_on_hand`.
- `in` and `out` movements require a positive quantity; `adjustment` accepts a signed quantity. Any movement or allocation that would take stock below zero returns `409 material_insufficient_stock`.
- Archived materials keep their ledger but refuse new movements and allocations (`422 material_inactive`).

### Allocations and ownership

- Quantity must be greater than zero; source must be one of the three keys.
- `designer_supplied` — decrements stock and writes an `allocation` movement with the garment as reference.
- `customer_supplied` — records the allocation without touching stock, because the fabric is not the studio's property. The same applies to `shared_supply`.
- Releasing an allocation deletes it; if it was designer-supplied, stock is returned and a `return` movement is written. Releasing is the only way to correct an allocation, keeping the ledger honest.
- Allocations are listed per garment, with material totals.

### Fittings and alterations

- Fittings require a garment and a valid scheduled time. `scheduled → completed|missed|cancelled`; `missed` and `cancelled` can be rebooked to `scheduled` with a new time; `completed` is terminal (`409 fitting_transition_invalid`).
- Alterations require a garment and a description. A cited fitting must belong to the same garment (`422 alteration_garment_mismatch`). Completion stamps `completedAt`.
- Both accept an assignee and validate membership.

### Quality checks

- Results are `passed`, `failed`, `needs_rework`; check time defaults to now but can be supplied.
- Checks are append-only. `failed` and `needs_rework` append an order timeline event so the customer-facing order history explains the delay.
- The dashboard surfaces the five most recent failures with garment, customer and order reference.

## 5. API

All routes are under `fashionos/v1`, use the workspace header and nonce, and check a permission before the service runs. Full request/response tables and error codes are in [`API_REFERENCE.md`](API_REFERENCE.md#implemented-in-phase-4).

| Area | Routes |
|---|---|
| Stages | `GET/POST /production/stages`, `PATCH /production/stages/{id}` |
| Jobs | `GET/POST /production/jobs`, `GET/PATCH /production/jobs/{id}`, `GET /production/dashboard` |
| Tasks | `POST /production/jobs/{id}/tasks`, `PATCH /production/tasks/{id}` |
| Materials | `GET/POST /materials`, `GET/PATCH /materials/{id}`, `GET/POST /materials/{id}/movements` |
| Allocations | `GET/POST /order-items/{id}/materials`, `DELETE /material-allocations/{id}` |
| Fittings | `GET/POST /fittings`, `PATCH /fittings/{id}` |
| Alterations | `GET/POST /alterations`, `PATCH /alterations/{id}` |
| Quality | `GET/POST /quality-checks` |

Errors follow the existing envelope: `{ success: false, error: { code, message, details? } }` with 404 for missing tenant records, 422 for validation and 409 for state conflicts.

## 6. Frontend

Two new studio views, reachable from the main navigation and respecting permissions:

- **Production** — dashboard tiles, unplanned garments, a filterable job list, and a job workspace showing garment/order context, stage tasks, materials, fittings, alterations and quality checks. Every write goes through the typed API client and refreshes the job.
- **Materials** — stock list with low-stock emphasis, create form, stock movements, and per-garment allocations with source selection and release.

Types live in `resources/js/types.ts`, all calls in `resources/js/services/api.ts`, and the development mock mirrors every route so the UI can be exercised without WordPress.

## 7. Acceptance criteria

| Criterion | Where it is met |
|---|---|
| Each garment can move through its own production workflow | One job per garment, own status, tasks, fittings, alterations and checks; two garments on one order are independent |
| Material ownership and source are clear | Every allocation records its source; only designer-supplied stock moves, and the ledger records allocation and return |
| Staff permissions are enforced | Route permission callbacks, plus workspace-membership checks for every assignee |

## 8. Verification

Completed in this environment:

- `npm run format:check`, `npm run typecheck`, `npm run lint`, `npm test` (Vitest, including new production/material/fitting API-client tests) and `npm run build`.
- A PHP syntax check over every plugin PHP file, including the new services, repositories, controllers, migration and test suite.
- Manual review of the development mock against the services.

Not executable here (recorded honestly):

- `vendor/bin/phpunit` — no PHP runtime, Composer vendor tree or MySQL in this environment. `tests/php/ProductionInventoryTest.php` covers migration version, stage seeding, independent garment jobs, invalid transitions, designer versus customer stock, insufficient stock, allocation release, fitting/alteration/quality flows, unplanned garments and tenant isolation.
- `vendor/bin/phpcs`, applying migration `0004` to MySQL, and exercising the REST routes against WordPress.

## 9. Known limitations

- Stock is decremented on allocation, not on consumption, and there is no reservation or purchase-order flow yet.
- Job status is not inferred from task completion; the studio moves it deliberately.
- There is no automatic hand-off between a garment's order status and its production job status; the two are related but not synchronised.
- Alterations are not costed; billing for alterations and materials belongs to the finance phase.
- Quality checks and stock movements have no attachments or photos.
- Stage deletion is not supported; stages are deactivated instead, preserving task history.
- The dashboard lists at most five unplanned garments and five recent failures.
- No PHP runtime or database was available, so migration, REST and PHPUnit results remain unverified (see section 8).

## 10. Next

Phase 5 (payments and finance) builds on the order and garment records: invoices, payments, balances, material costs and alteration charges, with the same tenant, permission and audit rules.
