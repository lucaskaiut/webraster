/** Variáveis aceitas nos textos da régua de cobrança (mesmas da API). */
export const COLLECTION_VARIABLES: Array<{ key: string; label: string; sample: string }> = [
  { key: '{cliente}', label: 'Nome do cliente', sample: 'Maria Souza' },
  { key: '{empresa}', label: 'Nome da sua empresa', sample: 'Nuvemax' },
  { key: '{codigo}', label: 'Código da cobrança', sample: '1024' },
  { key: '{valor}', label: 'Valor total', sample: 'R$ 129,90' },
  { key: '{vencimento}', label: 'Data de vencimento', sample: '15/11/2026' },
  { key: '{dias_atraso}', label: 'Dias em atraso', sample: '4' },
  { key: '{link}', label: 'Link para pagamento', sample: 'https://www.asaas.com/i/exemplo' },
]

/** Prévia do texto com dados de exemplo. */
export function renderCollectionPreview(template: string): string {
  return COLLECTION_VARIABLES.reduce(
    (text, variable) => text.split(variable.key).join(variable.sample),
    template,
  )
}
