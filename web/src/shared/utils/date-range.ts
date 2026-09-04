import {
  addDays,
  endOfMonth,
  formatDisplayDate,
  isSameDay,
  parseIsoDate,
  startOfMonth,
  startOfWeek,
  toIsoDate,
  type DateRange,
} from './date'

export type DateRangePreset =
  | 'today'
  | 'yesterday'
  | 'tomorrow'
  | 'this_week'
  | 'last_week'
  | 'next_week'
  | 'this_month'
  | 'last_month'
  | 'next_month'
  | 'this_year'
  | 'last_year'
  | 'next_year'
  | 'last_7_days'
  | 'last_30_days'
  | 'last_90_days'
  | 'last_12_months'
  | 'all_time'
  | 'custom'

export interface DateRangeFilterState {
  preset: DateRangePreset
  startDate: Date | null
  endDate: Date | null
}

export interface DateRangePresetDefinition {
  id: DateRangePreset
  label: string
  group?: 'day' | 'week' | 'month' | 'year' | 'rolling' | 'other'
  singleDay?: boolean
  resolve: (reference?: Date) => DateRange
}

type NavigationMode = 'day' | 'week' | 'month' | 'year' | 'custom'

function endOfWeek(date: Date): Date {
  return addDays(startOfWeek(date), 6)
}

function startOfYear(date: Date): Date {
  return new Date(date.getFullYear(), 0, 1)
}

function endOfYear(date: Date): Date {
  return new Date(date.getFullYear(), 11, 31)
}

function addMonths(date: Date, amount: number): Date {
  const copy = new Date(date)
  const day = copy.getDate()

  copy.setDate(1)
  copy.setMonth(copy.getMonth() + amount)
  copy.setDate(Math.min(day, endOfMonth(copy).getDate()))

  return copy
}

function diffDays(from: Date, to: Date): number {
  const start = new Date(from.getFullYear(), from.getMonth(), from.getDate())
  const end = new Date(to.getFullYear(), to.getMonth(), to.getDate())

  return Math.round((end.getTime() - start.getTime()) / 86_400_000)
}

function isFullWeek(from: Date, to: Date): boolean {
  return diffDays(from, to) === 6 && from.getDay() === 1 && to.getDay() === 0
}

function isFullMonth(from: Date, to: Date): boolean {
  return (
    from.getDate() === 1
    && isSameDay(to, endOfMonth(from))
    && from.getMonth() === to.getMonth()
    && from.getFullYear() === to.getFullYear()
  )
}

function isFullYear(from: Date, to: Date): boolean {
  return (
    from.getMonth() === 0
    && from.getDate() === 1
    && to.getMonth() === 11
    && to.getDate() === 31
    && from.getFullYear() === to.getFullYear()
  )
}

export function isRangeEmpty(from: string, to: string): boolean {
  return from === '' && to === ''
}

export function toFilterState(from: string, to: string): DateRangeFilterState {
  if (isRangeEmpty(from, to)) {
    return { preset: 'all_time', startDate: null, endDate: null }
  }

  const startDate = parseIsoDate(from)
  const endDate = parseIsoDate(to)

  if (!startDate || !endDate) {
    return { preset: 'custom', startDate, endDate }
  }

  return {
    preset: matchPreset(from, to),
    startDate,
    endDate,
  }
}

export function fromFilterState(state: DateRangeFilterState): DateRange {
  if (state.preset === 'all_time' || !state.startDate || !state.endDate) {
    return { from: '', to: '' }
  }

  return {
    from: toIsoDate(state.startDate),
    to: toIsoDate(state.endDate),
  }
}

