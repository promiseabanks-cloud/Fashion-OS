# Development Roadmap

> Extracted from Sections 15, 16 and 21 of the [Master Technical Specification](../FashionOS_Master_Technical_Specification.md). That document remains the authoritative source.

Build in phases. Each phase must have a working, testable outcome before moving on.

## Phase 0: Environment and repository setup

**Tasks:**

- Inspect hosting capabilities.
- Establish local development environment.
- Create Git repository.
- Create plugin skeleton.
- Configure Vite and React/TypeScript.
- Configure coding standards and test tooling.
- Establish migration strategy.
- Create architecture decision log.
- Create development and deployment documentation.

**Exit criteria:**

- Plugin activates safely.
- React application shell loads through WordPress.
- Build assets are served correctly.
- No Node runtime is required in production.

## Phase 1: Core platform and tenancy

**Tasks:**

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

**Exit criteria:**

- A user can create a workspace.
- Multiple tenants can exist.
- Users can belong to multiple tenants.
- Cross-tenant access tests pass.

## Phase 2: CRM and measurements

**Tasks:**

- Customer CRUD.
- Customer search and pagination.
- Preferences.
- Measurement templates.
- Measurement profiles.
- Versioned measurement sessions.
- Measurement values.
- Private file handling.
- Measurement history UI.

**Exit criteria:**

- A designer can create a customer and multiple measurement profiles.
- Measurement versions remain immutable.
- An order can later reference a specific measurement version.

## Phase 3: Designs, quotations and orders

**Tasks:**

- Design records and references.
- Quotation creation and status.
- Quotation line items.
- PDF generation.
- Order creation.
- Multiple garment items per order.
- Measurement/design references.
- Order activity history.
- Customer approval flow.

**Exit criteria:**

- One order can contain multiple independently trackable garments.
- Quotations and orders remain separate.
- Order history is auditable.

## Phase 4: Production and inventory

**Tasks:**

- Configurable production stages.
- Production jobs and tasks.
- Staff assignments.
- Material catalogue.
- Material allocations and movements.
- Customer/designer material source.
- Fittings and alterations.
- Quality checks.
- Production dashboard.

**Exit criteria:**

- Each garment can move through its own production workflow.
- Material ownership/source is clear.
- Staff permissions are enforced.

## Phase 5: Finance

**Tasks:**

- Invoice generation.
- Invoice line items.
- Payment recording.
- Partial payments.
- Payment verification integration.
- Expenses and categories.
- Outstanding balances.
- Financial activity.
- PDF receipts and invoices.

**Exit criteria:**

- Payments are verified and idempotent.
- Financial balances reconcile.
- Audit history is preserved.

## Phase 6: Client portal

**Tasks:**

- Customer authentication and access.
- Tenant-branded portal.
- Customer profile.
- Design brief submission.
- Order and garment tracking.
- Approvals.
- Appointments.
- Invoice and payment visibility.
- Notifications.

**Exit criteria:**

- A customer sees only their own permitted information.
- Internal business data remains private.

## Phase 7: Automation and PWA

**Tasks:**

- Event system.
- Database-backed job queue.
- Cron processor.
- Notification delivery.
- Retry and failure handling.
- Service worker.
- IndexedDB.
- Offline drafts.
- Synchronisation and conflict handling.

**Exit criteria:**

- Jobs process reliably.
- Failed jobs are recoverable.
- Offline operations synchronise without silent data loss.

## Phase 8: SaaS administration and integrations

**Tasks:**

- Platform tenant management.
- Plans and entitlements.
- Usage limits.
- Subscription state.
- Tenant usage reporting.
- Connector plugin.
- External website authentication.
- Integration logs and revocation.
- Operational monitoring.

**Exit criteria:**

- Plans are enforced server-side.
- External websites connect securely.
- Platform administrators can manage tenants without bypassing audit controls.

## Pilot recommendation

Do not wait for all phases before testing with real businesses.

A controlled pilot can begin after the core CRM, measurements, orders, production and finance workflows are reliable. A simple customer-facing status page can precede the full portal.

Pilot with a small number of businesses, observe actual workflows, and prioritise changes based on evidence.

---

# Testing Strategy

> Section 16 of the Master Technical Specification.

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

# Definition of Done

> Section 21 of the Master Technical Specification.

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
