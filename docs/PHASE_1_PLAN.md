# Phase 1 — Core Platform and Tenancy

> Phase 1 of the [Development Roadmap](DEVELOPMENT_ROADMAP.md), implemented on top of the Phase 0 foundation. The [Master Technical Specification](../FashionOS_Master_Technical_Specification.md) remains authoritative.
>
> **Status:** implemented. Frontend typecheck, lint, formatting, unit tests and production build pass. PHP was syntax-checked with a parser; it was **not executed** — no PHP interpreter or database exists in this environment, so activation, migrations and the REST endpoints are unverified at runtime, and the PHPUnit suite has not been run.

## 1. Fresh-eyes review of Phase 0

Phase 0 was re-read before building on it. Changes made:

| Finding | Change |
|---|---|
| `Container` existed to hold a single controller, and its `get()` escaped a string inside an exception message. | Removed `app/Core/Container.php`. `Plugin` constructs repositories, services and controllers explicitly — fewer moving parts, no global service locator. |
| `Assets` cached the manifest in a method-level `static` keyed by directory, and held a view method (`render_missing_build_notice`). | Manifest is now a per-instance property; the admin notice moved to `AppShell`, where UI concerns belong. |
| The REST namespace was a constant on `SystemController`, so every new controller would duplicate it. | Added `FashionOS\API\Rest` with the namespace, the workspace header name and the shared 401/403/404/422 error constructors. |
| Permission callbacks returned `WP_Error`, which WordPress serialised in its own format while controllers returned the documented envelope. The React client could not read those errors. | Added `ErrorEnvelope`, a single `rest_post_dispatch` filter that wraps errors from `fashionos/v1` in `{ success: false, error: { code, message } }`. |
| The Phase 0 shell was a status page with placeholder navigation. | Replaced by the real studio shell (workspace switcher, team, settings, audit, platform). The status page and its cards were removed; `GET /system/health` remains for operators. |
| The health endpoint's `Migration`-only check was the only schema signal. | Kept, and extended: the service container now also registers tenant routes, and the migration registry ships the first business migration. |

Kept as-is: the hand-rolled PSR-4 autoloader (no production Composer), the explicit migration registry, manifest-driven asset loading, the `{prefix}fos_*` table convention, the shortcode mount point, and the design-token approach (no CSS framework).

## 2. What Phase 1 delivers

- **First business migration** (`0001`) creating `fos_tenants`, `fos_roles`, `fos_memberships` and `fos_audit_logs`.
- **Workspace creation**, with the creator becoming owner inside a transaction; roles are seeded per workspace.
- **Membership and role model**: a user can belong to several workspaces with a different role in each; the owner cannot be re-roled or removed through the API.
- **Tenant resolver and context**: the server resolves the workspace from the authenticated user's membership. The `X-FashionOS-Workspace` header selects a workspace but never grants access.
- **Permission policies**: a permission catalogue, seeded role permissions and `Policy` checks used by every route's permission callback and by the controllers.
- **Base API infrastructure**: shared namespace, error constructors, consistent envelopes, JSON-body handling, validation at the service boundary.
- **Audit log** for workspace, membership and platform-administration actions, readable per workspace.
- **Studio shell**: onboarding, workspace switcher, overview, team management, settings, audit trail and a read-only platform view for WordPress administrators.
- **Cross-tenant isolation test** written for the WordPress test library — **not executed here** (no PHP runtime).

Deliberately deferred, with the phase that owns them:

- Invitations and email delivery (Phase 6 notifications; Phase 1 requires an existing WordPress account).
- Plans, subscriptions and entitlements (Phase 8).
- `fos_settings` (introduced with the settings module that uses it; workspace timezone and currency live on the tenant row today).
- Dedicated application route and client router, platform tenant management actions, rate limiting, and any Phase 2+ business table.

## 3. Schema

All tables use the configured WordPress prefix and `dbDelta()` from `Migration0001Tenancy`.

