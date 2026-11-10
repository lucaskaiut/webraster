import test from 'node:test'
import assert from 'node:assert/strict'
import { addressReferenceUrl } from '../src/modules/service-orders/lib/address-reference.ts'

test('reconhece links de localização como URL', () => {
  assert.equal(addressReferenceUrl(' https://maps.app.goo.gl/abc123 '), 'https://maps.app.goo.gl/abc123')
  assert.equal(addressReferenceUrl('www.google.com/maps/place/x'), 'https://www.google.com/maps/place/x')
})

test('mantém textos comuns como texto', () => {
  assert.equal(addressReferenceUrl('Portão azul ao lado da padaria'), null)
  assert.equal(addressReferenceUrl('veja https://maps.app.goo.gl/abc'), null)
  assert.equal(addressReferenceUrl(''), null)
  assert.equal(addressReferenceUrl(null), null)
})
