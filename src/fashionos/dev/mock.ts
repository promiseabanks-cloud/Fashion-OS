/**
 * Development-only WordPress stand-in for `npm run dev`.
 *
 * It supplies the runtime config WordPress would inject and emulates the
 * FashionOS REST endpoints in memory so the studio can be reviewed without a
 * WordPress install. This file is excluded from the plugin package
 * (.distignore). It is not a test of the PHP implementation.
 */

import type {
  ActiveTenant,
  AuditEntry,
  Client,
  MeasurementField,
  MeasurementProfile,
  MeasurementProfileDetail,
  MeasurementTemplate,
  MeasurementVersion,
  MeResponse,
  PlatformTenant,
  Role,
  TeamMember,
  Tenant,
  WorkspaceSummary,
} from '../resources/js/types';

const config = {
  version: '0.3.0-dev',
  restUrl: `${window.location.origin}/mock/fashionos/v1`,
  nonce: 'development-nonce',
  homeUrl: '/',
  logoutUrl: '#',
  isDevelopment: true,
  user: { id: 1, displayName: 'Ada Obi' },
};

window.FashionOS = config;

const CURRENT_USER = 1;

const roles: Role[] = [
  { slug: 'owner', name: 'Owner', permissions: ['*'] },
  {
    slug: 'manager',
    name: 'Manager',
    permissions: [
      'workspace.view',
      'workspace.manage',
      'team.view',
      'team.manage',
      'audit.view',
      'clients.view',
      'clients.manage',
      'measurements.view',
      'measurements.manage',
    ],
  },
  {
    slug: 'designer',
    name: 'Designer',
    permissions: [
      'workspace.view',
      'team.view',
      'clients.view',
      'clients.manage',
      'measurements.view',
      'measurements.manage',
    ],
  },
  {
    slug: 'tailor',
    name: 'Tailor / production',
    permissions: ['workspace.view', 'clients.view', 'measurements.view'],
  },
  { slug: 'finance', name: 'Finance', permissions: ['workspace.view', 'clients.view'] },
];

const users = [
  { id: 1, name: 'Ada Obi', email: 'ada@atelier.test' },
  { id: 2, name: 'Tunde Bello', email: 'tunde@atelier.test' },
  { id: 3, name: 'Ngozi Eze', email: 'ngozi@tailoring.test' },
];

type Membership = {
  tenantId: number;
  userId: number;
  role: string;
  status: string;
  joinedAt: string;
};

const tenants: Tenant[] = [
  {
    id: 1,
    uuid: '0f3a4a4e-9d1f-4b3d-9c2a-1a2b3c4d5e6f',
    name: 'Adaeze Atelier',
    slug: 'adaeze-atelier',
    status: 'active',
    timezone: 'Africa/Lagos',
    currency: 'NGN',
    createdAt: '2026-11-01 10:00:00',
  },
  {
    id: 2,
    uuid: '7b2c8f10-2f4a-4d55-8c6b-9e0f1a2b3c4d',
    name: 'Lagos Bespoke House',
    slug: 'lagos-bespoke-house',
    status: 'active',
    timezone: 'Africa/Lagos',
    currency: 'NGN',
    createdAt: '2026-11-05 14:30:00',
  },
];

const memberships: Membership[] = [
  { tenantId: 1, userId: 1, role: 'owner', status: 'active', joinedAt: '2026-11-01 10:00:00' },
  { tenantId: 1, userId: 2, role: 'tailor', status: 'active', joinedAt: '2026-11-03 09:15:00' },
  { tenantId: 2, userId: 1, role: 'manager', status: 'active', joinedAt: '2026-11-05 14:30:00' },
  { tenantId: 2, userId: 3, role: 'owner', status: 'active', joinedAt: '2026-11-05 14:30:00' },
];

type StoredClient = Client & { tenantId: number };
type StoredTemplate = MeasurementTemplate & { tenantId: number };
type StoredProfile = MeasurementProfile & { tenantId: number };

const suitFields: MeasurementField[] = [
  { key: 'chest', label: 'Chest', hint: 'Around the fullest part', required: true },
  { key: 'waist', label: 'Waist', hint: '', required: true },
  { key: 'hips', label: 'Hips', hint: '', required: true },
  { key: 'shoulder', label: 'Shoulder width', hint: 'Bone to bone', required: true },
  { key: 'sleeve', label: 'Sleeve length', hint: '', required: true },
  { key: 'trouser_length', label: 'Trouser length', hint: 'Waist to hem', required: false },
];

const dressFields: MeasurementField[] = [
  { key: 'bust', label: 'Bust', hint: '', required: true },
  { key: 'waist', label: 'Waist', hint: '', required: true },
  { key: 'hips', label: 'Hips', hint: '', required: true },
  { key: 'shoulder_to_floor', label: 'Shoulder to floor', hint: 'With shoes', required: true },
];

