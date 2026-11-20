import { describe, expect, it } from 'vitest';
import { ApiError, createApiClient, WORKSPACE_HEADER } from './api';

const config = { restUrl: 'https://example.test/wp-json/fashionos/v1/', nonce: 'test-nonce' };

function respondWith(body: unknown, status = 200): typeof fetch {
  return async () =>
    new Response(JSON.stringify(body), {
      status,
      headers: { 'Content-Type': 'application/json' },
    });
}

describe('createApiClient', () => {
  it('unwraps the success envelope and sends the REST nonce', async () => {
    let captured: { url: string; init?: RequestInit } | undefined;

    const fetchImpl: typeof fetch = async (input, init) => {
      captured = { url: String(input), init };
      return new Response(JSON.stringify({ success: true, data: { ok: true } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const result = await createApiClient(config, fetchImpl).request<{ ok: boolean }>(
      '/system/health'
    );

    expect(result).toEqual({ ok: true });
    expect(captured?.url).toBe('https://example.test/wp-json/fashionos/v1/system/health');
    expect(new Headers(captured?.init?.headers).get('X-WP-Nonce')).toBe('test-nonce');
    expect(new Headers(captured?.init?.headers).get(WORKSPACE_HEADER)).toBeNull();
  });

  it('sends the selected workspace after setWorkspace', async () => {
    let captured: RequestInit | undefined;

    const fetchImpl: typeof fetch = async (_input, init) => {
      captured = init;
      return new Response(JSON.stringify({ success: true, data: { entries: [] } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const client = createApiClient(config, fetchImpl);
    client.setWorkspace(12);

    await client.audit();

    expect(new Headers(captured?.headers).get(WORKSPACE_HEADER)).toBe('12');
  });

  it('surfaces the server error code and message', async () => {
    const client = createApiClient(
      config,
      respondWith(
        { success: false, error: { code: 'appointment_not_found', message: 'Not found.' } },
        404
      )
    );

    await expect(client.request('system/health')).rejects.toMatchObject({
      name: 'ApiError',
      code: 'appointment_not_found',
      message: 'Not found.',
      status: 404,
    });
  });

  it('falls back to a transport error when the body is not JSON', async () => {
    const fetchImpl: typeof fetch = async () => new Response('<html>error</html>', { status: 500 });
    const client = createApiClient(config, fetchImpl);

    await expect(client.request('system/health')).rejects.toBeInstanceOf(ApiError);
  });

  it('builds customer search queries without empty parameters', async () => {
    let captured = '';

    const fetchImpl: typeof fetch = async (input) => {
      captured = String(input);
      return new Response(JSON.stringify({ success: true, data: { clients: [] } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const client = createApiClient(config, fetchImpl);

    await client.clients({ search: 'amina yusuf', status: 'all', page: 2, perPage: 10 });

    expect(captured).toBe(
      'https://example.test/wp-json/fashionos/v1/clients?search=amina+yusuf&status=all&page=2&per_page=10'
    );

    await client.clients();

    expect(captured).toBe('https://example.test/wp-json/fashionos/v1/clients');
  });

  it('posts a new measurement version to the profile it belongs to', async () => {
    let captured: { url: string; init?: RequestInit } | undefined;

    const fetchImpl: typeof fetch = async (input, init) => {
      captured = { url: String(input), init };
      return new Response(JSON.stringify({ success: true, data: { id: 7 } }), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const client = createApiClient(config, fetchImpl);
    client.setWorkspace(3);

    await client.createMeasurementVersion(12, { values: { chest: 94 }, unit: 'cm' });

    expect(captured?.url).toBe(
      'https://example.test/wp-json/fashionos/v1/measurement-profiles/12/versions'
    );
    expect(captured?.init?.method).toBe('POST');
    expect(captured?.init?.body).toBe('{"values":{"chest":94},"unit":"cm"}');
    expect(new Headers(captured?.init?.headers).get(WORKSPACE_HEADER)).toBe('3');
  });

  it('builds design filters and omits page one', async () => {
    let captured = '';

    const fetchImpl: typeof fetch = async (input) => {
      captured = String(input);
      return new Response(JSON.stringify({ success: true, data: { designs: [] } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const client = createApiClient(config, fetchImpl);

    await client.designs({
      search: 'ivory suit',
      status: 'draft',
      clientId: 2,
      page: 2,
      perPage: 10,
    });

    expect(captured).toBe(
      'https://example.test/wp-json/fashionos/v1/designs?search=ivory+suit&status=draft&client_id=2&page=2&per_page=10'
    );

    await client.designs({ page: 1 });

    expect(captured).toBe('https://example.test/wp-json/fashionos/v1/designs');
  });

  it('posts quotation lines without computing totals on the client', async () => {
    let captured: { url: string; init?: RequestInit } | undefined;

    const fetchImpl: typeof fetch = async (input, init) => {
      captured = { url: String(input), init };
      return new Response(JSON.stringify({ success: true, data: { id: 9 } }), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const client = createApiClient(config, fetchImpl);

    await client.createQuotation({
      clientId: 4,
      items: [{ description: 'Wedding jacket', quantity: 1, unitPrice: '380000' }],
    });

    expect(captured?.url).toBe('https://example.test/wp-json/fashionos/v1/quotations');
    expect(captured?.init?.method).toBe('POST');
    expect(captured?.init?.body).toBe(
      '{"clientId":4,"items":[{"description":"Wedding jacket","quantity":1,"unitPrice":"380000"}]}'
    );
  });

  it('updates one garment and adds an order note on their own routes', async () => {
    const calls: Array<{ url: string; method?: string }> = [];

    const fetchImpl: typeof fetch = async (input, init) => {
      calls.push({ url: String(input), method: init?.method });
      return new Response(JSON.stringify({ success: true, data: { id: 1 } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const client = createApiClient(config, fetchImpl);

    await client.updateOrderItem(7, { status: 'cutting' });
    await client.addOrderNote(3, 'Customer approved the lining.');

    expect(calls).toEqual([
      { url: 'https://example.test/wp-json/fashionos/v1/order-items/7', method: 'PATCH' },
      { url: 'https://example.test/wp-json/fashionos/v1/orders/3/events', method: 'POST' },
    ]);
  });

  it('builds production job filters and completes a task on its own route', async () => {
    const calls: Array<{ url: string; method?: string; body?: unknown }> = [];

    const fetchImpl: typeof fetch = async (input, init) => {
      calls.push({ url: String(input), method: init?.method, body: init?.body });
      return new Response(JSON.stringify({ success: true, data: { id: 1 } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const client = createApiClient(config, fetchImpl);

    await client.productionJobs({ status: 'in_progress', page: 2, perPage: 10 });
    await client.updateProductionTask(4, { status: 'done' });

    expect(calls[0]?.url).toBe(
      'https://example.test/wp-json/fashionos/v1/production/jobs?status=in_progress&page=2&per_page=10'
    );
    expect(calls[1]).toEqual({
      url: 'https://example.test/wp-json/fashionos/v1/production/tasks/4',
      method: 'PATCH',
      body: '{"status":"done"}',
    });
  });

  it('sends the supply source when allocating material to a garment', async () => {
    let captured: { url: string; init?: RequestInit } | undefined;

    const fetchImpl: typeof fetch = async (input, init) => {
      captured = { url: String(input), init };
      return new Response(JSON.stringify({ success: true, data: { allocations: [] } }), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const client = createApiClient(config, fetchImpl);

    await client.allocateMaterial(9, {
      materialId: 3,
      quantity: '2.5',
      source: 'customer_supplied',
    });

    expect(captured?.url).toBe('https://example.test/wp-json/fashionos/v1/order-items/9/materials');
    expect(captured?.init?.method).toBe('POST');
    expect(captured?.init?.body).toBe(
      '{"materialId":3,"quantity":"2.5","source":"customer_supplied"}'
    );
  });

  it('schedules a fitting on the fittings route', async () => {
    let captured = '';

    const fetchImpl: typeof fetch = async (input) => {
      captured = String(input);
      return new Response(JSON.stringify({ success: true, data: { id: 5 } }), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const client = createApiClient(config, fetchImpl);

    await client.createFitting({ orderItemId: 2, scheduledAt: '2026-12-01 10:00:00' });

    expect(captured).toBe('https://example.test/wp-json/fashionos/v1/fittings');
  });

  it('builds invoice filters without empty parameters', async () => {
    let captured = '';

    const fetchImpl: typeof fetch = async (input) => {
      captured = String(input);
      return new Response(JSON.stringify({ success: true, data: { invoices: [] } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const client = createApiClient(config, fetchImpl);

    await client.invoices({
      search: 'amina',
      status: 'issued',
      clientId: 3,
      overdue: true,
      page: 2,
      perPage: 10,
    });

    expect(captured).toBe(
      'https://example.test/wp-json/fashionos/v1/invoices?search=amina&status=issued&client_id=3&overdue=1&page=2&per_page=10'
    );

    await client.invoices();

    expect(captured).toBe('https://example.test/wp-json/fashionos/v1/invoices');
  });

  it('sends an idempotency key when recording a payment', async () => {
    let captured: { url: string; init?: RequestInit } | undefined;

    const fetchImpl: typeof fetch = async (input, init) => {
      captured = { url: String(input), init };
      return new Response(JSON.stringify({ success: true, data: { id: 8 } }), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const client = createApiClient(config, fetchImpl);

    await client.createPayment({
      invoiceId: 4,
      amount: '250000',
      method: 'transfer',
      idempotencyKey: 'attempt-1',
    });

    expect(captured?.url).toBe('https://example.test/wp-json/fashionos/v1/payments');
    expect(captured?.init?.method).toBe('POST');
    expect(captured?.init?.body).toBe(
      '{"invoiceId":4,"amount":"250000","method":"transfer","idempotencyKey":"attempt-1"}'
    );
  });

  it('verifies a payment and voids an expense on their own routes', async () => {
    const calls: Array<{ url: string; method?: string; body?: unknown }> = [];

    const fetchImpl: typeof fetch = async (input, init) => {
      calls.push({ url: String(input), method: init?.method, body: init?.body });
      return new Response(JSON.stringify({ success: true, data: { id: 1 } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    const client = createApiClient(config, fetchImpl);

    await client.updatePayment(6, { status: 'verified' });
    await client.updateExpense(2, { status: 'void' });

    expect(calls).toEqual([
      {
        url: 'https://example.test/wp-json/fashionos/v1/payments/6',
        method: 'PATCH',
        body: '{"status":"verified"}',
      },
      {
        url: 'https://example.test/wp-json/fashionos/v1/expenses/2',
        method: 'PATCH',
        body: '{"status":"void"}',
      },
    ]);
  });
});
