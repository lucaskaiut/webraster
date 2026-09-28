import { useRef, useState } from 'react'
import { Controller, useFormContext } from 'react-hook-form'
import { Trash2 } from 'lucide-react'
import {
  Button,
  Field,
  ImageUploader,
  SegmentedControl,
  SignaturePad,
  type ImageValue,
  type SignaturePadHandle,
} from '@/shared/design-system'
import { http } from '@/shared/api/http'
import type { TenantSettingsFormValues } from '../schemas/tenant.schema'

type SignatureMode = 'draw' | 'upload'

const MODES: Array<{ value: SignatureMode; label: string }> = [
  { value: 'draw', label: 'Desenhar' },
  { value: 'upload', label: 'Enviar imagem' },
]

export function SignatureField({ initialUrl }: { initialUrl?: string | null }) {
  const { control } = useFormContext<TenantSettingsFormValues>()
  const padRef = useRef<SignaturePadHandle>(null)
  const [mode, setMode] = useState<SignatureMode>('draw')
  const [value, setValue] = useState<ImageValue | null>(
    initialUrl ? { url: initialUrl, alt: 'Assinatura do responsável' } : null,
  )
  const [uploading, setUploading] = useState(false)
  const [empty, setEmpty] = useState(false)

  const upload = async (file: Blob, filename: string): Promise<ImageValue> => {
    const formData = new FormData()
    formData.append('file', file, filename)

    const response = await http.post<{ data: { url: string; path: string } }>('/uploads', formData)

    return {
      url: response.data.data.url,
      path: response.data.data.path,
      alt: 'Assinatura do responsável',
    }
  }

  return (
    <Controller
      control={control}
      name="signature_path"
      render={({ field, fieldState }) => {
        const handleChange = (next: ImageValue | null) => {
          setValue(next)
          field.onChange(next?.path ?? null)
        }

        const handleUseDrawn = async () => {
          const dataUrl = padRef.current?.toDataURL() ?? null

          if (!dataUrl) {
            setEmpty(true)
            return
          }

          setEmpty(false)
          setUploading(true)

          try {
            const blob = await (await fetch(dataUrl)).blob()
            handleChange(await upload(blob, 'assinatura.png'))
          } finally {
            setUploading(false)
          }
        }

        if (value?.url) {
          return (
            <Field
              label="Assinatura do responsável"
              hint="Usada nos contratos pela variável {{ASSINATURA_EMPRESA}}."
              error={fieldState.error?.message}
            >
              <div className="relative overflow-hidden rounded-xl bg-surface-2 dark:bg-white">
                <img
                  src={value.url}
                  alt={value.alt ?? 'Assinatura do responsável'}
                  className="max-h-40 w-full object-contain p-4"
                />
                <div className="absolute right-2 bottom-2">
                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    className="shadow-raised"
                    onClick={() => handleChange(null)}
                  >
                    <Trash2 className="size-3.5" />
                    Remover
                  </Button>
                </div>
              </div>
            </Field>
          )
        }

        return (
          <div className="space-y-3">
            <Field
              label="Assinatura do responsável"
              hint="Assine com o mouse ou o dedo, ou envie uma imagem. Usada nos contratos pela variável {{ASSINATURA_EMPRESA}}."
              error={fieldState.error?.message}
            >
              <SegmentedControl value={mode} options={MODES} onChange={setMode} />
            </Field>

            {mode === 'draw' ? (
              <div className="space-y-3">
                <SignaturePad ref={padRef} />
                {empty && (
                  <p className="text-[13px] text-danger" role="alert">
                    Desenhe a assinatura antes de continuar.
                  </p>
                )}
                <div className="flex justify-end">
                  <Button
                    type="button"
                    variant="primary"
                    size="sm"
                    loading={uploading}
                    onClick={() => {
                      void handleUseDrawn().catch(() => undefined)
                    }}
                  >
                    Usar assinatura
                  </Button>
                </div>
              </div>
            ) : (
              <ImageUploader
                label="Arquivo da assinatura"
                hint="PNG, JPG ou WEBP com a assinatura do responsável."
                value={value}
                onChange={handleChange}
              />
            )}
          </div>
        )
      }}
    />
  )
}
