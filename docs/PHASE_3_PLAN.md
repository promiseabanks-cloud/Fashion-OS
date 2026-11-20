# Phase 3 — Designs, Quotations and Orders

> Phase 3 of the [Development Roadmap](DEVELOPMENT_ROADMAP.md), implemented on top of Phases 1 and 2. The [Master Technical Specification](../FashionOS_Master_Technical_Specification.md) remains authoritative.
>
> **Status:** implemented. Frontend typecheck, lint, formatting, unit tests and production build pass. PHP was syntax-checked with a parser; it was **not executed** — no PHP interpreter or database exists in this environment, so migrations, REST routes and the PHPUnit suite are unverified at runtime.

## 1. What Phase 3 delivers

- **Designs** (`fos_designs`): a titled brief for one customer, with an optional garment category and an optional reference to one measurement profile and **one exact measurement version**. Reference numbers are `D-0001`, sequential per workspace.
- **Quotations** (`fos_quotations`, `fos_quotation_items`): a priced offer with its own status (`draft`, `sent`, `accepted`, `declined`, `expired`), validity date, notes and repeatable line items. Reference numbers are `Q-0001`.
- **Server-priced totals**: every line total and the quotation subtotal are computed from quantity and unit price on the server. Amounts submitted by the browser are ignored.
- **Draft-only line editing**: a quotation's lines may be replaced while it is a `draft`; afterwards the API returns `409 quotation_locked`. A changed offer is a new quotation.
- **Orders** (`fos_orders`, `fos_order_items`): a customer's order with **multiple independently tracked garments**. Reference numbers are `O-0001`.
- **Per-garment references**: each garment stores its own `design_id`, `measurement_profile_id` and `measurement_version_id`, validated against the order's customer. Garments inherit their measurement version from the design chosen for them, and an explicit version may override it.
- **Per-garment status**: `pending`, `cutting`, `sewing`, `fitting`, `ready`, `delivered`, `cancelled`, changed through a dedicated endpoint. Moving the order's own status does not overwrite garment statuses.
- **Orders from quotations**: creating an order with `quotationId` copies every quotation line into garments, keeping the source `quotation_item_id`. The quotation is **not** modified; the two records keep separate lifecycles.
- **Order activity history** (`fos_order_events`): creation, garment additions, order status changes, garment status changes and free-text notes, each with actor and timestamp. Status changes are also written to the workspace audit trail.
- **Studio views**: Designs, Quotations and Orders, with permission-aware navigation.
- **Role permissions** for designs, quotations and orders, including migration `0003` re-seeding existing system roles.

Deliberately deferred, with what owns it:

- **PDF generation** for quotations: requires a PDF library and a font/asset strategy on shared hosting. WordPress has no bundled PDF renderer, and adding one is a deployment-sized decision. The plan is a server-rendered HTML document plus a browser print stylesheet first (Phase 5, with invoices and receipts), then a PDF library if the printed output is insufficient.
- **Customer approval flow**: the quotation statuses `sent`/`accepted`/`declined` are recorded by staff today. Customer-facing approval needs portal authentication and per-customer scoping, so it belongs to Phase 6; the status values already match it.
- **Quotation send action and delivery**: `POST /quotations/{id}/send` in the specification implies email/notification delivery, which arrives with the queue and notification system in Phase 7. Sending is currently a status change recorded by staff.
- **Tax, discounts and deposits**: totals are the sum of the lines. Financial rules belong to Phase 5 and must not be half-implemented here.
- **Editing garments after creation**: garment identity, design and measurement references are fixed at creation so an order always states what was cut. Status and notes are editable; adding or removing a garment is a future explicit action rather than a silent edit.
- **Production stages, assignments and materials**: Phase 4, which will add the configurable stage list on top of the garment status field.
- **Private design files and images**: file handling remains deferred with the media rules described in `PHASE_2_PLAN.md`.

## 2. Schema

Migration `0003` (`Migration0003QuotationsOrders`), all tables prefixed with the configured WordPress prefix via `dbDelta()`.

