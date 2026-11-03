import { useEffect, useMemo, useState } from 'react'
import { useParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { Clock, LinkIcon, MapPin, X } from 'lucide-react'
import { isApiError } from '@/shared/api/errors'
import { queryKeys } from '@/shared/constants/query-keys'
import { Loading, ThemeToggle } from '@/shared/design-system'
import { applyTheme } from '@/shared/theme/theme'
import { cn } from '@/shared/utils/cn'
import { formatDateTime } from '@/shared/utils/format'
import type { PublicTrackingVehicle, TrackingLiveVehicle } from '@/shared/types/models'
import { TrackingMap } from '../components/TrackingMap'
import { connectionStatus, formatSpeed, formatUpdatedAt } from '../lib/tracking'
import { trackingService } from '../services/tracking.service'

const REFRESH_MS = 15_000
const MARKER_INFO = { plate: true, vehicle: false, driver: false }

/** Adapta o veículo público ao formato usado pelo mapa do painel. */
function toMapVehicle(vehicle: PublicTrackingVehicle): TrackingLiveVehicle {
  return {
    id: vehicle.id,
    plate: vehicle.plate,
    brand: vehicle.brand,
    model: vehicle.model,
    color: vehicle.color,
    year: null,
    vehicle_type: vehicle.vehicle_type,
    client_id: null,
    online: vehicle.online,
    position: vehicle.position
      ? {
          ...vehicle.position,
          battery: null,
          altitude: null,
        }
      : null,
  }
}

function remaining(expiresAt: string, now: number): string {
  const diff = Math.max(0, new Date(expiresAt).getTime() - now)
  const minutes = Math.ceil(diff / 60_000)
  const hours = Math.floor(minutes / 60)

  if (hours === 0) return `${minutes} min`

  return `${hours}h ${String(minutes % 60).padStart(2, '0')}min`
}

function Message({ title, description }: { title: string; description: string }) {
  return (
    <div className="flex min-h-dvh items-center justify-center bg-background px-4">
      <div className="max-w-sm rounded-2xl bg-surface p-6 text-center shadow-card">
        <LinkIcon className="mx-auto size-8 text-muted" aria-hidden="true" />
        <h1 className="mt-3 text-base font-semibold text-foreground">{title}</h1>
        <p className="mt-1 text-sm text-muted">{description}</p>
      </div>
    </div>
  )
}

/**
 * Página pública (sem login) do link de compartilhamento: apenas o mapa e os
 * veículos compartilhados, atualizados periodicamente até a expiração.
 */
export default function PublicTrackingPage() {
  const { token = '' } = useParams()
  const [now, setNow] = useState(() => Date.now())
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [fitRequest, setFitRequest] = useState<{ id: number; target: 'fleet' | 'vehicle' | 'route' } | null>(
    null,
  )

  const query = useQuery({
    queryKey: queryKeys.tracking.publicShare(token),
    queryFn: () => trackingService.publicShare(token),
    enabled: Boolean(token),
    retry: (count, error) => !(isApiError(error) && [404, 410].includes(error.status)) && count < 2,
    refetchInterval: (state) => (state.state.error ? false : REFRESH_MS),
  })

  useEffect(() => {
    const timer = window.setInterval(() => setNow(Date.now()), 1000)
    return () => window.clearInterval(timer)
  }, [])

  const data = query.data
  const tenantTheme = data?.tenant.theme

  useEffect(() => {
    if (tenantTheme) applyTheme(tenantTheme)
  }, [tenantTheme])

  const vehicles = useMemo(() => (data?.vehicles ?? []).map(toMapVehicle), [data])
  const selected = vehicles.find((vehicle) => vehicle.id === selectedId) ?? null
  const expired = data ? new Date(data.expires_at).getTime() <= now : false

  if (query.isPending) return <Loading />

  if (query.error || !data) {
    const status = isApiError(query.error) ? query.error.status : 0
    return status === 410 ? (
      <Message title="Link expirado" description="Este link de acompanhamento não está mais disponível." />
    ) : status === 404 ? (
      <Message title="Link inválido" description="Verifique se o endereço foi copiado corretamente." />
    ) : (
      <Message title="Não foi possível carregar" description="Tente novamente em alguns instantes." />
    )
  }

  if (expired) {
    return <Message title="Link expirado" description="Este link de acompanhamento não está mais disponível." />
  }

  return (
    <div className="flex h-dvh flex-col bg-background">
      <header className="flex items-center justify-between gap-3 bg-surface px-4 py-2.5 shadow-card">
        <div className="flex min-w-0 items-center gap-3">
          {data.tenant.logo_url ? (
            <img src={data.tenant.logo_url} alt={data.tenant.name ?? ''} className="h-7 w-auto max-w-32 object-contain" />
          ) : (
            <span className="truncate text-sm font-semibold text-foreground">{data.tenant.name}</span>
          )}
          <span className="hidden text-sm text-muted sm:inline">Acompanhamento em tempo real</span>
        </div>
        <div className="flex items-center gap-2">
          <span
            className="flex items-center gap-1.5 rounded-full bg-surface-2 px-2.5 py-1 text-xs text-muted"
            title={`Expira em ${formatDateTime(data.expires_at)}`}
          >
            <Clock className="size-3.5" aria-hidden="true" />
            Expira em {remaining(data.expires_at, now)}
          </span>
          <ThemeToggle />
        </div>
      </header>

      <main className="relative min-h-0 flex-1 p-2 sm:p-3">
        <TrackingMap
          vehicles={vehicles}
          selectedId={selectedId}
          onSelect={(id) => {
            setSelectedId(id)
            setFitRequest({ id: Date.now(), target: 'vehicle' })
          }}
          route={[]}
          playbackPosition={null}
          follow={Boolean(selectedId)}
          fitRequest={fitRequest}
          onFitFleet={() => {
            setSelectedId(null)
            setFitRequest({ id: Date.now(), target: 'fleet' })
          }}
          onFitSelected={() => setFitRequest({ id: Date.now(), target: 'vehicle' })}
          showGeofences={false}
          showPois={false}
          markerInfo={MARKER_INFO}
        />

        <div className="pointer-events-none absolute bottom-5 left-5 z-10 flex max-h-[45%] w-[min(20rem,calc(100%-2.5rem))] flex-col gap-2">
          {selected ? (
            <section className="pointer-events-auto rounded-xl bg-surface p-3 shadow-pop">
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                  <p className="text-sm font-bold text-foreground uppercase">{selected.plate}</p>
                  <p className="truncate text-[13px] text-muted">
                    {[selected.brand, selected.model, selected.color].filter(Boolean).join(' · ') || 'Veículo'}
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => setSelectedId(null)}
                  aria-label="Fechar"
                  className="rounded-md p-1 text-muted hover:bg-surface-2"
                >
                  <X className="size-4" />
                </button>
              </div>
              <dl className="mt-2 grid grid-cols-2 gap-2 text-[13px]">
                <div>
                  <dt className="text-muted">Velocidade</dt>
                  <dd className="text-foreground">{formatSpeed(selected.position?.speed)}</dd>
                </div>
                <div>
                  <dt className="text-muted">Atualizado</dt>
                  <dd className="text-foreground">{formatUpdatedAt(selected.position?.recorded_at, now)}</dd>
                </div>
                {selected.position?.address && (
                  <div className="col-span-2">
                    <dt className="text-muted">Endereço</dt>
                    <dd className="text-foreground">{selected.position.address}</dd>
                  </div>
                )}
              </dl>
            </section>
          ) : (
            vehicles.length > 1 && (
              <ul className="pointer-events-auto space-y-1 overflow-y-auto rounded-xl bg-surface p-2 shadow-pop">
                {vehicles.map((vehicle) => {
                  const status = connectionStatus(vehicle)
                  return (
                    <li key={vehicle.id}>
                      <button
                        type="button"
                        disabled={!vehicle.position}
                        onClick={() => {
                          setSelectedId(vehicle.id)
                          setFitRequest({ id: Date.now(), target: 'vehicle' })
                        }}
                        className="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-[13px] hover:bg-surface-2 disabled:opacity-60"
                      >
                        <span
                          className={cn(
                            'size-2 shrink-0 rounded-full',
                            status === 'online' ? 'bg-success' : status === 'offline' ? 'bg-muted' : 'bg-warning',
                          )}
                          aria-hidden="true"
                        />
                        <span className="font-semibold text-foreground">{vehicle.plate}</span>
                        <span className="ml-auto text-muted">
                          {vehicle.position ? formatUpdatedAt(vehicle.position.recorded_at, now) : 'Sem posição'}
                        </span>
                      </button>
                    </li>
                  )
                })}
              </ul>
            )
          )}

          {vehicles.every((vehicle) => !vehicle.position) && (
            <p className="pointer-events-auto flex items-center gap-2 rounded-xl bg-surface p-3 text-[13px] text-muted shadow-pop">
              <MapPin className="size-4 shrink-0" aria-hidden="true" />
              Nenhuma posição disponível no momento.
            </p>
          )}
        </div>
      </main>
    </div>
  )
}
