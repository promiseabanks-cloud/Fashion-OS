# FashionOS
## Master Product, Technical Architecture & AI Implementation Specification

**Document status:** Foundational specification  
**Product:** FashionOS  
**Architecture:** Central multi-tenant SaaS on WordPress/PHP with an independently designed React application  
**Initial hosting:** Existing cPanel shared hosting  
**Primary market:** Nigeria, with an architecture that can support international expansion  
**Primary users:** Bespoke fashion designers, tailors, fashion studios and their customers  

**Document purpose:** Single source of truth for AI-assisted product design, architecture, implementation, testing and future development.

---

# 1. Instructions to the AI Development Model

You are working on **FashionOS**, a serious, modular fashion-business operating system.

You are not building a conventional WordPress website, a theme, or a basic order-tracking plugin.

Your responsibility is to implement the product described in this specification while preserving its architecture, data integrity, security, usability and long-term maintainability.

## 1.1 Non-negotiable working rules

1. Read this entire specification before proposing or changing architecture.
2. Treat this document as the foundational source of truth. Do not silently replace its decisions with your own preferences.
3. Do not attempt to build the entire product in one response or one code-generation pass.
4. Work in small, independently testable modules and phases.
5. Before implementing a module, identify its dependencies, database changes, API contracts, permissions, UI states and acceptance criteria.
6. Do not invent requirements where the specification is silent. Identify the gap and propose a decision.
7. Do not rewrite working modules merely to make them stylistically different.
8. Do not introduce a new framework, service, package or paid infrastructure dependency without explaining why it is necessary.
9. Keep the production hosting model compatible with ordinary cPanel shared hosting for the initial release.
10. Do not require a continuously running Node.js server in production.
11. Do not put business logic in React components.
12. Do not bypass the PHP backend or access the database directly from the browser.
13. Never compromise tenant isolation, authorization, payment integrity or customer privacy for development convenience.
14. Every database schema change must have a migration.
15. Every module must include meaningful tests and documented acceptance criteria.
16. Keep the code readable, modular, documented and suitable for another developer to maintain.
17. Report assumptions, limitations, incomplete work and test results honestly.
18. Do not claim that a feature is complete unless its implementation and tests support that claim.

## 1.2 Required workflow for every implementation task

For each task:

1. Read the relevant sections of this specification and inspect the existing codebase.
2. Summarize the intended change and identify dependencies.
3. Identify affected files, database tables, API endpoints and permissions.
4. Propose the smallest safe implementation plan.
5. Implement the change without disturbing unrelated modules.
6. Add or update migrations, tests, validation and documentation.
7. Run available tests, static checks and build checks.
8. Review tenant isolation and authorization implications.
9. Report files changed, decisions made, tests run, results and remaining risks.

If a requirement conflicts with existing code, stop and explain the conflict before making a destructive change.

---

# 2. Product Overview

FashionOS is a comprehensive business management platform for fashion designers and tailoring businesses.

It should help a fashion business manage the complete customer and garment lifecycle, from first enquiry to customer onboarding, measurements, design, quotation, order, materials, production, fitting, payment and delivery.

The product should reduce manual administration, improve customer communication, make production easier to track and give business owners a clear view of operations and finances.

FashionOS is intended to become a complete operating system for fashion businesses, not merely a measurement book or order tracker.

## 2.1 Product principles

- **Business-first:** Features must solve real operational problems.
- **Garment-aware:** A customer order can contain several garments, each with its own requirements and production lifecycle.
- **Customer-aware:** Customer history, measurements, preferences, conversations and orders should be connected.
- **Production-aware:** The platform must represent how garments are actually made, fitted, altered and completed.
- **Financially accurate:** Quotations, invoices, payments, expenses and balances must remain distinct and auditable.
- **Mobile-first:** Core operations must work comfortably on phones.
- **Offline-capable:** Selected workflows should support safe offline drafting and later synchronisation.
- **Configurable:** Fashion businesses differ. Templates, stages, roles, measurement fields and workflows should be configurable where appropriate.
- **Extensible:** The system should support external website integrations and future services without redesigning its core.
- **Accessible:** Use clear language, consistent navigation, readable typography, keyboard support and appropriate contrast.

## 2.2 Target users and roles

1. **Platform administrator:** Manages the FashionOS platform, tenants, plans, platform configuration and operational health.
2. **Business owner:** Owns a fashion business workspace and controls business settings, staff and financial access.
3. **Manager:** Manages day-to-day operations according to assigned permissions.
4. **Designer:** Handles customer briefs, designs, measurements and garment specifications.
5. **Tailor/production staff:** Works on assigned production tasks and updates progress.
6. **Finance staff:** Manages quotations, invoices, payments and expenses within assigned permissions.
7. **Customer:** Uses a branded client portal to view relevant information, submit details, review approvals and track orders.

A user may belong to more than one business. Their role and permissions may differ in each business.

## 2.3 Initial commercial model

The intended model is freemium:

- Free/basic plan for small or new businesses.
- Paid professional plan.
- Higher-tier studio/team plan.

Exact pricing, quotas and feature limits are commercial decisions to validate. Do not hardcode illustrative prices into application logic.

A central entitlement service must determine whether a tenant can use a feature or has reached a usage limit.

---

# 3. Product Scope and Functional Modules

The following describes the intended product. Implementation should follow the phased roadmap in Section 15.

## 3.1 Business workspace and onboarding

