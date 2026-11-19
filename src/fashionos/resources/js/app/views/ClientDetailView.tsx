import { useCallback, useEffect, useState, type FormEvent } from 'react';
import type { ApiClient } from '../../services/api';
import type {
  ActiveTenant,
  Client,
  ClientInput,
  MeasurementProfile,
  MeasurementProfileDetail,
  MeasurementTemplate,
  MeasurementVersion,
} from '../../types';
import { Alert, EmptyState, Loading, Panel, formatDate } from '../ui';
import { ClientForm } from './ClientForm';

/**
 * One customer: contact details and preferences, measurement profiles, and the
 * version history of whichever profile is open.
 *
 * Recording measurements always creates a new version; nothing in this view
 * edits an existing one.
 */
export function ClientDetailView({
  client,
  active,
  clientId,
  onBack,
}: {
  client: ApiClient;
  active: ActiveTenant;
  clientId: number;
  onBack: () => void;
}) {
  const [record, setRecord] = useState<Client | null>(null);
  const [profiles, setProfiles] = useState<MeasurementProfile[] | null>(null);
  const [templates, setTemplates] = useState<MeasurementTemplate[]>([]);
  const [openProfile, setOpenProfile] = useState<MeasurementProfileDetail | null>(null);
  const [version, setVersion] = useState<MeasurementVersion | null>(null);
  const [previousVersion, setPreviousVersion] = useState<MeasurementVersion | null>(null);
  const [editing, setEditing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [profileName, setProfileName] = useState('');
  const [templateId, setTemplateId] = useState(0);

  const canManage =
    active.permissions.includes('*') || active.permissions.includes('clients.manage');
  const canRecord =
    active.permissions.includes('*') || active.permissions.includes('measurements.manage');

  const load = useCallback(async () => {
    try {
      const [customer, profileList, templateList] = await Promise.all([
        client.client(clientId),
        client.clientMeasurements(clientId),
        client.measurementTemplates(),
      ]);

      setRecord(customer);
      setProfiles(profileList.profiles);
      setTemplates(templateList.templates);

      setTemplateId((current) => {
        if (current !== 0 || templateList.templates.length === 0) {
          return current;
        }

        const firstActive = templateList.templates.find((item) => item.status === 'active');

        return firstActive?.id ?? templateList.templates[0].id;
      });

      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'This customer could not be loaded.');
    }
  }, [client, clientId]);

  useEffect(() => {
    void load();
  }, [load]);

  const save = async (input: ClientInput) => {
    setBusy(true);

    try {
      const updated = await client.updateClient(clientId, input);
      setRecord(updated);
      setEditing(false);
    } finally {
      setBusy(false);
    }
  };

  const addProfile = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setBusy(true);
    setError(null);

    try {
      await client.createMeasurementProfile(clientId, { profileName, templateId });
      setProfileName('');
      await load();
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The profile could not be created.');
    } finally {
      setBusy(false);
    }
  };

  const open = async (profileId: number) => {
    setBusy(true);

    try {
      const detail = await client.measurementProfile(profileId);
      setOpenProfile(detail);
      setVersion(null);
      setPreviousVersion(null);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The profile could not be loaded.');
    } finally {
      setBusy(false);
    }
  };

  const showVersion = async (versionId: number) => {
    try {
      const detail = await client.measurementVersion(versionId);
      const prior = openProfile?.history.find(
        (entry) => entry.versionNumber === detail.versionNumber - 1
      );

      setVersion(detail);
      setPreviousVersion(prior ? await client.measurementVersion(prior.id) : null);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The version could not be loaded.');
    }
  };

  const activeTemplates = templates.filter((template) => template.status === 'active');
  const profileTemplate = openProfile
    ? templates.find((template) => template.id === openProfile.templateId)
    : undefined;

  return (
    <div className="fashionos-view">
      <button className="fashionos-link-btn" type="button" onClick={onBack}>
        ← All customers
      </button>

      {error ? <Alert>{error}</Alert> : null}

      {record === null ? (
        <Loading label="Loading customer…" />
      ) : (
        <>
          <header>
            <p className="fashionos-eyebrow">
              {record.reference} · {record.status}
            </p>
            <h1 className="fashionos-view__title">{record.name}</h1>
            <p className="fashionos-view__lead">
              {[record.email, record.phone].filter(Boolean).join(' · ') ||
                'No contact details recorded yet.'}
            </p>
          </header>

          <Panel
            title="Profile and preferences"
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
              <ClientForm
                initial={record}
                submitLabel="Save changes"
                busy={busy}
                onSubmit={save}
                onCancel={() => setEditing(false)}
              />
            ) : (
              <>
                <dl className="fashionos-stats">
                  <Stat
                    label="Preferred contact"
                    value={record.preferences.preferredContact || 'Not recorded'}
                  />
                  <Stat label="Profiles" value={String(profiles?.length ?? 0)} />
                  <Stat label="Customer since" value={formatDate(record.createdAt)} />
                  <Stat label="Last updated" value={formatDate(record.updatedAt)} />
                </dl>

                {record.preferences.styleNotes ? (
                  <div className="fashionos-note-block">
                    <p className="fashionos-subhead">Style preferences</p>
                    <p className="fashionos-note">{record.preferences.styleNotes}</p>
                  </div>
                ) : null}

                {record.preferences.fitNotes ? (
                  <div className="fashionos-note-block">
                    <p className="fashionos-subhead">Fit observations</p>
                    <p className="fashionos-note">{record.preferences.fitNotes}</p>
                  </div>
                ) : null}

                {record.notes ? (
                  <div className="fashionos-note-block">
                    <p className="fashionos-subhead">Internal notes</p>
                    <p className="fashionos-note">{record.notes}</p>
                  </div>
                ) : null}
              </>
            )}
          </Panel>

          <Panel
            title="Measurement profiles"
            description="One profile per garment or purpose, each with its own version history."
          >
            {profiles === null ? (
              <Loading label="Loading profiles…" />
            ) : profiles.length === 0 ? (
              <EmptyState
                title="No measurement profiles yet"
                detail="Create a profile to record this customer's measurements."
              />
            ) : (
              <div className="fashionos-table-wrap">
                <table className="fashionos-table">
                  <thead>
                    <tr>
                      <th scope="col">Profile</th>
                      <th scope="col">Template</th>
                      <th scope="col">Versions</th>
                      <th scope="col">Latest</th>
                      <th scope="col">
                        <span className="fashionos-visually-hidden">Open</span>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {profiles.map((profile) => (
                      <tr key={profile.id}>
                        <td>
                          <span className="fashionos-table__strong">{profile.profileName}</span>
                          <span className="fashionos-table__meta">{profile.garmentCategory}</span>
                        </td>
                        <td>{profile.templateName || '—'}</td>
                        <td>{profile.versionCount}</td>
                        <td className="fashionos-table__muted">
                          {profile.latestVersion
                            ? `v${profile.latestVersion.versionNumber} · ${formatDate(
                                profile.latestVersion.takenAt
                              )}`
                            : 'No measurements yet'}
                        </td>
                        <td className="fashionos-table__actions">
                          <button
                            className="fashionos-btn fashionos-btn--ghost"
                            type="button"
                            disabled={busy}
                            onClick={() => void open(profile.id)}
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

            {canRecord && activeTemplates.length > 0 ? (
              <form className="fashionos-inline-form" onSubmit={addProfile}>
                <div className="fashionos-field">
                  <label className="fashionos-label" htmlFor="fashionos-profile-name">
                    New profile
                  </label>
                  <input
                    className="fashionos-input"
                    id="fashionos-profile-name"
                    value={profileName}
                    onChange={(event) => setProfileName(event.target.value)}
                    placeholder="e.g. Wedding suit"
                    maxLength={120}
                    required
                  />
                </div>

                <div className="fashionos-field">
                  <label className="fashionos-label" htmlFor="fashionos-profile-template">
                    Template
                  </label>
                  <select
                    className="fashionos-select"
                    id="fashionos-profile-template"
                    value={templateId}
                    onChange={(event) => setTemplateId(Number(event.target.value))}
                  >
                    {activeTemplates.map((template) => (
                      <option key={template.id} value={template.id}>
                        {template.name} ({template.garmentCategory})
                      </option>
                    ))}
                  </select>
                </div>

                <button
                  className="fashionos-btn fashionos-btn--primary"
                  type="submit"
                  disabled={busy}
                >
                  {busy ? 'Working…' : 'Create profile'}
                </button>
              </form>
            ) : (
              <p className="fashionos-hint">
                {activeTemplates.length === 0
                  ? 'Create a measurement template first, then add profiles for this customer.'
                  : 'You can view profiles but not record measurements in this workspace.'}
              </p>
            )}
          </Panel>

          {openProfile ? (
            <Panel
              title={`${openProfile.profileName} history`}
              description={`${openProfile.garmentCategory} · ${openProfile.versionCount} ${
                openProfile.versionCount === 1 ? 'version' : 'versions'
              }`}
            >
              {openProfile.history.length === 0 ? (
                <EmptyState
                  title="No measurements recorded"
                  detail="Recording measurements creates version 1 and keeps it forever."
                />
              ) : (
                <div className="fashionos-table-wrap">
                  <table className="fashionos-table">
                    <thead>
                      <tr>
                        <th scope="col">Version</th>
                        <th scope="col">Taken</th>
                        <th scope="col">By</th>
                        <th scope="col">Values</th>
                        <th scope="col">
                          <span className="fashionos-visually-hidden">View</span>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {openProfile.history.map((entry) => (
                        <tr key={entry.id}>
                          <td className="fashionos-table__strong">v{entry.versionNumber}</td>
                          <td className="fashionos-table__muted">{formatDate(entry.takenAt)}</td>
                          <td>{entry.takenBy.name || 'Unknown'}</td>
                          <td>
                            {entry.valueCount} ({entry.unit})
                          </td>
                          <td className="fashionos-table__actions">
                            <button
                              className="fashionos-btn fashionos-btn--ghost"
                              type="button"
                              onClick={() => void showVersion(entry.id)}
                            >
                              View
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              {canRecord && profileTemplate && profileTemplate.status === 'active' ? (
                <MeasurementForm
                  fields={profileTemplate.fields}
                  unit={profileTemplate.unit}
                  busy={busy}
                  onSubmit={async (values, notes, fitNotes) => {
                    setBusy(true);

                    try {
                      const created = await client.createMeasurementVersion(openProfile.id, {
                        values,
                        unit: profileTemplate.unit,
                        notes,
                        fitNotes,
                      });

                      const detail = await client.measurementProfile(openProfile.id);
                      const prior = detail.history.find(
                        (entry) => entry.versionNumber === created.versionNumber - 1
                      );

                      setOpenProfile(detail);
                      setVersion(created);
                      setPreviousVersion(prior ? await client.measurementVersion(prior.id) : null);
                      setError(null);
                    } finally {
                      setBusy(false);
                    }
                  }}
                />
              ) : (
                <p className="fashionos-hint">
                  {profileTemplate && profileTemplate.status !== 'active'
                    ? 'This template is archived, so no new versions can be recorded. History stays available.'
                    : 'You can view measurements but not record them in this workspace.'}
                </p>
              )}
            </Panel>
          ) : null}

          {version ? (
            <Panel
              title={`Version ${version.versionNumber}`}
              description={`${version.profileName} · ${formatDate(version.takenAt)} · ${
                version.takenBy.name || 'Unknown'
              }`}
            >
              <div className="fashionos-table-wrap">
                <table className="fashionos-table">
                  <thead>
                    <tr>
                      <th scope="col">Measurement</th>
                      <th scope="col">Value</th>
                      <th scope="col">Unit</th>
                      {previousVersion ? <th scope="col">Previous</th> : null}
                      {previousVersion ? <th scope="col">Change</th> : null}
                    </tr>
                  </thead>
                  <tbody>
                    {version.values.map((value) => {
                      const previous = previousVersion?.values.find(
                        (entry) => entry.key === value.key
                      );
                      const difference =
                        previous === undefined ? null : value.value - previous.value;

                      return (
                        <tr key={value.key}>
                          <td>{value.label}</td>
                          <td className="fashionos-table__strong">{value.value.toFixed(2)}</td>
                          <td className="fashionos-table__muted">{value.unit}</td>
                          {previousVersion ? (
                            <td className="fashionos-table__muted">
                              {previous ? previous.value.toFixed(2) : '—'}
                            </td>
                          ) : null}
                          {previousVersion ? (
                            <td className="fashionos-table__muted">
                              {difference === null
                                ? 'New'
                                : `${difference > 0 ? '+' : ''}${difference.toFixed(2)}`}
                            </td>
                          ) : null}
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>

              {previousVersion ? (
                <p className="fashionos-meta">
                  Compared with version {previousVersion.versionNumber}, recorded{' '}
                  {formatDate(previousVersion.takenAt)}.
                </p>
              ) : null}

              <p className="fashionos-meta">
                Captured with {version.template.name} (schema v{version.template.schemaVersion}).
                This version is frozen; corrections are recorded as a new version.
              </p>

              {version.notes ? <p className="fashionos-note">Notes: {version.notes}</p> : null}
              {version.fitNotes ? <p className="fashionos-note">Fit: {version.fitNotes}</p> : null}
            </Panel>
          ) : null}
        </>
      )}
    </div>
  );
}

/**
 * Capture form built from a template's fields.
 */
function MeasurementForm({
  fields,
  unit,
  busy,
  onSubmit,
}: {
  fields: MeasurementTemplate['fields'];
  unit: string;
  busy: boolean;
  onSubmit: (values: Record<string, string>, notes: string, fitNotes: string) => Promise<void>;
}) {
  const [values, setValues] = useState<Record<string, string>>({});
  const [notes, setNotes] = useState('');
  const [fitNotes, setFitNotes] = useState('');
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    try {
      await onSubmit(values, notes, fitNotes);
      setValues({});
      setNotes('');
      setFitNotes('');
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The measurements could not be saved.');
    }
  };

  return (
    <div className="fashionos-subsection">
      <p className="fashionos-subhead">Record new measurements ({unit})</p>

      <form className="fashionos-form" onSubmit={submit}>
        <div className="fashionos-field-grid">
          {fields.map((field) => (
            <div className="fashionos-field" key={field.key}>
              <label className="fashionos-label" htmlFor={`fashionos-value-${field.key}`}>
                {field.label}
                {field.required ? ' *' : ''}
              </label>
              <input
                className="fashionos-input"
                id={`fashionos-value-${field.key}`}
                inputMode="decimal"
                value={values[field.key] ?? ''}
                onChange={(event) =>
                  setValues((current) => ({ ...current, [field.key]: event.target.value }))
                }
                placeholder={field.hint || unit}
                required={field.required}
              />
            </div>
          ))}
        </div>

        <div className="fashionos-form__row">
          <div className="fashionos-field">
            <label className="fashionos-label" htmlFor="fashionos-version-notes">
              Notes
            </label>
            <textarea
              className="fashionos-input fashionos-textarea"
              id="fashionos-version-notes"
              value={notes}
              onChange={(event) => setNotes(event.target.value)}
              rows={2}
              maxLength={2000}
            />
          </div>

          <div className="fashionos-field">
            <label className="fashionos-label" htmlFor="fashionos-version-fit">
              Fit observations
            </label>
            <textarea
              className="fashionos-input fashionos-textarea"
              id="fashionos-version-fit"
              value={fitNotes}
              onChange={(event) => setFitNotes(event.target.value)}
              rows={2}
              maxLength={2000}
            />
          </div>
        </div>

        {error ? <Alert>{error}</Alert> : null}

        <div className="fashionos-actions">
          <button className="fashionos-btn fashionos-btn--primary" type="submit" disabled={busy}>
            {busy ? 'Saving…' : 'Save new version'}
          </button>
        </div>
      </form>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="fashionos-stat">
      <dt className="fashionos-stat__label">{label}</dt>
      <dd className="fashionos-stat__value">{value}</dd>
    </div>
  );
}
