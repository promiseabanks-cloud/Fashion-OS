# Phase 0 — Environment and Repository Setup

> The plan required by Section 22 of the [Master Technical Specification](../FashionOS_Master_Technical_Specification.md), with results.
>
> **Status:** implemented. Frontend build, typecheck, lint and unit tests pass. PHP runtime checks (activation in WordPress, REST request, migration run) still need a WordPress host — see [Verification](#verification-and-known-limitations).

## 1. Repository and environment assessment

Before this phase the repository contained documentation only: the master specification, architecture decisions, changelog, `docs/` and an empty `src/` placeholder. There was no application code, build configuration, database schema or test tooling.

The fixed constraints that shaped the work:

- Production is cPanel shared hosting with WordPress and PHP; no Node.js runtime is allowed.
- The frontend is React + TypeScript compiled by Vite, with no direct database access.
- Business data lives in custom `fos_*` tables keyed by the WordPress prefix; WordPress provides identities only.
- Tenant isolation and server-side authorization are non-negotiable, so nothing in Phase 0 may introduce browser-trusted tenant identifiers.
- The UI must not inherit WordPress admin design; it is an independent product surface.

The build environment for this phase had Node.js 26 and npm 11 but no PHP interpreter and no database server, and root access was unavailable. PHP was therefore syntax-checked with a parser rather than executed. See [Verification](#verification-and-known-limitations).

## 2. Plugin skeleton

`src/fashionos/` is one modular plugin, following Section 5 of the specification:

```text
src/fashionos/
├── fashionos.php                  bootstrap: constants, guard, autoloader, lifecycle hooks
├── uninstall.php                  removes plugin options only; business data is retained
├── readme.txt                     WordPress plugin readme
├── composer.json                  PHP coding-standard tooling (development only)
├── phpcs.xml.dist                 WordPress Coding Standards configuration
├── package.json                   Vite / React / TypeScript tooling
├── vite.config.ts                 build, manifest and Vitest configuration
├── tsconfig.json                  strict TypeScript configuration
├── eslint.config.js               ESLint flat config
├── .prettierrc.json               Prettier options
├── .distignore                    files excluded from the deployable package
├── index.html                     standalone development preview page
├── app/
│   ├── Core/
│   │   ├── Plugin.php             boot, service registration, activation
│   │   ├── Container.php          minimal service container
│   │   ├── Assets.php             manifest-based asset loading + Vite dev server
│   │   └── AppShell.php           shortcode + signed-out handling
│   ├── API/
│   │   ├── Controllers/SystemController.php   GET /system/health
│   │   └── Responses/Response.php             success/error envelope
│   └── Database/
│       ├── Migration.php          migration contract
│       ├── Migrator.php           runner and version table
│       └── Migrations/Migrations.php          ordered registry (empty in Phase 0)
├── resources/
│   ├── js/                        React entry, shell UI, typed API client, tests
│   └── css/app.css                design tokens + shell styles
├── templates/app-shell.php        React mount point, no-JS fallback
└── public/build/                  compiled assets (generated; not committed)
```

Backend responsibilities stay separated: controllers handle HTTP, services will hold business rules, repositories will own SQL, and migrations own schema. Phase 0 deliberately ships no business module.

## 3. React/Vite/TypeScript build configuration

- Single entry `resources/js/app/main.tsx`, React 19, StrictMode, TypeScript `strict`.
- `base: './'` so asset URLs work from any plugin directory.
- `build.manifest: 'manifest.json'` and hashed filenames produce `public/build/manifest.json`, which PHP reads to enqueue the exact compiled JS and CSS.
- `emptyOutDir` keeps stale hashed files out of releases.
- No runtime dependency beyond React and React DOM; no global state library, router or query library is introduced before a phase needs one.
- `npm run dev` serves `index.html` against `dev/mock.ts`, which supplies the configuration WordPress normally injects and stubs `GET /system/health`. This lets the shell be reviewed without a WordPress install.
- Unit tests run in Vitest (Node environment); the central API client is the first tested unit.

## 4. WordPress application-shell integration

- `[fashionos_app]` renders the mount point. A page containing the shortcode becomes the application entry URL until Phase 1 adds dedicated routing.
- Anonymous visitors get a sign-in prompt linking to `wp_login_url()`; the shell is never rendered to logged-out users.
- Assets are only enqueued when the shortcode renders. Vite output is ESM, so a `script_loader_tag` filter sets `type="module"`.
- `window.FashionOS` is injected before the bundle with the REST namespace URL, a `wp_rest` nonce, home/logout URLs and the current user. The nonce guards CSRF; authorization remains a server responsibility.
- If the frontend has not been built, administrators see an explanatory notice instead of a blank page.
- Local development can point at the Vite server with `define( 'FASHIONOS_VITE_DEV', 'http://localhost:5173' );` in `wp-config.php`; production never uses this path.

## 5. Database migration framework

- `Migration` defines a zero-padded version, description and `up()`.
- `Migrations::all()` is an explicit ordered registry, so schema history is reviewable in one place and never depends on filenames.
- `Migrator` creates `{prefix}fos_migrations` with `dbDelta()`, applies only versions that are not recorded, inserts a row per applied migration, and stores the resulting version in `fashionos_db_version`.
- The plugin activation hook runs `migrate()`, making activation and upgrades safe to repeat.
- Migrations are forward-only. Historical measurement and financial records require deliberate, documented changes rather than rollbacks.
- Phase 0 registers no business migrations; Phase 1 adds the first one (platform and tenancy tables).

## 6. Initial coding and testing conventions

- PHP 8.1+, WordPress Coding Standards via `composer lint:php` (configured; not executed in this environment).
- PSR-4 class autoloading from `app/`, hand-rolled so production does not need Composer.
- All PHP files guard with `ABSPATH`; all output escaped; all input validated at the controller boundary.
- Every tenant-owned table carries `tenant_id`; every query, job and file download is tenant-scoped. This is enforced in later phases and tested with two tenants.
- API responses use the `{ success, data, meta }` / `{ success, error }` envelope from `Response`.
- TypeScript strict mode, ESLint + Prettier, no business logic in components.
- Tests: Vitest for frontend logic; PHP unit/integration tests arrive with the first business module (Phase 1) using the WordPress test library.

## 7. Hosting facts that must be verified before deployment

These are assumptions from the specification, not confirmed hosting capabilities:

- PHP version (8.3 preferred, 8.1 minimum) and available extensions (`mysqli`, `mbstring`, `json`, `gd`/`imagick` if images are processed).
- MySQL/MariaDB version, `dbDelta` behaviour, InnoDB availability and foreign-key enforcement.
- PHP memory limit (256 MB preferred), upload size (32 MB preferred), max execution time.
- Cron availability and minimum interval; whether PHP CLI can be invoked.
- SFTP access and writable private storage outside the web root.
- HTTPS with a valid certificate for the application and portal origins.
- Backup frequency, retention and a tested restore path.
- Disk and database quotas, plus provider policies on email sending.

## 8. Acceptance criteria

| Criterion (from the roadmap) | Status |
|---|---|
| Plugin activates safely | Code complete; **not executed** here (no PHP/WordPress). Activation only runs `dbDelta` and options writes, and fails non-fatally when requirements are unmet. |
| React application shell loads through WordPress | Integration complete (shortcode, manifest assets, config injection, sign-in gate); **not executed** against WordPress. The same shell was built and served through Vite. |
| Build assets are served correctly | `npm run build` emits `public/build/manifest.json` plus hashed JS/CSS, and PHP resolves both from the manifest. Verified locally at the build level. |
| No Node runtime required in production | Satisfied: WordPress enqueues pre-built files; Node is used only to build. |

## Verification and known limitations

Executed in this environment:

- `npm run typecheck` — passes (TypeScript strict).
- `npm test` — 3 API-client tests pass.
- `npm run build` — succeeds; manifest and hashed assets generated.
- `npm run lint` — passes.
- PHP syntax check across all plugin PHP files — passes (parser-based).

Not executable here, and therefore unverified:

- Plugin activation, migration run and `GET /fashionos/v1/system/health` inside a real WordPress install.
- PHP_CodeSniffer / WordPress Coding Standards (`composer lint:php`) and the `phpcs.xml.dist` ruleset.
- PHP unit tests (no WordPress test library or PHP runtime available).

Known gaps, intentional for Phase 0:

- No dedicated application route: the shell is mounted through a shortcode until Phase 1 introduces routing and the studio layout.
- The design system exists as tokens plus shell components only; it expands in Phase 1 before many screens are built.
- No router, query cache, offline support, queue or business modules yet — each belongs to a later phase.
