import { useEffect, useMemo, useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Check, Copy, ExternalLink, Link2, MessageCircle } from 'lucide-react'
import { Button, Checkbox, Field, Input, Modal, Select } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { toast } from '@/shared/stores/toast.store'
import { formatDateTime } from '@/shared/utils/format'
import type { TrackingShareLink } from '@/shared/types/models'
import { useTrackingLiveQuery } from '../hooks/useTracking'
import { trackingService } from '../services/tracking.service'

export interface ShareableVehicle {
  id: string
  plate: string
  brand?: string | null
  model?: string | null
}

const DURATION_OPTIONS = [1, 2, 4, 8, 12, 24].map((hours) => ({
  value: String(hours),
  label: hours === 1 ? '1 hora' : `${hours} horas`,
}))

function publicTrackingUrl(token: string): string {
  return `${window.location.origin}/acompanhar/${token}`
}

function vehicleLabel(vehicle: ShareableVehicle): string {
  return [vehicle.plate, vehicle.brand, vehicle.model].filter(Boolean).join(' · ')
}

/**
 * Gera um link público e temporário (até 24h) que mostra apenas o mapa com
 * os veículos selecionados.
 */
export function ShareTrackingModal({
  open,
  onClose,
  initialVehicles,
}: {
  open: boolean
  onClose: () => void
  /** Veículos já marcados ao abrir (ex.: o veículo clicado). */
  initialVehicles: ShareableVehicle[]
}) {
  const liveQuery = useTrackingLiveQuery(open)
  const [selectedIds, setSelectedIds] = useState<string[]>([])
  const [hours, setHours] = useState('4')
  const [search, setSearch] = useState('')
  const [link, setLink] = useState<TrackingShareLink | null>(null)
  const [copied, setCopied] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const initialKey = initialVehicles.map((vehicle) => vehicle.id).join(',')

  // Reinicia o formulário a cada abertura.
  useEffect(() => {
    if (!open) return
    setSelectedIds(initialKey ? initialKey.split(',') : [])
    setHours('4')
    setSearch('')
    setLink(null)
    setCopied(false)
    setError(null)
  }, [open, initialKey])

  const options = useMemo(() => {
    const byId = new Map<string, ShareableVehicle>()
    initialVehicles.forEach((vehicle) => byId.set(vehicle.id, vehicle))
    ;(liveQuery.data ?? []).forEach((vehicle) => {
      if (!byId.has(vehicle.id)) byId.set(vehicle.id, vehicle)
    })
    return [...byId.values()]
  }, [initialVehicles, liveQuery.data])

  const term = search.trim().toLowerCase()
  const visible = term
    ? options.filter((vehicle) => vehicleLabel(vehicle).toLowerCase().includes(term))
    : options

  const create = useMutation({
    mutationFn: trackingService.createShareLink,
    onSuccess: (data) => setLink(data),
    onError: (err) => {
      setError(isApiError(err) ? err.message : 'Não foi possível gerar o link.')
    },
  })

  const url = link ? publicTrackingUrl(link.token) : ''

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(url)
      setCopied(true)
      toast.success('Link copiado')
      window.setTimeout(() => setCopied(false), 2000)
    } catch {
      toast.error('Não foi possível copiar', 'Selecione o link e copie manualmente.')
    }
  }

  const toggle = (id: string, checked: boolean) => {
    setSelectedIds((current) => (checked ? [...current, id] : current.filter((item) => item !== id)))
  }

  const submit = () => {
    setError(null)
    if (selectedIds.length === 0) {
      setError('Selecione ao menos um veículo.')
      return
    }
    create.mutate({ vehicle_ids: selectedIds, expires_in_hours: Number(hours) })
  }

  const plates = link?.vehicles.map((vehicle) => vehicle.plate).join(', ') ?? ''
  const whatsappText = link
    ? `Acompanhe em tempo real (${plates}) até ${formatDateTime(link.expires_at)}: ${url}`
    : ''

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Compartilhar localização"
      description="Link público que mostra apenas o mapa com os veículos selecionados. Expira em até 24 horas."
      size="md"
      footer={
        link ? (
          <Button type="button" variant="secondary" onClick={onClose}>
            Fechar
          </Button>
        ) : (
          <>
            <Button type="button" variant="secondary" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="button" loading={create.isPending} onClick={submit}>
              <Link2 className="size-4" />
              Gerar link
            </Button>
          </>
        )
      }
    >
      {link ? (
        <div className="space-y-4">
          <Field label="Link de acompanhamento" hint={`Válido até ${formatDateTime(link.expires_at)} · ${plates}`}>
            <div className="flex gap-2">
              <Input readOnly value={url} onFocus={(event) => event.currentTarget.select()} />
              <Button type="button" variant="secondary" onClick={() => void copy()} aria-label="Copiar link">
                {copied ? <Check className="size-4" /> : <Copy className="size-4" />}
              </Button>
            </div>
          </Field>
          <div className="flex flex-wrap gap-2">
            <a
              href={`https://wa.me/?text=${encodeURIComponent(whatsappText)}`}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex h-9 items-center gap-2 rounded-lg bg-surface-2 px-3 text-sm font-medium text-foreground hover:bg-surface-3"
            >
              <MessageCircle className="size-4" />
              Enviar pelo WhatsApp
            </a>
            <a
              href={url}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex h-9 items-center gap-2 rounded-lg bg-surface-2 px-3 text-sm font-medium text-foreground hover:bg-surface-3"
            >
              <ExternalLink className="size-4" />
              Abrir link
            </a>
          </div>
          <p className="text-[13px] text-muted">
            Quem tiver o link verá a posição atual dos veículos até a expiração. Guarde-o: por segurança ele não
            pode ser exibido novamente.
          </p>
        </div>
      ) : (
        <div className="space-y-4">
          <Field label="Validade">
            <Select value={hours} onChange={(event) => setHours(event.target.value)} options={DURATION_OPTIONS} />
          </Field>

          <fieldset>
            <legend className="mb-2 text-sm font-medium text-foreground">
              Veículos <span className="font-normal text-muted">({selectedIds.length} selecionado{selectedIds.length === 1 ? '' : 's'})</span>
            </legend>
            {options.length > 6 && (
              <Input
                className="mb-2"
                placeholder="Buscar por placa, marca ou modelo..."
                value={search}
                onChange={(event) => setSearch(event.target.value)}
              />
            )}
            <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg bg-surface-2 p-2">
              {liveQuery.isPending && options.length === 0 ? (
                <p className="p-2 text-sm text-muted">Carregando veículos...</p>
              ) : visible.length === 0 ? (
                <p className="p-2 text-sm text-muted">Nenhum veículo encontrado.</p>
              ) : (
                visible.map((vehicle) => (
                  <Checkbox
                    key={vehicle.id}
                    label={vehicleLabel(vehicle)}
                    checked={selectedIds.includes(vehicle.id)}
                    onChange={(event) => toggle(vehicle.id, event.target.checked)}
                  />
                ))
              )}
            </div>
          </fieldset>

          {error && <p className="text-[13px] text-danger">{error}</p>}
        </div>
      )}
    </Modal>
  )
}
