export type SortDirection = 'asc' | 'desc'

/** Critério de ordenação por coluna; a ordem do array é a prioridade. */
export interface SortCriterion {
  column: string
  direction: SortDirection
}

const MAX_CRITERIA = 5

/**
 * Lê o parâmetro `sort` no formato `coluna:asc,coluna2:desc`.
 * Critérios inválidos são ignorados.
 */
export function parseSortParam(value: string | null | undefined): SortCriterion[] {
  if (!value) return []

  const criteria: SortCriterion[] = []

  for (const part of value.split(',')) {
    const trimmed = part.trim()
    if (trimmed === '') continue

    const [column, rawDirection] = trimmed.split(':')
    const name = column?.trim()

    if (!name || !/^[a-zA-Z0-9_]+$/.test(name)) continue

    criteria.push({
      column: name,
      direction: rawDirection?.trim().toLowerCase() === 'desc' ? 'desc' : 'asc',
    })

    if (criteria.length >= MAX_CRITERIA) break
  }

  return criteria
}

export function serializeSortParam(criteria: SortCriterion[]): string {
  return criteria.map((criterion) => `${criterion.column}:${criterion.direction}`).join(',')
}

/**
 * Ciclo de estados da coluna: desativado → crescente → decrescente →
 * desativado. As demais colunas permanecem na ordenação, preservando a
 * ordem em que foram clicadas.
 */
export function toggleSortCriterion(criteria: SortCriterion[], column: string): SortCriterion[] {
  const existing = criteria.find((criterion) => criterion.column === column)

  if (!existing) {
    return [...criteria, { column, direction: 'asc' as const }].slice(0, MAX_CRITERIA)
  }

  if (existing.direction === 'asc') {
    return criteria.map((criterion): SortCriterion =>
      criterion.column === column ? { ...criterion, direction: 'desc' } : criterion,
    )
  }

  return criteria.filter((criterion) => criterion.column !== column)
}