- Create a business workspace.
- Configure business name, logo, contact details, address and timezone.
- Configure currency and date preferences.
- Configure brand colours and client portal identity.
- Invite staff and assign roles.
- Configure measurement templates and garment categories.
- Configure production stages.
- Configure notification preferences.
- Configure invoice numbering and business details.
- View plan, usage and subscription status.

Onboarding should guide a new designer through the minimum setup required to begin using the system. Do not force completion of every optional setting before the user can create a customer.

## 3.2 Customer relationship management (CRM)

Each customer should have a central profile containing:

- Name and contact information.
- Customer reference number.
- Communication preferences.
- Style and fit preferences.
- Relevant notes.
- Measurement profiles and history.
- Design briefs and reference images.
- Quotations and orders.
- Invoices and payment history.
- Fittings and appointments.
- Relevant customer-facing activity.

Support search, filtering, sorting, pagination, customer status and duplicate detection.

Do not expose private internal notes to customers.

## 3.3 Customer onboarding and design brief

Allow a designer or customer to capture:

- Garment type and occasion.
- Preferred style and fit.
- Fabric/material information.
- Colour and design preferences.
- Reference images and documents.
- Requested delivery date.
- Special instructions.
- Designer notes.
- Approval status.

The customer may submit a brief through a branded portal or an integrated external website.

Distinguish customer-provided information from designer-authored internal notes.

## 3.4 Measurements

Measurements are a core product capability.

Requirements:

- Multiple measurement profiles per customer.
- Configurable measurement templates by garment category.
- Configurable measurement fields and labels.
- Units such as centimetres and inches.
- Record who took the measurements and when.
- Measurement notes and fit observations.
- Measurement history and version comparison.
- Optional customer-specific measurement instructions.
- Link the exact measurement version used by an order garment.
- Support future measurement diagrams or guided capture interfaces.

**Never overwrite a historical measurement version.** A new measurement session creates a new version. Existing orders retain their selected measurement version unless an authorized user explicitly changes it through a recorded action.

The system must not assume that one universal set of body measurements works for every garment.

## 3.5 Designs and references

- Create design records.
- Associate designs with customers and garment categories.
- Attach sketches, reference images and documents.
- Record design notes and specifications.
- Track design review and approval.
- Associate a design with one or more order garments where appropriate.
- Maintain revision history for important design changes.

AI may assist with extracting information from briefs or images, but it must not invent confirmed garment specifications. AI-generated suggestions require human review.

## 3.6 Quotations and orders

A quotation is a proposed commercial offer. An order is an accepted business commitment. They must be separate records.

Quotation capabilities:

- Create quotations for customers.
- Add multiple line items.
- Apply discounts and taxes where configured.
- Set expiry dates.
- Generate a PDF.
- Send to the customer.
- Record acceptance, rejection or expiry.

Order capabilities:

- Create an order from an accepted quotation or directly.
- Generate a unique human-readable order number.
- Add multiple garments.
- Set requested and promised dates.
- Record deposit/payment terms.
- Record material responsibility.
- Track order-level and garment-level statuses.
- Record customer approvals and important changes.
- Maintain an activity timeline.

**A garment must be a first-class entity within an order.** An order may contain multiple garments. Each garment can have its own design, measurement version, materials, production job, fitting, alterations, quality checks and completion status.

Do not reduce all garment details to a single free-text order description.

## 3.7 Materials and inventory

Support:

- Fabric and material catalogue.
- Accessories and consumables.
- Units of measure.
- Quantity on hand.
- Reorder thresholds.
- Stock adjustments.
- Material allocation to a garment.
- Material movement history.
- Supplier details as a future extension.
- Material cost tracking where applicable.

Every allocation must identify the supply source:

- `customer_supplied`
- `designer_supplied`
- `shared_supply`

Customer-supplied fabric must not be counted as designer-owned stock.

## 3.8 Production management

Production must be configurable to match different business workflows.

Capabilities:

- Production jobs per garment.
- Configurable production stages.
- Task assignments.
- Staff workload views.
- Start and due dates.
- Task status and completion history.
- Production notes and attachments.
- Material readiness.
- Fittings.
- Alterations.
- Quality control.
- Completion and delivery readiness.

Possible stages include design confirmation, material preparation, cutting, sewing, finishing, fitting, alterations, quality control and ready for collection. These are examples, not fixed universal stages.

The designer should be able to configure stage names, order and applicability.

## 3.9 Fittings and appointments

- Schedule appointments.
- Associate appointments with a customer, order or garment.
- Assign staff.
- Record location or meeting details.
- Send reminders.
- Record attendance and outcome.
- Capture alteration requirements.
- Reschedule and cancel with history.

## 3.10 Finance

Finance must distinguish:

- Quotations.
- Invoices.
- Invoice line items.
- Payments.
- Partial payments.
- Outstanding balances.
- Refunds or reversals where supported.
- Expenses.
- Expense categories.
- Financial summaries.

Rules:

- Use decimal database types for money, never floating-point.
- An invoice is not a payment.
- A payment must be traceable to an invoice or another documented receivable.
- Payment status must be confirmed by the backend.
- Verify payment-provider webhooks server-side.
- Make webhook processing idempotent.
- Do not store raw card details.
- Preserve financial audit history.
- Do not mark a payment as confirmed while offline.

Support manual payment recording with appropriate permissions and audit history, alongside future payment-provider integrations.

## 3.11 Customer portal

Every business should have a branded client portal.

Customers should be able to:

