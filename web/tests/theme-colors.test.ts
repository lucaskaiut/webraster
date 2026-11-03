import test from 'node:test'
import assert from 'node:assert/strict'
import {
  contrastRatio,
  deriveDarkTokens,
  deriveLightTokens,
  hexToHsl,
  hslToHex,
  readableForeground,
  suggestSecondaryColor,
  suggestSecondaryColors,
} from '../src/shared/theme/colors.ts'

test('hex ↔ hsl round-trip preserves the color', () => {
  for (const hex of ['#4F46E5', '#DC2626', '#047857', '#1F2937', '#FFFFFF', '#000000']) {
    assert.equal(hslToHex(hexToHsl(hex)), hex)
  }
})

test('readable foreground picks white on dark and dark text on light colors', () => {
  assert.equal(readableForeground('#1E3A8A'), '#FFFFFF')
  assert.equal(readableForeground('#FDE047'), '#16181D')
})

test('light tokens darken hover and lighten soft background', () => {
  const tokens = deriveLightTokens('#4F46E5')
  assert.ok(hexToHsl(tokens.hover).l < hexToHsl('#4F46E5').l)
  assert.ok(hexToHsl(tokens.soft).l > 90)
})

test('dark tokens lighten very dark primaries for contrast with the dark background', () => {
  const tokens = deriveDarkTokens('#1E3A8A')
  assert.ok(contrastRatio(tokens.base, '#17181C') > contrastRatio('#1E3A8A', '#17181C'))
})

test('secondary suggestion rotates hue and stays vibrant', () => {
  const primary = hexToHsl('#2563EB')
  const secondary = hexToHsl(suggestSecondaryColor('#2563EB'))
  const distance = Math.abs(((secondary.h - primary.h + 540) % 360) - 180)
  assert.ok(Math.abs(distance - 150) < 3, `expected ~150° apart, got ${distance}`)
  assert.ok(secondary.s >= 55 && secondary.l >= 42 && secondary.l <= 55)
})

test('neutral primaries still produce a colorful suggestion', () => {
  const suggestion = hexToHsl(suggestSecondaryColor('#1F2937'))
  assert.ok(suggestion.s >= 55)
  assert.equal(suggestSecondaryColors('#1F2937').length, 4)
})
