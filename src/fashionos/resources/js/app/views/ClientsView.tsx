import { useCallback, useEffect, useState, type FormEvent } from 'react';
import type { ApiClient } from '../../services/api';
import type { ActiveTenant, ClientInput, ClientListResponse } from '../../types';
import { Alert, EmptyState, Loading, Pagination, Panel, formatDate } from '../ui';
import { ClientDetailView } from './ClientDetailView';
import { ClientForm } from './ClientForm';

/**
 * Customer list with search, status filter and pagination.
 *
 * Selecting a customer swaps the list for the detail view; the list is reloaded
 * on return so edits and new measurements are reflected.
 */
export function ClientsView({ client, active }: { client: ApiClient; active: ActiveTenant }) {
  const [list, setList] = useState<ClientListResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('active');
  const [page, setPage] = useState(1);
  const [creating, setCreating] = useState(false);
  const [busy, setBusy] = useState(false);
  const [openClientId, setOpenClientId] = useState<number | null>(null);

  const canManage =
    active.permissions.includes('*') || active.permissions.includes('clients.manage');

  const load = useCallback(async () => {
    try {
      const result = await client.clients({ search, status, page });
      setList(result);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Customers could not be loaded.');
    }
  }, [client, search, status, page]);

  useEffect(() => {
    if (openClientId === null) {
      void load();
    }
  }, [load, openClientId]);

  const applySearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPage(1);
    setSearch(searchInput.trim());
  };

  const create = async (input: ClientInput) => {
    setBusy(true);

    try {
      await client.createClient(input);
      setCreating(false);
      setPage(1);
      await load();
    } finally {
      setBusy(false);
    }
  };

  if (openClientId !== null) {
    return (
      <ClientDetailView
        client={client}
        active={active}
        clientId={openClientId}
        onBack={() => setOpenClientId(null)}
      />
    );
  }

  return (
    <div className="fashionos-view">
      <header>
        <p className="fashionos-eyebrow">Customers</p>
        <h1 className="fashionos-view__title">Clients</h1>
        <p className="fashionos-view__lead">
          Every customer in {active.tenant.name}, with their contact details, preferences and
          measurement history.
        </p>
      </header>

      {error ? <Alert>{error}</Alert> : null}

      <Panel
        title="Customers"
        description={
          list ? `${list.total} ${list.total === 1 ? 'customer' : 'customers'}` : undefined
        }
        action={
          canManage ? (
            <button
              className="fashionos-btn fashionos-btn--primary"
              type="button"
              onClick={() => setCreating((value) => !value)}
            >
              {creating ? 'Close' : 'Add customer'}
            </button>
          ) : null
        }
      >
        {creating ? (
          <div className="fashionos-subsection">
            <ClientForm
              submitLabel="Create customer"
              busy={busy}
              onSubmit={create}
              onCancel={() => setCreating(false)}
            />
          </div>
        ) : null}

        <form className="fashionos-toolbar" onSubmit={applySearch}>
          <div className="fashionos-toolbar__group">
            <label className="fashionos-visually-hidden" htmlFor="fashionos-client-search">
              Search customers
            </label>
            <input
              className="fashionos-input"
              id="fashionos-client-search"
              type="search"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Search name, email, phone or reference"
            />
            <button className="fashionos-btn fashionos-btn--ghost" type="submit">
              Search
            </button>
          </div>

          <div className="fashionos-toolbar__group">
            <label className="fashionos-visually-hidden" htmlFor="fashionos-client-status-filter">
              Filter by status
            </label>
            <select
              className="fashionos-select fashionos-select--compact"
              id="fashionos-client-status-filter"
              value={status}
              onChange={(event) => {
                setStatus(event.target.value);
                setPage(1);
              }}
            >
              <option value="active">Active</option>
              <option value="archived">Archived</option>
              <option value="all">All</option>
            </select>
          </div>
        </form>

        {list === null ? (
          <Loading label="Loading customers…" />
        ) : list.clients.length === 0 ? (
          <EmptyState
            title={search ? 'No customers match that search' : 'No customers yet'}
            detail={
              search
                ? 'Try a different name, phone number or reference.'
                : 'Add your first customer to start recording measurements.'
            }
          />
        ) : (
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Customer</th>
                  <th scope="col">Contact</th>
                  <th scope="col">Profiles</th>
                  <th scope="col">Updated</th>
                  <th scope="col">
                    <span className="fashionos-visually-hidden">Open</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {list.clients.map((record) => (
                  <tr key={record.id}>
                    <td>
                      <span className="fashionos-table__strong">{record.name}</span>
                      <span className="fashionos-table__meta">
                        {record.reference}
                        {record.status !== 'active' ? ` · ${record.status}` : ''}
                      </span>
                    </td>
                    <td>
                      {record.email || record.phone ? (
                        <>
                          {record.email ? (
                            <span className="fashionos-table__meta">{record.email}</span>
                          ) : null}
                          {record.phone ? (
                            <span className="fashionos-table__meta">{record.phone}</span>
                          ) : null}
                        </>
                      ) : (
                        <span className="fashionos-table__muted">No contact yet</span>
                      )}
                    </td>
                    <td>{record.profileCount}</td>
                    <td className="fashionos-table__muted">{formatDate(record.updatedAt)}</td>
                    <td className="fashionos-table__actions">
                      <button
                        className="fashionos-btn fashionos-btn--ghost"
                        type="button"
                        onClick={() => setOpenClientId(record.id)}
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
