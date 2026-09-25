import { Code, ImagePlus, Trash2 } from 'lucide-react'
import { http } from '@/shared/api/http'
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
  const upload = async (file: File) => {
    const formData = new FormData()
    formData.append('file', file)

    const response = await http.post<{ data: { url: string; path: string } }>(uploadUrl, formData)
    const { url, path } = response.data.data

    onChange({ url, path, alt: value?.alt ?? '' })
  }

  const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    if (!file) return

    upload(file).catch((err) => console.error('Upload failed', err))
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
        <label
          className={cn(
            'flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-surface-3 p-10 transition-colors hover:border-primary/50',
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
          <input type="file" accept={accept} className="sr-only" onChange={handleFileChange} />
        </label>
      )}
    </Field>
  )
}
