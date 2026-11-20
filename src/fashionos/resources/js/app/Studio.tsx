import { useState } from 'react';
import type { ApiClient } from '../services/api';
import type { MeResponse } from '../types';
import { CreateWorkspaceForm } from './WorkspaceSetup';
import { AuditView } from './views/AuditView';
import { ClientsView } from './views/ClientsView';
import { DesignsView } from './views/DesignsView';
import { FinanceView } from './views/FinanceView';
import { MaterialsView } from './views/MaterialsView';
import { OrdersView } from './views/OrdersView';
import { OverviewView } from './views/OverviewView';
import { ProductionView } from './views/ProductionView';
import { PlatformView } from './views/PlatformView';
import { QuotationsView } from './views/QuotationsView';
import { SettingsView } from './views/SettingsView';
import { TeamView } from './views/TeamView';
import { TemplatesView } from './views/TemplatesView';

type View =
  | 'overview'
  | 'clients'
  | 'designs'
  | 'templates'
  | 'quotations'
  | 'orders'
  | 'production'
  | 'materials'
  | 'finance'
  | 'team'
  | 'settings'
  | 'audit'
  | 'platform';

/**
 * The studio shell: workspace switcher, permission-aware navigation and views.
 *
 * Navigation visibility is a convenience only; each endpoint re-checks the
 * caller's permissions on the server.
 */
export function Studio({
  client,
  session,
  logoutUrl,
  onSelectWorkspace,
  onRefresh,
}: {
  client: ApiClient;
  session: MeResponse;
  logoutUrl: string;
  onSelectWorkspace: (workspaceId: number) => void;
  onRefresh: () => void;
}) {
  const active = session.activeTenant;
  const [view, setView] = useState<View>('overview');
  const [creating, setCreating] = useState(false);

  if (!active) {
    return null;
  }

  const can = (permission: string) =>
    active.permissions.includes('*') || active.permissions.includes(permission);

  const navItems: Array<{ id: View; label: string }> = [{ id: 'overview', label: 'Overview' }];

  if (can('clients.view')) {
    navItems.push({ id: 'clients', label: 'Clients' });
  }

  if (can('designs.view')) {
    navItems.push({ id: 'designs', label: 'Designs' });
  }

  if (can('measurements.view')) {
    navItems.push({ id: 'templates', label: 'Measurement templates' });
  }

  if (can('quotations.view')) {
    navItems.push({ id: 'quotations', label: 'Quotations' });
  }

  if (can('orders.view')) {
    navItems.push({ id: 'orders', label: 'Orders' });
  }

  if (can('production.view')) {
    navItems.push({ id: 'production', label: 'Production' });
  }

  if (can('materials.view')) {
    navItems.push({ id: 'materials', label: 'Materials' });
  }

  if (can('invoices.view')) {
    navItems.push({ id: 'finance', label: 'Finance' });
  }

  if (can('team.view')) {
    navItems.push({ id: 'team', label: 'Team' });
  }

  if (can('workspace.manage')) {
    navItems.push({ id: 'settings', label: 'Workspace settings' });
  }

  if (can('audit.view')) {
    navItems.push({ id: 'audit', label: 'Audit trail' });
  }

  if (session.user.canManagePlatform) {
    navItems.push({ id: 'platform', label: 'Platform' });
  }

  const current = navItems.some((item) => item.id === view) ? view : 'overview';

  return (
    <div className="fashionos-app">
      <a className="fashionos-skip" href="#fashionos-main">
        Skip to content
      </a>

      <header className="fashionos-topbar">
        <div className="fashionos-brand">
          <span className="fashionos-brand__mark" aria-hidden="true">
            F
          </span>
          <span className="fashionos-brand__name">FashionOS</span>
        </div>

        <div className="fashionos-topbar__tools">
          <label className="fashionos-picker">
            <span className="fashionos-picker__label">Workspace</span>
            <select
              className="fashionos-select fashionos-select--compact"
              value={active.tenant.id}
              onChange={(event) => onSelectWorkspace(Number(event.target.value))}
            >
              {session.workspaces.map((workspace) => (
                <option key={workspace.id} value={workspace.id}>
                  {workspace.name}
                </option>
              ))}
            </select>
          </label>

          <div className="fashionos-account">
            <span className="fashionos-account__name">{session.user.displayName}</span>
            <a className="fashionos-account__link" href={logoutUrl}>
              Sign out
            </a>
          </div>
        </div>
      </header>

      <div className="fashionos-body">
        <nav className="fashionos-nav" aria-label="Studio sections">
          <p className="fashionos-nav__heading">Studio</p>
          <ul className="fashionos-nav__list">
            {navItems.map((item) => (
              <li key={item.id}>
                <button
                  className={`fashionos-nav__item${
                    item.id === current ? ' fashionos-nav__item--active' : ''
                  }`}
                  type="button"
                  aria-current={item.id === current ? 'page' : undefined}
                  onClick={() => setView(item.id)}
                >
                  {item.label}
                </button>
              </li>
            ))}
          </ul>

          <button
            className="fashionos-nav__create"
            type="button"
            onClick={() => setCreating((value) => !value)}
          >
            + New workspace
          </button>

          <p className="fashionos-nav__note">
            {active.tenant.name} · {active.role.name}
          </p>
        </nav>

        <main className="fashionos-main" id="fashionos-main">
          {creating ? (
            <section className="fashionos-panel" aria-labelledby="fashionos-new-workspace">
              <div className="fashionos-panel__header">
                <h2 className="fashionos-panel__title" id="fashionos-new-workspace">
                  New workspace
                </h2>
              </div>
              <CreateWorkspaceForm
                client={client}
                onCreated={(workspaceId) => {
                  setCreating(false);
                  onSelectWorkspace(workspaceId);
                }}
                onCancel={() => setCreating(false)}
              />
            </section>
          ) : null}

          {current === 'overview' ? <OverviewView active={active} /> : null}
          {current === 'clients' ? (
            <ClientsView key={`clients-${active.tenant.id}`} client={client} active={active} />
          ) : null}
          {current === 'designs' ? (
            <DesignsView key={`designs-${active.tenant.id}`} client={client} active={active} />
          ) : null}
          {current === 'templates' ? (
            <TemplatesView key={`templates-${active.tenant.id}`} client={client} active={active} />
          ) : null}
          {current === 'quotations' ? (
            <QuotationsView
              key={`quotations-${active.tenant.id}`}
              client={client}
              active={active}
            />
          ) : null}
          {current === 'orders' ? (
            <OrdersView key={`orders-${active.tenant.id}`} client={client} active={active} />
          ) : null}
          {current === 'production' ? (
            <ProductionView
              key={`production-${active.tenant.id}`}
              client={client}
              active={active}
            />
          ) : null}
          {current === 'materials' ? (
            <MaterialsView key={`materials-${active.tenant.id}`} client={client} active={active} />
          ) : null}
          {current === 'finance' ? (
            <FinanceView key={`finance-${active.tenant.id}`} client={client} active={active} />
          ) : null}
          {current === 'team' ? (
            <TeamView client={client} active={active} onChanged={onRefresh} />
          ) : null}
          {current === 'settings' ? (
            <SettingsView
              key={`settings-${active.tenant.id}`}
              client={client}
              active={active}
              onUpdated={onRefresh}
            />
          ) : null}
          {current === 'audit' ? (
            <AuditView key={`audit-${active.tenant.id}`} client={client} active={active} />
          ) : null}
          {current === 'platform' ? <PlatformView client={client} /> : null}
        </main>
      </div>
    </div>
  );
}
