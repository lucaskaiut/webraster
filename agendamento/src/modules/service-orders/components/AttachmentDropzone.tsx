import { useId, useState, type DragEvent } from 'react'
import { FileText, Trash2, UploadCloud } from 'lucide-react'
import { Button, Field } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'

export const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024
export const MAX_ATTACHMENTS = 10
const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'doc', 'docx', 'xls', 'xlsx', 'txt']
const ACCEPT = ALLOWED_EXTENSIONS.map((extension) => `.${extension}`).join(',')

function extensionOf(name: string): string {
  return name.split('.').pop()?.toLowerCase() ?? ''
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`
}

interface AttachmentDropzoneProps {
  files: File[]
  onChange: (files: File[]) => void
  label?: string
  hint?: string
  error?: string | null
  disabled?: boolean
  className?: string
}

/**
 * Seleção de documentos por arrastar e soltar (ou clique). Os arquivos ficam
 * em memória e são enviados junto com a OS.
 */
export function AttachmentDropzone({
  files,
  onChange,
  label = 'Documento',
  hint = 'Opcional · PDF, imagens, Word, Excel ou texto, até 10 MB por arquivo.',
  error,
  disabled = false,
  className,
}: AttachmentDropzoneProps) {
  const inputId = useId()
  const [dragging, setDragging] = useState(false)
  const [rejection, setRejection] = useState<string | null>(null)

  const addFiles = (incoming: FileList | null) => {
    if (!incoming || incoming.length === 0) return

    const selected = Array.from(incoming)
    const invalid = selected.find((file) => !ALLOWED_EXTENSIONS.includes(extensionOf(file.name)))
    const tooLarge = selected.find((file) => file.size > MAX_ATTACHMENT_BYTES)

    if (invalid) {
      setRejection(`Formato não permitido: ${invalid.name}.`)
      return
    }

    if (tooLarge) {
      setRejection(`${tooLarge.name} ultrapassa o limite de 10 MB.`)
      return
    }

    if (files.length + selected.length > MAX_ATTACHMENTS) {
      setRejection(`Envie no máximo ${MAX_ATTACHMENTS} arquivos.`)
      return
    }

    setRejection(null)
    onChange([...files, ...selected])
  }

  const handleDrop = (event: DragEvent<HTMLLabelElement>) => {
    event.preventDefault()
    setDragging(false)
    if (!disabled) addFiles(event.dataTransfer.files)
  }

  const remove = (index: number) => {
    setRejection(null)
    onChange(files.filter((_, current) => current !== index))
  }

  return (
    <Field label={label} hint={hint} error={rejection ?? error ?? undefined} className={className}>
      <div className="space-y-2">
        <label
          htmlFor={inputId}
          onDragOver={(event) => {
            event.preventDefault()
            if (!disabled) setDragging(true)
          }}
          onDragLeave={() => setDragging(false)}
          onDrop={handleDrop}
          className={cn(
            'flex w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-xl px-6 py-8 text-center transition-colors',
            dragging ? 'bg-primary-soft text-primary' : 'bg-surface-2/60 text-muted hover:bg-surface-2',
            disabled && 'cursor-not-allowed opacity-60',
          )}
        >
          <UploadCloud className="size-8" aria-hidden="true" />
          <span className="text-sm font-medium text-foreground">
            {dragging ? 'Solte o arquivo aqui' : 'Arraste o documento ou clique para selecionar'}
          </span>
          <span className="text-xs">PDF, JPG, PNG, WEBP, HEIC, DOC, DOCX, XLS, XLSX ou TXT</span>
        </label>
        <input
          id={inputId}
          type="file"
          accept={ACCEPT}
          multiple
          disabled={disabled}
          className="sr-only"
          onChange={(event) => {
            addFiles(event.target.files)
            event.target.value = ''
          }}
        />

        {files.map((file, index) => (
          <div
            key={`${file.name}-${file.size}-${file.lastModified}`}
            className="flex items-center gap-3 rounded-lg bg-surface-2/60 px-3 py-2 text-sm"
          >
            <FileText className="size-4 shrink-0 text-muted" aria-hidden="true" />
            <div className="min-w-0 flex-1">
              <p className="truncate font-medium text-foreground">{file.name}</p>
              <p className="text-xs text-muted">{formatBytes(file.size)}</p>
            </div>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              disabled={disabled}
              onClick={() => remove(index)}
              aria-label={`Remover ${file.name}`}
              className="text-danger hover:bg-danger-soft hover:text-danger"
            >
              <Trash2 className="size-4" />
            </Button>
          </div>
        ))}
      </div>
    </Field>
  )
}
