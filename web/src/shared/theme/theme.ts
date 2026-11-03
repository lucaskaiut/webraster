import { deriveDarkTokens, deriveLightTokens, isHexColor, type ColorTokens } from './colors'

/** Tema do painel por tenant. `null` = padrão da aplicação. */
export interface TenantTheme {
  primary_color: string | null
  secondary_color: string | null
  font_family: string | null
}

export const EMPTY_THEME: TenantTheme = {
  primary_color: null,
  secondary_color: null,
  font_family: null,
}

/** Cores padrão da aplicação (ver `index.css`). */
export const DEFAULT_THEME_PRIMARY = '#4F46E5'
export const DEFAULT_THEME_FONT = 'Inter'

export interface ThemeFontOption {
  value: string
  label: string
  /** Pesos disponíveis no Google Fonts. */
  weights: string
}

export const THEME_FONTS: ThemeFontOption[] = [
  { value: 'Inter', label: 'Inter', weights: '400;500;600;700' },
  { value: 'Roboto', label: 'Roboto', weights: '400;500;600;700' },
  { value: 'Open Sans', label: 'Open Sans', weights: '400;500;600;700' },
  { value: 'Lato', label: 'Lato', weights: '400;700' },
  { value: 'Montserrat', label: 'Montserrat', weights: '400;500;600;700' },
  { value: 'Poppins', label: 'Poppins', weights: '400;500;600;700' },
  { value: 'Nunito', label: 'Nunito', weights: '400;500;600;700' },
  { value: 'Source Sans 3', label: 'Source Sans 3', weights: '400;500;600;700' },
  { value: 'Work Sans', label: 'Work Sans', weights: '400;500;600;700' },
  { value: 'Manrope', label: 'Manrope', weights: '400;500;600;700' },
  { value: 'DM Sans', label: 'DM Sans', weights: '400;500;600;700' },
  { value: 'IBM Plex Sans', label: 'IBM Plex Sans', weights: '400;500;600;700' },
  { value: 'Raleway', label: 'Raleway', weights: '400;500;600;700' },
  { value: 'Rubik', label: 'Rubik', weights: '400;500;600;700' },
]

const STYLE_ELEMENT_ID = 'tenant-theme'

export function normalizeTheme(theme: Partial<TenantTheme> | null | undefined): TenantTheme {
  const font = theme?.font_family
  return {
    primary_color: isHexColor(theme?.primary_color) ? theme.primary_color.toUpperCase() : null,
    secondary_color: isHexColor(theme?.secondary_color) ? theme.secondary_color.toUpperCase() : null,
    font_family: font && THEME_FONTS.some((option) => option.value === font) ? font : null,
  }
}

export function isDefaultTheme(theme: TenantTheme): boolean {
  return !theme.primary_color && !theme.secondary_color && !theme.font_family
}

export function googleFontsUrl(families: string[]): string | null {
  const options = THEME_FONTS.filter((option) => families.includes(option.value))

  if (options.length === 0) return null

  const query = options
    .map((option) => `family=${option.value.replaceAll(' ', '+')}:wght@${option.weights}`)
    .join('&')

  return `https://fonts.googleapis.com/css2?${query}&display=swap`
}

/** Injeta (uma única vez por URL) a folha do Google Fonts no `<head>`. */
export function loadFonts(families: string[], id: string): void {
  if (typeof document === 'undefined') return

  const url = googleFontsUrl(families)
  const existing = document.getElementById(id) as HTMLLinkElement | null

  if (!url) {
    existing?.remove()
    return
  }

  if (existing?.href === url) return

  const link = existing ?? document.createElement('link')
  link.id = id
  link.rel = 'stylesheet'
  link.href = url

  if (!existing) document.head.appendChild(link)
}

function fontStack(font: string): string {
  return `'${font}'`
}

function colorDeclarations(prefix: 'primary' | 'secondary', tokens: ColorTokens): string[] {
  return [
    `--app-${prefix}: ${tokens.base};`,
    `--app-${prefix}-hover: ${tokens.hover};`,
    `--app-${prefix}-soft: ${tokens.soft};`,
    `--app-${prefix}-fg: ${tokens.foreground};`,
  ]
}

/**
 * Gera as regras CSS que sobrescrevem os tokens do design system.
 * `scope` permite aplicar o tema só dentro de um contêiner (pré-visualização).
 */
export function buildThemeCss(theme: TenantTheme, scope = ':root'): string {
  const light: string[] = []
  const dark: string[] = []
  const primary = theme.primary_color
  const secondary = theme.secondary_color ?? primary

  if (primary) {
    const lightTokens = deriveLightTokens(primary)
    const darkTokens = deriveDarkTokens(primary)
    light.push(...colorDeclarations('primary', lightTokens), `--app-ring: ${lightTokens.ring};`)
    dark.push(...colorDeclarations('primary', darkTokens), `--app-ring: ${darkTokens.ring};`)
  }

  if (secondary) {
    light.push(...colorDeclarations('secondary', deriveLightTokens(secondary)))
    dark.push(...colorDeclarations('secondary', deriveDarkTokens(secondary)))
  } else if (scope !== ':root') {
    // Fora da raiz, a secundária padrão (= primária) precisa ser recalculada no escopo.
    for (const target of [light, dark]) {
      target.push(
        '--app-secondary: var(--app-primary);',
        '--app-secondary-hover: var(--app-primary-hover);',
        '--app-secondary-soft: var(--app-primary-soft);',
        '--app-secondary-fg: var(--app-primary-fg);',
      )
    }
  }

  if (theme.font_family) {
    light.push(`--app-font: ${fontStack(theme.font_family)};`)
  }

  const darkScope = scope === ':root' ? ':root.dark' : `.dark ${scope}`
  const rules: string[] = []

  if (light.length > 0) rules.push(`${scope} { ${light.join(' ')} }`)
  if (dark.length > 0) rules.push(`${darkScope} { ${dark.join(' ')} }`)

  return rules.join('\n')
}

/** Aplica o tema do tenant em toda a aplicação. */
export function applyTheme(theme: Partial<TenantTheme> | null | undefined): void {
  if (typeof document === 'undefined') return

  const normalized = normalizeTheme(theme)
  const css = buildThemeCss(normalized)
  let style = document.getElementById(STYLE_ELEMENT_ID) as HTMLStyleElement | null

  if (css === '') {
    style?.remove()
  } else {
    if (!style) {
      style = document.createElement('style')
      style.id = STYLE_ELEMENT_ID
      document.head.appendChild(style)
    }

    if (style.textContent !== css) style.textContent = css
  }

  loadFonts(normalized.font_family ? [normalized.font_family] : [], 'tenant-theme-font')
}
