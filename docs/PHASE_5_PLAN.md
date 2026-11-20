# Phase 5 — Finance

> Phase 5 of the [Development Roadmap](DEVELOPMENT_ROADMAP.md), implemented on top of Phases 1–4. The [Master Technical Specification](../FashionOS_Master_Technical_Specification.md) remains authoritative.
>
> **Status:** implemented. Frontend typecheck, lint, formatting, unit tests and production build pass. PHP was syntax-checked with a parser; it was **not executed** — no PHP interpreter or database exists in this environment, so migration `0005`, the finance REST routes and `tests/php/FinanceTest.php` are unverified at runtime.

## 1. What Phase 5 delivers

Phase 5 turns a delivered garment into money the studio can account for:

- **Invoices** (`fos_invoices`, `fos_invoice_items`): what a customer owes, priced line by line, either from an order's garments or from ad-hoc lines. Reference numbers are `I-0001`, sequential per workspace.
- **Server-computed money.** Every line total, the invoice total and the balance are computed on the server from quantity and unit price. A total sent by the browser is ignored.
- **A real invoice lifecycle.** `draft → issued → partially_paid → paid`, with `void` available while no money has been verified. `partially_paid` and `paid` are derived from payments, never set by hand.
- **Payments as their own records** (`fos_payments`): a partial payment is a row, not a rewritten total. References are `P-0001`. Methods are `cash`, `transfer`, `card` and `pos`, with an optional provider reference for a transfer or POS slip.
- **Verification.** A payment is recorded as `pending`; staff verify or reject it. Only verified money moves the balance, so a mistyped amount cannot silently change what a customer owes.
- **Idempotency.** Every payment carries an idempotency key, unique per workspace. Submitting the same key twice returns the existing payment with `200` instead of recording the money twice.
- **Reconciled balances.** Verifying a payment recomputes the invoice's `amount_paid`, `balance` and derived status inside the same transaction. Overpayment is refused, including when two payments race to settle the same invoice.
- **Expenses and categories** (`fos_expenses`): money leaving the studio, grouped into `materials`, `rent`, `salaries`, `utilities`, `transport`, `marketing`, `equipment` and `other`. Expenses are voided, never deleted, so the history survives.
- **Financial position** (`GET /finance/summary`): outstanding balance, open invoiced total, overdue invoices, draft count, money collected this month, expenses this month, payments awaiting verification, and short lists of recent payments and overdue invoices.
- **A printable invoice.** The invoice document is rendered in the Finance view and printed through the browser's print stylesheet, which is the Phase 3 plan's stated first step for PDF output. Nothing else on screen is printed.
- **Permissions and audit.** Six new keys: `invoices.view`, `invoices.manage`, `payments.view`, `payments.manage`, `expenses.view`, `expenses.manage`, granted to the existing system roles and re-seeded by migration `0005`. Every write is audited (`invoice.*`, `payment.*`, `expense.*`).

Phase 5 exits when payments are verified and idempotent, financial balances reconcile, and the audit history is preserved.

## 2. Schema

Migration `0005` adds four tenant-scoped tables and no foreign keys, matching the earlier phases:

- `fos_invoices` — header with `reference`, `client_id`, optional `order_id`, currency, status, issue/due dates, `subtotal`, `total`, `amount_paid` and `balance`.
- `fos_invoice_items` — priced lines with quantity and unit price, ordered by `sort_order`.
- `fos_payments` — amount, method, provider details, status, `idempotency_key`, `paid_at` and verification stamps. Unique `(tenant_id, idempotency_key)`.
- `fos_expenses` — category, description, amount, currency, `spent_at` and status.

Optional dates use `CHAR(10)` and optional timestamps `CHAR(19)`, both defaulting to `''` instead of `NULL`, as in Phases 3 and 4. Money is `DECIMAL(14,2)`, following the specification's guidance for balances. See [`DATABASE_SCHEMA.md`](DATABASE_SCHEMA.md) for every column and index.

