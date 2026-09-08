export interface DateRange {
  from: string
  to: string
}

function pad2(value: number): string {
  return String(value).padStart(2, '0')
}

export function toIsoDate(date: Date): string {
  return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`
}

export function toLocalDateTimeValue(date: Date): string {
  return `${toIsoDate(date)}T${pad2(date.getHours())}:${pad2(date.getMinutes())}`
}

export function extractIsoDate(value: string): string | null {
  const match = /^(\d{4}-\d{2}-\d{2})/.exec(value.trim())

  return match?.[1] ?? null
}

export function extractLocalTime(value: string): string {
  const match = /T(\d{2}):(\d{2})/.exec(value)

  if (!match) {
    return '00:00'
  }

  return `${match[1]}:${match[2]}`
}

export function parseIsoDate(value: string): Date | null {
  const isoDate = extractIsoDate(value)

  if (!isoDate) {
    return null
  }

  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(isoDate)

  if (!match) {
    return null
  }

  return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
}

export function parseLocalDateTime(value: string): Date | null {
  const trimmed = value.trim()

  if (!trimmed) {
    return null
  }

  const localMatch = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/.exec(trimmed)

  if (localMatch) {
    const date = new Date(
      Number(localMatch[1]),
      Number(localMatch[2]) - 1,
      Number(localMatch[3]),
      Number(localMatch[4]),
      Number(localMatch[5]),
      Number(localMatch[6] ?? 0),
    )

    if (
      date.getFullYear() !== Number(localMatch[1])
      || date.getMonth() !== Number(localMatch[2]) - 1
      || date.getDate() !== Number(localMatch[3])
    ) {
      return null
    }

    return date
  }

  const dateOnly = parseIsoDate(trimmed)

  if (dateOnly) {
    return dateOnly
  }

  const parsed = new Date(trimmed)

  return Number.isNaN(parsed.getTime()) ? null : parsed
}

export function formatDisplayDate(iso: string): string {
  const date = parseIsoDate(iso)

  if (!date) {
    return ''
  }

  return `${pad2(date.getDate())}/${pad2(date.getMonth() + 1)}/${date.getFullYear()}`
}

export function formatDisplayDateTime(value: string): string {
  const date = parseLocalDateTime(value)

  if (!date) {
    return ''
  }

  return `${pad2(date.getDate())}/${pad2(date.getMonth() + 1)}/${date.getFullYear()} ${pad2(date.getHours())}:${pad2(date.getMinutes())}`
}

export function parseDisplayDate(value: string): string | null {
  const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value.trim())

  if (!match) {
    return null
  }

  const day = Number(match[1])
  const month = Number(match[2])
  const year = Number(match[3])
  const date = new Date(year, month - 1, day)

  if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
    return null
  }

  return toIsoDate(date)
}

export function parseDisplayDateTime(value: string): string | null {
  const match = /^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2}))?$/.exec(value.trim())

  if (!match) {
    return null
  }

  const day = Number(match[1])
  const month = Number(match[2])
  const year = Number(match[3])
  const hours = Number(match[4] ?? 0)
  const minutes = Number(match[5] ?? 0)

  if (hours > 23 || minutes > 59) {
    return null
  }

  const date = new Date(year, month - 1, day, hours, minutes)

  if (
    date.getFullYear() !== year
    || date.getMonth() !== month - 1
    || date.getDate() !== day
    || date.getHours() !== hours
    || date.getMinutes() !== minutes
  ) {
    return null
  }

  return toLocalDateTimeValue(date)
}

export function combineIsoDateAndTime(isoDate: string, time: string): string | null {
  const date = parseIsoDate(isoDate)

  if (!date) {
    return null
  }

  const timeMatch = /^(\d{2}):(\d{2})$/.exec(time)

  if (!timeMatch) {
    return null
  }

  date.setHours(Number(timeMatch[1]), Number(timeMatch[2]), 0, 0)

  return toLocalDateTimeValue(date)
}

export function maskDateInput(input: string): string {
  const digits = input.replace(/\D/g, '').slice(0, 8)

  if (digits.length <= 2) {
    return digits
  }

  if (digits.length <= 4) {
    return `${digits.slice(0, 2)}/${digits.slice(2)}`
  }

  return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`
}

export function maskDateTimeInput(input: string): string {
  const digits = input.replace(/\D/g, '').slice(0, 12)
  const datePart = maskDateInput(digits.slice(0, 8))
  const timeDigits = digits.slice(8)

  if (!timeDigits) {
    return datePart
  }

  if (timeDigits.length <= 2) {
    return `${datePart} ${timeDigits}`
  }

  return `${datePart} ${timeDigits.slice(0, 2)}:${timeDigits.slice(2)}`
}

export function isSameDay(a: Date, b: Date): boolean {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
}

export function getCalendarWeeks(viewDate: Date): Date[][] {
  const year = viewDate.getFullYear()
  const month = viewDate.getMonth()
  const firstOfMonth = new Date(year, month, 1)

  let startOffset = firstOfMonth.getDay() - 1

  if (startOffset < 0) {
    startOffset = 6
  }

  const startDate = addDays(firstOfMonth, -startOffset)
  const weeks: Date[][] = []

  for (let week = 0; week < 6; week++) {
    const days: Date[] = []

    for (let day = 0; day < 7; day++) {
      days.push(addDays(startDate, week * 7 + day))
    }

    weeks.push(days)
  }

  return weeks
}

export function isDateInRange(date: Date, min?: string, max?: string): boolean {
  const iso = toIsoDate(date)

  if (min && iso < min) {
    return false
  }

  if (max && iso > max) {
    return false
  }

  return true
}

export function addDays(date: Date, amount: number): Date {
  const copy = new Date(date)

  copy.setDate(copy.getDate() + amount)

  return copy
}

export function startOfWeek(date: Date): Date {
  const copy = new Date(date)
  const day = copy.getDay()

  copy.setDate(copy.getDate() + (day === 0 ? -6 : 1 - day))

  return copy
}

export function startOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1)
}

export function endOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth() + 1, 0)
}
