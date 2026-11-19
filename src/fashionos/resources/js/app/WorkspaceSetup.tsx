import { useState, type FormEvent } from 'react';
import type { ApiClient } from '../services/api';
import type { MeResponse, WorkspaceSummary } from '../types';
import { CURRENCIES, TIMEZONES } from './options';
import { Alert, Panel } from './ui';

/**
 * Workspace creation form, used during onboarding and from the studio switcher.
 */
export function CreateWorkspaceForm({
  client,
  onCreated,
  onCancel,
}: {
  client: ApiClient;
  onCreated: (workspaceId: number) => void;
  onCancel?: () => void;
}) {
  const [name, setName] = useState('');
  const [timezone, setTimezone] = useState('Africa/Lagos');
  const [currency, setCurrency] = useState('NGN');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setSaving(true);

    try {
      const active = await client.createWorkspace({ name, timezone, currency });
      onCreated(active.tenant.id);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The workspace could not be created.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <form className="fashionos-form" onSubmit={submit}>
      <div className="fashionos-field">
        <label className="fashionos-label" htmlFor="fashionos-workspace-name">
          Business name
        </label>
        <input
          className="fashionos-input"
          id="fashionos-workspace-name"
          name="name"
          value={name}
          onChange={(event) => setName(event.target.value)}
          placeholder="e.g. Adaeze Atelier"
          maxLength={120}
          required
          autoComplete="organization"
        />
      </div>

      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-workspace-timezone">
            Timezone
          </label>
          <select
            className="fashionos-select"
            id="fashionos-workspace-timezone"
            value={timezone}
            onChange={(event) => setTimezone(event.target.value)}
          >
            {TIMEZONES.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-workspace-currency">
            Currency
          </label>
          <select
            className="fashionos-select"
            id="fashionos-workspace-currency"
            value={currency}
            onChange={(event) => setCurrency(event.target.value)}
          >
            {CURRENCIES.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      {error ? <Alert>{error}</Alert> : null}

      <div className="fashionos-actions">
        <button className="fashionos-btn fashionos-btn--primary" type="submit" disabled={saving}>
          {saving ? 'Creating…' : 'Create workspace'}
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

/**
 * Shown when the user has no workspace yet, or belongs to several and none is
 * selected. The server decides which workspaces exist; this only lists them.
 */
export function WorkspaceSetup({
  client,
  session,
  onReady,
}: {
  client: ApiClient;
  session: MeResponse;
  onReady: (workspaceId: number) => void;
}) {
  const hasWorkspaces = session.workspaces.length > 0;

  return (
    <div className="fashionos-gate">
      <div className="fashionos-gate__card">
        <p className="fashionos-eyebrow">
          {hasWorkspaces ? 'Choose a workspace' : 'Welcome to FashionOS'}
        </p>
        <h1 className="fashionos-gate__title">
          {hasWorkspaces ? 'Where are you working today?' : 'Set up your studio'}
        </h1>
        <p className="fashionos-gate__lead">
          {hasWorkspaces
            ? 'You belong to more than one business. Pick the workspace you want to open.'
            : 'A workspace holds your customers, measurements, orders and team. You can create more later and switch between them.'}
        </p>

        {hasWorkspaces ? (
          <WorkspaceChooser workspaces={session.workspaces} onOpen={onReady} />
        ) : null}

        <Panel
          title={hasWorkspaces ? 'Create another workspace' : 'Create your workspace'}
          quiet={hasWorkspaces}
        >
          <CreateWorkspaceForm client={client} onCreated={onReady} />
        </Panel>
      </div>
    </div>
  );
}

function WorkspaceChooser({
  workspaces,
  onOpen,
}: {
  workspaces: WorkspaceSummary[];
  onOpen: (workspaceId: number) => void;
}) {
  return (
    <ul className="fashionos-chooser">
      {workspaces.map((workspace) => (
        <li key={workspace.id}>
          <button
            className="fashionos-chooser__button"
            type="button"
            onClick={() => onOpen(workspace.id)}
          >
            <span className="fashionos-chooser__name">{workspace.name}</span>
            <span className="fashionos-chooser__meta">
              {workspace.role.name} · /{workspace.slug}
            </span>
          </button>
        </li>
      ))}
    </ul>
  );
}
