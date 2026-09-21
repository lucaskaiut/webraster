import { Badge, Button, Skeleton } from '@/shared/design-system'
import { formatDateTimeWithSeconds } from '@/shared/utils/format'
import { useDeviceCommandsHistoryQuery } from '../hooks/useDeviceCommands'
import { commandLabel } from '../lib/command-labels'
import type { DeviceCommandLog } from '../services/device-commands.service'

const STATUS: Record<
  DeviceCommandLog['status'],
  { label: string; variant: 'success' | 'warning' | 'danger' }
> = {
  pending: { label: 'Pendente', variant: 'warning' },
  success: { label: 'Executado', variant: 'success' },
  failed: { label: 'Falhou', variant: 'danger' },
}

export function DeviceCommandHistory({ deviceId }: { deviceId: string }) {
  const query = useDeviceCommandsHistoryQuery(deviceId)

  if (query.isPending) {
    return (
      <div className="space-y-2">
        <Skeleton className="h-12 w-full" />
        <Skeleton className="h-12 w-full" />
      </div>
    )
  }

  if (query.isError) {
    return (
      <div className="space-y-3">
        <p className="text-sm text-danger">Não foi possível carregar o histórico de comandos.</p>
        <Button size="sm" variant="secondary" onClick={() => void query.refetch()}>
          Tentar novamente
        </Button>
      </div>
    )
  }

  const logs = query.data ?? []

  if (logs.length === 0) {
    return <p className="text-sm text-muted">Nenhum comando enviado para este equipamento.</p>
  }

  return (
    <ul className="space-y-2">
      {logs.map((log) => {
        const status = STATUS[log.status]
        const data = typeof log.payload?.data === 'string' ? log.payload.data : null

        return (
          <li
            key={log.id}
            className="flex flex-wrap items-start justify-between gap-2 rounded-lg bg-surface-2 px-3 py-2"
          >
            <div className="min-w-0">
              <p className="text-sm font-medium text-foreground">{commandLabel(log.command_type)}</p>
              <p className="text-xs text-muted">
                Solicitado em {formatDateTimeWithSeconds(log.requested_at)}
                {log.executed_at
                  ? ` · Executado em ${formatDateTimeWithSeconds(log.executed_at)}`
                  : ''}
              </p>
              {data && <p className="mt-0.5 truncate text-xs text-muted">Dados: {data}</p>}
            </div>
            <Badge variant={status.variant}>{status.label}</Badge>
          </li>
        )
      })}
    </ul>
  )
}