**`{prefix}fos_tenants`** — `id`, `uuid` (unique), `name`, `slug` (unique), `status`, `timezone`, `currency`, `logo_id`, `created_by`, `created_at`, `updated_at`; index on `status`.

**`{prefix}fos_roles`** — `id`, `tenant_id`, `slug`, `name`, `permissions_json`, `is_system`, `created_at`; unique `(tenant_id, slug)`, index on `tenant_id`.

**`{prefix}fos_memberships`** — `id`, `tenant_id`, `user_id`, `role_id`, `status`, `created_at`, `updated_at`; unique `(tenant_id, user_id)`, indexes on `user_id` and `(tenant_id, status)`.

**`{prefix}fos_audit_logs`** — `id`, `tenant_id`, `actor_id`, `action`, `entity_type`, `entity_id`, `metadata_json`, `created_at`; index on `(tenant_id, created_at)`, plus `actor_id` and `action`. Platform administration is recorded with `tenant_id = 0` so it never appears in a tenant's own audit view.

No foreign keys are declared: the specification warns that hosting engines may not enforce them, so ownership is enforced in the repositories instead (every tenant-owned query filters by `tenant_id`).

## 4. Roles and permissions

Roles are copied into each workspace when it is created, so the same person can hold different permissions in different businesses.

| Role | Slug | Permissions |
|---|---|---|
| Owner | `owner` | `*` (all current and future permissions) |
| Manager | `manager` | `workspace.view`, `workspace.manage`, `team.view`, `team.manage`, `audit.view` |
| Designer | `designer` | `workspace.view`, `team.view` |
| Tailor / production | `tailor` | `workspace.view` |
| Finance | `finance` | `workspace.view` |

The permission catalogue lives in `app/Tenancy/Permissions.php` and gains keys as modules are added. The wildcard avoids a data migration every time a permission is introduced. `owner` cannot be assigned through the team API; the workspace creator holds it.

`TenantContext::can()` returns false for an inactive workspace regardless of role, so suspending a workspace removes its permissions without deleting memberships.

## 5. Tenant resolution and authorization flow

1. WordPress authenticates the request (session cookie plus REST nonce).
2. The permission callback calls `TenantResolver::from_request()`.
3. The resolver reads `X-FashionOS-Workspace` (id or slug). With no selector it accepts the user's only workspace and otherwise returns `workspace_required` — ambiguity is never resolved by guessing.
4. It loads the tenant, then the user's membership **inside that tenant**. No membership or an inactive one is `403 workspace_access_denied`; an unknown tenant is `404 workspace_not_found`.
5. `Policy::allows()` checks the permission against the membership's role, and the context is stored for the route callback.
6. Services receive the verified `TenantContext`; every repository call is tenant-scoped.

Platform access is separate: `Policy::is_platform_admin()` requires WordPress `manage_options`, and `GET /platform/tenants` writes an audit entry with `tenant_id = 0`.

## 6. API

| Method | Route | Permission | Purpose |
|---|---|---|---|
| `GET` | `/auth/me` | signed in | Session bootstrap: user, workspaces, active workspace, roles |
| `GET` | `/tenants` | signed in | Workspaces the user belongs to |
| `POST` | `/tenants` | signed in | Create a workspace (creator becomes owner) |
| `GET` | `/tenants/current` | `workspace.view` | Active workspace, role, permissions, member count |
| `PATCH` | `/tenants/current` | `workspace.manage` | Update name, timezone, currency |
| `GET` | `/team` | `team.view` | Members and assignable roles |
| `POST` | `/team` | `team.manage` | Add an existing WordPress user by email |
| `PATCH` | `/team/{user_id}` | `team.manage` | Change a member's role |
| `DELETE` | `/team/{user_id}` | `team.manage` | Remove a member |
| `GET` | `/audit` | `audit.view` | Recent audit entries for the workspace |
| `GET` | `/platform/tenants` | `manage_options` | Read-only platform workspace list (audited) |
| `GET` | `/system/health` | signed in | Plugin, migration and build status |