## 3. Roles and permissions

| Permission | Owner | Manager | Designer | Tailor / production | Finance |
|---|---|---|---|---|---|
| `invoices.view` | yes | yes | no | no | yes |
| `invoices.manage` | yes | yes | no | no | yes |
| `payments.view` | yes | yes | no | no | yes |
| `payments.manage` | yes | yes | no | no | yes |
| `expenses.view` | yes | yes | no | no | yes |
| `expenses.manage` | yes | yes | no | no | yes |

Designers and production staff keep their existing access but see no financial records: a designer's visibility of quotations does not extend to what the customer has paid. Finance holds the finance keys plus the read-only permissions it already had on clients, quotations, orders and production.

## 4. Models and rules

### Invoices

- An invoice needs a customer. It may cite an order, which must belong to that same customer (`invoice_order_mismatch` otherwise).
- Creating from an order copies every garment into a line. Explicit lines replace the copy; the order itself is never modified.
- Lines are validated like quotation lines: description required (≤191), whole quantity 1–9999, price 0–9,999,999.99, at most 100 lines.
- The issue date defaults to today; a due date cannot be before the issue date.
- Lines may be replaced only while the invoice is a `draft` (`invoice_locked` after that). Replacing them recomputes `subtotal`, `total`, `amount_paid` and `balance` from the new lines and the verified payments in the same transaction. A changed invoice is voided and reissued so its number still means one agreed document.
- Issuing requires a positive total (`invoice_total_required`).
- `draft → issued|void` and `issued → void` are the only manual moves. `partially_paid` and `paid` are written by payment verification. A void invoice cannot be reopened (`invoice_void`), and an invoice with verified money cannot be voided (`invoice_has_payments`).

### Payments

- A payment needs an invoice that is `issued` or `partially_paid`; a draft is refused with `invoice_not_issued`, a void invoice with `invoice_void`, and a settled one with `invoice_not_payable`.
- The amount must be greater than zero and no more than the outstanding balance (`payment_exceeds_balance`).
- Methods are `cash`, `transfer`, `card` and `pos`. Provider and provider reference are optional strings for reconciliation against a bank or POS statement.
- A payment is recorded as `pending`. Verifying stamps `verified_by`/`verified_at` and recomputes the invoice; rejecting leaves the balance untouched. Verification requires the invoice still to be `issued` or `partially_paid`, so a late verification cannot revive a voided invoice.
- `verified` and `rejected` are terminal (`payment_locked`). A refund or a correction is a future flow with its own model, not an edit to decided history.
- The idempotency key is supplied by the caller or generated by the server. The unique index on `(tenant_id, idempotency_key)` makes a repeated callback return the original payment.

### Expenses

- Categories are a fixed list, so the summary groups spending without guessing. Descriptions are required (≤191) and amounts must be positive.
- `spent_at` defaults to today. Voiding keeps the row and removes it from every total (`expense_void` refuses later edits).

### Financial summary

Every figure is computed from the rows at read time:

- **Outstanding** — sum of `balance` on `issued` and `partially_paid` invoices.
- **Invoiced and open** — sum of `total` on the same invoices.
- **Overdue** — open invoices with a due date before today.
- **Collected this month** — verified payments with a `paid_at` in the current month.
- **Expenses this month** — recorded expenses with `spent_at` in the current month.
- **Awaiting verification** — count and total of `pending` payments.

## 5. API

