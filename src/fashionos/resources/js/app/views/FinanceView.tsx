import { useCallback, useEffect, useState, type FormEvent } from 'react';
import type { ApiClient } from '../../services/api';
import type {
  ActiveTenant,
  Client,
  Expense,
  ExpenseInput,
  ExpenseListResponse,
  FinanceSummary,
  Invoice,
  InvoiceInput,
  InvoiceListResponse,
  Order,
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

const INVOICE_STATUSES = ['draft', 'issued', 'partially_paid', 'paid', 'void'];
const PAYMENT_METHODS = ['cash', 'transfer', 'card', 'pos'];

type DraftLine = { description: string; quantity: string; unitPrice: string };

const EMPTY_LINE: DraftLine = { description: '', quantity: '1', unitPrice: '' };

/**
 * Finance: invoices, payments, balances and expenses.
 *
 * Amounts on screen come from the server. The form's preview total is a
 * convenience only; the API recomputes every line and never trusts a total
 * sent by the browser.
 */
export function FinanceView({ client, active }: { client: ApiClient; active: ActiveTenant }) {
  const [summary, setSummary] = useState<FinanceSummary | null>(null);
  const [invoices, setInvoices] = useState<InvoiceListResponse | null>(null);
  const [expenses, setExpenses] = useState<ExpenseListResponse | null>(null);
  const [clients, setClients] = useState<Client[]>([]);
  const [selected, setSelected] = useState<Invoice | null>(null);
  const [invoiceSearch, setInvoiceSearch] = useState('');
  const [invoiceSearchInput, setInvoiceSearchInput] = useState('');
  const [invoiceStatus, setInvoiceStatus] = useState('all');
  const [invoicePage, setInvoicePage] = useState(1);
  const [expenseCategory, setExpenseCategory] = useState('all');
  const [expensePage, setExpensePage] = useState(1);
  const [creatingInvoice, setCreatingInvoice] = useState(false);
  const [addingExpense, setAddingExpense] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const can = (permission: string) =>
    active.permissions.includes('*') || active.permissions.includes(permission);

  const canManageInvoices = can('invoices.manage');
  const canManagePayments = can('payments.manage');
  const canManageExpenses = can('expenses.manage');

  const loadSummary = useCallback(async () => {
    try {
      setSummary(await client.financeSummary());
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The finance summary could not be loaded.');
    }
  }, [client]);

  const loadInvoices = useCallback(async () => {
    try {
      setInvoices(
        await client.invoices({
          search: invoiceSearch,
          status: invoiceStatus,
          page: invoicePage,
        })
      );
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Invoices could not be loaded.');
    }
  }, [client, invoiceSearch, invoiceStatus, invoicePage]);

  const loadExpenses = useCallback(async () => {
    try {
      setExpenses(
        await client.expenses({ category: expenseCategory, page: expensePage, perPage: 10 })
      );
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Expenses could not be loaded.');
    }
  }, [client, expenseCategory, expensePage]);

  useEffect(() => {
    void loadSummary();
  }, [loadSummary]);

  useEffect(() => {
    if (selected === null) {
      void loadInvoices();
    }
  }, [loadInvoices, selected]);

  useEffect(() => {
    void loadExpenses();
  }, [loadExpenses]);

  useEffect(() => {
    client
      .clients({ perPage: 100 })
      .then((result) => setClients(result.clients))
      .catch(() => setClients([]));
  }, [client]);

  const refreshDetail = async (invoiceId: number) => {
    setSelected(await client.invoice(invoiceId));
    await Promise.all([loadSummary(), loadInvoices()]);
  };

  const open = async (invoiceId: number) => {
    setBusy(true);

    try {
      setSelected(await client.invoice(invoiceId));
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The invoice could not be loaded.');
    } finally {
      setBusy(false);
    }
  };

  const createInvoice = async (input: InvoiceInput) => {
    setBusy(true);

    try {
      const created = await client.createInvoice(input);
      setCreatingInvoice(false);
      setInvoicePage(1);
      await loadInvoices();
      setSelected(created);
      await loadSummary();
    } finally {
      setBusy(false);
    }
  };

  const moveInvoice = async (invoiceId: number, status: string) => {
    setBusy(true);

    try {
      setSelected(await client.updateInvoice(invoiceId, { status }));
      await Promise.all([loadSummary(), loadInvoices()]);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The invoice could not be updated.');
    } finally {
      setBusy(false);
    }
  };

  const recordPayment = async (input: {
    amount: string;
    method: string;
    providerReference: string;
    notes: string;
    idempotencyKey: string;
  }) => {
    if (selected === null) {
      return;
    }

    setBusy(true);

    try {
      await client.createPayment({
        invoiceId: selected.id,
        amount: input.amount,
        method: input.method,
        providerReference: input.providerReference,
        notes: input.notes,
        idempotencyKey: input.idempotencyKey,
      });
      await refreshDetail(selected.id);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The payment could not be recorded.');
    } finally {
      setBusy(false);
    }
  };

  const decidePayment = async (paymentId: number, status: 'verified' | 'rejected') => {
    if (selected === null) {
      return;
    }

    setBusy(true);

    try {
      await client.updatePayment(paymentId, { status });
      await refreshDetail(selected.id);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The payment could not be updated.');
    } finally {
      setBusy(false);
    }
  };

  const addExpense = async (input: ExpenseInput) => {
    setBusy(true);

    try {
      await client.createExpense(input);
      setAddingExpense(false);
      setExpensePage(1);
      await Promise.all([loadExpenses(), loadSummary()]);
      setError(null);
    } finally {
      setBusy(false);
    }
  };

  const voidExpense = async (expense: Expense) => {
    setBusy(true);

    try {
      await client.updateExpense(expense.id, { status: 'void' });
      await Promise.all([loadExpenses(), loadSummary()]);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The expense could not be voided.');
    } finally {
      setBusy(false);
    }
  };

  if (selected !== null) {
    return (
      <div className="fashionos-view">
        <button className="fashionos-link-btn" type="button" onClick={() => setSelected(null)}>
          ← All invoices
        </button>

        {error ? <Alert>{error}</Alert> : null}

        <header>
          <p className="fashionos-eyebrow">
            {selected.reference} · {labelValue(selected.status)}
            {selected.overdue ? ' · overdue' : ''}
          </p>
          <h1 className="fashionos-view__title">{selected.clientName}</h1>
          <p className="fashionos-view__lead">
            {formatMoney(selected.total, selected.currency)} ·{' '}
            {formatMoney(selected.balance, selected.currency)} outstanding
            {selected.orderReference ? ` · order ${selected.orderReference}` : ''}
          </p>
        </header>

        <section
          className="fashionos-panel fashionos-print"
          aria-labelledby="fashionos-invoice-doc"
        >
          <div className="fashionos-panel__header">
            <div>
              <h2 className="fashionos-panel__title" id="fashionos-invoice-doc">
                Invoice {selected.reference}
              </h2>
              <p className="fashionos-panel__description">
                {active.tenant.name} · issued {selected.issueDate || '—'}
                {selected.dueDate ? ` · due ${selected.dueDate}` : ''}
              </p>
            </div>
            <button
              className="fashionos-btn fashionos-btn--ghost"
              type="button"
              onClick={() => window.print()}
            >
              Print / save as PDF
            </button>
          </div>

          <p className="fashionos-print__client">
            Billed to {selected.clientName}
            {selected.clientReference ? ` (${selected.clientReference})` : ''}
          </p>

          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Description</th>
                  <th scope="col">Qty</th>
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
            <Stat label="Total" value={formatMoney(selected.total, selected.currency)} />
            <Stat label="Paid" value={formatMoney(selected.amountPaid, selected.currency)} />
            <Stat label="Balance" value={formatMoney(selected.balance, selected.currency)} />
          </dl>

          {selected.notes ? <p className="fashionos-note">{selected.notes}</p> : null}
        </section>

        <Panel
          title="Payments"
          description="Money counts towards the balance only once it is verified."
        >
          {selected.payments.length === 0 ? (
            <EmptyState
              title="No payments recorded"
              detail={
                selected.status === 'draft'
                  ? 'Issue the invoice before recording a payment.'
                  : 'Record the first payment below.'
              }
            />
          ) : (
            <div className="fashionos-table-wrap">
              <table className="fashionos-table">
                <thead>
                  <tr>
                    <th scope="col">Payment</th>
                    <th scope="col">Received</th>
                    <th scope="col">Amount</th>
                    <th scope="col">Method</th>
                    <th scope="col">Status</th>
                    <th scope="col">
                      <span className="fashionos-visually-hidden">Actions</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {selected.payments.map((payment) => (
                    <tr key={payment.id}>
                      <td>
                        <span className="fashionos-table__strong">{payment.reference}</span>
                        {payment.providerReference ? (
                          <span className="fashionos-table__meta">{payment.providerReference}</span>
                        ) : null}
                      </td>
                      <td className="fashionos-table__muted">{formatDate(payment.paidAt)}</td>
                      <td>{formatMoney(payment.amount, selected.currency)}</td>
                      <td>{labelValue(payment.method)}</td>
                      <td>
                        <span className={`fashionos-pill fashionos-pill--${payment.status}`}>
                          {labelValue(payment.status)}
                        </span>
                        {payment.verifiedByName ? (
                          <span className="fashionos-table__meta">by {payment.verifiedByName}</span>
                        ) : null}
                      </td>
                      <td className="fashionos-table__actions">
                        {canManagePayments && payment.status === 'pending' ? (
                          <>
                            <button
                              className="fashionos-btn fashionos-btn--ghost"
                              type="button"
                              disabled={busy}
                              onClick={() => void decidePayment(payment.id, 'verified')}
                            >
                              Verify
                            </button>
                            <button
                              className="fashionos-btn fashionos-btn--ghost"
                              type="button"
                              disabled={busy}
                              onClick={() => void decidePayment(payment.id, 'rejected')}
                            >
                              Reject
                            </button>
                          </>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {canManagePayments && ['issued', 'partially_paid'].includes(selected.status) ? (
            <div className="fashionos-subsection">
              <PaymentForm
                key={`payment-${selected.balance}`}
                busy={busy}
                balance={selected.balance}
                currency={selected.currency}
                onSubmit={recordPayment}
              />
            </div>
          ) : null}
        </Panel>

        {canManageInvoices ? (
          <Panel title="Invoice status" quiet>
            <div className="fashionos-actions">
              {selected.status === 'draft' ? (
                <button
                  className="fashionos-btn fashionos-btn--primary"
                  type="button"
                  disabled={busy}
                  onClick={() => void moveInvoice(selected.id, 'issued')}
                >
                  Issue invoice
                </button>
              ) : null}
              {['draft', 'issued', 'partially_paid'].includes(selected.status) ? (
                <button
                  className="fashionos-btn fashionos-btn--ghost"
                  type="button"
                  disabled={busy}
                  onClick={() => void moveInvoice(selected.id, 'void')}
                >
                  Void invoice
                </button>
              ) : null}
            </div>
            <p className="fashionos-hint">
              Paid and partially paid are set by verified payments, not by hand. A void invoice
              keeps its number and history.
            </p>
          </Panel>
        ) : null}
      </div>
    );
  }

  return (
    <div className="fashionos-view">
      <header>
        <p className="fashionos-eyebrow">Finance</p>
        <h1 className="fashionos-view__title">Finance</h1>
        <p className="fashionos-view__lead">
          Invoices, verified payments and expenses for {active.tenant.name}, with balances the
          server computes from the ledger.
        </p>
      </header>

      {error ? <Alert>{error}</Alert> : null}

      <Panel
        title="Today"
        description="Balances are all-time; collections and expenses cover this month."
      >
        {summary === null ? (
          <Loading label="Loading the finance summary…" />
        ) : (
          <dl className="fashionos-stats">
            <Stat
              label="Outstanding"
              value={formatMoney(summary.outstanding, summary.currency)}
              detail={`${formatMoney(summary.invoicedOpen, summary.currency)} invoiced and open`}
            />
            <Stat
              label="Collected this month"
              value={formatMoney(summary.collectedThisMonth, summary.currency)}
              detail={`${summary.paidInvoices} paid ${summary.paidInvoices === 1 ? 'invoice' : 'invoices'}`}
            />
            <Stat
              label="Expenses this month"
              value={formatMoney(summary.expensesThisMonth, summary.currency)}
            />
            <Stat
              label="Overdue"
              value={String(summary.overdue)}
              detail={summary.overdue === 1 ? 'invoice past due' : 'invoices past due'}
            />
            <Stat
              label="Awaiting verification"
              value={formatMoney(summary.pendingPayments.total, summary.currency)}
              detail={`${summary.pendingPayments.count} ${summary.pendingPayments.count === 1 ? 'payment' : 'payments'}`}
            />
            <Stat label="Drafts" value={String(summary.drafts)} detail="not yet issued" />
          </dl>
        )}
      </Panel>

      {summary !== null && summary.overdueInvoices.length > 0 ? (
        <Panel title="Overdue invoices" description="Open, past the due date, oldest first.">
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Invoice</th>
                  <th scope="col">Customer</th>
                  <th scope="col">Due</th>
                  <th scope="col">Balance</th>
                  <th scope="col">
                    <span className="fashionos-visually-hidden">Open</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {summary.overdueInvoices.map((invoice) => (
                  <tr key={invoice.id}>
                    <td className="fashionos-table__strong">{invoice.reference}</td>
                    <td>{invoice.clientName}</td>
                    <td className="fashionos-table__muted">{invoice.dueDate}</td>
                    <td>{formatMoney(invoice.balance, invoice.currency)}</td>
                    <td className="fashionos-table__actions">
                      <button
                        className="fashionos-btn fashionos-btn--ghost"
                        type="button"
                        onClick={() => void open(invoice.id)}
                      >
                        Open
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Panel>
      ) : null}

      <Panel
        title="Invoices"
        description={
          invoices
            ? `${invoices.total} ${invoices.total === 1 ? 'invoice' : 'invoices'}`
            : undefined
        }
        action={
          canManageInvoices ? (
            <button
              className="fashionos-btn fashionos-btn--primary"
              type="button"
              disabled={clients.length === 0}
              onClick={() => setCreatingInvoice((value) => !value)}
            >
              {creatingInvoice ? 'Close' : 'New invoice'}
            </button>
          ) : null
        }
      >
        {creatingInvoice ? (
          <div className="fashionos-subsection">
            <InvoiceForm
              client={client}
              clients={clients}
              busy={busy}
              onSubmit={createInvoice}
              onCancel={() => setCreatingInvoice(false)}
            />
          </div>
        ) : null}

        <form
          className="fashionos-toolbar"
          onSubmit={(event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            setInvoicePage(1);
            setInvoiceSearch(invoiceSearchInput.trim());
          }}
        >
          <div className="fashionos-toolbar__group">
            <label className="fashionos-visually-hidden" htmlFor="fashionos-invoice-search">
              Search invoices
            </label>
            <input
              className="fashionos-input"
              id="fashionos-invoice-search"
              type="search"
              value={invoiceSearchInput}
              onChange={(event) => setInvoiceSearchInput(event.target.value)}
              placeholder="Search reference, customer or order"
            />
            <button className="fashionos-btn fashionos-btn--ghost" type="submit">
              Search
            </button>
          </div>

          <div className="fashionos-toolbar__group">
            <label className="fashionos-visually-hidden" htmlFor="fashionos-invoice-filter">
              Filter by status
            </label>
            <select
              className="fashionos-select fashionos-select--compact"
              id="fashionos-invoice-filter"
              value={invoiceStatus}
              onChange={(event) => {
                setInvoiceStatus(event.target.value);
                setInvoicePage(1);
              }}
            >
              <option value="all">All statuses</option>
              {INVOICE_STATUSES.map((status) => (
                <option key={status} value={status}>
                  {labelValue(status)}
                </option>
              ))}
            </select>
          </div>
        </form>

        {invoices === null ? (
          <Loading label="Loading invoices…" />
        ) : invoices.invoices.length === 0 ? (
          <EmptyState
            title={invoiceSearch ? 'No invoices match that search' : 'No invoices yet'}
            detail="Create one from an order, or price the lines yourself."
          />
        ) : (
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Invoice</th>
                  <th scope="col">Customer</th>
                  <th scope="col">Total</th>
                  <th scope="col">Balance</th>
                  <th scope="col">Status</th>
                  <th scope="col">Due</th>
                  <th scope="col">
                    <span className="fashionos-visually-hidden">Open</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {invoices.invoices.map((invoice) => (
                  <tr key={invoice.id}>
                    <td>
                      <span className="fashionos-table__strong">{invoice.reference}</span>
                      <span className="fashionos-table__meta">
                        {invoice.orderReference
                          ? `Order ${invoice.orderReference}`
                          : `${invoice.itemCount} ${invoice.itemCount === 1 ? 'line' : 'lines'}`}
                      </span>
                    </td>
                    <td>{invoice.clientName}</td>
                    <td>{formatMoney(invoice.total, invoice.currency)}</td>
                    <td>{formatMoney(invoice.balance, invoice.currency)}</td>
                    <td>
                      {labelValue(invoice.status)}
                      {invoice.overdue ? (
                        <span className="fashionos-table__meta fashionos-table__meta--alert">
                          Overdue
                        </span>
                      ) : null}
                    </td>
                    <td className="fashionos-table__muted">{invoice.dueDate || '—'}</td>
                    <td className="fashionos-table__actions">
                      <button
                        className="fashionos-btn fashionos-btn--ghost"
                        type="button"
                        disabled={busy}
                        onClick={() => void open(invoice.id)}
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

        {invoices ? (
          <Pagination
            page={invoices.page}
            totalPages={invoices.totalPages}
            onChange={setInvoicePage}
          />
        ) : null}
      </Panel>

      <Panel
        title="Expenses"
        description="Money leaving the studio, grouped by category."
        action={
          canManageExpenses ? (
            <button
              className="fashionos-btn fashionos-btn--ghost"
              type="button"
              onClick={() => setAddingExpense((value) => !value)}
            >
              {addingExpense ? 'Close' : 'Add expense'}
            </button>
          ) : null
        }
      >
        {addingExpense ? (
          <div className="fashionos-subsection">
            <ExpenseForm
              busy={busy}
              categories={expenses?.categories ?? []}
              onSubmit={addExpense}
              onCancel={() => setAddingExpense(false)}
            />
          </div>
        ) : null}

        <div className="fashionos-toolbar">
          <div className="fashionos-toolbar__group">
            <label className="fashionos-visually-hidden" htmlFor="fashionos-expense-filter">
              Filter by category
            </label>
            <select
              className="fashionos-select fashionos-select--compact"
              id="fashionos-expense-filter"
              value={expenseCategory}
              onChange={(event) => {
                setExpenseCategory(event.target.value);
                setExpensePage(1);
              }}
            >
              <option value="all">All categories</option>
              {(expenses?.categories ?? []).map((category) => (
                <option key={category} value={category}>
                  {labelValue(category)}
                </option>
              ))}
            </select>
          </div>
        </div>

        {expenses === null ? (
          <Loading label="Loading expenses…" />
        ) : expenses.expenses.length === 0 ? (
          <EmptyState title="No expenses recorded" detail="Record rent, materials or wages here." />
        ) : (
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Expense</th>
                  <th scope="col">Spent</th>
                  <th scope="col">Category</th>
                  <th scope="col">Amount</th>
                  <th scope="col">Status</th>
                  <th scope="col">
                    <span className="fashionos-visually-hidden">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {expenses.expenses.map((expense) => (
                  <tr key={expense.id}>
                    <td>
                      <span className="fashionos-table__strong">{expense.description}</span>
                      <span className="fashionos-table__meta">{expense.reference}</span>
                    </td>
                    <td className="fashionos-table__muted">{expense.spentAt}</td>
                    <td>{labelValue(expense.category)}</td>
                    <td>{formatMoney(expense.amount, expense.currency)}</td>
                    <td>{labelValue(expense.status)}</td>
                    <td className="fashionos-table__actions">
                      {canManageExpenses && expense.status === 'recorded' ? (
                        <button
                          className="fashionos-btn fashionos-btn--ghost"
                          type="button"
                          disabled={busy}
                          onClick={() => void voidExpense(expense)}
                        >
                          Void
                        </button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {expenses ? (
          <Pagination
            page={expenses.page}
            totalPages={expenses.totalPages}
            onChange={setExpensePage}
          />
        ) : null}
      </Panel>
    </div>
  );
}

/**
 * Invoice builder. Choosing an order and leaving the lines empty copies every
 * garment on that order; adding lines prices the invoice directly.
 */
function InvoiceForm({
  client,
  clients,
  busy,
  onSubmit,
  onCancel,
}: {
  client: ApiClient;
  clients: Client[];
  busy: boolean;
  onSubmit: (input: InvoiceInput) => Promise<void>;
  onCancel?: () => void;
}) {
  const [clientId, setClientId] = useState(clients[0]?.id ?? 0);
  const [orders, setOrders] = useState<Order[]>([]);
  const [orderId, setOrderId] = useState(0);
  const [issueDate, setIssueDate] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [notes, setNotes] = useState('');
  const [lines, setLines] = useState<DraftLine[]>([{ ...EMPTY_LINE }]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (clientId <= 0) {
      setOrders([]);
      return;
    }

    let cancelled = false;

    client
      .orders({ clientId, perPage: 100 })
      .then((result) => {
        if (!cancelled) {
          setOrders(result.orders);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setOrders([]);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [client, clientId]);

  const updateLine = (index: number, change: Partial<DraftLine>) => {
    setLines((current) =>
      current.map((line, position) => (position === index ? { ...line, ...change } : line))
    );
  };

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    const filled = lines.filter((line) => line.description.trim() !== '');

    try {
      await onSubmit({
        clientId,
        orderId,
        issueDate: issueDate || undefined,
        dueDate,
        notes,
        items:
          filled.length === 0 && orderId > 0
            ? undefined
            : filled.map((line) => ({
                description: line.description,
                quantity: line.quantity,
                unitPrice: line.unitPrice,
              })),
      });
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The invoice could not be created.');
    }
  };

  return (
    <form className="fashionos-form" onSubmit={submit}>
      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-invoice-customer">
            Customer
          </label>
          <select
            className="fashionos-select"
            id="fashionos-invoice-customer"
            value={clientId}
            required
            disabled={orderId > 0}
            onChange={(event) => {
              setClientId(Number(event.target.value));
              setOrderId(0);
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
          <label className="fashionos-label" htmlFor="fashionos-invoice-order">
            Copy an order
          </label>
          <select
            className="fashionos-select"
            id="fashionos-invoice-order"
            value={orderId}
            disabled={orders.length === 0}
            onChange={(event) => {
              const next = Number(event.target.value);
              setOrderId(next);

              const order = orders.find((item) => item.id === next);

              if (order) {
                setClientId(order.clientId);
              }
            }}
          >
            <option value={0}>
              {orders.length === 0 ? 'No orders for this customer' : 'No order'}
            </option>
            {orders.map((order) => (
              <option key={order.id} value={order.id}>
                {order.reference} · {formatMoney(order.total, order.currency)}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="fashionos-field">
        <p className="fashionos-label">Lines</p>
        {orderId > 0 ? (
          <p className="fashionos-note">
            Leave every row empty to bill all garments on the order. Adding rows bills exactly those
            lines instead.
          </p>
        ) : null}
        <div className="fashionos-line-items">
          {lines.map((line, index) => (
            <div className="fashionos-line-item" key={index}>
              <input
                className="fashionos-input"
                value={line.description}
                onChange={(event) => updateLine(index, { description: event.target.value })}
                placeholder="Description, e.g. Wedding jacket"
                maxLength={191}
                aria-label={`Line ${index + 1} description`}
              />
              <input
                className="fashionos-input fashionos-input--narrow"
                value={line.quantity}
                onChange={(event) => updateLine(index, { quantity: event.target.value })}
                inputMode="numeric"
                placeholder="Qty"
                aria-label={`Line ${index + 1} quantity`}
              />
              <input
                className="fashionos-input fashionos-input--narrow"
                value={line.unitPrice}
                onChange={(event) => updateLine(index, { unitPrice: event.target.value })}
                inputMode="decimal"
                placeholder="Unit price"
                aria-label={`Line ${index + 1} unit price`}
              />
              <button
                className="fashionos-btn fashionos-btn--ghost"
                type="button"
                disabled={lines.length === 1}
                onClick={() =>
                  setLines((current) => current.filter((_line, position) => position !== index))
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
          <span className="fashionos-meta">
            The server recomputes every line total and the invoice total.
          </span>
        </div>
      </div>

      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-invoice-issued">
            Issue date
          </label>
          <input
            className="fashionos-input"
            id="fashionos-invoice-issued"
            type="date"
            value={issueDate}
            onChange={(event) => setIssueDate(event.target.value)}
          />
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-invoice-due">
            Due date
          </label>
          <input
            className="fashionos-input"
            id="fashionos-invoice-due"
            type="date"
            value={dueDate}
            onChange={(event) => setDueDate(event.target.value)}
          />
        </div>
      </div>

      <div className="fashionos-field">
        <label className="fashionos-label" htmlFor="fashionos-invoice-notes">
          Notes
        </label>
        <textarea
          className="fashionos-input fashionos-textarea"
          id="fashionos-invoice-notes"
          value={notes}
          onChange={(event) => setNotes(event.target.value)}
          rows={2}
          maxLength={2000}
        />
      </div>

      {error ? <Alert>{error}</Alert> : null}

      <div className="fashionos-actions">
        <button
          className="fashionos-btn fashionos-btn--primary"
          type="submit"
          disabled={busy || clients.length === 0}
        >
          {busy ? 'Saving…' : 'Create invoice'}
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
 * Payment form. The idempotency key is generated when the form opens, so a
 * double submit records one payment rather than two.
 */
function PaymentForm({
  busy,
  balance,
  currency,
  onSubmit,
}: {
  busy: boolean;
  balance: number;
  currency: string;
  onSubmit: (input: {
    amount: string;
    method: string;
    providerReference: string;
    notes: string;
    idempotencyKey: string;
  }) => Promise<void>;
}) {
  const [amount, setAmount] = useState(String(balance));
  const [method, setMethod] = useState('transfer');
  const [providerReference, setProviderReference] = useState('');
  const [notes, setNotes] = useState('');
  const [idempotencyKey] = useState(() => crypto.randomUUID());
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    try {
      await onSubmit({ amount, method, providerReference, notes, idempotencyKey });
      setProviderReference('');
      setNotes('');
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The payment could not be recorded.');
    }
  };

  return (
    <form className="fashionos-form" onSubmit={submit}>
      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-payment-amount">
            Amount received ({currency})
          </label>
          <input
            className="fashionos-input"
            id="fashionos-payment-amount"
            value={amount}
            onChange={(event) => setAmount(event.target.value)}
            inputMode="decimal"
            required
          />
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-payment-method">
            Method
          </label>
          <select
            className="fashionos-select"
            id="fashionos-payment-method"
            value={method}
            onChange={(event) => setMethod(event.target.value)}
          >
            {PAYMENT_METHODS.map((choice) => (
              <option key={choice} value={choice}>
                {labelValue(choice)}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-payment-reference">
            Bank or provider reference
          </label>
          <input
            className="fashionos-input"
            id="fashionos-payment-reference"
            value={providerReference}
            onChange={(event) => setProviderReference(event.target.value)}
            maxLength={191}
            placeholder="e.g. GTB-8842190"
          />
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-payment-notes">
            Notes
          </label>
          <input
            className="fashionos-input"
            id="fashionos-payment-notes"
            value={notes}
            onChange={(event) => setNotes(event.target.value)}
            maxLength={2000}
          />
        </div>
      </div>

      {error ? <Alert>{error}</Alert> : null}

      <div className="fashionos-actions">
        <button className="fashionos-btn fashionos-btn--primary" type="submit" disabled={busy}>
          {busy ? 'Recording…' : 'Record payment'}
        </button>
        <span className="fashionos-meta">
          Payments start pending; verifying one moves the balance.
        </span>
      </div>
    </form>
  );
}

function ExpenseForm({
  busy,
  categories,
  onSubmit,
  onCancel,
}: {
  busy: boolean;
  categories: string[];
  onSubmit: (input: ExpenseInput) => Promise<void>;
  onCancel?: () => void;
}) {
  const [category, setCategory] = useState(categories[0] ?? 'materials');
  const [description, setDescription] = useState('');
  const [amount, setAmount] = useState('');
  const [spentAt, setSpentAt] = useState('');
  const [notes, setNotes] = useState('');
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    try {
      await onSubmit({ category, description, amount, spentAt: spentAt || undefined, notes });
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The expense could not be recorded.');
    }
  };

  return (
    <form className="fashionos-form" onSubmit={submit}>
      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-expense-category">
            Category
          </label>
          <select
            className="fashionos-select"
            id="fashionos-expense-category"
            value={category}
            onChange={(event) => setCategory(event.target.value)}
          >
            {categories.map((choice) => (
              <option key={choice} value={choice}>
                {labelValue(choice)}
              </option>
            ))}
          </select>
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-expense-amount">
            Amount
          </label>
          <input
            className="fashionos-input"
            id="fashionos-expense-amount"
            value={amount}
            onChange={(event) => setAmount(event.target.value)}
            inputMode="decimal"
            required
          />
        </div>
      </div>

      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-expense-description">
            Description
          </label>
          <input
            className="fashionos-input"
            id="fashionos-expense-description"
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            maxLength={191}
            placeholder="e.g. Ivory wool from the market"
            required
          />
        </div>

        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-expense-date">
            Date spent
          </label>
          <input
            className="fashionos-input"
            id="fashionos-expense-date"
            type="date"
            value={spentAt}
            onChange={(event) => setSpentAt(event.target.value)}
          />
        </div>
      </div>

      <div className="fashionos-field">
        <label className="fashionos-label" htmlFor="fashionos-expense-notes">
          Notes
        </label>
        <textarea
          className="fashionos-input fashionos-textarea"
          id="fashionos-expense-notes"
          value={notes}
          onChange={(event) => setNotes(event.target.value)}
          rows={2}
          maxLength={2000}
        />
      </div>

      {error ? <Alert>{error}</Alert> : null}

      <div className="fashionos-actions">
        <button className="fashionos-btn fashionos-btn--primary" type="submit" disabled={busy}>
          {busy ? 'Saving…' : 'Record expense'}
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