const templates: StoredTemplate[] = [
  {
    id: 1,
    tenantId: 1,
    uuid: 'a1b2c3d4-0000-4000-8000-000000000001',
    name: 'Two-piece suit',
    garmentCategory: 'Suit',
    unit: 'cm',
    status: 'active',
    schemaVersion: 2,
    fields: suitFields,
    createdAt: '2026-11-02 09:00:00',
    updatedAt: '2026-11-06 16:20:00',
  },
  {
    id: 2,
    tenantId: 1,
    uuid: 'a1b2c3d4-0000-4000-8000-000000000002',
    name: 'Evening dress',
    garmentCategory: 'Dress',
    unit: 'cm',
    status: 'active',
    schemaVersion: 1,
    fields: dressFields,
    createdAt: '2026-11-04 11:30:00',
    updatedAt: '2026-11-04 11:30:00',
  },
  {
    id: 3,
    tenantId: 2,
    uuid: 'a1b2c3d4-0000-4000-8000-000000000003',
    name: 'Bespoke jacket',
    garmentCategory: 'Jacket',
    unit: 'in',
    status: 'active',
    schemaVersion: 1,
    fields: [
      { key: 'chest', label: 'Chest', hint: '', required: true },
      { key: 'shoulder', label: 'Shoulder width', hint: '', required: true },
    ],
    createdAt: '2026-11-06 10:00:00',
    updatedAt: '2026-11-06 10:00:00',
  },
];

const clients: StoredClient[] = [
  {
    id: 1,
    tenantId: 1,
    uuid: '0c1d2e3f-0000-4000-8000-000000000001',
    reference: 'C-0001',
    name: 'Amina Yusuf',
    email: 'amina@example.test',
    phone: '+234 803 555 0101',
    notes: 'Prefers morning appointments. Allergic to wool blends.',
    status: 'active',
    preferences: {
      preferredContact: 'whatsapp',
      styleNotes: 'Structured shoulders, muted colours, no contrast stitching.',
      fitNotes: 'Slight forward shoulder; add ease at the upper arm.',
    },
    profileCount: 0,
    createdAt: '2026-11-07 10:15:00',
    updatedAt: '2026-11-15 09:40:00',
  },
  {
    id: 2,
    tenantId: 1,
    uuid: '0c1d2e3f-0000-4000-8000-000000000002',
    reference: 'C-0002',
    name: 'Chidi Okonkwo',
    email: 'chidi@example.test',
    phone: '+234 806 555 0144',
    notes: '',
    status: 'active',
    preferences: {
      preferredContact: 'phone',
      styleNotes: 'Slim fit, Italian collar.',
      fitNotes: '',
    },
    profileCount: 0,
    createdAt: '2026-11-09 14:00:00',
    updatedAt: '2026-11-09 14:00:00',
  },
  {
    id: 3,
    tenantId: 1,
    uuid: '0c1d2e3f-0000-4000-8000-000000000003',
    reference: 'C-0003',
    name: 'Halima Bello',
    email: '',
    phone: '',
    notes: 'Walk-in referral from Amina.',
    status: 'archived',
    preferences: { preferredContact: '', styleNotes: '', fitNotes: '' },
    profileCount: 0,
    createdAt: '2026-10-20 12:00:00',
    updatedAt: '2026-11-01 08:00:00',
  },
  {
    id: 4,
    tenantId: 2,
    uuid: '0c1d2e3f-0000-4000-8000-000000000004',
    reference: 'C-0001',
    name: 'Bola Adeyemi',
    email: 'bola@example.test',
    phone: '+234 809 555 0199',
    notes: '',
    status: 'active',
    preferences: { preferredContact: 'email', styleNotes: '', fitNotes: '' },
    profileCount: 0,
    createdAt: '2026-11-08 09:30:00',
    updatedAt: '2026-11-08 09:30:00',
  },
];

type StoredValue = { key: string; label: string; value: number; unit: string };
type StoredVersionValues = StoredValue[];

const profiles: StoredProfile[] = [
  {
    id: 1,
    tenantId: 1,
    uuid: 'bb1c2d3e-0000-4000-8000-000000000001',
    clientId: 1,
    profileName: 'Wedding suit',
    garmentCategory: 'Suit',
    templateId: 1,
    templateName: 'Two-piece suit',
    status: 'active',
    versionCount: 0,
    latestVersion: null,
    createdAt: '2026-11-10 10:00:00',
    updatedAt: '2026-11-10 10:00:00',
  },
  {
    id: 2,
    tenantId: 1,
    uuid: 'bb1c2d3e-0000-4000-8000-000000000002',
    clientId: 1,
    profileName: 'Reception dress',
    garmentCategory: 'Dress',
    templateId: 2,
    templateName: 'Evening dress',
    status: 'active',
    versionCount: 0,
    latestVersion: null,
    createdAt: '2026-11-12 15:20:00',
    updatedAt: '2026-11-12 15:20:00',
  },
  {
    id: 3,
    tenantId: 1,
    uuid: 'bb1c2d3e-0000-4000-8000-000000000003',
    clientId: 2,
    profileName: 'Business suit',
    garmentCategory: 'Suit',
    templateId: 1,
    templateName: 'Two-piece suit',
    status: 'active',
    versionCount: 0,
    latestVersion: null,
    createdAt: '2026-11-11 11:00:00',
    updatedAt: '2026-11-11 11:00:00',
  },
];

