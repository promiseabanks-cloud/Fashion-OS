=== FashionOS ===
Contributors: fashionos
Tags: fashion, tailoring, crm, measurements, orders
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A multi-tenant business operating system for bespoke fashion designers, tailors and studios.

== Description ==

FashionOS manages the customer and garment lifecycle: enquiries, customers,
measurements, designs, quotations, orders, materials, production, fittings,
payments and delivery.

The backend is WordPress and PHP; the interface is an independent React
application served by WordPress. Production requires no Node.js runtime.

This release covers the Phase 1 foundation (workspaces, team memberships,
tenant-scoped roles and permissions, audit trail, REST API and the React studio
shell) and Phase 2: customer profiles with preferences and duplicate detection,
configurable measurement templates, measurement profiles, and append-only
measurement versions with history comparison. Order, production and finance
modules arrive in later phases as described in docs/DEVELOPMENT_ROADMAP.md.

== Installation ==

1. Build the frontend: `npm install && npm run build` inside the plugin directory.
2. Upload the plugin folder to `wp-content/plugins/fashionos`.
3. Activate the plugin in WordPress.
4. Create a page containing the `[fashionos_app]` shortcode.

== Changelog ==

= 0.3.0 =
* Phase 2: customer and measurement migration, customer CRM with search and pagination, preferences, configurable measurement templates, measurement profiles and immutable measurement versions with history comparison.

= 0.2.0 =
* Phase 1: tenancy migration, workspaces, roles and permissions, audit trail, team and platform API, studio shell.

= 0.1.0 =
* Phase 0: plugin skeleton, migration runner, REST health endpoint, React shell.
