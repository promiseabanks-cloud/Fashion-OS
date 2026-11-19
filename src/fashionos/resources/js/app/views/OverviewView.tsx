import type { ActiveTenant } from '../../types';
import { Panel, formatDate } from '../ui';

const PERMISSION_LABELS: Record<string, string> = {
  '*': 'Full access to every workspace permission',
  'workspace.view': 'View workspace',
  'workspace.manage': 'Manage workspace settings',
  'team.view': 'View team',
  'team.manage': 'Manage team members',
  'audit.view': 'View audit trail',
  'clients.view': 'View customers',
  'clients.manage': 'Manage customers',
  'measurements.view': 'View measurements',
  'measurements.manage': 'Manage templates and measurements',
};

/**
 * Workspace summary: who you are here, what you may do, and what comes next.
 */
export function OverviewView({ active }: { active: ActiveTenant }) {
  const permissions = active.permissions.map(
    (permission) => PERMISSION_LABELS[permission] ?? permission
  );

  return (
    <div className="fashionos-view">
      <header>
        <p className="fashionos-eyebrow">Workspace</p>
        <h1 className="fashionos-view__title">{active.tenant.name}</h1>
        <p className="fashionos-view__lead">
          You are signed in as {active.role.name.toLowerCase()} of this workspace. Everything you
          see is scoped to this business.
        </p>
      </header>

      <Panel title="At a glance">
        <dl className="fashionos-stats">
          <Stat label="Your role" value={active.role.name} detail={active.isOwner ? 'Owner' : ''} />
          <Stat
            label="Team"
            value={`${active.memberCount} ${active.memberCount === 1 ? 'member' : 'members'}`}
            detail="Including you"
          />
          <Stat label="Timezone" value={active.tenant.timezone} detail="Used for display" />
          <Stat label="Currency" value={active.tenant.currency} detail="Financial records" />
        </dl>
        <p className="fashionos-meta">
          Workspace /{active.tenant.slug} · created {formatDate(active.tenant.createdAt)}
        </p>
      </Panel>

      <Panel title="What you can do here">
        <ul className="fashionos-permissions">
          {permissions.map((permission) => (
            <li key={permission}>{permission}</li>
          ))}
        </ul>
        <p className="fashionos-hint">
          Permissions are enforced by the server on every request. Changing what is visible here
          never changes what you are allowed to do.
        </p>
      </Panel>
    </div>
  );
}

function Stat({ label, value, detail }: { label: string; value: string; detail?: string }) {
  return (
    <div className="fashionos-stat">
      <dt className="fashionos-stat__label">{label}</dt>
      <dd className="fashionos-stat__value">{value}</dd>
      {detail ? <dd className="fashionos-stat__detail">{detail}</dd> : null}
    </div>
  );
}