**`{prefix}fos_designs`** — `id`, `tenant_id`, `uuid`, `reference`, `client_id`, `measurement_profile_id`, `measurement_version_id`, `title`, `garment_category`, `brief`, `status`, `created_by`, `created_at`, `updated_at`. Unique `uuid` and `(tenant_id, reference)`; indexes `(tenant_id, client_id)`, `(tenant_id, status)`, `(tenant_id, measurement_version_id)`.

**`{prefix}fos_quotations`** — `id`, `tenant_id`, `uuid`, `reference`, `client_id`, `design_id`, `currency`, `status`, `valid_until`, `notes`, `subtotal`, `total`, `created_by`, `created_at`, `updated_at`. Unique `uuid` and `(tenant_id, reference)`; indexes `(tenant_id, client_id)`, `(tenant_id, status)`.

**`{prefix}fos_quotation_items`** — `id`, `tenant_id`, `quotation_id`, `description`, `quantity`, `unit_price`, `line_total`, `sort_order`. Index `(tenant_id, quotation_id)`.

**`{prefix}fos_orders`** — `id`, `tenant_id`, `uuid`, `reference`, `client_id`, `quotation_id`, `currency`, `status`, `due_date`, `notes`, `total`, `created_by`, `created_at`, `updated_at`. Unique `uuid` and `(tenant_id, reference)`; indexes `(tenant_id, client_id)`, `(tenant_id, status)`, `(tenant_id, due_date)`.

**`{prefix}fos_order_items`** — `id`, `tenant_id`, `order_id`, `quotation_item_id`, `design_id`, `measurement_profile_id`, `measurement_version_id`, `garment_name`, `garment_category`, `quantity`, `unit_price`, `status`, `notes`, `sort_order`, `created_at`, `updated_at`. Indexes `(tenant_id, order_id)`, `(tenant_id, measurement_version_id)`, `(tenant_id, status)`.

**`{prefix}fos_order_events`** — `id`, `tenant_id`, `order_id`, `order_item_id`, `event_type`, `message`, `actor_id`, `created_at`. Index `(tenant_id, order_id, id)`.

No foreign keys, for the same reason recorded in Phase 1: hosting engines may not enforce them. Every query filters by `tenant_id`, and parent records (customer, design, profile, version, quotation) are checked in the service before a child is written.

Dates that are optional (`valid_until`, `due_date`) are stored as `CHAR(10)` in `YYYY-MM-DD` form with `''` for "not set", avoiding MySQL strict-mode failures on `DATE` columns that receive an empty string.

Money is `DECIMAL(12,2)`, matching the specification's financial convention closely enough for quotations and orders; invoices in Phase 5 will revisit the `DECIMAL(14,2)` guidance for balances.

Role permissions created by earlier migrations were fixed lists, so migration `0003` re-seeds the `is_system` roles from `Roles::defaults()` per tenant. There is still no role-editing UI, and the owner's wildcard is untouched.

## 3. Roles and permissions

Three permission pairs were added to the catalogue:

| Permission | Meaning |
|---|---|
| `designs.view` | See design records and their measurement references |
| `designs.manage` | Create, edit and archive designs |
| `quotations.view` | See quotations, their lines and totals |
| `quotations.manage` | Create and edit quotations |
| `orders.view` | See orders, garments and activity history |
| `orders.manage` | Create orders and move garments through production |

| Role | Phase 3 permissions |
|---|---|
| Owner | `*` (includes everything above) |
| Manager | all six |
| Designer | all six — designs, quotations and orders are the designer's daily work |
| Tailor / production | `designs.view`, `orders.view` — read the brief and update nothing at the quotation stage |
| Finance | `quotations.view`, `orders.view` |

Garment status changes require `orders.manage`; a tailor who only has `orders.view` sees the status but cannot move it. Phase 4 adds production-specific permissions and stage configuration.

## 4. Models and rules

### Designs

A design belongs to exactly one customer and may cite a profile and version. The service validates that both belong to that customer, so a version id from another customer is refused with `design_measurement_mismatch` instead of being attached. A design's customer cannot be changed; moving it would invalidate its references.

Status is `draft`, `approved` or `archived`. Archiving hides a design from the default filters but never deletes it or touches orders that cite it.

