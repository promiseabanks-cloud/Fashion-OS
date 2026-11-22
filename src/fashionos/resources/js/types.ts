/**
 * Runtime configuration provided by WordPress and API contracts shared by the
 * frontend. Types mirror the PHP response envelopes; keep them in sync.
 */

export type FashionOSConfig = {
  version: string;
  restUrl: string;
  nonce: string;
  homeUrl: string;
  logoutUrl: string;
  isDevelopment: boolean;
  /** Set by the portal shell, which renders for customers without a login. */
  mode?: 'studio' | 'portal';
  /** Portal token from the link the studio issued. Never sent with studio calls. */
  portalToken?: string;
  user: {
    id: number;
    displayName: string;
  };
};

export type DatabaseStatus = {
  latestVersion: string;
  applied: number;
  pending: number;
};

export type BuildStatus = {
  built: boolean;
  development: boolean;
};

export type HealthResponse = {
  pluginVersion: string;
  database: DatabaseStatus;
  build: BuildStatus;
  server: {
    php: string;
    wordpress: string;
  };
};

export type RoleChoice = {
  slug: string;
  name: string;
};

export type Role = RoleChoice & {
  permissions: string[];
};

export type Tenant = {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  status: string;
  timezone: string;
  currency: string;
  createdAt: string;
};

export type WorkspaceSummary = {
  id: number;
  name: string;
  slug: string;
  status: string;
  role: RoleChoice;
};

export type ActiveTenant = {
  tenant: Tenant;
  role: RoleChoice;
  permissions: string[];
  isOwner: boolean;
  memberCount: number;
  roles?: Role[];
};

export type MeResponse = {
  user: {
    id: number;
    displayName: string;
    canManagePlatform: boolean;
  };
  workspaces: WorkspaceSummary[];
  activeTenant: ActiveTenant | null;
  workspaceError: string | null;
};

export type TeamMember = {
  userId: number;
  name: string;
  email: string;
  role: string;
  roleName: string;
  status: string;
  joinedAt: string;
  isOwner: boolean;
};

export type TeamResponse = {
  members: TeamMember[];
  roles: RoleChoice[];
};

export type AuditEntry = {
  id: number;
  action: string;
  actorId: number;
  actorName: string;
  entityType: string;
  entityId: number;
  metadata: Record<string, unknown>;
  createdAt: string;
};

export type AuditResponse = {
  entries: AuditEntry[];
};

export type PlatformTenant = {
  id: number;
  name: string;
  slug: string;
  status: string;
  memberCount: number;
  createdAt: string;
};

export type PlatformResponse = {
  tenants: PlatformTenant[];
};

export type CreateWorkspaceInput = {
  name: string;
  timezone?: string;
  currency?: string;
};

export type UpdateWorkspaceInput = {
  name?: string;
  timezone?: string;
  currency?: string;
};

/* -------------------------------------------------------------------------- */
/* Phase 2: customers and measurements                                        */
/* -------------------------------------------------------------------------- */

export type ClientPreferences = {
  preferredContact: string;
  styleNotes: string;
  fitNotes: string;
};

export type Client = {
  id: number;
  uuid: string;
  reference: string;
  name: string;
  email: string;
  phone: string;
  notes: string;
  status: string;
  preferences: ClientPreferences;
  profileCount: number;
  createdAt: string;
  updatedAt: string;
};

