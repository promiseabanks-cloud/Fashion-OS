import { describe, expect, it } from 'vitest';
import { ApiError, createPortalClient, PORTAL_HEADER, WORKSPACE_HEADER } from './api';

const config = { restUrl: 'https://example.test/wp-json/fashionos/v1', token: 'portal-token-123' };

describe('createPortalClient', () => {
  it('sends the portal token instead of a nonce or workspace', async () => {
    let captured: { url: string; init?: RequestInit } | undefined;

    const fetchImpl: typeof fetch = async (input, init) => {
      captured = { url: String(input), init };

      return new Response(JSON.stringify({ success: true, data: { studio: {} } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    await createPortalClient(config, fetchImpl).session();

    const headers = new Headers(captured?.init?.headers);

    expect(captured?.url).toBe('https://example.test/wp-json/fashionos/v1/portal/session');
    expect(headers.get(PORTAL_HEADER)).toBe('portal-token-123');
    expect(headers.get('X-WP-Nonce')).toBeNull();
    expect(headers.get(WORKSPACE_HEADER)).toBeNull();
  });

  it('builds paginated order and invoice queries without empty parameters', async () => {
    const urls: string[] = [];

    const fetchImpl: typeof fetch = async (input) => {
      urls.push(String(input));
      return new Response(JSON.stringify({ success: true, data: { orders: [], invoices: [] } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const client = createPortalClient(config, fetchImpl);

    await client.orders({ page: 2, perPage: 20 });
    await client.orders({ page: 1 });
    await client.invoices({ perPage: 50 });

    expect(urls).toEqual([
      'https://example.test/wp-json/fashionos/v1/portal/orders?page=2&per_page=20',
      'https://example.test/wp-json/fashionos/v1/portal/orders',
      'https://example.test/wp-json/fashionos/v1/portal/invoices?per_page=50',
    ]);
  });

  it('requests one order and one invoice by id', async () => {
    const urls: string[] = [];

    const fetchImpl: typeof fetch = async (input) => {
      urls.push(String(input));
      return new Response(JSON.stringify({ success: true, data: { id: 1 } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const client = createPortalClient(config, fetchImpl);

    await client.order(7);
    await client.invoice(9);

    expect(urls).toEqual([
      'https://example.test/wp-json/fashionos/v1/portal/orders/7',
      'https://example.test/wp-json/fashionos/v1/portal/invoices/9',
    ]);
  });

  it('surfaces an invalid-link error so the portal can explain it', async () => {
    const failing = createPortalClient(
      config,
      async () =>
        new Response(
          JSON.stringify({
            success: false,
            error: {
              code: 'portal_access_invalid',
              message: 'This portal link is no longer valid.',
            },
          }),
          { status: 401, headers: { 'Content-Type': 'application/json' } }
        )
    );

    await expect(failing.session()).rejects.toMatchObject({
      name: 'ApiError',
      code: 'portal_access_invalid',
      status: 401,
    });
    await expect(failing.session()).rejects.toBeInstanceOf(ApiError);
  });
});
