# FashionOS

**A comprehensive, multi-tenant business operating system for bespoke fashion designers, tailors and fashion studios.**

FashionOS helps a fashion business manage the complete customer and garment lifecycle — from first enquiry through customer onboarding, measurements, design, quotation, order, materials, production, fitting, payment and delivery. It aims to reduce manual administration, improve customer communication, make production easier to track, and give business owners a clear view of operations and finances.

FashionOS is intended to become a complete operating system for fashion businesses, not merely a measurement book or order tracker.

> **Status:** Foundational specification. Implementation follows the phased roadmap in [`docs/DEVELOPMENT_ROADMAP.md`](docs/DEVELOPMENT_ROADMAP.md). The current first task is **Phase 0: Environment and Repository Setup**.

---

## Product principles

- **Business-first** — features must solve real operational problems.
- **Garment-aware** — an order can contain several garments, each with its own requirements and production lifecycle.
- **Customer-aware** — customer history, measurements, preferences and orders stay connected.
- **Production-aware** — the platform represents how garments are actually made, fitted, altered and completed.
- **Financially accurate** — quotations, invoices, payments, expenses and balances stay distinct and auditable.
- **Mobile-first** — core operations work comfortably on phones.
- **Offline-capable** — selected workflows support safe offline drafting and later synchronisation.
- **Configurable** — templates, stages, roles, measurement fields and workflows are configurable where appropriate.
- **Extensible** — supports external website integrations and future services without redesigning the core.
- **Accessible** — clear language, consistent navigation, readable typography, keyboard support and appropriate contrast.

## Users and roles

Platform administrator · Business owner · Manager · Designer · Tailor / production staff · Finance staff · Customer (branded portal). A single user may belong to more than one business, with a different role in each.

## Commercial model

Intended freemium model — free/basic, paid professional, and higher-tier studio/team plans. Pricing and quotas are commercial decisions to validate; they are not hardcoded into application logic. A central entitlement service determines whether a tenant can use a feature or has reached a usage limit.

---

## Architecture at a glance

| Area | Decision |
|---|---|
| Hosting (initial) | Existing cPanel shared hosting |
| Backend runtime | WordPress + PHP |
| Database | MySQL / MariaDB (custom `fos_*` tables) |
| Frontend | React + TypeScript, built with Vite |
| API | WordPress REST API under `fashionos/v1` |
| Tenancy | One central install, shared database, strict tenant isolation (custom tenant layer, **not** Multisite) |
| Offline | PWA, service worker, IndexedDB |
| Background jobs | Database-backed queue triggered by cPanel Cron |

Key constraints: **production must not require a continuously running Node.js server** (Vite builds assets ahead of deployment), **React must never talk to the database directly**, and **WordPress is the runtime, not the UI design system** — the React application is designed as a premium, independent product.

See the architecture decisions that must not be changed casually in [`ARCHITECTURE_DECISIONS.md`](ARCHITECTURE_DECISIONS.md).

---

## Repository structure

```text
fashionos/
├── README.md                                   ← this file
├── FashionOS_Master_Technical_Specification.md ← full source-of-truth spec
├── ARCHITECTURE_DECISIONS.md                   ← foundational decisions + change log
├── CHANGELOG.md                                ← implementation history
├── docs/
│   ├── DATABASE_SCHEMA.md                       ← data model and conventions
│   ├── API_REFERENCE.md                         ← REST API architecture
│   ├── SECURITY.md                              ← security, privacy and data integrity
│   └── DEVELOPMENT_ROADMAP.md                   ← phased roadmap, testing, definition of done
└── src/                                         ← implementation code (to come)
```

## Documentation

- **[Master Technical Specification](FashionOS_Master_Technical_Specification.md)** — the complete, authoritative specification and single source of truth.
- **[Database Schema](docs/DATABASE_SCHEMA.md)** — tables, conventions and isolation rules.
- **[API Reference](docs/API_REFERENCE.md)** — REST namespace, route groups and response contracts.
- **[Security](docs/SECURITY.md)** — backend, tenant, file and financial security, plus privacy and compliance.
- **[Development Roadmap](docs/DEVELOPMENT_ROADMAP.md)** — phases and release gates, testing strategy, and the definition of done.
- **[Architecture Decisions](ARCHITECTURE_DECISIONS.md)** — decisions that require a documented change process.

---

## Primary market

Nigeria, with an architecture that can support international expansion. Review the Nigerian Data Protection Act 2023 and applicable regulations before public launch (see [`docs/SECURITY.md`](docs/SECURITY.md)).
