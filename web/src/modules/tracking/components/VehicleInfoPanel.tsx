import { useState, type ReactNode } from 'react'
import { useNavigate } from 'react-router'
import {
  Crosshair,
  FileBarChart,
  History,
  KeyRound,
  Map,
  Monitor,
  Navigation,
  Pencil,
  View,
  X,
  type LucideIcon,
} from 'lucide-react'
import { Button, SegmentedControl, buttonClasses } from '@/shared/design-system'
import { VehicleAlarmTooltip } from './VehicleAlarmTooltip'
import type { TrackingLiveVehicle } from '@/shared/types/models'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { Permission } from '@/shared/constants/permissions'
import { cn } from '@/shared/utils/cn'
import { formatDateTime, formatDateTimeWithSeconds } from '@/shared/utils/format'
import {
  formatCoordinates,
  formatDuration,
  formatHeading,
  formatUpdatedAt,
  speedKmh,
  vehicleAlarms,
} from '../lib/tracking'
import { externalPowerStatus } from '../lib/indicators'
import { VehicleCommandsPanel } from './VehicleCommandsPanel'
import { VehicleStatusIndicators } from './VehicleStatusIndicators'

type PanelTab = 'info' | 'events' | 'commands'

function GridItem({
  label,
  value,
  className,
}: {
  label: string
  value: ReactNode
  className?: string
}) {
  return (
    <div className={cn('min-w-0', className)}>
      <dt className="text-[13px] leading-tight font-semibold text-foreground">{label}</dt>
      <dd className="mt-0.5 text-[12.5px] leading-snug text-muted">{value}</dd>
    </div>
  )
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-[11px] text-muted">{label}</dt>
      <dd className="truncate text-foreground">{value}</dd>
    </div>
  )
}

function ToolbarButton({
  icon: Icon,
  label,
  onClick,
  active = false,
  disabled = false,
}: {
  icon: LucideIcon
  label: string
  onClick: () => void
  active?: boolean
  disabled?: boolean
}) {
  return (
    <Button
      variant={active ? 'primary' : 'secondary'}
      size="sm"
      onClick={onClick}
      disabled={disabled}
      title={label}
      aria-label={label}
      aria-pressed={active || undefined}
      className="size-8 px-0"
    >
      <Icon className="size-4" aria-hidden="true" />
    </Button>
  )
}

function ExternalToolbarLink({
  icon: Icon,
  label,
  href,
}: {
  icon: LucideIcon
  label: string
  href: string
}) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      title={label}
      aria-label={label}
      className={buttonClasses('secondary', 'sm', 'size-8 px-0')}
    >
      <Icon className="size-4" aria-hidden="true" />
    </a>
  )
}

