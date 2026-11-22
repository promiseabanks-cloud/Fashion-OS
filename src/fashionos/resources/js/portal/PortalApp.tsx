import { useCallback, useEffect, useMemo, useState } from 'react';
import { createPortalClient, type PortalClient } from '../services/api';
import type {
  PortalInvoice,
  PortalInvoicesResponse,
  PortalOrder,
  PortalOrdersResponse,
  PortalSession,
} from '../types';
import {
  EmptyState,
  FullPageMessage,
  Loading,
  Pagination,
  Panel,
  Stat,
  formatDate,
  formatMoney,
  labelValue,
} from '../app/ui';

type Tab = 'orders' | 'invoices';

/**
 * The customer portal: one studio's branding, one customer's own records.
 *
 * Everything here comes from `/portal/*`, which the server scopes to the
 * customer behind the link. Draft orders, draft invoices and unverified
 * payments never reach this application, so the pages have nothing to hide.
 */
export function PortalApp({ restUrl, token }: { restUrl: string; token: string }) {
  const client = useMemo(() => createPortalClient({ restUrl, token }), [restUrl, token]);
  const [session, setSession] = useState<PortalSession | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [tab, setTab] = useState<Tab>('orders');

  useEffect(() => {
    let cancelled = false;

    client
      .session()
      .then((result) => {
        if (!cancelled) {
          setSession(result);
          setError(null);
        }
      })
      .catch((cause: unknown) => {
        if (!cancelled) {
          setError(
            cause instanceof Error ? cause.message : 'This portal link could not be opened.'
          );
        }
      });

    return () => {
      cancelled = true;
    };
  }, [client]);

  if (error) {
    return (
      <FullPageMessage
        eyebrow="Client portal"
        title="This link cannot be used"
        detail={`${error} Ask the studio to send you a new link.`}
      />
    );
  }

  if (session === null) {
    return (
      <FullPageMessage
        eyebrow="Client portal"
        title="Opening your portal"
        detail="Checking your access link."
      />
    );
  }

  const { studio, customer, access, summary } = session;
  const firstName = customer.name.trim().split(/\s+/)[0] || customer.name;
  const mark = studio.name.trim().charAt(0).toUpperCase() || 'F';

  return (
    <div className="fashionos-app fashionos-portal">
      <a className="fashionos-skip" href="#fashionos-portal-main">
        Skip to content
      </a>

      <header className="fashionos-topbar">
        <div className="fashionos-brand">
          <span className="fashionos-brand__mark" aria-hidden="true">
            {mark}
          </span>
          <span className="fashionos-brand__name">{studio.name}</span>
        </div>

        <div className="fashionos-account">
          <span className="fashionos-account__name">{customer.name}</span>
        </div>
      </header>

      <main className="fashionos-main fashionos-portal__main" id="fashionos-portal-main">
        <header>
          <p className="fashionos-eyebrow">Client portal</p>
          <h1 className="fashionos-view__title">Hello, {firstName}</h1>
          <p className="fashionos-view__lead">
            {customer.reference ? `${customer.reference} · ` : ''}
            Your orders and invoices with {studio.name}.
          </p>
        </header>

        <dl className="fashionos-stats">
          <Stat label="Orders in progress" value={String(summary.openOrders)} />
          <Stat label="Open invoices" value={String(summary.openInvoices)} />
          <Stat
            label="Outstanding"
            value={formatMoney(summary.outstanding, studio.currency)}
            detail={access.expiresAt ? `Link expires ${formatDate(access.expiresAt)}` : undefined}
          />
        </dl>

        <nav className="fashionos-portal__tabs" aria-label="Portal sections">
          <button
            className={`fashionos-portal__tab${
              tab === 'orders' ? ' fashionos-portal__tab--active' : ''
            }`}
            type="button"
            aria-current={tab === 'orders' ? 'page' : undefined}
            onClick={() => setTab('orders')}
          >
            Orders
          </button>
          <button
            className={`fashionos-portal__tab${
              tab === 'invoices' ? ' fashionos-portal__tab--active' : ''
            }`}
            type="button"
            aria-current={tab === 'invoices' ? 'page' : undefined}
            onClick={() => setTab('invoices')}
          >
            Invoices
          </button>
        </nav>

        {tab === 'orders' ? <PortalOrders client={client} /> : <PortalInvoices client={client} />}

        <p className="fashionos-portal__footer">
          Questions about an order or a payment? Reply to the studio directly — changes here are
          read-only.
        </p>
      </main>
    </div>
  );
}

