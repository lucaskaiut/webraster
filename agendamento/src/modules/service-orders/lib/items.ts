/**
 * Regras de valores dos serviços da OS.
 *
 * Os valores são digitados em reais, mas o contrato da API usa centavos. Manter a
 * conversão e a soma em um único lugar evita divergência entre o total exibido e o
 * total enviado.
 */
export interface ServiceOrderItemDraft {
  service_id: string
  name: string
  quantity: string
  amount: string
  /** Serviço excluído do catálogo: mantém o histórico visual, mas sai no próximo salvamento. */
  missing?: boolean
}

export function reaisInputToCents(value: string): number {
  const parsed = Number(value.replace(',', '.'))
  return Number.isFinite(parsed) ? Math.round(parsed * 100) : 0
}

export function centsToReaisInput(cents: number): string {
  return (cents / 100).toFixed(2)
}

/** Quantidade usada no cálculo visual. Valores inválidos viram zero. */
export function itemQuantity(value: string): number {
  return Math.max(0, Math.floor(Number(value) || 0))
}

export function itemLineTotalCents(item: ServiceOrderItemDraft): number {
  if (item.missing) return 0

  return reaisInputToCents(item.amount) * itemQuantity(item.quantity)
}

export function itemsTotalCents(items: ServiceOrderItemDraft[]): number {
  return items.reduce((total, item) => total + itemLineTotalCents(item), 0)
}

/**
 * Validação dos itens antes do envio. As mensagens são as mesmas já apresentadas
 * pelo formulário e as regras espelham o backend (serviço obrigatório e quantidade
 * mínima).
 */
export function validateServiceOrderItems(items: ServiceOrderItemDraft[]): string | null {
  for (const item of items) {
    if (item.missing) continue

    if (!item.service_id) {
      return 'Selecione o serviço em todas as linhas ou remova as linhas vazias.'
    }

    const quantity = Math.floor(Number(item.quantity))
    if (!Number.isFinite(quantity) || quantity < 1) {
      return 'Informe uma quantidade válida (mínimo 1) nos serviços realizados.'
    }
  }

  return null
}
