import type { ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'
import {
  AlertTriangle,
  BatteryFull,
  BatteryLow,
  BatteryMedium,
  Gauge,
  Lock,
  LockOpen,
  Navigation,
  Plug,
  Power,
  Satellite,
  Signal,
  SignalHigh,
  SignalLow,
  SignalMedium,
  Wifi,
  WifiLow,
  WifiOff,
  Zap,
} from 'lucide-react'
import { Tooltip } from '@/shared/design-system'
import type { DeviceAlarm, TrackingLiveVehicle } from '@/shared/types/models'
import { cn } from '@/shared/utils/cn'
import {
  BATTERY_GOOD_MIN,
  BATTERY_LOW_MIN,
  INDICATOR_TONE_CLASS,
  batteryPercent,
  connectionIndicator,
  externalPowerStatus,
  formatSignal,
  gpsStatus,
  signalLevel,
  voltageLevel,
  type ConnectionIndicator,
  type ExternalPowerStatus,
  type IndicatorTone,
  type SignalLevel,
  type VoltageLevel,
} from '../lib/indicators'
import { formatSpeed, formatUpdatedAt, motionStatus, speedKmh, vehicleAlarms } from '../lib/tracking'

type IndicatorConfig = {
  icon: LucideIcon
  tone: IndicatorTone
  title: string
  details?: string[]
}

const PER_ROW_CLASS: Record<number, string> = {
  1: 'grid-cols-1',
  2: 'grid-cols-2',
  3: 'grid-cols-3',
  4: 'grid-cols-4',
  5: 'grid-cols-5',
  6: 'grid-cols-6',
}

function TooltipContent({ title, details }: { title: string; details?: string[] }) {
  return (
    <div className="space-y-0.5 text-xs leading-snug">
      <p className="font-semibold">{title}</p>
      {details?.map((detail) => (
        <p key={detail} className="text-background/80">
          {detail}
        </p>
      ))}
    </div>
  )
}

function AlarmsTooltip({ alarms }: { alarms: DeviceAlarm[] }) {
  return (
    <div className="space-y-1 text-xs leading-snug">
      <p className="font-semibold">Alerta Ativo</p>
      <ul className="list-none space-y-1">
        {alarms.map((alarm) => (
          <li key={alarm.code} className="flex items-start gap-2">
            <span aria-hidden="true" className="mt-1.5 size-1 shrink-0 rounded-full bg-background/70" />
            <span>{alarm.label}</span>
          </li>
        ))}
      </ul>
    </div>
  )
}

function IndicatorIcon({
  tone,
  label,
  content,
  children,
}: {
  tone: IndicatorTone
  label: string
  content: ReactNode
  children: ReactNode
}) {
  return (
    <Tooltip content={content}>
      <span
        role="img"
        aria-label={label}
        className={cn('inline-flex transition-colors', INDICATOR_TONE_CLASS[tone])}
      >
        {children}
      </span>
    </Tooltip>
  )
}

function Indicator({ icon: Icon, tone, title, details }: IndicatorConfig) {
  return (
    <IndicatorIcon tone={tone} label={title} content={<TooltipContent title={title} details={details} />}>
      <Icon className="size-3.5" aria-hidden="true" />
    </IndicatorIcon>
  )
}

function ConnectionIndicator({ vehicle, now }: { vehicle: TrackingLiveVehicle; now: number }) {
  const status = connectionIndicator(vehicle, now)
  const lastCommunication = vehicle.position
    ? `Última comunicação: ${formatUpdatedAt(vehicle.position.recorded_at, now)}`
    : 'Sem posição registrada'

  const configs: Record<ConnectionIndicator, IndicatorConfig> = {
    online: { icon: Wifi, tone: 'success', title: 'Equipamento Online', details: [lastCommunication] },
    delayed: { icon: WifiLow, tone: 'warning', title: 'Comunicação Atrasada', details: [lastCommunication] },
    offline: { icon: WifiOff, tone: 'danger', title: 'Equipamento Offline', details: [lastCommunication] },
    unknown: { icon: WifiOff, tone: 'muted', title: 'Sem Comunicação', details: [lastCommunication] },
  }

  return <Indicator {...configs[status]} />
}

function IgnitionIndicator({ vehicle }: { vehicle: TrackingLiveVehicle }) {
  const ignition = vehicle.position?.ignition ?? null

  if (ignition === null) {
    return <Indicator icon={Power} tone="muted" title="Ignição" details={['Não informado']} />
  }

  return ignition ? (
    <Indicator icon={Power} tone="success" title="Ignição Ligada" />
  ) : (
    <Indicator icon={Power} tone="danger" title="Ignição Desligada" />
  )
}

function MovementIndicator({ vehicle }: { vehicle: TrackingLiveVehicle }) {
  const motion = motionStatus(vehicle)

  if (motion === 'moving') {
    return (
      <Indicator
        icon={Navigation}
        tone="success"
        title="Em Movimento"
        details={[`Velocidade Atual: ${formatSpeed(vehicle.position?.speed)}`]}
      />
    )
  }

  if (motion === 'stopped') {
    return <Indicator icon={Navigation} tone="muted" title="Parado" />
  }

  return <Indicator icon={Navigation} tone="muted" title="Movimento" details={['Sem posição']} />
}

function SpeedIndicator({ vehicle }: { vehicle: TrackingLiveVehicle }) {
  const speed = speedKmh(vehicle.position?.speed)

  if (speed === null) {
    return <Indicator icon={Gauge} tone="muted" title="Velocidade Atual" details={['Não informado']} />
  }

  const kmh = Math.round(speed)

  return (
    <Indicator
      icon={Gauge}
      tone={kmh > 0 ? 'success' : 'muted'}
      title="Velocidade Atual"
      details={[`${kmh} km/h`]}
    />
  )
}

function ExternalPowerIndicator({ vehicle }: { vehicle: TrackingLiveVehicle }) {
  const status = externalPowerStatus(vehicle)

  const configs: Record<ExternalPowerStatus, IndicatorConfig> = {
    connected: { icon: Plug, tone: 'success', title: 'Alimentação Externa', details: ['Conectada'] },
    disconnected: { icon: Plug, tone: 'danger', title: 'Alimentação Externa', details: ['Desconectada'] },
    unknown: { icon: Plug, tone: 'muted', title: 'Alimentação Externa', details: ['Não informado'] },
  }

  return <Indicator {...configs[status]} />
}

function VoltageIndicator({ vehicle }: { vehicle: TrackingLiveVehicle }) {
  const voltage = vehicle.position?.voltage ?? null

  if (voltage === null) {
    return <Indicator icon={Zap} tone="muted" title="Tensão da Alimentação" details={['Não informado']} />
  }

  const level = voltageLevel(voltage)

  const titles: Record<VoltageLevel, string> = {
    normal: 'Tensão da Alimentação',
    low: 'Tensão da Alimentação Baixa',
    critical: 'Tensão Crítica',
  }

  const tones: Record<VoltageLevel, IndicatorTone> = {
    normal: 'success',
    low: 'warning',
    critical: 'danger',
  }

  return <Indicator icon={Zap} tone={tones[level]} title={titles[level]} details={[`${voltage.toFixed(1)}V`]} />
}

function BatteryIndicator({ vehicle }: { vehicle: TrackingLiveVehicle }) {
  const percent = batteryPercent(vehicle.position?.battery)

  if (percent === null) {
    return <Indicator icon={BatteryMedium} tone="muted" title="Bateria Interna" details={['Não informado']} />
  }

  const icon = percent > BATTERY_GOOD_MIN ? BatteryFull : percent >= BATTERY_LOW_MIN ? BatteryMedium : BatteryLow
  const tone: IndicatorTone = percent > BATTERY_GOOD_MIN ? 'success' : percent >= BATTERY_LOW_MIN ? 'warning' : 'danger'

  return <Indicator icon={icon} tone={tone} title="Bateria Interna" details={[`${Math.round(percent)}%`]} />
}

function SignalIndicator({ vehicle }: { vehicle: TrackingLiveVehicle }) {
  const signal = vehicle.position?.signal ?? null
  const level = signalLevel(signal)

  if (level === 'none') {
    return (
      <IndicatorIcon
        tone="muted"
        label="Sem Sinal"
        content={<TooltipContent title="Sinal GSM" details={['Sem Sinal']} />}
      >
        <span className="relative inline-flex size-3.5 items-center justify-center">
          <Signal className="size-3.5" aria-hidden="true" />
          <span aria-hidden="true" className="absolute h-px w-4 rotate-45 rounded-full bg-current" />
        </span>
      </IndicatorIcon>
    )
  }

  const configs: Record<Exclude<SignalLevel, 'none'>, IndicatorConfig> = {
    strong: { icon: SignalHigh, tone: 'success', title: 'Sinal GSM', details: [formatSignal(signal)] },
    medium: { icon: SignalMedium, tone: 'warning', title: 'Sinal GSM', details: [formatSignal(signal)] },
    weak: { icon: SignalLow, tone: 'danger', title: 'Sinal GSM', details: [formatSignal(signal)] },
    unknown: { icon: Signal, tone: 'muted', title: 'Sinal GSM', details: ['Não informado'] },
  }

  return <Indicator {...configs[level]} />
}

function GpsIndicator({ vehicle }: { vehicle: TrackingLiveVehicle }) {
  const status = gpsStatus(vehicle)

  if (status === 'valid') {
    const satellites = vehicle.position?.satellites

    return (
      <Indicator
        icon={Satellite}
        tone="success"
        title="GPS Válido"
        details={satellites != null ? [`Satélites: ${satellites}`] : undefined}
      />
    )
  }

  if (status === 'invalid') {
    return <Indicator icon={Satellite} tone="danger" title="GPS Inválido" details={['Sem Fixação de Satélites']} />
  }

  return <Indicator icon={Satellite} tone="muted" title="GPS" details={['Não informado']} />
}

function BlockIndicator({ vehicle }: { vehicle: TrackingLiveVehicle }) {
  const blocked = vehicle.position?.blocked ?? null

  if (blocked === null) {
    return <Indicator icon={Lock} tone="muted" title="Bloqueio" details={['Não informado']} />
  }

  return blocked ? (
    <Indicator icon={Lock} tone="danger" title="Veículo Bloqueado" />
  ) : (
    <Indicator icon={LockOpen} tone="success" title="Veículo Liberado" />
  )
}

function AlertsIndicator({ vehicle }: { vehicle: TrackingLiveVehicle }) {
  const alarms = vehicleAlarms(vehicle)

  if (alarms.length === 0) {
    return <Indicator icon={AlertTriangle} tone="muted" title="Nenhum alerta ativo" />
  }

  const label = `${alarms.length} alerta${alarms.length === 1 ? '' : 's'} ativo${alarms.length === 1 ? '' : 's'}`

  return (
    <IndicatorIcon tone="danger" label={label} content={<AlarmsTooltip alarms={alarms} />}>
      <AlertTriangle className="size-3.5" aria-hidden="true" />
    </IndicatorIcon>
  )
}

export function VehicleStatusIndicators({
  vehicle,
  now,
  perRow,
  className,
}: {
  vehicle: TrackingLiveVehicle
  now: number
  perRow?: number
  className?: string
}) {
  const indicators = [
    <ConnectionIndicator key="connection" vehicle={vehicle} now={now} />,
    <IgnitionIndicator key="ignition" vehicle={vehicle} />,
    <MovementIndicator key="movement" vehicle={vehicle} />,
    <SpeedIndicator key="speed" vehicle={vehicle} />,
    <ExternalPowerIndicator key="power" vehicle={vehicle} />,
    <VoltageIndicator key="voltage" vehicle={vehicle} />,
    <BatteryIndicator key="battery" vehicle={vehicle} />,
    <SignalIndicator key="signal" vehicle={vehicle} />,
    <GpsIndicator key="gps" vehicle={vehicle} />,
    <BlockIndicator key="block" vehicle={vehicle} />,
    <AlertsIndicator key="alerts" vehicle={vehicle} />,
  ]

  const gridClass = perRow ? PER_ROW_CLASS[perRow] : undefined

  return (
    <div
      className={cn(
        gridClass
          ? cn('grid justify-items-start gap-1.5', gridClass)
          : 'flex flex-wrap items-center gap-1.5',
        className,
      )}
    >
      {indicators}
    </div>
  )
}
