# Phase 6 — Client portal

> Phase 6 of the [Development Roadmap](DEVELOPMENT_ROADMAP.md), implemented on top of Phases 1–5. The [Master Technical Specification](../FashionOS_Master_Technical_Specification.md) remains authoritative.
>
> **Status:** customer-scoped orders, invoices and payment status are implemented, with tests and documentation. Frontend typecheck, lint, formatting, unit tests and the PHP syntax check pass. PHP was **not executed** — no PHP interpreter or database exists in this environment, so migration `0006`, the portal REST routes and `tests/php/PortalTest.php` are unverified at runtime.

## 1. What this phase delivers

Phase 6 turns the studio's own records into a customer's view of them. This implementation covers the parts of the Phase 6 task list that give a customer their own orders, invoices and payment status:

- **Customer access without WordPress accounts.** A customer is a CRM record, not a WordPress user. The studio issues a portal link; the token in that link is the customer's credential.
- **Hashed, revocable, expirable links** (`fos_portal_access`). Only a SHA-256 hash of the token is stored, so a database dump cannot be replayed as a working link. Staff can set an optional lifetime (up to 365 days, or none) and revoke a link at any time.
- **A tenant-branded portal.** The portal shows the studio's name and its own visual language, not WordPress admin chrome: one page with the studio's mark, the customer's name, and counts of orders in progress, open invoices and outstanding balance.
- **The customer's own profile** — name, reference, email and phone. Nothing internal.
- **Order and garment tracking.** A customer sees orders the studio has confirmed and each garment's own production status, because garments move independently.
- **Invoice and payment visibility.** Issued invoices with lines, totals, amount paid, balance, due date and the payments the studio has verified. Unverified payments are not shown, so the balance the customer sees always matches the ledger the studio sees.
- **Staff-side sharing.** A panel on the customer record issues and revokes links. The link is displayed once, because only its hash is kept; if it is lost, it is revoked and replaced.

The exit criteria are that a customer sees only their own permitted information and that internal business data remains private. Section 8 maps each criterion to where it is met.

Not included here, and still open from the roadmap's Phase 6 list: design brief submission, approvals, appointments, notification delivery and customer-editable profile data. Section 10 records them as limitations rather than pretending they exist.

## 2. Access model

```
studio issues link  ->  /?fos_portal=<64 hex chars>
                          |
customer opens it   ->  [fashionos_portal] shortcode renders the portal shell
                          |
React reads the token from config, strips it from the address bar,
and sends it as X-FashionOS-Portal on every /portal/* request
                          |
server hashes it    ->  fos_portal_access.token_hash (unique)
                          -> checks revocation, expiry, workspace status, customer status
                          -> PortalContext( tenant, customer, access )
```

- The token is 32 random bytes from `random_bytes()`, rendered as 64 hex characters. Revocation is a timestamp, not a delete, so the audit trail keeps a row to point at.
- The token hash is globally unique, which is why a portal request can carry nothing but its token and still identify its workspace and customer.
- Resolution refuses, with the **same** `401 portal_access_invalid`: an unknown token, a revoked link, an expired link, an inactive workspace and a missing or archived customer. One error for every failure means a caller cannot probe for the existence of a link.
- Portal routes are read-only (`GET`). There is nothing to protect with a CSRF nonce, and the portal has no WordPress session to attach one to.
- Portal pages send `nocache_headers()` and `noindex, nofollow`, because the page belongs to one customer and must not be cached or indexed.
- `last_used_at` is written at most once per hour per link, so portal reads do not cause a write on every request.

## 3. Schema

Migration `0006` adds one tenant-scoped table:

- `{prefix}fos_portal_access` — `tenant_id`, `uuid`, `client_id`, `token_hash`, `label`, `expires_at`, `revoked_at`, `last_used_at`, `created_by`, `created_at`, `updated_at`.
- Unique `uuid` and unique `token_hash`; index `(tenant_id, client_id)`.
- Optional timestamps use `CHAR(19)` defaulting to `''`, matching Phases 3–5.