- Authenticate securely.
- View their own profile.
- Submit or update permitted customer information.
- Submit design briefs and reference files.
- View relevant quotations.
- Review and approve permitted design/order details.
- Track their orders and garment progress.
- View fitting appointments.
- View invoices and payment history.
- Receive relevant notifications.

Customers must never access another customer's records or a business's internal notes, staff data or restricted financial information.

## 3.12 Notifications and communication

Support in-app notifications and an extensible delivery architecture for email and other channels.

Potential events:

- Customer invitation.
- Quotation sent or expiring.
- Order created.
- Production stage completed.
- Fitting scheduled or changed.
- Invoice due or overdue.
- Payment confirmed.
- Order ready for collection.
- Order delivered.

Record delivery attempts and failures. Do not block normal business operations because a notification provider is temporarily unavailable.

## 3.13 Reports and dashboard

Potential dashboard metrics:

- Active orders.
- Orders due soon.
- Delayed orders.
- Production workload.
- Outstanding invoices.
- Payments received.
- Expenses.
- Revenue summaries.
- Customer growth.
- Garment category performance.
- Staff workload.

Reports must respect tenant scope and user permissions. Clearly distinguish cash received from invoiced revenue and other accounting concepts.

## 3.14 Automation and intelligence

Build an event-driven automation foundation.

Example event names:

- `client.created`
- `measurement.version.created`
- `quotation.sent`
- `quotation.accepted`
- `order.created`
- `material.required`
- `production.stage.completed`
- `fitting.scheduled`
- `invoice.overdue`
- `payment.confirmed`
- `order.delivered`

Automation examples:

- Send an onboarding invitation.
- Remind a customer to provide missing information.
- Remind staff about material readiness.
- Notify a customer of a production milestone.
- Schedule a fitting reminder.
- Queue an invoice reminder.
- Notify the designer when an order is ready for review.

AI capabilities may later include:

- Extracting structured information from design briefs.
- Drafting garment specifications from customer-provided information.
- Identifying missing information.
- Drafting customer messages.
- Summarising order history.
- Highlighting operational patterns.

AI must not invent measurements, confirm payments, approve designs, change delivery commitments or perform consequential actions without explicit human approval.

---

# 4. Confirmed Technical Architecture

## 4.1 Decisions

| Area | Decision |
|---|---|
| Hosting | Existing cPanel shared hosting initially |
| Backend runtime | WordPress and PHP |
| Database | Existing MySQL/MariaDB |
| Frontend | React + TypeScript |
| Frontend integration | React application embedded/served by WordPress |
| Build tooling | Vite |
| Styling | Tailwind CSS or an equivalent controlled design system |
| API | WordPress REST API under `fashionos/v1` |
| Deployment | One central WordPress installation serving multiple businesses |
| Tenancy | Shared database with strict tenant isolation |
| Authentication | WordPress session and REST nonce for same-site app |
| Offline | PWA, service worker and IndexedDB |
| Background jobs | Database-backed queue triggered by cPanel Cron |
| External WordPress integration | Separate lightweight connector plugin |
| AI | External API calls from the server when introduced |

## 4.2 High-level request flow

1. User opens the FashionOS application URL.
2. WordPress serves the application shell and compiled React assets.
3. React communicates with the WordPress REST API over HTTPS.
4. PHP authenticates the request and resolves the user's active tenant.
5. Permission checks authorize the requested operation.
6. A module service applies business rules.
7. A repository performs tenant-scoped database operations.
8. The API returns a consistent response.
9. Events and jobs are recorded for relevant follow-up work.

React must never connect directly to the database.

## 4.3 Hosting constraint

The production deployment must not require a continuously running Node.js server.

Use Node/Vite locally or in a build environment to compile frontend assets. Deploy the compiled JavaScript, CSS and manifest files with the plugin.

The initial shared-hosting environment is for a controlled pilot and modest early usage. It is not a promise of unlimited scale. Keep infrastructure-dependent operations behind service interfaces so the application can later migrate to a VPS or other environment without rewriting business modules.

## 4.4 Suggested hosting baseline

Confirm actual limits with the hosting provider.

- PHP 8.3+ preferred.
- Supported modern MySQL or MariaDB.
- HTTPS.
- 256 MB PHP memory preferred if available.
- Upload size of 32 MB or higher if available.
- Adequate execution time for ordinary requests.
- cPanel Cron access.
- SFTP access.
- Adequate disk space and database limits.
- Automated backups with an independent copy and tested restoration.

Do not assume these resources are available until verified.

---

# 5. WordPress Plugin Architecture

Build the main application as one modular plugin named `fashionos`.

Do not build the product as a conventional WordPress theme or a large collection of unrelated plugins.

Suggested structure:

```text
fashionos/
├── fashionos.php
├── uninstall.php
├── readme.txt
├── app/
│   ├── Core/
│   ├── Tenancy/
│   ├── Auth/
│   ├── API/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Responses/
│   ├── Database/
│   │   ├── Migrations/
│   │   ├── Repositories/
│   │   └── Seeders/
│   ├── Modules/
│   │   ├── Clients/
│   │   ├── Measurements/
│   │   ├── Designs/
│   │   ├── Orders/
│   │   ├── Production/
│   │   ├── Inventory/
│   │   ├── Finance/
│   │   ├── Appointments/
│   │   ├── Notifications/
│   │   ├── Automation/
│   │   ├── Reports/
│   │   └── Subscriptions/
│   ├── Services/
│   ├── Security/
│   └── Support/
├── resources/
│   ├── js/
│   │   ├── app/
│   │   ├── components/
│   │   ├── layouts/
│   │   ├── modules/
│   │   ├── hooks/
│   │   ├── services/
│   │   ├── routes/
│   │   └── types/
│   └── css/
├── public/
│   ├── build/
│   ├── manifest.json
│   └── service-worker.js
├── templates/
│   ├── app-shell.php
│   ├── portal-shell.php
│   └── emails/
└── languages/
```

