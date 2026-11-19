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