See [`DATABASE_SCHEMA.md`](DATABASE_SCHEMA.md#phase-6--client-portal) for every column.

## 4. What the customer sees — and what stays private

| Shown | Hidden |
|---|---|
| Studio name and currency | Tenant id, uuid, slug, timezone, plan or internal settings |
| Customer name, reference, email, phone | Customer id, internal notes and preferences |
| Confirmed and in-progress orders; each garment's name, category, quantity and status | Draft orders, order notes, order timeline, staff names, design and measurement references |
| Issued, partially paid and paid invoices: lines, totals, paid, balance, due date | Draft and void invoices |
| Verified payments: reference, method, provider reference, amount, received time | Pending and rejected payments |
| Invoice notes (treated as the customer-facing note on the document) | Expenses, other customers, other workspaces |

A record outside the visible set answers `404`, not `403`: confirming that an invoice exists but is not theirs would leak information. Invoice `amountPaid` is recomputed from the verified payment ledger on every detail read, so the portal cannot show a stale balance.

## 5. API

All routes remain under `fashionos/v1`. Full request/response tables are in [`API_REFERENCE.md`](API_REFERENCE.md#implemented-in-phase-6).

| Area | Routes | Credential |
|---|---|---|
| Portal | `GET /portal/session`, `GET /portal/orders`, `GET /portal/orders/{id}`, `GET /portal/invoices`, `GET /portal/invoices/{id}` | `X-FashionOS-Portal` token |
| Staff sharing | `GET/POST /clients/{id}/portal-access`, `DELETE /portal-access/{id}` | Workspace header, nonce, `clients.manage` |

Portal lists page at 20 rows by default and accept at most 50. Sharing a link is customer administration, so it reuses `clients.manage` rather than adding new permission keys; no migration role re-seed is needed in this phase.

## 6. Frontend

One bundle serves both experiences, chosen at start-up:

- **Studio** — the existing application. The customer detail view gains a **Client portal** panel listing issued links with their status, last use, an issue form (label, lifetime) and a **Revoke** action. The new link is shown once with a warning.
- **Portal** — rendered when the runtime configuration carries a portal token, or when the URL carries `fos_portal`. It shows the studio header, the customer greeting and summary, then two tabs: **Orders** (list, then garments and their statuses) and **Invoices** (list, then lines, totals, balance and verified payments).
- The token is read once, removed from the address bar with `history.replaceState`, and sent only as a header. It is never written to `localStorage`.

Types live in `resources/js/types.ts`, portal calls in `createPortalClient` (`resources/js/services/api.ts`), and the development mock mirrors every portal route. Run `npm run dev` and open `/?portal=1` to review the customer view with the seeded customer, or the studio normally to issue a link from a customer record.

## 7. Templates

- `[fashionos_portal]` renders the portal shell (`templates/portal-shell.php`). It works for signed-out visitors, because the token is the credential.
- `[fashionos_app]` is unchanged and still requires a WordPress login.

The issued link points at `home_url( '/' )` with the token appended and can be redirected with the `fashionos_portal_url` filter if the site mounts the portal on a dedicated page.

## 8. Acceptance criteria

| Criterion | Where it is met |
|---|---|
| A customer sees only their own permitted information | Every portal query is filtered by the customer id taken from the resolved token, not from the request. `test_portal_reads_only_the_customers_visible_records` covers another customer's order and invoice in the same workspace. |
| Internal business data remains private | Order notes, timelines, staff names, design and measurement references, drafts, void invoices and pending payments are absent from the portal queries and presenters. The same test asserts the absent keys. |
| Links cannot be replayed from the database | Only the SHA-256 hash is stored; `test_issued_link_is_stored_as_a_hash` asserts the stored value differs from the token and equals its hash. |
| Access can be withdrawn | Revocation and expiry are checked on every request; `test_revoked_expired_and_unknown_links_are_refused` covers both, plus an unknown token. |
| Sharing is auditable and tenant-scoped | `portal.access_issued` and `portal.access_revoked` are written through the audit repository; access rows are read and revoked inside the workspace. |

## 9. Verification

Completed in this environment:

- `npm run typecheck`, `npm run lint`, `npm run format:check` and `npm test` — Vitest now runs 19 tests in two files, including the new portal client tests (token header, no nonce or workspace header, pagination, error mapping).
- A PHP syntax check over every plugin file: **111 files, 0 syntax errors**.

Not executable here (recorded honestly):

- `vendor/bin/phpunit` — no PHP runtime, Composer vendor tree or MySQL. `tests/php/PortalTest.php` covers migration `0006`, hash-only storage, token resolution, customer scoping, hidden drafts/voids/pending payments, expired and revoked links, archived customers, audit entries and tenant isolation.
- `vendor/bin/phpcs`, applying migration `0006` to MySQL, and exercising the REST routes against WordPress.
- The portal and the staff sharing panel were not re-opened in a browser after the final edits; the mock and the React code typecheck, but no visual check was made in this pass.

## 10. Known limitations

- **No email delivery.** The studio copies the link and sends it itself. Sending it belongs with the notification and queue work in Phase 7.
- **No customer WordPress accounts.** Access is a bearer link; there is no password, second factor or account recovery. A leaked link is valid until it expires or is revoked, which is why links can be revoked individually and capped at 365 days.
- **The link is shown once.** Only its hash is stored, so it cannot be re-displayed. Recovery means revoking and issuing a new link.
- **No customer edits.** The portal is read-only: briefs, approvals, appointments, reference uploads and profile changes are not implemented.
- **No briefs, approvals, appointments or notifications**, all of which appear in the roadmap's Phase 6 task list.
- **No portal read audit.** Staff writes are audited; customer reads are not logged beyond `last_used_at`, so there is no per-view history.
- **No rate limiting on token attempts.** Tokens carry 256 bits of entropy, so guessing is impractical, but there is no throttle or lockout.
- **Fixed wording.** The portal UI is English and its copy is not translatable from the interface yet.
- **One currency per workspace**, inherited from Phase 5: invoice amounts are shown in the invoice's own currency with no conversion.
- **PHP was syntax-checked only**, so migration `0006`, the routes and the test suite remain unverified at runtime (see section 9).

## 11. Next

Phase 7 (automation and PWA) can deliver the portal link by email, add reminder jobs for overdue invoices, and queue customer notifications. Customer-authored briefs and approvals would extend the same portal context with write routes, an idempotency key and an audit trail.
