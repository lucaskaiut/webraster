import type { AlertConfig, AlertSeverity } from '@/shared/types/models'

export const VEHICLE_ALERT_TYPE_VALUES = [
  'speed',
  'ignition_on',
  'ignition_off',
  'sos',
  'offline',
  'battery',
  'jamming',
] as const

export type VehicleAlertType = (typeof VEHICLE_ALERT_TYPE_VALUES)[number]

export const ALERT_TYPE_VALUES = [...VEHICLE_ALERT_TYPE_VALUES, 'device_alarm'] as const

export type AlertTypeValue = (typeof ALERT_TYPE_VALUES)[number]

export interface VehicleAlertTypeOption {
  type: VehicleAlertType
  label: string
  description: string
}

/** Alarmes gerais, definidos em App\Modules\Alert\Enums\AlertType::configurable(). */
export const VEHICLE_ALERT_TYPES: VehicleAlertTypeOption[] = [
  {
    type: 'speed',
    label: 'Excesso de velocidade',
    description: 'Avisa quando o veículo passa do limite de velocidade.',
  },
  {
    type: 'ignition_on',
    label: 'Ignição ligada',
    description: 'Avisa quando o veículo é ligado.',
  },
  {
    type: 'ignition_off',
    label: 'Ignição desligada',
    description: 'Avisa quando o veículo é desligado.',
  },
  {
    type: 'sos',
    label: 'SOS',
    description: 'Avisa quando o botão de SOS é acionado.',
  },
  {
    type: 'offline',
    label: 'Dispositivo offline',
    description: 'Avisa quando o rastreador fica sem comunicação.',
  },
  {
    type: 'battery',
    label: 'Bateria baixa',
    description: 'Avisa quando a bateria do rastreador fica baixa.',
  },
  {
    type: 'jamming',
    label: 'Jamming',
    description: 'Avisa quando há tentativa de bloquear o sinal de GPS/GSM.',
  },
]

export interface DeviceAlarmTypeOption {
  code: string
  label: string
  severity: AlertSeverity
}

/**
 * Espelho de TraccarAttributeReader::configurableDeviceAlarms().
 * Novos códigos enviados pelo rastreador são catalogados automaticamente e
 * aparecem no formulário mesmo sem estarem nesta lista.
 */
export const DEVICE_ALARM_TYPES: DeviceAlarmTypeOption[] = [
  { code: 'accident', label: 'Acidente', severity: 'critical' },
  { code: 'powercut', label: 'Alimentação cortada', severity: 'critical' },
  { code: 'removing', label: 'Remoção', severity: 'critical' },
  { code: 'tampering', label: 'Violação', severity: 'critical' },
  { code: 'fault', label: 'Falha', severity: 'high' },
  { code: 'fuelleak', label: 'Vazamento de combustível', severity: 'high' },
  { code: 'gpsantennacut', label: 'Antena GPS cortada', severity: 'high' },
  { code: 'lowbattery', label: 'Bateria baixa', severity: 'high' },
  { code: 'lowpower', label: 'Tensão baixa', severity: 'high' },
  { code: 'hardacceleration', label: 'Aceleração brusca', severity: 'medium' },
  { code: 'hardbraking', label: 'Frenagem brusca', severity: 'medium' },
  { code: 'hardcornering', label: 'Curva brusca', severity: 'medium' },
  { code: 'bonnet', label: 'Capô', severity: 'medium' },
  { code: 'door', label: 'Porta', severity: 'medium' },
  { code: 'parking', label: 'Estacionamento', severity: 'medium' },
  { code: 'fatiguedriving', label: 'Fadiga ao volante', severity: 'medium' },
  { code: 'footbrake', label: 'Freio de pé', severity: 'medium' },
  { code: 'geofence', label: 'Geocerca', severity: 'medium' },
  { code: 'geofenceenter', label: 'Entrada em geocerca', severity: 'medium' },
  { code: 'general', label: 'Alarme geral', severity: 'medium' },
  { code: 'lock', label: 'Travamento', severity: 'medium' },
  { code: 'movement', label: 'Movimento', severity: 'medium' },
  { code: 'lanechange', label: 'Mudança de faixa', severity: 'medium' },
  { code: 'idle', label: 'Ocioso', severity: 'medium' },
  { code: 'poweroff', label: 'Dispositivo desligado', severity: 'medium' },
  { code: 'falldown', label: 'Queda', severity: 'medium' },
  { code: 'tow', label: 'Reboque', severity: 'medium' },
  { code: 'highrpm', label: 'RPM alto', severity: 'medium' },
  { code: 'temperature', label: 'Temperatura', severity: 'medium' },
  { code: 'unlock', label: 'Destravamento', severity: 'medium' },
  { code: 'vibration', label: 'Vibração', severity: 'medium' },
]

