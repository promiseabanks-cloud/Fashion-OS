import { useState, type FormEvent } from 'react';
import type { Client, ClientInput } from '../../types';
import { Alert } from '../ui';

const CONTACT_CHOICES = [
  { value: '', label: 'No preference recorded' },
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'phone', label: 'Phone call' },
  { value: 'email', label: 'Email' },
  { value: 'none', label: 'Do not contact' },
];

/**
 * Customer profile form, shared by the create panel and the detail view.
 *
 * Contact details are optional: a walk-in customer can be recorded with a name
 * alone and completed later.
 */
export function ClientForm({
  initial,
  submitLabel,
  busy,
  onSubmit,
  onCancel,
}: {
  initial?: Client;
  submitLabel: string;
  busy: boolean;
  onSubmit: (input: ClientInput) => Promise<void>;
  onCancel?: () => void;
}) {
  const [name, setName] = useState(initial?.name ?? '');
  const [email, setEmail] = useState(initial?.email ?? '');
  const [phone, setPhone] = useState(initial?.phone ?? '');
  const [notes, setNotes] = useState(initial?.notes ?? '');
  const [status, setStatus] = useState(initial?.status ?? 'active');
  const [preferredContact, setPreferredContact] = useState(
    initial?.preferences.preferredContact ?? ''
  );
  const [styleNotes, setStyleNotes] = useState(initial?.preferences.styleNotes ?? '');
  const [fitNotes, setFitNotes] = useState(initial?.preferences.fitNotes ?? '');
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    try {
      await onSubmit({
        name,
        email,
        phone,
        notes,
        ...(initial ? { status } : {}),
        preferences: { preferredContact, styleNotes, fitNotes },
      });
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The customer could not be saved.');
    }
  };

  return (
    <form className="fashionos-form" onSubmit={submit}>
      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-client-name">
            Full name
          </label>
          <input
            className="fashionos-input"
            id="fashionos-client-name"
            value={name}
            onChange={(event) => setName(event.target.value)}
            maxLength={191}
            required
            autoComplete="name"
          />
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-client-email">
            Email
          </label>
          <input
            className="fashionos-input"
            id="fashionos-client-email"
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            autoComplete="email"
          />
        </div>
      </div>

      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-client-phone">
            Phone
          </label>
          <input
            className="fashionos-input"
            id="fashionos-client-phone"
            value={phone}
            onChange={(event) => setPhone(event.target.value)}
            maxLength={40}
            autoComplete="tel"
          />
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-client-contact">
            Preferred contact
          </label>
          <select
            className="fashionos-select"
            id="fashionos-client-contact"
            value={preferredContact}
            onChange={(event) => setPreferredContact(event.target.value)}
          >
            {CONTACT_CHOICES.map((choice) => (
              <option key={choice.value} value={choice.value}>
                {choice.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-client-style">
            Style preferences
          </label>
          <textarea
            className="fashionos-input fashionos-textarea"
            id="fashionos-client-style"
            value={styleNotes}
            onChange={(event) => setStyleNotes(event.target.value)}
            rows={3}
            maxLength={2000}
            placeholder="Silhouettes, colours and fabrics they return to."
          />
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-client-fit">
            Fit observations
          </label>
          <textarea
            className="fashionos-input fashionos-textarea"
            id="fashionos-client-fit"
            value={fitNotes}
            onChange={(event) => setFitNotes(event.target.value)}
            rows={3}
            maxLength={2000}
            placeholder="Posture, shoulder drop, preferred ease."
          />
        </div>
      </div>

      <div className="fashionos-field">
        <label className="fashionos-label" htmlFor="fashionos-client-notes">
          Internal notes
        </label>
        <textarea
          className="fashionos-input fashionos-textarea"
          id="fashionos-client-notes"
          value={notes}
          onChange={(event) => setNotes(event.target.value)}
          rows={3}
          maxLength={5000}
          placeholder="Only your team sees these notes."
        />
      </div>

      {initial ? (
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-client-status">
            Status
          </label>
          <select
            className="fashionos-select"
            id="fashionos-client-status"
            value={status}
            onChange={(event) => setStatus(event.target.value)}
          >
            <option value="active">Active</option>
            <option value="archived">Archived</option>
          </select>
          <p className="fashionos-note">
            Archiving keeps the customer and every measurement version; it only hides them from the
            active list.
          </p>
        </div>
      ) : null}

      {error ? <Alert>{error}</Alert> : null}

      <div className="fashionos-actions">
        <button className="fashionos-btn fashionos-btn--primary" type="submit" disabled={busy}>
          {busy ? 'Saving…' : submitLabel}
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
