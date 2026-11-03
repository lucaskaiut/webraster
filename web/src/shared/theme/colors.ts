/**
 * Utilitários de cor do tema do painel: conversões, contraste (WCAG),
 * derivação dos tokens (hover, soft, foreground, ring) e sugestão da cor
 * secundária a partir da primária por harmonias do círculo cromático.
 *
 * Sem dependências: usado no boot (antes do React) e nos testes.
 */

export interface Hsl {
  h: number
  s: number
  l: number
}

export interface Rgb {
  r: number
  g: number
  b: number
}

const HEX_PATTERN = /^#[0-9a-fA-F]{6}$/

export function isHexColor(value: unknown): value is string {
  return typeof value === 'string' && HEX_PATTERN.test(value)
}

const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value))

export function hexToRgb(hex: string): Rgb {
  const value = hex.replace('#', '')
  return {
    r: parseInt(value.slice(0, 2), 16),
    g: parseInt(value.slice(2, 4), 16),
    b: parseInt(value.slice(4, 6), 16),
  }
}

export function rgbToHex({ r, g, b }: Rgb): string {
  const part = (channel: number) => Math.round(clamp(channel, 0, 255)).toString(16).padStart(2, '0')
  return `#${part(r)}${part(g)}${part(b)}`.toUpperCase()
}

export function hexToHsl(hex: string): Hsl {
  const { r, g, b } = hexToRgb(hex)
  const rn = r / 255
  const gn = g / 255
  const bn = b / 255
  const max = Math.max(rn, gn, bn)
  const min = Math.min(rn, gn, bn)
  const l = (max + min) / 2
  const delta = max - min

  if (delta === 0) {
    return { h: 0, s: 0, l: l * 100 }
  }

  const s = delta / (1 - Math.abs(2 * l - 1))
  let h: number

  if (max === rn) h = ((gn - bn) / delta) % 6
  else if (max === gn) h = (bn - rn) / delta + 2
  else h = (rn - gn) / delta + 4

  return { h: (h * 60 + 360) % 360, s: s * 100, l: l * 100 }
}

export function hslToHex({ h, s, l }: Hsl): string {
  const sn = clamp(s, 0, 100) / 100
  const ln = clamp(l, 0, 100) / 100
  const hue = ((h % 360) + 360) % 360
  const c = (1 - Math.abs(2 * ln - 1)) * sn
  const x = c * (1 - Math.abs(((hue / 60) % 2) - 1))
  const m = ln - c / 2
  let rgb: [number, number, number]

  if (hue < 60) rgb = [c, x, 0]
  else if (hue < 120) rgb = [x, c, 0]
  else if (hue < 180) rgb = [0, c, x]
  else if (hue < 240) rgb = [0, x, c]
  else if (hue < 300) rgb = [x, 0, c]
  else rgb = [c, 0, x]

  return rgbToHex({ r: (rgb[0] + m) * 255, g: (rgb[1] + m) * 255, b: (rgb[2] + m) * 255 })
}

/** Mistura `hex` com `target` (0 = só hex, 1 = só target). */
export function mix(hex: string, target: string, amount: number): string {
  const a = hexToRgb(hex)
  const b = hexToRgb(target)
  const t = clamp(amount, 0, 1)
  return rgbToHex({ r: a.r + (b.r - a.r) * t, g: a.g + (b.g - a.g) * t, b: a.b + (b.b - a.b) * t })
}

export function adjustLightness(hex: string, delta: number): string {
  const hsl = hexToHsl(hex)
  return hslToHex({ ...hsl, l: hsl.l + delta })
}

export function rgba(hex: string, alpha: number): string {
  const { r, g, b } = hexToRgb(hex)
  return `rgb(${r} ${g} ${b} / ${alpha})`
}

export function relativeLuminance(hex: string): number {
  const { r, g, b } = hexToRgb(hex)
  const channel = (value: number) => {
    const c = value / 255
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
  }
  return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)
}

