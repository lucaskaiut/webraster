import test from 'node:test'
import assert from 'node:assert/strict'
import {
  formatKilometers,
  groupBlockedSlots,
  sameInstant,
  type ScheduleSlotView,
} from '../src/modules/service-orders/lib/schedule.ts'

test('agrupa bloqueios consecutivos da mesma OS usando o intervalo real', () => {
  const slots: ScheduleSlotView[] = [
    {
      start: '2026-10-20T11:00:00.000Z',
      end: '2026-10-20T11:15:00.000Z',
      available: false,
      reason: 'Conflito com a OS 1',
      blocked_by: {
        id: 'order-1',
        code: '1',
        client: 'Cliente A',
        service_start: '2026-10-20T11:30:00.000Z',
        service_end: '2026-10-20T12:00:00.000Z',
        blocked_start: '2026-10-20T11:00:00.000Z',
        blocked_end: '2026-10-20T12:30:00.000Z',
      },
    },
    {
      start: '2026-10-20T11:15:00.000Z',
      end: '2026-10-20T11:30:00.000Z',
      available: false,
      reason: 'Conflito com a OS 1',
      blocked_by: {
        id: 'order-1',
        code: '1',
        client: 'Cliente A',
        service_start: '2026-10-20T11:30:00.000Z',
        service_end: '2026-10-20T12:00:00.000Z',
        blocked_start: '2026-10-20T11:00:00.000Z',
        blocked_end: '2026-10-20T12:30:00.000Z',
      },
    },
    {
      start: '2026-10-20T12:30:00.000Z',
      end: '2026-10-20T12:45:00.000Z',
      available: true,
      reason: null,
      blocked_by: null,
    },
  ]

  const grouped = groupBlockedSlots(slots)

  assert.equal(grouped.length, 2)
  assert.equal(grouped[0].start, '2026-10-20T11:00:00.000Z')
  assert.equal(grouped[0].end, '2026-10-20T12:30:00.000Z')
  assert.equal(grouped[1].available, true)
})

test('compara horários por instante e formata distância', () => {
  assert.equal(
    sameInstant('2026-10-20T09:00:00-03:00', '2026-10-20T12:00:00.000Z'),
    true,
  )
  assert.equal(
    sameInstant('2026-10-20T09:00:00-03:00', '2026-10-20T09:15:00-03:00'),
    false,
  )
  assert.equal(formatKilometers(12500), '12,5 km')
})
