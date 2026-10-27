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

/** Contrato mínimo da resposta de estimativa usado pela apresentação. */
export interface ScheduleEstimateView {
  timezone: string
  slots: ScheduleSlotView[]
}

export type SchedulePeriod = 'morning' | 'afternoon'

/** Limite operacional entre manhã e tarde (12:00 no fuso da agenda). */
export const MORNING_END_MINUTES = 12 * 60

/** Tamanho do lote inicial e dos lotes seguintes, em dias de calendário. */
export const CALENDAR_DAY_BATCH_SIZE = 10

/**
 * Janela máxima consultada a partir da data de referência. Não existe um limite
 * de agendamento na API; este valor apenas impede buscas indefinidas quando o
 * técnico não possui nenhum dia disponível.
 */
export const SEARCH_DAYS_LIMIT = 90

export interface ScheduleDayView {
  date: string
  timezone: string
  availableSlots: ScheduleSlotView[]
  morningSlots: ScheduleSlotView[]
  afternoonSlots: ScheduleSlotView[]
  suggestedSlots: ScheduleSlotView[]
  hasMoreSlots: boolean
}

const timeZoneFormatters = new Map<string, Intl.DateTimeFormat>()

function timeZoneFormatter(timeZone: string): Intl.DateTimeFormat {
  const cached = timeZoneFormatters.get(timeZone)

  if (cached) return cached

  const formatter = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  })

  timeZoneFormatters.set(timeZone, formatter)

  return formatter
}

/** Minutos desde a meia-noite no fuso operacional da agenda. */
export function slotMinutesInTimeZone(iso: string, timeZone?: string): number | null {
  const date = new Date(iso)

  if (Number.isNaN(date.getTime())) return null

  if (!timeZone) return date.getHours() * 60 + date.getMinutes()

  try {
    const parts = timeZoneFormatter(timeZone).formatToParts(date)
    const hour = Number(parts.find((part) => part.type === 'hour')?.value)
    const minute = Number(parts.find((part) => part.type === 'minute')?.value)

    if (!Number.isFinite(hour) || !Number.isFinite(minute)) return null

    return hour * 60 + minute
  } catch {
    // Fuso inválido no navegador: mantém o horário local como fallback visual.
    return date.getHours() * 60 + date.getMinutes()
  }
}

export function slotPeriod(iso: string, timeZone?: string): SchedulePeriod | null {
  const minutes = slotMinutesInTimeZone(iso, timeZone)

  if (minutes === null) return null

  return minutes < MORNING_END_MINUTES ? 'morning' : 'afternoon'
}

/**
 * Constrói a apresentação de um dia. Retorna `null` quando não há nenhum
 * horário válido, garantindo que dias indisponíveis não gerem cartões.
 */
export function buildScheduleDay(
  date: string,
  estimate: ScheduleEstimateView,
): ScheduleDayView | null {
  const availableSlots = estimate.slots
    .filter((slot) => slot.available)
    .sort((a, b) => new Date(a.start).getTime() - new Date(b.start).getTime())

  if (availableSlots.length === 0) return null

  const morningSlots = availableSlots.filter(
    (slot) => slotPeriod(slot.start, estimate.timezone) === 'morning',
  )
  const afternoonSlots = availableSlots.filter(
    (slot) => slotPeriod(slot.start, estimate.timezone) === 'afternoon',
  )

  // As sugestões são apenas a primeira opção de cada período. A ausência de
  // um período simplesmente produz uma única sugestão, sem opção fictícia.
  const suggestedSlots = [morningSlots[0], afternoonSlots[0]].filter(
    (slot): slot is ScheduleSlotView => Boolean(slot),
  )

  return {
    date,
    timezone: estimate.timezone,
    availableSlots,
    morningSlots,
    afternoonSlots,
    suggestedSlots,
    hasMoreSlots: availableSlots.length > suggestedSlots.length,
  }
}