All routes are under `fashionos/v1`, use the workspace header and nonce, and check a permission before the service runs. Full request/response tables and error codes are in [`API_REFERENCE.md`](API_REFERENCE.md#implemented-in-phase-5).

| Area | Routes |
|---|---|
| Invoices | `GET/POST /invoices`, `GET/PATCH /invoices/{id}` |
| Payments | `GET/POST /payments`, `GET/PATCH /payments/{id}` |
| Expenses | `GET/POST /expenses`, `GET/PATCH /expenses/{id}` |
| Dashboard | `GET /finance/summary` |

Errors follow the existing envelope: `{ success: false, error: { code, message, details? } }` with 404 for missing tenant records, 422 for validation and 409 for state conflicts.

## 6. Frontend

One **Finance** view, reachable from the main navigation when `invoices.view` is granted:

- **Today** — outstanding balance, money collected this month, expenses this month, overdue count, payments awaiting verification and draft count.
- **Overdue invoices** — a short list, shown only when something is late.
- **Invoices** — search and status filter, a builder that copies an order's garments or prices lines directly, and a detail view with the printable document, its lines, totals and payment history.
- **Payments** — record an amount with method and provider reference; verify or reject a pending payment. The form generates one idempotency key per open, so a double submit does not duplicate the payment.
- **Expenses** — category filter, recording form and a Void action.

Types live in `resources/js/types.ts`, all calls in `resources/js/services/api.ts`, and the development mock mirrors every route so the UI can be exercised without WordPress.

## 7. Acceptance criteria

| Criterion | Where it is met |
|---|---|
| Payments are verified and idempotent | Payments start `pending` and only verification moves the balance; `(tenant_id, idempotency_key)` is unique and a repeat returns the existing payment. Covered by `test_duplicate_payments_are_idempotent`. |
| Financial balances reconcile | `amount_paid`, `balance` and status are recomputed from verified payments inside the verification transaction; overpayment is refused at recording and at verification. Covered by `test_balances_reconcile_across_partial_payments`. |
| Audit history is preserved | `invoice.created`, `invoice.updated`, `payment.recorded`, `payment.verified`, `payment.rejected`, `expense.recorded` and `expense.updated` are written through the audit repository; voided records keep their rows. |

## 8. Verification

Completed in this environment:

- `npm run format:check`, `npm run typecheck`, `npm run lint`, `npm test` (Vitest, including the new finance API-client tests) and `npm run build`.
- A PHP syntax check over every plugin PHP file, including the new services, repositories, controllers, migration and test suite.
- The development mock was exercised in a browser: the Finance view, invoice detail, payment recording and verification, and expense recording all render and update without console errors.

Not executable here (recorded honestly):

- `vendor/bin/phpunit` — no PHP runtime, Composer vendor tree or MySQL in this environment. `tests/php/FinanceTest.php` covers migration version, server-computed totals, draft-only line edits, idempotent duplicate payments, partial-payment reconciliation, overpayment and locked-payment rules, expense categories and voiding, the summary totals, tenant isolation and audit entries.
- `vendor/bin/phpcs`, applying migration `0005` to MySQL, and exercising the REST routes against WordPress.
- A real PDF file: printing relies on the browser's "Save as PDF", which was not exercised in this environment.

## 9. Known limitations

- **No payment gateway.** Verification is a staff action with an idempotency key and provider reference fields ready for a gateway callback. There is no signed webhook receiver yet; that belongs with the queue and connector work in Phases 7–8.
- **No refunds or credit notes.** Decided payments are final. A refund needs its own model so the ledger stays append-only.
- **No tax, discount or deposit rules.** `total` equals the sum of the lines; the specification's tax handling is not implemented.
- **Invoice lines are replaced, not patched.** Like quotations, sending the whole set keeps totals consistent.
- **A verified payment cannot be voided.** Correcting a mistake means a voided invoice plus a new one, or a future refund flow.
- **Expense categories are fixed.** There is no per-workspace category editor.
- **One currency per workspace.** Invoices and expenses copy the workspace currency; there is no conversion.
- **PDF is browser print.** The print stylesheet produces a clean invoice document; a server-side PDF library remains deferred.
- **The summary lists at most five recent payments and five overdue invoices.**
- **No PHP runtime or database was available**, so migration, REST and PHPUnit results remain unverified (see section 8).

## 10. Next

Phase 6 (client portal) can show a customer their own orders, invoices and payment status, using the statuses and balances already recorded here. Customer authentication and per-customer scoping are the new work; the finance records do not need reshaping.
