import test from 'node:test'
import assert from 'node:assert/strict'
import {
  addDaysToIsoDate,
  buildScheduleDay,
  buildScheduleDays,
  CALENDAR_DAY_BATCH_SIZE,
  calendarDatesFrom,
  calendarDatesUpTo,
  formatDayCard,
  formatFullDay,
  formatTime,
  isValidIsoDate,
  SEARCH_DAYS_LIMIT,
  slotPeriod,
  type ScheduleSlotView,
} from '../src/modules/service-orders/lib/schedule.ts'

const TIMEZONE = 'America/Sao_Paulo'
const DAY = '2026-10-20'

function slot(start: string, end: string, available = true): ScheduleSlotView {
  return {
    start,
    end,
    available,
    reason: available ? null : 'Conflito com OS 10',
    blocked_by: available
      ? null
      : {
          id: 'order-10',
          code: '10',
          client: 'Cliente',
          service_start: start,
          service_end: end,
          blocked_start: start,
          blocked_end: end,
        },
  }
}

// 2026-10-20 em São Paulo (UTC-3): 11:00Z = 08:00, 16:00Z = 13:00.
const morningFirst = slot('2026-10-20T11:00:00.000Z', '2026-10-20T12:30:00.000Z')
const morningLater = slot('2026-10-20T12:00:00.000Z', '2026-10-20T13:30:00.000Z')
const afternoonFirst = slot('2026-10-20T16:00:00.000Z', '2026-10-20T17:30:00.000Z')
const afternoonLater = slot('2026-10-20T18:00:00.000Z', '2026-10-20T19:30:00.000Z')
const blocked = slot('2026-10-20T13:00:00.000Z', '2026-10-20T14:30:00.000Z', false)

test('considera a tarde a partir das 13h no fuso operacional da agenda', () => {
  assert.equal(slotPeriod('2026-10-20T14:55:00.000Z', TIMEZONE), 'morning')
  assert.equal(slotPeriod('2026-10-20T15:00:00.000Z', TIMEZONE), 'morning')
  assert.equal(slotPeriod('2026-10-20T15:55:00.000Z', TIMEZONE), 'morning')
  assert.equal(slotPeriod('2026-10-20T16:00:00.000Z', TIMEZONE), 'afternoon')
  assert.equal(slotPeriod('2026-10-20T11:00:00.000Z', TIMEZONE), 'morning')
  assert.equal(slotPeriod('2026-10-20T22:00:00.000Z', TIMEZONE), 'afternoon')
  assert.equal(formatTime(morningFirst.start, TIMEZONE), '08:00')
  assert.equal(formatTime(afternoonFirst.start, TIMEZONE), '13:00')
})

test('sugere no máximo o primeiro horário da manhã e o primeiro da tarde', () => {
  const day = buildScheduleDay(DAY, {
    timezone: TIMEZONE,
    slots: [morningFirst, morningLater, afternoonFirst, afternoonLater, blocked],
  })

  assert.ok(day)
  assert.deepEqual(
    day.suggestedSlots.map((item) => item.start),
    [morningFirst.start, afternoonFirst.start],
  )
  assert.equal(day.availableSlots.length, 4)
  assert.equal(day.morningSlots.length, 2)
  assert.equal(day.afternoonSlots.length, 2)
  assert.equal(day.hasMoreSlots, true)
})

test('lista todos os horários disponíveis para a modal, agrupados por período', () => {
  const day = buildScheduleDay(DAY, {
    timezone: TIMEZONE,
    slots: [morningFirst, blocked, morningLater, afternoonFirst, afternoonLater],
  })

  assert.ok(day)
  assert.deepEqual(
    [...day.morningSlots, ...day.afternoonSlots].map((item) => item.start),
    day.availableSlots.map((item) => item.start),
  )
  assert.equal(day.availableSlots.length, 4)
})

test('não cria sugestão fictícia quando só um período tem horários', () => {
  const morningOnly = buildScheduleDay(DAY, {
    timezone: TIMEZONE,
    slots: [morningFirst, morningLater, blocked],
  })
  const afternoonOnly = buildScheduleDay(DAY, {
    timezone: TIMEZONE,
    slots: [afternoonFirst],
  })

  assert.ok(morningOnly)
  assert.deepEqual(morningOnly.suggestedSlots.map((item) => item.start), [morningFirst.start])
  assert.deepEqual(morningOnly.afternoonSlots, [])

  assert.ok(afternoonOnly)
  assert.deepEqual(afternoonOnly.suggestedSlots.map((item) => item.start), [
    afternoonFirst.start,
  ])
  assert.equal(afternoonOnly.hasMoreSlots, false)
})

test('omite dias sem nenhum horário válido', () => {
  const unavailable = buildScheduleDay(DAY, {
    timezone: TIMEZONE,
    slots: [blocked],
  })
  const empty = buildScheduleDay(DAY, { timezone: TIMEZONE, slots: [] })

  assert.equal(unavailable, null)
  assert.equal(empty, null)
})

test('mantém somente dias disponíveis ao montar a lista, em ordem cronológica', () => {
  const days = buildScheduleDays([
    {
      date: '2026-10-22',
      estimate: { timezone: TIMEZONE, slots: [afternoonFirst] },
    },
    {
      date: '2026-10-20',
      estimate: { timezone: TIMEZONE, slots: [blocked] },
    },
    {
      date: '2026-10-21',
      estimate: { timezone: TIMEZONE, slots: [morningFirst] },
    },
  ])

  assert.deepEqual(
    days.map((day) => day.date),
    ['2026-10-21', '2026-10-22'],
  )
})

test('divide a busca em lotes de 10 dias de calendário sem sobreposição', () => {
  const firstBatch = calendarDatesFrom('2026-10-25', CALENDAR_DAY_BATCH_SIZE)
  const secondBatch = calendarDatesFrom(addDaysToIsoDate(firstBatch[9], 1), CALENDAR_DAY_BATCH_SIZE)

  assert.equal(firstBatch.length, 10)
  assert.equal(secondBatch.length, 10)
  assert.equal(firstBatch[0], '2026-10-25')
  assert.equal(firstBatch[9], '2026-11-03')
  assert.equal(secondBatch[0], '2026-11-04')
  assert.equal(new Set([...firstBatch, ...secondBatch]).size, 20)
  assert.ok(!firstBatch.some((date) => secondBatch.includes(date)))
})

test('respeita o limite máximo da janela de busca', () => {
  const dates = calendarDatesUpTo('2026-10-20', addDaysToIsoDate('2026-10-20', SEARCH_DAYS_LIMIT - 1))

  assert.equal(dates.length, SEARCH_DAYS_LIMIT)
  assert.equal(dates[0], '2026-10-20')
  assert.equal(dates[SEARCH_DAYS_LIMIT - 1], '2027-01-17')
  assert.deepEqual(calendarDatesUpTo('2026-10-21', '2026-10-20'), [])
})

test('formata o cartão e o cabeçalho da modal sem deslocar a data', () => {
  assert.deepEqual(formatDayCard('2026-10-27'), { weekday: 'Ter', shortDate: '27/10' })
  assert.match(formatFullDay('2026-10-27'), /27 de outubro de 2026/)
  assert.equal(addDaysToIsoDate('2026-12-31', 1), '2027-01-01')
})

test('não entra em busca infinita com uma data de referência inválida', () => {
  assert.equal(isValidIsoDate(''), false)
  assert.equal(isValidIsoDate('2026-02-30'), false)
  assert.equal(isValidIsoDate('2026-10-20'), true)
  assert.deepEqual(calendarDatesUpTo('', ''), [])
})
