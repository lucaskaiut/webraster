import test from 'node:test'
import assert from 'node:assert/strict'
import { isAllowedWhileBlocked } from '../src/modules/finance/lib/financial-access.ts'

test('libera só o financeiro do portal durante o bloqueio', () => {
  assert.equal(isAllowedWhileBlocked('/finance/portal/billings'), true)
  assert.equal(isAllowedWhileBlocked('/finance/portal/billings/abc'), true)
  assert.equal(isAllowedWhileBlocked('/finance/portal/history'), true)
  assert.equal(isAllowedWhileBlocked('/dashboard'), false)
  assert.equal(isAllowedWhileBlocked('/vehicles'), false)
  assert.equal(isAllowedWhileBlocked('/finance/portalx'), false)
})