## 5.1 Backend separation

- **Controllers:** HTTP/API request handling and response formatting.
- **Services:** Business rules and application workflows.
- **Repositories:** Database reads and writes.
- **Requests/Validators:** Input validation.
- **Policies/Permissions:** Authorization.
- **Events:** Business event definitions and dispatch.
- **Jobs:** Deferred work.
- **Migrations:** Database schema changes.

A controller should not contain complex business logic or raw SQL for unrelated modules.

Modules should communicate through defined services or events rather than reaching into each other's internal tables wherever practical.

## 5.2 React application structure

```text
resources/js/
├── app/
│   ├── App.tsx
│   ├── router.tsx
│   └── providers.tsx
├── components/
│   ├── ui/
│   ├── forms/
│   ├── tables/
│   ├── modals/
│   └── feedback/
├── layouts/
│   ├── AuthLayout.tsx
│   ├── StudioLayout.tsx
│   ├── PortalLayout.tsx
│   └── AdminLayout.tsx
├── modules/
│   ├── dashboard/
│   ├── clients/
│   ├── measurements/
│   ├── designs/
│   ├── orders/
│   ├── production/
│   ├── inventory/
│   ├── finance/
│   ├── appointments/
│   └── settings/
├── services/
│   ├── api.ts
│   ├── auth.ts
│   └── uploads.ts
├── hooks/
├── types/
└── utils/
```

Use a central API client, typed API contracts, reusable UI components and consistent loading, empty, error and success states.

A query/cache library such as TanStack Query may be used if justified. Avoid premature complexity in global state management.

---

# 6. Creative and Visual Design Direction

## 6.1 Fundamental design requirement

**FashionOS must not look or feel like WordPress.**

WordPress is the backend runtime and deployment host. It must not dictate the visual language, layout system, component design or interaction patterns of the product.

The React application should be designed as a premium, independent software product with its own design system.

Do not use the WordPress admin interface as the primary user experience.

Do not constrain the interface to WordPress admin styles, Gutenberg layouts, WordPress dashboard conventions or theme templates.

## 6.2 Creative freedom

The design should be as creative, polished and expressive as the product's purpose allows while remaining usable for real business operations.

Explore:

- Strong editorial layouts.
- Fashion-inspired visual storytelling.
- Distinctive typography.
- Refined colour systems.
- Rich but restrained surfaces.
- Thoughtful motion and transitions.
- Contextual dashboards.
- Visually clear production timelines.
- Elegant measurement interfaces.
- Highly usable data tables and filters.
- Responsive navigation.
- Carefully designed empty states.
- Microinteractions that clarify state and provide feedback.
- Different visual treatments for designer, staff and customer experiences.

Do not make the application look like a generic admin template or an off-the-shelf SaaS dashboard.

Do not add animation merely for decoration. Motion should explain state changes, hierarchy, navigation or feedback. Respect reduced-motion preferences.

## 6.3 Design-system implementation

Create a reusable design system before building many screens.

It should define:

- Design tokens for colour, typography, spacing, radii, elevation and motion.
- Typography scale.
- Buttons and interaction states.
- Form controls.
- Navigation.
- Cards and panels.
- Tables and data lists.
- Dialogs and drawers.
- Toasts and alerts.
- Status indicators.
- Empty and loading states.
- Responsive breakpoints.
- Accessibility and focus states.

Keep visual tokens independent from business logic. Tenant branding should be applied through controlled theme variables, not arbitrary user-provided CSS.

## 6.4 UX principles

- Mobile-first, not desktop-only.
- Fast access to frequent tasks.
- Clear hierarchy and restrained density.
- Avoid unnecessary steps in common workflows.
- Keep complex forms logically grouped.
- Save drafts where appropriate.
- Clearly distinguish internal notes from customer-visible content.
- Always show whether an operation is saved, pending or failed.
- Provide confirmation for destructive or consequential actions.
- Preserve user input when validation fails.
- Use clear, non-technical language in customer-facing interfaces.

The AI model should propose visual directions and screen layouts, but must not sacrifice workflow clarity for novelty.

---

# 7. Multi-Tenant Architecture and Isolation

One central WordPress installation serves multiple independent businesses.

Each tenant has isolated:

- Customers.
- Measurement profiles and versions.
- Designs and references.
- Quotations and orders.
- Garments.
- Production jobs and tasks.
- Inventory.
- Appointments.
- Invoices and payments.
- Expenses.
- Notifications.
- Settings.
- Staff memberships and permissions.

## 7.1 Tenant identity

Use native WordPress users for authentication identities and a custom membership table to associate users with tenants.

A user may belong to multiple tenants with different roles.

Tenant context must be resolved on the server from authenticated membership and the selected workspace. A tenant identifier supplied by the browser is never sufficient proof of access.

## 7.2 Tenant isolation rules

