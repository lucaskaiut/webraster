import { useCallback, useEffect, useRef, useState } from 'react'
import { Button, Modal, Spinner } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import { crmService } from '../services/crm.service'
import type { EvolutionConnectPayload, MessagingConnection } from '../lib/types'

type Step = 'qrcode' | 'connected'

interface EvolutionConnectModalProps {
  open: boolean
  onClose: () => void
  connection?: MessagingConnection | null
  onSuccess: () => void
}

function isConnected(state: string | undefined, status: string | undefined): boolean {
  return state === 'open' || state === 'connected' || status === 'connected'
}

export function EvolutionConnectModal({
  open,
  onClose,
  connection: existingConnection,
  onSuccess,
}: EvolutionConnectModalProps) {
  const [step, setStep] = useState<Step>('qrcode')
  const [connection, setConnection] = useState<MessagingConnection | null>(null)
  const [connect, setConnect] = useState<EvolutionConnectPayload | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const bootstrapStarted = useRef(false)

  const activeConnection = connection ?? existingConnection ?? null

  const applyConnectResult = useCallback(
    (payload: { connection: MessagingConnection; connect: EvolutionConnectPayload }) => {
      setConnection(payload.connection)
      setConnect(payload.connect)

      if (isConnected(payload.connect.state, payload.connection.connection_status)) {
        setStep('connected')
        return
      }

      setStep('qrcode')
    },
    [],
  )

  const startConnect = useCallback(async (target: MessagingConnection) => {
    setLoading(true)
    setError(null)
    try {
      const result = await crmService.evolutionConnect(target.id)
      applyConnectResult(result)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Não foi possível obter o QR Code.')
    } finally {
      setLoading(false)
    }
  }, [applyConnectResult])

  const runBootstrap = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const result = await crmService.evolutionBootstrap()
      applyConnectResult(result)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Falha ao criar a instância na Evolution.')
    } finally {
      setLoading(false)
    }
  }, [applyConnectResult])

  useEffect(() => {
    if (!open) {
      bootstrapStarted.current = false
      setStep('qrcode')
      setConnection(null)
      setConnect(null)
      setError(null)
      setLoading(false)
      return
    }

    if (existingConnection) {
      if (isConnected(undefined, existingConnection.connection_status)) {
        setStep('connected')
        setConnection(existingConnection)
      } else {
        void startConnect(existingConnection)
      }
      return
    }

    if (!bootstrapStarted.current) {
      bootstrapStarted.current = true
      void runBootstrap()
    }
  }, [open, existingConnection, startConnect, runBootstrap])

  useEffect(() => {
    if (!open || step !== 'qrcode' || !activeConnection) {
      return
    }

    const interval = window.setInterval(async () => {
      try {
        const result = await crmService.evolutionState(activeConnection.id)
        setConnection(result.connection)
        setConnect(result.connect)

        if (isConnected(result.connect.state, result.connection.connection_status)) {
          setStep('connected')
        } else if (result.connect.qrcode_base64) {
          setConnect(result.connect)
        }
      } catch {
        // mantém polling silencioso
      }
    }, 4000)

    return () => window.clearInterval(interval)
  }, [open, step, activeConnection])

  const handleClose = () => {
    if (step === 'connected') {
      onSuccess()
    }
    onClose()
  }

  return (
    <Modal
      open={open}
      onClose={handleClose}
      title="Conectar WhatsApp (Evolution)"
      description="A instância é criada automaticamente. Escaneie o QR Code para conectar."
      size="md"
      footer={
        step === 'qrcode' ? (
          <>
            <Button variant="secondary" onClick={onClose}>
              Fechar
            </Button>
            <Button
              onClick={() => activeConnection && void startConnect(activeConnection)}
              disabled={loading || !activeConnection}
            >
              Atualizar QR Code
            </Button>
          </>
        ) : (
          <Button onClick={handleClose}>Concluir</Button>
        )
      }
    >
      {error ? (
        <p className="mb-4 rounded-xl bg-danger/10 px-3 py-2 text-sm text-danger">{error}</p>
      ) : null}

      {step === 'qrcode' && (
        <div className="flex flex-col items-center gap-4 py-2">
          {loading && !connect?.qrcode_base64 ? (
            <div className="flex flex-col items-center gap-2 py-8">
              <Spinner />
              <p className="text-sm text-muted">Criando instância e gerando QR Code…</p>
            </div>
          ) : connect?.qrcode_base64 ? (
            <img
              src={connect.qrcode_base64}
              alt="QR Code WhatsApp"
              className="size-64 rounded-xl bg-white p-2 shadow-card"
            />
          ) : (
            <div className="flex flex-col items-center gap-2 py-8 text-center text-sm text-muted">
              <Spinner />
              <p>Aguardando QR Code…</p>
            </div>
          )}
          {activeConnection?.instance_name ? (
            <p className="text-xs text-muted">Instância: {activeConnection.instance_name}</p>
          ) : null}
          <div className="text-center text-sm text-muted">
            <p>No celular: WhatsApp → Dispositivos conectados → Conectar dispositivo.</p>
            <p className="mt-1">O QR Code expira em cerca de 60 segundos. Use &quot;Atualizar QR Code&quot; se necessário.</p>
            {connect?.pairing_code ? (
              <p className="mt-2 font-mono text-foreground">Código: {connect.pairing_code}</p>
            ) : null}
          </div>
        </div>
      )}

      {step === 'connected' && (
        <div className={cn('rounded-xl bg-surface-2/80 px-4 py-6 text-center')}>
          <p className="font-medium text-foreground">WhatsApp conectado</p>
          <p className="mt-1 text-sm text-muted">
            Instância {activeConnection?.instance_name ?? '—'} pronta para receber mensagens.
          </p>
        </div>
      )}
    </Modal>
  )
}