export const DATE_RANGE_PRESETS: DateRangePresetDefinition[] = [
  {
    id: 'today',
    label: 'Hoje',
    group: 'day',
    singleDay: true,
    resolve: (reference = new Date()) => {
      const iso = toIsoDate(reference)

      return { from: iso, to: iso }
    },
  },
  {
    id: 'yesterday',
    label: 'Ontem',
    group: 'day',
    singleDay: true,
    resolve: (reference = new Date()) => {
      const iso = toIsoDate(addDays(reference, -1))

      return { from: iso, to: iso }
    },
  },
  {
    id: 'tomorrow',
    label: 'Amanhã',
    group: 'day',
    singleDay: true,
    resolve: (reference = new Date()) => {
      const iso = toIsoDate(addDays(reference, 1))

      return { from: iso, to: iso }
    },
  },
  {
    id: 'this_week',
    label: 'Esta semana',
    group: 'week',
    resolve: (reference = new Date()) => ({
      from: toIsoDate(startOfWeek(reference)),
      to: toIsoDate(endOfWeek(reference)),
    }),
  },
  {
    id: 'last_week',
    label: 'Semana passada',
    group: 'week',
    resolve: (reference = new Date()) => {
      const monday = addDays(startOfWeek(reference), -7)

      return {
        from: toIsoDate(monday),
        to: toIsoDate(addDays(monday, 6)),
      }
    },
  },
  {
    id: 'next_week',
    label: 'Próxima semana',
    group: 'week',
    resolve: (reference = new Date()) => {
      const monday = addDays(startOfWeek(reference), 7)

      return {
        from: toIsoDate(monday),
        to: toIsoDate(addDays(monday, 6)),
      }
    },
  },
  {
    id: 'this_month',
    label: 'Este mês',
    group: 'month',
    resolve: (reference = new Date()) => ({
      from: toIsoDate(startOfMonth(reference)),
      to: toIsoDate(endOfMonth(reference)),
    }),
  },
  {
    id: 'last_month',
    label: 'Mês passado',
    group: 'month',
    resolve: (reference = new Date()) => {
      const previous = addMonths(reference, -1)

      return {
        from: toIsoDate(startOfMonth(previous)),
        to: toIsoDate(endOfMonth(previous)),
      }
    },
  },
  {
    id: 'next_month',
    label: 'Próximo mês',
    group: 'month',
    resolve: (reference = new Date()) => {
      const next = addMonths(reference, 1)

      return {
        from: toIsoDate(startOfMonth(next)),
        to: toIsoDate(endOfMonth(next)),
      }
    },
  },
  {
    id: 'this_year',
    label: 'Este ano',
    group: 'year',
    resolve: (reference = new Date()) => ({
      from: toIsoDate(startOfYear(reference)),
      to: toIsoDate(endOfYear(reference)),
    }),
  },
  {
    id: 'last_year',
    label: 'Ano passado',
    group: 'year',
    resolve: (reference = new Date()) => {
      const year = reference.getFullYear() - 1

      return {
        from: toIsoDate(new Date(year, 0, 1)),
        to: toIsoDate(new Date(year, 11, 31)),
      }
    },
  },
  {
    id: 'next_year',
    label: 'Próximo ano',
    group: 'year',
    resolve: (reference = new Date()) => {
      const year = reference.getFullYear() + 1

      return {
        from: toIsoDate(new Date(year, 0, 1)),
        to: toIsoDate(new Date(year, 11, 31)),
      }
    },
  },
  {
    id: 'last_7_days',
    label: 'Últimos 7 dias',
    group: 'rolling',
    resolve: (reference = new Date()) => ({
      from: toIsoDate(addDays(reference, -6)),
      to: toIsoDate(reference),
    }),
  },
  {
    id: 'last_30_days',
    label: 'Últimos 30 dias',
    group: 'rolling',
    resolve: (reference = new Date()) => ({
      from: toIsoDate(addDays(reference, -29)),
      to: toIsoDate(reference),
    }),
  },
  {
    id: 'last_90_days',
    label: 'Últimos 90 dias',
    group: 'rolling',
    resolve: (reference = new Date()) => ({
      from: toIsoDate(addDays(reference, -89)),
      to: toIsoDate(reference),
    }),
  },
  {
    id: 'last_12_months',
    label: 'Últimos 12 meses',
    group: 'rolling',
    resolve: (reference = new Date()) => ({
      from: toIsoDate(addMonths(reference, -12)),
      to: toIsoDate(reference),
    }),
  },
  {
    id: 'all_time',
    label: 'Todo o período',
    group: 'other',
    resolve: () => ({ from: '', to: '' }),
  },
]

export function resolvePresetRange(preset: DateRangePreset, reference = new Date()): DateRange {
  if (preset === 'custom') {
    return { from: '', to: '' }
  }

  const definition = DATE_RANGE_PRESETS.find((item) => item.id === preset)

  return definition?.resolve(reference) ?? { from: '', to: '' }
}

