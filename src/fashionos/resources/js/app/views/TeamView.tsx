import { useCallback, useEffect, useState, type FormEvent } from 'react';
import type { ApiClient } from '../../services/api';
import type { ActiveTenant, TeamResponse } from '../../types';
import { Alert, EmptyState, Loading, Panel, formatDate } from '../ui';

/**
 * Team management for the active workspace.
 *
 * Adding a member requires an existing WordPress account: invitations are a
 * separate feature (Phase 6 notifications) and are not implied here.
 */
export function TeamView({
  client,
  active,
  onChanged,
}: {
  client: ApiClient;
  active: ActiveTenant;
  onChanged: () => void;
}) {
  const [team, setTeam] = useState<TeamResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [email, setEmail] = useState('');
  const [role, setRole] = useState('');

  const canManage = active.permissions.includes('*') || active.permissions.includes('team.manage');

  const load = useCallback(async () => {
    try {
      const result = await client.team();
      setTeam(result);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The team could not be loaded.');
    }
  }, [client]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (!role && team && team.roles.length > 0) {
      setRole(team.roles[0].slug);
    }
  }, [role, team]);

  const addMember = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setBusy(true);
    setError(null);

    try {
      await client.addMember({ email, role });
      setEmail('');
      await load();
      onChanged();
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The member could not be added.');
    } finally {
      setBusy(false);
    }
  };

  const changeRole = async (userId: number, nextRole: string) => {
    setBusy(true);
    setError(null);

    try {
      await client.updateMember(userId, nextRole);
      await load();
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The role could not be changed.');
    } finally {
      setBusy(false);
    }
  };

  const removeMember = async (userId: number, name: string) => {
    if (!window.confirm(`Remove ${name} from ${active.tenant.name}?`)) {
      return;
    }

    setBusy(true);
    setError(null);

    try {
      await client.removeMember(userId);
      await load();
      onChanged();
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The member could not be removed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fashionos-view">
      <header>
        <p className="fashionos-eyebrow">People</p>
        <h1 className="fashionos-view__title">Team</h1>
        <p className="fashionos-view__lead">
          Everyone here works inside {active.tenant.name} only. The same person can hold a different
          role in another workspace.
        </p>
      </header>

      {error ? <Alert>{error}</Alert> : null}

      <Panel title="Members" description={`${team?.members.length ?? 0} in this workspace`}>
        {team === null ? (
          <Loading label="Loading the team…" />
        ) : team.members.length === 0 ? (
          <EmptyState title="No members yet" />
        ) : (
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Member</th>
                  <th scope="col">Role</th>
                  <th scope="col">Joined</th>
                  {canManage ? (
                    <th scope="col">
                      <span className="fashionos-visually-hidden">Actions</span>
                    </th>
                  ) : null}
                </tr>
              </thead>
              <tbody>
                {team.members.map((member) => (
                  <tr key={member.userId}>
                    <td>
                      <span className="fashionos-table__strong">{member.name}</span>
                      <span className="fashionos-table__meta">{member.email}</span>
                    </td>
                    <td>
                      {member.isOwner || !canManage ? (
                        <span className="fashionos-tag">{member.roleName}</span>
                      ) : (
                        <select
                          className="fashionos-select fashionos-select--compact"
                          value={member.role}
                          disabled={busy}
                          aria-label={`Role for ${member.name}`}
                          onChange={(event) => void changeRole(member.userId, event.target.value)}
                        >
                          {team.roles.map((option) => (
                            <option key={option.slug} value={option.slug}>
                              {option.name}
                            </option>
                          ))}
                        </select>
                      )}
                    </td>
                    <td className="fashionos-table__muted">{formatDate(member.joinedAt)}</td>
                    {canManage ? (
                      <td className="fashionos-table__actions">
                        {member.isOwner ? (
                          <span className="fashionos-table__muted">Owner</span>
                        ) : (
                          <button
                            className="fashionos-btn fashionos-btn--danger"
                            type="button"
                            disabled={busy}
                            onClick={() => void removeMember(member.userId, member.name)}
                          >
                            Remove
                          </button>
                        )}
                      </td>
                    ) : null}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      {canManage && team ? (
        <Panel title="Add a team member" description="They need an existing WordPress account.">
          <form className="fashionos-inline-form" onSubmit={addMember}>
            <div className="fashionos-field">
              <label className="fashionos-label" htmlFor="fashionos-member-email">
                Email address
              </label>
              <input
                className="fashionos-input"
                id="fashionos-member-email"
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                placeholder="name@studio.com"
                required
                autoComplete="email"
              />
            </div>

            <div className="fashionos-field">
              <label className="fashionos-label" htmlFor="fashionos-member-role">
                Role
              </label>
              <select
                className="fashionos-select"
                id="fashionos-member-role"
                value={role}
                onChange={(event) => setRole(event.target.value)}
              >
                {team.roles.map((option) => (
                  <option key={option.slug} value={option.slug}>
                    {option.name}
                  </option>
                ))}
              </select>
            </div>

            <button className="fashionos-btn fashionos-btn--primary" type="submit" disabled={busy}>
              {busy ? 'Working…' : 'Add member'}
            </button>
          </form>
        </Panel>
      ) : null}
    </div>
  );
}