export type ClientListResponse = {
  clients: Client[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type ClientInput = {
  name?: string;
  email?: string;
  phone?: string;
  notes?: string;
  status?: string;
  preferences?: Partial<ClientPreferences>;
};

export type MeasurementField = {
  key: string;
  label: string;
  hint: string;
  required: boolean;
};

export type MeasurementTemplate = {
  id: number;
  uuid: string;
  name: string;
  garmentCategory: string;
  unit: string;
  status: string;
  schemaVersion: number;
  fields: MeasurementField[];
  createdAt: string;
  updatedAt: string;
};

export type MeasurementTemplateInput = {
  name?: string;
  garmentCategory?: string;
  unit?: string;
  status?: string;
  fields?: Array<Partial<MeasurementField>>;
};

export type LatestVersion = {
  id: number;
  versionNumber: number;
  takenAt: string;
  takenByName: string;
};

export type MeasurementProfile = {
  id: number;
  uuid: string;
  clientId: number;
  profileName: string;
  garmentCategory: string;
  templateId: number;
  templateName: string;
  status: string;
  versionCount: number;
  latestVersion: LatestVersion | null;
  createdAt: string;
  updatedAt: string;
};

export type MeasurementVersionSummary = {
  id: number;
  versionNumber: number;
  templateId: number;
  templateVersion: number;
  unit: string;
  takenAt: string;
  takenBy: { id: number; name: string };
  notes: string;
  fitNotes: string;
  valueCount: number;
  createdAt: string;
};

export type MeasurementProfileDetail = MeasurementProfile & {
  history: MeasurementVersionSummary[];
};

export type ClientProfilesResponse = {
  clientId: number;
  profiles: MeasurementProfile[];
};

export type MeasurementValue = {
  key: string;
  label: string;
  value: number;
  unit: string;
};

export type MeasurementVersion = MeasurementVersionSummary & {
  profileId: number;
  profileName: string;
  clientId: number;
  template: {
    id: number;
    name: string;
    garmentCategory: string;
    unit: string;
    schemaVersion: number;
    fields: MeasurementField[];
  };
  values: MeasurementValue[];
};

export type MeasurementVersionInput = {
  values: Record<string, string | number>;
  unit?: string;
  notes?: string;
  fitNotes?: string;
  takenAt?: string;
};

export type TemplatesResponse = {
  templates: MeasurementTemplate[];
};

/* -------------------------------------------------------------------------- */
/* Phase 3: designs, quotations and orders                                    */
/* -------------------------------------------------------------------------- */

export type Design = {
  id: number;
  uuid: string;
  reference: string;
  clientId: number;
  clientName: string;
  measurementProfileId: number;
  profileName: string;
  measurementVersionId: number;
  measurementVersionNumber: number;
  measurementTakenAt: string;
  title: string;
  garmentCategory: string;
  brief: string;
  status: string;
  createdAt: string;
  updatedAt: string;
};

export type DesignListResponse = {
  designs: Design[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type DesignInput = {
  title?: string;
  clientId?: number;
  garmentCategory?: string;
  brief?: string;
  status?: string;
  measurementProfileId?: number;
  measurementVersionId?: number;
};

export type QuotationItem = {
  id: number;
  description: string;
  quantity: number;
  unitPrice: number;
  lineTotal: number;
};

export type Quotation = {
  id: number;
  uuid: string;
  reference: string;
  clientId: number;
  clientName: string;
  designId: number;
  designTitle: string;
  currency: string;
  status: string;
  validUntil: string;
  notes: string;
  subtotal: number;
  total: number;
  itemCount: number;
  items: QuotationItem[];
  createdAt: string;
  updatedAt: string;
};

export type QuotationListResponse = {
  quotations: Quotation[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type QuotationItemInput = {
  description: string;
  quantity: number | string;
  unitPrice: number | string;
};

export type QuotationInput = {
  clientId?: number;
  designId?: number;
  status?: string;
  validUntil?: string;
  notes?: string;
  items?: QuotationItemInput[];
};

export type OrderItem = {
  id: number;
  garmentName: string;
  garmentCategory: string;
  quantity: number;
  unitPrice: number;
  lineTotal: number;
  status: string;
  notes: string;
  designId: number;
  designTitle: string;
  designReference: string;
  measurementProfileId: number;
  profileName: string;
  measurementVersionId: number;
  measurementVersionNumber: number;
  measurementTakenAt: string;
  createdAt: string;
  updatedAt: string;
};

export type OrderEvent = {
  id: number;
  type: string;
  message: string;
  orderItemId: number;
  actorId: number;
  actorName: string;
  createdAt: string;
};

export type Order = {
  id: number;
  uuid: string;
  reference: string;
  clientId: number;
  clientName: string;
  quotationId: number;
  quotationReference: string;
  currency: string;
  status: string;
  dueDate: string;
  notes: string;
  total: number;
  garmentCount: number;
  items: OrderItem[];
  timeline: OrderEvent[];
  createdAt: string;
  updatedAt: string;
};

export type OrderListResponse = {
  orders: Order[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type OrderGarmentInput = {
  garmentName: string;
  quantity: number | string;
  unitPrice: number | string;
  designId?: number;
  measurementVersionId?: number;
  notes?: string;
};

export type OrderInput = {
  clientId?: number;
  quotationId?: number;
  status?: string;
  dueDate?: string;
  notes?: string;
  items?: OrderGarmentInput[];
};

export type OrderItemInput = {
  status?: string;
  notes?: string;
};

/* -------------------------------------------------------------------------- */
/* Phase 4: production and inventory                                          */
/* -------------------------------------------------------------------------- */

export type ProductionStage = {
  id: number;
  name: string;
  slug: string;
  sequence: number;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
};

export type ProductionStageInput = {
  name?: string;
  sequence?: number;
  isActive?: boolean;
};

export type ProductionTask = {
  id: number;
  jobId: number;
  stageId: number;
  stageName: string;
  stageSlug: string;
  status: string;
  assignedTo: number;
  assigneeName: string;
  dueDate: string;
  completedAt: string;
  notes: string;
};

export type ProductionJob = {
  id: number;
  orderItemId: number;
  orderId: number;
  orderReference: string;
  orderDueDate: string;
  clientName: string;
  garmentName: string;
  garmentCategory: string;
  garmentStatus: string;
  status: string;
  assignedTo: number;
  assigneeName: string;
  startDate: string;
  dueDate: string;
  notes: string;
  taskTotal: number;
  taskDone: number;
  tasks: ProductionTask[];
  createdAt: string;
  updatedAt: string;
};

export type ProductionJobListResponse = {
  jobs: ProductionJob[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type ProductionJobInput = {
  orderItemId?: number;
  status?: string;
  assignedTo?: number | string;
  startDate?: string;
  dueDate?: string;
  notes?: string;
};

export type ProductionTaskInput = {
  stageId?: number;
  status?: string;
  assignedTo?: number | string;
  dueDate?: string;
  notes?: string;
};

export type MaterialsResponse = {
  materials: Material[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type Material = {
  id: number;
  name: string;
  materialType: string;
  unit: string;
  quantityOnHand: number;
  reorderLevel: number;
  unitCost: number;
  notes: string;
  isActive: boolean;
  lowStock: boolean;
  movements: MaterialMovement[];
  createdAt: string;
  updatedAt: string;
};

export type MaterialMovement = {
  id: number;
  type: string;
  quantity: number;
  referenceType: string;
  referenceId: number;
  note: string;
  actorId: number;
  actorName: string;
  createdAt: string;
};

export type MaterialInput = {
  name?: string;
  materialType?: string;
  unit?: string;
  quantityOnHand?: number | string;
  reorderLevel?: number | string;
  unitCost?: number | string;
  notes?: string;
  isActive?: boolean;
};

export type MaterialMovementInput = {
  type: string;
  quantity: number | string;
  note?: string;
};

export type MaterialAllocation = {
  id: number;
  orderItemId: number;
  materialId: number;
  materialName: string;
  unit: string;
  quantity: number;
  source: string;
  notes: string;
  createdBy: number;
  createdByName: string;
  createdAt: string;
};

export type AllocationResponse = {
  garment: {
    id: number;
    garmentName: string;
    garmentCategory: string;
    orderId: number;
    orderReference: string;
    clientName: string;
  };
  allocations: MaterialAllocation[];
};

export type AllocationInput = {
  materialId: number;
  quantity: number | string;
  source: string;
  notes?: string;
};

export type Fitting = {
  id: number;
  orderItemId: number;
  orderId: number;
  orderReference: string;
  clientName: string;
  garmentName: string;
  assignedTo: number;
  assigneeName: string;
  scheduledAt: string;
  status: string;
  location: string;
  notes: string;
  outcome: string;
  createdAt: string;
  updatedAt: string;
};

export type FittingsResponse = {
  fittings: Fitting[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type FittingInput = {
  orderItemId?: number;
  scheduledAt?: string;
  assignedTo?: number | string;
  status?: string;
  location?: string;
  notes?: string;
  outcome?: string;
};

export type Alteration = {
  id: number;
  orderItemId: number;
  orderId: number;
  orderReference: string;
  clientName: string;
  garmentName: string;
  fittingId: number;
  fittingScheduledAt: string;
  description: string;
  status: string;
  dueDate: string;
  completedAt: string;
  createdAt: string;
  updatedAt: string;
};

export type AlterationsResponse = {
  alterations: Alteration[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type AlterationInput = {
  fittingId?: number;
  orderItemId?: number;
  description?: string;
  status?: string;
  dueDate?: string;
};

export type QualityCheck = {
  id: number;
  orderItemId: number;
  orderId: number;
  orderReference: string;
  clientName: string;
  garmentName: string;
  checkedBy: number;
  checkedByName: string;
  result: string;
  notes: string;
  checkedAt: string;
  createdAt: string;
};

export type QualityChecksResponse = {
  checks: QualityCheck[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type QualityCheckInput = {
  orderItemId: number;
  result: string;
  notes?: string;
  checkedAt?: string;
};

export type ProductionDashboard = {
  counts: {
    jobs: Record<string, number>;
    jobsTotal: number;
    overdueJobs: number;
    unassignedJobs: number;
    openTasks: number;
    unplannedGarments: number;
    failedChecks: number;
    needsRework: number;
  };
  unplannedGarments: Array<{
    id: number;
    garmentName: string;
    garmentStatus: string;
    orderId: number;
    orderReference: string;
    clientName: string;
  }>;
  upcomingFittings: Fitting[];
  recentFailures: QualityCheck[];
  lowStock: Material[];
};

/* -------------------------------------------------------------------------- */
/* Phase 5: finance                                                           */
/* -------------------------------------------------------------------------- */

export type InvoiceItem = {
  id: number;
  description: string;
  quantity: number;
  unitPrice: number;
  lineTotal: number;
};

export type Payment = {
  id: number;
  uuid: string;
  reference: string;
  invoiceId: number;
  invoiceReference: string;
  invoiceStatus: string;
  clientId: number;
  clientName: string;
  amount: number;
  method: string;
  provider: string;
  providerReference: string;
  status: string;
  paidAt: string;
  verifiedBy: number;
  verifiedByName: string;
  verifiedAt: string;
  notes: string;
  createdAt: string;
  updatedAt: string;
};

export type Invoice = {
  id: number;
  uuid: string;
  reference: string;
  clientId: number;
  clientName: string;
  clientReference: string;
  orderId: number;
  orderReference: string;
  currency: string;
  status: string;
  issueDate: string;
  dueDate: string;
  overdue: boolean;
  notes: string;
  subtotal: number;
  total: number;
  amountPaid: number;
  balance: number;
  itemCount: number;
  items: InvoiceItem[];
  payments: Payment[];
  createdAt: string;
  updatedAt: string;
};

export type InvoiceListResponse = {
  invoices: Invoice[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type InvoiceItemInput = {
  description: string;
  quantity: number | string;
  unitPrice: number | string;
};

export type InvoiceInput = {
  clientId?: number;
  orderId?: number;
  status?: string;
  issueDate?: string;
  dueDate?: string;
  notes?: string;
  items?: InvoiceItemInput[];
};

export type PaymentListResponse = {
  payments: Payment[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type PaymentInput = {
  invoiceId?: number;
  amount?: number | string;
  method?: string;
  provider?: string;
  providerReference?: string;
  paidAt?: string;
  notes?: string;
  idempotencyKey?: string;
  status?: string;
};

export type Expense = {
  id: number;
  uuid: string;
  reference: string;
  category: string;
  description: string;
  amount: number;
  currency: string;
  spentAt: string;
  status: string;
  notes: string;
  createdBy: number;
  createdByName: string;
  createdAt: string;
  updatedAt: string;
};

export type ExpenseListResponse = {
  expenses: Expense[];
  categories: string[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type ExpenseInput = {
  category?: string;
  description?: string;
  amount?: number | string;
  spentAt?: string;
  notes?: string;
  status?: string;
};

export type FinanceSummary = {
  currency: string;
  today: string;
  outstanding: number;
  invoicedOpen: number;
  overdue: number;
  drafts: number;
  paidInvoices: number;
  collectedThisMonth: number;
  expensesThisMonth: number;
  pendingPayments: { count: number; total: number };
  recentPayments: Payment[];
  overdueInvoices: Invoice[];
};

/* -------------------------------------------------------------------------- */
/* Phase 6: client portal                                                     */
/* -------------------------------------------------------------------------- */

export type PortalSession = {
  studio: {
    name: string;
    currency: string;
  };
  customer: {
    reference: string;
    name: string;
    email: string;
    phone: string;
  };
  access: {
    expiresAt: string;
  };
  summary: {
    openOrders: number;
    openInvoices: number;
    outstanding: number;
  };
};

export type PortalGarment = {
  id: number;
  garmentName: string;
  garmentCategory: string;
  quantity: number;
  status: string;
};

export type PortalOrder = {
  id: number;
  reference: string;
  status: string;
  dueDate: string;
  garmentCount: number;
  items: PortalGarment[];
  updatedAt: string;
};

export type PortalOrdersResponse = {
  orders: PortalOrder[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type PortalPayment = {
  reference: string;
  amount: number;
  method: string;
  providerReference: string;
  paidAt: string;
};

export type PortalInvoiceLine = {
  description: string;
  quantity: number;
  unitPrice: number;
  lineTotal: number;
};

export type PortalInvoice = {
  id: number;
  reference: string;
  orderReference: string;
  currency: string;
  status: string;
  issueDate: string;
  dueDate: string;
  overdue: boolean;
  notes: string;
  total: number;
  amountPaid: number;
  balance: number;
  items: PortalInvoiceLine[];
  payments: PortalPayment[];
  issuedAt: string;
};

export type PortalInvoicesResponse = {
  invoices: PortalInvoice[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type PortalAccess = {
  id: number;
  label: string;
  expiresAt: string;
  revokedAt: string;
  lastUsedAt: string;
  isActive: boolean;
  createdAt: string;
};

export type PortalAccessList = {
  access: PortalAccess[];
};

export type PortalAccessIssued = {
  access: PortalAccess;
  token: string;
  url: string;
};

export type PortalAccessInput = {
  label?: string;
  expiresInDays?: number;
};
