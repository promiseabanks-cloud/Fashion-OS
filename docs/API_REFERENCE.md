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
| `GET` | `/auth/me` | signed in | `{ user, workspaces, activeTenant, workspaceError, roles }`; errors resolving a workspace return `activeTenant: null` instead of failing the bootstrap call |
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
