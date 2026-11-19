import { useState, type FormEvent } from 'react';
import type { ApiClient } from '../../services/api';
import type { ActiveTenant } from '../../types';
import { CURRENCIES, TIMEZONES } from '../options';
import { Alert, Panel } from '../ui';

/**
 * Workspace profile settings.
 *
 * Slug and status are not editable here: the slug is a stable address used in
 * links and status changes are an administrative action.
 */
export function SettingsView({
  client,
  active,
  onUpdated,
}: {
  client: ApiClient;
  active: ActiveTenant;
  onUpdated: () => void;
}) {
  const [name, setName] = useState(active.tenant.name);
  const [timezone, setTimezone] = useState(active.tenant.timezone);
  const [currency, setCurrency] = useState(active.tenant.currency);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [saving, setSaving] = useState(false);

  const timezoneOptions = TIMEZONES.some((option) => option.value === timezone)
    ? TIMEZONES
    : [{ value: timezone, label: timezone }, ...TIMEZONES];

  const currencyOptions = CURRENCIES.some((option) => option.value === currency)
    ? CURRENCIES
    : [{ value: currency, label: currency }, ...CURRENCIES];

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSaving(true);
    setError(null);
    setSaved(false);

    try {
      await client.updateWorkspace({ name, timezone, currency });
      setSaved(true);
      onUpdated();
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The workspace could not be updated.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fashionos-view">
      <header>
        <p className="fashionos-eyebrow">Workspace</p>
        <h1 className="fashionos-view__title">Settings</h1>
        <p className="fashionos-view__lead">
          These details shape how dates and money appear across {active.tenant.name}.
        </p>
      </header>

      <Panel title="Profile">
        <form className="fashionos-form" onSubmit={submit}>
          <div className="fashionos-field">
            <label className="fashionos-label" htmlFor="fashionos-settings-name">
              Business name
            </label>
            <input
              className="fashionos-input"
              id="fashionos-settings-name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              maxLength={120}
              required
            />
          </div>

          <div className="fashionos-form__row">
            <div className="fashionos-field">
              <label className="fashionos-label" htmlFor="fashionos-settings-timezone">
                Timezone
              </label>
              <select
                className="fashionos-select"
                id="fashionos-settings-timezone"
                value={timezone}
                onChange={(event) => setTimezone(event.target.value)}
              >
                {timezoneOptions.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>

            <div className="fashionos-field">
              <label className="fashionos-label" htmlFor="fashionos-settings-currency">
                Currency
              </label>
              <select
                className="fashionos-select"
                id="fashionos-settings-currency"
                value={currency}
                onChange={(event) => setCurrency(event.target.value)}
              >
                {currencyOptions.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="fashionos-field">
            <span className="fashionos-label">Workspace address</span>
            <p className="fashionos-readonly">/{active.tenant.slug}</p>
          </div>

          {error ? <Alert>{error}</Alert> : null}
          {saved ? (
            <p className="fashionos-success" role="status">
              Workspace settings saved.
            </p>
          ) : null}

          <div className="fashionos-actions">
            <button
              className="fashionos-btn fashionos-btn--primary"
              type="submit"
              disabled={saving}
            >
              {saving ? 'Saving…' : 'Save changes'}
            </button>
          </div>
        </form>
      </Panel>
    </div>
  );
}
