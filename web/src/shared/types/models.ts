import type { Permission } from '@/shared/constants/permissions'

export interface Role {
  id: number
  name: string
  description: string | null
  permissions?: Permission[]
}

export interface User {
  id: string
  name: string
  email: string
  phone: string | null
  document: string | null
  is_master: boolean
  roles?: Role[]
  created_at: string | null
  updated_at: string | null
}

export interface Tenant {
  id: string
  name: string
  document: string
  email: string
  phone: string | null
  domain: string
  is_umbrella?: boolean
  users_count?: number
  subscription?: Subscription | null
  created_at: string | null
  updated_at: string | null
}

export interface AvailableTenant {
  id: string
  name: string
  is_home?: boolean
  is_umbrella?: boolean
}

export interface ApiToken {
  id: number
  name: string
  permissions: string[] | null
  last_used_at: string | null
  expires_at: string | null
  created_at: string | null
}

export interface Webhook {
  id: number
  name: string
  url: string
  method: string
  event: string
  headers: Record<string, string> | null
  query_params: Record<string, string> | null
  body_template: Record<string, unknown> | null
  is_active: boolean
  description: string | null
  created_at: string | null
  updated_at: string | null
}

export interface WebhookLog {
  id: number
  status_code: number | null
  response_body: string | null
  request_payload: Record<string, unknown> | null
  error_message: string | null
  duration_ms: number | null
  created_at: string | null
}

export interface AuditLog {
  id: number
  action: string
  entity_type: string | null
  entity_id: string | null
  details: Record<string, unknown> | null
  ip: string | null
  user: { id: string; name: string; email: string } | null
  created_at: string | null
}

export interface Session {
  user: User
  tenant: Tenant
  roles: Role[]
  permissions: Permission[]
  is_master: boolean
  available_tenants: AvailableTenant[]
}

