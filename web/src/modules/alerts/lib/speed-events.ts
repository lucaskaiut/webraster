import type { DerivedEvent } from '@/modules/tracking/lib/tracking'
import type { Alert } from '@/shared/types/models'

export function speedAlertsToTimelineEvents(alerts: Alert[]): DerivedEvent[] {
  return alerts
    .filter((alert) => alert.type === 'speed' && alert.speed_excess)
    .map((alert) => {
      const excess = alert.speed_excess!
      const max = excess.max_speed_kmh ?? alert.speed_kmh

      return {
        id: alert.id,
        at: excess.started_at,
        label: `Excesso de velocidade${max != null ? ` · máx. ${Math.round(max)} km/h` : ''} · ${formatDurationLabel(excess.duration_seconds)}`,
        tone: 'warning' as const,
      }
    })
    .sort((a, b) => new Date(a.at).getTime() - new Date(b.at).getTime())
}

function formatDurationLabel(seconds: number | null | undefined): string {
  if (seconds == null) return 'duração não informada'

  if (seconds < 60) return `${seconds}s`

  const minutes = Math.floor(seconds / 60)
  const rest = seconds % 60

  return rest > 0 ? `${minutes}min ${rest}s` : `${minutes}min`
}
