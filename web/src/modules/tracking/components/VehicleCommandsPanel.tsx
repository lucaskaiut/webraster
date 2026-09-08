import { useState } from 'react'
import { Button, ConfirmDialog, Spinner, Textarea } from '@/shared/design-system'
import { useDeviceCommandsQuery, useSendDeviceCommand } from '../hooks/useDeviceCommands'
import { commandLabel, isCriticalCommand } from '../lib/command-labels'

export function VehicleCommandsPanel({ deviceId }: { deviceId: string }) {
  const query = useDeviceCommandsQuery(deviceId)
  const send = useSendDeviceCommand(deviceId)
  const [customData, setCustomData] = useState('')
  const [pendingType, setPendingType] = useState<string | null>(null)

  const commands = query.data ?? []
  const customAvailable = commands.some((item) => item.type === 'custom')
  const standardCommands = commands.filter((item) => item.type !== 'custom')

  const execute = (type: string, attributes: Record<string, unknown> = {}) => {
    send.mutate(
      { type, attributes },
      {
        onSuccess: () => {
          if (type === 'custom') setCustomData('')
        },
      },
    )
  }

  const requestSend = (type: string) => {
    if (isCriticalCommand(type)) {
      setPendingType(type)
      return
    }
    execute(type)
  }

  if (query.isPending) {
    return (
      <div className="flex items-center justify-center gap-2 py-8 text-sm text-muted">
        <Spinner className="size-4" />
        Carregando comandos…
      </div>
    )
  }

  if (query.isError) {
    return (
      <div className="space-y-3 py-4 text-sm">
        <p className="text-danger">Não foi possível carregar os comandos deste dispositivo.</p>
        <Button size="sm" variant="secondary" onClick={() => void query.refetch()}>
          Tentar novamente
        </Button>
      </div>
    )
  }

  if (commands.length === 0) {
    return (
      <p className="py-6 text-center text-sm text-muted">
        Nenhum comando disponível para este dispositivo no Traccar.
      </p>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2">
        {standardCommands.map((command) => (
          <Button
            key={command.type}
            size="sm"
            variant="secondary"
            disabled={send.isPending}
            onClick={() => requestSend(command.type)}
          >
            {commandLabel(command.type)}
          </Button>
        ))}
      </div>

      {customAvailable && (
        <div className="space-y-2">
          <label className="block text-[13px] font-medium text-foreground" htmlFor="custom-command">
            {commandLabel('custom')}
          </label>
          <Textarea
            id="custom-command"
            rows={3}
            placeholder="Ex.: RELAY,1# ou DWXX#"
            value={customData}
            onChange={(event) => setCustomData(event.target.value)}
          />
          <Button
            size="sm"
            disabled={send.isPending || customData.trim() === ''}
            onClick={() => execute('custom', { data: customData.trim() })}
          >
            Enviar
          </Button>
        </div>
      )}

      <ConfirmDialog
        open={pendingType !== null}
        onClose={() => setPendingType(null)}
        onConfirm={() => {
          if (!pendingType) return
          const type = pendingType
          setPendingType(null)
          execute(type)
        }}
        loading={send.isPending}
        title="Confirmar comando"
        description={
          <>
            Deseja realmente executar <strong>{pendingType ? commandLabel(pendingType) : ''}</strong>?
          </>
        }
        confirmLabel="Confirmar"
        variant="danger"
      />
    </div>
  )
}
