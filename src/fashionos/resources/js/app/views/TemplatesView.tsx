import { useCallback, useEffect, useState, type FormEvent } from 'react';
import type { ApiClient } from '../../services/api';
import type {
  ActiveTenant,
  MeasurementField,
  MeasurementTemplate,
  MeasurementTemplateInput,
} from '../../types';
import { Alert, EmptyState, Loading, Panel, formatDate } from '../ui';

type DraftField = { label: string; hint: string; required: boolean };

const BLANK_FIELD: DraftField = { label: '', hint: '', required: false };

/**
 * Measurement templates.
 *
 * Templates are the configurable part of measurements: a garment category and
 * the fields it needs. Editing fields bumps the template's schema version, and
 * recorded versions keep their own snapshot, so history stays readable.
 */
export function TemplatesView({ client, active }: { client: ApiClient; active: ActiveTenant }) {
  const [templates, setTemplates] = useState<MeasurementTemplate[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [editor, setEditor] = useState<{ template?: MeasurementTemplate } | null>(null);
  const [busy, setBusy] = useState(false);

  const canManage =
    active.permissions.includes('*') || active.permissions.includes('measurements.manage');

  const load = useCallback(async () => {
    try {
      const result = await client.measurementTemplates();
      setTemplates(result.templates);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Templates could not be loaded.');
    }
  }, [client]);

  useEffect(() => {
    void load();
  }, [load]);

  const save = async (input: MeasurementTemplateInput) => {
    setBusy(true);

    try {
      if (editor?.template) {
        await client.updateMeasurementTemplate(editor.template.id, input);
      } else {
        await client.createMeasurementTemplate(input);
      }

      setEditor(null);
      await load();
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fashionos-view">
      <header>
        <p className="fashionos-eyebrow">Measurements</p>
        <h1 className="fashionos-view__title">Templates</h1>
        <p className="fashionos-view__lead">
          One template per garment category. Templates define the fields, labels and unit used when
          measuring.
        </p>
      </header>

      {error ? <Alert>{error}</Alert> : null}

      {editor ? (
        <Panel
          title={editor.template ? `Edit ${editor.template.name}` : 'New template'}
          description="Recorded versions keep a snapshot of the fields used, so edits never change history."
        >
          <TemplateForm
            initial={editor.template}
            busy={busy}
            onSubmit={save}
            onCancel={() => setEditor(null)}
          />
        </Panel>
      ) : (
        <Panel
          title="Templates"
          description={
            templates
              ? `${templates.length} ${templates.length === 1 ? 'template' : 'templates'}`
              : undefined
          }
          action={
            canManage ? (
              <button
                className="fashionos-btn fashionos-btn--primary"
                type="button"
                onClick={() => setEditor({})}
              >
                New template
              </button>
            ) : null
          }
        >
          {templates === null ? (
            <Loading label="Loading templates…" />
          ) : templates.length === 0 ? (
            <EmptyState
              title="No measurement templates yet"
              detail="Create one to describe the measurements a garment category needs."
            />
          ) : (
            <div className="fashionos-table-wrap">
              <table className="fashionos-table">
                <thead>
                  <tr>
                    <th scope="col">Template</th>
                    <th scope="col">Unit</th>
                    <th scope="col">Fields</th>
                    <th scope="col">Schema</th>
                    <th scope="col">Status</th>
                    <th scope="col">Updated</th>
                    <th scope="col">
                      <span className="fashionos-visually-hidden">Actions</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {templates.map((template) => (
                    <tr key={template.id}>
                      <td>
                        <span className="fashionos-table__strong">{template.name}</span>
                        <span className="fashionos-table__meta">{template.garmentCategory}</span>
                      </td>
                      <td>{template.unit}</td>
                      <td>{template.fields.length}</td>
                      <td>v{template.schemaVersion}</td>
                      <td>
                        <span className="fashionos-tag fashionos-tag--muted">
                          {template.status}
                        </span>
                      </td>
                      <td className="fashionos-table__muted">{formatDate(template.updatedAt)}</td>
                      <td className="fashionos-table__actions">
                        {canManage ? (
                          <button
                            className="fashionos-btn fashionos-btn--ghost"
                            type="button"
                            onClick={() => setEditor({ template })}
                          >
                            Edit
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
      )}
    </div>
  );
}

/**
 * Template editor with repeatable field rows.
 */
function TemplateForm({
  initial,
  busy,
  onSubmit,
  onCancel,
}: {
  initial?: MeasurementTemplate;
  busy: boolean;
  onSubmit: (input: MeasurementTemplateInput) => Promise<void>;
  onCancel: () => void;
}) {
  const [name, setName] = useState(initial?.name ?? '');
  const [garmentCategory, setGarmentCategory] = useState(initial?.garmentCategory ?? '');
  const [unit, setUnit] = useState(initial?.unit ?? 'cm');
  const [status, setStatus] = useState(initial?.status ?? 'active');
  const [fields, setFields] = useState<DraftField[]>(
    initial ? initial.fields.map(toDraft) : [{ ...BLANK_FIELD }]
  );
  const [error, setError] = useState<string | null>(null);

  const updateField = (index: number, patch: Partial<DraftField>) => {
    setFields((current) =>
      current.map((field, position) => (position === index ? { ...field, ...patch } : field))
    );
  };

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    const cleaned = fields
      .map((field) => ({ ...field, label: field.label.trim(), hint: field.hint.trim() }))
      .filter((field) => field.label !== '');

    if (cleaned.length === 0) {
      setError('Add at least one measurement field.');
      return;
    }

    try {
      await onSubmit({
        name,
        garmentCategory,
        unit,
        ...(initial ? { status } : {}),
        fields: cleaned.map((field) => ({
          label: field.label,
          hint: field.hint,
          required: field.required,
          key: slug(field.label),
        })),
      });
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The template could not be saved.');
    }
  };

  return (
    <form className="fashionos-form" onSubmit={submit}>
      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-template-name">
            Template name
          </label>
          <input
            className="fashionos-input"
            id="fashionos-template-name"
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="e.g. Three-piece suit"
            maxLength={120}
            required
          />
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-template-category">
            Garment category
          </label>
          <input
            className="fashionos-input"
            id="fashionos-template-category"
            value={garmentCategory}
            onChange={(event) => setGarmentCategory(event.target.value)}
            placeholder="e.g. Suit"
            maxLength={60}
            required
          />
        </div>
      </div>

      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-template-unit">
            Capture unit
          </label>
          <select
            className="fashionos-select"
            id="fashionos-template-unit"
            value={unit}
            onChange={(event) => setUnit(event.target.value)}
          >
            <option value="cm">Centimetres</option>
            <option value="in">Inches</option>
          </select>
        </div>

        {initial ? (
          <div className="fashionos-field">
            <label className="fashionos-label" htmlFor="fashionos-template-status">
              Status
            </label>
            <select
              className="fashionos-select"
              id="fashionos-template-status"
              value={status}
              onChange={(event) => setStatus(event.target.value)}
            >
              <option value="active">Active</option>
              <option value="archived">Archived</option>
            </select>
          </div>
        ) : null}
      </div>

      <div className="fashionos-field">
        <span className="fashionos-label">Measurement fields</span>

        <div className="fashionos-field-list">
          {fields.map((field, index) => (
            <div className="fashionos-field-row" key={index}>
              <input
                className="fashionos-input"
                value={field.label}
                onChange={(event) => updateField(index, { label: event.target.value })}
                placeholder="Label, e.g. Chest"
                aria-label={`Field ${index + 1} label`}
                maxLength={120}
              />
              <input
                className="fashionos-input"
                value={field.hint}
                onChange={(event) => updateField(index, { hint: event.target.value })}
                placeholder="Hint (optional)"
                aria-label={`Field ${index + 1} hint`}
                maxLength={200}
              />
              <label className="fashionos-check">
                <input
                  type="checkbox"
                  checked={field.required}
                  onChange={(event) => updateField(index, { required: event.target.checked })}
                />
                Required
              </label>
              <button
                className="fashionos-btn fashionos-btn--danger"
                type="button"
                disabled={fields.length === 1}
                onClick={() =>
                  setFields((current) => current.filter((_item, position) => position !== index))
                }
              >
                Remove
              </button>
            </div>
          ))}
        </div>

        <div className="fashionos-actions">
          <button
            className="fashionos-btn fashionos-btn--ghost"
            type="button"
            onClick={() => setFields((current) => [...current, { ...BLANK_FIELD }])}
          >
            Add field
          </button>
        </div>
      </div>

      {error ? <Alert>{error}</Alert> : null}

      <div className="fashionos-actions">
        <button className="fashionos-btn fashionos-btn--primary" type="submit" disabled={busy}>
          {busy ? 'Saving…' : initial ? 'Save template' : 'Create template'}
        </button>
        <button className="fashionos-btn fashionos-btn--ghost" type="button" onClick={onCancel}>
          Cancel
        </button>
      </div>
    </form>
  );
}

function toDraft(field: MeasurementField): DraftField {
  return { label: field.label, hint: field.hint, required: field.required };
}

function slug(value: string): string {
  return value
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_|_$/g, '')
    .slice(0, 60);
}