- Every tenant-owned record must have `tenant_id`.
- Every tenant-owned query must scope by `tenant_id`.
- Every update and delete must verify tenant ownership.
- Every file download must verify tenant and record permissions.
- Every background job must carry and validate tenant context.
- Every report and export must be tenant-scoped.
- Every cache key containing tenant data must include tenant identity.
- Cross-tenant access must be explicitly tested.
- Platform administrator access must be separate, deliberate and audited.

Do not rely on frontend route hiding or obscured IDs as security.

Do not use WordPress Multisite initially. Use one WordPress site and one custom tenant layer.

---

# 8. Database Architecture

Use custom MySQL/MariaDB tables for FashionOS business data. Use WordPress native tables for user identities, authentication and suitable WordPress infrastructure.

Use the configured WordPress database prefix. Never hardcode `wp_`.

Example: `$wpdb->prefix . 'fos_orders'`.

Use `dbDelta()` or an equivalent carefully tested migration process for table installation and schema updates.

## 8.1 Database conventions

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

## 8.2 Platform and tenant tables

| Table | Important fields | Purpose |
|---|---|---|
| `fos_tenants` | id, uuid, name, slug, status, timezone, currency, logo_id, created_at | Business workspaces |
| `fos_memberships` | id, tenant_id, user_id, role_id, status | User-to-tenant membership |
| `fos_roles` | id, tenant_id, name, permissions_json | Tenant-specific roles |
| `fos_plans` | id, name, limits_json, features_json, status | Subscription plans |
| `fos_subscriptions` | id, tenant_id, plan_id, status, starts_at, ends_at | Subscription state |
| `fos_settings` | id, tenant_id, setting_key, setting_value | Tenant configuration |

## 8.3 Customer and measurement tables

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

## 8.4 Design and order tables

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

## 8.5 Production and inventory tables

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

## 8.6 Finance tables

| Table | Important fields | Purpose |
|---|---|---|
| `fos_invoices` | id, tenant_id, client_id, order_id, invoice_number, subtotal, total, due_date, status | Invoices |
| `fos_invoice_items` | id, tenant_id, invoice_id, description, quantity, unit_price, total | Invoice lines |
| `fos_payments` | id, tenant_id, invoice_id, amount, payment_method, reference, status, paid_at | Payment records |
| `fos_expense_categories` | id, tenant_id, name | Expense categories |
| `fos_expenses` | id, tenant_id, category_id, description, amount, expense_date | Business expenses |
| `fos_financial_events` | id, tenant_id, event_type, reference_type, reference_id, amount | Financial activity |

Design payment allocation carefully if a payment may cover multiple invoices or an invoice may receive multiple payments. Do not hardwire a one-payment-to-one-invoice assumption if the product requirements evolve to support more complex reconciliation. Model allocations explicitly when needed.

## 8.7 Automation and system tables

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

# 9. API Architecture

Use the WordPress REST API namespace:

`/wp-json/fashionos/v1/`

Example route groups:

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

Use a consistent response structure.

Success:

```json
{
  "success": true,
  "data": {},
  "meta": {}
}
```

Error:

```json
{
  "success": false,
  "error": {
    "code": "RESOURCE_NOT_FOUND",
    "message": "The requested record could not be found."
  }
}
```

Every route must define:

- Authentication requirements.
- Permission callback.
- Tenant resolution.
- Input schema and validation.
- Pagination rules where applicable.
- Response shape.
- Error behaviour.
- Audit/event requirements.

For the same-site React application, use WordPress authenticated session cookies and REST API nonces appropriately. A nonce is not a permission check.

Use server-to-server authentication for external WordPress connectors. Never put permanent integration secrets in browser JavaScript.

---

# 10. Application Routes and Experiences

Suggested application routes:

```text
/login
/register
/onboarding

/admin
/admin/tenants
/admin/plans
/admin/system

/studio/:slug/dashboard
/studio/:slug/clients
/studio/:slug/clients/:id
/studio/:slug/measurements
/studio/:slug/designs
/studio/:slug/orders
/studio/:slug/orders/:id
/studio/:slug/production
/studio/:slug/inventory
/studio/:slug/appointments
/studio/:slug/finance
/studio/:slug/invoices
/studio/:slug/expenses
/studio/:slug/reports
/studio/:slug/team
/studio/:slug/settings

/portal/:slug
/portal/:slug/orders
/portal/:slug/orders/:id
/portal/:slug/profile
```

The server must validate the selected tenant and user permissions. Route visibility is not security.

Build distinct experiences for:

- Platform administration.
- Business owner/manager.
- Production staff.
- Finance staff.
- Customer portal.

Do not create one overloaded dashboard full of conditional buttons.

---

# 11. PWA and Offline Architecture

Use a service worker for application shell caching and IndexedDB for selected local records and queued operations.

Offline capabilities may include:

- Viewing recently loaded, permitted records.
- Drafting measurement sessions.
- Drafting orders.
- Recording selected production updates.
- Queuing reference image uploads.
- Synchronising queued changes when connectivity returns.

Do not promise that every part of the application works offline.

## 11.1 Offline operation record

Each queued operation should include:

- Unique operation ID.
- Entity type and entity identifier.
- Operation type.
- Intended changes.
- Original record version.
- Creation timestamp.
- Synchronisation state.
- Retry metadata.

## 11.2 Synchronisation rules