export function matchPreset(from: string, to: string, reference = new Date()): DateRangePreset {
  if (isRangeEmpty(from, to)) {
    return 'all_time'
  }

  for (const preset of DATE_RANGE_PRESETS) {
    if (preset.id === 'all_time' || preset.id === 'custom') {
      continue
    }

    const range = preset.resolve(reference)

    if (range.from === from && range.to === to) {
      return preset.id
    }
  }

  return 'custom'
}

export function getPresetDefinition(preset: DateRangePreset): DateRangePresetDefinition | undefined {
  return DATE_RANGE_PRESETS.find((item) => item.id === preset)
}

export function getNavigationMode(from: Date, to: Date): NavigationMode {
  if (isSameDay(from, to)) {
    return 'day'
  }

  if (isFullWeek(from, to)) {
    return 'week'
  }

  if (isFullMonth(from, to)) {
    return 'month'
  }

  if (isFullYear(from, to)) {
    return 'year'
  }

  return 'custom'
}

export function navigateRange(from: string, to: string, direction: -1 | 1): DateRange {
  const start = parseIsoDate(from)
  const end = parseIsoDate(to)

  if (!start || !end) {
    return { from, to }
  }

  const mode = getNavigationMode(start, end)

  if (mode === 'day') {
    const next = addDays(start, direction)

    return { from: toIsoDate(next), to: toIsoDate(next) }
  }

  if (mode === 'week') {
    return {
      from: toIsoDate(addDays(start, direction * 7)),
      to: toIsoDate(addDays(end, direction * 7)),
    }
  }

  if (mode === 'month') {
    const anchor = addMonths(start, direction)

    return {
      from: toIsoDate(startOfMonth(anchor)),
      to: toIsoDate(endOfMonth(anchor)),
    }
  }

  if (mode === 'year') {
    const year = start.getFullYear() + direction

    return {
      from: toIsoDate(new Date(year, 0, 1)),
      to: toIsoDate(new Date(year, 11, 31)),
    }
  }

  const duration = diffDays(start, end) + 1

  return {
    from: toIsoDate(addDays(start, direction * duration)),
    to: toIsoDate(addDays(end, direction * duration)),
  }
}

export function formatRangeLabel(from: string, to: string): string {
  if (isRangeEmpty(from, to)) {
    return 'Todo o período'
  }

  if (!from || !to) {
    return 'Selecionar período'
  }

  if (from === to) {
    return formatDisplayDate(from)
  }

  return `${formatDisplayDate(from)} até ${formatDisplayDate(to)}`
}

export function formatMonthYearLabel(from: string, to: string): string | null {
  const start = parseIsoDate(from)
  const end = parseIsoDate(to)

  if (!start || !end || !isFullMonth(start, end)) {
    return null
  }

  const formatter = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' })
  const label = formatter.format(start)

  return label.charAt(0).toUpperCase() + label.slice(1)
}

export function getVisiblePresets(options: { variant?: 'range' | 'single'; allowAllTime?: boolean }): DateRangePresetDefinition[] {
  const { variant = 'range', allowAllTime = false } = options

  return DATE_RANGE_PRESETS.filter((preset) => {
    if (preset.id === 'all_time') {
      return allowAllTime
    }

    if (variant === 'single') {
      return preset.singleDay === true
    }

    return true
  })
}

export function isDateWithinRange(date: Date, start: Date | null, end: Date | null): boolean {
  if (!start) {
    return false
  }

  if (!end) {
    return isSameDay(date, start)
  }

  const iso = toIsoDate(date)
  const fromIso = toIsoDate(start)
  const toIso = toIsoDate(end)

  return iso >= fromIso && iso <= toIso
}

export function isRangeBoundary(date: Date, start: Date | null, end: Date | null): 'start' | 'end' | 'both' | false {
  if (!start) {
    return false
  }

  const sameStart = isSameDay(date, start)
  const sameEnd = end ? isSameDay(date, end) : false

  if (sameStart && sameEnd) {
    return 'both'
  }

  if (sameStart) {
    return 'start'
  }

  if (sameEnd) {
    return 'end'
  }

  return false
}