export interface VehicleAlertConfigValue {
  type: AlertTypeValue
  alarm_code: string | null
  label: string
  is_enabled: boolean
  notify_in_app: boolean
  notify_monitoring: boolean
  notify_push: boolean
  notify_email: boolean
}

const DEFAULT_ENABLED_GENERAL: VehicleAlertType[] = ['sos', 'jamming', 'offline']
const DEFAULT_EMAIL_GENERAL: VehicleAlertType[] = ['sos', 'jamming']
const DEFAULT_ENABLED_DEVICE = ['accident', 'powercut', 'removing', 'tampering']

function generalValue(
  option: VehicleAlertTypeOption,
  config?: AlertConfig,
): VehicleAlertConfigValue {
  return {
    type: option.type,
    alarm_code: null,
    label: option.label,
    is_enabled: config?.is_enabled ?? DEFAULT_ENABLED_GENERAL.includes(option.type),
    notify_in_app: config?.notify_in_app ?? true,
    notify_monitoring: config?.notify_monitoring ?? true,
    notify_push: config?.notify_push ?? true,
    notify_email: config?.notify_email ?? DEFAULT_EMAIL_GENERAL.includes(option.type),
  }
}

function deviceValue(code: string, label: string, config?: AlertConfig): VehicleAlertConfigValue {
  return {
    type: 'device_alarm',
    alarm_code: code,
    label: config?.alarm_label ?? label,
    is_enabled: config?.is_enabled ?? DEFAULT_ENABLED_DEVICE.includes(code),
    notify_in_app: config?.notify_in_app ?? true,
    notify_monitoring: config?.notify_monitoring ?? true,
    notify_push: config?.notify_push ?? true,
    notify_email: config?.notify_email ?? DEFAULT_ENABLED_DEVICE.includes(code),
  }
}

/** Padrão do backend para veículo novo: gerais e alarmes críticos habilitados. */
export function defaultVehicleAlertConfigs(): VehicleAlertConfigValue[] {
  return [
    ...VEHICLE_ALERT_TYPES.map((option) => generalValue(option)),
    ...DEVICE_ALARM_TYPES.map((option) => deviceValue(option.code, option.label)),
  ]
}

export function vehicleAlertConfigsFromApi(
  configs?: AlertConfig[] | null,
): VehicleAlertConfigValue[] {
  const byType = new Map<string, AlertConfig>()

  for (const config of configs ?? []) {
    byType.set(config.alarm_code ? `device_alarm|${config.alarm_code}` : `${config.type}|`, config)
  }

  const catalogued = new Set(DEVICE_ALARM_TYPES.map((option) => option.code))

  const general = VEHICLE_ALERT_TYPES.map((option) =>
    generalValue(option, byType.get(`${option.type}|`)),
  )

  const device = DEVICE_ALARM_TYPES.map((option) =>
    deviceValue(option.code, option.label, byType.get(`device_alarm|${option.code}`)),
  )

  const discovered = (configs ?? [])
    .filter(
      (config) =>
        config.type === 'device_alarm' &&
        Boolean(config.alarm_code) &&
        !catalogued.has(config.alarm_code as string),
    )
    .map((config) => deviceValue(config.alarm_code as string, config.alarm_code as string, config))

  return [...general, ...device, ...discovered]
}