export function buildScheduleDays(
  entries: Array<{ date: string; estimate: ScheduleEstimateView }>,
): ScheduleDayView[] {
  return entries
    .map(({ date, estimate }) => buildScheduleDay(date, estimate))
    .filter((day): day is ScheduleDayView => day !== null)
    .sort((a, b) => a.date.localeCompare(b.date))
}

export function formatTime(iso: string, timeZone?: string): string {
  const date = new Date(iso)

  if (Number.isNaN(date.getTime())) return ''

  try {
    return date.toLocaleTimeString('pt-BR', {
      hour: '2-digit',
      minute: '2-digit',
      ...(timeZone ? { timeZone } : {}),
    })
  } catch {
    return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
  }
}

/** Dia da semana e dia do mês, ex.: "Ter, 27". */
export function formatWeekdayDay(iso: string, timeZone?: string): string {
  const date = new Date(iso)

  if (Number.isNaN(date.getTime())) return ''

  try {
    const label = date.toLocaleDateString('pt-BR', {
      weekday: 'short',
      day: '2-digit',
      ...(timeZone ? { timeZone } : {}),
    })

    return label.charAt(0).toUpperCase() + label.slice(1).replace('.', '')
  } catch {
    return ''
  }
}

function parseIsoDateParts(value: string): Date | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value.trim())

  if (!match) return null

  const year = Number(match[1])
  const month = Number(match[2])
  const day = Number(match[3])
  const date = new Date(year, month - 1, day, 12)

  if (
    date.getFullYear() !== year ||
    date.getMonth() !== month - 1 ||
    date.getDate() !== day
  ) {
    return null
  }

  return date
}

/** Dia da semana curto e data (dd/mm) para o cartão. */
export function formatDayCard(date: string): { weekday: string; shortDate: string } {
  const parsed = parseIsoDateParts(date)

  if (!parsed) return { weekday: '', shortDate: '' }

  const rawWeekday = parsed.toLocaleDateString('pt-BR', { weekday: 'short' })
  const weekday = rawWeekday.charAt(0).toUpperCase() + rawWeekday.slice(1).replace('.', '')
  const day = String(parsed.getDate()).padStart(2, '0')
  const month = String(parsed.getMonth() + 1).padStart(2, '0')

  return { weekday, shortDate: `${day}/${month}` }
}

/** Data completa e dia da semana para o cabeçalho da modal. */
export function formatFullDay(date: string): string {
  const parsed = parseIsoDateParts(date)

  if (!parsed) return ''

  const label = parsed.toLocaleDateString('pt-BR', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })

  return label.charAt(0).toUpperCase() + label.slice(1)
}

export function isValidIsoDate(value: string): boolean {
  return parseIsoDateParts(value) !== null
}

/** Soma dias a uma data ISO (YYYY-MM-DD) sem depender do fuso do navegador. */
export function addDaysToIsoDate(date: string, amount: number): string {
  const parsed = parseIsoDateParts(date)

  if (!parsed) return date

  const utc = new Date(Date.UTC(parsed.getFullYear(), parsed.getMonth(), parsed.getDate()))
  utc.setUTCDate(utc.getUTCDate() + amount)

  const year = utc.getUTCFullYear()
  const month = String(utc.getUTCMonth() + 1).padStart(2, '0')
  const day = String(utc.getUTCDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

/** Lista datas consecutivas a partir de `start`, inclusive. */
export function calendarDatesFrom(start: string, count: number): string[] {
  const total = Math.max(0, Math.floor(count))

  return Array.from({ length: total }, (_, index) => addDaysToIsoDate(start, index))
}

/** Datas entre `start` e `limit`, ambas inclusive. */
export function calendarDatesUpTo(start: string, limit: string): string[] {
  const parsedStart = parseIsoDateParts(start)
  const parsedLimit = parseIsoDateParts(limit)

  if (!parsedStart || !parsedLimit || start > limit) return []

  const dates: string[] = []
  let cursor = start

  while (cursor <= limit) {
    dates.push(cursor)
    cursor = addDaysToIsoDate(cursor, 1)
  }

  return dates
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
