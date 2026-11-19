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
});
