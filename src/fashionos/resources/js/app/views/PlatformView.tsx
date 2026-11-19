import { useCallback, useEffect, useState } from 'react';
import type { ApiClient } from '../../services/api';
import type { PlatformTenant } from '../../types';
import { Alert, EmptyState, Loading, Panel, formatDate } from '../ui';

/**
 * Read-only platform overview for WordPress administrators.
 *
 * Each visit is audited. Tenant management actions belong to Phase 8 and are
 * deliberately not offered here.
 */
export function PlatformView({ client }: { client: ApiClient }) {
  const [tenants, setTenants] = useState<PlatformTenant[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const result = await client.platformTenants();
      setTenants(result.tenants);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Platform workspaces could not be loaded.');
    }
  }, [client]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="fashionos-view">
      <header>
        <p className="fashionos-eyebrow">Platform</p>
        <h1 className="fashionos-view__title">Workspaces</h1>
        <p className="fashionos-view__lead">
          Every business on this installation. Opening this page is recorded in the platform audit
          trail.
        </p>
      </header>

      {error ? <Alert>{error}</Alert> : null}

      <Panel>
        {tenants === null ? (
          <Loading label="Loading workspaces…" />
        ) : tenants.length === 0 ? (
          <EmptyState title="No workspaces yet" />
        ) : (
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Workspace</th>
                  <th scope="col">Status</th>
                  <th scope="col">Members</th>
                  <th scope="col">Created</th>
                </tr>
              </thead>
              <tbody>
                {tenants.map((tenant) => (
                  <tr key={tenant.id}>
                    <td>
                      <span className="fashionos-table__strong">{tenant.name}</span>
                      <span className="fashionos-table__meta">/{tenant.slug}</span>
                    </td>
                    <td>
                      <span className="fashionos-tag fashionos-tag--muted">{tenant.status}</span>
                    </td>
                    <td>{tenant.memberCount}</td>
                    <td className="fashionos-table__muted">{formatDate(tenant.createdAt)}</td>
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
