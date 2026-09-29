import { useRef } from 'react'
import { Code, ImagePlus, Trash2 } from 'lucide-react'
import { apiErrorMessage, isApiError } from '@/shared/api/errors'
import { MAX_UPLOAD_BYTES, MAX_UPLOAD_LABEL, uploadFile } from '@/shared/api/uploads'
import { toast } from '@/shared/stores/toast.store'
import { Button } from './Button'
import { Field } from './Field'
import { cn } from '@/shared/utils/cn'

export interface ImageValue {
  url: string
  path?: string
  alt?: string
}

interface ImageUploaderProps {
  value: ImageValue | null
  onChange: (value: ImageValue | null) => void
  label?: string
  hint?: string
  error?: string
  className?: string
  uploadUrl?: string
  accept?: string
}

/**
 * Upload + preview de imagem. Reutilizável por posts, produtos, banners, etc.
 * Envia o arquivo para o endpoint de upload e retorna {url, path}.
 */
export function ImageUploader({
  value,
  onChange,
  label = 'Imagem destacada',
  hint,
  error,
  className,
  uploadUrl = '/uploads',
  accept = 'image/*',
}: ImageUploaderProps) {
  const inputRef = useRef<HTMLInputElement>(null)

  const handleFileChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return

    if (file.size > MAX_UPLOAD_BYTES) {
      toast.error('Arquivo muito grande', `O limite para envio é de ${MAX_UPLOAD_LABEL}.`)
      return
    }

    try {
      const { url, path } = await uploadFile(file, uploadUrl)
      onChange({ url, path, alt: value?.alt ?? '' })
    } catch (uploadError) {
      toast.error(
        'Falha ao enviar imagem',
        isApiError(uploadError)
          ? apiErrorMessage(uploadError)
          : 'Não foi possível enviar a imagem. Tente novamente.',
      )
    }
  }

  const remove = () => onChange(null)

  return (
    <Field label={label} hint={hint} error={error} className={className}>
      {value?.url ? (
        <div className="relative overflow-hidden rounded-xl bg-surface-2">
          <img
            src={value.url}
            alt={value.alt || ''}
            className="max-h-64 w-full object-cover"
          />
          <div className="absolute right-2 bottom-2 flex gap-1.5">
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={remove}
              className="shadow-raised"
            >
              <Trash2 className="size-3.5" />
              Remover
            </Button>
          </div>
        </div>
      ) : (
        <>
          <input
            ref={inputRef}
            type="file"
            accept={accept}
            className="hidden"
            onChange={handleFileChange}
          />
          <button
            type="button"
            disabled={!uploadUrl}
            onClick={() => inputRef.current?.click()}
            className={cn(
              'flex w-full cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-surface-3 p-10 transition-colors hover:border-primary/50',
              !uploadUrl && 'cursor-default',
            )}
          >
            {uploadUrl ? (
              <>
                <ImagePlus className="size-9 text-subtle" aria-hidden="true" />
                <span className="text-sm text-muted">Clique para selecionar uma imagem</span>
              </>
            ) : (
              <>
                <Code className="size-9 text-subtle" aria-hidden="true" />
                <span className="text-sm text-muted">Cole a URL da imagem no campo abaixo</span>
              </>
            )}
          </button>
        </>
      )}
    </Field>
  )
}
