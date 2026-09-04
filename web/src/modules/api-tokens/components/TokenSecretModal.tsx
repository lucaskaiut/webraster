import { useState } from 'react'
import { Check, Copy } from 'lucide-react'
import { Alert, Button, Modal } from '@/shared/design-system'
import { toast } from '@/shared/stores/toast.store'

export function TokenSecretModal({
  token,
  open,
  onClose,
}: {
  token: string | null
  open: boolean
  onClose: () => void
}) {
  const [copied, setCopied] = useState(false)

  const copy = async () => {
    if (!token) return

    try {
      await navigator.clipboard.writeText(token)
      setCopied(true)
      window.setTimeout(() => setCopied(false), 2000)
    } catch {
      toast.error('Não foi possível copiar', 'Copie o token manualmente.')
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Token criado com sucesso"
      description="Copie e guarde o token em um local seguro."
      footer={
        <Button variant="secondary" onClick={onClose}>
          Concluir
        </Button>
      }
    >
      <div className="space-y-4">
        <Alert variant="warning" title="Este token não será exibido novamente">
          Por segurança, armazenamos apenas uma versão criptografada. Se você perdê-lo, será
          necessário gerar um novo token.
        </Alert>

        <div className="flex items-center gap-2 rounded-xl bg-surface-2 p-3">
          <code className="min-w-0 flex-1 font-mono text-[13px] break-all text-foreground">
            {token}
          </code>
          <Button variant="secondary" size="sm" onClick={copy} aria-label="Copiar token">
            {copied ? <Check className="size-4 text-success" /> : <Copy className="size-4" />}
            {copied ? 'Copiado' : 'Copiar'}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
