/** Opções padrão de quantidade de registros por página das listagens. */
export const TABLE_PER_PAGE_OPTIONS = [
  { value: '10', label: '10 registros' },
  { value: '50', label: '50 registros' },
  { value: '100', label: '100 registros' },
  { value: 'all', label: 'Todos os registros' },
]

export function parseTablePerPage(value: string | null): number | 'all' {
  if (value === 'all') return 'all'

  const parsed = Number(value)

  return parsed === 50 || parsed === 100 ? parsed : 10
}
