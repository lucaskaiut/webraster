import { useMemo } from 'react'
import { Modal } from '@/shared/design-system'
import { RouteReportMap } from '@/modules/reports/components/RouteReportMap'
import { useTrackingHistoryQuery } from '@/modules/tracking/hooks/useTracking'
import { formatDuration, formatMeters } from '@/modules/tracking/lib/tracking'
import type { Alert } from '@/shared/types/models'
import { formatDateTime } from '@/shared/utils/format'

function formatSeconds(seconds: number | null | undefined): string {
  if (seconds == null) return '—'

  return formatDuration(seconds * 1000)
}

export function SpeedAlertDetailModal({
  alert,
  open,
  onClose,
}: {
  alert: Alert | null
  open: boolean
  onClose: () => void
}) {
  const excess = alert?.speed_excess ?? null
  const from = excess?.started_at ?? ''
  const to = excess?.ended_at ?? ''

  const historyQuery = useTrackingHistoryQuery(alert?.vehicle_id ?? undefined, from, to, open && Boolean(excess))

  const route = useMemo(() => {
    if (!excess || !historyQuery.data) return []

    const start = new Date(excess.started_at).getTime()
    const end = new Date(excess.ended_at).getTime()

    return historyQuery.data.filter((point) => {
      const at = new Date(point.recorded_at).getTime()
      return at >= start && at <= end
    })
  }, [excess, historyQuery.data])

  if (!alert) return null

  return (
    <Modal
      open={open}
      onClose={onClose}
      size="lg"
      title={alert.title}
      description={alert.vehicle?.plate ?? undefined}
    >
      <div className="space-y-6">
        <section className="space-y-3">
          <h3 className="text-sm font-semibold text-foreground">Resumo</h3>
          <div className="grid gap-3 sm:grid-cols-2">
            <Info label="Início" value={formatDateTime(excess?.started_at ?? alert.occurred_at)} />
            <Info label="Fim" value={formatDateTime(excess?.ended_at)} />
            <Info label="Duração" value={formatSeconds(excess?.duration_seconds)} />
            <Info
              label="Veículo"
              value={[alert.vehicle?.plate, alert.vehicle?.model].filter(Boolean).join(' · ') || '—'}
            />
          </div>
        </section>

        <section className="space-y-3">
          <h3 className="text-sm font-semibold text-foreground">Indicadores</h3>
          <div className="grid gap-3 sm:grid-cols-2">
            <Info
              label="Limite configurado"
              value={excess?.limit_kmh != null ? `${excess.limit_kmh} km/h` : '—'}
            />
            <Info
              label="Velocidade máxima atingida"
              value={excess?.max_speed_kmh != null ? `${excess.max_speed_kmh} km/h` : '—'}
            />
            <Info
              label="Velocidade média"
              value={excess?.avg_speed_kmh != null ? `${excess.avg_speed_kmh} km/h` : '—'}
            />
            <Info label="Distância percorrida" value={formatMeters(excess?.distance_meters)} />
          </div>
        </section>

        <section className="space-y-3">
          <h3 className="text-sm font-semibold text-foreground">Mapa</h3>
          {historyQuery.isPending ? (
            <p className="text-sm text-muted">Carregando trajeto...</p>
          ) : (
            <RouteReportMap route={route} />
          )}
        </section>
      </div>
    </Modal>
  )
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg bg-surface-2 px-3 py-2">
      <p className="text-[11px] text-muted">{label}</p>
      <p className="font-medium text-foreground">{value}</p>
    </div>
  )
}