const versions: Array<{
  id: number;
  tenantId: number;
  profileId: number;
  versionNumber: number;
  unit: string;
  takenAt: string;
  takenBy: number;
  notes: string;
  fitNotes: string;
  templateId: number;
  templateVersion: number;
  values: StoredVersionValues;
}> = [
  {
    id: 1,
    tenantId: 1,
    profileId: 1,
    versionNumber: 1,
    unit: 'cm',
    takenAt: '2026-11-10 10:20:00',
    takenBy: 1,
    notes: 'First fitting baseline.',
    fitNotes: 'Left shoulder sits 1cm lower.',
    templateId: 1,
    templateVersion: 1,
    values: [
      { key: 'chest', label: 'Chest', value: 96, unit: 'cm' },
      { key: 'waist', label: 'Waist', value: 82, unit: 'cm' },
      { key: 'hips', label: 'Hips', value: 99, unit: 'cm' },
      { key: 'shoulder', label: 'Shoulder width', value: 44.5, unit: 'cm' },
      { key: 'sleeve', label: 'Sleeve length', value: 62, unit: 'cm' },
    ],
  },
  {
    id: 2,
    tenantId: 1,
    profileId: 1,
    versionNumber: 2,
    unit: 'cm',
    takenAt: '2026-11-15 09:30:00',
    takenBy: 1,
    notes: 'Re-measured after two weeks of training.',
    fitNotes: 'Chest tighter by 3cm; keep the same ease.',
    templateId: 1,
    templateVersion: 2,
    values: [
      { key: 'chest', label: 'Chest', value: 93, unit: 'cm' },
      { key: 'waist', label: 'Waist', value: 79, unit: 'cm' },
      { key: 'hips', label: 'Hips', value: 98, unit: 'cm' },
      { key: 'shoulder', label: 'Shoulder width', value: 44.5, unit: 'cm' },
      { key: 'sleeve', label: 'Sleeve length', value: 62, unit: 'cm' },
      { key: 'trouser_length', label: 'Trouser length', value: 104, unit: 'cm' },
    ],
  },
  {
    id: 3,
    tenantId: 1,
    profileId: 3,
    versionNumber: 1,
    unit: 'cm',
    takenAt: '2026-11-11 11:30:00',
    takenBy: 1,
    notes: '',
    fitNotes: '',
    templateId: 1,
    templateVersion: 2,
    values: [
      { key: 'chest', label: 'Chest', value: 104, unit: 'cm' },
      { key: 'waist', label: 'Waist', value: 92, unit: 'cm' },
      { key: 'hips', label: 'Hips', value: 103, unit: 'cm' },
      { key: 'shoulder', label: 'Shoulder width', value: 47, unit: 'cm' },
      { key: 'sleeve', label: 'Sleeve length', value: 65, unit: 'cm' },
    ],
  },
];

const audit: Array<AuditEntry & { tenantId: number }> = [
  {
    id: 6,
    tenantId: 1,
    action: 'measurement.version.created',
    actorId: 1,
    actorName: 'Ada Obi',
    entityType: 'measurement_version',
    entityId: 2,
    metadata: { profileId: 1, versionNumber: 2, unit: 'cm' },
    createdAt: '2026-11-15 09:30:00',
  },
  {
    id: 5,
    tenantId: 1,
    action: 'client.created',
    actorId: 1,
    actorName: 'Ada Obi',
    entityType: 'client',
    entityId: 2,
    metadata: { reference: 'C-0002', name: 'Chidi Okonkwo' },
    createdAt: '2026-11-09 14:00:00',
  },
  {
    id: 4,
    tenantId: 1,
    action: 'measurement.profile.created',
    actorId: 1,
    actorName: 'Ada Obi',
    entityType: 'measurement_profile',
    entityId: 1,
    metadata: { clientId: 1, profileName: 'Wedding suit', templateId: 1 },
    createdAt: '2026-11-10 10:00:00',
  },
  {
    id: 3,
    tenantId: 1,
    action: 'membership.added',
    actorId: 1,
    actorName: 'Ada Obi',
    entityType: 'membership',
    entityId: 2,
    metadata: { email: 'tunde@atelier.test', role: 'tailor' },
    createdAt: '2026-11-03 09:15:00',
  },
  {
    id: 2,
    tenantId: 1,
    action: 'tenant.updated',
    actorId: 1,
    actorName: 'Ada Obi',
    entityType: 'tenant',
    entityId: 1,
    metadata: { currency: 'NGN' },
    createdAt: '2026-11-02 11:40:00',
  },
  {
    id: 1,
    tenantId: 1,
    action: 'tenant.created',
    actorId: 1,
    actorName: 'Ada Obi',
    entityType: 'tenant',
    entityId: 1,
    metadata: { name: 'Adaeze Atelier' },
    createdAt: '2026-11-01 10:00:00',
  },
];

let nextTenantId = 3;
let nextAuditId = 7;
let nextUserId = 4;
let nextClientId = 5;
let nextTemplateId = 4;
let nextProfileId = 4;
let nextVersionId = 4;

