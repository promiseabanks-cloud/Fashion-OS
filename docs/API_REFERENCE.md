# API Reference

> Extracted from Section 9 of the [Master Technical Specification](../FashionOS_Master_Technical_Specification.md). That document remains the authoritative source.

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

Use a consistent response structure.

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
