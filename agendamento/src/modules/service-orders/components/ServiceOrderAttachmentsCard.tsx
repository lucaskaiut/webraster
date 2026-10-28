import { useRef, useState } from 'react'
import { Download, FileText, Paperclip, Trash2 } from 'lucide-react'
import { Button, Card, CardContent, CardHeader, ConfirmDialog } from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { apiErrorMessage, isApiError } from '@/shared/api/errors'
import { toast } from '@/shared/stores/toast.store'
import { formatDateTime } from '@/shared/utils/format'
import { downloadBlob } from '@/shared/utils/report-export'
import type { ServiceOrder, ServiceOrderAttachment } from '@/shared/types/models'
import { serviceOrdersService } from '../services/service-orders.service'
import {
  useRemoveServiceOrderAttachment,
  useUploadServiceOrderAttachment,
} from '../hooks/useServiceOrders'

const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024
const ACCEPT = '.pdf,.jpg,.jpeg,.png,.webp,.heic,.doc,.docx,.xls,.xlsx,.txt'

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`
}

export function ServiceOrderAttachmentsCard({ order }: { order: ServiceOrder }) {
  const { can } = usePermissions()
  const inputRef = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)
  const [downloadingId, setDownloadingId] = useState<string | null>(null)
  const [toRemove, setToRemove] = useState<ServiceOrderAttachment | null>(null)
  const upload = useUploadServiceOrderAttachment(order.id)
  const remove = useRemoveServiceOrderAttachment(order.id)
  const attachments = order.attachments ?? []
  const canManage = can(Permission.SERVICE_ORDER_UPDATE) && order.status !== 'cancelled'

  const handleFiles = async (files: FileList | null) => {
    if (!files || files.length === 0) return

    const selected = Array.from(files)
    if (inputRef.current) {
      inputRef.current.value = ''
    }

    if (selected.some((file) => file.size > MAX_ATTACHMENT_BYTES)) {
      toast.error('Arquivo muito grande', 'O limite por anexo é de 10 MB.')
      return
    }

    setUploading(true)

    try {
      for (const file of selected) {
        await upload.mutateAsync(file)
      }
      toast.success(selected.length > 1 ? 'Anexos enviados' : 'Anexo enviado')
    } catch (error) {
      toast.error(
        'Falha ao enviar anexo',
        isApiError(error) ? apiErrorMessage(error) : 'Não foi possível enviar o arquivo.',
      )
    } finally {
      setUploading(false)
    }
  }

  const handleDownload = async (attachment: ServiceOrderAttachment) => {
    setDownloadingId(attachment.id)

    try {
      downloadBlob(await serviceOrdersService.downloadAttachment(order.id, attachment.id), attachment.name)
    } catch {
      toast.error('Não foi possível baixar o anexo.')
    } finally {
      setDownloadingId(null)
    }
  }

  return (
    <Card>
      <CardHeader
        title="Anexos"
        description="Documentos da OS: PDF, imagens, DOC e planilhas (até 10 MB)."
        actions={
          canManage ? (
            <Button
              size="sm"
              variant="secondary"
              loading={uploading}
              onClick={() => inputRef.current?.click()}
            >
              <Paperclip className="size-4" />
              Anexar
            </Button>
          ) : undefined
        }
      />
      <CardContent className="space-y-2">
        {attachments.length === 0 && <p className="text-sm text-muted">Nenhum anexo enviado.</p>}

        {attachments.map((attachment) => (
          <div
            key={attachment.id}
            className="flex items-center gap-3 rounded-lg bg-surface-2/60 px-3 py-2 text-sm"
          >
            <FileText className="size-4 shrink-0 text-muted" aria-hidden="true" />
            <div className="min-w-0 flex-1">
              <p className="truncate font-medium text-foreground">{attachment.name}</p>
              <p className="truncate text-xs text-muted">
                {formatBytes(attachment.size_bytes)}
                {attachment.uploaded_by ? ` · ${attachment.uploaded_by.name}` : ''}
                {attachment.created_at ? ` · ${formatDateTime(attachment.created_at)}` : ''}
              </p>
            </div>
            <Button
              variant="ghost"
              size="sm"
              loading={downloadingId === attachment.id}
              onClick={() => void handleDownload(attachment)}
              aria-label={`Baixar ${attachment.name}`}
            >
              <Download className="size-4" />
            </Button>
            {canManage && (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setToRemove(attachment)}
                aria-label={`Remover ${attachment.name}`}
                className="text-danger hover:bg-danger-soft hover:text-danger"
              >
                <Trash2 className="size-4" />
              </Button>
            )}
          </div>
        ))}

        <input
          ref={inputRef}
          type="file"
          accept={ACCEPT}
          multiple
          className="hidden"
          onChange={(event) => void handleFiles(event.target.files)}
        />
      </CardContent>

      <ConfirmDialog
        open={toRemove !== null}
        onClose={() => setToRemove(null)}
        onConfirm={() => {
          if (toRemove) remove.mutate(toRemove.id, { onSettled: () => setToRemove(null) })
        }}
        loading={remove.isPending}
        title="Remover anexo"
        description={
          <>
            Remover <strong>{toRemove?.name}</strong> desta OS?
          </>
        }
        confirmLabel="Remover"
      />
    </Card>
  )
}