function nowStamp(): string {
  return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

function roleBySlug(slug: string): Role {
  return roles.find((role) => role.slug === slug) ?? roles[0];
}

function activeFor(tenantId: number, userId = CURRENT_USER): ActiveTenant | null {
  const tenant = tenants.find((item) => item.id === tenantId);
  const membership = memberships.find(
    (item) => item.tenantId === tenantId && item.userId === userId && item.status === 'active'
  );

  if (!tenant || !membership) {
    return null;
  }

  const role = roleBySlug(membership.role);

  return {
    tenant: { ...tenant },
    role: { slug: role.slug, name: role.name },
    permissions: role.permissions,
    isOwner: role.slug === 'owner',
    memberCount: memberships.filter(
      (item) => item.tenantId === tenantId && item.status === 'active'
    ).length,
  };
}

function workspacesFor(userId = CURRENT_USER): WorkspaceSummary[] {
  return memberships
    .filter((item) => item.userId === userId && item.status === 'active')
    .map((item) => {
      const tenant = tenants.find((entry) => entry.id === item.tenantId);
      const role = roleBySlug(item.role);

      return {
        id: item.tenantId,
        name: tenant?.name ?? 'Workspace',
        slug: tenant?.slug ?? String(item.tenantId),
        status: tenant?.status ?? 'active',
        role: { slug: role.slug, name: role.name },
      };
    });
}

function teamFor(tenantId: number): TeamMember[] {
  return memberships
    .filter((item) => item.tenantId === tenantId && item.status === 'active')
    .map((item) => {
      const user = users.find((entry) => entry.id === item.userId);
      const role = roleBySlug(item.role);

      return {
        userId: item.userId,
        name: user?.name ?? 'Unknown member',
        email: user?.email ?? '',
        role: role.slug,
        roleName: role.name,
        status: item.status,
        joinedAt: item.joinedAt,
        isOwner: role.slug === 'owner',
      };
    });
}

function record(
  tenantId: number,
  action: string,
  metadata: Record<string, unknown>,
  entityType = 'tenant',
  entityId = tenantId
): void {
  audit.unshift({
    id: nextAuditId++,
    tenantId,
    action,
    actorId: CURRENT_USER,
    actorName: 'Ada Obi',
    entityType,
    entityId,
    metadata,
    createdAt: nowStamp(),
  });
}

function resolveActive(selector: string | null): ActiveTenant | null {
  if (!selector) {
    const available = workspacesFor();

    return available.length === 1 ? activeFor(available[0].id) : null;
  }

  const tenant = /^\d+$/.test(selector)
    ? tenants.find((item) => item.id === Number(selector))
    : tenants.find((item) => item.slug === selector);

  return tenant ? activeFor(tenant.id) : null;
}

/* -------------------------------------------------------------------------- */
/* Customers and measurements                                                 */
/* -------------------------------------------------------------------------- */

function clientFor(tenantId: number, clientId: number): StoredClient | undefined {
  return clients.find((item) => item.tenantId === tenantId && item.id === clientId);
}

function profilesForClient(tenantId: number, clientId: number): StoredProfile[] {
  return profiles.filter((item) => item.tenantId === tenantId && item.clientId === clientId);
}

function versionsForProfile(tenantId: number, profileId: number) {
  return versions
    .filter((item) => item.tenantId === tenantId && item.profileId === profileId)
    .sort((left, right) => right.versionNumber - left.versionNumber);
}

function latestVersion(tenantId: number, profileId: number) {
  const history = versionsForProfile(tenantId, profileId);
  const latest = history[0];

  return latest
    ? {
        id: latest.id,
        versionNumber: latest.versionNumber,
        takenAt: latest.takenAt,
        takenByName: users.find((user) => user.id === latest.takenBy)?.name ?? '',
      }
    : null;
}

function presentProfile(profile: StoredProfile): MeasurementProfile {
  const history = versionsForProfile(profile.tenantId, profile.id);

  return {
    ...profile,
    versionCount: history.length,
    latestVersion: latestVersion(profile.tenantId, profile.id),
  };
}

function presentClient(record: StoredClient): Client {
  return {
    ...record,
    profileCount: profilesForClient(record.tenantId, record.id).length,
  };
}

function versionSummary(version: (typeof versions)[number]) {
  return {
    id: version.id,
    versionNumber: version.versionNumber,
    templateId: version.templateId,
    templateVersion: version.templateVersion,
    unit: version.unit,
    takenAt: version.takenAt,
    takenBy: {
      id: version.takenBy,
      name: users.find((user) => user.id === version.takenBy)?.name ?? '',
    },
    notes: version.notes,
    fitNotes: version.fitNotes,
    valueCount: version.values.length,
    createdAt: version.takenAt,
  };
}

function presentVersion(version: (typeof versions)[number], tenantId: number): MeasurementVersion {
  const template = templates.find(
    (item) => item.tenantId === tenantId && item.id === version.templateId
  );
  const profile = profiles.find(
    (item) => item.tenantId === tenantId && item.id === version.profileId
  );

  return {
    ...versionSummary(version),
    profileId: version.profileId,
    profileName: profile?.profileName ?? '',
    clientId: profile?.clientId ?? 0,
    template: {
      id: template?.id ?? 0,
      name: template?.name ?? '',
      garmentCategory: template?.garmentCategory ?? '',
      unit: version.unit,
      schemaVersion: version.templateVersion,
      fields: template?.fields ?? [],
    },
    values: version.values,
  };
}

function presentTemplate(template: StoredTemplate): MeasurementTemplate {
  return { ...template };
}

const json = (body: unknown, status = 200): Response =>
  new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });

const ok = (data: unknown, status = 200): Response =>
  json({ success: true, data, meta: {} }, status);

const fail = (code: string, message: string, status: number): Response =>
  json({ success: false, error: { code, message } }, status);

