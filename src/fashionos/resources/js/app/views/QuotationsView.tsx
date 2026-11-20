import { useCallback, useEffect, useState, type FormEvent } from 'react';
import type { ApiClient } from '../../services/api';
import type {
  ActiveTenant,
  Client,
  Design,
  Quotation,
  QuotationInput,
  QuotationItemInput,
  QuotationListResponse,
} from '../../types';
import {
  Alert,
  EmptyState,
  Loading,
  Pagination,
  Panel,
  Stat,
  formatDate,
  formatMoney,
  labelValue,
} from '../ui';

type DraftLine = { description: string; quantity: string; unitPrice: string };

const EMPTY_LINE: DraftLine = { description: '', quantity: '1', unitPrice: '' };

/**
 * Quotations: priced offers that stay separate from orders.
 *
 * Totals shown while editing are a preview only; the server recomputes every
 * line and the subtotal from quantity and unit price.
 */
export function QuotationsView({ client, active }: { client: ApiClient; active: ActiveTenant }) {
  const [list, setList] = useState<QuotationListResponse | null>(null);
  const [clients, setClients] = useState<Client[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');
  const [page, setPage] = useState(1);
  const [creating, setCreating] = useState(false);
  const [busy, setBusy] = useState(false);
  const [selected, setSelected] = useState<Quotation | null>(null);

  const canManage =
    active.permissions.includes('*') || active.permissions.includes('quotations.manage');

  const load = useCallback(async () => {
    try {
      const result = await client.quotations({ search, status, page });
      setList(result);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Quotations could not be loaded.');
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

  const create = async (input: QuotationInput) => {
    setBusy(true);

    try {
      await client.createQuotation(input);
      setCreating(false);
      setPage(1);
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
      const updated = await client.updateQuotation(selected.id, { status: next });
      setSelected(updated);
      await load();
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The quotation could not be updated.');
    } finally {
      setBusy(false);
    }
  };

  if (selected !== null) {
    return (
      <div className="fashionos-view">
        <button className="fashionos-link-btn" type="button" onClick={() => setSelected(null)}>
          ← All quotations
        </button>

        {error ? <Alert>{error}</Alert> : null}

        <header>
          <p className="fashionos-eyebrow">
            {selected.reference} · {selected.status}
          </p>
          <h1 className="fashionos-view__title">Quotation for {selected.clientName}</h1>
          <p className="fashionos-view__lead">
            {selected.designTitle ? `${selected.designTitle} · ` : ''}
            {selected.itemCount} {selected.itemCount === 1 ? 'line' : 'lines'} ·{' '}
            {formatMoney(selected.total, selected.currency)}
          </p>
        </header>

        <Panel
          title="Line items"
          description="Totals are recalculated by the server on every save."
        >
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Description</th>
                  <th scope="col">Quantity</th>
                  <th scope="col">Unit price</th>
                  <th scope="col">Line total</th>
                </tr>
              </thead>
              <tbody>
                {selected.items.map((item) => (
                  <tr key={item.id}>
                    <td className="fashionos-table__strong">{item.description}</td>
                    <td>{item.quantity}</td>
                    <td>{formatMoney(item.unitPrice, selected.currency)}</td>
                    <td>{formatMoney(item.lineTotal, selected.currency)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <dl className="fashionos-stats">
            <Stat label="Subtotal" value={formatMoney(selected.subtotal, selected.currency)} />
            <Stat label="Total" value={formatMoney(selected.total, selected.currency)} />
            <Stat label="Valid until" value={selected.validUntil || 'No expiry'} />
            <Stat label="Updated" value={formatDate(selected.updatedAt)} />
          </dl>

          {selected.notes ? <p className="fashionos-note">{selected.notes}</p> : null}

          {canManage ? (
            <div className="fashionos-inline-form">
              <div className="fashionos-field">
                <label className="fashionos-label" htmlFor="fashionos-quotation-status">
                  Status
                </label>
                <select
                  className="fashionos-select"
                  id="fashionos-quotation-status"
                  value={selected.status}
                  disabled={busy}
                  onChange={(event) => void changeStatus(event.target.value)}
                >
                  {QUOTATION_STATUSES.map((choice) => (
                    <option key={choice} value={choice}>
                      {labelValue(choice)}
                    </option>
                  ))}
                </select>
              </div>
              <p className="fashionos-hint">
                Line items can only be replaced while the quotation is a draft. Later changes are a
                new quotation.
              </p>
            </div>
          ) : null}
        </Panel>
      </div>
    );
  }

  return (
    <div className="fashionos-view">
      <header>
        <p className="fashionos-eyebrow">Quotations</p>
        <h1 className="fashionos-view__title">Quotations</h1>
        <p className="fashionos-view__lead">
          Offers sent to {active.tenant.name} customers, priced line by line and kept apart from
          orders.
        </p>
      </header>

      {error ? <Alert>{error}</Alert> : null}

      <Panel
        title="Quotations"
        description={
          list ? `${list.total} ${list.total === 1 ? 'quotation' : 'quotations'}` : undefined
        }
        action={
          canManage ? (
            <button
              className="fashionos-btn fashionos-btn--primary"
              type="button"
              disabled={clients.length === 0}
              onClick={() => setCreating((value) => !value)}
            >
              {creating ? 'Close' : 'New quotation'}
            </button>
          ) : null
        }
      >
        {creating ? (
          <div className="fashionos-subsection">
            <QuotationForm
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
            <label className="fashionos-visually-hidden" htmlFor="fashionos-quotation-search">
              Search quotations
            </label>
            <input
              className="fashionos-input"
              id="fashionos-quotation-search"
              type="search"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Search reference, customer or design"
            />
            <button className="fashionos-btn fashionos-btn--ghost" type="submit">
              Search
            </button>
          </div>

          <div className="fashionos-toolbar__group">
            <label className="fashionos-visually-hidden" htmlFor="fashionos-quotation-filter">
              Filter by status
            </label>
            <select
              className="fashionos-select fashionos-select--compact"
              id="fashionos-quotation-filter"
              value={status}
              onChange={(event) => {
                setStatus(event.target.value);
                setPage(1);
              }}
            >
              <option value="all">All statuses</option>
              {QUOTATION_STATUSES.map((choice) => (
                <option key={choice} value={choice}>
                  {labelValue(choice)}
                </option>
              ))}
            </select>
          </div>
        </form>

        {list === null ? (
          <Loading label="Loading quotations…" />
        ) : list.quotations.length === 0 ? (
          <EmptyState
            title={search ? 'No quotations match that search' : 'No quotations yet'}
            detail={
              clients.length === 0
                ? 'Add a customer first, then price an offer for them.'
                : 'Create a quotation with its line items; it stays separate from any order.'
            }
          />
        ) : (
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Quotation</th>
                  <th scope="col">Customer</th>
                  <th scope="col">Design</th>
                  <th scope="col">Total</th>
                  <th scope="col">Status</th>
                  <th scope="col">Updated</th>
                  <th scope="col">
                    <span className="fashionos-visually-hidden">Open</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {list.quotations.map((quotation) => (
                  <tr key={quotation.id}>
                    <td>
                      <span className="fashionos-table__strong">{quotation.reference}</span>
                      <span className="fashionos-table__meta">
                        {quotation.itemCount} {quotation.itemCount === 1 ? 'line' : 'lines'}
                      </span>
                    </td>
                    <td>{quotation.clientName}</td>
                    <td className="fashionos-table__muted">{quotation.designTitle || '—'}</td>
                    <td>{formatMoney(quotation.total, quotation.currency)}</td>
                    <td>{labelValue(quotation.status)}</td>
                    <td className="fashionos-table__muted">{formatDate(quotation.updatedAt)}</td>
                    <td className="fashionos-table__actions">
                      <button
                        className="fashionos-btn fashionos-btn--ghost"
                        type="button"
                        onClick={() => setSelected(quotation)}
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

const QUOTATION_STATUSES = ['draft', 'sent', 'accepted', 'declined', 'expired'];

/**
 * Quotation builder with repeatable priced lines.
 */
function QuotationForm({
  client,
  clients,
  busy,
  onSubmit,
  onCancel,
}: {
  client: ApiClient;
  clients: Client[];
  busy: boolean;
  onSubmit: (input: QuotationInput) => Promise<void>;
  onCancel?: () => void;
}) {
  const [clientId, setClientId] = useState(clients[0]?.id ?? 0);
  const [designs, setDesigns] = useState<Design[]>([]);
  const [designId, setDesignId] = useState(0);
  const [validUntil, setValidUntil] = useState('');
  const [notes, setNotes] = useState('');
  const [lines, setLines] = useState<DraftLine[]>([{ ...EMPTY_LINE }]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (clientId <= 0) {
      setDesigns([]);
      return;
    }

    let cancelled = false;

    client
      .designs({ clientId, perPage: 100 })
      .then((result) => {
        if (!cancelled) {
          setDesigns(result.designs);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setDesigns([]);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [client, clientId]);

  const subtotal = lines.reduce(
    (sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.unitPrice) || 0),
    0
  );

  const updateLine = (index: number, change: Partial<DraftLine>) => {
    setLines((current) =>
      current.map((line, position) => (position === index ? { ...line, ...change } : line))
    );
  };

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    const items: QuotationItemInput[] = lines.map((line) => ({
      description: line.description,
      quantity: line.quantity,
      unitPrice: line.unitPrice,
    }));

    try {
      await onSubmit({ clientId, designId, validUntil, notes, items });
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The quotation could not be saved.');
    }
  };

  return (
    <form className="fashionos-form" onSubmit={submit}>
      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-quotation-customer">
            Customer
          </label>
          <select
            className="fashionos-select"
            id="fashionos-quotation-customer"
            value={clientId}
            required
            onChange={(event) => {
              setClientId(Number(event.target.value));
              setDesignId(0);
            }}
          >
            {clients.length === 0 ? <option value={0}>Add a customer first</option> : null}
            {clients.map((record) => (
              <option key={record.id} value={record.id}>
                {record.name} ({record.reference})
              </option>
            ))}
          </select>
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-quotation-design">
            Design
          </label>
          <select
            className="fashionos-select"
            id="fashionos-quotation-design"
            value={designId}
            disabled={designs.length === 0}
            onChange={(event) => setDesignId(Number(event.target.value))}
          >
            <option value={0}>
              {designs.length === 0 ? 'No designs for this customer' : 'No design referenced'}
            </option>
            {designs.map((design) => (
              <option key={design.id} value={design.id}>
                {design.title} ({design.reference})
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="fashionos-field">
        <p className="fashionos-label">Line items</p>
        <div className="fashionos-line-items">
          {lines.map((line, index) => (
            <div className="fashionos-line-item" key={index}>
              <input
                className="fashionos-input"
                value={line.description}
                onChange={(event) => updateLine(index, { description: event.target.value })}
                placeholder="e.g. Two-piece wool suit"
                maxLength={191}
                required
                aria-label={`Line ${index + 1} description`}
              />
              <input
                className="fashionos-input fashionos-input--narrow"
                value={line.quantity}
                onChange={(event) => updateLine(index, { quantity: event.target.value })}
                inputMode="numeric"
                placeholder="Qty"
                aria-label={`Line ${index + 1} quantity`}
                required
              />
              <input
                className="fashionos-input fashionos-input--narrow"
                value={line.unitPrice}
                onChange={(event) => updateLine(index, { unitPrice: event.target.value })}
                inputMode="decimal"
                placeholder="Unit price"
                aria-label={`Line ${index + 1} unit price`}
                required
              />
              <button
                className="fashionos-btn fashionos-btn--ghost"
                type="button"
                disabled={lines.length === 1}
                onClick={() =>
                  setLines((current) => current.filter((_item, position) => position !== index))
                }
              >
                Remove
              </button>
            </div>
          ))}
        </div>
        <div className="fashionos-toolbar__group">
          <button
            className="fashionos-btn fashionos-btn--ghost"
            type="button"
            onClick={() => setLines((current) => [...current, { ...EMPTY_LINE }])}
          >
            Add line
          </button>
          <span className="fashionos-meta">Preview subtotal: {subtotal.toFixed(2)}</span>
        </div>
      </div>

      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-quotation-valid">
            Valid until
          </label>
          <input
            className="fashionos-input"
            id="fashionos-quotation-valid"
            type="date"
            value={validUntil}
            onChange={(event) => setValidUntil(event.target.value)}
          />
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-quotation-notes">
            Notes
          </label>
          <textarea
            className="fashionos-input fashionos-textarea"
            id="fashionos-quotation-notes"
            value={notes}
            onChange={(event) => setNotes(event.target.value)}
            rows={2}
            maxLength={2000}
          />
        </div>
      </div>

      {error ? <Alert>{error}</Alert> : null}

      <div className="fashionos-actions">
        <button
          className="fashionos-btn fashionos-btn--primary"
          type="submit"
          disabled={busy || clients.length === 0}
        >
          {busy ? 'Saving…' : 'Create quotation'}
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
