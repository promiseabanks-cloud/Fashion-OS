import { useCallback, useEffect, useState, type FormEvent } from 'react';
import type { ApiClient } from '../../services/api';
import type {
  ActiveTenant,
  Client,
  Design,
  Order,
  OrderGarmentInput,
  OrderInput,
  OrderListResponse,
  Quotation,
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

type DraftGarment = {
  garmentName: string;
  quantity: string;
  unitPrice: string;
  designId: string;
};

const EMPTY_GARMENT: DraftGarment = {
  garmentName: '',
  quantity: '1',
  unitPrice: '',
  designId: '0',
};

const ORDER_STATUSES = ['draft', 'confirmed', 'in_progress', 'ready', 'delivered', 'cancelled'];
const GARMENT_STATUSES = [
  'pending',
  'cutting',
  'sewing',
  'fitting',
  'ready',
  'delivered',
  'cancelled',
];

/**
 * Orders: one customer, several independently tracked garments.
 *
 * Each garment stores its own design and measurement version reference, so a
 * jacket can be at fitting while the trousers are still being cut.
 */
export function OrdersView({ client, active }: { client: ApiClient; active: ActiveTenant }) {
  const [list, setList] = useState<OrderListResponse | null>(null);
  const [clients, setClients] = useState<Client[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');
  const [page, setPage] = useState(1);
  const [creating, setCreating] = useState(false);
  const [busy, setBusy] = useState(false);
  const [selected, setSelected] = useState<Order | null>(null);
  const [note, setNote] = useState('');

  const canManage =
    active.permissions.includes('*') || active.permissions.includes('orders.manage');

  const load = useCallback(async () => {
    try {
      const result = await client.orders({ search, status, page });
      setList(result);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Orders could not be loaded.');
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

  const create = async (input: OrderInput) => {
    setBusy(true);

    try {
      const created = await client.createOrder(input);
      setCreating(false);
      setPage(1);
      setSelected(created);
      await load();
    } finally {
      setBusy(false);
    }
  };

  const open = async (orderId: number) => {
    setBusy(true);

    try {
      setSelected(await client.order(orderId));
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The order could not be loaded.');
    } finally {
      setBusy(false);
    }
  };

  const changeOrderStatus = async (next: string) => {
    if (selected === null) {
      return;
    }

    setBusy(true);

    try {
      setSelected(await client.updateOrder(selected.id, { status: next }));
      await load();
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The order could not be updated.');
    } finally {
      setBusy(false);
    }
  };

  const changeGarmentStatus = async (itemId: number, next: string) => {
    if (selected === null) {
      return;
    }

    setBusy(true);

    try {
      setSelected(await client.updateOrderItem(itemId, { status: next }));
      await load();
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The garment could not be updated.');
    } finally {
      setBusy(false);
    }
  };

  const addNote = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (selected === null) {
      return;
    }

    setBusy(true);

    try {
      setSelected(await client.addOrderNote(selected.id, note));
      setNote('');
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The note could not be saved.');
    } finally {
      setBusy(false);
    }
  };

  if (selected !== null) {
    return (
      <div className="fashionos-view">
        <button className="fashionos-link-btn" type="button" onClick={() => setSelected(null)}>
          ← All orders
        </button>

        {error ? <Alert>{error}</Alert> : null}

        <header>
          <p className="fashionos-eyebrow">
            {selected.reference} · {selected.status}
          </p>
          <h1 className="fashionos-view__title">{selected.clientName}</h1>
          <p className="fashionos-view__lead">
            {selected.garmentCount} {selected.garmentCount === 1 ? 'garment' : 'garments'} ·{' '}
            {formatMoney(selected.total, selected.currency)}
            {selected.quotationReference ? ` · from quotation ${selected.quotationReference}` : ''}
          </p>
        </header>

        <Panel title="Garments" description="Each garment carries its own status and references.">
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Garment</th>
                  <th scope="col">Design</th>
                  <th scope="col">Measurements</th>
                  <th scope="col">Qty</th>
                  <th scope="col">Line total</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                {selected.items.map((item) => (
                  <tr key={item.id}>
                    <td>
                      <span className="fashionos-table__strong">{item.garmentName}</span>
                      {item.garmentCategory ? (
                        <span className="fashionos-table__meta">{item.garmentCategory}</span>
                      ) : null}
                    </td>
                    <td className="fashionos-table__muted">
                      {item.designTitle
                        ? `${item.designTitle} (${item.designReference})`
                        : 'No design'}
                    </td>
                    <td className="fashionos-table__muted">
                      {item.measurementVersionId > 0
                        ? `${item.profileName} v${item.measurementVersionNumber}`
                        : 'No version'}
                    </td>
                    <td>{item.quantity}</td>
                    <td>{formatMoney(item.lineTotal, selected.currency)}</td>
                    <td>
                      {canManage ? (
                        <select
                          className="fashionos-select fashionos-select--compact"
                          value={item.status}
                          disabled={busy}
                          aria-label={`Status for ${item.garmentName}`}
                          onChange={(event) =>
                            void changeGarmentStatus(item.id, event.target.value)
                          }
                        >
                          {GARMENT_STATUSES.map((choice) => (
                            <option key={choice} value={choice}>
                              {labelValue(choice)}
                            </option>
                          ))}
                        </select>
                      ) : (
                        labelValue(item.status)
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <dl className="fashionos-stats">
            <Stat label="Due" value={selected.dueDate || 'No due date'} />
            <Stat label="Created" value={formatDate(selected.createdAt)} />
            <Stat label="Updated" value={formatDate(selected.updatedAt)} />
            <Stat label="Order status" value={labelValue(selected.status)} />
          </dl>

          {selected.notes ? <p className="fashionos-note">{selected.notes}</p> : null}

          {canManage ? (
            <div className="fashionos-inline-form">
              <div className="fashionos-field">
                <label className="fashionos-label" htmlFor="fashionos-order-status">
                  Move the whole order
                </label>
                <select
                  className="fashionos-select"
                  id="fashionos-order-status"
                  value={selected.status}
                  disabled={busy}
                  onChange={(event) => void changeOrderStatus(event.target.value)}
                >
                  {ORDER_STATUSES.map((choice) => (
                    <option key={choice} value={choice}>
                      {labelValue(choice)}
                    </option>
                  ))}
                </select>
              </div>
              <p className="fashionos-hint">
                Garment statuses are independent, so moving the order does not overwrite them.
              </p>
            </div>
          ) : null}
        </Panel>

        <Panel title="Activity" description="Every status change and note, oldest first.">
          {selected.timeline.length === 0 ? (
            <EmptyState title="No activity recorded yet" />
          ) : (
            <ol className="fashionos-timeline">
              {selected.timeline.map((entry) => (
                <li className="fashionos-timeline__item" key={entry.id}>
                  <p className="fashionos-timeline__message">{entry.message}</p>
                  <p className="fashionos-timeline__meta">
                    {formatDate(entry.createdAt)}
                    {entry.actorName ? ` · ${entry.actorName}` : ''}
                  </p>
                </li>
              ))}
            </ol>
          )}

          {canManage ? (
            <form className="fashionos-inline-form" onSubmit={addNote}>
              <div className="fashionos-field">
                <label className="fashionos-label" htmlFor="fashionos-order-note">
                  Add a note
                </label>
                <input
                  className="fashionos-input"
                  id="fashionos-order-note"
                  value={note}
                  onChange={(event) => setNote(event.target.value)}
                  maxLength={2000}
                  placeholder="e.g. Customer approved the lining on Friday."
                />
              </div>
              <button
                className="fashionos-btn fashionos-btn--primary"
                type="submit"
                disabled={busy || note.trim() === ''}
              >
                {busy ? 'Saving…' : 'Add note'}
              </button>
            </form>
          ) : null}
        </Panel>
      </div>
    );
  }

  return (
    <div className="fashionos-view">
      <header>
        <p className="fashionos-eyebrow">Orders</p>
        <h1 className="fashionos-view__title">Orders</h1>
        <p className="fashionos-view__lead">
          Work in progress for {active.tenant.name}, with every garment tracked on its own.
        </p>
      </header>

      {error ? <Alert>{error}</Alert> : null}

      <Panel
        title="Orders"
        description={list ? `${list.total} ${list.total === 1 ? 'order' : 'orders'}` : undefined}
        action={
          canManage ? (
            <button
              className="fashionos-btn fashionos-btn--primary"
              type="button"
              disabled={clients.length === 0}
              onClick={() => setCreating((value) => !value)}
            >
              {creating ? 'Close' : 'New order'}
            </button>
          ) : null
        }
      >
        {creating ? (
          <div className="fashionos-subsection">
            <OrderForm
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
            <label className="fashionos-visually-hidden" htmlFor="fashionos-order-search">
              Search orders
            </label>
            <input
              className="fashionos-input"
              id="fashionos-order-search"
              type="search"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Search reference, customer or garment"
            />
            <button className="fashionos-btn fashionos-btn--ghost" type="submit">
              Search
            </button>
          </div>

          <div className="fashionos-toolbar__group">
            <label className="fashionos-visually-hidden" htmlFor="fashionos-order-filter">
              Filter by status
            </label>
            <select
              className="fashionos-select fashionos-select--compact"
              id="fashionos-order-filter"
              value={status}
              onChange={(event) => {
                setStatus(event.target.value);
                setPage(1);
              }}
            >
              <option value="all">All statuses</option>
              {ORDER_STATUSES.map((choice) => (
                <option key={choice} value={choice}>
                  {labelValue(choice)}
                </option>
              ))}
            </select>
          </div>
        </form>

        {list === null ? (
          <Loading label="Loading orders…" />
        ) : list.orders.length === 0 ? (
          <EmptyState
            title={search ? 'No orders match that search' : 'No orders yet'}
            detail={
              clients.length === 0
                ? 'Add a customer first, then create an order for them.'
                : 'Create an order directly, or copy the lines of a quotation.'
            }
          />
        ) : (
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Order</th>
                  <th scope="col">Customer</th>
                  <th scope="col">Garments</th>
                  <th scope="col">Total</th>
                  <th scope="col">Status</th>
                  <th scope="col">Due</th>
                  <th scope="col">
                    <span className="fashionos-visually-hidden">Open</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {list.orders.map((order) => (
                  <tr key={order.id}>
                    <td>
                      <span className="fashionos-table__strong">{order.reference}</span>
                      <span className="fashionos-table__meta">
                        {order.quotationReference
                          ? `Quotation ${order.quotationReference}`
                          : 'Direct order'}
                      </span>
                    </td>
                    <td>{order.clientName}</td>
                    <td>{order.garmentCount}</td>
                    <td>{formatMoney(order.total, order.currency)}</td>
                    <td>{labelValue(order.status)}</td>
                    <td className="fashionos-table__muted">
                      {order.dueDate ? formatDate(order.dueDate) : '—'}
                    </td>
                    <td className="fashionos-table__actions">
                      <button
                        className="fashionos-btn fashionos-btn--ghost"
                        type="button"
                        disabled={busy}
                        onClick={() => void open(order.id)}
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
 * Order builder. Leaving the garment rows empty after choosing a quotation
 * copies every quotation line, with the quotation's design on each garment.
 */
function OrderForm({
  client,
  clients,
  busy,
  onSubmit,
  onCancel,
}: {
  client: ApiClient;
  clients: Client[];
  busy: boolean;
  onSubmit: (input: OrderInput) => Promise<void>;
  onCancel?: () => void;
}) {
  const [clientId, setClientId] = useState(clients[0]?.id ?? 0);
  const [quotations, setQuotations] = useState<Quotation[]>([]);
  const [quotationId, setQuotationId] = useState(0);
  const [designs, setDesigns] = useState<Design[]>([]);
  const [dueDate, setDueDate] = useState('');
  const [notes, setNotes] = useState('');
  const [garments, setGarments] = useState<DraftGarment[]>([{ ...EMPTY_GARMENT }]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (clientId <= 0) {
      setQuotations([]);
      setDesigns([]);
      return;
    }

    let cancelled = false;

    Promise.all([
      client.quotations({ clientId, perPage: 100 }),
      client.designs({ clientId, perPage: 100 }),
    ])
      .then(([quotationList, designList]) => {
        if (!cancelled) {
          setQuotations(quotationList.quotations);
          setDesigns(designList.designs);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setQuotations([]);
          setDesigns([]);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [client, clientId]);

  const updateGarment = (index: number, change: Partial<DraftGarment>) => {
    setGarments((current) =>
      current.map((garment, position) => (position === index ? { ...garment, ...change } : garment))
    );
  };

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    const filled = garments.filter((garment) => garment.garmentName.trim() !== '');
    const items: OrderGarmentInput[] = filled.map((garment) => ({
      garmentName: garment.garmentName,
      quantity: garment.quantity,
      unitPrice: garment.unitPrice,
      designId: Number(garment.designId) || 0,
    }));

    try {
      await onSubmit({
        clientId,
        quotationId,
        dueDate,
        notes,
        items: quotationId > 0 && items.length === 0 ? undefined : items,
      });
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The order could not be saved.');
    }
  };

  return (
    <form className="fashionos-form" onSubmit={submit}>
      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-order-customer">
            Customer
          </label>
          <select
            className="fashionos-select"
            id="fashionos-order-customer"
            value={clientId}
            required
            disabled={quotationId > 0}
            onChange={(event) => {
              setClientId(Number(event.target.value));
              setQuotationId(0);
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
          <label className="fashionos-label" htmlFor="fashionos-order-quotation">
            Copy a quotation
          </label>
          <select
            className="fashionos-select"
            id="fashionos-order-quotation"
            value={quotationId}
            disabled={quotations.length === 0}
            onChange={(event) => {
              const next = Number(event.target.value);
              setQuotationId(next);

              const quotation = quotations.find((item) => item.id === next);

              if (quotation) {
                setClientId(quotation.clientId);
              }
            }}
          >
            <option value={0}>
              {quotations.length === 0 ? 'No quotations for this customer' : 'No quotation'}
            </option>
            {quotations.map((quotation) => (
              <option key={quotation.id} value={quotation.id}>
                {quotation.reference} · {formatMoney(quotation.total, quotation.currency)}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="fashionos-field">
        <p className="fashionos-label">Garments</p>
        {quotationId > 0 ? (
          <p className="fashionos-note">
            Leave every row empty to copy all lines from the quotation. Adding rows creates exactly
            those garments instead.
          </p>
        ) : null}
        <div className="fashionos-line-items">
          {garments.map((garment, index) => (
            <div className="fashionos-line-item" key={index}>
              <input
                className="fashionos-input"
                value={garment.garmentName}
                onChange={(event) => updateGarment(index, { garmentName: event.target.value })}
                placeholder="Garment, e.g. Wedding jacket"
                maxLength={120}
                aria-label={`Garment ${index + 1} name`}
              />
              <select
                className="fashionos-select"
                value={garment.designId}
                onChange={(event) => updateGarment(index, { designId: event.target.value })}
                aria-label={`Garment ${index + 1} design`}
              >
                <option value="0">No design</option>
                {designs.map((design) => (
                  <option key={design.id} value={design.id}>
                    {design.title}
                  </option>
                ))}
              </select>
              <input
                className="fashionos-input fashionos-input--narrow"
                value={garment.quantity}
                onChange={(event) => updateGarment(index, { quantity: event.target.value })}
                inputMode="numeric"
                placeholder="Qty"
                aria-label={`Garment ${index + 1} quantity`}
              />
              <input
                className="fashionos-input fashionos-input--narrow"
                value={garment.unitPrice}
                onChange={(event) => updateGarment(index, { unitPrice: event.target.value })}
                inputMode="decimal"
                placeholder="Unit price"
                aria-label={`Garment ${index + 1} unit price`}
              />
              <button
                className="fashionos-btn fashionos-btn--ghost"
                type="button"
                disabled={garments.length === 1}
                onClick={() =>
                  setGarments((current) => current.filter((_item, position) => position !== index))
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
            onClick={() => setGarments((current) => [...current, { ...EMPTY_GARMENT }])}
          >
            Add garment
          </button>
          <span className="fashionos-meta">
            A garment inherits its measurement version from the design chosen for it.
          </span>
        </div>
      </div>

      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-order-due">
            Due date
          </label>
          <input
            className="fashionos-input"
            id="fashionos-order-due"
            type="date"
            value={dueDate}
            onChange={(event) => setDueDate(event.target.value)}
          />
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-order-notes">
            Notes
          </label>
          <textarea
            className="fashionos-input fashionos-textarea"
            id="fashionos-order-notes"
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
          {busy ? 'Saving…' : 'Create order'}
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
