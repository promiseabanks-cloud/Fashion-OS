import type {
  ActiveTenant,
  AllocationInput,
  AllocationResponse,
  Alteration,
  AlterationInput,
  AlterationsResponse,
  AuditResponse,
  Client,
  ClientInput,
  ClientListResponse,
  ClientProfilesResponse,
  CreateWorkspaceInput,
  Design,
  DesignInput,
  DesignListResponse,
  Expense,
  ExpenseInput,
  ExpenseListResponse,
  FashionOSConfig,
  FinanceSummary,
  Fitting,
  FittingInput,
  FittingsResponse,
  HealthResponse,
  Invoice,
  InvoiceInput,
  InvoiceListResponse,
  MeasurementProfileDetail,
  MeasurementTemplate,
  MeasurementTemplateInput,
  MeasurementVersion,
  MeasurementVersionInput,
  Material,
  MaterialInput,
  MaterialMovementInput,
  MaterialsResponse,
  MeResponse,
  Order,
  OrderInput,
  OrderItemInput,
  OrderListResponse,
  Payment,
  PaymentInput,
  PaymentListResponse,
  PlatformResponse,
  ProductionDashboard,
  ProductionJob,
  ProductionJobInput,
  ProductionJobListResponse,
  ProductionStage,
  ProductionStageInput,
  ProductionTaskInput,
  QualityCheck,
  QualityCheckInput,
  QualityChecksResponse,
  Quotation,
  QuotationInput,
  QuotationListResponse,
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

  /**
   * Build a query string from optional filters, dropping blanks and page 1.
   */
  function filters(params: Record<string, string | number | undefined | null>): string {
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
      if (
        value !== undefined &&
        value !== null &&
        value !== '' &&
        !(key === 'page' && value === 1)
      ) {
        query.set(key, String(value));
      }
    });

    const suffix = query.toString();

    return suffix ? `?${suffix}` : '';
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

    /** Designs, each optionally citing a measurement profile and version. */
    designs: (
      params: {
        search?: string;
        status?: string;
        clientId?: number;
        page?: number;
        perPage?: number;
      } = {}
    ) =>
      request<DesignListResponse>(
        `designs${filters({
          search: params.search,
          status: params.status,
          client_id: params.clientId,
          page: params.page,
          per_page: params.perPage,
        })}`
      ),
    design: (designId: number) => request<Design>(`designs/${designId}`),
    createDesign: (input: DesignInput) => mutate<Design>('designs', 'POST', input),
    updateDesign: (designId: number, input: DesignInput) =>
      mutate<Design>(`designs/${designId}`, 'PATCH', input),

    /** Quotations, separate from orders. */
    quotations: (
      params: {
        search?: string;
        status?: string;
        clientId?: number;
        page?: number;
        perPage?: number;
      } = {}
    ) =>
      request<QuotationListResponse>(
        `quotations${filters({
          search: params.search,
          status: params.status,
          client_id: params.clientId,
          page: params.page,
          per_page: params.perPage,
        })}`
      ),
    quotation: (quotationId: number) => request<Quotation>(`quotations/${quotationId}`),
    createQuotation: (input: QuotationInput) => mutate<Quotation>('quotations', 'POST', input),
    updateQuotation: (quotationId: number, input: QuotationInput) =>
      mutate<Quotation>(`quotations/${quotationId}`, 'PATCH', input),

    /** Orders containing independently tracked garments. */
    orders: (
      params: {
        search?: string;
        status?: string;
        clientId?: number;
        page?: number;
        perPage?: number;
      } = {}
    ) =>
      request<OrderListResponse>(
        `orders${filters({
          search: params.search,
          status: params.status,
          client_id: params.clientId,
          page: params.page,
          per_page: params.perPage,
        })}`
      ),
    order: (orderId: number) => request<Order>(`orders/${orderId}`),
    createOrder: (input: OrderInput) => mutate<Order>('orders', 'POST', input),
    updateOrder: (orderId: number, input: OrderInput) =>
      mutate<Order>(`orders/${orderId}`, 'PATCH', input),
    updateOrderItem: (itemId: number, input: OrderItemInput) =>
      mutate<Order>(`order-items/${itemId}`, 'PATCH', input),
    addOrderNote: (orderId: number, message: string) =>
      mutate<Order>(`orders/${orderId}/events`, 'POST', { message }),

    /** Production stages, jobs, tasks and the dashboard. */
    productionStages: (includeInactive = false) =>
      request<{ stages: ProductionStage[] }>(
        `production/stages${filters({ include_inactive: includeInactive ? 1 : undefined })}`
      ),
    createProductionStage: (input: ProductionStageInput) =>
      mutate<ProductionStage>('production/stages', 'POST', input),
    updateProductionStage: (stageId: number, input: ProductionStageInput) =>
      mutate<ProductionStage>(`production/stages/${stageId}`, 'PATCH', input),
    productionJobs: (
      params: {
        search?: string;
        status?: string;
        assignedTo?: number;
        orderItemId?: number;
        page?: number;
        perPage?: number;
      } = {}
    ) =>
      request<ProductionJobListResponse>(
        `production/jobs${filters({
          search: params.search,
          status: params.status,
          assigned_to: params.assignedTo,
          order_item_id: params.orderItemId,
          page: params.page,
          per_page: params.perPage,
        })}`
      ),
    productionJob: (jobId: number) => request<ProductionJob>(`production/jobs/${jobId}`),
    createProductionJob: (input: ProductionJobInput) =>
      mutate<ProductionJob>('production/jobs', 'POST', input),
    updateProductionJob: (jobId: number, input: ProductionJobInput) =>
      mutate<ProductionJob>(`production/jobs/${jobId}`, 'PATCH', input),
    addProductionTask: (jobId: number, input: ProductionTaskInput) =>
      mutate<ProductionJob>(`production/jobs/${jobId}/tasks`, 'POST', input),
    updateProductionTask: (taskId: number, input: ProductionTaskInput) =>
      mutate<ProductionJob>(`production/tasks/${taskId}`, 'PATCH', input),
    productionDashboard: () => request<ProductionDashboard>('production/dashboard'),

    /** Material catalogue, stock and garment allocations. */
    materials: (
      params: {
        search?: string;
        type?: string;
        lowStock?: boolean;
        includeInactive?: boolean;
        page?: number;
        perPage?: number;
      } = {}
    ) =>
      request<MaterialsResponse>(
        `materials${filters({
          search: params.search,
          type: params.type,
          low_stock: params.lowStock ? 1 : undefined,
          include_inactive: params.includeInactive ? 1 : undefined,
          page: params.page,
          per_page: params.perPage,
        })}`
      ),
    material: (materialId: number) => request<Material>(`materials/${materialId}`),
    createMaterial: (input: MaterialInput) => mutate<Material>('materials', 'POST', input),
    updateMaterial: (materialId: number, input: MaterialInput) =>
      mutate<Material>(`materials/${materialId}`, 'PATCH', input),
    addMaterialMovement: (materialId: number, input: MaterialMovementInput) =>
      mutate<Material>(`materials/${materialId}/movements`, 'POST', input),
    orderItemMaterials: (itemId: number) =>
      request<AllocationResponse>(`order-items/${itemId}/materials`),
    allocateMaterial: (itemId: number, input: AllocationInput) =>
      mutate<AllocationResponse>(`order-items/${itemId}/materials`, 'POST', input),
    releaseMaterialAllocation: (allocationId: number) =>
      mutate<AllocationResponse>(`material-allocations/${allocationId}`, 'DELETE'),

    /** Fittings and alterations. */
    fittings: (
      params: {
        status?: string;
        upcoming?: boolean;
        orderItemId?: number;
        page?: number;
        perPage?: number;
      } = {}
    ) =>
      request<FittingsResponse>(
        `fittings${filters({
          status: params.status,
          upcoming: params.upcoming ? 1 : undefined,
          order_item_id: params.orderItemId,
          page: params.page,
          per_page: params.perPage,
        })}`
      ),
    fitting: (fittingId: number) => request<Fitting>(`fittings/${fittingId}`),
    createFitting: (input: FittingInput) => mutate<Fitting>('fittings', 'POST', input),
    updateFitting: (fittingId: number, input: FittingInput) =>
      mutate<Fitting>(`fittings/${fittingId}`, 'PATCH', input),
    alterations: (
      params: { status?: string; orderItemId?: number; page?: number; perPage?: number } = {}
    ) =>
      request<AlterationsResponse>(
        `alterations${filters({
          status: params.status,
          order_item_id: params.orderItemId,
          page: params.page,
          per_page: params.perPage,
        })}`
      ),
    createAlteration: (input: AlterationInput) => mutate<Alteration>('alterations', 'POST', input),
    updateAlteration: (alterationId: number, input: AlterationInput) =>
      mutate<Alteration>(`alterations/${alterationId}`, 'PATCH', input),

    /** Quality-control checks. */
    qualityChecks: (
      params: { result?: string; orderItemId?: number; page?: number; perPage?: number } = {}
    ) =>
      request<QualityChecksResponse>(
        `quality-checks${filters({
          result: params.result,
          order_item_id: params.orderItemId,
          page: params.page,
          per_page: params.perPage,
        })}`
      ),
    createQualityCheck: (input: QualityCheckInput) =>
      mutate<QualityCheck>('quality-checks', 'POST', input),

    /** Invoices, balances and the finance summary. */
    invoices: (
      params: {
        search?: string;
        status?: string;
        clientId?: number;
        overdue?: boolean;
        page?: number;
        perPage?: number;
      } = {}
    ) =>
      request<InvoiceListResponse>(
        `invoices${filters({
          search: params.search,
          status: params.status,
          client_id: params.clientId,
          overdue: params.overdue ? 1 : undefined,
          page: params.page,
          per_page: params.perPage,
        })}`
      ),
    invoice: (invoiceId: number) => request<Invoice>(`invoices/${invoiceId}`),
    createInvoice: (input: InvoiceInput) => mutate<Invoice>('invoices', 'POST', input),
    updateInvoice: (invoiceId: number, input: InvoiceInput) =>
      mutate<Invoice>(`invoices/${invoiceId}`, 'PATCH', input),

    /** Payments recorded against an invoice, then verified by staff. */
    payments: (
      params: { invoiceId?: number; status?: string; page?: number; perPage?: number } = {}
    ) =>
      request<PaymentListResponse>(
        `payments${filters({
          invoice_id: params.invoiceId,
          status: params.status,
          page: params.page,
          per_page: params.perPage,
        })}`
      ),
    createPayment: (input: PaymentInput) => mutate<Payment>('payments', 'POST', input),
    updatePayment: (paymentId: number, input: PaymentInput) =>
      mutate<Payment>(`payments/${paymentId}`, 'PATCH', input),

    /** Studio expenses and their categories. */
    expenses: (
      params: {
        search?: string;
        category?: string;
        status?: string;
        page?: number;
        perPage?: number;
      } = {}
    ) =>
      request<ExpenseListResponse>(
        `expenses${filters({
          search: params.search,
          category: params.category,
          status: params.status,
          page: params.page,
          per_page: params.perPage,
        })}`
      ),
    createExpense: (input: ExpenseInput) => mutate<Expense>('expenses', 'POST', input),
    updateExpense: (expenseId: number, input: ExpenseInput) =>
      mutate<Expense>(`expenses/${expenseId}`, 'PATCH', input),

    financeSummary: () => request<FinanceSummary>('finance/summary'),
  };
}
