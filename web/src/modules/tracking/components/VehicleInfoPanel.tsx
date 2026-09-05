import { Button, Switch } from '@/shared/design-system'
import type { TrackingLiveVehicle } from '@/shared/types/models'
import { formatDateTime } from '@/shared/utils/format'
import {
  connectionStatus,
  formatCoordinates,
  formatHeading,
  formatMeters,
  formatSpeed,
  formatUpdatedAt,
  motionStatus,
  vehicleLabel,
} from '../lib/tracking'

export function VehicleInfoPanel({
  vehicle,
  now,
  follow,
  onFollowChange,
  onCenter,
  onHistory,
  onEvents,
  onDetails,
  detailsOpen,
}: {
  vehicle: TrackingLiveVehicle
  now: number
  follow: boolean
  onFollowChange: (value: boolean) => void
  onCenter: () => void
  onHistory: () => void
  onEvents: () => void
  onDetails: () => void
  detailsOpen: boolean
}) {
  const connection = connectionStatus(vehicle)
  const motion = motionStatus(vehicle)
  const position = vehicle.position

  return (
    <section className="rounded-xl bg-surface p-4 shadow-card">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <h2 className="font-semibold text-foreground">{vehicle.plate}</h2>
          <p className="text-sm text-muted">
            {vehicleLabel(vehicle)}
            {vehicle.client?.name ? ` · ${vehicle.client.name}` : ''}
          </p>
        </div>
        <p className="text-xs text-muted">
          {connection === 'online' ? 'Online' : connection === 'offline' ? 'Offline' : 'Sem posição'}
          {motion === 'moving' ? ' · Em movimento' : motion === 'stopped' ? ' · Parado' : ''}
        </p>
      </div>

      <dl className="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
        <Info label="Velocidade" value={formatSpeed(position?.speed)} />
        <Info label="Direção" value={formatHeading(position?.heading)} />
        <Info label="Atualização" value={formatUpdatedAt(position?.recorded_at, now)} />
        <Info label="Bateria" value={position?.battery != null ? `${Math.round(position.battery)}%` : 'Não informado'} />
      </dl>

      <p className="mt-3 text-xs text-muted">
        Última posição: {position ? formatDateTime(position.recorded_at) : 'Indisponível'}
        {position ? ` · ${formatCoordinates(position.latitude, position.longitude)}` : ''}
      </p>

      <div className="mt-3 flex flex-wrap items-center gap-2">
        <Button size="sm" variant="secondary" onClick={onCenter} disabled={!position}>
          Centralizar
        </Button>
        <Button size="sm" variant="secondary" onClick={onHistory}>
          Histórico
        </Button>
        <Button size="sm" variant="secondary" onClick={onEvents}>
          Eventos
        </Button>
        <Button size="sm" variant="ghost" onClick={onDetails}>
          {detailsOpen ? 'Ocultar detalhes' : 'Detalhes'}
        </Button>
        <label className="ml-auto flex items-center gap-2 text-xs text-muted">
          <Switch checked={follow} onCheckedChange={onFollowChange} label="Acompanhar veículo" />
          Acompanhar
        </label>
      </div>

      {detailsOpen && (
        <dl className="mt-4 grid grid-cols-2 gap-3 pt-3 text-sm sm:grid-cols-3">
          <Info label="Placa" value={vehicle.plate} />
          <Info label="Cliente" value={vehicle.client?.name ?? 'Não informado'} />
          <Info label="IMEI" value={vehicle.equipment?.imei ?? 'Não informado'} />
          <Info label="Dispositivo" value={vehicle.equipment?.model ?? 'Não informado'} />
          <Info label="Ignição" value={position?.ignition == null ? 'Não informado' : position.ignition ? 'Ligada' : 'Desligada'} />
          <Info label="Altitude" value={position?.altitude != null ? `${Math.round(position.altitude)} m` : 'Não informado'} />
          <Info label="Hodômetro" value={formatMeters(position?.odometer)} />
          <Info label="Protocolo" value={position?.protocol ?? 'Não informado'} />
          <Info
            label="Carregando"
            value={position?.charging == null ? 'Não informado' : position.charging ? 'Sim' : 'Não'}
          />
        </dl>
      )}
    </section>
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
