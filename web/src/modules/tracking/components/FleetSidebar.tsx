import { memo } from 'react'
import { SearchInput, Skeleton } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import type { TrackingLiveVehicle } from '@/shared/types/models'
import { AlertTriangle } from 'lucide-react'
import { VehicleAlarmTooltip } from './VehicleAlarmTooltip'
import { VehicleStatusIndicators } from './VehicleStatusIndicators'
import {
  connectionStatus,
  fleetStats,
  formatSpeed,
  formatUpdatedAt,
  matchesFleetFilter,
  matchesSearch,
  motionStatus,
  vehicleAlarms,
  vehicleLabel,
  type FleetFilter,
} from '../lib/tracking'

const FILTERS: Array<{ value: FleetFilter; label: string }> = [
  { value: 'all', label: 'Todos' },
  { value: 'with_alarm', label: 'Com alarme' },
  { value: 'online', label: 'Online' },
  { value: 'offline', label: 'Offline' },
  { value: 'moving', label: 'Em movimento' },
  { value: 'stopped', label: 'Parado' },
  { value: 'no_signal', label: 'Sem sinal' },
]

export type FleetSidebarProps = {
  vehicles: TrackingLiveVehicle[]
  search: string
  onSearch: (value: string) => void
  filter: FleetFilter
  onFilter: (value: FleetFilter) => void
  selectedId: string | null
  onSelect: (id: string) => void
  loading: boolean
  now: number
  className?: string
}

export function FleetSidebar({
  vehicles,
  search,
  onSearch,
  filter,
  onFilter,
  selectedId,
  onSelect,
  loading,
  now,
  className,
}: FleetSidebarProps) {
  const stats = fleetStats(vehicles)
  const visible = vehicles.filter(
    (vehicle) => matchesSearch(vehicle, search) && matchesFleetFilter(vehicle, filter),
  )

  return (
    <aside className={cn('flex min-h-0 flex-col rounded-xl bg-surface shadow-card', className)}>
      <div className="space-y-3 p-3">
        <SearchInput
          className="sm:max-w-none"
          placeholder="Placa, cliente ou IMEI"
          aria-label="Buscar veículos"
          value={search}
          onChange={(event) => onSearch(event.target.value)}
        />

        <div className="grid grid-cols-3 gap-2 text-center">
          <Stat label="Total" value={stats.total} />
          <Stat label="Online" value={stats.online} />
          <Stat label="Offline" value={stats.offline} />
        </div>
        <div className="grid grid-cols-2 gap-2 text-center">
          <Stat label="Em movimento" value={stats.moving} />
          <Stat label="Parados" value={stats.stopped} />
        </div>
        {stats.withAlarm > 0 && (
          <div className="flex items-center gap-2 px-1 text-xs text-warning">
            <AlertTriangle className="size-3.5 shrink-0" aria-hidden="true" />
            <span>
              {stats.withAlarm} veículo{stats.withAlarm === 1 ? '' : 's'} com alarme ativo
            </span>
          </div>
        )}

        <div className="flex flex-wrap gap-1.5">
          {FILTERS.map((item) => (
            <button
              key={item.value}
              type="button"
              onClick={() => onFilter(item.value)}
              className={cn(
                'rounded-full px-2.5 py-1 text-xs font-medium transition-colors',
                filter === item.value
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-surface-2 text-muted hover:text-foreground',
              )}
            >
              {item.label}
            </button>
          ))}
        </div>
      </div>

      <div className="min-h-0 flex-1 overflow-y-auto">
        {loading && vehicles.length === 0 ? (
          <div className="space-y-2 p-3">
            <Skeleton className="h-20 w-full" />
            <Skeleton className="h-20 w-full" />
          </div>
        ) : visible.length === 0 ? (
          <p className="px-4 py-8 text-center text-sm text-muted">
            {vehicles.length === 0
              ? 'Sua frota aparecerá aqui quando houver veículos cadastrados com equipamento.'
              : 'Nenhum veículo encontrado. Tente alterar os filtros ou a busca.'}
          </p>
        ) : (
          <ul className="space-y-0.5 px-1.5 pb-2">
            {visible.map((vehicle) => (
              <VehicleListItem
                key={vehicle.id}
                vehicle={vehicle}
                selected={vehicle.id === selectedId}
                now={now}
                onSelect={onSelect}
              />
            ))}
          </ul>
        )}
      </div>
    </aside>
  )
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg bg-surface-2 px-2 py-1.5">
      <p className="text-sm font-semibold text-foreground">{value}</p>
      <p className="text-[11px] text-muted">{label}</p>
    </div>
  )
}

const VehicleListItem = memo(function VehicleListItem({
  vehicle,
  selected,
  now,
  onSelect,
}: {
  vehicle: TrackingLiveVehicle
  selected: boolean
  now: number
  onSelect: (id: string) => void
}) {
  const connection = connectionStatus(vehicle)
  const motion = motionStatus(vehicle)
  const alarms = vehicleAlarms(vehicle)

  return (
    <li>
      <button
        type="button"
        onClick={() => onSelect(vehicle.id)}
        className={cn(
          'group/item flex w-full flex-col gap-1 rounded-lg px-2.5 py-2.5 text-left transition-colors hover:bg-surface-2',
          selected && 'bg-surface-2',
          alarms.length > 0 && 'relative z-10',
        )}
      >
        <div className="flex items-center justify-between gap-2">
          <span className="flex items-center gap-2 font-medium text-foreground">
            <StatusDot status={connection} />
            {vehicle.plate}
            <VehicleAlarmTooltip alarms={alarms} />
          </span>
          <span className="text-[11px] text-muted">
            {connection === 'online' ? 'Online' : connection === 'offline' ? 'Offline' : 'Sem sinal'}
          </span>
        </div>
        <p className="truncate text-[13px] text-muted">
          {vehicleLabel(vehicle)}
          {vehicle.client?.name ? ` · ${vehicle.client.name}` : ''}
        </p>
        <div className="flex items-center justify-between text-xs text-muted">
          <span>
            {motion === 'moving' ? 'Em movimento' : motion === 'stopped' ? 'Parado' : 'Sem posição'}
            {motion === 'moving' ? ` · ${formatSpeed(vehicle.position?.speed)}` : ''}
          </span>
          <span>{formatUpdatedAt(vehicle.position?.recorded_at, now)}</span>
        </div>
        <VehicleStatusIndicators vehicle={vehicle} now={now} className="mt-0.5" />
      </button>
    </li>
  )
})

function StatusDot({ status }: { status: 'online' | 'offline' | 'no_position' }) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        'size-2 rounded-full',
        status === 'online' && 'bg-success',
        status === 'offline' && 'bg-muted',
        status === 'no_position' && 'bg-warning',
      )}
    />
  )
}