### Quotations

A quotation is an offer, not a commitment. Its status is independent of any order created from it. Lines carry a description, whole quantity and unit price; the server computes `line_total = quantity × unit_price` and `subtotal = total = Σ line_total`. The workspace currency is copied onto the quotation at creation so a later currency change cannot rewrite history.

Lines can be replaced only while the quotation is a `draft`. The update replaces the whole set inside a transaction, so a partially written quotation cannot be observed.

### Orders and garments

An order is a customer's production commitment. `quotation_id` records provenance without coupling lifecycles. Each garment row is the unit of production: it has its own name, category, quantity, unit price, status, notes, design and measurement version.

Garment references are validated as follows:

1. A `designId` must exist in the workspace and belong to the order's customer.
2. A `measurementVersionId` must exist in the workspace and belong to the order's customer, and implies its profile.
3. If a garment cites a design and no explicit version, it inherits the design's measurement version and profile.
4. If an order is created from a quotation without items, every quotation line becomes a garment, with the quotation's design on each.

Measurement versions are append-only from Phase 2, so a garment's "cut from these measurements" reference cannot drift. A refit creates a new version; the garment keeps the one it was made against until a new garment is created.

Order status is `draft`, `confirmed`, `in_progress`, `ready`, `delivered`, `cancelled`. Garment status adds `pending`, `cutting`, `sewing`, `fitting` to the same lifecycle. Each change appends an event and, for status changes, an audit entry.

Timeline events are written inside the same transaction as the change they describe: `created`, `item_added`, `status`, `item_status`, `item_updated`, `note`.

## 5. API

All routes live under `/wp-json/fashionos/v1/`, resolve the workspace from `X-FashionOS-Workspace`, and require `X-WP-Nonce` on writes.

| Method | Route | Permission | Purpose |
|---|---|---|---|
| `GET` | `/designs` | `designs.view` | Search (`search`), filter (`status`, `client_id`), paginate (`page`, `per_page`) |
| `POST` | `/designs` | `designs.manage` | Create a design (`title`, `clientId`, `garmentCategory?`, `brief?`, `measurementProfileId?`, `measurementVersionId?`) |
| `GET` | `/designs/{id}` | `designs.view` | One design with its references |
| `PATCH` | `/designs/{id}` | `designs.manage` | Update details, status or references |
| `GET` | `/quotations` | `quotations.view` | Search, filter by status or customer, paginate |
| `POST` | `/quotations` | `quotations.manage` | Create a quotation (`clientId`, `designId?`, `validUntil?`, `notes?`, `items`) |
| `GET` | `/quotations/{id}` | `quotations.view` | One quotation with its lines and totals |
| `PATCH` | `/quotations/{id}` | `quotations.manage` | Status, validity, notes or (draft only) replacement lines |
| `GET` | `/orders` | `orders.view` | Search reference/customer/garment, filter by status or customer, paginate |
| `POST` | `/orders` | `orders.manage` | Create an order (`clientId` or `quotationId`, `dueDate?`, `notes?`, `items?`) |
| `GET` | `/orders/{id}` | `orders.view` | Order with garments and timeline |
| `PATCH` | `/orders/{id}` | `orders.manage` | Order status, due date or notes |
| `POST` | `/orders/{id}/events` | `orders.manage` | Append a free-text timeline note |
| `PATCH` | `/order-items/{id}` | `orders.manage` | Change one garment's status or notes |

List responses put pagination in `data` (`page`, `perPage`, `total`, `totalPages`) alongside `designs`, `quotations` or `orders`, matching Phase 2.

A quotation line is `{ description, quantity, unitPrice }` on input and gains `lineTotal` on output:

```json
{
  "clientId": 12,
  "designId": 4,
  "validUntil": "2026-12-15",
  "items": [
    { "description": "Wedding jacket", "quantity": 1, "unitPrice": 380000 },
    { "description": "Wedding trousers", "quantity": 1, "unitPrice": 100000 }
  ]
}
```

A garment is `{ garmentName, quantity?, unitPrice?, designId?, measurementVersionId?, notes? }`. Creating an order from a quotation only needs the quotation id:

