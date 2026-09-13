import { formatDocument } from '@/shared/utils/document'
import { formatCurrency } from '@/shared/utils/format'
import { maskPhone } from '@/shared/utils/mask'

export type ContractVariableKey =
  | 'NOME_CLIENTE'
  | 'DOCUMENTO_CLIENTE'
  | 'EMAIL_CLIENTE'
  | 'TELEFONE_CLIENTE'
  | 'ENDERECO_CLIENTE'
  | 'TABELA_PEDIDO'

export interface ContractVariableDefinition {
  key: ContractVariableKey
  token: string
  label: string
  description: string
}

export const CONTRACT_VARIABLES: ContractVariableDefinition[] = [
  {
    key: 'NOME_CLIENTE',
    token: '{{NOME_CLIENTE}}',
    label: 'Nome do cliente',
    description: 'Nome cadastrado do cliente',
  },
  {
    key: 'DOCUMENTO_CLIENTE',
    token: '{{DOCUMENTO_CLIENTE}}',
    label: 'CPF/CNPJ',
    description: 'Documento do cliente formatado',
  },
  {
    key: 'EMAIL_CLIENTE',
    token: '{{EMAIL_CLIENTE}}',
    label: 'E-mail',
    description: 'E-mail principal do cliente',
  },
  {
    key: 'TELEFONE_CLIENTE',
    token: '{{TELEFONE_CLIENTE}}',
    label: 'Telefone',
    description: 'Telefone do cliente formatado',
  },
  {
    key: 'ENDERECO_CLIENTE',
    token: '{{ENDERECO_CLIENTE}}',
    label: 'Endereço completo',
    description: 'Endereço no formato brasileiro',
  },
  {
    key: 'TABELA_PEDIDO',
    token: '{{TABELA_PEDIDO}}',
    label: 'Tabela do pedido',
    description: 'Itens e serviços do pedido do cliente',
  },
]

export interface ContractSubstitutionValues {
  NOME_CLIENTE: string
  DOCUMENTO_CLIENTE: string
  EMAIL_CLIENTE: string
  TELEFONE_CLIENTE: string
  ENDERECO_CLIENTE: string
  TABELA_PEDIDO: string
}

export interface ContractOrderLine {
  service_name: string
  quantity: number
  unit_amount_cents: number
  line_total_cents: number
  plates: string[]
}

function escapeHtml(value: string): string {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;')
}

export function formatBrazilianAddress(parts: {
  street?: string | null
  number?: string | null
  complement?: string | null
  neighborhood?: string | null
  city?: string | null
  state?: string | null
  zip?: string | null
}): string {
  const line1 = [parts.street, parts.number].filter(Boolean).join(', ')
  const withComplement = [line1, parts.complement].filter(Boolean).join(' — ')
  const line2 = [parts.neighborhood, [parts.city, parts.state].filter(Boolean).join('/')].filter(Boolean).join(' — ')
  const zip = parts.zip ? `CEP ${parts.zip.length === 8 ? `${parts.zip.slice(0, 5)}-${parts.zip.slice(5)}` : parts.zip}` : ''

  return [withComplement, line2, zip].filter(Boolean).join(', ')
}

export function buildOrderItemsTableHtml(items: ContractOrderLine[]): string {
  if (items.length === 0) {
    return '<p><em>Nenhum item no pedido.</em></p>'
  }

  const rows = items
    .map((item) => {
      const plates = item.plates.length > 0 ? item.plates.join(', ') : '—'

      return `<tr>
  <td style="border:1px solid #ccc;padding:8px;">${escapeHtml(item.service_name)}</td>
  <td style="border:1px solid #ccc;padding:8px;text-align:center;">${item.quantity}</td>
  <td style="border:1px solid #ccc;padding:8px;">${escapeHtml(plates)}</td>
  <td style="border:1px solid #ccc;padding:8px;text-align:right;">${escapeHtml(formatCurrency(item.unit_amount_cents / 100))}</td>
  <td style="border:1px solid #ccc;padding:8px;text-align:right;">${escapeHtml(formatCurrency(item.line_total_cents / 100))}</td>
</tr>`
    })
    .join('')

  const totalCents = items.reduce((sum, item) => sum + item.line_total_cents, 0)

  return `<table style="width:100%;border-collapse:collapse;margin:1rem 0;">
  <thead>
    <tr>
      <th style="border:1px solid #ccc;padding:8px;text-align:left;">Serviço</th>
      <th style="border:1px solid #ccc;padding:8px;text-align:center;">Qtd.</th>
      <th style="border:1px solid #ccc;padding:8px;text-align:left;">Veículos</th>
      <th style="border:1px solid #ccc;padding:8px;text-align:right;">Valor unit.</th>
      <th style="border:1px solid #ccc;padding:8px;text-align:right;">Total</th>
    </tr>
  </thead>
  <tbody>
    ${rows}
  </tbody>
  <tfoot>
    <tr>
      <td colspan="4" style="border:1px solid #ccc;padding:8px;text-align:right;font-weight:600;">Total do contrato</td>
      <td style="border:1px solid #ccc;padding:8px;text-align:right;font-weight:600;">${escapeHtml(formatCurrency(totalCents / 100))}</td>
    </tr>
  </tfoot>
</table>`
}

export function getFictionalContractValues(): ContractSubstitutionValues {
  const document = '52998224725'
  const phone = '41999998888'
  const items: ContractOrderLine[] = [
    {
      service_name: 'Instalação de rastreador',
      quantity: 3,
      unit_amount_cents: 5000,
      line_total_cents: 15000,
      plates: ['AAA1A11', 'BBB2B22', 'CCC3C33'],
    },
    {
      service_name: 'Instalação de bloqueador',
      quantity: 2,
      unit_amount_cents: 3000,
      line_total_cents: 6000,
      plates: ['AAA1A11', 'BBB2B22'],
    },
  ]

  return {
    NOME_CLIENTE: 'Transportadora Silva Ltda',
    DOCUMENTO_CLIENTE: formatDocument(document),
    EMAIL_CLIENTE: 'contato@transportadorasilva.example',
    TELEFONE_CLIENTE: maskPhone(phone),
    ENDERECO_CLIENTE: formatBrazilianAddress({
      street: 'Rua das Flores',
      number: '123',
      complement: 'Sala 4',
      neighborhood: 'Centro',
      city: 'Curitiba',
      state: 'PR',
      zip: '80010000',
    }),
    TABELA_PEDIDO: buildOrderItemsTableHtml(items),
  }
}

export function substituteContractVariables(
  body: string,
  values: Partial<ContractSubstitutionValues>,
): string {
  return CONTRACT_VARIABLES.reduce((html, variable) => {
    const replacement = values[variable.key] ?? ''

    return html.replaceAll(variable.token, replacement)
  }, body)
}

export function isEmptyRichText(html: string): boolean {
  const text = html
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/\s+/g, ' ')
    .trim()

  return text.length === 0
}
