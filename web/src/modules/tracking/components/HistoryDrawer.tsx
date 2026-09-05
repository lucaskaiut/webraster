import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { Button, Modal, TextField } from '@/shared/design-system'
import { FormProvider, useForm } from 'react-hook-form'
import { parseApiError } from '@/shared/api/errors'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import type { GpsPosition, TrackingLiveVehicle } from '@/shared/types/models'
import { formatDateTime } from '@/shared/utils/format'
import { trackingService } from '../services/tracking.service'
import { deriveRouteEvents, formatDuration, formatMeters, formatSpeed, summarizeRoute, vehicleLabel } from '../lib/tracking'

const PLAYBACK_SPEEDS = [0.5, 1, 2, 4, 8] as const

function toLocalInputValue(date: Date): string {
  const pad = (value: number) => String(value).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function defaultRange(): { from: string; to: string } {
  const now = new Date()
  const from = new Date(now)
  from.setHours(from.getHours() - 24)

  return { from: toLocalInputValue(from), to: toLocalInputValue(now) }
}

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
  const defaults = useMemo(() => defaultRange(), [vehicle?.id])
  const form = useForm({ defaultValues: defaults })
  const [loading, setLoading] = useState(false)
  const stats = useMemo(() => summarizeRoute(route), [route])
  const events = useMemo(() => deriveRouteEvents(route), [route])
  const current = route[playbackIndex] ?? null

  useEffect(() => {
    form.reset(defaults)
  }, [defaults, form])

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
          <FormProvider {...form}>
            <form
              className="grid gap-3 sm:grid-cols-2"
              onSubmit={form.handleSubmit(async (values) => {
                const fromDate = new Date(values.from)
                const toDate = new Date(values.to)

                if (Number.isNaN(fromDate.getTime()) || Number.isNaN(toDate.getTime()) || fromDate > toDate) {
                  toast.error('Período inválido', 'Informe um intervalo válido.')
                  return
                }

                if (toDate.getTime() - fromDate.getTime() > 31 * 24 * 60 * 60 * 1000) {
                  toast.error('Período muito longo', 'O intervalo máximo é de 31 dias.')
                  return
                }

                onPlayingChange(false)
                onPlaybackIndexChange(0)
                setLoading(true)

                try {
                  const from = fromDate.toISOString()
                  const to = toDate.toISOString()
                  const points = await queryClient.fetchQuery({
                    queryKey: queryKeys.tracking.history(vehicle.id, from, to),
                    queryFn: () => trackingService.history(vehicle.id, from, to),
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
              })}
            >
              <TextField name="from" label="De" type="datetime-local" required />
              <TextField name="to" label="Até" type="datetime-local" required />
              <div className="sm:col-span-2">
                <Button type="submit" loading={loading}>
                  Carregar percurso
                </Button>
              </div>
            </form>
          </FormProvider>

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
