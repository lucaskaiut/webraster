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
  document: string
  email: string | null
  phone: string | null
  street: string | null
  number: string | null
  complement: string | null
  neighborhood: string | null
  city: string | null
  state: string | null
  zip: string | null
  is_active: boolean
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
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

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