const sleep = (ms: number): Promise<void> => new Promise((resolve) => setTimeout(resolve, ms));

const passthroughFetch = globalThis.fetch.bind(globalThis);

function workspaceRequired(): Response {
  return fail('workspace_required', 'Choose which workspace you want to work in.', 400);
}

async function handle(url: URL, request: Request): Promise<Response> {
  const path = url.pathname.replace(/^\/mock\/fashionos\/v1/, '');
  const method = request.method.toUpperCase();
  const active = resolveActive(request.headers.get('X-FashionOS-Workspace'));
  const body =
    method === 'GET' || method === 'DELETE' ? null : await request.json().catch(() => null);

  await sleep(220);

  if (path === '/system/health' && method === 'GET') {
    return ok({
      pluginVersion: '0.3.0',
      database: { latestVersion: '0002', applied: 2, pending: 0 },
      build: { built: true, development: true },
      server: { php: '8.3.0', wordpress: '6.8' },
    });
  }

  if (path === '/auth/me' && method === 'GET') {
    const workspaces = workspacesFor();
    const payload: MeResponse = {
      user: { id: CURRENT_USER, displayName: 'Ada Obi', canManagePlatform: true },
      workspaces,
      activeTenant: active,
      workspaceError: active ? null : workspaces.length > 1 ? 'workspace_required' : null,
    };

    return ok(payload);
  }

  if (path === '/tenants' && method === 'GET') {
    return ok({ workspaces: workspacesFor() });
  }

  if (path === '/tenants' && method === 'POST') {
    const name = String((body as { name?: string } | null)?.name ?? '').trim();

    if (!name) {
      return fail('workspace_name_required', 'Give your workspace a name.', 422);
    }

    const tenant: Tenant = {
      id: nextTenantId++,
      uuid: crypto.randomUUID(),
      name,
      slug: name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, ''),
      status: 'active',
      timezone: String((body as { timezone?: string } | null)?.timezone ?? 'Africa/Lagos'),
      currency: String((body as { currency?: string } | null)?.currency ?? 'NGN'),
      createdAt: nowStamp(),
    };

    tenants.push(tenant);
    memberships.push({
      tenantId: tenant.id,
      userId: CURRENT_USER,
      role: 'owner',
      status: 'active',
      joinedAt: tenant.createdAt,
    });
    record(tenant.id, 'tenant.created', { name });

    return ok(activeFor(tenant.id), 201);
  }

  if (path === '/tenants/current' && method === 'GET') {
    const current = resolveActive(request.headers.get('X-FashionOS-Workspace'));

    return current ? ok(current) : workspaceRequired();
  }

  if (path === '/tenants/current' && method === 'PATCH') {
    if (!active) {
      return workspaceRequired();
    }

    const tenant = tenants.find((item) => item.id === active.tenant.id);

    if (!tenant) {
      return fail('workspace_not_found', 'That workspace could not be found.', 404);
    }

    const input = (body ?? {}) as { name?: string; timezone?: string; currency?: string };
    const changes: Record<string, string> = {};

    if (typeof input.name === 'string' && input.name.trim()) {
      tenant.name = input.name.trim();
      changes.name = tenant.name;
    }

    if (typeof input.timezone === 'string') {
      tenant.timezone = input.timezone;
      changes.timezone = tenant.timezone;
    }

    if (typeof input.currency === 'string') {
      tenant.currency = input.currency;
      changes.currency = tenant.currency;
    }

    if (Object.keys(changes).length > 0) {
      record(tenant.id, 'tenant.updated', changes);
    }

    return ok(activeFor(tenant.id));
  }

  if (path === '/team' && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    return ok({
      members: teamFor(active.tenant.id),
      roles: roles
        .filter((role) => role.slug !== 'owner')
        .map((role) => ({ slug: role.slug, name: role.name })),
    });
  }

  if (path === '/team' && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const input = (body ?? {}) as { email?: string; role?: string };
    const email = String(input.email ?? '')
      .trim()
      .toLowerCase();
    const roleSlug = String(input.role ?? '');

    if (!email.includes('@')) {
      return fail('member_email_invalid', 'Enter a valid email address.', 422);
    }

    if (!roles.some((role) => role.slug === roleSlug) || roleSlug === 'owner') {
      return fail('member_role_invalid', 'Choose a valid team role.', 422);
    }

    const known = users.find((user) => user.email === email);
    const user = known ?? { id: nextUserId++, name: email.split('@')[0], email };

    if (!known) {
      users.push(user);
    }

    if (memberships.some((item) => item.tenantId === active.tenant.id && item.userId === user.id)) {
      return fail('member_already_exists', 'That person is already on this team.', 409);
    }

    memberships.push({
      tenantId: active.tenant.id,
      userId: user.id,
      role: roleSlug,
      status: 'active',
      joinedAt: nowStamp(),
    });
    record(active.tenant.id, 'membership.added', { email, role: roleSlug }, 'membership', user.id);

    return ok({ added: true }, 201);
  }

  const memberMatch = /^\/team\/(\d+)$/.exec(path);

  if (memberMatch) {
    if (!active) {
      return workspaceRequired();
    }

    const userId = Number(memberMatch[1]);
    const membership = memberships.find(
      (item) => item.tenantId === active.tenant.id && item.userId === userId
    );

    if (!membership) {
      return fail('member_not_found', 'That person is not on this team.', 404);
    }

    if (membership.role === 'owner') {
      return fail('owner_role_protected', 'The workspace owner cannot be changed.', 422);
    }

    if (method === 'PATCH') {
      const roleSlug = String((body as { role?: string } | null)?.role ?? '');

      if (!roles.some((role) => role.slug === roleSlug) || roleSlug === 'owner') {
        return fail('member_role_invalid', 'Choose a valid team role.', 422);
      }

      const previous = membership.role;
      membership.role = roleSlug;
      record(
        active.tenant.id,
        'membership.role_changed',
        { from: previous, to: roleSlug },
        'membership',
        userId
      );

      return ok({ updated: true });
    }

    if (method === 'DELETE') {
      memberships.splice(memberships.indexOf(membership), 1);
      record(
        active.tenant.id,
        'membership.removed',
        { role: membership.role },
        'membership',
        userId
      );

      return ok({ removed: true });
    }
  }

  if (path === '/clients' && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    const search = (url.searchParams.get('search') ?? '').trim().toLowerCase();
    const status = url.searchParams.get('status') ?? 'active';
    const page = Math.max(1, Number(url.searchParams.get('page') ?? 1));
    const perPage = Math.min(100, Math.max(1, Number(url.searchParams.get('per_page') ?? 25)));

    const matches = clients
      .filter((item) => item.tenantId === active.tenant.id)
      .filter((item) => (status === 'all' ? true : item.status === status))
      .filter((item) =>
        search === ''
          ? true
          : [item.name, item.email, item.phone, item.reference]
              .join(' ')
              .toLowerCase()
              .includes(search)
      )
      .sort((left, right) => right.updatedAt.localeCompare(left.updatedAt));

    const start = (page - 1) * perPage;
    const pageItems = matches.slice(start, start + perPage);

    return ok({
      clients: pageItems.map(presentClient),
      page,
      perPage,
      total: matches.length,
      totalPages: matches.length === 0 ? 0 : Math.ceil(matches.length / perPage),
    });
  }

  if (path === '/clients' && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const input = (body ?? {}) as {
      name?: string;
      email?: string;
      phone?: string;
      notes?: string;
      preferences?: Partial<Client['preferences']>;
    };
    const name = String(input.name ?? '').trim();

    if (!name) {
      return fail('client_name_required', 'Give the customer a name.', 422);
    }

    const email = String(input.email ?? '')
      .trim()
      .toLowerCase();

    if (email) {
      const duplicate = clients.find(
        (item) =>
          item.tenantId === active.tenant.id && item.status === 'active' && item.email === email
      );

      if (duplicate) {
        return fail(
          'client_duplicate',
          `${duplicate.name} (${duplicate.reference}) already uses that email address.`,
          409
        );
      }
    }

    const id = nextClientId++;
    const created: StoredClient = {
      id,
      tenantId: active.tenant.id,
      uuid: crypto.randomUUID(),
      reference: `C-${String(id).padStart(4, '0')}`,
      name,
      email,
      phone: String(input.phone ?? '').trim(),
      notes: String(input.notes ?? '').trim(),
      status: 'active',
      preferences: {
        preferredContact: String(input.preferences?.preferredContact ?? ''),
        styleNotes: String(input.preferences?.styleNotes ?? ''),
        fitNotes: String(input.preferences?.fitNotes ?? ''),
      },
      profileCount: 0,
      createdAt: nowStamp(),
      updatedAt: nowStamp(),
    };

    clients.push(created);
    record(
      active.tenant.id,
      'client.created',
      { reference: created.reference, name: created.name },
      'client',
      created.id
    );

    return ok(presentClient(created), 201);
  }

  const clientMatch = /^\/clients\/(\d+)$/.exec(path);

  if (clientMatch) {
    if (!active) {
      return workspaceRequired();
    }

    const clientId = Number(clientMatch[1]);
    const existing = clientFor(active.tenant.id, clientId);

    if (!existing) {
      return fail('client_not_found', 'That customer could not be found.', 404);
    }

    if (method === 'GET') {
      return ok(presentClient(existing));
    }

    if (method === 'PATCH') {
      const input = (body ?? {}) as {
        name?: string;
        email?: string;
        phone?: string;
        notes?: string;
        status?: string;
        preferences?: Partial<Client['preferences']>;
      };
      const changed: string[] = [];

      if (typeof input.name === 'string' && input.name.trim()) {
        existing.name = input.name.trim();
        changed.push('name');
      }

      if (typeof input.email === 'string') {
        const email = input.email.trim().toLowerCase();

        if (
          email &&
          clients.some(
            (item) =>
              item.tenantId === active.tenant.id &&
              item.status === 'active' &&
              item.id !== clientId &&
              item.email === email
          )
        ) {
          return fail('client_duplicate', 'Another customer already uses that email address.', 409);
        }

        existing.email = email;
        changed.push('email');
      }

      if (typeof input.phone === 'string') {
        existing.phone = input.phone.trim();
        changed.push('phone');
      }

      if (typeof input.notes === 'string') {
        existing.notes = input.notes.trim();
        changed.push('notes');
      }

      if (input.status === 'active' || input.status === 'archived') {
        existing.status = input.status;
        changed.push('status');
      }

      if (input.preferences) {
        existing.preferences = {
          preferredContact: String(input.preferences.preferredContact ?? ''),
          styleNotes: String(input.preferences.styleNotes ?? ''),
          fitNotes: String(input.preferences.fitNotes ?? ''),
        };
        changed.push('preferences');
      }

      existing.updatedAt = nowStamp();
      record(active.tenant.id, 'client.updated', { fields: changed }, 'client', clientId);

      return ok(presentClient(existing));
    }
  }

  const clientMeasurementsMatch = /^\/clients\/(\d+)\/measurements$/.exec(path);

  if (clientMeasurementsMatch) {
    if (!active) {
      return workspaceRequired();
    }

    const clientId = Number(clientMeasurementsMatch[1]);

    if (!clientFor(active.tenant.id, clientId)) {
      return fail('client_not_found', 'That customer could not be found.', 404);
    }

    if (method === 'GET') {
      return ok({
        clientId,
        profiles: profilesForClient(active.tenant.id, clientId).map(presentProfile),
      });
    }

    if (method === 'POST') {
      const input = (body ?? {}) as { profileName?: string; templateId?: number };
      const profileName = String(input.profileName ?? '').trim();

      if (!profileName) {
        return fail('measurement_profile_name_required', 'Give the profile a name.', 422);
      }

      const template = templates.find(
        (item) => item.tenantId === active.tenant.id && item.id === Number(input.templateId)
      );

      if (!template) {
        return fail(
          'measurement_template_not_found',
          'That measurement template could not be found.',
          404
        );
      }

      if (template.status !== 'active') {
        return fail(
          'measurement_template_archived',
          'That template is archived. Choose an active one.',
          422
        );
      }

      const profile: StoredProfile = {
        id: nextProfileId++,
        tenantId: active.tenant.id,
        uuid: crypto.randomUUID(),
        clientId,
        profileName,
        garmentCategory: template.garmentCategory,
        templateId: template.id,
        templateName: template.name,
        status: 'active',
        versionCount: 0,
        latestVersion: null,
        createdAt: nowStamp(),
        updatedAt: nowStamp(),
      };

      profiles.push(profile);
      record(
        active.tenant.id,
        'measurement.profile.created',
        { clientId, profileName, templateId: template.id },
        'measurement_profile',
        profile.id
      );

      const detail: MeasurementProfileDetail = { ...presentProfile(profile), history: [] };

      return ok(detail, 201);
    }
  }

  if (path === '/measurement-templates' && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    return ok({
      templates: templates
        .filter((item) => item.tenantId === active.tenant.id)
        .map(presentTemplate),
    });
  }

  if (path === '/measurement-templates' && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const input = (body ?? {}) as {
      name?: string;
      garmentCategory?: string;
      unit?: string;
      fields?: Array<Partial<MeasurementField>>;
    };
    const name = String(input.name ?? '').trim();
    const category = String(input.garmentCategory ?? '').trim();
    const fields = Array.isArray(input.fields) ? input.fields : [];

    if (!name) {
      return fail('measurement_template_name_required', 'Give the template a name.', 422);
    }

    if (!category) {
      return fail('measurement_template_category_required', 'Name the garment category.', 422);
    }

    if (fields.length === 0) {
      return fail(
        'measurement_template_fields_required',
        'Add at least one measurement field.',
        422
      );
    }

    const unit = input.unit === 'in' ? 'in' : 'cm';
    const normalised: MeasurementField[] = fields.map((field, index) => ({
      key: String(field.key ?? `field_${index + 1}`),
      label: String(field.label ?? `Field ${index + 1}`),
      hint: String(field.hint ?? ''),
      required: Boolean(field.required),
    }));

    const template: StoredTemplate = {
      id: nextTemplateId++,
      tenantId: active.tenant.id,
      uuid: crypto.randomUUID(),
      name,
      garmentCategory: category,
      unit,
      status: 'active',
      schemaVersion: 1,
      fields: normalised,
      createdAt: nowStamp(),
      updatedAt: nowStamp(),
    };

    templates.push(template);
    record(
      active.tenant.id,
      'measurement.template.created',
      { name, garmentCategory: category, fields: normalised.length },
      'measurement_template',
      template.id
    );

    return ok(presentTemplate(template), 201);
  }

  const templateMatch = /^\/measurement-templates\/(\d+)$/.exec(path);

  if (templateMatch && method === 'PATCH') {
    if (!active) {
      return workspaceRequired();
    }

    const template = templates.find(
      (item) => item.tenantId === active.tenant.id && item.id === Number(templateMatch[1])
    );

    if (!template) {
      return fail(
        'measurement_template_not_found',
        'That measurement template could not be found.',
        404
      );
    }

    const input = (body ?? {}) as {
      name?: string;
      garmentCategory?: string;
      unit?: string;
      status?: string;
      fields?: Array<Partial<MeasurementField>>;
    };

    if (typeof input.name === 'string' && input.name.trim()) {
      template.name = input.name.trim();
    }

    if (typeof input.garmentCategory === 'string' && input.garmentCategory.trim()) {
      template.garmentCategory = input.garmentCategory.trim();
    }

    if (input.unit === 'cm' || input.unit === 'in') {
      template.unit = input.unit;
    }

    if (input.status === 'active' || input.status === 'archived') {
      template.status = input.status;
    }

    if (Array.isArray(input.fields) && input.fields.length > 0) {
      template.fields = input.fields.map((field, index) => ({
        key: String(field.key ?? `field_${index + 1}`),
        label: String(field.label ?? `Field ${index + 1}`),
        hint: String(field.hint ?? ''),
        required: Boolean(field.required),
      }));
      template.schemaVersion += 1;
    }

    template.updatedAt = nowStamp();
    record(
      active.tenant.id,
      'measurement.template.updated',
      { fields: Array.isArray(input.fields) ? 'changed' : 'unchanged' },
      'measurement_template',
      template.id
    );

    return ok(presentTemplate(template));
  }

  const profileMatch = /^\/measurement-profiles\/(\d+)$/.exec(path);

  if (profileMatch && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    const profile = profiles.find(
      (item) => item.tenantId === active.tenant.id && item.id === Number(profileMatch[1])
    );

    if (!profile) {
      return fail(
        'measurement_profile_not_found',
        'That measurement profile could not be found.',
        404
      );
    }

    const detail: MeasurementProfileDetail = {
      ...presentProfile(profile),
      history: versionsForProfile(active.tenant.id, profile.id).map(versionSummary),
    };

    return ok(detail);
  }

  const profileVersionsMatch = /^\/measurement-profiles\/(\d+)\/versions$/.exec(path);

  if (profileVersionsMatch && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const profile = profiles.find(
      (item) => item.tenantId === active.tenant.id && item.id === Number(profileVersionsMatch[1])
    );

    if (!profile) {
      return fail(
        'measurement_profile_not_found',
        'That measurement profile could not be found.',
        404
      );
    }

    const template = templates.find(
      (item) => item.tenantId === active.tenant.id && item.id === profile.templateId
    );

    if (!template) {
      return fail(
        'measurement_template_not_found',
        'The template for this profile could not be found.',
        404
      );
    }

    if (template.status !== 'active') {
      return fail('measurement_template_archived', 'That template is archived.', 422);
    }

    const input = (body ?? {}) as {
      values?: Record<string, string | number>;
      notes?: string;
      fitNotes?: string;
    };
    const submitted = input.values ?? {};
    const values: StoredValue[] = [];

    for (const field of template.fields) {
      const raw = submitted[field.key];
      const text = raw === undefined || raw === null ? '' : String(raw).trim();

      if (text === '') {
        if (field.required) {
          return fail('measurement_value_required', `Enter a value for ${field.label}.`, 422);
        }

        continue;
      }

      if (!/^\d{1,4}(\.\d{1,2})?$/.test(text)) {
        return fail(
          'measurement_value_invalid',
          `${field.label} must be a number between 0 and 9999.99.`,
          422
        );
      }

      values.push({
        key: field.key,
        label: field.label,
        value: Number(text),
        unit: template.unit,
      });
    }

    if (values.length === 0) {
      return fail('measurement_values_required', 'Enter at least one measurement.', 422);
    }

    const history = versionsForProfile(active.tenant.id, profile.id);
    const version = {
      id: nextVersionId++,
      tenantId: active.tenant.id,
      profileId: profile.id,
      versionNumber: history.length + 1,
      unit: template.unit,
      takenAt: nowStamp(),
      takenBy: CURRENT_USER,
      notes: String(input.notes ?? '').trim(),
      fitNotes: String(input.fitNotes ?? '').trim(),
      templateId: template.id,
      templateVersion: template.schemaVersion,
      values,
    };

    versions.push(version);
    record(
      active.tenant.id,
      'measurement.version.created',
      { profileId: profile.id, versionNumber: version.versionNumber, unit: version.unit },
      'measurement_version',
      version.id
    );

    return ok(presentVersion(version, active.tenant.id), 201);
  }

  const versionMatch = /^\/measurement-versions\/(\d+)$/.exec(path);

  if (versionMatch && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    const version = versions.find(
      (item) => item.tenantId === active.tenant.id && item.id === Number(versionMatch[1])
    );

    if (!version) {
      return fail(
        'measurement_version_not_found',
        'That measurement version could not be found.',
        404
      );
    }

    return ok(presentVersion(version, active.tenant.id));
  }

  if (path === '/audit' && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    return ok({
      entries: audit.filter((entry) => entry.tenantId === active.tenant.id),
    });
  }

  if (path === '/platform/tenants' && method === 'GET') {
    const list: PlatformTenant[] = tenants.map((tenant) => ({
      id: tenant.id,
      name: tenant.name,
      slug: tenant.slug,
      status: tenant.status,
      memberCount: memberships.filter(
        (item) => item.tenantId === tenant.id && item.status === 'active'
      ).length,
      createdAt: tenant.createdAt,
    }));

    return ok({ tenants: list });
  }

  return fail('not_found', 'That endpoint does not exist in the development mock.', 404);
}

globalThis.fetch = (async (input: RequestInfo | URL, init?: RequestInit) => {
  const request = input instanceof Request ? input : new Request(input, init);
  const url = new URL(request.url);

  if (!url.pathname.startsWith('/mock/fashionos/v1')) {
    return passthroughFetch(input, init);
  }

  return handle(url, request);
}) as typeof fetch;
