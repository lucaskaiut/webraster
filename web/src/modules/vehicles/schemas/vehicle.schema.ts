import { z } from 'zod'
import { ALERT_TYPE_VALUES } from '@/modules/alerts/lib/alert-types'

export const transmissionOptions = [
  { value: 'manual', label: 'Manual' },
  { value: 'automatic', label: 'Automático' },
  { value: 'automated', label: 'Automatizado' },
] as const

export const vehicleAlertConfigSchema = z.object({
  type: z.enum(ALERT_TYPE_VALUES),
  alarm_code: z.string().nullable(),
  label: z.string(),
  is_enabled: z.boolean(),
  notify_in_app: z.boolean(),
  notify_monitoring: z.boolean(),
  notify_push: z.boolean(),
  notify_email: z.boolean(),
})

export const vehicleSchema = z.object({
  client_id: z.string().min(1, 'Selecione o cliente'),
  plate: z.string().min(1, 'Informe a placa').max(10, 'Placa inválida'),
  chassis: z.string(),
  renavam: z.string(),
  brand: z.string(),
  model: z.string(),
  color: z.string(),
  year: z.string(),
  vehicle_type: z.string().min(1, 'Selecione o tipo de veículo'),
  transmission: z.enum(['manual', 'automatic', 'automated', '']),
  odometer: z.string(),
  max_speed_kmh: z.string(),
  speed_hysteresis_percent: z.string(),
  speed_min_duration_seconds: z.string(),
  average_consumption: z.string(),
  tank_capacity: z.string(),
  crlv_file: z.string(),
  fipe_code: z.string(),
  fipe_model_year: z.string(),
  fipe_fuel: z.string(),
  fipe_reference_month: z.string(),
  fipe_value: z.string(),
  fipe_model: z.string(),
  fipe_brand: z.string(),
  fipe_score: z.string(),
  is_active: z.boolean(),
  alert_configs: z.array(vehicleAlertConfigSchema),
})

export type VehicleFormValues = z.infer<typeof vehicleSchema>
