import type { Granularity } from '../types'

export type PeriodPreset = '1h' | '6h' | '24h' | '7d' | '30d' | '90d' | 'custom'

export const PERIOD_OPTIONS: Array<{ value: PeriodPreset; label: string; longLabel: string }> = [
  { value: '1h', label: '1h', longLabel: 'Última hora' },
  { value: '6h', label: '6h', longLabel: 'Últimas 6 horas' },
  { value: '24h', label: '24h', longLabel: 'Últimas 24 horas' },
  { value: '7d', label: '7d', longLabel: 'Últimos 7 dias' },
  { value: '30d', label: '30d', longLabel: 'Últimos 30 dias' },
  { value: '90d', label: '90d', longLabel: 'Últimos 90 dias' },
  { value: 'custom', label: 'Período', longLabel: 'Período personalizado' },
]

export const GRANULARITY_OPTIONS: Array<{ value: Granularity; label: string }> = [
  { value: 'minute', label: 'Minuto' },
  { value: 'hour', label: 'Hora' },
  { value: 'day', label: 'Dia' },
  { value: 'week', label: 'Semana' },
  { value: 'month', label: 'Mês' },
]

const MINUTE = 60_000
const HOUR = 60 * MINUTE
const DAY = 24 * HOUR

const PRESET_DURATION: Record<Exclude<PeriodPreset, 'custom'>, number> = {
  '1h': HOUR,
  '6h': 6 * HOUR,
  '24h': DAY,
  '7d': 7 * DAY,
  '30d': 30 * DAY,
  '90d': 90 * DAY,
}

const GRANULARITY_SIZE: Record<Granularity, number> = {
  minute: MINUTE,
  hour: HOUR,
  day: DAY,
  week: 7 * DAY,
  month: 30 * DAY,
}

/** Mesmo limite do backend (ExternalApiMetricsService::MAX_BUCKETS). */
const MAX_BUCKETS = 1500
/** Menos que isso deixa o gráfico com um ou dois pontos. */
const MIN_BUCKETS = 2

/** Formato local sem fuso ("2026-11-05T14:30:00"), interpretado no fuso enviado à API. */
function toLocalDateTime(date: Date): string {
  const pad = (value: number) => String(value).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
}

export function resolvePeriod(
  preset: PeriodPreset,
  anchor: Date,
  custom: { from: string; to: string },
): { from: string; to: string; durationMs: number } {
  if (preset === 'custom' && custom.from && custom.to) {
    const from = `${custom.from}T00:00:00`
    const to = `${custom.to}T23:59:59`

    return { from, to, durationMs: new Date(to).getTime() - new Date(from).getTime() }
  }

  const duration = PRESET_DURATION[preset === 'custom' ? '24h' : preset]

  return {
    from: toLocalDateTime(new Date(anchor.getTime() - duration)),
    to: toLocalDateTime(anchor),
    durationMs: duration,
  }
}

export function allowedGranularities(durationMs: number): Granularity[] {
  return GRANULARITY_OPTIONS.map((option) => option.value).filter((granularity) => {
    const buckets = durationMs / GRANULARITY_SIZE[granularity]

    return buckets <= MAX_BUCKETS && buckets >= MIN_BUCKETS
  })
}

/** Granularidade que rende algo entre ~24 e ~200 pontos no período. */
export function defaultGranularity(durationMs: number): Granularity {
  if (durationMs <= 6 * HOUR) return 'minute'
  if (durationMs <= 7 * DAY) return 'hour'
  if (durationMs <= 180 * DAY) return 'day'

  return 'week'
}

export function browserTimezone(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Sao_Paulo'
  } catch {
    return 'America/Sao_Paulo'
  }
}

const TIME = new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit' })
const DAY_MONTH = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit' })
const DAY_MONTH_TIME = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
const MONTH_YEAR = new Intl.DateTimeFormat('pt-BR', { month: 'short', year: '2-digit' })
const FULL_DATE = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' })

/** Rótulo curto do eixo X. */
export function formatBucketTick(iso: string, granularity: Granularity, spansDays: boolean): string {
  const date = new Date(iso)

  switch (granularity) {
    case 'minute':
    case 'hour':
      return spansDays ? DAY_MONTH_TIME.format(date) : TIME.format(date)
    case 'day':
    case 'week':
      return DAY_MONTH.format(date)
    default:
      return MONTH_YEAR.format(date)
  }
}

/** Rótulo completo do intervalo, usado no tooltip. */
export function formatBucketLabel(iso: string, granularity: Granularity): string {
  const date = new Date(iso)

  switch (granularity) {
    case 'minute':
      return `${FULL_DATE.format(date)} ${TIME.format(date)}`
    case 'hour': {
      const end = new Date(date.getTime() + HOUR)
      return `${FULL_DATE.format(date)} ${TIME.format(date)}–${TIME.format(end)}`
    }
    case 'day':
      return FULL_DATE.format(date)
    case 'week': {
      const end = new Date(date.getTime() + 6 * DAY)
      return `Semana de ${DAY_MONTH.format(date)} a ${FULL_DATE.format(end)}`
    }
    default:
      return MONTH_YEAR.format(date)
  }
}

export const NUMBER = new Intl.NumberFormat('pt-BR')
