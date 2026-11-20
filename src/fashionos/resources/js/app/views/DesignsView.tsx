import { useCallback, useEffect, useState, type FormEvent } from 'react';
import type { ApiClient } from '../../services/api';
import type {
  ActiveTenant,
  Client,
  Design,
  DesignInput,
  DesignListResponse,
  MeasurementProfile,
  MeasurementVersionSummary,
} from '../../types';
import { Alert, EmptyState, Loading, Pagination, Panel, Stat, formatDate } from '../ui';

/**
 * Designs: the brief for a garment, optionally citing the customer's exact
 * measurement profile and version it was drafted against.
 *
 * Changing a design's status never touches measurements or orders; those keep
 * their own references.
 */
export function DesignsView({ client, active }: { client: ApiClient; active: ActiveTenant }) {
  const [list, setList] = useState<DesignListResponse | null>(null);
  const [clients, setClients] = useState<Client[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');
  const [page, setPage] = useState(1);
  const [creating, setCreating] = useState(false);
  const [busy, setBusy] = useState(false);
  const [selected, setSelected] = useState<Design | null>(null);
  const [editing, setEditing] = useState(false);

  const canManage =
    active.permissions.includes('*') || active.permissions.includes('designs.manage');

  const load = useCallback(async () => {
    try {
      const result = await client.designs({ search, status, page });
      setList(result);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Designs could not be loaded.');
    }
  }, [client, search, status, page]);

  useEffect(() => {
    if (selected === null) {
      void load();
    }
  }, [load, selected]);

  useEffect(() => {
    client
      .clients({ perPage: 100 })
      .then((result) => setClients(result.clients))
      .catch(() => setClients([]));
  }, [client]);

  const applySearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPage(1);
    setSearch(searchInput.trim());
  };

  const create = async (input: DesignInput) => {
    setBusy(true);

    try {
      await client.createDesign(input);
      setCreating(false);
      setPage(1);
      await load();
    } finally {
      setBusy(false);
    }
  };

  const saveEdit = async (input: DesignInput) => {
    if (selected === null) {
      return;
    }

    setBusy(true);

    try {
      const updated = await client.updateDesign(selected.id, input);
      setSelected(updated);
      setEditing(false);
      await load();
    } finally {
      setBusy(false);
    }
  };

  const changeStatus = async (next: string) => {
    if (selected === null) {
      return;
    }

    setBusy(true);

    try {
      const updated = await client.updateDesign(selected.id, { status: next });
      setSelected(updated);
      await load();
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The design could not be updated.');
    } finally {
      setBusy(false);
    }
  };

  if (selected !== null) {
    return (
      <div className="fashionos-view">
        <button className="fashionos-link-btn" type="button" onClick={() => setSelected(null)}>
          ← All designs
        </button>

        {error ? <Alert>{error}</Alert> : null}

        <header>
          <p className="fashionos-eyebrow">
            {selected.reference} · {selected.status}
          </p>
          <h1 className="fashionos-view__title">{selected.title}</h1>
          <p className="fashionos-view__lead">
            {selected.clientName}
            {selected.garmentCategory ? ` · ${selected.garmentCategory}` : ''}
          </p>
        </header>

        <Panel
          title="Design record"
          action={
            canManage ? (
              <button
                className="fashionos-btn fashionos-btn--ghost"
                type="button"
                onClick={() => setEditing((value) => !value)}
              >
                {editing ? 'Close' : 'Edit'}
              </button>
            ) : null
          }
        >
          {editing ? (
            <DesignForm
              client={client}
              clients={clients}
              initial={selected}
              busy={busy}
              onSubmit={saveEdit}
              onCancel={() => setEditing(false)}
            />
          ) : (
            <>
              <dl className="fashionos-stats">
                <Stat label="Customer" value={selected.clientName} />
                <Stat label="Garment" value={selected.garmentCategory || 'Not recorded'} />
                <Stat
                  label="Measurements"
                  value={
                    selected.measurementVersionId > 0
                      ? `${selected.profileName} v${selected.measurementVersionNumber}`
                      : 'No version referenced'
                  }
                />
                <Stat label="Updated" value={formatDate(selected.updatedAt)} />
              </dl>

              {selected.brief ? (
                <div className="fashionos-note-block">
                  <p className="fashionos-subhead">Brief</p>
                  <p className="fashionos-note">{selected.brief}</p>
                </div>
              ) : (
                <p className="fashionos-hint">No design brief recorded yet.</p>
              )}

              {canManage ? (
                <div className="fashionos-inline-form">
                  <div className="fashionos-field">
                    <label className="fashionos-label" htmlFor="fashionos-design-status">
                      Status
                    </label>
                    <select
                      className="fashionos-select"
                      id="fashionos-design-status"
                      value={selected.status}
                      disabled={busy}
                      onChange={(event) => void changeStatus(event.target.value)}
                    >
                      <option value="draft">Draft</option>
                      <option value="approved">Approved</option>
                      <option value="archived">Archived</option>
                    </select>
                  </div>
                </div>
              ) : null}
            </>
          )}
        </Panel>
      </div>
    );
  }

  return (
    <div className="fashionos-view">
      <header>
        <p className="fashionos-eyebrow">Designs</p>
        <h1 className="fashionos-view__title">Designs</h1>
        <p className="fashionos-view__lead">
          Briefs for {active.tenant.name}, each optionally pinned to the measurement version it was
          drafted against.
        </p>
      </header>

      {error ? <Alert>{error}</Alert> : null}

      <Panel
        title="Design records"
        description={list ? `${list.total} ${list.total === 1 ? 'design' : 'designs'}` : undefined}
        action={
          canManage ? (
            <button
              className="fashionos-btn fashionos-btn--primary"
              type="button"
              disabled={clients.length === 0}
              onClick={() => setCreating((value) => !value)}
            >
              {creating ? 'Close' : 'New design'}
            </button>
          ) : null
        }
      >
        {creating ? (
          <div className="fashionos-subsection">
            <DesignForm
              client={client}
              clients={clients}
              busy={busy}
              onSubmit={create}
              onCancel={() => setCreating(false)}
            />
          </div>
        ) : null}

        <form className="fashionos-toolbar" onSubmit={applySearch}>
          <div className="fashionos-toolbar__group">
            <label className="fashionos-visually-hidden" htmlFor="fashionos-design-search">
              Search designs
            </label>
            <input
              className="fashionos-input"
              id="fashionos-design-search"
              type="search"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Search title, reference or customer"
            />
            <button className="fashionos-btn fashionos-btn--ghost" type="submit">
              Search
            </button>
          </div>

          <div className="fashionos-toolbar__group">
            <label className="fashionos-visually-hidden" htmlFor="fashionos-design-filter">
              Filter by status
            </label>
            <select
              className="fashionos-select fashionos-select--compact"
              id="fashionos-design-filter"
              value={status}
              onChange={(event) => {
                setStatus(event.target.value);
                setPage(1);
              }}
            >
              <option value="all">All statuses</option>
              <option value="draft">Draft</option>
              <option value="approved">Approved</option>
              <option value="archived">Archived</option>
            </select>
          </div>
        </form>

        {list === null ? (
          <Loading label="Loading designs…" />
        ) : list.designs.length === 0 ? (
          <EmptyState
            title={search ? 'No designs match that search' : 'No designs yet'}
            detail={
              clients.length === 0
                ? 'Add a customer first, then record the designs you create for them.'
                : 'Record a design brief and pin it to a measurement version.'
            }
          />
        ) : (
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Design</th>
                  <th scope="col">Customer</th>
                  <th scope="col">Measurements</th>
                  <th scope="col">Status</th>
                  <th scope="col">Updated</th>
                  <th scope="col">
                    <span className="fashionos-visually-hidden">Open</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {list.designs.map((design) => (
                  <tr key={design.id}>
                    <td>
                      <span className="fashionos-table__strong">{design.title}</span>
                      <span className="fashionos-table__meta">
                        {design.reference}
                        {design.garmentCategory ? ` · ${design.garmentCategory}` : ''}
                      </span>
                    </td>
                    <td>{design.clientName}</td>
                    <td className="fashionos-table__muted">
                      {design.measurementVersionId > 0
                        ? `${design.profileName} v${design.measurementVersionNumber}`
                        : '—'}
                    </td>
                    <td>{design.status}</td>
                    <td className="fashionos-table__muted">{formatDate(design.updatedAt)}</td>
                    <td className="fashionos-table__actions">
                      <button
                        className="fashionos-btn fashionos-btn--ghost"
                        type="button"
                        onClick={() => setSelected(design)}
                      >
                        Open
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {list ? (
          <Pagination page={list.page} totalPages={list.totalPages} onChange={setPage} />
        ) : null}
      </Panel>
    </div>
  );
}

/**
 * Design form. On create the customer is chosen; on edit it is fixed, because
 * moving a design between customers would invalidate its measurement reference.
 */
function DesignForm({
  client,
  clients,
  initial,
  busy,
  onSubmit,
  onCancel,
}: {
  client: ApiClient;
  clients: Client[];
  initial?: Design;
  busy: boolean;
  onSubmit: (input: DesignInput) => Promise<void>;
  onCancel?: () => void;
}) {
  const [clientId, setClientId] = useState(initial?.clientId ?? clients[0]?.id ?? 0);
  const [title, setTitle] = useState(initial?.title ?? '');
  const [garmentCategory, setGarmentCategory] = useState(initial?.garmentCategory ?? '');
  const [brief, setBrief] = useState(initial?.brief ?? '');
  const [profiles, setProfiles] = useState<MeasurementProfile[]>([]);
  const [profileId, setProfileId] = useState(initial?.measurementProfileId ?? 0);
  const [versions, setVersions] = useState<MeasurementVersionSummary[]>([]);
  const [versionId, setVersionId] = useState(initial?.measurementVersionId ?? 0);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (clientId <= 0) {
      setProfiles([]);
      return;
    }

    let cancelled = false;

    client
      .clientMeasurements(clientId)
      .then((result) => {
        if (!cancelled) {
          setProfiles(result.profiles);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setProfiles([]);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [client, clientId]);

  useEffect(() => {
    if (profileId <= 0) {
      setVersions([]);
      return;
    }

    let cancelled = false;

    client
      .measurementProfile(profileId)
      .then((result) => {
        if (!cancelled) {
          setVersions(result.history);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setVersions([]);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [client, profileId]);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    try {
      await onSubmit({
        clientId,
        title,
        garmentCategory,
        brief,
        measurementProfileId: profileId,
        measurementVersionId: versionId,
      });
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The design could not be saved.');
    }
  };

  return (
    <form className="fashionos-form" onSubmit={submit}>
      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-design-customer">
            Customer
          </label>
          {initial ? (
            <p className="fashionos-meta">{initial.clientName}</p>
          ) : (
            <select
              className="fashionos-select"
              id="fashionos-design-customer"
              value={clientId}
              required
              onChange={(event) => {
                setClientId(Number(event.target.value));
                setProfileId(0);
                setVersionId(0);
              }}
            >
              {clients.length === 0 ? <option value={0}>Add a customer first</option> : null}
              {clients.map((record) => (
                <option key={record.id} value={record.id}>
                  {record.name} ({record.reference})
                </option>
              ))}
            </select>
          )}
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-design-title">
            Title
          </label>
          <input
            className="fashionos-input"
            id="fashionos-design-title"
            value={title}
            onChange={(event) => setTitle(event.target.value)}
            maxLength={120}
            required
            placeholder="e.g. Ivory wedding suit"
          />
        </div>
      </div>

      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-design-category">
            Garment category
          </label>
          <input
            className="fashionos-input"
            id="fashionos-design-category"
            value={garmentCategory}
            onChange={(event) => setGarmentCategory(event.target.value)}
            maxLength={60}
            placeholder="e.g. Suit"
          />
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-design-profile">
            Measurement profile
          </label>
          <select
            className="fashionos-select"
            id="fashionos-design-profile"
            value={profileId}
            disabled={profiles.length === 0}
            onChange={(event) => {
              setProfileId(Number(event.target.value));
              setVersionId(0);
            }}
          >
            <option value={0}>
              {profiles.length === 0 ? 'No profiles recorded' : 'No profile referenced'}
            </option>
            {profiles.map((profile) => (
              <option key={profile.id} value={profile.id}>
                {profile.profileName} ({profile.versionCount} versions)
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="fashionos-field">
        <label className="fashionos-label" htmlFor="fashionos-design-version">
          Measurement version
        </label>
        <select
          className="fashionos-select"
          id="fashionos-design-version"
          value={versionId}
          disabled={versions.length === 0}
          onChange={(event) => setVersionId(Number(event.target.value))}
        >
          <option value={0}>
            {profileId <= 0 ? 'Choose a profile first' : 'No version referenced'}
          </option>
          {versions.map((version) => (
            <option key={version.id} value={version.id}>
              v{version.versionNumber} · {formatDate(version.takenAt)}
            </option>
          ))}
        </select>
        <p className="fashionos-note">
          Pinning a version keeps this design interpretable even after new measurements are
          recorded.
        </p>
      </div>

      <div className="fashionos-field">
        <label className="fashionos-label" htmlFor="fashionos-design-brief">
          Brief
        </label>
        <textarea
          className="fashionos-input fashionos-textarea"
          id="fashionos-design-brief"
          value={brief}
          onChange={(event) => setBrief(event.target.value)}
          rows={4}
          maxLength={5000}
          placeholder="Silhouette, fabric, finishing details and anything the tailor must not change."
        />
      </div>

      {error ? <Alert>{error}</Alert> : null}

      <div className="fashionos-actions">
        <button
          className="fashionos-btn fashionos-btn--primary"
          type="submit"
          disabled={busy || clients.length === 0}
        >
          {busy ? 'Saving…' : initial ? 'Save design' : 'Create design'}
        </button>
        {onCancel ? (
          <button className="fashionos-btn fashionos-btn--ghost" type="button" onClick={onCancel}>
            Cancel
          </button>
        ) : null}
      </div>
    </form>
  );
}
