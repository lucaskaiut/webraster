import { RefreshCw } from 'lucide-react'
import { Badge } from '@/shared/design-system'
import { isRealtimeConfigured } from '@/shared/realtime/echo'
import { useRealtimeStore } from '@/shared/realtime/realtime.store'
import { formatUpdatedAt } from '../lib/tracking'

export function MonitoringHeader({
  updating,
  updatedAt,
  onRefresh,
  error,
  variant = 'page',
}: {
  updating: boolean
  updatedAt: number | null
  onRefresh: () => void
  error: string | null
  variant?: 'page' | 'topbar'
}) {
  const realtimeConfigured = isRealtimeConfigured()
  const realtimeConnected = useRealtimeStore((state) => state.connected)

  const statusRow = (
    <>
      {realtimeConfigured && (
        <Badge variant={realtimeConnected ? 'success' : 'neutral'}>
          <span
            className={
              realtimeConnected ? 'size-1.5 rounded-full bg-success' : 'size-1.5 rounded-full bg-subtle'
            }
            aria-hidden="true"
          />
          {realtimeConnected ? 'Tempo real' : 'Tempo real inativo'}
        </Badge>
      )}
      <p className="hidden text-xs text-muted sm:block">
        {updating ? 'Atualizando…' : formatUpdatedAt(updatedAt ? new Date(updatedAt).toISOString() : null)}
      </p>
      <button
        type="button"
        onClick={onRefresh}
        aria-label="Atualizar frota"
        className="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground"
      >
        <RefreshCw className={`size-4 ${updating ? 'animate-spin' : ''}`} />
      </button>
    </>
  )

  if (variant === 'topbar') {
    return (
      <div className="flex min-w-0 flex-1 items-center justify-between gap-3">
        <div className="flex min-w-0 items-center gap-2">
          <span className="truncate text-sm font-semibold tracking-tight text-foreground">Monitoramento</span>
          {error && (
            <span className="hidden truncate text-xs text-warning lg:inline" role="status" title={error}>
              Sem conexão com o servidor
            </span>
          )}
        </div>
        <div className="flex shrink-0 items-center gap-2">{statusRow}</div>
      </div>
    )
  }

  return (
    <header className="flex flex-wrap items-start justify-between gap-3">
      <div className="min-w-0">
        <h1 className="text-lg font-semibold tracking-tight text-foreground">Monitoramento</h1>
        <p className="mt-0.5 text-sm text-muted">Frota ao vivo, histórico de percurso e playback</p>
      </div>

      <div className="flex flex-wrap items-center gap-2">{statusRow}</div>

      {error && (
        <p className="w-full text-sm text-warning" role="status">
          Não foi possível atualizar o servidor de rastreamento. Exibindo a última posição conhecida.
        </p>
      )}
    </header>
  )
}
