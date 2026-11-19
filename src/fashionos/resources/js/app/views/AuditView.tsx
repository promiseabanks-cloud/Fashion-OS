import { useCallback, useEffect, useState } from 'react';
import type { ApiClient } from '../../services/api';
import type { ActiveTenant, AuditEntry } from '../../types';
import { Alert, EmptyState, Loading, Panel, formatDate } from '../ui';

const ACTION_LABELS: Record<string, string> = {
  'tenant.created': 'Workspace created',
  'tenant.updated': 'Workspace settings changed',
  'membership.added': 'Team member added',
  'membership.role_changed': 'Team role changed',
  'membership.removed': 'Team member removed',
  'client.created': 'Customer created',
  'client.updated': 'Customer updated',
  'measurement.template.created': 'Measurement template created',
  'measurement.template.updated': 'Measurement template updated',
  'measurement.profile.created': 'Measurement profile created',
  'measurement.version.created': 'Measurements recorded',
};

/**
 * The workspace audit trail. Entries are append-only and tenant-scoped; platform
 * administration is recorded outside any tenant and never appears here.
 */
export function AuditView({ client, active }: { client: ApiClient; active: ActiveTenant }) {
  const [entries, setEntries] = useState<AuditEntry[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const result = await client.audit();
      setEntries(result.entries);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The audit trail could not be loaded.');
    }
  }, [client]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="fashionos-view">
      <header>
        <p className="fashionos-eyebrow">Traceability</p>
        <h1 className="fashionos-view__title">Audit trail</h1>
        <p className="fashionos-view__lead">
          Security-relevant changes in {active.tenant.name}, newest first.
        </p>
      </header>

      {error ? <Alert>{error}</Alert> : null}

      <Panel>
        {entries === null ? (
          <Loading label="Loading the audit trail…" />
        ) : entries.length === 0 ? (
          <EmptyState
            title="Nothing recorded yet"
            detail="Creating a workspace, changing settings and managing the team all appear here."
          />
        ) : (
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Action</th>
                  <th scope="col">By</th>
                  <th scope="col">When</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((entry) => (
                  <tr key={entry.id}>
                    <td>
                      <span className="fashionos-table__strong">
                        {ACTION_LABELS[entry.action] ?? entry.action}
                      </span>
                      <span className="fashionos-table__meta">{describe(entry)}</span>
                    </td>
                    <td>{entry.actorName || 'System'}</td>
                    <td className="fashionos-table__muted">{formatDate(entry.createdAt)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </div>
  );
}

function describe(entry: AuditEntry): string {
  const metadata = entry.metadata ?? {};
  const parts = Object.entries(metadata)
    .filter(([, value]) => value !== null && value !== undefined && value !== '')
    .map(([key, value]) => `${key}: ${String(value)}`);

  return parts.length > 0 ? parts.join(' · ') : entry.action;
}
