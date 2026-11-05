const SLOTS = 6

/** Cor fixa por serviço (ordem do catálogo), nunca pela posição no ranking. */
export function seriesColor(slot: number): string {
  return `var(--app-viz-${(slot % SLOTS) + 1})`
}
