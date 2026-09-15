import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { Button, DateRangeFilter, Modal } from '@/shared/design-system'
import { parseApiError } from '@/shared/api/errors'
import { speedAlertsToTimelineEvents } from '@/modules/alerts/lib/speed-events'
import { alertsService } from '@/modules/alerts/services/alerts.service'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import type { GpsPosition, TrackingLiveVehicle } from '@/shared/types/models'
import { formatDateTime } from '@/shared/utils/format'
import { parseIsoDate, type DateRange } from '@/shared/utils/date'
import { resolvePresetRange } from '@/shared/utils/date-range'
import { trackingService } from '../services/tracking.service'
import { deriveRouteEvents, formatDuration, formatMeters, formatSpeed, rangeToApiBounds, summarizeRoute, vehicleLabel } from '../lib/tracking'

const PLAYBACK_SPEEDS = [0.5, 1, 2, 4, 8] as const
const MAX_RANGE_MS = 31 * 24 * 60 * 60 * 1000

export function HistoryDrawer({
  open,
  onClose,
  vehicle,
  route,
  onRouteChange,
  playbackIndex,
  onPlaybackIndexChange,
  playing,
  onPlayingChange,
  playbackSpeed,
  onPlaybackSpeedChange,
  showEvents,
}: {
  open: boolean
  onClose: () => void
  vehicle: TrackingLiveVehicle | null
  route: GpsPosition[]
  onRouteChange: (route: GpsPosition[]) => void
  playbackIndex: number
  onPlaybackIndexChange: (index: number) => void
  playing: boolean
  onPlayingChange: (playing: boolean) => void
  playbackSpeed: number
  onPlaybackSpeedChange: (speed: (typeof PLAYBACK_SPEEDS)[number]) => void
  showEvents: boolean
}) {
  const queryClient = useQueryClient()
  const defaults = useMemo(() => resolvePresetRange('today'), [vehicle?.id])
  const [range, setRange] = useState<DateRange>(defaults)
  const [loading, setLoading] = useState(false)
  const stats = useMemo(() => summarizeRoute(route), [route])
  const bounds = useMemo(() => rangeToApiBounds(range), [range.from, range.to])
  const speedAlertsQuery = useQuery({
    queryKey: queryKeys.alerts.list({
      vehicle_id: vehicle?.id ?? '',
      type: 'speed',
      from: bounds?.from,
      to: bounds?.to,
      per_page: 100,
    }),
    queryFn: () =>
      alertsService.list({
        vehicle_id: vehicle!.id,
        type: 'speed',
        from: bounds?.from,
        to: bounds?.to,
        per_page: 100,
        sort: 'asc',
      }),
    enabled: showEvents && Boolean(vehicle?.id) && Boolean(bounds),
  })
  const events = useMemo(() => {
    const routeEvents = deriveRouteEvents(route)
    const speedEvents = speedAlertsToTimelineEvents(speedAlertsQuery.data?.data ?? [])

    return [...routeEvents, ...speedEvents].sort(
      (a, b) => new Date(a.at).getTime() - new Date(b.at).getTime(),
    )
  }, [route, speedAlertsQuery.data?.data])
  const current = route[playbackIndex] ?? null

  useEffect(() => {
    setRange(defaults)
  }, [defaults])

  const loadHistory = async () => {
    if (!vehicle) {
      return
    }

    const bounds = rangeToApiBounds(range)

    if (!bounds) {
      toast.error('Período inválido', 'Informe um intervalo válido.')
      return
    }

    const start = parseIsoDate(range.from)
    const end = parseIsoDate(range.to)

    if (start && end && end.getTime() - start.getTime() > MAX_RANGE_MS) {
      toast.error('Período muito longo', 'O intervalo máximo é de 31 dias.')
      return
    }

    onPlayingChange(false)
    onPlaybackIndexChange(0)
    setLoading(true)

    try {
      const points = await queryClient.fetchQuery({
        queryKey: queryKeys.tracking.history(vehicle.id, bounds.from, bounds.to),
        queryFn: () => trackingService.history(vehicle.id, bounds.from, bounds.to),
        staleTime: 0,
      })
      const routePoints = Array.isArray(points) ? points : []
      onRouteChange(routePoints)

      if (routePoints.length === 0) {
        toast.info('Nenhum ponto no período', 'Não há posições GPS neste intervalo.')
      }
    } catch (error) {
      const apiError = parseApiError(error)
      toast.error(
        'Não foi possível carregar o percurso',
        Object.values(apiError.fieldErrors).flat()[0] ?? apiError.message,
      )
      onRouteChange([])
    } finally {
      setLoading(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      size="lg"
      title={showEvents ? 'Eventos' : 'Histórico do veículo'}
      description={vehicle ? `${vehicle.plate} · ${vehicleLabel(vehicle)}` : undefined}
    >
      {!vehicle ? (
        <p className="text-sm text-muted">Selecione um veículo para consultar o histórico.</p>
      ) : (
        <div className="space-y-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div className="min-w-0 flex-1">
              <DateRangeFilter
                label="Período"
                from={range.from}
                to={range.to}
                onChange={setRange}
              />
            </div>
            <Button type="button" loading={loading} onClick={() => void loadHistory()}>
              Carregar percurso
            </Button>
          </div>

          {route.length > 0 && (
            <>
              <div className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <Summary label="Distância" value={formatMeters(stats.distanceMeters)} />
                <Summary label="Tempo" value={formatDuration(stats.durationMs)} />
                <Summary label="Paradas" value={String(stats.stops)} />
                <Summary
                  label="Vel. máxima"
                  value={
                    stats.maxSpeedKmh == null
                      ? 'Não informado'
                      : `${stats.maxSpeedKmh.toLocaleString('pt-BR', { maximumFractionDigits: 0 })} km/h`
                  }
                />
              </div>

              <div className="space-y-2">
                <input
                  type="range"
                  min={0}
                  max={Math.max(route.length - 1, 0)}
                  value={playbackIndex}
                  onChange={(event) => {
                    onPlayingChange(false)
                    onPlaybackIndexChange(Number(event.target.value))
                  }}
                  className="w-full"
                  aria-label="Progresso do percurso"
                />
                <div className="flex flex-wrap items-center gap-2">
                  <Button
                    size="sm"
                    variant="secondary"
                    onClick={() => onPlaybackIndexChange(Math.max(0, playbackIndex - 1))}
                    disabled={playbackIndex === 0}
                  >
                    ⏮
                  </Button>
                  <Button
                    size="sm"
                    disabled={route.length < 2}
                    onClick={() => onPlayingChange(!playing)}
                  >
                    {playing ? 'Pausar' : 'Play'}
                  </Button>
                  <Button
                    size="sm"
                    variant="secondary"
                    onClick={() => onPlaybackIndexChange(Math.min(route.length - 1, playbackIndex + 1))}
                    disabled={playbackIndex >= route.length - 1}
                  >
                    ⏭
                  </Button>
                  <div className="flex gap-1">
                    {PLAYBACK_SPEEDS.map((item) => (
                      <button
                        key={item}
                        type="button"
                        onClick={() => onPlaybackSpeedChange(item)}
                        className={`rounded-md px-2 py-1 text-xs ${playbackSpeed === item ? 'bg-primary text-primary-foreground' : 'bg-surface-2 text-muted'}`}
                      >
                        {item}x
                      </button>
                    ))}
                  </div>
                  <p className="ml-auto text-xs text-muted">
                    {current ? formatDateTime(current.recorded_at) : '—'} · {formatSpeed(current?.speed)}
                  </p>
                </div>
              </div>

              {showEvents && (
                <ol className="max-h-56 space-y-2 overflow-y-auto pt-1">
                  {events.length === 0 ? (
                    <li className="text-sm text-muted">Nenhum evento derivado neste percurso.</li>
                  ) : (
                    events.map((event) => (
                      <li key={event.id} className="flex gap-3 text-sm">
                        <span className="w-28 shrink-0 text-xs text-muted">{formatDateTime(event.at)}</span>
                        <span className="text-foreground">{event.label}</span>
                      </li>
                    ))
                  )}
                </ol>
              )}

              <div className="flex justify-end">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => {
                    onPlayingChange(false)
                    onPlaybackIndexChange(0)
                    onRouteChange([])
                  }}
                >
                  Limpar percurso
                </Button>
              </div>
            </>
          )}
        </div>
      )}
    </Modal>
  )
}

function Summary({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg bg-surface-2 px-3 py-2">
      <p className="text-[11px] text-muted">{label}</p>
      <p className="font-medium text-foreground">{value}</p>
    </div>
  )
}