function PortalOrders({ client }: { client: PortalClient }) {
  const [list, setList] = useState<PortalOrdersResponse | null>(null);
  const [detail, setDetail] = useState<PortalOrder | null>(null);
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;

    setBusy(true);

    client
      .orders({ page })
      .then((result) => {
        if (!cancelled) {
          setList(result);
          setError(null);
        }
      })
      .catch((cause: unknown) => {
        if (!cancelled) {
          setError(cause instanceof Error ? cause.message : 'Your orders could not be loaded.');
        }
      })
      .finally(() => {
        if (!cancelled) {
          setBusy(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [client, page]);

  const open = useCallback(
    async (orderId: number) => {
      setBusy(true);

      try {
        setDetail(await client.order(orderId));
        setError(null);
      } catch (cause: unknown) {
        setError(cause instanceof Error ? cause.message : 'That order could not be opened.');
      } finally {
        setBusy(false);
      }
    },
    [client]
  );

  if (detail) {
    return (
      <Panel
        title={`Order ${detail.reference}`}
        description={`${detail.garmentCount} ${
          detail.garmentCount === 1 ? 'garment' : 'garments'
        } · ${labelValue(detail.status)}`}
        action={
          <button
            className="fashionos-btn fashionos-btn--ghost"
            type="button"
            onClick={() => setDetail(null)}
          >
            Back to orders
          </button>
        }
      >
        <dl className="fashionos-stats">
          <Stat label="Status" value={labelValue(detail.status)} />
          <Stat label="Due" value={detail.dueDate || 'Not scheduled'} />
          <Stat label="Garments" value={String(detail.garmentCount)} />
        </dl>

        <div className="fashionos-table-wrap">
          <table className="fashionos-table">
            <thead>
              <tr>
                <th scope="col">Garment</th>
                <th scope="col">Category</th>
                <th scope="col">Quantity</th>
                <th scope="col">Progress</th>
              </tr>
            </thead>
            <tbody>
              {detail.items.map((garment) => (
                <tr key={garment.id}>
                  <td className="fashionos-table__strong">{garment.garmentName}</td>
                  <td className="fashionos-table__muted">{garment.garmentCategory || '—'}</td>
                  <td>{garment.quantity}</td>
                  <td>
                    <span className="fashionos-tag">{labelValue(garment.status)}</span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Panel>
    );
  }

  return (
    <Panel
      title="Your orders"
      description="Each garment moves through the studio's workflow on its own."
    >
      {error ? <p className="fashionos-alert">{error}</p> : null}

      {list === null ? (
        <Loading label="Loading your orders…" />
      ) : list.orders.length === 0 ? (
        <EmptyState
          title="No orders yet"
          detail="Orders appear here once the studio confirms them."
        />
      ) : (
        <>
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Order</th>
                  <th scope="col">Status</th>
                  <th scope="col">Garments</th>
                  <th scope="col">Due</th>
                  <th scope="col">
                    <span className="fashionos-visually-hidden">Open</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {list.orders.map((order) => (
                  <tr key={order.id}>
                    <td className="fashionos-table__strong">{order.reference}</td>
                    <td>
                      <span className="fashionos-tag">{labelValue(order.status)}</span>
                    </td>
                    <td className="fashionos-table__muted">{order.garmentCount}</td>
                    <td className="fashionos-table__muted">{order.dueDate || '—'}</td>
                    <td className="fashionos-table__actions">
                      <button
                        className="fashionos-btn fashionos-btn--ghost"
                        type="button"
                        disabled={busy}
                        onClick={() => void open(order.id)}
                      >
                        View
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <Pagination page={list.page} totalPages={list.totalPages} onChange={setPage} />
        </>
      )}
    </Panel>
  );
}

function PortalInvoices({ client }: { client: PortalClient }) {
  const [list, setList] = useState<PortalInvoicesResponse | null>(null);
  const [detail, setDetail] = useState<PortalInvoice | null>(null);
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;

    setBusy(true);

    client
      .invoices({ page })
      .then((result) => {
        if (!cancelled) {
          setList(result);
          setError(null);
        }
      })
      .catch((cause: unknown) => {
        if (!cancelled) {
          setError(cause instanceof Error ? cause.message : 'Your invoices could not be loaded.');
        }
      })
      .finally(() => {
        if (!cancelled) {
          setBusy(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [client, page]);

  const open = useCallback(
    async (invoiceId: number) => {
      setBusy(true);

      try {
        setDetail(await client.invoice(invoiceId));
        setError(null);
      } catch (cause: unknown) {
        setError(cause instanceof Error ? cause.message : 'That invoice could not be opened.');
      } finally {
        setBusy(false);
      }
    },
    [client]
  );

  if (detail) {
    return (
      <Panel
        title={`Invoice ${detail.reference}`}
        description={
          detail.orderReference
            ? `For order ${detail.orderReference} · ${labelValue(detail.status)}`
            : labelValue(detail.status)
        }
        action={
          <button
            className="fashionos-btn fashionos-btn--ghost"
            type="button"
            onClick={() => setDetail(null)}
          >
            Back to invoices
          </button>
        }
      >
        {detail.overdue ? (
          <p className="fashionos-alert">
            This invoice was due on {detail.dueDate}. Please contact the studio if it is already
            paid.
          </p>
        ) : null}

        <div className="fashionos-table-wrap">
          <table className="fashionos-table">
            <thead>
              <tr>
                <th scope="col">Line</th>
                <th scope="col">Quantity</th>
                <th scope="col">Unit price</th>
                <th scope="col">Total</th>
              </tr>
            </thead>
            <tbody>
              {detail.items.map((item, index) => (
                <tr key={`${item.description}-${index}`}>
                  <td className="fashionos-table__strong">{item.description}</td>
                  <td>{item.quantity}</td>
                  <td>{formatMoney(item.unitPrice, detail.currency)}</td>
                  <td>{formatMoney(item.lineTotal, detail.currency)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <dl className="fashionos-stats">
          <Stat label="Invoice total" value={formatMoney(detail.total, detail.currency)} />
          <Stat label="Paid" value={formatMoney(detail.amountPaid, detail.currency)} />
          <Stat label="Balance due" value={formatMoney(detail.balance, detail.currency)} />
          <Stat label="Due date" value={detail.dueDate || 'Not set'} />
        </dl>

        {detail.notes ? <p className="fashionos-note">{detail.notes}</p> : null}

        <div className="fashionos-subsection">
          <p className="fashionos-subhead">Payments received</p>

          {detail.payments.length === 0 ? (
            <EmptyState
              title="No payments recorded yet"
              detail="Payments show here once the studio has verified them."
            />
          ) : (
            <div className="fashionos-table-wrap">
              <table className="fashionos-table">
                <thead>
                  <tr>
                    <th scope="col">Reference</th>
                    <th scope="col">Method</th>
                    <th scope="col">Received</th>
                    <th scope="col">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  {detail.payments.map((payment) => (
                    <tr key={payment.reference}>
                      <td className="fashionos-table__strong">
                        {payment.reference}
                        {payment.providerReference ? (
                          <span className="fashionos-table__meta">{payment.providerReference}</span>
                        ) : null}
                      </td>
                      <td className="fashionos-table__muted">{labelValue(payment.method)}</td>
                      <td className="fashionos-table__muted">{formatDate(payment.paidAt)}</td>
                      <td>{formatMoney(payment.amount, detail.currency)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </Panel>
    );
  }

  return (
    <Panel title="Your invoices" description="Amounts come from the studio's verified payments.">
      {error ? <p className="fashionos-alert">{error}</p> : null}

      {list === null ? (
        <Loading label="Loading your invoices…" />
      ) : list.invoices.length === 0 ? (
        <EmptyState
          title="No invoices yet"
          detail="Invoices appear here once the studio issues them."
        />
      ) : (
        <>
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Invoice</th>
                  <th scope="col">Status</th>
                  <th scope="col">Total</th>
                  <th scope="col">Balance</th>
                  <th scope="col">Due</th>
                  <th scope="col">
                    <span className="fashionos-visually-hidden">Open</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {list.invoices.map((invoice) => (
                  <tr key={invoice.id}>
                    <td className="fashionos-table__strong">{invoice.reference}</td>
                    <td>
                      <span className="fashionos-tag">
                        {invoice.overdue ? 'Overdue' : labelValue(invoice.status)}
                      </span>
                    </td>
                    <td>{formatMoney(invoice.total, invoice.currency)}</td>
                    <td>{formatMoney(invoice.balance, invoice.currency)}</td>
                    <td className="fashionos-table__muted">{invoice.dueDate || '—'}</td>
                    <td className="fashionos-table__actions">
                      <button
                        className="fashionos-btn fashionos-btn--ghost"
                        type="button"
                        disabled={busy}
                        onClick={() => void open(invoice.id)}
                      >
                        View
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <Pagination page={list.page} totalPages={list.totalPages} onChange={setPage} />
        </>
      )}
    </Panel>
  );
}
