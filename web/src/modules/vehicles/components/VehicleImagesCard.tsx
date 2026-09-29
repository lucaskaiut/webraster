import { useRef, useState } from 'react'
import { ImagePlus, Trash2 } from 'lucide-react'
import { Button, Card, CardContent, CardHeader, Spinner } from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { apiErrorMessage, isApiError } from '@/shared/api/errors'
import { MAX_UPLOAD_BYTES, MAX_UPLOAD_LABEL, uploadFile } from '@/shared/api/uploads'
import { toast } from '@/shared/stores/toast.store'
import type { Vehicle } from '@/shared/types/models'
import { useAddVehicleImage, useRemoveVehicleImage } from '../hooks/useVehicles'

export function VehicleImagesCard({ vehicle }: { vehicle: Vehicle }) {
  const { can } = usePermissions()
  const inputRef = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)
  const addImage = useAddVehicleImage(vehicle.id)
  const removeImage = useRemoveVehicleImage(vehicle.id)
  const images = vehicle.images ?? []
  const canManage = can(Permission.VEHICLE_UPDATE)

  const handleFiles = async (files: FileList | null) => {
    if (!files || files.length === 0) return

    const selected = Array.from(files)
    if (inputRef.current) {
      inputRef.current.value = ''
    }

    if (selected.some((file) => file.size > MAX_UPLOAD_BYTES)) {
      toast.error('Arquivo muito grande', `O limite para envio é de ${MAX_UPLOAD_LABEL}.`)
      return
    }

    setUploading(true)

    try {
      for (const file of selected) {
        const { path } = await uploadFile(file)
        await addImage.mutateAsync(path)
      }
    } catch (error) {
      toast.error(
        'Falha ao enviar imagem',
        isApiError(error)
          ? apiErrorMessage(error)
          : 'Não foi possível enviar a imagem. Tente novamente.',
      )
    } finally {
      setUploading(false)
    }
  }

  return (
    <Card>
      <CardHeader
        title="Fotos"
        description="Imagens exibidas no painel de monitoramento do veículo."
      />
      <CardContent className="space-y-4">
        {images.length === 0 && (
          <p className="text-sm text-muted">Nenhuma foto cadastrada.</p>
        )}

        <div className="flex flex-wrap gap-3">
          {images.map((image) => (
            <div
              key={image.id}
              className="relative h-24 w-40 overflow-hidden rounded-xl bg-surface-2 shadow-card"
            >
              <img src={image.url} alt="" className="h-full w-full object-cover" />
              {canManage && (
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => removeImage.mutate(image.id)}
                  loading={removeImage.isPending && removeImage.variables === image.id}
                  aria-label="Remover foto"
                  className="absolute right-1.5 bottom-1.5 px-2 shadow-raised"
                >
                  <Trash2 className="size-3.5" aria-hidden="true" />
                </Button>
              )}
            </div>
          ))}

          {canManage && (
            <>
              <button
                type="button"
                disabled={uploading}
                onClick={() => inputRef.current?.click()}
                className="flex h-24 w-40 cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl bg-surface-2 text-muted transition-colors hover:bg-surface-3 disabled:cursor-default disabled:opacity-60"
              >
                {uploading ? (
                  <Spinner className="size-5" />
                ) : (
                  <ImagePlus className="size-5" aria-hidden="true" />
                )}
                <span className="text-xs font-medium">
                  {uploading ? 'Enviando...' : 'Adicionar foto'}
                </span>
              </button>
              <input
                ref={inputRef}
                type="file"
                accept="image/png,image/jpeg,image/webp"
                multiple
                className="hidden"
                onChange={(event) => void handleFiles(event.target.files)}
              />
            </>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
