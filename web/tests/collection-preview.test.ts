import test from 'node:test'
import assert from 'node:assert/strict'
import { renderCollectionPreview } from '../src/modules/finance/lib/collection.ts'

test('substitui as variáveis da régua pelos dados de exemplo', () => {
  assert.equal(
    renderCollectionPreview('{cliente}, pague {codigo} ({valor}) até {vencimento}: {link}'),
    'Maria Souza, pague 1024 (R$ 129,90) até 15/11/2026: https://www.asaas.com/i/exemplo',
  )
})

test('mantém o texto sem variáveis', () => {
  assert.equal(renderCollectionPreview('Olá!'), 'Olá!')
})
