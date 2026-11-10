import { ExternalLink } from 'lucide-react'
import { addressReferenceUrl } from '../lib/address-reference'

/**
 * Exibe o ponto de referência. Se for um link, abre em nova aba — no celular,
 * links do Google Maps são abertos direto no app de mapas.
 */
export function AddressReference({ value }: { value: string | null | undefined }) {
  const text = value?.trim() ?? ''

  if (text === '') return <>—</>

  const url = addressReferenceUrl(text)

  if (url === null) return <span className="whitespace-pre-line">{text}</span>

  return (
    <a
      href={url}
      target="_blank"
      rel="noopener noreferrer"
      className="inline-flex items-center gap-1 break-all text-primary hover:underline"
    >
      Abrir localização
      <ExternalLink className="size-3.5 shrink-0" />
    </a>
  )
}
