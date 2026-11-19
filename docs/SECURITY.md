# Security, Privacy and Data Integrity

> Extracted from Section 14 of the [Master Technical Specification](../FashionOS_Master_Technical_Specification.md). That document remains the authoritative source.

Security is foundational, not a final polish phase.

## Backend security

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

## Tenant security

- Scope every tenant-owned query.
- Check tenant ownership on reads, updates, deletes and file access.
- Validate tenant context in jobs and webhooks.
- Ensure cache keys include tenant identity.
- Test cross-tenant access attempts.
- Audit privileged platform access.

> See Section 7 of the Master Technical Specification for the full multi-tenant architecture and isolation rules. Tenant context must be resolved on the server from authenticated membership and the selected workspace — a tenant identifier supplied by the browser is never sufficient proof of access. Do not rely on frontend route hiding or obscured IDs as security.

## File security

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

## Financial integrity

- Use decimal monetary values.
- Verify payment webhooks.
- Make webhook handling idempotent.
- Preserve payment references.
- Record reversals and refunds as auditable events.
- Never treat frontend success as proof of payment.
- Keep financial records traceable.

## Privacy and compliance

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

## Related

- Data model and tenant-scoping conventions: [`DATABASE_SCHEMA.md`](DATABASE_SCHEMA.md).
- API authentication and permission callbacks: [`API_REFERENCE.md`](API_REFERENCE.md).
- Security-relevant test areas (tenant isolation, files, finance, queue): [`DEVELOPMENT_ROADMAP.md`](DEVELOPMENT_ROADMAP.md) and Section 16 of the Master Technical Specification.