export function VehicleInfoPanel({
  vehicle,
  now,
  follow,
  onFollowChange,
  onCenter,
  onHistory,
  onEvents,
  onClose,
  onlySelected,
  onOnlySelectedChange,
}: {
  vehicle: TrackingLiveVehicle
  now: number
  follow: boolean
  onFollowChange: (value: boolean) => void
  onCenter: () => void
  onHistory: () => void
  onEvents: () => void
  onClose: () => void
  onlySelected: boolean
  onOnlySelectedChange: (value: boolean) => void
}) {
  const { can } = usePermissions()
  const navigate = useNavigate()
  const [tab, setTab] = useState<PanelTab>('info')
  const [lightboxUrl, setLightboxUrl] = useState<string | null>(null)
  const position = vehicle.position
  const alarms = vehicleAlarms(vehicle)
  const powerStatus = externalPowerStatus(vehicle)
  const externalPowerValue =
    powerStatus === 'connected'
      ? 'Conectada'
      : powerStatus === 'disconnected'
        ? 'Desconectada'
        : 'Não informado'
  const images = vehicle.images ?? []
  const [coverImage, ...otherImages] = images
  const canSendCommands = can(Permission.DEVICE_COMMANDS_SEND)
  const canViewEquipmentDetails = can(Permission.EQUIPMENT_DETAILS_READ)
  const deviceId = vehicle.equipment?.id ?? null

  const title = [vehicle.model ?? vehicle.brand, vehicle.plate, vehicle.color]
    .filter(Boolean)
    .join(' ')
    .toUpperCase()

  const yearValue =
    vehicle.year != null ? `${vehicle.year} / ${vehicle.year}` : '--- / ---'
  const kmh = speedKmh(position?.speed)
  const speedValue = kmh === null ? '---' : `${Math.round(kmh)}km/h`
  const odometerValue =
    position?.odometer != null
      ? `${Math.round(position.odometer).toLocaleString('pt-BR')} Km`
      : '---'
  const hoursValue =
    position?.hours != null ? formatDuration(position.hours * 3_600_000) : '---'

  const lastConnection = vehicle.equipment?.traccar_last_update
    ? formatDateTimeWithSeconds(vehicle.equipment.traccar_last_update)
    : '---'
  const lastPosition = position?.recorded_at
    ? formatDateTimeWithSeconds(position.recorded_at)
    : '---'

  const mapsUrl = position
    ? `https://www.google.com/maps/search/?api=1&query=${position.latitude},${position.longitude}`
    : null
  const streetViewUrl = position
    ? `https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=${position.latitude},${position.longitude}`
    : null

  const tabOptions: Array<{ value: PanelTab; label: string }> = [
    { value: 'info', label: 'Informações' },
    { value: 'events', label: 'Eventos' },
    ...(canSendCommands ? [{ value: 'commands' as const, label: 'Comandos' }] : []),
  ]

  return (
    <div className="relative">
      <section className="max-h-[80vh] overflow-y-auto rounded-2xl bg-surface p-4 shadow-pop">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0 flex-1">
            <h2 className="flex items-center gap-2 text-sm font-bold text-foreground">
              <span className="truncate uppercase" title={title}>
                {title}
              </span>
              <VehicleAlarmTooltip alarms={alarms} iconClassName="size-4" />
            </h2>
            <p className="mt-3.5 text-[13px] text-foreground">
              <span className="font-semibold">Motorista:</span>{' '}
              <span className="font-normal text-muted">---</span>
            </p>
          </div>
          <Button
            variant="ghost"
            size="sm"
            onClick={onClose}
            aria-label="Fechar painel do veículo"
            className="size-8 shrink-0 px-0"
          >
            <X className="size-4" aria-hidden="true" />
          </Button>
        </div>
  
        <dl className="mt-3.5 grid grid-cols-3 gap-x-3 gap-y-3.5">
          <GridItem label="Ano" value={yearValue} />
          <GridItem label="Última conexão" value={lastConnection} />
          <GridItem label="Última posição" value={lastPosition} />
          <GridItem label="Velocidade" value={speedValue} />
          <GridItem label="Odômetro" value={odometerValue} />
          <GridItem label="Horímetro" value={hoursValue} />
        <GridItem label="Endereço" value={position?.address ?? '---'} className="col-span-3" />
      </dl>

      {coverImage && (
        <div className="mt-4 space-y-2">
          <button
            type="button"
            onClick={() => setLightboxUrl(coverImage.url)}
            aria-label="Ampliar foto do veículo"
            className="mx-auto block w-1/2 overflow-hidden rounded-xl bg-surface-2 shadow-card transition-opacity hover:opacity-90"
          >
            <img src={coverImage.url} alt="" className="h-auto w-full object-contain" />
          </button>

          {otherImages.length > 0 && (
            <div className="flex gap-2">
              {otherImages.slice(0, 3).map((image) => (
                <button
                  key={image.id}
                  type="button"
                  onClick={() => setLightboxUrl(image.url)}
                  aria-label="Ampliar foto do veículo"
                  className="h-14 flex-1 overflow-hidden rounded-lg bg-surface-2 shadow-card transition-opacity hover:opacity-90"
                >
                  <img src={image.url} alt="" className="h-full w-full object-cover" />
                </button>
              ))}
            </div>
          )}
        </div>
      )}

      <VehicleStatusIndicators vehicle={vehicle} now={now} className="mt-3.5 justify-center" />
  
        <div className="my-3.5 h-px bg-border/60" aria-hidden="true" />
  
        <div className="flex flex-wrap gap-1">
          <ToolbarButton
            icon={Monitor}
            label="Mostrar apenas este veículo"
            active={onlySelected}
            onClick={() => onOnlySelectedChange(!onlySelected)}
          />
          <ToolbarButton icon={History} label="Histórico no mapa" onClick={onHistory} />
          {can(Permission.REPORT_VIEW) && (
            <ToolbarButton
              icon={FileBarChart}
              label="Relatórios e histórico"
              onClick={() => navigate('/reports')}
            />
          )}
          <ToolbarButton
            icon={Crosshair}
            label="Centralizar veículo"
            onClick={onCenter}
            disabled={!position}
          />
          <ToolbarButton
            icon={Navigation}
            label="Acompanhar veículo"
            active={follow}
            onClick={() => onFollowChange(!follow)}
          />
          {mapsUrl && <ExternalToolbarLink icon={Map} label="Ver no Google Maps" href={mapsUrl} />}
          {streetViewUrl && (
            <ExternalToolbarLink icon={View} label="Street View" href={streetViewUrl} />
          )}
          {can(Permission.VEHICLE_UPDATE) && (
            <ToolbarButton
              icon={Pencil}
              label="Editar veículo"
              onClick={() => navigate(`/vehicles/${vehicle.id}/edit`)}
            />
          )}
          {canSendCommands && deviceId && (
            <ToolbarButton
              icon={KeyRound}
              label="Enviar comandos"
              active={tab === 'commands'}
              onClick={() => setTab('commands')}
            />
          )}
        </div>
  
        <div className="mt-3.5 h-px bg-border/60" aria-hidden="true" />
  
        <div className="mt-3.5">
          <SegmentedControl value={tab} options={tabOptions} onChange={setTab} />
        </div>
  
        <div className="mt-4">
          {tab === 'info' && (
            <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
              <Info
                label="Direção"
                value={position ? formatHeading(position.heading) : 'Não informado'}
              />
              <Info
                label="Localização"
                value={
                  position?.address?.trim() ||
                  formatCoordinates(position?.latitude, position?.longitude)
                }
              />
              <Info
                label="Atualização"
                value={formatUpdatedAt(position?.recorded_at, now)}
              />
              <Info
                label="Ignição"
                value={
                  position?.ignition == null ? 'Não informado' : position.ignition ? 'Ligada' : 'Desligada'
                }
              />
              <Info
                label="Altitude"
                value={position?.altitude != null ? `${Math.round(position.altitude)} m` : 'Não informado'}
              />
              <Info
                label="Bateria"
                value={position?.battery != null ? `${Math.round(position.battery)}%` : 'Não informado'}
              />
              {canViewEquipmentDetails && (
                <>
                  <Info label="IMEI" value={vehicle.equipment?.imei ?? 'Não informado'} />
                  <Info label="Dispositivo" value={vehicle.equipment?.model ?? 'Não informado'} />
                </>
              )}
              <Info label="Cliente" value={vehicle.client?.name ?? 'Não informado'} />
              <Info label="Protocolo" value={position?.protocol ?? 'Não informado'} />
              <Info label="Alimentação Externa" value={externalPowerValue} />
            </dl>
          )}
  
          {tab === 'events' && (
            <div className="space-y-3">
              {alarms.length > 0 ? (
                <ul className="space-y-2">
                  {alarms.map((alarm) => (
                    <li
                      key={alarm.code}
                      className="flex items-start gap-2 rounded-lg bg-surface-2 px-3 py-2 text-sm"
                    >
                      <div>
                        <p className="font-medium text-foreground">{alarm.label}</p>
                        <p className="text-xs text-muted">
                          Alarme reportado pelo dispositivo via Traccar
                          {position?.recorded_at
                            ? ` · ${formatDateTime(position.recorded_at)}`
                            : ''}
                        </p>
                      </div>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="text-sm text-muted">Nenhum alarme ativo no momento.</p>
              )}
              <div className="flex flex-wrap gap-2">
                <Button size="sm" variant="secondary" onClick={onHistory}>
                  Abrir histórico
                </Button>
                <Button size="sm" variant="secondary" onClick={onEvents}>
                  Ver eventos do percurso
                </Button>
              </div>
            </div>
          )}
  
          {tab === 'commands' && canSendCommands && (
            deviceId ? (
              <VehicleCommandsPanel deviceId={deviceId} />
            ) : (
              <p className="py-4 text-sm text-muted">
                Este veículo não possui equipamento vinculado.
              </p>
            )
          )}
        </div>
      </section>

      <div
        className="absolute -bottom-2 left-1/2 size-4 -translate-x-1/2 rotate-45 bg-surface"
        aria-hidden="true"
      />

      {lightboxUrl && (
        <div
          role="dialog"
          aria-modal="true"
          aria-label="Foto do veículo"
          className="fixed inset-0 z-[60] flex items-center justify-center bg-overlay p-6"
          onClick={() => setLightboxUrl(null)}
        >
          <img
            src={lightboxUrl}
            alt=""
            className="max-h-[85vh] max-w-full rounded-xl shadow-pop"
          />
        </div>
      )}
    </div>
  )
}