- Server remains the source of truth.
- Detect version conflicts.
- Never silently overwrite concurrent edits.
- Preserve measurement history.
- Do not confirm payments offline.
- Do not issue authoritative invoice numbers offline.
- Do not send notifications until server processing succeeds.
- Make retries idempotent.
- Show pending, synchronised, failed and conflict states clearly.

Protect cached sensitive records. Provide logout/cache-clearing behaviour and avoid storing credentials or unnecessary private data in browser storage.

---

# 12. Automation and Background Jobs

Use a database-backed queue. Do not require Redis, RabbitMQ or a permanently running worker for the initial shared-hosting release.

## 12.1 Job lifecycle

Suggested statuses:

- `pending`
- `processing`
- `completed`
- `failed`
- `cancelled`

A job should include:

- ID.
- Tenant ID.
- Job type.
- JSON payload.
- Status.
- Attempt count.
- Available time.
- Lock time.
- Last error.
- Creation and update timestamps.

The processor should:

1. Fetch a small batch of due jobs.
2. Claim jobs safely to prevent duplicate execution.
3. Execute within a short time budget.
4. Record success or failure.
5. Retry transient failures with backoff.
6. Stop retrying after a configured threshold.
7. Expose failed jobs for review.

## 12.2 Event recording

Where possible, write the business change and its event/job record in the same database transaction. This avoids a saved order with a lost follow-up event.

## 12.3 cPanel Cron

Use cPanel Cron to trigger the queue processor periodically, subject to the host's minimum interval and resource policy.

Prefer a secured PHP CLI entry point if supported. Otherwise use a protected endpoint with a strong secret that is never exposed to the browser.

Do not run long jobs in normal customer-facing requests. Keep batch sizes and execution time small.

---

# 13. External WordPress Website Integration

Build a separate lightweight plugin named `fashionos-connector` after the central application is stable.

Its role is to connect a designer's existing WordPress website to their central FashionOS workspace.

Potential capabilities:

- Connect/disconnect a FashionOS tenant.
- Server-to-server authentication.
- Shortcodes and Gutenberg blocks.
- Customer onboarding forms.
- Design submission forms.
- Booking interfaces.
- Order tracking.
- Branded portal entry points.

Example shortcodes:

```text
[fashionos_client_portal]
[fashionos_order_tracking]
[fashionos_booking_form]
[fashionos_design_submission]
```

The central FashionOS application remains the source of truth.

Do not install the complete FashionOS engine on every connected website.

The connector must not expose permanent secrets in frontend code. Use revocable credentials, hashed secret storage, secure token exchange and authorization checks.

Avoid relying on cross-domain WordPress cookies for authentication. Treat external integrations as server-to-server connections.

---

# 14. Security, Privacy and Data Integrity

Security is foundational, not a final polish phase.

## 14.1 Backend security

- Use prepared database queries.
- Validate all incoming values.
- Sanitize data at appropriate boundaries.
- Escape output in the correct context.
- Use capability and permission checks.
- Protect state-changing requests against CSRF.
- Apply rate limits or abuse controls to sensitive public endpoints where practical.
- Use secure password and account recovery practices through WordPress.
- Do not expose internal errors or secrets.
- Keep API keys and provider credentials server-side.
- Log important security-sensitive operations.

## 14.2 Tenant security

- Scope every tenant-owned query.
- Check tenant ownership on reads, updates, deletes and file access.
- Validate tenant context in jobs and webhooks.
- Ensure cache keys include tenant identity.
- Test cross-tenant access attempts.
- Audit privileged platform access.

## 14.3 File security

Customer measurements, photos and design references can be sensitive.

- Do not assume WordPress public media URLs are appropriate for private files.
- Store file metadata and tenant ownership.
- Verify authorization before download.
- Use private storage outside the public web root where hosting permits.
- If not possible, use an authenticated download controller with strict access checks.
- Validate file types and size.
- Generate safe filenames.
- Avoid executing uploaded files.
- Consider malware scanning where a suitable service is available.
- Do not expose private file paths or predictable identifiers.

## 14.4 Financial integrity

- Use decimal monetary values.
- Verify payment webhooks.
- Make webhook handling idempotent.
- Preserve payment references.
- Record reversals and refunds as auditable events.
- Never treat frontend success as proof of payment.
- Keep financial records traceable.

## 14.5 Privacy and compliance

Review the Nigerian Data Protection Act 2023 and applicable regulations before public launch. Establish:

- Privacy notice and lawful processing basis.
- Data minimisation.
- Access controls.
- Retention and deletion rules.
- Customer data export process.
- Incident response.
- Backup protection.
- Vendor and processor review.
- Appropriate consent and communication preferences.

Do not claim legal compliance solely because these controls have been implemented. Obtain appropriate legal/privacy review.

---

# 15. Development Roadmap and Release Gates

Build in phases. Each phase must have a working, testable outcome before moving on.

## Phase 0: Environment and repository setup

Tasks:

- Inspect hosting capabilities.
- Establish local development environment.
- Create Git repository.
- Create plugin skeleton.
- Configure Vite and React/TypeScript.
- Configure coding standards and test tooling.
- Establish migration strategy.
- Create architecture decision log.
- Create development and deployment documentation.

Exit criteria:

- Plugin activates safely.
- React application shell loads through WordPress.
- Build assets are served correctly.
- No Node runtime is required in production.

## Phase 1: Core platform and tenancy

Tasks:

- Plugin bootstrap and service registration.
- Database installer and migration versioning.
- Tenant registration and workspace creation.
- WordPress authentication integration.
- Membership and role model.
- Tenant resolver and context.
- Permission policies.
- Base API infrastructure.
- Audit log.
- Platform and studio application shells.