```json
{ "quotationId": 9, "dueDate": "2026-12-20" }
```

## 6. Frontend

- **Designs** (`views/DesignsView.tsx`): list with search and status filter, a create form whose customer selection loads that customer's measurement profiles and their versions, a detail panel with the brief and references, and an inline status change.
- **Quotations** (`views/QuotationsView.tsx`): list with status filter, a builder with repeatable priced lines (a preview subtotal is shown, but the server recomputes it), optional design reference and validity date, and a detail panel with lines, totals and status.
- **Orders** (`views/OrdersView.tsx`): list with per-order garment counts and totals, a builder that can copy a quotation's lines or list garments individually with a design each, and a detail panel with a per-garment status select, order status and an activity timeline with a note form.
- **Audit labels** cover the new actions (`design.*`, `quotation.*`, `order.*`). Free-text order notes appear only in the order timeline, not the audit trail.
- Navigation is permission-aware; the server re-checks every request.
- `dev/mock.ts` now emulates the Phase 3 endpoints for `npm run dev`: designs, quotations with server-style totals, orders with garments, independent garment statuses, timeline events and tenant scoping.

## 7. Acceptance criteria

| Criterion (from the roadmap) | Status |
|---|---|
| One order can contain multiple independently trackable garments | Implemented: garments are rows with their own status, references and notes, changed through `PATCH /order-items/{id}`. Covered by `tests/php/QuotationOrderTest.php`. **Not exercised against WordPress.** |
| Quotations and orders remain separate | Implemented: an order copies quotation lines and records `quotation_id`; no status or edit propagates either way. Covered by a test that reloads the quotation after ordering. |
| Order history is auditable | Implemented twice over: the order timeline records every event, and status changes also write audit entries (`order.created`, `order.updated`, `order.status_changed`, `order.item.status_changed`). |

## 8. Verification

Executed in this environment:

- `npm run typecheck` — passes (strict TypeScript).
- `npm run lint` — passes.
- `npm run format:check` — passes.
- `npm test` — API-client tests pass, including the new design, quotation and order query/mutation builders.
- `npm run build` — succeeds; manifest and hashed assets emitted.
- PHP syntax check across every PHP file — passes (parser-based, not execution).

Not executable here, therefore unverified:

- Migration `0003` against MySQL/MariaDB: `dbDelta()` output, indexes, the role-permission re-seed.
- Every Phase 3 REST route, including permission callbacks and error envelopes.
- `tests/php/QuotationOrderTest.php` and the Phase 1 and 2 suites.
- PHP_CodeSniffer / WordPress Coding Standards (`composer lint:php`).

## 9. Known limitations

- **No PDF or printed quotation yet.** The API returns everything a document needs; rendering is deferred to Phase 5.
- **Approval is internal.** Staff set `sent`, `accepted`, `declined` or `expired`; customers cannot approve until the portal exists (Phase 6).
- **No tax, discount or deposit handling.** `total` equals `subtotal`; Phase 5 owns financial rules.
- **Garments cannot be added to or removed from an order yet.** Adjusting an order means creating a new one, so the record always matches what was produced. This will be revisited with explicit amendments.
- **Quotation edits replace all lines.** There is no per-line patch, which keeps totals consistent at the cost of resending the set.
- **Order status is free to move.** There is no transition graph; a delivered order can be moved back to draft by a manager. Phase 4 introduces production stages and can tighten this.
- **References are read-then-insert for numbering.** `(tenant_id, reference)` is unique, so a concurrent collision fails the write rather than duplicating a number.
- **Currency is copied, not converted.** Changing the workspace currency does not rewrite existing quotations or orders.
- **Search is `LIKE`-based**, as in Phase 2; orders search across reference, customer name and garment names with an `EXISTS` subquery.
- **No files or images on designs.** Deferred with the media rules.

## 10. Next

Phase 4 (production and inventory) builds directly on garments: configurable production stages, staff assignments, material allocation and fittings become per-garment workflows. The garment row is already the unit of production, so stages can be added without reshaping orders.
