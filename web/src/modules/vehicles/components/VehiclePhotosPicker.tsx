import { useEffect, useMemo, useRef } from 'react'
import { ImagePlus, Trash2 } from 'lucide-react'
import { Button } from '@/shared/design-system'
import { MAX_UPLOAD_BYTES, MAX_UPLOAD_LABEL } from '@/shared/api/uploads'
import { toast } from '@/shared/stores/toast.store'

interface VehiclePhotosPickerProps {
  value: File[]
  onChange: (files: File[]) => void
  disabled?: boolean
}

/**
 * Seleção local de fotos para um veículo ainda não cadastrado. Os arquivos
 * são enviados após o cadastro (ver `useCreateVehicle`).
 */
export function VehiclePhotosPicker({ value, onChange, disabled }: VehiclePhotosPickerProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const previews = useMemo(() => value.map((file) => URL.createObjectURL(file)), [value])

  useEffect(() => () => previews.forEach((url) => URL.revokeObjectURL(url)), [previews])

  const handleFiles = (files: FileList | null) => {
    if (!files || files.length === 0) return

    const selected = Array.from(files)
    if (inputRef.current) {
      inputRef.current.value = ''
    }

    const accepted = selected.filter((file) => file.size <= MAX_UPLOAD_BYTES)
    if (accepted.length < selected.length) {
      toast.error('Arquivo muito grande', `O limite para envio é de ${MAX_UPLOAD_LABEL}.`)
    }

    if (accepted.length > 0) {
      onChange([...value, ...accepted])
    }
  }

  return (
    <div className="flex flex-wrap gap-3">
      {value.map((file, index) => (
        <div
          key={`${file.name}-${file.lastModified}-${index}`}
          className="relative h-24 w-40 overflow-hidden rounded-xl bg-surface-2 shadow-card"
        >
          <img src={previews[index]} alt={file.name} className="h-full w-full object-cover" />
          <Button
            type="button"
            variant="secondary"
            size="sm"
            disabled={disabled}
            onClick={() => onChange(value.filter((_, i) => i !== index))}
            aria-label="Remover foto"
            className="absolute right-1.5 bottom-1.5 px-2 shadow-raised"
          >
            <Trash2 className="size-3.5" aria-hidden="true" />
          </Button>
        </div>
      ))}

      <button
        type="button"
        disabled={disabled}
        onClick={() => inputRef.current?.click()}
        className="flex h-24 w-40 cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl bg-surface-2 text-muted transition-colors hover:bg-surface-3 disabled:cursor-default disabled:opacity-60"
      >
        <ImagePlus className="size-5" aria-hidden="true" />
        <span className="text-xs font-medium">Adicionar foto</span>
      </button>
      <input
        ref={inputRef}
        type="file"
        accept="image/png,image/jpeg,image/webp"
        multiple
        className="hidden"
        onChange={(event) => handleFiles(event.target.files)}
      />
    </div>
  )
}