Exit criteria:

- A user can create a workspace.
- Multiple tenants can exist.
- Users can belong to multiple tenants.
- Cross-tenant access tests pass.

## Phase 2: CRM and measurements

Tasks:

- Customer CRUD.
- Customer search and pagination.
- Preferences.
- Measurement templates.
- Measurement profiles.
- Versioned measurement sessions.
- Measurement values.
- Private file handling.
- Measurement history UI.

Exit criteria:

- A designer can create a customer and multiple measurement profiles.
- Measurement versions remain immutable.
- An order can later reference a specific measurement version.

## Phase 3: Designs, quotations and orders

Tasks:

- Design records and references.
- Quotation creation and status.
- Quotation line items.
- PDF generation.
- Order creation.
- Multiple garment items per order.
- Measurement/design references.
- Order activity history.
- Customer approval flow.

Exit criteria:

- One order can contain multiple independently trackable garments.
- Quotations and orders remain separate.
- Order history is auditable.

## Phase 4: Production and inventory

Tasks:

- Configurable production stages.
- Production jobs and tasks.
- Staff assignments.
- Material catalogue.
- Material allocations and movements.
- Customer/designer material source.
- Fittings and alterations.
- Quality checks.
- Production dashboard.

Exit criteria:

- Each garment can move through its own production workflow.
- Material ownership/source is clear.
- Staff permissions are enforced.

## Phase 5: Finance

Tasks:

- Invoice generation.
- Invoice line items.
- Payment recording.
- Partial payments.
- Payment verification integration.
- Expenses and categories.
- Outstanding balances.
- Financial activity.
- PDF receipts and invoices.

Exit criteria:

- Payments are verified and idempotent.
- Financial balances reconcile.
- Audit history is preserved.

## Phase 6: Client portal

Tasks:

- Customer authentication and access.
- Tenant-branded portal.
- Customer profile.
- Design brief submission.
- Order and garment tracking.
- Approvals.
- Appointments.
- Invoice and payment visibility.
- Notifications.

Exit criteria:

- A customer sees only their own permitted information.
- Internal business data remains private.

## Phase 7: Automation and PWA

Tasks:

- Event system.
- Database-backed job queue.
- Cron processor.
- Notification delivery.
- Retry and failure handling.
- Service worker.
- IndexedDB.
- Offline drafts.
- Synchronisation and conflict handling.

Exit criteria:

- Jobs process reliably.
- Failed jobs are recoverable.
- Offline operations synchronise without silent data loss.

## Phase 8: SaaS administration and integrations

Tasks:

- Platform tenant management.
- Plans and entitlements.
- Usage limits.
- Subscription state.
- Tenant usage reporting.
- Connector plugin.
- External website authentication.
- Integration logs and revocation.
- Operational monitoring.

Exit criteria:

- Plans are enforced server-side.
- External websites connect securely.
- Platform administrators can manage tenants without bypassing audit controls.

## Pilot recommendation

Do not wait for all phases before testing with real businesses.

A controlled pilot can begin after the core CRM, measurements, orders, production and finance workflows are reliable. A simple customer-facing status page can precede the full portal.

Pilot with a small number of businesses, observe actual workflows, and prioritise changes based on evidence.

---

# 16. Testing Strategy

Testing must cover business behaviour, security and data integrity.

## Required test areas

| Area | Required verification |
|---|---|
| Authentication | Logged-out users cannot access private records |
| Tenant isolation | Tenant A cannot retrieve or modify Tenant B data |
| Membership | A user's permissions differ correctly by tenant |
| Measurements | Historical versions remain unchanged |
| Templates | Historical data remains interpretable after template changes |
| Orders | Multiple garments have independent progress |
| Production | Tasks and assignments obey permissions |
| Inventory | Material movements reconcile |
| Finance | Duplicate payment callbacks do not duplicate payments |
| Files | Unauthorized users cannot retrieve private files |
| API | Invalid input and unauthorized operations are rejected |
| Offline | Conflicts are detected and not silently overwritten |
| Queue | Jobs retry safely and do not duplicate consequential actions |
| Entitlements | Plan restrictions are enforced on the backend |
| Backups | Database and files can be restored together |

Use unit tests for business services, integration tests for repositories/API workflows and end-to-end tests for critical user journeys where tooling permits.

At minimum, test with two tenants, multiple staff accounts and multiple customer accounts.

---

# 17. Shared Hosting Performance Rules

1. Use tenant-scoped, indexed database queries.
2. Paginate lists.
3. Filter and sort on the server.
4. Avoid loading full histories into dashboards.
5. Cache only where tenant isolation and invalidation are clear.
6. Keep image sizes appropriate for the display context.
7. Generate complex reports in background jobs.
8. Avoid long-running requests.
9. Avoid unnecessary plugins and runtime dependencies.
10. Avoid WebSocket infrastructure in the first release.
11. Use external AI services through server-side requests when introduced.
12. Maintain independent backups.
13. Monitor database growth, file storage, PHP errors and job failures.
14. Keep the application portable to a future VPS or dedicated environment.

Do not claim unlimited scale on shared hosting. When real usage approaches host resource limits, measure the bottleneck and plan an infrastructure migration.

---

# 18. Deployment and Operations

## 18.1 Development