export function contrastRatio(a: string, b: string): number {
  const la = relativeLuminance(a)
  const lb = relativeLuminance(b)
  return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05)
}

export const LIGHT_TEXT = '#FFFFFF'
export const DARK_TEXT = '#16181D'

/** Cor de texto (branco ou quase preto) mais legível sobre `background`. */
export function readableForeground(background: string): string {
  return contrastRatio(background, LIGHT_TEXT) >= contrastRatio(background, DARK_TEXT)
    ? LIGHT_TEXT
    : DARK_TEXT
}

/** Tokens derivados de uma cor base, para os modos claro e escuro. */
export interface ColorTokens {
  base: string
  hover: string
  soft: string
  foreground: string
  ring: string
}

export function deriveLightTokens(base: string): ColorTokens {
  return {
    base,
    hover: adjustLightness(base, -7),
    soft: mix(base, '#FFFFFF', 0.9),
    foreground: readableForeground(base),
    ring: rgba(base, 0.45),
  }
}

/** No modo escuro, cores muito escuras são clareadas para manter contraste com o fundo. */
export function deriveDarkTokens(base: string): ColorTokens {
  const hsl = hexToHsl(base)
  const adjusted = hsl.l < 55 ? hslToHex({ ...hsl, l: Math.min(hsl.l + 8, 62) }) : base

  return {
    base: adjusted,
    hover: adjustLightness(adjusted, 6),
    soft: rgba(adjusted, 0.14),
    foreground: readableForeground(adjusted),
    ring: rgba(adjusted, 0.55),
  }
}

export type HarmonyKey = 'complementary' | 'split' | 'triadic' | 'analogous'

export interface HarmonySuggestion {
  key: HarmonyKey
  label: string
  color: string
}

const HARMONIES: Array<{ key: HarmonyKey; label: string; rotation: number }> = [
  { key: 'complementary', label: 'Complementar', rotation: 180 },
  { key: 'split', label: 'Complementar dividida', rotation: 150 },
  { key: 'triadic', label: 'Triádica', rotation: 120 },
  { key: 'analogous', label: 'Análoga', rotation: 30 },
]

/**
 * Gira o matiz da primária e normaliza saturação/luminosidade para que a
 * secundária seja vibrante e legível como cor de destaque.
 */
export function harmonyColor(primary: string, rotation: number): string {
  const { h, s, l } = hexToHsl(primary)
  // Primária neutra (cinza/preto): matiz não existe; usa um azul de destaque.
  const hue = s < 8 ? 205 : h + rotation
  return hslToHex({ h: hue, s: clamp(s < 8 ? 80 : s, 55, 85), l: clamp(l, 42, 55) })
}

export function suggestSecondaryColors(primary: string): HarmonySuggestion[] {
  return HARMONIES.map((harmony) => ({
    key: harmony.key,
    label: harmony.label,
    color: harmonyColor(primary, harmony.rotation),
  }))
}

/** Sugestão principal: complementar dividida (contraste sem conflito com a primária). */
export function suggestSecondaryColor(primary: string): string {
  return harmonyColor(primary, 150)
}

export interface ColorPreset {
  name: string
  primary: string
  secondary: string
}

export const COLOR_PRESETS: ColorPreset[] = [
  { name: 'Índigo e ciano', primary: '#4F46E5', secondary: '#0891B2' },
  { name: 'Azul e laranja', primary: '#2563EB', secondary: '#EA580C' },
  { name: 'Marinho e dourado', primary: '#1E3A8A', secondary: '#D97706' },
  { name: 'Esmeralda e âmbar', primary: '#047857', secondary: '#D97706' },
  { name: 'Teal e coral', primary: '#0F766E', secondary: '#E11D48' },
  { name: 'Roxo e rosa', primary: '#7C3AED', secondary: '#DB2777' },
  { name: 'Vermelho e grafite', primary: '#DC2626', secondary: '#475569' },
  { name: 'Grafite e lima', primary: '#1F2937', secondary: '#65A30D' },
]
