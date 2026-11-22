import { useCallback, useEffect, useState } from 'react';
import type { ApiClient } from '../../services/api';
import type { PortalAccess } from '../../types';
import { Alert, EmptyState, Loading, Panel, formatDate } from '../ui';

/**
 * Portal links issued to one customer.
 *
 * A link is shown once, when it is created, because only its hash is stored. An
 * existing link can be revoked but never revealed again, which is the point.
 */
export function PortalAccessPanel({
  client,
  clientId,
  canManage,
}: {
  client: ApiClient;
  clientId: number;
  canManage: boolean;
}) {
  const [links, setLinks] = useState<PortalAccess[] | null>(null);
  const [issued, setIssued] = useState<string | null>(null);
  const [label, setLabel] = useState('');
  const [days, setDays] = useState(30);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const result = await client.portalAccess(clientId);
      setLinks(result.access);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Portal links could not be loaded.');
    }
  }, [client, clientId]);

  useEffect(() => {
    void load();
  }, [load]);

  const issue = async () => {
    setBusy(true);
    setError(null);

    try {
      const result = await client.createPortalAccess(clientId, {
        label,
        expiresInDays: days,
      });

      setIssued(result.url);
      setLabel('');
      await load();
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The portal link could not be created.');
    } finally {
      setBusy(false);
    }
  };

  const revoke = async (accessId: number) => {
    setBusy(true);

    try {
      await client.revokePortalAccess(accessId);
      await load();
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The link could not be revoked.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Panel
      title="Client portal"
      description="Share a link so this customer can follow their own orders and invoices."
    >
      {error ? <Alert>{error}</Alert> : null}

      {issued ? (
        <div className="fashionos-note-block">
          <p className="fashionos-subhead">New link — copy it now</p>
          <p className="fashionos-portal__link">
            <code>{issued}</code>
          </p>
          <p className="fashionos-table__meta">
            Only a hash is stored, so this address cannot be shown again. Revoke it and issue
            another if it is lost.
          </p>
        </div>
      ) : null}

      {canManage ? (
        <div className="fashionos-actions">
          <label className="fashionos-field">
            <span className="fashionos-field__label">Label</span>
            <input
              className="fashionos-input"
              type="text"
              value={label}
              maxLength={100}
              placeholder="e.g. Wedding suit updates"
              onChange={(event) => setLabel(event.target.value)}
            />
          </label>

          <label className="fashionos-field">
            <span className="fashionos-field__label">Expires in (days)</span>
            <select
              className="fashionos-select"
              value={days}
              onChange={(event) => setDays(Number(event.target.value))}
            >
              <option value={7}>7 days</option>
              <option value={30}>30 days</option>
              <option value={90}>90 days</option>
              <option value={0}>No expiry</option>
            </select>
          </label>

          <button
            className="fashionos-btn fashionos-btn--primary"
            type="button"
            disabled={busy}
            onClick={() => void issue()}
          >
            Create portal link
          </button>
        </div>
      ) : (
        <p className="fashionos-table__meta">
          You can see whether this customer has portal access, but only a manager can share or
          revoke a link.
        </p>
      )}

      {links === null ? (
        <Loading label="Loading portal links…" />
      ) : links.length === 0 ? (
        <EmptyState
          title="No portal links yet"
          detail="The customer cannot see anything until a link is shared."
        />
      ) : (
        <div className="fashionos-table-wrap">
          <table className="fashionos-table">
            <thead>
              <tr>
                <th scope="col">Label</th>
                <th scope="col">Status</th>
                <th scope="col">Created</th>
                <th scope="col">Last used</th>
                <th scope="col">
                  <span className="fashionos-visually-hidden">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {links.map((link) => (
                <tr key={link.id}>
                  <td className="fashionos-table__strong">{link.label || 'Untitled link'}</td>
                  <td>
                    <span className="fashionos-tag">
                      {link.isActive
                        ? link.expiresAt
                          ? `Expires ${link.expiresAt}`
                          : 'Active'
                        : link.revokedAt
                          ? 'Revoked'
                          : 'Expired'}
                    </span>
                  </td>
                  <td className="fashionos-table__muted">{formatDate(link.createdAt)}</td>
                  <td className="fashionos-table__muted">
                    {link.lastUsedAt ? formatDate(link.lastUsedAt) : 'Never'}
                  </td>
                  <td className="fashionos-table__actions">
                    {canManage && link.isActive ? (
                      <button
                        className="fashionos-btn fashionos-btn--ghost"
                        type="button"
                        disabled={busy}
                        onClick={() => void revoke(link.id)}
                      >
                        Revoke
                      </button>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Panel>
  );
}
