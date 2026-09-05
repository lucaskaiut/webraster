import { RefreshCw } from 'lucide-react'
import { Badge } from '@/shared/design-system'
import { formatUpdatedAt } from '../lib/tracking'

export function MonitoringHeader({
  connected,
  updating,
  updatedAt,
  onRefresh,
  error,
}: {
  connected: boolean | null
  updating: boolean
  updatedAt: number | null
  onRefresh: () => void
  error: string | null
}) {
  return (
    <header className="flex flex-wrap items-start justify-between gap-3">
      <div className="min-w-0">
        <h1 className="text-lg font-semibold tracking-tight text-foreground">Monitoramento</h1>
        <p className="mt-0.5 text-sm text-muted">Frota ao vivo, histórico de percurso e playback</p>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        {connected !== null && (
          <Badge variant={connected ? 'success' : 'warning'}>
            <span
              className={connected ? 'size-1.5 rounded-full bg-success' : 'size-1.5 rounded-full bg-warning'}
              aria-hidden="true"
            />
            {connected ? 'Traccar conectado' : 'Traccar desconectado'}
          </Badge>
        )}
        <p className="text-xs text-muted">
          {updating ? 'Atualizando…' : formatUpdatedAt(updatedAt ? new Date(updatedAt).toISOString() : null)}
        </p>
        <button
          type="button"
          onClick={onRefresh}
          aria-label="Atualizar frota"
          className="flex size-8 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground"
        >
          <RefreshCw className={`size-4 ${updating ? 'animate-spin' : ''}`} />
        </button>
      </div>

      {error && (
        <p className="w-full text-sm text-warning" role="status">
          Não foi possível atualizar o servidor de rastreamento. Exibindo a última posição conhecida.
        </p>
      )}
    </header>
  )
}