Audit actions: `tenant.created`, `tenant.updated`, `membership.added`, `membership.role_changed`, `membership.removed`, `platform.tenants_viewed`.

## 7. Frontend

- `App` owns the session: it loads `/auth/me` with the stored workspace selector, then renders onboarding, the workspace chooser or the studio. A stale stored workspace is cleared when the server reports `workspace_not_found`.
- `Studio` renders the workspace switcher and permission-aware navigation. Hiding a link is presentation only; the server re-checks every request.
- Views: `OverviewView`, `TeamView`, `SettingsView`, `AuditView`, `PlatformView`. Each has explicit loading, empty, error and success states.
- The workspace selector is kept in `localStorage`; it is a convenience, not a security boundary.
- No router, query cache or state library was added: the shell has one fetch per view and switching workspaces reloads the session.
- `dev/mock.ts` emulates the Phase 1 endpoints in memory for `npm run dev`. It is excluded from the plugin package and is not a test of the PHP code.

## 8. Acceptance criteria

| Criterion (from the roadmap) | Status |
|---|---|
| A user can create a workspace | Implemented end to end (service, transaction, seeded roles, owner membership, audit). **Not exercised against WordPress.** |
| Multiple tenants can exist | Implemented: unique slug generation, per-tenant roles, platform list. Verified only in the dev mock. |
| Users can belong to multiple tenants | Implemented: memberships are unique per `(tenant_id, user_id)`, and the switcher lists every workspace. Not exercised in PHP. |
| Cross-tenant access tests pass | Test written (`tests/php/TenancyIsolationTest.php`) covering a foreign workspace selector and role differences. **Not executed** — requires WordPress, MySQL and PHPUnit. |

## 9. Verification

Executed in this environment:

- `npm run typecheck` — passes (strict TypeScript).
- `npm run lint` — passes.
- `npm run format:check` — passes.
- `npm test` — 4 API-client tests pass, including workspace-header selection.
- `npm run build` — succeeds; manifest and hashed assets emitted.
- PHP syntax check across all 33 PHP files — passes (parser-based, not execution).

Not executable here, therefore unverified:

- Activation, `dbDelta()` output and the migration runner against MySQL/MariaDB.
- Every REST route, including 401/403/404 behaviour and the error-envelope filter.
- The WordPress test library suite (`tests/php`), including cross-tenant isolation.
- PHP_CodeSniffer / WordPress Coding Standards (`composer lint:php`).

## 10. Known limitations

- **No invitations.** Adding a member requires a user who already has a WordPress account; the API returns `member_account_required` otherwise.
- **No self-service leave or ownership transfer.** There is exactly one owner per workspace and no transfer flow yet.
- **Transactions are only as good as the storage engine.** `TenantService` uses `START TRANSACTION`/`COMMIT`/`ROLLBACK`; the hosting database must be InnoDB. Confirm this before release (see [DEPLOYMENT.md](DEPLOYMENT.md)).
- **Platform view is read-only.** Tenant management, plans and entitlements belong to Phase 8.
- **No router or dedicated application route.** The shell still mounts through `[fashionos_app]`, so the browser URL does not change between views.
- **No rate limiting** on workspace creation. WordPress has no built-in throttle; abuse controls stay on the Phase 8/launch checklist.
- **The client stores the selected workspace id in `localStorage`.** The server validates it on every request, so this is a convenience only.

## 11. Next

Phase 2 (CRM and measurements) adds `fos_clients`, measurement templates, profiles and immutable measurement versions, plus the customer list and measurement history UI. It builds on the tenant context and audit log introduced here.

Phase 2 was implemented in [PHASE_2_PLAN.md](PHASE_2_PLAN.md), which also records the fresh-eyes review of this phase and the simplifications that came out of it.
