/**
 * Utilitários de apresentação da agenda.
 *
 * A API devolve todos os candidatos de 5 minutos, inclusive os bloqueados. Para não
 * repetir o mesmo conflito em vários quadros, os bloqueios consecutivos causados pela
 * mesma OS são agrupados em um único intervalo.
 */
export interface ScheduleSlotView {
  start: string
  end: string
  available: boolean
  reason: string | null
  blocked_by: {
    id: string
    code: string
    client: string | null
    service_start: string | null
    service_end: string | null
    blocked_start: string
    blocked_end: string
  } | null
}

export function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

/** Dia da semana e dia do mês, ex.: "Ter, 27". */
export function formatWeekdayDay(iso: string): string {
  const date = new Date(iso)

  if (Number.isNaN(date.getTime())) return ''

  const label = date.toLocaleDateString('pt-BR', {
    weekday: 'short',
    day: '2-digit',
  })

  return label.charAt(0).toUpperCase() + label.slice(1).replace('.', '')
}

export function formatKilometers(meters: number): string {
  return `${(meters / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} km`
}

export function sameInstant(a: string, b: string): boolean {
  if (!a || !b) return false

  const first = new Date(a).getTime()
  const second = new Date(b).getTime()

  return Number.isFinite(first) && Number.isFinite(second) && first === second
}

export function groupBlockedSlots(slots: ScheduleSlotView[]): ScheduleSlotView[] {
  const grouped: ScheduleSlotView[] = []

  for (const slot of slots) {
    const previous = grouped[grouped.length - 1]
    const blockedKey = slot.blocked_by?.id ?? slot.reason
    const previousKey = previous?.blocked_by?.id ?? previous?.reason

    if (!slot.available && previous && !previous.available && blockedKey === previousKey) {
      // Sem o intervalo real (API antiga), usa o fim do último candidato.
      if (!previous.blocked_by) previous.end = slot.end
      continue
    }

    grouped.push({
      ...slot,
      // O grupo representa o bloqueio real da OS (incluindo deslocamento),
      // não o intervalo entre o primeiro e o último candidato.
      start: slot.blocked_by?.blocked_start ?? slot.start,
      end: slot.blocked_by?.blocked_end ?? slot.end,
    })
  }

  return grouped
}
