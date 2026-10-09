import type { SearchSelectOption } from '@/shared/design-system'
import { EQUIPMENT_MODELS } from './equipment-models'

const DIACRITICS = /[\u0300-\u036f]/g
const NON_ALPHANUMERIC = /[^a-z0-9]+/g

/**
 * Normaliza o texto para busca por equivalência.
 *
 * "Abest-Tech GT-110ES" e "abest tech gt 110es" viram "abesttechgt110es",
 * então qualquer variação (separadores, acentos, maiúsculas) casa entre si.
 */
function normalizeForSearch(value: string): string {
  return value.normalize('NFD').replace(DIACRITICS, '').toLowerCase().replace(NON_ALPHANUMERIC, '')
}

const INDEXED_MODELS = EQUIPMENT_MODELS.map((option) => ({
  option,
  index: normalizeForSearch(option.label),
}))

/** Busca modelos do catálogo pelo índice normalizado (prefixo primeiro, depois o restante). */
export function searchEquipmentModels(search: string, limit: number): SearchSelectOption[] {
  const term = normalizeForSearch(search)

  if (!term) {
    return INDEXED_MODELS.slice(0, limit).map((entry) => entry.option)
  }

  const matches = INDEXED_MODELS.filter((entry) => entry.index.includes(term))
  const prioritized = [
    ...matches.filter((entry) => entry.index.startsWith(term)),
    ...matches.filter((entry) => !entry.index.startsWith(term)),
  ]

  return prioritized.slice(0, limit).map((entry) => entry.option)
}