export interface Plan {
  id: string
  name: string
  description: string | null
  price: string
  recurrence_value: number
  recurrence_unit: 'days' | 'weeks' | 'months' | 'years'
  free_trial_days: number
  trial_days?: number
  is_trial?: boolean
  requires_immediate_payment?: boolean
  active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface SubscriptionEvent {
  id: number
  event: string
  payload: Record<string, unknown> | null
  created_at: string | null
}

export interface Subscription {
  id: string
  status: 'ACTIVE' | 'TRIALING' | 'PAST_DUE' | 'SUSPENDED' | 'CANCELLED'
  payment_gateway: string | null
  started_at: string | null
  trial_ends_at: string | null
  last_billed_at: string | null
  next_billing_at: string | null
  cancelled_at: string | null
  is_complimentary?: boolean
  is_complimentary_active?: boolean
  complimentary_ends_at?: string | null
  plan?: Plan
  events?: SubscriptionEvent[]
  created_at: string | null
  updated_at: string | null
}

export interface PaymentGatewayOption {
  key: string
  label: string
  payment_method: string
}

export interface Invoice {
  id: string
  gateway: string | null
  amount: string
  status: 'PENDING' | 'PROCESSING' | 'PAID' | 'EXPIRED' | 'FAILED' | 'CANCELLED'
  payment_method: 'pix' | 'credit_card' | 'boleto' | null
  external_id: string | null
  pix_code: string | null
  pix_qrcode: string | null
  invoice_url?: string | null
  awaiting_payment_method?: boolean
  due_date: string | null
  paid_at: string | null
  expires_at: string | null
  subscription?: Subscription
  created_at: string | null
  updated_at: string | null
}

export interface ConversationSummary {
  id: string
  title: string
  message_count: number | null
  last_message: string | null
  created_at: string | null
  updated_at: string | null
}

export interface AssistantMessage {
  id: string
  role: 'user' | 'assistant' | 'tool' | 'system'
  content: string
  tool_calls: Array<{ id: string; name: string; arguments: Record<string, unknown> }> | null
  tool_results: Array<{ id: string; name: string }> | null
  created_at: string | null
}

export interface Conversation {
  id: string
  title: string
  messages: AssistantMessage[]
  created_at: string | null
  updated_at: string | null
}

export interface Client {
  id: string
  name: string
  legal_name: string | null
  trade_name: string | null
  document: string
  state_registration: string | null
  email: string | null
  financial_email: string | null
  phone: string | null
  street: string | null
  number: string | null
  complement: string | null
  neighborhood: string | null
  city: string | null
  state: string | null
  zip: string | null
  plan_id: string | null
  plan?: Pick<FinancePlan, 'id' | 'name' | 'amount_cents' | 'periodicity'> | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface ClientOrderVehicle {
  id: string
  plate: string
  brand: string | null
  model: string | null
}

export interface ClientOrderItem {
  id: number
  service_id: string
  service_name: string
  unit_amount_cents: number
  unit_amount: string
  quantity: number
  line_total_cents: number
  line_total: string
  vehicles: ClientOrderVehicle[]
}

export interface ClientOrder {
  id: string
  client_id: string
  due_day: number
  periodicity: BillingPeriodicity
  periodicity_label?: string
  total_cents: number
  total: string
  subscription_id: string | null
  items: ClientOrderItem[]
  created_at: string | null
  updated_at: string | null
}

export interface Driver {
  id: string
  client_id: string
  client?: Client
  name: string
  document: string | null
  phone: string | null
  email: string | null
  cnh_number: string | null
  cnh_expires_at: string | null
  notes: string | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface CatalogService {
  id: string
  name: string
  amount_cents: number
  amount: string
  created_at: string | null
  updated_at: string | null
}

export interface Contract {
  id: string
  name: string
  body: string
  created_at: string | null
  updated_at: string | null
}

export interface ClientContract {
  id: string
  contract_id: string | null
  contract_name: string | null
  valid_until: string | null
  body: string
  created_at: string | null
  updated_at: string | null
}

export interface Equipment {
  id: string
  vehicle_id: string | null
  vehicle?: Vehicle
  imei: string
  model: string | null
  iccid: string | null
  carrier: string | null
  is_active: boolean
  is_assigned: boolean
  created_at: string | null
  updated_at: string | null
}

export interface Vehicle {
  id: string
  client_id: string
  client?: Client
  equipment?: Equipment | null
  plate: string
  chassis: string | null
  renavam: string | null
  brand: string | null
  model: string | null
  color: string | null
  year: number | null
  transmission: VehicleTransmission | null
  odometer: number | null
  average_consumption: number | null
  tank_capacity: number | null
  crlv_file: string | null
  crlv_file_url: string | null
  fipe_code: string | null
  fipe_model_year: string | null
  fipe_fuel: string | null
  fipe_reference_month: string | null
  fipe_value: string | null
  fipe_model: string | null
  fipe_brand: string | null
  fipe_score: number | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export type VehicleTransmission = 'manual' | 'automatic' | 'automated'

export interface EquipmentAssignmentEvent {
  id: string
  vehicle_id: string
  vehicle?: Vehicle
  equipment_id: string
  equipment?: Equipment
  previous_equipment_id: string | null
  previous_equipment?: Equipment | null
  event: 'installation' | 'removal' | 'swap'
  occurred_at: string
  notes: string | null
  created_at: string | null
}

export interface DeviceAlarm {
  code: string
  label: string
  severity: AlertSeverity
}

export interface GpsPosition {
  id: string
  vehicle_id?: string | null
  latitude: number
  longitude: number
  recorded_at: string
  speed: number | null
  ignition: boolean | null
  battery: number | null
  heading: number | null
  altitude: number | null
  motion?: boolean | null
  odometer?: number | null
  charging?: boolean | null
  protocol?: string | null
  alarms?: DeviceAlarm[]
}

export interface TrackingLiveVehicle {
  id: string
  plate: string
  brand: string | null
  model: string | null
  color: string | null
  year: number | null
  client_id: string | null
  client?: Client | null
  equipment?: Equipment | null
  online: boolean
  position: GpsPosition | null
}

export interface TrackingGatewayStatus {
  enabled: boolean
  configured: boolean
  base_url: string | null
}

export interface GeofencePoint {
  latitude: number
  longitude: number
}

export interface Geofence {
  id: string
  client_id: string | null
  client?: Client | null
  name: string
  description: string | null
  type: 'circle' | 'polygon'
  is_active: boolean
  center_latitude: number | null
  center_longitude: number | null
  radius_meters: number | null
  geometry: GeofencePoint[] | null
  bbox?: {
    min_lat: number | null
    max_lat: number | null
    min_lng: number | null
    max_lng: number | null
  }
  events_count?: number
  created_at: string | null
  updated_at: string | null
}

export interface GeofenceEvent {
  id: string
  type: 'entry' | 'exit'
  latitude: number
  longitude: number
  speed: number | null
  recorded_at: string | null
  processed_at: string | null
  meta?: Record<string, unknown> | null
  client_id?: string | null
  client?: Client | null
  vehicle_id?: string | null
  vehicle?: Vehicle | null
  geofence_id?: string | null
  geofence?: Geofence | null
  created_at: string | null
}

export interface PoiCategory {
  id: string
  name: string
  slug: string
  color: string | null
  is_active: boolean
  sort_order: number
}

export interface Poi {
  id: string
  client_id: string | null
  client?: Client | null
  category_id: string | null
  category?: PoiCategory | null
  name: string
  description: string | null
  latitude: number
  longitude: number
  address: string | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export type AlertType =
  | 'speed'
  | 'ignition_on'
  | 'ignition_off'
  | 'sos'
  | 'offline'
  | 'online'
  | 'battery'
  | 'jamming'
  | 'device_alarm'

export type AlertSeverity = 'low' | 'medium' | 'high' | 'critical'
export type AlertStatus = 'open' | 'acknowledged' | 'resolved'

export interface Alert {
  id: string
  type: AlertType
  type_label?: string
  severity: AlertSeverity
  status: AlertStatus
  title: string
  description: string | null
  latitude: number | null
  longitude: number | null
  speed: number | null
  speed_kmh: number | null
  meta?: Record<string, unknown> | null
  occurred_at: string | null
  acknowledged_at: string | null
  resolved_at: string | null
  vehicle_id?: string | null
  vehicle?: Vehicle | null
  client_id?: string | null
  client?: Client | null
  created_at: string | null
}

export interface AlertConfig {
  id: string
  name: string | null
  type: AlertType
  type_label?: string
  is_enabled: boolean
  notify_in_app: boolean
  notify_email: boolean
  settings: {
    speed_limit_kmh?: number
    min_duration_seconds?: number
    offline_minutes?: number
    battery_threshold?: number
  }
  client_id?: string | null
  client?: Pick<Client, 'id' | 'name'> | null
  vehicle_id?: string | null
  vehicle?: Pick<Vehicle, 'id' | 'plate'> | null
  scope: 'all' | 'client' | 'vehicle'
  created_at?: string | null
  updated_at: string | null
}

export interface AlertDashboardStats {
  totals: { today: number; week: number; month: number }
  by_type: Record<string, number>
  critical_open: Alert[]
}

export interface AppNotification {
  id: string
  type: string
  title: string
  body: string | null
  data?: Record<string, unknown> | null
  read_at: string | null
  alert?: Alert | null
  created_at: string | null
}

export type ServiceOrderType = 'installation' | 'maintenance' | 'removal'
export type ServiceOrderStatus = 'open' | 'in_progress' | 'completed' | 'cancelled'
export type ServiceOrderPriority = 'low' | 'normal' | 'high' | 'urgent'

export interface ServiceOrderHistory {
  id: string
  action: string
  field: string | null
  old_value: string | null
  new_value: string | null
  meta?: Record<string, unknown> | null
  user?: Pick<User, 'id' | 'name'> | null
  created_at: string | null
}

export interface ServiceOrder {
  id: string
  number: number
  code: string
  type: ServiceOrderType
  type_label?: string
  status: ServiceOrderStatus
  status_label?: string
  priority: ServiceOrderPriority
  priority_label?: string
  client_id?: string | null
  client?: Pick<Client, 'id' | 'name'> | null
  vehicle_id?: string | null
  vehicle?: Pick<Vehicle, 'id' | 'plate' | 'brand' | 'model'> | null
  equipment_id?: string | null
  equipment?: Pick<Equipment, 'id' | 'imei' | 'model'> | null
  technician_id?: string | null
  technician?: Pick<User, 'id' | 'name'> | null
  scheduled_start_at: string | null
  scheduled_end_at: string | null
  description: string | null
  notes: string | null
  execution_notes: string | null
  cancellation_reason: string | null
  created_by?: Pick<User, 'id' | 'name'> | null
  completed_by?: Pick<User, 'id' | 'name'> | null
  cancelled_by?: Pick<User, 'id' | 'name'> | null
  completed_at: string | null
  cancelled_at: string | null
  created_at: string | null
  updated_at: string | null
  histories?: ServiceOrderHistory[]
}

export type ServiceOrderKanban = Record<ServiceOrderStatus, ServiceOrder[]>

export type BillingPeriodicity = 'monthly' | 'bimonthly' | 'quarterly' | 'semiannual' | 'annual'
export type FinanceSubscriptionStatus = 'active' | 'past_due' | 'suspended' | 'cancelled'
export type FinanceBillingStatus =
  | 'pending'
  | 'awaiting_payment'
  | 'paid'
  | 'overdue'
  | 'cancelled'
  | 'refunded'
export type FinancePaymentMethod = 'pix' | 'boleto' | 'credit_card'

export interface GatewayCredentialOption {
  value: string
  label: string
}

export interface GatewayCredentialField {
  name: string
  label: string
  type: string
  required?: boolean
  secret?: boolean
  hint?: string
  options?: GatewayCredentialOption[]
}

export interface PaymentGatewayCatalogItem {
  key: string
  label: string
  ready: boolean
}

export interface PaymentGatewayConfig {
  gateway: string
  label: string
  is_active: boolean
  is_ready: boolean
  webhook_url: string
  tenant_uuid: string | null
  credential_schema: GatewayCredentialField[]
  credentials: Record<string, unknown>
  gateways: PaymentGatewayCatalogItem[]
}

export interface VehicleDataProviderCatalogItem {
  key: string
  label: string
}

export interface VehicleDataConfig {
  provider: string
  label: string
  is_active: boolean
  is_ready: boolean
  credential_schema: GatewayCredentialField[]
  credentials: Record<string, unknown>
  providers: VehicleDataProviderCatalogItem[]
}

export interface VehicleData {
  plate: string
  brand: string | null
  model: string | null
  submodel: string | null
  version: string | null
  year: number | null
  model_year: number | null
  color: string | null
  chassis: string | null
  fuel: string | null
  transmission: string | null
  segment: string | null
  municipality: string | null
  uf: string | null
  situation: string | null
  fipe: VehicleFipe | null
  extra: Record<string, unknown> | null
}

export interface VehicleFipe {
  code: string | null
  model_year: string | null
  fuel: string | null
  reference_month: string | null
  value: string | null
  model: string | null
  brand: string | null
  score: number | null
}

export interface FinancePlan {
  id: string
  name: string
  description: string | null
  amount_cents: number
  amount: string
  periodicity: BillingPeriodicity
  periodicity_label?: string
  device_limit: number | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface FinanceSubscription {
  id: string
  status: FinanceSubscriptionStatus
  status_label?: string
  client_id: string | null
  client?: Pick<Client, 'id' | 'name'> | null
  plan_id: string | null
  plan?: Pick<FinancePlan, 'id' | 'name' | 'amount_cents' | 'periodicity'> | null
  plan_name: string
  plan_price_cents: number
  plan_periodicity: BillingPeriodicity
  plan_periodicity_label?: string
  due_day: number
  block_on_overdue: boolean
  block_after_days: number
  next_billing_at: string | null
  last_billed_at: string | null
  started_at: string | null
  gateway_subscription_id: string | null
  cancelled_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface FinanceBilling {
  id: string
  number: number
  code: string
  status: FinanceBillingStatus
  status_label?: string
  payment_method: FinancePaymentMethod | null
  payment_method_label?: string | null
  client_id: string | null
  client?: Pick<Client, 'id' | 'name'> | null
  subscription_id: string | null
  amount_cents: number
  amount: string
  discount_cents: number
  fine_cents: number
  interest_cents: number
  total_cents: number
  total: string
  paid_amount_cents: number | null
  due_at: string
  paid_at: string | null
  cancelled_at: string | null
  invoice_url: string | null
  bank_slip_url: string | null
  pix_qr_code: string | null
  pix_copy_paste: string | null
  description: string | null
  payment_gateway: string | null
  gateway_payment_id: string | null
  created_at: string | null
  updated_at: string | null
}

export interface FinanceClientOverview {
  plan: FinancePlan | null
  subscription: FinanceSubscription | null
  open_billing: FinanceBilling | null
  billings: FinanceBilling[]
}

export interface FinanceDashboardMetrics {
  mrr_cents: number
  mrr: string
  arr_cents: number
  arr: string
  active_clients: number
  active_subscriptions: number
  delinquent_clients: number
  month_revenue_received_cents: number
  month_revenue_received: string
  month_expected_cents: number
  month_expected: string
  open_amount_cents: number
  open_amount: string
}

export type FinanceReportType =
  | 'billings'
  | 'delinquency'
  | 'receipts'
  | 'subscriptions'
  | 'blocked_clients'

export interface FinanceReportResult {
  type: FinanceReportType | string
  rows: Array<Record<string, unknown>>
}

