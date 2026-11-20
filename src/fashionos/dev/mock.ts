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
  Alteration,
  AuditEntry,
  Client,
  Design,
  MeasurementField,
  MeasurementProfile,
  MeasurementProfileDetail,
  MeasurementTemplate,
  MeasurementVersion,
  Material,
  MaterialAllocation,
  MaterialMovement,
  MeResponse,
  Order,
  OrderEvent,
  OrderItem,
  Fitting,
  PlatformTenant,
  ProductionDashboard,
  ProductionJob,
  ProductionStage,
  ProductionTask,
  QualityCheck,
  Quotation,
  QuotationItem,
  Role,
  TeamMember,
  Tenant,
  WorkspaceSummary,
} from '../resources/js/types';

const config = {
  version: '0.5.0-dev',
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

type StoredDesign = Design & { tenantId: number };
type StoredQuotation = Omit<Quotation, 'items' | 'itemCount'> & { tenantId: number };
type StoredQuotationItem = QuotationItem & { tenantId: number; quotationId: number };
type StoredOrder = Omit<Order, 'items' | 'timeline' | 'garmentCount'> & { tenantId: number };
type StoredOrderItem = OrderItem & { tenantId: number; orderId: number; quotationItemId: number };
type StoredOrderEvent = OrderEvent & { tenantId: number; orderId: number };

const designs: StoredDesign[] = [
  {
    id: 1,
    tenantId: 1,
    uuid: 'dd1c2d3e-0000-4000-8000-000000000001',
    reference: 'D-0001',
    clientId: 1,
    clientName: 'Amina Yusuf',
    measurementProfileId: 1,
    profileName: 'Wedding suit',
    measurementVersionId: 2,
    measurementVersionNumber: 2,
    measurementTakenAt: '2026-11-15 09:30:00',
    title: 'Ivory wedding suit',
    garmentCategory: 'Suit',
    brief: 'Ivory wool, peak lapels, no contrast stitching. Keep the shoulder unstructured.',
    status: 'approved',
    createdAt: '2026-11-16 10:00:00',
    updatedAt: '2026-11-16 16:45:00',
  },
  {
    id: 2,
    tenantId: 1,
    uuid: 'dd1c2d3e-0000-4000-8000-000000000002',
    reference: 'D-0002',
    clientId: 2,
    clientName: 'Chidi Okonkwo',
    measurementProfileId: 3,
    profileName: 'Business suit',
    measurementVersionId: 3,
    measurementVersionNumber: 1,
    measurementTakenAt: '2026-11-11 11:30:00',
    title: 'Charcoal business suit',
    garmentCategory: 'Suit',
    brief: '',
    status: 'draft',
    createdAt: '2026-11-17 09:15:00',
    updatedAt: '2026-11-17 09:15:00',
  },
  {
    id: 3,
    tenantId: 2,
    uuid: 'dd1c2d3e-0000-4000-8000-000000000003',
    reference: 'D-0001',
    clientId: 4,
    clientName: 'Bola Adeyemi',
    measurementProfileId: 0,
    profileName: '',
    measurementVersionId: 0,
    measurementVersionNumber: 0,
    measurementTakenAt: '',
    title: 'Reception gown',
    garmentCategory: 'Dress',
    brief: 'Beaded bodice, floor-length skirt.',
    status: 'draft',
    createdAt: '2026-11-12 13:00:00',
    updatedAt: '2026-11-12 13:00:00',
  },
];

const quotationItems: StoredQuotationItem[] = [
  {
    id: 1,
    tenantId: 1,
    quotationId: 1,
    description: 'Wedding jacket',
    quantity: 1,
    unitPrice: 380000,
    lineTotal: 380000,
  },
  {
    id: 2,
    tenantId: 1,
    quotationId: 1,
    description: 'Wedding trousers',
    quantity: 1,
    unitPrice: 100000,
    lineTotal: 100000,
  },
  {
    id: 3,
    tenantId: 1,
    quotationId: 1,
    description: 'Silk shirt',
    quantity: 1,
    unitPrice: 35000,
    lineTotal: 35000,
  },
  {
    id: 4,
    tenantId: 1,
    quotationId: 2,
    description: 'Charcoal two-piece suit',
    quantity: 1,
    unitPrice: 310000,
    lineTotal: 310000,
  },
];

const quotations: StoredQuotation[] = [
  {
    id: 1,
    tenantId: 1,
    uuid: 'ee1c2d3e-0000-4000-8000-000000000001',
    reference: 'Q-0001',
    clientId: 1,
    clientName: 'Amina Yusuf',
    designId: 1,
    designTitle: 'Ivory wedding suit',
    currency: 'NGN',
    status: 'sent',
    validUntil: '2026-12-15',
    notes: 'Prices hold for 30 days. A 50% deposit confirms the order.',
    subtotal: 515000,
    total: 515000,
    createdAt: '2026-11-16 11:00:00',
    updatedAt: '2026-11-16 11:30:00',
  },
  {
    id: 2,
    tenantId: 1,
    uuid: 'ee1c2d3e-0000-4000-8000-000000000002',
    reference: 'Q-0002',
    clientId: 2,
    clientName: 'Chidi Okonkwo',
    designId: 2,
    designTitle: 'Charcoal business suit',
    currency: 'NGN',
    status: 'draft',
    validUntil: '',
    notes: '',
    subtotal: 310000,
    total: 310000,
    createdAt: '2026-11-17 10:00:00',
    updatedAt: '2026-11-17 10:00:00',
  },
];

const orders: StoredOrder[] = [
  {
    id: 1,
    tenantId: 1,
    uuid: 'ff1c2d3e-0000-4000-8000-000000000001',
    reference: 'O-0001',
    clientId: 1,
    clientName: 'Amina Yusuf',
    quotationId: 1,
    quotationReference: 'Q-0001',
    currency: 'NGN',
    status: 'in_progress',
    dueDate: '2026-12-20',
    notes: 'Needed before the 20th; the shirt can follow a week later.',
    total: 515000,
    createdAt: '2026-11-17 09:00:00',
    updatedAt: '2026-11-18 14:20:00',
  },
];

const orderItems: StoredOrderItem[] = [
  {
    id: 1,
    tenantId: 1,
    orderId: 1,
    quotationItemId: 1,
    garmentName: 'Wedding jacket',
    garmentCategory: 'Suit',
    quantity: 1,
    unitPrice: 380000,
    lineTotal: 380000,
    status: 'fitting',
    notes: 'Sleeve pitch adjusted at the first fitting.',
    designId: 1,
    designTitle: 'Ivory wedding suit',
    designReference: 'D-0001',
    measurementProfileId: 1,
    profileName: 'Wedding suit',
    measurementVersionId: 2,
    measurementVersionNumber: 2,
    measurementTakenAt: '2026-11-15 09:30:00',
    createdAt: '2026-11-17 09:00:00',
    updatedAt: '2026-11-18 14:20:00',
  },
  {
    id: 2,
    tenantId: 1,
    orderId: 1,
    quotationItemId: 2,
    garmentName: 'Wedding trousers',
    garmentCategory: 'Suit',
    quantity: 1,
    unitPrice: 100000,
    lineTotal: 100000,
    status: 'cutting',
    notes: '',
    designId: 1,
    designTitle: 'Ivory wedding suit',
    designReference: 'D-0001',
    measurementProfileId: 1,
    profileName: 'Wedding suit',
    measurementVersionId: 2,
    measurementVersionNumber: 2,
    measurementTakenAt: '2026-11-15 09:30:00',
    createdAt: '2026-11-17 09:00:00',
    updatedAt: '2026-11-17 09:00:00',
  },
  {
    id: 3,
    tenantId: 1,
    orderId: 1,
    quotationItemId: 3,
    garmentName: 'Silk shirt',
    garmentCategory: 'Shirt',
    quantity: 1,
    unitPrice: 35000,
    lineTotal: 35000,
    status: 'pending',
    notes: '',
    designId: 0,
    designTitle: '',
    designReference: '',
    measurementProfileId: 1,
    profileName: 'Wedding suit',
    measurementVersionId: 2,
    measurementVersionNumber: 2,
    measurementTakenAt: '2026-11-15 09:30:00',
    createdAt: '2026-11-17 09:00:00',
    updatedAt: '2026-11-17 09:00:00',
  },
];

const orderEvents: StoredOrderEvent[] = [
  {
    id: 1,
    tenantId: 1,
    orderId: 1,
    type: 'created',
    message: 'Order created with 3 garments.',
    orderItemId: 0,
    actorId: 1,
    actorName: 'Ada Obi',
    createdAt: '2026-11-17 09:00:00',
  },
  {
    id: 2,
    tenantId: 1,
    orderId: 1,
    type: 'item_added',
    message: 'Wedding jacket added with measurements v2.',
    orderItemId: 1,
    actorId: 1,
    actorName: 'Ada Obi',
    createdAt: '2026-11-17 09:00:00',
  },
  {
    id: 3,
    tenantId: 1,
    orderId: 1,
    type: 'item_added',
    message: 'Wedding trousers added with measurements v2.',
    orderItemId: 2,
    actorId: 1,
    actorName: 'Ada Obi',
    createdAt: '2026-11-17 09:00:00',
  },
  {
    id: 4,
    tenantId: 1,
    orderId: 1,
    type: 'item_added',
    message: 'Silk shirt added with measurements v2.',
    orderItemId: 3,
    actorId: 1,
    actorName: 'Ada Obi',
    createdAt: '2026-11-17 09:00:00',
  },
  {
    id: 5,
    tenantId: 1,
    orderId: 1,
    type: 'status',
    message: 'Order moved from Draft to Confirmed.',
    orderItemId: 0,
    actorId: 1,
    actorName: 'Ada Obi',
    createdAt: '2026-11-17 12:00:00',
  },
  {
    id: 6,
    tenantId: 1,
    orderId: 1,
    type: 'item_status',
    message: 'Wedding jacket moved from Pending to Fitting.',
    orderItemId: 1,
    actorId: 1,
    actorName: 'Ada Obi',
    createdAt: '2026-11-18 14:20:00',
  },
];

/* Production and inventory ------------------------------------------------- */

type StoredStage = ProductionStage & { tenantId: number };
type StoredJob = {
  id: number;
  tenantId: number;
  orderItemId: number;
  assignedTo: number;
  status: string;
  startDate: string;
  dueDate: string;
  notes: string;
  createdAt: string;
  updatedAt: string;
};
type StoredTask = {
  id: number;
  tenantId: number;
  jobId: number;
  stageId: number;
  assignedTo: number;
  status: string;
  dueDate: string;
  completedAt: string;
  notes: string;
};
type StoredMaterial = {
  id: number;
  tenantId: number;
  name: string;
  materialType: string;
  unit: string;
  quantityOnHand: number;
  reorderLevel: number;
  unitCost: number;
  notes: string;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
};
type StoredMovement = MaterialMovement & { tenantId: number; materialId: number };
type StoredAllocation = MaterialAllocation & { tenantId: number };
type StoredFitting = {
  id: number;
  tenantId: number;
  orderItemId: number;
  assignedTo: number;
  scheduledAt: string;
  status: string;
  location: string;
  notes: string;
  outcome: string;
  createdAt: string;
  updatedAt: string;
};
type StoredAlteration = {
  id: number;
  tenantId: number;
  orderItemId: number;
  fittingId: number;
  description: string;
  status: string;
  dueDate: string;
  completedAt: string;
  createdAt: string;
  updatedAt: string;
};
type StoredCheck = {
  id: number;
  tenantId: number;
  orderItemId: number;
  checkedBy: number;
  result: string;
  notes: string;
  checkedAt: string;
  createdAt: string;
};

const stages: StoredStage[] = [
  {
    id: 1,
    tenantId: 1,
    name: 'Design confirmation',
    slug: 'design-confirmation',
    sequence: 10,
    isActive: true,
    createdAt: '2026-11-01 10:00:00',
    updatedAt: '2026-11-01 10:00:00',
  },
  {
    id: 2,
    tenantId: 1,
    name: 'Material preparation',
    slug: 'material-preparation',
    sequence: 20,
    isActive: true,
    createdAt: '2026-11-01 10:00:00',
    updatedAt: '2026-11-01 10:00:00',
  },
  {
    id: 3,
    tenantId: 1,
    name: 'Cutting',
    slug: 'cutting',
    sequence: 30,
    isActive: true,
    createdAt: '2026-11-01 10:00:00',
    updatedAt: '2026-11-01 10:00:00',
  },
  {
    id: 4,
    tenantId: 1,
    name: 'Sewing',
    slug: 'sewing',
    sequence: 40,
    isActive: true,
    createdAt: '2026-11-01 10:00:00',
    updatedAt: '2026-11-01 10:00:00',
  },
  {
    id: 5,
    tenantId: 1,
    name: 'Finishing',
    slug: 'finishing',
    sequence: 50,
    isActive: true,
    createdAt: '2026-11-01 10:00:00',
    updatedAt: '2026-11-01 10:00:00',
  },
  {
    id: 6,
    tenantId: 1,
    name: 'Fitting',
    slug: 'fitting',
    sequence: 60,
    isActive: true,
    createdAt: '2026-11-01 10:00:00',
    updatedAt: '2026-11-01 10:00:00',
  },
  {
    id: 7,
    tenantId: 1,
    name: 'Alterations',
    slug: 'alterations',
    sequence: 70,
    isActive: true,
    createdAt: '2026-11-01 10:00:00',
    updatedAt: '2026-11-01 10:00:00',
  },
  {
    id: 8,
    tenantId: 1,
    name: 'Quality control',
    slug: 'quality-control',
    sequence: 80,
    isActive: true,
    createdAt: '2026-11-01 10:00:00',
    updatedAt: '2026-11-01 10:00:00',
  },
  {
    id: 9,
    tenantId: 1,
    name: 'Ready for collection',
    slug: 'ready-for-collection',
    sequence: 90,
    isActive: true,
    createdAt: '2026-11-01 10:00:00',
    updatedAt: '2026-11-01 10:00:00',
  },
];

const jobs: StoredJob[] = [
  {
    id: 1,
    tenantId: 1,
    orderItemId: 1,
    assignedTo: 2,
    status: 'in_progress',
    startDate: '2026-11-18',
    dueDate: '2026-12-15',
    notes: 'Lining approved. Keep the shoulder light.',
    createdAt: '2026-11-17 09:30:00',
    updatedAt: '2026-11-19 08:10:00',
  },
  {
    id: 2,
    tenantId: 1,
    orderItemId: 2,
    assignedTo: 0,
    status: 'queued',
    startDate: '',
    dueDate: '2026-12-17',
    notes: '',
    createdAt: '2026-11-17 09:30:00',
    updatedAt: '2026-11-17 09:30:00',
  },
];

const tasks: StoredTask[] = [
  {
    id: 1,
    tenantId: 1,
    jobId: 1,
    stageId: 3,
    assignedTo: 2,
    status: 'done',
    dueDate: '2026-11-18',
    completedAt: '2026-11-18 16:00:00',
    notes: '',
  },
  {
    id: 2,
    tenantId: 1,
    jobId: 1,
    stageId: 4,
    assignedTo: 2,
    status: 'in_progress',
    dueDate: '2026-11-22',
    completedAt: '',
    notes: '',
  },
  {
    id: 3,
    tenantId: 1,
    jobId: 1,
    stageId: 6,
    assignedTo: 1,
    status: 'pending',
    dueDate: '2026-11-23',
    completedAt: '',
    notes: '',
  },
  {
    id: 4,
    tenantId: 1,
    jobId: 2,
    stageId: 3,
    assignedTo: 0,
    status: 'pending',
    dueDate: '2026-11-25',
    completedAt: '',
    notes: '',
  },
];

const materials: StoredMaterial[] = [
  {
    id: 1,
    tenantId: 1,
    name: 'Italian wool — ivory',
    materialType: 'fabric',
    unit: 'metre',
    quantityOnHand: 6,
    reorderLevel: 10,
    unitCost: 45000,
    notes: 'Ordered from Milan; 150 cm wide.',
    isActive: true,
    createdAt: '2026-11-02 09:00:00',
    updatedAt: '2026-11-18 10:00:00',
  },
  {
    id: 2,
    tenantId: 1,
    name: 'Silk lining — champagne',
    materialType: 'fabric',
    unit: 'metre',
    quantityOnHand: 3.5,
    reorderLevel: 4,
    unitCost: 12000,
    notes: '',
    isActive: true,
    createdAt: '2026-11-02 09:05:00',
    updatedAt: '2026-11-18 10:00:00',
  },
  {
    id: 3,
    tenantId: 1,
    name: 'Horn buttons — 20 mm',
    materialType: 'accessory',
    unit: 'piece',
    quantityOnHand: 40,
    reorderLevel: 20,
    unitCost: 450,
    notes: '',
    isActive: true,
    createdAt: '2026-11-02 09:10:00',
    updatedAt: '2026-11-02 09:10:00',
  },
  {
    id: 4,
    tenantId: 1,
    name: 'Gutterman thread — ecru',
    materialType: 'consumable',
    unit: 'spool',
    quantityOnHand: 12,
    reorderLevel: 6,
    unitCost: 1500,
    notes: '',
    isActive: true,
    createdAt: '2026-11-02 09:15:00',
    updatedAt: '2026-11-02 09:15:00',
  },
];

const movements: StoredMovement[] = [
  {
    id: 1,
    tenantId: 1,
    materialId: 1,
    type: 'in',
    quantity: 13,
    referenceType: 'opening',
    referenceId: 0,
    note: 'Opening stock',
    actorId: 1,
    actorName: 'Ada Obi',
    createdAt: '2026-11-02 09:00:00',
  },
  {
    id: 2,
    tenantId: 1,
    materialId: 2,
    type: 'in',
    quantity: 3.5,
    referenceType: 'opening',
    referenceId: 0,
    note: 'Opening stock',
    actorId: 1,
    actorName: 'Ada Obi',
    createdAt: '2026-11-02 09:05:00',
  },
  {
    id: 3,
    tenantId: 1,
    materialId: 3,
    type: 'in',
    quantity: 40,
    referenceType: 'opening',
    referenceId: 0,
    note: 'Opening stock',
    actorId: 1,
    actorName: 'Ada Obi',
    createdAt: '2026-11-02 09:10:00',
  },
  {
    id: 4,
    tenantId: 1,
    materialId: 4,
    type: 'in',
    quantity: 12,
    referenceType: 'opening',
    referenceId: 0,
    note: 'Opening stock',
    actorId: 1,
    actorName: 'Ada Obi',
    createdAt: '2026-11-02 09:15:00',
  },
  {
    id: 5,
    tenantId: 1,
    materialId: 1,
    type: 'allocation',
    quantity: -4,
    referenceType: 'order_item',
    referenceId: 1,
    note: 'Allocated Italian wool — ivory',
    actorId: 1,
    actorName: 'Ada Obi',
    createdAt: '2026-11-17 09:40:00',
  },
  {
    id: 6,
    tenantId: 1,
    materialId: 1,
    type: 'allocation',
    quantity: -3,
    referenceType: 'order_item',
    referenceId: 2,
    note: 'Allocated Italian wool — ivory',
    actorId: 1,
    actorName: 'Ada Obi',
    createdAt: '2026-11-17 09:42:00',
  },
];

const allocations: StoredAllocation[] = [
  {
    id: 1,
    tenantId: 1,
    orderItemId: 1,
    materialId: 1,
    materialName: 'Italian wool — ivory',
    unit: 'metre',
    quantity: 4,
    source: 'designer_supplied',
    notes: '',
    createdBy: 1,
    createdByName: 'Ada Obi',
    createdAt: '2026-11-17 09:40:00',
  },
  {
    id: 2,
    tenantId: 1,
    orderItemId: 2,
    materialId: 1,
    materialName: 'Italian wool — ivory',
    unit: 'metre',
    quantity: 3,
    source: 'designer_supplied',
    notes: '',
    createdBy: 1,
    createdByName: 'Ada Obi',
    createdAt: '2026-11-17 09:42:00',
  },
  {
    id: 3,
    tenantId: 1,
    orderItemId: 3,
    materialId: 2,
    materialName: 'Silk lining — champagne',
    unit: 'metre',
    quantity: 1.5,
    source: 'customer_supplied',
    notes: 'Customer brought her own silk.',
    createdBy: 1,
    createdByName: 'Ada Obi',
    createdAt: '2026-11-18 11:00:00',
  },
];

const fittings: StoredFitting[] = [
  {
    id: 1,
    tenantId: 1,
    orderItemId: 1,
    assignedTo: 1,
    scheduledAt: '2026-11-14 10:00:00',
    status: 'completed',
    location: 'Atelier, Ikoyi',
    notes: 'First fitting.',
    outcome: 'Sleeves taken up; customer approved the lining.',
    createdAt: '2026-11-12 12:00:00',
    updatedAt: '2026-11-14 11:00:00',
  },
  {
    id: 2,
    tenantId: 1,
    orderItemId: 1,
    assignedTo: 1,
    scheduledAt: '2026-11-21 10:00:00',
    status: 'scheduled',
    location: 'Atelier, Ikoyi',
    notes: 'Second fitting after alterations.',
    outcome: '',
    createdAt: '2026-11-18 09:00:00',
    updatedAt: '2026-11-18 09:00:00',
  },
  {
    id: 3,
    tenantId: 1,
    orderItemId: 2,
    assignedTo: 2,
    scheduledAt: '2026-11-22 14:00:00',
    status: 'scheduled',
    location: 'Atelier, Ikoyi',
    notes: '',
    outcome: '',
    createdAt: '2026-11-18 09:05:00',
    updatedAt: '2026-11-18 09:05:00',
  },
];

const alterations: StoredAlteration[] = [
  {
    id: 1,
    tenantId: 1,
    orderItemId: 1,
    fittingId: 1,
    description: 'Shorten jacket sleeves by 1 cm',
    status: 'completed',
    dueDate: '2026-11-18',
    completedAt: '2026-11-18 15:00:00',
    createdAt: '2026-11-14 11:05:00',
    updatedAt: '2026-11-18 15:00:00',
  },
  {
    id: 2,
    tenantId: 1,
    orderItemId: 1,
    fittingId: 1,
    description: 'Let out the waist by 2 cm',
    status: 'open',
    dueDate: '2026-11-24',
    completedAt: '',
    createdAt: '2026-11-14 11:08:00',
    updatedAt: '2026-11-14 11:08:00',
  },
];

const checks: StoredCheck[] = [
  {
    id: 1,
    tenantId: 1,
    orderItemId: 1,
    checkedBy: 1,
    result: 'failed',
    notes: 'Loose thread on the left cuff; re-stitch before the second fitting.',
    checkedAt: '2026-11-19 09:00:00',
    createdAt: '2026-11-19 09:00:00',
  },
  {
    id: 2,
    tenantId: 1,
    orderItemId: 2,
    checkedBy: 1,
    result: 'passed',
    notes: '',
    checkedAt: '2026-11-18 17:00:00',
    createdAt: '2026-11-18 17:00:00',
  },
];

let nextStageId = 10;
let nextJobId = 3;
let nextTaskId = 5;
let nextMaterialId = 5;
let nextMovementId = 7;
let nextAllocationId = 4;
let nextFittingId = 4;
let nextAlterationId = 3;
let nextCheckId = 3;

let nextDesignId = 4;
let nextQuotationId = 3;
let nextQuotationItemId = 5;
let nextOrderId = 2;
let nextOrderItemId = 4;
let nextOrderEventId = 7;
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

/* -------------------------------------------------------------------------- */
/* Designs, quotations and orders                                             */
/* -------------------------------------------------------------------------- */

function withoutTenant<T extends { tenantId: number }>(record: T): Omit<T, 'tenantId'> {
  const { tenantId, ...rest } = record;

  void tenantId;

  return rest;
}

function designFor(tenantId: number, designId: number): StoredDesign | undefined {
  return designs.find((item) => item.tenantId === tenantId && item.id === designId);
}

function presentDesign(design: StoredDesign): Design {
  return withoutTenant(design);
}

function quotationItemsFor(tenantId: number, quotationId: number): StoredQuotationItem[] {
  return quotationItems
    .filter((item) => item.tenantId === tenantId && item.quotationId === quotationId)
    .sort((left, right) => left.id - right.id);
}

function quotationFor(tenantId: number, quotationId: number): StoredQuotation | undefined {
  return quotations.find((item) => item.tenantId === tenantId && item.id === quotationId);
}

function presentQuotation(quotation: StoredQuotation): Quotation {
  const items = quotationItemsFor(quotation.tenantId, quotation.id).map(withoutTenant);

  return { ...withoutTenant(quotation), items, itemCount: items.length };
}

function orderFor(tenantId: number, orderId: number): StoredOrder | undefined {
  return orders.find((item) => item.tenantId === tenantId && item.id === orderId);
}

function orderItemsFor(tenantId: number, orderId: number): StoredOrderItem[] {
  return orderItems
    .filter((item) => item.tenantId === tenantId && item.orderId === orderId)
    .sort((left, right) => left.id - right.id);
}

function orderEventsFor(tenantId: number, orderId: number): StoredOrderEvent[] {
  return orderEvents
    .filter((item) => item.tenantId === tenantId && item.orderId === orderId)
    .sort((left, right) => left.id - right.id);
}

function presentOrder(order: StoredOrder): Order {
  const items = orderItemsFor(order.tenantId, order.id).map(withoutTenant);
  const timeline = orderEventsFor(order.tenantId, order.id).map(withoutTenant);

  return { ...withoutTenant(order), items, timeline, garmentCount: items.length };
}

const DEFAULT_STAGE_DEFINITIONS = [
  ['Design confirmation', 'design-confirmation', 10],
  ['Material preparation', 'material-preparation', 20],
  ['Cutting', 'cutting', 30],
  ['Sewing', 'sewing', 40],
  ['Finishing', 'finishing', 50],
  ['Fitting', 'fitting', 60],
  ['Alterations', 'alterations', 70],
  ['Quality control', 'quality-control', 80],
  ['Ready for collection', 'ready-for-collection', 90],
] as const;

function stagesFor(tenantId: number): StoredStage[] {
  let list = stages
    .filter((stage) => stage.tenantId === tenantId)
    .sort((left, right) => left.sequence - right.sequence || left.id - right.id);

  if (list.length === 0) {
    DEFAULT_STAGE_DEFINITIONS.forEach(([name, slug, sequence]) => {
      stages.push({
        id: nextStageId++,
        tenantId,
        name,
        slug,
        sequence,
        isActive: true,
        createdAt: nowStamp(),
        updatedAt: nowStamp(),
      });
    });

    list = stages
      .filter((stage) => stage.tenantId === tenantId)
      .sort((left, right) => left.sequence - right.sequence || left.id - right.id);
  }

  return list;
}

function stageFor(tenantId: number, stageId: number): StoredStage | undefined {
  return stages.find((stage) => stage.tenantId === tenantId && stage.id === stageId);
}

function stageSlug(name: string): string {
  return name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
}

function presentStage(stage: StoredStage): ProductionStage {
  return withoutTenant(stage);
}

function jobFor(tenantId: number, jobId: number): StoredJob | undefined {
  return jobs.find((job) => job.tenantId === tenantId && job.id === jobId);
}

function tasksFor(tenantId: number, jobId: number): StoredTask[] {
  return tasks
    .filter((task) => task.tenantId === tenantId && task.jobId === jobId)
    .sort((left, right) => {
      const leftStage = stageFor(left.tenantId, left.stageId)?.sequence ?? 0;
      const rightStage = stageFor(right.tenantId, right.stageId)?.sequence ?? 0;

      return leftStage - rightStage || left.id - right.id;
    });
}

function presentTask(task: StoredTask): ProductionTask {
  const stage = stageFor(task.tenantId, task.stageId);

  return {
    id: task.id,
    jobId: task.jobId,
    stageId: task.stageId,
    stageName: stage?.name ?? '',
    stageSlug: stage?.slug ?? '',
    status: task.status,
    assignedTo: task.assignedTo,
    assigneeName: users.find((user) => user.id === task.assignedTo)?.name ?? '',
    dueDate: task.dueDate,
    completedAt: task.completedAt,
    notes: task.notes,
  };
}

function itemContext(itemId: number) {
  const item = orderItems.find((line) => line.id === itemId);

  if (!item) {
    return undefined;
  }

  const order = orderFor(item.tenantId, item.orderId);

  return order ? { item, order } : undefined;
}

function presentJob(job: StoredJob): ProductionJob {
  const context = itemContext(job.orderItemId);
  const jobTasks = tasksFor(job.tenantId, job.id);

  return {
    id: job.id,
    orderItemId: job.orderItemId,
    orderId: context?.order.id ?? 0,
    orderReference: context?.order.reference ?? '',
    orderDueDate: context?.order.dueDate ?? '',
    clientName: context?.order.clientName ?? '',
    garmentName: context?.item.garmentName ?? '',
    garmentCategory: context?.item.garmentCategory ?? '',
    garmentStatus: context?.item.status ?? '',
    status: job.status,
    assignedTo: job.assignedTo,
    assigneeName: users.find((user) => user.id === job.assignedTo)?.name ?? '',
    startDate: job.startDate,
    dueDate: job.dueDate,
    notes: job.notes,
    taskTotal: jobTasks.length,
    taskDone: jobTasks.filter((task) => task.status === 'done').length,
    tasks: jobTasks.map(presentTask),
    createdAt: job.createdAt,
    updatedAt: job.updatedAt,
  };
}

function materialFor(tenantId: number, materialId: number): StoredMaterial | undefined {
  return materials.find((material) => material.tenantId === tenantId && material.id === materialId);
}

function movementsFor(tenantId: number, materialId: number): StoredMovement[] {
  return movements
    .filter((movement) => movement.tenantId === tenantId && movement.materialId === materialId)
    .sort((left, right) => right.id - left.id);
}

function presentMaterial(material: StoredMaterial, withMovements = false): Material {
  const recent = withMovements ? movementsFor(material.tenantId, material.id) : [];

  return {
    id: material.id,
    name: material.name,
    materialType: material.materialType,
    unit: material.unit,
    quantityOnHand: money(material.quantityOnHand),
    reorderLevel: material.reorderLevel,
    unitCost: material.unitCost,
    notes: material.notes,
    isActive: material.isActive,
    lowStock: material.reorderLevel > 0 && material.quantityOnHand <= material.reorderLevel,
    movements: recent.map((movement) => ({
      id: movement.id,
      type: movement.type,
      quantity: movement.quantity,
      referenceType: movement.referenceType,
      referenceId: movement.referenceId,
      note: movement.note,
      actorId: movement.actorId,
      actorName: movement.actorName,
      createdAt: movement.createdAt,
    })),
    createdAt: material.createdAt,
    updatedAt: material.updatedAt,
  };
}

function presentAllocation(allocation: StoredAllocation): MaterialAllocation {
  return withoutTenant(allocation);
}

function allocationsFor(tenantId: number, itemId: number): StoredAllocation[] {
  return allocations
    .filter((allocation) => allocation.tenantId === tenantId && allocation.orderItemId === itemId)
    .sort((left, right) => left.id - right.id);
}

function allocationResponse(tenantId: number, itemId: number) {
  const context = itemContext(itemId);

  return {
    garment: {
      id: itemId,
      garmentName: context?.item.garmentName ?? '',
      garmentCategory: context?.item.garmentCategory ?? '',
      orderId: context?.order.id ?? 0,
      orderReference: context?.order.reference ?? '',
      clientName: context?.order.clientName ?? '',
    },
    allocations: allocationsFor(tenantId, itemId).map(presentAllocation),
  };
}

function presentFitting(fitting: StoredFitting): Fitting {
  const context = itemContext(fitting.orderItemId);

  return {
    id: fitting.id,
    orderItemId: fitting.orderItemId,
    orderId: context?.order.id ?? 0,
    orderReference: context?.order.reference ?? '',
    clientName: context?.order.clientName ?? '',
    garmentName: context?.item.garmentName ?? '',
    assignedTo: fitting.assignedTo,
    assigneeName: users.find((user) => user.id === fitting.assignedTo)?.name ?? '',
    scheduledAt: fitting.scheduledAt,
    status: fitting.status,
    location: fitting.location,
    notes: fitting.notes,
    outcome: fitting.outcome,
    createdAt: fitting.createdAt,
    updatedAt: fitting.updatedAt,
  };
}

function presentAlteration(alteration: StoredAlteration): Alteration {
  const context = itemContext(alteration.orderItemId);
  const fitting = fittings.find(
    (item) => item.tenantId === alteration.tenantId && item.id === alteration.fittingId
  );

  return {
    id: alteration.id,
    orderItemId: alteration.orderItemId,
    orderId: context?.order.id ?? 0,
    orderReference: context?.order.reference ?? '',
    clientName: context?.order.clientName ?? '',
    garmentName: context?.item.garmentName ?? '',
    fittingId: alteration.fittingId,
    fittingScheduledAt: fitting?.scheduledAt ?? '',
    description: alteration.description,
    status: alteration.status,
    dueDate: alteration.dueDate,
    completedAt: alteration.completedAt,
    createdAt: alteration.createdAt,
    updatedAt: alteration.updatedAt,
  };
}

function presentCheck(check: StoredCheck): QualityCheck {
  const context = itemContext(check.orderItemId);

  return {
    id: check.id,
    orderItemId: check.orderItemId,
    orderId: context?.order.id ?? 0,
    orderReference: context?.order.reference ?? '',
    clientName: context?.order.clientName ?? '',
    garmentName: context?.item.garmentName ?? '',
    checkedBy: check.checkedBy,
    checkedByName: users.find((user) => user.id === check.checkedBy)?.name ?? '',
    result: check.result,
    notes: check.notes,
    checkedAt: check.checkedAt,
    createdAt: check.createdAt,
  };
}

function recordOrderEvent(
  tenantId: number,
  orderId: number,
  type: string,
  message: string,
  orderItemId = 0
): void {
  orderEvents.push({
    id: nextOrderEventId++,
    tenantId,
    orderId,
    type,
    message,
    orderItemId,
    actorId: CURRENT_USER,
    actorName: 'Ada Obi',
    createdAt: nowStamp(),
  });
}

function humanStatus(status: string): string {
  return status.replace(/_/g, ' ').replace(/^\w/, (letter) => letter.toUpperCase());
}

function money(value: number): number {
  return Math.round(value * 100) / 100;
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
      pluginVersion: '0.5.0',
      database: { latestVersion: '0004', applied: 4, pending: 0 },
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

  /* Designs ------------------------------------------------------------- */

  if (path === '/designs' && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    const search = (url.searchParams.get('search') ?? '').trim().toLowerCase();
    const status = url.searchParams.get('status') ?? 'all';
    const clientFilter = Number(url.searchParams.get('client_id') ?? 0);
    const page = Math.max(1, Number(url.searchParams.get('page') ?? 1));
    const perPage = Math.min(100, Math.max(1, Number(url.searchParams.get('per_page') ?? 25)));

    const matches = designs
      .filter((item) => item.tenantId === active.tenant.id)
      .filter((item) => (status === 'all' ? true : item.status === status))
      .filter((item) => (clientFilter > 0 ? item.clientId === clientFilter : true))
      .filter((item) =>
        search === ''
          ? true
          : [item.title, item.reference, item.garmentCategory, item.clientName]
              .join(' ')
              .toLowerCase()
              .includes(search)
      )
      .sort((left, right) => right.updatedAt.localeCompare(left.updatedAt));

    const start = (page - 1) * perPage;
    const pageItems = matches.slice(start, start + perPage);

    return ok({
      designs: pageItems.map(presentDesign),
      page,
      perPage,
      total: matches.length,
      totalPages: matches.length === 0 ? 0 : Math.ceil(matches.length / perPage),
    });
  }

  if (path === '/designs' && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const input = (body ?? {}) as {
      title?: string;
      clientId?: number;
      garmentCategory?: string;
      brief?: string;
      measurementProfileId?: number;
      measurementVersionId?: number;
    };
    const title = String(input.title ?? '').trim();

    if (!title) {
      return fail('design_title_required', 'Give the design a title.', 422);
    }

    const customer = clientFor(active.tenant.id, Number(input.clientId ?? 0));

    if (!customer) {
      return fail('design_client_required', 'Choose the customer this design is for.', 422);
    }

    let profileId = Number(input.measurementProfileId ?? 0);
    const versionId = Number(input.measurementVersionId ?? 0);

    if (versionId > 0) {
      const version = versions.find(
        (item) => item.tenantId === active.tenant.id && item.id === versionId
      );
      const profile = version
        ? profiles.find(
            (item) => item.tenantId === active.tenant.id && item.id === version.profileId
          )
        : undefined;

      if (!version || !profile) {
        return fail(
          'measurement_version_not_found',
          'That measurement version could not be found.',
          404
        );
      }

      if (profile.clientId !== customer.id) {
        return fail(
          'design_measurement_mismatch',
          'That measurement version belongs to a different customer.',
          422
        );
      }

      profileId = profile.id;
    } else if (profileId > 0) {
      const profile = profiles.find(
        (item) => item.tenantId === active.tenant.id && item.id === profileId
      );

      if (!profile) {
        return fail(
          'measurement_profile_not_found',
          'That measurement profile could not be found.',
          404
        );
      }

      if (profile.clientId !== customer.id) {
        return fail(
          'design_measurement_mismatch',
          'That measurement profile belongs to a different customer.',
          422
        );
      }
    }

    const profile = profiles.find(
      (item) => item.tenantId === active.tenant.id && item.id === profileId
    );
    const version = versions.find(
      (item) => item.tenantId === active.tenant.id && item.id === versionId
    );
    const id = nextDesignId++;

    const design: StoredDesign = {
      id,
      tenantId: active.tenant.id,
      uuid: crypto.randomUUID(),
      reference: `D-${String(id).padStart(4, '0')}`,
      clientId: customer.id,
      clientName: customer.name,
      measurementProfileId: profileId,
      profileName: profile?.profileName ?? '',
      measurementVersionId: versionId,
      measurementVersionNumber: version?.versionNumber ?? 0,
      measurementTakenAt: version?.takenAt ?? '',
      title,
      garmentCategory: String(input.garmentCategory ?? '').trim(),
      brief: String(input.brief ?? '').trim(),
      status: 'draft',
      createdAt: nowStamp(),
      updatedAt: nowStamp(),
    };

    designs.push(design);
    record(
      active.tenant.id,
      'design.created',
      {
        reference: design.reference,
        title,
        clientId: customer.id,
        measurementVersionId: versionId,
      },
      'design',
      id
    );

    return ok(presentDesign(design), 201);
  }

  const designMatch = /^\/designs\/(\d+)$/.exec(path);

  if (designMatch) {
    if (!active) {
      return workspaceRequired();
    }

    const design = designFor(active.tenant.id, Number(designMatch[1]));

    if (!design) {
      return fail('design_not_found', 'That design could not be found.', 404);
    }

    if (method === 'GET') {
      return ok(presentDesign(design));
    }

    if (method === 'PATCH') {
      const input = (body ?? {}) as {
        title?: string;
        garmentCategory?: string;
        brief?: string;
        status?: string;
        measurementProfileId?: number;
        measurementVersionId?: number;
      };
      const changed: string[] = [];

      if (typeof input.title === 'string' && input.title.trim()) {
        design.title = input.title.trim();
        changed.push('title');
      }

      if (typeof input.garmentCategory === 'string') {
        design.garmentCategory = input.garmentCategory.trim();
        changed.push('garment_category');
      }

      if (typeof input.brief === 'string') {
        design.brief = input.brief.trim();
        changed.push('brief');
      }

      if (input.status === 'draft' || input.status === 'approved' || input.status === 'archived') {
        design.status = input.status;
        changed.push('status');
      }

      if (input.measurementVersionId !== undefined) {
        const versionId = Number(input.measurementVersionId);
        const version = versions.find(
          (item) => item.tenantId === active.tenant.id && item.id === versionId
        );
        const profile = version
          ? profiles.find(
              (item) => item.tenantId === active.tenant.id && item.id === version.profileId
            )
          : undefined;

        if (versionId > 0 && (!version || !profile)) {
          return fail(
            'measurement_version_not_found',
            'That measurement version could not be found.',
            404
          );
        }

        if (profile && profile.clientId !== design.clientId) {
          return fail(
            'design_measurement_mismatch',
            'That measurement version belongs to a different customer.',
            422
          );
        }

        design.measurementVersionId = versionId > 0 ? versionId : 0;
        design.measurementProfileId = profile?.id ?? 0;
        design.profileName = profile?.profileName ?? '';
        design.measurementVersionNumber = version?.versionNumber ?? 0;
        design.measurementTakenAt = version?.takenAt ?? '';
        changed.push('measurement_version_id');
      } else if (input.measurementProfileId !== undefined) {
        const profileId = Number(input.measurementProfileId);
        const profile = profiles.find(
          (item) => item.tenantId === active.tenant.id && item.id === profileId
        );

        if (profileId > 0 && !profile) {
          return fail(
            'measurement_profile_not_found',
            'That measurement profile could not be found.',
            404
          );
        }

        if (profile && profile.clientId !== design.clientId) {
          return fail(
            'design_measurement_mismatch',
            'That measurement profile belongs to a different customer.',
            422
          );
        }

        design.measurementProfileId = profileId > 0 ? profileId : 0;
        design.profileName = profile?.profileName ?? '';
        changed.push('measurement_profile_id');
      }

      design.updatedAt = nowStamp();
      record(active.tenant.id, 'design.updated', { changed }, 'design', design.id);

      return ok(presentDesign(design));
    }
  }

  /* Quotations ---------------------------------------------------------- */

  if (path === '/quotations' && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    const search = (url.searchParams.get('search') ?? '').trim().toLowerCase();
    const status = url.searchParams.get('status') ?? 'all';
    const clientFilter = Number(url.searchParams.get('client_id') ?? 0);
    const page = Math.max(1, Number(url.searchParams.get('page') ?? 1));
    const perPage = Math.min(100, Math.max(1, Number(url.searchParams.get('per_page') ?? 25)));

    const matches = quotations
      .filter((item) => item.tenantId === active.tenant.id)
      .filter((item) => (status === 'all' ? true : item.status === status))
      .filter((item) => (clientFilter > 0 ? item.clientId === clientFilter : true))
      .filter((item) =>
        search === ''
          ? true
          : [item.reference, item.clientName, item.designTitle]
              .join(' ')
              .toLowerCase()
              .includes(search)
      )
      .sort((left, right) => right.updatedAt.localeCompare(left.updatedAt));

    const start = (page - 1) * perPage;

    return ok({
      quotations: matches.slice(start, start + perPage).map(presentQuotation),
      page,
      perPage,
      total: matches.length,
      totalPages: matches.length === 0 ? 0 : Math.ceil(matches.length / perPage),
    });
  }

  if (path === '/quotations' && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const customer = clientFor(
      active.tenant.id,
      Number((body as { clientId?: number } | null)?.clientId ?? 0)
    );

    if (!customer) {
      return fail('quotation_client_required', 'Choose the customer this quotation is for.', 422);
    }

    const input = (body ?? {}) as {
      designId?: number;
      validUntil?: string;
      notes?: string;
      items?: Array<{
        description?: string;
        quantity?: number | string;
        unitPrice?: number | string;
      }>;
    };
    const design = designFor(active.tenant.id, Number(input.designId ?? 0));

    if (Number(input.designId ?? 0) > 0 && !design) {
      return fail('design_not_found', 'That design could not be found.', 404);
    }

    if (design && design.clientId !== customer.id) {
      return fail('quotation_design_mismatch', 'That design belongs to a different customer.', 422);
    }

    const lines = Array.isArray(input.items) ? input.items : [];

    if (lines.length === 0) {
      return fail('quotation_items_required', 'Add at least one line to the quotation.', 422);
    }

    const priced: StoredQuotationItem[] = [];

    for (const line of lines) {
      const description = String(line.description ?? '').trim();
      const quantity = Number(line.quantity ?? 1);
      const unitPrice = Number(line.unitPrice ?? 0);

      if (!description) {
        return fail(
          'quotation_item_description_required',
          'Give each quotation line a description.',
          422
        );
      }

      if (!Number.isInteger(quantity) || quantity < 1 || quantity > 9999) {
        return fail(
          'quotation_item_quantity_invalid',
          'Quantities must be whole numbers between 1 and 9999.',
          422
        );
      }

      if (!Number.isFinite(unitPrice) || unitPrice < 0) {
        return fail(
          'quotation_item_price_invalid',
          'Prices must be amounts between 0 and 9,999,999.99.',
          422
        );
      }

      priced.push({
        id: nextQuotationItemId++,
        tenantId: active.tenant.id,
        quotationId: 0,
        description,
        quantity,
        unitPrice: money(unitPrice),
        lineTotal: money(quantity * unitPrice),
      });
    }

    const id = nextQuotationId++;
    const total = money(priced.reduce((sum, line) => sum + line.lineTotal, 0));

    const quotation: StoredQuotation = {
      id,
      tenantId: active.tenant.id,
      uuid: crypto.randomUUID(),
      reference: `Q-${String(id).padStart(4, '0')}`,
      clientId: customer.id,
      clientName: customer.name,
      designId: design?.id ?? 0,
      designTitle: design?.title ?? '',
      currency: active.tenant.currency,
      status: 'draft',
      validUntil: String(input.validUntil ?? ''),
      notes: String(input.notes ?? '').trim(),
      subtotal: total,
      total,
      createdAt: nowStamp(),
      updatedAt: nowStamp(),
    };

    priced.forEach((line) => {
      line.quotationId = id;
      quotationItems.push(line);
    });
    quotations.push(quotation);
    record(
      active.tenant.id,
      'quotation.created',
      { reference: quotation.reference, clientId: customer.id, items: priced.length, total },
      'quotation',
      id
    );

    return ok(presentQuotation(quotation), 201);
  }

  const quotationMatch = /^\/quotations\/(\d+)$/.exec(path);

  if (quotationMatch) {
    if (!active) {
      return workspaceRequired();
    }

    const quotation = quotationFor(active.tenant.id, Number(quotationMatch[1]));

    if (!quotation) {
      return fail('quotation_not_found', 'That quotation could not be found.', 404);
    }

    if (method === 'GET') {
      return ok(presentQuotation(quotation));
    }

    if (method === 'PATCH') {
      const input = (body ?? {}) as {
        status?: string;
        validUntil?: string;
        notes?: string;
        items?: Array<{
          description?: string;
          quantity?: number | string;
          unitPrice?: number | string;
        }>;
      };
      const changed: string[] = [];

      if (typeof input.status === 'string') {
        if (!['draft', 'sent', 'accepted', 'declined', 'expired'].includes(input.status)) {
          return fail('quotation_status_invalid', 'Choose a valid quotation status.', 422);
        }

        quotation.status = input.status;
        changed.push('status');
      }

      if (typeof input.validUntil === 'string') {
        quotation.validUntil = input.validUntil;
        changed.push('valid_until');
      }

      if (typeof input.notes === 'string') {
        quotation.notes = input.notes.trim();
        changed.push('notes');
      }

      if (Array.isArray(input.items)) {
        if (quotation.status !== 'draft') {
          return fail('quotation_locked', 'Only a draft quotation can change its line items.', 409);
        }

        const replacement: StoredQuotationItem[] = input.items.map((line) => {
          const quantity = Number(line.quantity ?? 1);
          const unitPrice = Number(line.unitPrice ?? 0);

          return {
            id: nextQuotationItemId++,
            tenantId: active.tenant.id,
            quotationId: quotation.id,
            description: String(line.description ?? '').trim(),
            quantity,
            unitPrice: money(unitPrice),
            lineTotal: money(quantity * unitPrice),
          };
        });

        if (replacement.length === 0) {
          return fail('quotation_items_required', 'Add at least one line to the quotation.', 422);
        }

        for (let index = quotationItems.length - 1; index >= 0; index -= 1) {
          if (
            quotationItems[index].tenantId === active.tenant.id &&
            quotationItems[index].quotationId === quotation.id
          ) {
            quotationItems.splice(index, 1);
          }
        }

        quotationItems.push(...replacement);

        const total = money(replacement.reduce((sum, line) => sum + line.lineTotal, 0));
        quotation.subtotal = total;
        quotation.total = total;
        changed.push('items');
      }

      quotation.updatedAt = nowStamp();
      record(active.tenant.id, 'quotation.updated', { changed }, 'quotation', quotation.id);

      return ok(presentQuotation(quotation));
    }
  }

  /* Orders -------------------------------------------------------------- */

  if (path === '/orders' && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    const search = (url.searchParams.get('search') ?? '').trim().toLowerCase();
    const status = url.searchParams.get('status') ?? 'all';
    const clientFilter = Number(url.searchParams.get('client_id') ?? 0);
    const page = Math.max(1, Number(url.searchParams.get('page') ?? 1));
    const perPage = Math.min(100, Math.max(1, Number(url.searchParams.get('per_page') ?? 25)));

    const matches = orders
      .filter((item) => item.tenantId === active.tenant.id)
      .filter((item) => (status === 'all' ? true : item.status === status))
      .filter((item) => (clientFilter > 0 ? item.clientId === clientFilter : true))
      .filter((item) => {
        if (search === '') {
          return true;
        }

        const garments = orderItemsFor(item.tenantId, item.id).map((line) => line.garmentName);

        return [item.reference, item.clientName, ...garments]
          .join(' ')
          .toLowerCase()
          .includes(search);
      })
      .sort((left, right) => right.updatedAt.localeCompare(left.updatedAt));

    const start = (page - 1) * perPage;

    return ok({
      orders: matches.slice(start, start + perPage).map(presentOrder),
      page,
      perPage,
      total: matches.length,
      totalPages: matches.length === 0 ? 0 : Math.ceil(matches.length / perPage),
    });
  }

  if (path === '/orders' && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const input = (body ?? {}) as {
      clientId?: number;
      quotationId?: number;
      dueDate?: string;
      notes?: string;
      items?: Array<{
        garmentName?: string;
        quantity?: number | string;
        unitPrice?: number | string;
        designId?: number;
        measurementVersionId?: number;
        quotationItemId?: number;
      }>;
    };
    const quotationId = Number(input.quotationId ?? 0);
    const quotation = quotationId > 0 ? quotationFor(active.tenant.id, quotationId) : undefined;

    if (quotationId > 0 && !quotation) {
      return fail('quotation_not_found', 'That quotation could not be found.', 404);
    }

    const clientId = quotation ? quotation.clientId : Number(input.clientId ?? 0);
    const customer = clientFor(active.tenant.id, clientId);

    if (!customer) {
      return fail('order_client_required', 'Choose the customer this order is for.', 422);
    }

    if (
      quotation &&
      Number(input.clientId ?? 0) > 0 &&
      Number(input.clientId) !== quotation.clientId
    ) {
      return fail('order_client_mismatch', 'That quotation belongs to a different customer.', 422);
    }

    let rawItems = Array.isArray(input.items) ? input.items : [];

    if (rawItems.length === 0) {
      if (!quotation) {
        return fail('order_items_required', 'Add at least one garment to the order.', 422);
      }

      rawItems = quotationItemsFor(active.tenant.id, quotation.id).map((line) => ({
        garmentName: line.description,
        quantity: line.quantity,
        unitPrice: line.unitPrice,
        designId: quotation.designId,
        quotationItemId: line.id,
      }));
    }

    const id = nextOrderId++;
    const prepared: StoredOrderItem[] = [];

    for (const line of rawItems) {
      const garmentName = String(line.garmentName ?? '').trim();
      const quantity = Number(line.quantity ?? 1);
      const unitPrice = Number(line.unitPrice ?? 0);
      const design = designFor(active.tenant.id, Number(line.designId ?? 0));
      let versionId = Number(line.measurementVersionId ?? design?.measurementVersionId ?? 0);

      if (!garmentName) {
        return fail('order_item_name_required', 'Give each garment a name.', 422);
      }

      if (!Number.isInteger(quantity) || quantity < 1 || quantity > 9999) {
        return fail(
          'order_item_quantity_invalid',
          'Quantities must be whole numbers between 1 and 9999.',
          422
        );
      }

      if (!Number.isFinite(unitPrice) || unitPrice < 0) {
        return fail(
          'order_item_price_invalid',
          'Prices must be amounts between 0 and 9,999,999.99.',
          422
        );
      }

      if (design && design.clientId !== customer.id) {
        return fail('order_design_mismatch', 'That design belongs to a different customer.', 422);
      }

      const version = versions.find(
        (item) => item.tenantId === active.tenant.id && item.id === versionId
      );
      const profile = version
        ? profiles.find(
            (item) => item.tenantId === active.tenant.id && item.id === version.profileId
          )
        : undefined;

      if (versionId > 0 && (!version || !profile)) {
        return fail(
          'measurement_version_not_found',
          'That measurement version could not be found.',
          404
        );
      }

      if (profile && profile.clientId !== customer.id) {
        return fail(
          'order_measurement_mismatch',
          'That measurement version belongs to a different customer.',
          422
        );
      }

      versionId = version?.id ?? 0;

      prepared.push({
        id: nextOrderItemId++,
        tenantId: active.tenant.id,
        orderId: id,
        quotationItemId: Number(line.quotationItemId ?? 0),
        garmentName,
        garmentCategory: design?.garmentCategory ?? '',
        quantity,
        unitPrice: money(unitPrice),
        lineTotal: money(quantity * unitPrice),
        status: 'pending',
        notes: '',
        designId: design?.id ?? 0,
        designTitle: design?.title ?? '',
        designReference: design?.reference ?? '',
        measurementProfileId: profile?.id ?? 0,
        profileName: profile?.profileName ?? '',
        measurementVersionId: versionId,
        measurementVersionNumber: version?.versionNumber ?? 0,
        measurementTakenAt: version?.takenAt ?? '',
        createdAt: nowStamp(),
        updatedAt: nowStamp(),
      });
    }

    const total = money(prepared.reduce((sum, line) => sum + line.lineTotal, 0));
    const order: StoredOrder = {
      id,
      tenantId: active.tenant.id,
      uuid: crypto.randomUUID(),
      reference: `O-${String(id).padStart(4, '0')}`,
      clientId: customer.id,
      clientName: customer.name,
      quotationId: quotation?.id ?? 0,
      quotationReference: quotation?.reference ?? '',
      currency: quotation?.currency ?? active.tenant.currency,
      status: 'draft',
      dueDate: String(input.dueDate ?? ''),
      notes: String(input.notes ?? '').trim(),
      total,
      createdAt: nowStamp(),
      updatedAt: nowStamp(),
    };

    orders.push(order);
    orderItems.push(...prepared);
    recordOrderEvent(
      active.tenant.id,
      id,
      'created',
      `Order created with ${prepared.length} garments.`
    );

    prepared.forEach((line) => {
      recordOrderEvent(
        active.tenant.id,
        id,
        'item_added',
        line.measurementVersionNumber > 0
          ? `${line.garmentName} added with measurements v${line.measurementVersionNumber}.`
          : `${line.garmentName} added without a measurement reference.`,
        line.id
      );
    });

    record(
      active.tenant.id,
      'order.created',
      {
        reference: order.reference,
        clientId: customer.id,
        quotationId: order.quotationId,
        garments: prepared.length,
        total,
      },
      'order',
      id
    );

    return ok(presentOrder(order), 201);
  }

  const orderMatch = /^\/orders\/(\d+)$/.exec(path);

  if (orderMatch) {
    if (!active) {
      return workspaceRequired();
    }

    const order = orderFor(active.tenant.id, Number(orderMatch[1]));

    if (!order) {
      return fail('order_not_found', 'That order could not be found.', 404);
    }

    if (method === 'GET') {
      return ok(presentOrder(order));
    }

    if (method === 'PATCH') {
      const input = (body ?? {}) as { status?: string; dueDate?: string; notes?: string };
      const changed: string[] = [];

      if (typeof input.status === 'string') {
        if (
          !['draft', 'confirmed', 'in_progress', 'ready', 'delivered', 'cancelled'].includes(
            input.status
          )
        ) {
          return fail('order_status_invalid', 'Choose a valid order status.', 422);
        }

        if (input.status !== order.status) {
          const previous = order.status;
          order.status = input.status;
          recordOrderEvent(
            active.tenant.id,
            order.id,
            'status',
            `Order moved from ${humanStatus(previous)} to ${humanStatus(input.status)}.`
          );
          record(
            active.tenant.id,
            'order.status_changed',
            { from: previous, to: input.status },
            'order',
            order.id
          );
        }

        changed.push('status');
      }

      if (typeof input.dueDate === 'string') {
        order.dueDate = input.dueDate;
        changed.push('due_date');
      }

      if (typeof input.notes === 'string') {
        order.notes = input.notes.trim();
        changed.push('notes');
      }

      order.updatedAt = nowStamp();
      record(active.tenant.id, 'order.updated', { changed }, 'order', order.id);

      return ok(presentOrder(order));
    }
  }

  const orderNoteMatch = /^\/orders\/(\d+)\/events$/.exec(path);

  if (orderNoteMatch && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const order = orderFor(active.tenant.id, Number(orderNoteMatch[1]));

    if (!order) {
      return fail('order_not_found', 'That order could not be found.', 404);
    }

    const message = String((body as { message?: string } | null)?.message ?? '').trim();

    if (!message) {
      return fail('order_note_required', 'Write the note before saving it.', 422);
    }

    recordOrderEvent(active.tenant.id, order.id, 'note', message);
    order.updatedAt = nowStamp();

    return ok(presentOrder(order));
  }

  const orderItemMatch = /^\/order-items\/(\d+)$/.exec(path);

  if (orderItemMatch && method === 'PATCH') {
    if (!active) {
      return workspaceRequired();
    }

    const item = orderItems.find(
      (line) => line.tenantId === active.tenant.id && line.id === Number(orderItemMatch[1])
    );

    if (!item) {
      return fail('order_item_not_found', 'That garment could not be found.', 404);
    }

    const input = (body ?? {}) as { status?: string; notes?: string };

    if (typeof input.status === 'string') {
      if (
        !['pending', 'cutting', 'sewing', 'fitting', 'ready', 'delivered', 'cancelled'].includes(
          input.status
        )
      ) {
        return fail('order_item_status_invalid', 'Choose a valid garment status.', 422);
      }

      if (input.status !== item.status) {
        const previous = item.status;
        item.status = input.status;
        recordOrderEvent(
          active.tenant.id,
          item.orderId,
          'item_status',
          `${item.garmentName} moved from ${humanStatus(previous)} to ${humanStatus(input.status)}.`,
          item.id
        );
        record(
          active.tenant.id,
          'order.item.status_changed',
          { orderId: item.orderId, from: previous, to: input.status },
          'order_item',
          item.id
        );
      }
    }

    if (typeof input.notes === 'string') {
      item.notes = input.notes.trim();
      recordOrderEvent(
        active.tenant.id,
        item.orderId,
        'item_updated',
        `Notes updated for ${item.garmentName}.`,
        item.id
      );
    }

    item.updatedAt = nowStamp();

    const order = orderFor(active.tenant.id, item.orderId);

    if (!order) {
      return fail('order_not_found', 'That order could not be found.', 404);
    }

    order.updatedAt = nowStamp();

    return ok(presentOrder(order));
  }

  /* Production ---------------------------------------------------------- */

  if (path === '/production/stages' && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    const includeInactive = ['1', 'true', 'yes'].includes(
      (url.searchParams.get('include_inactive') ?? '').toLowerCase()
    );

    return ok({
      stages: stagesFor(active.tenant.id)
        .filter((stage) => (includeInactive ? true : stage.isActive))
        .map(presentStage),
    });
  }

  if (path === '/production/stages' && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const input = (body ?? {}) as { name?: string; sequence?: number; isActive?: boolean };
    const name = String(input.name ?? '').trim();

    if (!name) {
      return fail('production_stage_name_required', 'Give the stage a name.', 422);
    }

    stagesFor(active.tenant.id);

    const slug = stageSlug(name);

    if (stages.some((stage) => stage.tenantId === active.tenant.id && stage.slug === slug)) {
      return fail('production_stage_slug_exists', 'A stage with that name already exists.', 409);
    }

    const stage: StoredStage = {
      id: nextStageId++,
      tenantId: active.tenant.id,
      name,
      slug,
      sequence: Number(input.sequence ?? 0),
      isActive: input.isActive !== false,
      createdAt: nowStamp(),
      updatedAt: nowStamp(),
    };

    stages.push(stage);
    record(
      active.tenant.id,
      'production.stage.created',
      { name, slug },
      'production_stage',
      stage.id
    );

    return ok(presentStage(stage), 201);
  }

  const stageMatch = /^\/production\/stages\/(\d+)$/.exec(path);

  if (stageMatch && method === 'PATCH') {
    if (!active) {
      return workspaceRequired();
    }

    const stage = stageFor(active.tenant.id, Number(stageMatch[1]));

    if (!stage) {
      return fail('production_stage_not_found', 'That production stage could not be found.', 404);
    }

    const input = (body ?? {}) as { name?: string; sequence?: number; isActive?: boolean };

    if (typeof input.name === 'string') {
      const name = input.name.trim();

      if (!name) {
        return fail('production_stage_name_required', 'Give the stage a name.', 422);
      }

      stage.name = name;
    }

    if (typeof input.sequence === 'number') {
      stage.sequence = input.sequence;
    }

    if (typeof input.isActive === 'boolean') {
      stage.isActive = input.isActive;
    }

    stage.updatedAt = nowStamp();
    record(active.tenant.id, 'production.stage.updated', {}, 'production_stage', stage.id);

    return ok(presentStage(stage));
  }

  if (path === '/production/jobs' && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    const status = url.searchParams.get('status') ?? 'all';
    const assignedTo = Number(url.searchParams.get('assigned_to') ?? 0);
    const itemFilter = Number(url.searchParams.get('order_item_id') ?? 0);
    const search = (url.searchParams.get('search') ?? '').trim().toLowerCase();
    const page = Math.max(1, Number(url.searchParams.get('page') ?? 1));
    const perPage = Math.min(100, Math.max(1, Number(url.searchParams.get('per_page') ?? 25)));

    const matches = jobs
      .filter((job) => job.tenantId === active.tenant.id)
      .map(presentJob)
      .filter((job) => (status === 'all' ? true : job.status === status))
      .filter((job) => (assignedTo > 0 ? job.assignedTo === assignedTo : true))
      .filter((job) => (itemFilter > 0 ? job.orderItemId === itemFilter : true))
      .filter((job) =>
        search === ''
          ? true
          : `${job.garmentName} ${job.orderReference} ${job.clientName}`
              .toLowerCase()
              .includes(search)
      )
      .sort((left, right) => left.dueDate.localeCompare(right.dueDate));

    const start = (page - 1) * perPage;

    return ok({
      jobs: matches.slice(start, start + perPage),
      page,
      perPage,
      total: matches.length,
      totalPages: matches.length === 0 ? 0 : Math.ceil(matches.length / perPage),
    });
  }

  if (path === '/production/jobs' && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const input = (body ?? {}) as {
      orderItemId?: number;
      assignedTo?: number;
      startDate?: string;
      dueDate?: string;
      notes?: string;
    };
    const itemId = Number(input.orderItemId ?? 0);
    const context = itemContext(itemId);

    if (!context || context.item.tenantId !== active.tenant.id) {
      return fail('order_item_not_found', 'That garment could not be found.', 404);
    }

    if (jobs.some((job) => job.tenantId === active.tenant.id && job.orderItemId === itemId)) {
      return fail('production_job_exists', 'That garment already has a production job.', 409);
    }

    const job: StoredJob = {
      id: nextJobId++,
      tenantId: active.tenant.id,
      orderItemId: itemId,
      assignedTo: Number(input.assignedTo ?? 0),
      status: 'queued',
      startDate: String(input.startDate ?? ''),
      dueDate: String(input.dueDate ?? ''),
      notes: String(input.notes ?? ''),
      createdAt: nowStamp(),
      updatedAt: nowStamp(),
    };

    jobs.push(job);
    recordOrderEvent(
      active.tenant.id,
      context.order.id,
      'production',
      `Production job opened for ${context.item.garmentName}.`,
      itemId
    );
    record(
      active.tenant.id,
      'production.job.created',
      { orderItemId: itemId },
      'production_job',
      job.id
    );

    return ok(presentJob(job), 201);
  }

  const jobMatch = /^\/production\/jobs\/(\d+)$/.exec(path);

  if (jobMatch) {
    if (!active) {
      return workspaceRequired();
    }

    const job = jobFor(active.tenant.id, Number(jobMatch[1]));

    if (!job) {
      return fail('production_job_not_found', 'That production job could not be found.', 404);
    }

    if (method === 'GET') {
      return ok(presentJob(job));
    }

    if (method === 'PATCH') {
      const input = (body ?? {}) as {
        status?: string;
        assignedTo?: number;
        startDate?: string;
        dueDate?: string;
        notes?: string;
      };

      const transitions: Record<string, string[]> = {
        queued: ['in_progress', 'blocked', 'cancelled'],
        in_progress: ['blocked', 'completed', 'cancelled'],
        blocked: ['in_progress', 'cancelled'],
        completed: [],
        cancelled: [],
      };
      const previous = job.status;

      if (typeof input.status === 'string') {
        if (
          !['queued', 'in_progress', 'blocked', 'completed', 'cancelled'].includes(input.status)
        ) {
          return fail('production_job_status_invalid', 'Choose a valid job status.', 422);
        }

        if (input.status !== previous && !(transitions[previous] ?? []).includes(input.status)) {
          return fail(
            'production_job_transition_invalid',
            'That job cannot move to the requested status.',
            409
          );
        }

        job.status = input.status;

        if (input.status === 'in_progress' && job.startDate === '') {
          job.startDate = nowStamp().slice(0, 10);
        }
      }

      if (typeof input.assignedTo === 'number') {
        job.assignedTo = input.assignedTo;
      }

      if (typeof input.startDate === 'string') {
        job.startDate = input.startDate;
      }

      if (typeof input.dueDate === 'string') {
        job.dueDate = input.dueDate;
      }

      if (typeof input.notes === 'string') {
        job.notes = input.notes.trim();
      }

      job.updatedAt = nowStamp();

      if (job.status !== previous) {
        const context = itemContext(job.orderItemId);

        if (context) {
          recordOrderEvent(
            active.tenant.id,
            context.order.id,
            'production',
            `Production for ${context.item.garmentName} moved from ${humanStatus(previous)} to ${humanStatus(job.status)}.`,
            job.orderItemId
          );
        }
      }

      record(
        active.tenant.id,
        'production.job.updated',
        { status: job.status },
        'production_job',
        job.id
      );

      return ok(presentJob(job));
    }
  }

  const jobTasksMatch = /^\/production\/jobs\/(\d+)\/tasks$/.exec(path);

  if (jobTasksMatch && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const job = jobFor(active.tenant.id, Number(jobTasksMatch[1]));

    if (!job) {
      return fail('production_job_not_found', 'That production job could not be found.', 404);
    }

    const input = (body ?? {}) as {
      stageId?: number;
      assignedTo?: number;
      dueDate?: string;
      notes?: string;
    };
    const stage = stageFor(active.tenant.id, Number(input.stageId ?? 0));

    if (!stage) {
      return fail('production_stage_not_found', 'That production stage could not be found.', 404);
    }

    if (!stage.isActive) {
      return fail(
        'production_stage_inactive',
        'That stage is switched off. Choose an active stage.',
        422
      );
    }

    const task: StoredTask = {
      id: nextTaskId++,
      tenantId: active.tenant.id,
      jobId: job.id,
      stageId: stage.id,
      assignedTo: Number(input.assignedTo ?? 0),
      status: 'pending',
      dueDate: String(input.dueDate ?? ''),
      completedAt: '',
      notes: String(input.notes ?? ''),
    };

    tasks.push(task);
    job.updatedAt = nowStamp();
    record(
      active.tenant.id,
      'production.task.created',
      { jobId: job.id },
      'production_task',
      task.id
    );

    return ok(presentJob(job), 201);
  }

  const taskMatch = /^\/production\/tasks\/(\d+)$/.exec(path);

  if (taskMatch && method === 'PATCH') {
    if (!active) {
      return workspaceRequired();
    }

    const task = tasks.find(
      (item) => item.tenantId === active.tenant.id && item.id === Number(taskMatch[1])
    );

    if (!task) {
      return fail('production_task_not_found', 'That production task could not be found.', 404);
    }

    const input = (body ?? {}) as {
      status?: string;
      assignedTo?: number;
      dueDate?: string;
      notes?: string;
    };

    if (typeof input.status === 'string') {
      if (!['pending', 'in_progress', 'done', 'cancelled'].includes(input.status)) {
        return fail('production_task_status_invalid', 'Choose a valid task status.', 422);
      }

      task.status = input.status;
      task.completedAt = input.status === 'done' ? nowStamp() : '';
    }

    if (typeof input.assignedTo === 'number') {
      task.assignedTo = input.assignedTo;
    }

    if (typeof input.dueDate === 'string') {
      task.dueDate = input.dueDate;
    }

    if (typeof input.notes === 'string') {
      task.notes = input.notes;
    }

    const job = jobFor(active.tenant.id, task.jobId);

    if (!job) {
      return fail('production_job_not_found', 'That production job could not be found.', 404);
    }

    job.updatedAt = nowStamp();

    return ok(presentJob(job));
  }

  if (path === '/production/dashboard' && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    const tenantJobs = jobs.filter((job) => job.tenantId === active.tenant.id);
    const tenantTasks = tasks.filter((task) => task.tenantId === active.tenant.id);
    const today = nowStamp().slice(0, 10);
    const activeStatuses = ['queued', 'in_progress', 'blocked'];
    const tenantChecks = checks.filter((check) => check.tenantId === active.tenant.id);
    const lowStock = materials
      .filter(
        (material) =>
          material.tenantId === active.tenant.id &&
          material.isActive &&
          material.reorderLevel > 0 &&
          material.quantityOnHand <= material.reorderLevel
      )
      .sort((left, right) => left.quantityOnHand - right.quantityOnHand)
      .slice(0, 5)
      .map((material) => presentMaterial(material));

    const unplanned = orderItems
      .filter((item) => item.tenantId === active.tenant.id)
      .filter((item) => !tenantJobs.some((job) => job.orderItemId === item.id))
      .filter((item) => {
        const order = orderFor(item.tenantId, item.orderId);
        return order !== undefined && order.status !== 'cancelled';
      })
      .slice(0, 5)
      .map((item) => {
        const order = orderFor(item.tenantId, item.orderId);
        return {
          id: item.id,
          garmentName: item.garmentName,
          garmentStatus: item.status,
          orderId: item.orderId,
          orderReference: order?.reference ?? '',
          clientName: order?.clientName ?? '',
        };
      });

    const upcoming = fittings
      .filter(
        (fitting) =>
          fitting.tenantId === active.tenant.id &&
          fitting.status === 'scheduled' &&
          fitting.scheduledAt >= nowStamp()
      )
      .sort((left, right) => left.scheduledAt.localeCompare(right.scheduledAt))
      .slice(0, 5)
      .map(presentFitting);

    const byStatus: Record<string, number> = {
      queued: 0,
      in_progress: 0,
      blocked: 0,
      completed: 0,
      cancelled: 0,
    };

    tenantJobs.forEach((job) => {
      byStatus[job.status] = (byStatus[job.status] ?? 0) + 1;
    });

    const dashboard: ProductionDashboard = {
      counts: {
        jobs: byStatus,
        jobsTotal: tenantJobs.length,
        overdueJobs: tenantJobs.filter(
          (job) => activeStatuses.includes(job.status) && job.dueDate !== '' && job.dueDate < today
        ).length,
        unassignedJobs: tenantJobs.filter(
          (job) => activeStatuses.includes(job.status) && job.assignedTo === 0
        ).length,
        openTasks: tenantTasks.filter(
          (task) => task.status === 'pending' || task.status === 'in_progress'
        ).length,
        unplannedGarments: orderItems
          .filter((item) => item.tenantId === active.tenant.id)
          .filter((item) => !tenantJobs.some((job) => job.orderItemId === item.id)).length,
        failedChecks: tenantChecks.filter((check) => check.result === 'failed').length,
        needsRework: tenantChecks.filter((check) => check.result === 'needs_rework').length,
      },
      unplannedGarments: unplanned,
      upcomingFittings: upcoming,
      recentFailures: tenantChecks
        .filter((check) => check.result !== 'passed')
        .sort((left, right) => right.checkedAt.localeCompare(left.checkedAt))
        .slice(0, 5)
        .map(presentCheck),
      lowStock,
    };

    return ok(dashboard);
  }

  /* Materials ----------------------------------------------------------- */

  if (path === '/materials' && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    const search = (url.searchParams.get('search') ?? '').trim().toLowerCase();
    const type = url.searchParams.get('type') ?? 'all';
    const lowStock = ['1', 'true', 'yes'].includes(
      (url.searchParams.get('low_stock') ?? '').toLowerCase()
    );
    const includeInactive = ['1', 'true', 'yes'].includes(
      (url.searchParams.get('include_inactive') ?? '').toLowerCase()
    );
    const page = Math.max(1, Number(url.searchParams.get('page') ?? 1));
    const perPage = Math.min(100, Math.max(1, Number(url.searchParams.get('per_page') ?? 25)));

    const matches = materials
      .filter((material) => material.tenantId === active.tenant.id)
      .filter((material) => (includeInactive ? true : material.isActive))
      .filter((material) => (type === 'all' ? true : material.materialType === type))
      .filter((material) =>
        lowStock
          ? material.reorderLevel > 0 && material.quantityOnHand <= material.reorderLevel
          : true
      )
      .filter((material) =>
        search === '' ? true : `${material.name} ${material.notes}`.toLowerCase().includes(search)
      )
      .sort((left, right) => left.name.localeCompare(right.name));

    const start = (page - 1) * perPage;

    return ok({
      materials: matches.slice(start, start + perPage).map((material) => presentMaterial(material)),
      page,
      perPage,
      total: matches.length,
      totalPages: matches.length === 0 ? 0 : Math.ceil(matches.length / perPage),
    });
  }

  if (path === '/materials' && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const input = (body ?? {}) as {
      name?: string;
      materialType?: string;
      unit?: string;
      quantityOnHand?: number | string;
      reorderLevel?: number | string;
      unitCost?: number | string;
      notes?: string;
      isActive?: boolean;
    };
    const name = String(input.name ?? '').trim();

    if (!name) {
      return fail('material_name_required', 'Give the material a name.', 422);
    }

    if (!['fabric', 'accessory', 'consumable'].includes(String(input.materialType ?? 'fabric'))) {
      return fail('material_type_invalid', 'Choose fabric, accessory or consumable.', 422);
    }

    const opening = Number(input.quantityOnHand ?? 0);
    const material: StoredMaterial = {
      id: nextMaterialId++,
      tenantId: active.tenant.id,
      name,
      materialType: String(input.materialType ?? 'fabric'),
      unit: String(input.unit ?? 'metre'),
      quantityOnHand: Number.isFinite(opening) && opening > 0 ? money(opening) : 0,
      reorderLevel: Number(input.reorderLevel ?? 0),
      unitCost: Number(input.unitCost ?? 0),
      notes: String(input.notes ?? ''),
      isActive: input.isActive !== false,
      createdAt: nowStamp(),
      updatedAt: nowStamp(),
    };

    materials.push(material);

    if (material.quantityOnHand > 0) {
      movements.push({
        id: nextMovementId++,
        tenantId: active.tenant.id,
        materialId: material.id,
        type: 'in',
        quantity: material.quantityOnHand,
        referenceType: 'opening',
        referenceId: 0,
        note: 'Opening stock',
        actorId: CURRENT_USER,
        actorName: 'Ada Obi',
        createdAt: nowStamp(),
      });
    }

    record(active.tenant.id, 'material.created', { name }, 'material', material.id);

    return ok(presentMaterial(material, true), 201);
  }

  const materialMatch = /^\/materials\/(\d+)$/.exec(path);

  if (materialMatch) {
    if (!active) {
      return workspaceRequired();
    }

    const material = materialFor(active.tenant.id, Number(materialMatch[1]));

    if (!material) {
      return fail('material_not_found', 'That material could not be found.', 404);
    }

    if (method === 'GET') {
      return ok(presentMaterial(material, true));
    }

    if (method === 'PATCH') {
      const input = (body ?? {}) as {
        name?: string;
        materialType?: string;
        unit?: string;
        reorderLevel?: number | string;
        unitCost?: number | string;
        notes?: string;
        isActive?: boolean;
      };

      if (typeof input.name === 'string') {
        if (input.name.trim() === '') {
          return fail('material_name_required', 'Give the material a name.', 422);
        }

        material.name = input.name.trim();
      }

      if (typeof input.materialType === 'string') {
        material.materialType = input.materialType;
      }

      if (typeof input.unit === 'string') {
        material.unit = input.unit;
      }

      if (input.reorderLevel !== undefined) {
        material.reorderLevel = Number(input.reorderLevel);
      }

      if (input.unitCost !== undefined) {
        material.unitCost = Number(input.unitCost);
      }

      if (typeof input.notes === 'string') {
        material.notes = input.notes;
      }

      if (typeof input.isActive === 'boolean') {
        material.isActive = input.isActive;
      }

      material.updatedAt = nowStamp();
      record(active.tenant.id, 'material.updated', {}, 'material', material.id);

      return ok(presentMaterial(material, true));
    }
  }

  const movementMatch = /^\/materials\/(\d+)\/movements$/.exec(path);

  if (movementMatch && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const material = materialFor(active.tenant.id, Number(movementMatch[1]));

    if (!material) {
      return fail('material_not_found', 'That material could not be found.', 404);
    }

    const input = (body ?? {}) as { type?: string; quantity?: number | string; note?: string };
    const type = String(input.type ?? '');

    if (!['in', 'out', 'adjustment'].includes(type)) {
      return fail(
        'material_movement_type_invalid',
        'Choose a stock in, stock out or adjustment movement.',
        422
      );
    }

    const quantity = Number(input.quantity);

    if (!Number.isFinite(quantity) || quantity === 0 || (type !== 'adjustment' && quantity <= 0)) {
      return fail('material_movement_quantity_invalid', 'Enter a quantity greater than zero.', 422);
    }

    const delta = type === 'out' ? -quantity : quantity;

    if (material.quantityOnHand + delta < 0) {
      return fail(
        'material_insufficient_stock',
        `Only ${material.quantityOnHand} ${material.unit} of ${material.name} is on hand.`,
        409
      );
    }

    material.quantityOnHand = money(material.quantityOnHand + delta);
    material.updatedAt = nowStamp();

    const movement: StoredMovement = {
      id: nextMovementId++,
      tenantId: active.tenant.id,
      materialId: material.id,
      type,
      quantity: delta,
      referenceType: 'manual',
      referenceId: 0,
      note: String(input.note ?? ''),
      actorId: CURRENT_USER,
      actorName: 'Ada Obi',
      createdAt: nowStamp(),
    };

    movements.push(movement);
    record(
      active.tenant.id,
      'material.stock_changed',
      { type, quantity: delta },
      'material',
      material.id
    );

    return ok(presentMaterial(material, true));
  }

  const itemMaterialsMatch = /^\/order-items\/(\d+)\/materials$/.exec(path);

  if (itemMaterialsMatch) {
    if (!active) {
      return workspaceRequired();
    }

    const itemId = Number(itemMaterialsMatch[1]);
    const context = itemContext(itemId);

    if (!context || context.item.tenantId !== active.tenant.id) {
      return fail('order_item_not_found', 'That garment could not be found.', 404);
    }

    if (method === 'GET') {
      return ok(allocationResponse(active.tenant.id, itemId));
    }

    if (method === 'POST') {
      const input = (body ?? {}) as {
        materialId?: number;
        quantity?: number | string;
        source?: string;
        notes?: string;
      };
      const material = materialFor(active.tenant.id, Number(input.materialId ?? 0));

      if (!material) {
        return fail('material_not_found', 'That material could not be found.', 404);
      }

      const quantity = Number(input.quantity);

      if (!Number.isFinite(quantity) || quantity <= 0) {
        return fail(
          'material_allocation_quantity_invalid',
          'Enter a quantity greater than zero.',
          422
        );
      }

      const source = String(input.source ?? '');

      if (!['customer_supplied', 'designer_supplied', 'shared_supply'].includes(source)) {
        return fail(
          'material_allocation_source_invalid',
          'Say whether the studio, the customer or both supplied this material.',
          422
        );
      }

      const consumesStock = source === 'designer_supplied';

      if (consumesStock && material.quantityOnHand < quantity) {
        return fail(
          'material_insufficient_stock',
          `Only ${material.quantityOnHand} ${material.unit} of ${material.name} is on hand.`,
          409
        );
      }

      const allocation: StoredAllocation = {
        id: nextAllocationId++,
        tenantId: active.tenant.id,
        orderItemId: itemId,
        materialId: material.id,
        materialName: material.name,
        unit: material.unit,
        quantity: money(quantity),
        source,
        notes: String(input.notes ?? ''),
        createdBy: CURRENT_USER,
        createdByName: 'Ada Obi',
        createdAt: nowStamp(),
      };

      allocations.push(allocation);

      if (consumesStock) {
        material.quantityOnHand = money(material.quantityOnHand - quantity);
        movements.push({
          id: nextMovementId++,
          tenantId: active.tenant.id,
          materialId: material.id,
          type: 'allocation',
          quantity: -quantity,
          referenceType: 'order_item',
          referenceId: itemId,
          note: `Allocated ${material.name}`,
          actorId: CURRENT_USER,
          actorName: 'Ada Obi',
          createdAt: nowStamp(),
        });
      }

      record(
        active.tenant.id,
        'material.allocated',
        { materialId: material.id, quantity, source },
        'order_item',
        itemId
      );

      return ok(allocationResponse(active.tenant.id, itemId), 201);
    }
  }

  const allocationMatch = /^\/material-allocations\/(\d+)$/.exec(path);

  if (allocationMatch && method === 'DELETE') {
    if (!active) {
      return workspaceRequired();
    }

    const allocation = allocations.find(
      (item) => item.tenantId === active.tenant.id && item.id === Number(allocationMatch[1])
    );

    if (!allocation) {
      return fail(
        'material_allocation_not_found',
        'That material allocation could not be found.',
        404
      );
    }

    if (allocation.source === 'designer_supplied') {
      const material = materialFor(active.tenant.id, allocation.materialId);

      if (material) {
        material.quantityOnHand = money(material.quantityOnHand + allocation.quantity);
        movements.push({
          id: nextMovementId++,
          tenantId: active.tenant.id,
          materialId: material.id,
          type: 'return',
          quantity: allocation.quantity,
          referenceType: 'order_item',
          referenceId: allocation.orderItemId,
          note: `Released allocation of ${material.name}`,
          actorId: CURRENT_USER,
          actorName: 'Ada Obi',
          createdAt: nowStamp(),
        });
      }
    }

    const index = allocations.indexOf(allocation);
    allocations.splice(index, 1);
    record(
      active.tenant.id,
      'material.released',
      { materialId: allocation.materialId, quantity: allocation.quantity },
      'order_item',
      allocation.orderItemId
    );

    return ok(allocationResponse(active.tenant.id, allocation.orderItemId));
  }

  /* Fittings, alterations and quality ----------------------------------- */

  if (path === '/fittings' && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    const status = url.searchParams.get('status') ?? 'all';
    const upcoming = ['1', 'true', 'yes'].includes(
      (url.searchParams.get('upcoming') ?? '').toLowerCase()
    );
    const itemFilter = Number(url.searchParams.get('order_item_id') ?? 0);
    const page = Math.max(1, Number(url.searchParams.get('page') ?? 1));
    const perPage = Math.min(100, Math.max(1, Number(url.searchParams.get('per_page') ?? 25)));

    const matches = fittings
      .filter((fitting) => fitting.tenantId === active.tenant.id)
      .filter((fitting) =>
        upcoming ? fitting.status === 'scheduled' && fitting.scheduledAt >= nowStamp() : true
      )
      .filter((fitting) => (status === 'all' ? true : fitting.status === status))
      .filter((fitting) => (itemFilter > 0 ? fitting.orderItemId === itemFilter : true))
      .sort((left, right) => left.scheduledAt.localeCompare(right.scheduledAt));

    const start = (page - 1) * perPage;

    return ok({
      fittings: matches.slice(start, start + perPage).map(presentFitting),
      page,
      perPage,
      total: matches.length,
      totalPages: matches.length === 0 ? 0 : Math.ceil(matches.length / perPage),
    });
  }

  if (path === '/fittings' && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const input = (body ?? {}) as {
      orderItemId?: number;
      scheduledAt?: string;
      assignedTo?: number;
      location?: string;
      notes?: string;
    };
    const itemId = Number(input.orderItemId ?? 0);
    const context = itemContext(itemId);

    if (!context || context.item.tenantId !== active.tenant.id) {
      return fail('order_item_not_found', 'That garment could not be found.', 404);
    }

    const scheduledAt = String(input.scheduledAt ?? '').trim();

    if (!scheduledAt) {
      return fail('fitting_scheduled_at_invalid', 'Choose the date and time of the fitting.', 422);
    }

    const fitting: StoredFitting = {
      id: nextFittingId++,
      tenantId: active.tenant.id,
      orderItemId: itemId,
      assignedTo: Number(input.assignedTo ?? 0),
      scheduledAt: scheduledAt.length === 16 ? `${scheduledAt}:00` : scheduledAt,
      status: 'scheduled',
      location: String(input.location ?? ''),
      notes: String(input.notes ?? ''),
      outcome: '',
      createdAt: nowStamp(),
      updatedAt: nowStamp(),
    };

    fittings.push(fitting);
    record(active.tenant.id, 'fitting.created', { orderItemId: itemId }, 'fitting', fitting.id);

    return ok(presentFitting(fitting), 201);
  }

  const fittingMatch = /^\/fittings\/(\d+)$/.exec(path);

  if (fittingMatch) {
    if (!active) {
      return workspaceRequired();
    }

    const fitting = fittings.find(
      (item) => item.tenantId === active.tenant.id && item.id === Number(fittingMatch[1])
    );

    if (!fitting) {
      return fail('fitting_not_found', 'That fitting could not be found.', 404);
    }

    if (method === 'GET') {
      return ok(presentFitting(fitting));
    }

    if (method === 'PATCH') {
      const input = (body ?? {}) as {
        status?: string;
        scheduledAt?: string;
        assignedTo?: number;
        location?: string;
        notes?: string;
        outcome?: string;
      };
      const transitions: Record<string, string[]> = {
        scheduled: ['completed', 'missed', 'cancelled'],
        missed: ['scheduled', 'cancelled'],
        cancelled: ['scheduled'],
        completed: [],
      };

      if (typeof input.status === 'string') {
        if (!['scheduled', 'completed', 'missed', 'cancelled'].includes(input.status)) {
          return fail('fitting_status_invalid', 'Choose a valid fitting status.', 422);
        }

        if (
          input.status !== fitting.status &&
          !(transitions[fitting.status] ?? []).includes(input.status)
        ) {
          return fail(
            'fitting_transition_invalid',
            'That fitting cannot move to the requested status.',
            409
          );
        }

        fitting.status = input.status;
      }

      if (typeof input.scheduledAt === 'string') {
        fitting.scheduledAt = input.scheduledAt.replace('T', ' ');
      }

      if (typeof input.assignedTo === 'number') {
        fitting.assignedTo = input.assignedTo;
      }

      if (typeof input.location === 'string') {
        fitting.location = input.location;
      }

      if (typeof input.notes === 'string') {
        fitting.notes = input.notes;
      }

      if (typeof input.outcome === 'string') {
        fitting.outcome = input.outcome;
      }

      fitting.updatedAt = nowStamp();
      record(
        active.tenant.id,
        'fitting.updated',
        { status: fitting.status },
        'fitting',
        fitting.id
      );

      return ok(presentFitting(fitting));
    }
  }

  if (path === '/alterations' && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    const status = url.searchParams.get('status') ?? 'all';
    const itemFilter = Number(url.searchParams.get('order_item_id') ?? 0);
    const page = Math.max(1, Number(url.searchParams.get('page') ?? 1));
    const perPage = Math.min(100, Math.max(1, Number(url.searchParams.get('per_page') ?? 25)));

    const matches = alterations
      .filter((alteration) => alteration.tenantId === active.tenant.id)
      .filter((alteration) => (status === 'all' ? true : alteration.status === status))
      .filter((alteration) => (itemFilter > 0 ? alteration.orderItemId === itemFilter : true))
      .sort((left, right) => right.id - left.id);

    const start = (page - 1) * perPage;

    return ok({
      alterations: matches.slice(start, start + perPage).map(presentAlteration),
      page,
      perPage,
      total: matches.length,
      totalPages: matches.length === 0 ? 0 : Math.ceil(matches.length / perPage),
    });
  }

  if (path === '/alterations' && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const input = (body ?? {}) as {
      fittingId?: number;
      orderItemId?: number;
      description?: string;
      dueDate?: string;
    };
    const fittingId = Number(input.fittingId ?? 0);
    let itemId = Number(input.orderItemId ?? 0);

    if (fittingId > 0) {
      const fitting = fittings.find(
        (item) => item.tenantId === active.tenant.id && item.id === fittingId
      );

      if (!fitting) {
        return fail('fitting_not_found', 'That fitting could not be found.', 404);
      }

      if (itemId > 0 && itemId !== fitting.orderItemId) {
        return fail(
          'alteration_garment_mismatch',
          'That fitting belongs to a different garment.',
          422
        );
      }

      itemId = fitting.orderItemId;
    }

    const context = itemContext(itemId);

    if (!context || context.item.tenantId !== active.tenant.id) {
      return fail('alteration_garment_required', 'Choose the garment this alteration is for.', 422);
    }

    const description = String(input.description ?? '').trim();

    if (!description) {
      return fail('alteration_description_required', 'Describe the alteration needed.', 422);
    }

    const alteration: StoredAlteration = {
      id: nextAlterationId++,
      tenantId: active.tenant.id,
      orderItemId: itemId,
      fittingId: Math.max(0, fittingId),
      description,
      status: 'open',
      dueDate: String(input.dueDate ?? ''),
      completedAt: '',
      createdAt: nowStamp(),
      updatedAt: nowStamp(),
    };

    alterations.push(alteration);
    record(
      active.tenant.id,
      'alteration.created',
      { orderItemId: itemId },
      'alteration',
      alteration.id
    );

    return ok(presentAlteration(alteration), 201);
  }

  const alterationMatch = /^\/alterations\/(\d+)$/.exec(path);

  if (alterationMatch && method === 'PATCH') {
    if (!active) {
      return workspaceRequired();
    }

    const alteration = alterations.find(
      (item) => item.tenantId === active.tenant.id && item.id === Number(alterationMatch[1])
    );

    if (!alteration) {
      return fail('alteration_not_found', 'That alteration could not be found.', 404);
    }

    const input = (body ?? {}) as { status?: string; description?: string; dueDate?: string };

    if (typeof input.status === 'string') {
      if (!['open', 'in_progress', 'completed', 'cancelled'].includes(input.status)) {
        return fail('alteration_status_invalid', 'Choose a valid alteration status.', 422);
      }

      alteration.status = input.status;
      alteration.completedAt = input.status === 'completed' ? nowStamp() : '';
    }

    if (typeof input.description === 'string') {
      if (input.description.trim() === '') {
        return fail('alteration_description_required', 'Describe the alteration needed.', 422);
      }

      alteration.description = input.description.trim();
    }

    if (typeof input.dueDate === 'string') {
      alteration.dueDate = input.dueDate;
    }

    alteration.updatedAt = nowStamp();
    record(
      active.tenant.id,
      'alteration.updated',
      { status: alteration.status },
      'alteration',
      alteration.id
    );

    return ok(presentAlteration(alteration));
  }

  if (path === '/quality-checks' && method === 'GET') {
    if (!active) {
      return workspaceRequired();
    }

    const result = url.searchParams.get('result') ?? 'all';
    const itemFilter = Number(url.searchParams.get('order_item_id') ?? 0);
    const page = Math.max(1, Number(url.searchParams.get('page') ?? 1));
    const perPage = Math.min(100, Math.max(1, Number(url.searchParams.get('per_page') ?? 25)));

    const matches = checks
      .filter((check) => check.tenantId === active.tenant.id)
      .filter((check) => (result === 'all' ? true : check.result === result))
      .filter((check) => (itemFilter > 0 ? check.orderItemId === itemFilter : true))
      .sort((left, right) => right.checkedAt.localeCompare(left.checkedAt));

    const start = (page - 1) * perPage;

    return ok({
      checks: matches.slice(start, start + perPage).map(presentCheck),
      page,
      perPage,
      total: matches.length,
      totalPages: matches.length === 0 ? 0 : Math.ceil(matches.length / perPage),
    });
  }

  if (path === '/quality-checks' && method === 'POST') {
    if (!active) {
      return workspaceRequired();
    }

    const input = (body ?? {}) as {
      orderItemId?: number;
      result?: string;
      notes?: string;
      checkedAt?: string;
    };
    const itemId = Number(input.orderItemId ?? 0);
    const context = itemContext(itemId);

    if (!context || context.item.tenantId !== active.tenant.id) {
      return fail('order_item_not_found', 'That garment could not be found.', 404);
    }

    const result = String(input.result ?? '');

    if (!['passed', 'failed', 'needs_rework'].includes(result)) {
      return fail('quality_result_invalid', 'Choose passed, failed or needs rework.', 422);
    }

    const check: StoredCheck = {
      id: nextCheckId++,
      tenantId: active.tenant.id,
      orderItemId: itemId,
      checkedBy: CURRENT_USER,
      result,
      notes: String(input.notes ?? ''),
      checkedAt: String(input.checkedAt ?? '') || nowStamp(),
      createdAt: nowStamp(),
    };

    checks.push(check);
    record(active.tenant.id, 'quality.check.recorded', { result }, 'quality_check', check.id);

    if (result !== 'passed') {
      recordOrderEvent(
        active.tenant.id,
        context.order.id,
        'quality',
        `Quality check for ${context.item.garmentName}: ${humanStatus(result)}.`,
        itemId
      );
    }

    return ok(presentCheck(check), 201);
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