- Develop locally.
- Use Git branches for meaningful features.
- Keep secrets out of the repository.
- Use environment-specific configuration.
- Build frontend assets with Vite.
- Run tests and build checks before deployment.

## 18.2 Production deployment

- Upload the plugin and compiled assets.
- Run database migrations through a controlled upgrade process.
- Verify application health.
- Verify authentication and tenant isolation.
- Verify cron processing.
- Verify file permissions and private file delivery.
- Check logs.
- Keep a rollback plan.

Do not make schema changes manually in phpMyAdmin as the normal development process. Use migrations.

## 18.3 Backups

Back up:

- Database.
- Uploaded/private files.
- Application configuration and required secrets through an appropriate secure process.
- Deployment/version information.

Keep an independent backup copy. Test restoration periodically. A backup that has never been restored is not a verified recovery plan.

## 18.4 Monitoring

At minimum, record:

- PHP/application errors.
- Failed background jobs.
- Failed notification deliveries.
- Payment webhook failures.
- Authentication and permission anomalies.
- Storage usage.
- Database growth.
- Slow API operations.

Do not log passwords, access tokens, full payment credentials or unnecessary sensitive customer information.

---

# 19. External Services and Future Extensions

Keep integrations behind service interfaces so providers can change without rewriting business modules.

Potential future integrations:

- Payment providers operating in Nigeria and other supported markets.
- Email delivery providers.
- WhatsApp or other messaging services where official APIs and applicable policies permit.
- AI model APIs.
- Object/file storage.
- Analytics and reporting services.
- Accounting/export tools.
- Calendar integrations.

Do not assume any integration is free, available on every hosting plan or approved for every use case. Validate pricing, regional availability, provider terms and technical requirements before committing.

The core product must remain useful when optional external services are unavailable.

---

# 20. Architecture Decisions That Must Not Be Changed Casually

| Decision | Rationale |
|---|---|
| WordPress is the backend runtime, not the UI design system | Allows a fully independent React experience |
| One central installation initially | Lower maintenance and simpler SaaS operations |
| Custom tenant layer, not Multisite | Better control over shared application data |
| Custom business tables | Structured transactional data and query control |
| React + TypeScript | Rich, maintainable application UI |
| Vite build assets | No production Node server requirement |
| Garment is a first-class order item | Different garments have different measurements and workflows |
| Measurement versioning | Protects historical accuracy |
| Server-side authorization | Frontend visibility is not security |
| Event/job architecture | Enables automation without always-on infrastructure |
| External connector plugin | Keeps connected websites lightweight |
| Service interfaces around infrastructure | Supports future hosting migration |

Any proposed change to these decisions must include:

1. The problem with the current decision.
2. The alternative.
3. Benefits and trade-offs.
4. Migration impact.
5. Hosting and cost impact.
6. Security and data implications.
7. A clear recommendation.

---

# 21. Definition of Done

A module is not complete merely because a screen exists.

A module is complete when:

- Its user journey works.
- Its backend business rules are implemented.
- Its database schema and migrations are complete.
- Its API contracts are documented.
- Its permissions are enforced server-side.
- Its tenant isolation is tested.
- Its loading, empty, error and success states exist.
- Its mobile layout works.
- Its validation is meaningful.
- Its audit/event behaviour is implemented where required.
- Its automated tests pass.
- Its documentation is updated.
- Its known limitations are recorded.

---

# 22. Immediate First Task for the AI Model

Do not start by implementing every FashionOS feature.

Start with **Phase 0: Environment and Repository Setup**.

First inspect the existing repository and hosting constraints. Then produce:

1. A concise repository and environment assessment.
2. The proposed exact plugin skeleton.
3. The React/Vite/TypeScript build configuration.
4. The WordPress application-shell integration.
5. The database migration framework design.
6. The initial coding and testing conventions.
7. A list of hosting facts that must be verified.
8. A Phase 0 implementation plan with acceptance criteria.

After approval, implement Phase 0 only.

Once Phase 0 passes its acceptance criteria, proceed to Phase 1. Do not skip ahead or generate placeholder implementations for every module.

---

# 23. Project Success Measures

The product should ultimately be judged by whether it improves the operation of real fashion businesses.

Useful measures include:

- Percentage of active orders managed in FashionOS.
- Time required to onboard a customer.
- Time required to retrieve a customer's measurements.
- Percentage of orders with complete garment specifications.
- Production tasks completed on time.
- Orders delivered by promised date.
- Reduction in missed fittings and follow-ups.
- Outstanding invoice visibility.
- Customer portal adoption.
- Staff adoption and active usage.
- Reliability of offline synchronisation.
- Support incidents caused by data access or workflow errors.

Do not optimise for feature count alone. Prioritise reliable workflows that solve repeated business problems.

---

# End of Foundational Specification

This document defines the product direction and foundational technical architecture. It should evolve through deliberate, documented decisions as implementation and real-world validation progress.

Maintain a separate `CHANGELOG.md` for implementation history and an `ARCHITECTURE_DECISIONS.md` file for approved changes to foundational decisions. Keep this master specification updated when product scope or architecture materially changes.

Recommended GitHub repository structure:

```text
fashionos/
├── README.md
├── FashionOS_Master_Technical_Specification.md
├── ARCHITECTURE_DECISIONS.md
├── CHANGELOG.md
├── docs/
│   ├── DATABASE_SCHEMA.md
│   ├── API_REFERENCE.md
│   ├── SECURITY.md
│   └── DEVELOPMENT_ROADMAP.md
└── src/
```
