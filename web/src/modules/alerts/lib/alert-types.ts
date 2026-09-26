import type { AlertConfig } from '@/shared/types/models'

export const VEHICLE_ALERT_TYPE_VALUES = [
  'speed',
  'ignition_on',
  'ignition_off',
  'sos',
  'offline',
  'battery',
  'jamming',
  'device_alarm',
] as const

export type VehicleAlertType = (typeof VEHICLE_ALERT_TYPE_VALUES)[number]

export interface VehicleAlertTypeOption {
  type: VehicleAlertType
  label: string
  description: string
}

/** Mesma ordem e textos de App\Modules\Alert\Enums\AlertType::configurable(). */
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
  {
    type: 'device_alarm',
    label: 'Alarme do dispositivo',
    description: 'Avisa alarmes do rastreador, como energia cortada, reboque ou porta aberta.',
  },
]

export interface VehicleAlertConfigValue {
  type: VehicleAlertType
  is_enabled: boolean
  notify_in_app: boolean
  notify_push: boolean
  notify_email: boolean
}

const DEFAULT_ENABLED: VehicleAlertType[] = ['sos', 'jamming', 'device_alarm', 'offline']
const DEFAULT_EMAIL: VehicleAlertType[] = ['sos', 'jamming', 'device_alarm']

/** Padrão do backend para veículo novo: críticos habilitados e demais desligados. */
export function defaultVehicleAlertConfigs(): VehicleAlertConfigValue[] {
  return VEHICLE_ALERT_TYPES.map(({ type }) => ({
    type,
    is_enabled: DEFAULT_ENABLED.includes(type),
    notify_in_app: true,
    notify_push: true,
    notify_email: DEFAULT_EMAIL.includes(type),
  }))
}

export function vehicleAlertConfigsFromApi(
  configs?: AlertConfig[] | null,
): VehicleAlertConfigValue[] {
  const defaults = defaultVehicleAlertConfigs()

  if (!configs?.length) {
    return defaults
  }

  const byType = new Map(configs.map((config) => [config.type, config] as const))

  return defaults.map((value) => {
    const config = byType.get(value.type)

    if (!config) {
      return value
    }

    return {
      type: value.type,
      is_enabled: config.is_enabled,
      notify_in_app: config.notify_in_app,
      notify_push: config.notify_push,
      notify_email: config.notify_email,
    }
  })
}
