import type {
  ActiveTenant,
  AuditResponse,
  Client,
  ClientInput,
  ClientListResponse,
  ClientProfilesResponse,
  CreateWorkspaceInput,
  FashionOSConfig,
  HealthResponse,
  MeasurementProfileDetail,
  MeasurementTemplate,
  MeasurementTemplateInput,
  MeasurementVersion,
  MeasurementVersionInput,
  MeResponse,
  PlatformResponse,
  TeamResponse,
  TemplatesResponse,
  UpdateWorkspaceInput,
  WorkspaceSummary,
} from '../types';

/**
 * Header carrying the selected workspace. The server validates it against the
 * signed-in user's membership; it is a selector, never proof of access.
 */
export const WORKSPACE_HEADER = 'X-FashionOS-Workspace';

/**
 * Error thrown for failed API requests. `code` comes from the server envelope
 * so callers can branch on stable machine-readable values.
 */
export class ApiError extends Error {
  readonly code: string;
  readonly status: number;

  constructor(code: string, message: string, status: number) {
    super(message);
    this.name = 'ApiError';
    this.code = code;
    this.status = status;
  }
}

type ApiSuccess<T> = { success: true; data: T; meta?: Record<string, unknown> };
type ApiFailure = { success: false; error: { code: string; message: string } };
export type ApiEnvelope<T> = ApiSuccess<T> | ApiFailure;

export type ApiClient = ReturnType<typeof createApiClient>;

/**
 * Build the typed API client.
 *
 * Uses WordPress session cookies plus the REST nonce. The nonce protects
 * against CSRF; authorization is always enforced by the server.
 */
export function createApiClient(
  config: Pick<FashionOSConfig, 'restUrl' | 'nonce'> & { workspace?: string | null },
  fetchImpl: typeof fetch = globalThis.fetch.bind(globalThis)
) {
  let workspace = config.workspace ?? null;

  async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
    const url = `${config.restUrl.replace(/\/+$/, '')}/${path.replace(/^\/+/, '')}`;

    const headers: Record<string, string> = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce,
      ...((init.headers as Record<string, string> | undefined) ?? {}),
    };

    if (workspace) {
      headers[WORKSPACE_HEADER] = workspace;
    }

    const response = await fetchImpl(url, {
      ...init,
      credentials: 'same-origin',
      headers,
    });

    let payload: ApiEnvelope<T> | null = null;

    try {
      payload = (await response.json()) as ApiEnvelope<T>;
    } catch {
      payload = null;
    }

    if (!response.ok || payload === null || payload.success === false) {
      const code =
        payload !== null && payload.success === false ? payload.error.code : 'REQUEST_FAILED';
      const message =
        payload !== null && payload.success === false
          ? payload.error.message
          : `The request failed with status ${response.status}.`;

      throw new ApiError(code, message, response.status);
    }

    return payload.data;
  }

  function mutate<T>(path: string, method: string, body?: unknown): Promise<T> {
    return request<T>(path, {
      method,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  }

  return {
    request,
    /** Select the workspace sent with subsequent requests. */
    setWorkspace(value: string | number | null) {
      workspace = value === null ? null : String(value);
    },
    health: () => request<HealthResponse>('system/health'),
    me: () => request<MeResponse>('auth/me'),
    workspaces: () => request<{ workspaces: WorkspaceSummary[] }>('tenants'),
    createWorkspace: (input: CreateWorkspaceInput) =>
      mutate<ActiveTenant>('tenants', 'POST', input),
    currentWorkspace: () => request<ActiveTenant>('tenants/current'),
    updateWorkspace: (input: UpdateWorkspaceInput) =>
      mutate<ActiveTenant>('tenants/current', 'PATCH', input),
    team: () => request<TeamResponse>('team'),
    addMember: (input: { email: string; role: string }) =>
      mutate<{ added: boolean }>('team', 'POST', input),
    updateMember: (userId: number, role: string) =>
      mutate<{ updated: boolean }>(`team/${userId}`, 'PATCH', { role }),
    removeMember: (userId: number) => mutate<{ removed: boolean }>(`team/${userId}`, 'DELETE'),
    audit: () => request<AuditResponse>('audit'),
    platformTenants: () => request<PlatformResponse>('platform/tenants'),

    /** Paginated customer search. */
    clients: (
      params: { search?: string; status?: string; page?: number; perPage?: number } = {}
    ) => {
      const query = new URLSearchParams();

      if (params.search) {
        query.set('search', params.search);
      }

      if (params.status) {
        query.set('status', params.status);
      }

      if (params.page && params.page > 1) {
        query.set('page', String(params.page));
      }

      if (params.perPage) {
        query.set('per_page', String(params.perPage));
      }

      const suffix = query.toString();

      return request<ClientListResponse>(suffix ? `clients?${suffix}` : 'clients');
    },
    client: (clientId: number) => request<Client>(`clients/${clientId}`),
    createClient: (input: ClientInput) => mutate<Client>('clients', 'POST', input),
    updateClient: (clientId: number, input: ClientInput) =>
      mutate<Client>(`clients/${clientId}`, 'PATCH', input),
    clientMeasurements: (clientId: number) =>
      request<ClientProfilesResponse>(`clients/${clientId}/measurements`),
    createMeasurementProfile: (
      clientId: number,
      input: { profileName: string; templateId: number }
    ) => mutate<MeasurementProfileDetail>(`clients/${clientId}/measurements`, 'POST', input),

    measurementTemplates: () => request<TemplatesResponse>('measurement-templates'),
    createMeasurementTemplate: (input: MeasurementTemplateInput) =>
      mutate<MeasurementTemplate>('measurement-templates', 'POST', input),
    updateMeasurementTemplate: (templateId: number, input: MeasurementTemplateInput) =>
      mutate<MeasurementTemplate>(`measurement-templates/${templateId}`, 'PATCH', input),

    measurementProfile: (profileId: number) =>
      request<MeasurementProfileDetail>(`measurement-profiles/${profileId}`),
    createMeasurementVersion: (profileId: number, input: MeasurementVersionInput) =>
      mutate<MeasurementVersion>(`measurement-profiles/${profileId}/versions`, 'POST', input),
    measurementVersion: (versionId: number) =>
      request<MeasurementVersion>(`measurement-versions/${versionId}`),
  };
}
