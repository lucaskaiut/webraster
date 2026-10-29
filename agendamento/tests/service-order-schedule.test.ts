import test from 'node:test'
import assert from 'node:assert/strict'
import {
  centsToReaisInput,
  itemLineTotalCents,
  itemsTotalCents,
  reaisInputToCents,
  validateServiceOrderItems,
  type ServiceOrderItemDraft,
} from '../src/modules/service-orders/lib/items.ts'
import {
  formatKilometers,
  groupBlockedSlots,
  sameInstant,
  type ScheduleSlotView,
} from '../src/modules/service-orders/lib/schedule.ts'

function item(overrides: Partial<ServiceOrderItemDraft> = {}): ServiceOrderItemDraft {
  return {
    service_id: 'service-1',
    name: 'Instalação',
    quantity: '1',
    amount: '100.00',
    ...overrides,
  }
}

test('converte valores em reais para centavos sem erro de ponto flutuante', () => {
  assert.equal(reaisInputToCents('100.00'), 10000)
  assert.equal(reaisInputToCents('99,90'), 9990)
  assert.equal(reaisInputToCents('0.1'), 10)
  assert.equal(reaisInputToCents(''), 0)
  assert.equal(reaisInputToCents('abc'), 0)
  assert.equal(centsToReaisInput(12345), '123.45')
})

test('calcula total por linha e total geral com quantidades diferentes', () => {
  const items = [
    item({ service_id: 'a', quantity: '2', amount: '150.00' }),
    item({ service_id: 'b', quantity: '3', amount: '49.90' }),
    item({ service_id: 'c', quantity: '1', amount: '0.00' }),
  ]

  assert.equal(itemLineTotalCents(items[0]), 30000)
  assert.equal(itemLineTotalCents(items[1]), 14970)
  assert.equal(itemsTotalCents(items), 44970)
})

test('ignora linhas cujo serviço saiu do catálogo', () => {
  const items = [
    item({ service_id: 'a', quantity: '2', amount: '100.00' }),
    item({ service_id: '', name: 'Serviço antigo', quantity: '1', amount: '50.00', missing: true }),
  ]

  assert.equal(itemLineTotalCents(items[1]), 0)
  assert.equal(itemsTotalCents(items), 20000)
})

test('valida linha sem serviço e quantidade menor que um', () => {
  assert.equal(
    validateServiceOrderItems([item({ service_id: '' })]),
    'Selecione o serviço em todas as linhas ou remova as linhas vazias.',
  )
  assert.equal(
    validateServiceOrderItems([item({ quantity: '0' })]),
    'Informe uma quantidade válida (mínimo 1) nos serviços realizados.',
  )
  assert.equal(
    validateServiceOrderItems([item({ quantity: 'abc' })]),
    'Informe uma quantidade válida (mínimo 1) nos serviços realizados.',
  )
  // Quantidades fracionárias são truncadas como no comportamento anterior.
  assert.equal(validateServiceOrderItems([item({ quantity: '1.5' })]), null)
  assert.equal(validateServiceOrderItems([item({ quantity: '2' })]), null)
  // Linhas fora do catálogo são excluídas no salvamento e não bloqueiam o envio.
  assert.equal(
    validateServiceOrderItems([
      item({ service_id: '', name: 'Serviço antigo', missing: true }),
    ]),
    null,
  )
})

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
