import { useEffect, useMemo, useState } from 'react'
import { AlertTriangle, Check, RotateCcw, Sparkles } from 'lucide-react'
import {
  Avatar,
  Badge,
  Button,
  Card,
  CardContent,
  Checkbox,
  ColorPicker,
  Section,
  SegmentedControl,
  Switch,
} from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import {
  COLOR_PRESETS,
  contrastRatio,
  isHexColor,
  suggestSecondaryColor,
  suggestSecondaryColors,
} from '@/shared/theme/colors'
import {
  buildThemeCss,
  DEFAULT_THEME_FONT,
  DEFAULT_THEME_PRIMARY,
  EMPTY_THEME,
  loadFonts,
  normalizeTheme,
  THEME_FONTS,
  type TenantTheme,
} from '@/shared/theme/theme'

type SecondaryMode = 'auto' | 'custom'

interface TenantThemeFormProps {
  theme: TenantTheme
  submitting: boolean
  onSubmit: (theme: TenantTheme) => Promise<unknown>
}

const PREVIEW_SCOPE = '[data-theme-preview]'

function initialMode(theme: TenantTheme): SecondaryMode {
  if (!theme.secondary_color) return 'auto'

  const primary = theme.primary_color ?? DEFAULT_THEME_PRIMARY
  return suggestSecondaryColor(primary) === theme.secondary_color ? 'auto' : 'custom'
}

export function TenantThemeForm({ theme, submitting, onSubmit }: TenantThemeFormProps) {
  const [primary, setPrimary] = useState<string | null>(theme.primary_color)
  const [secondary, setSecondary] = useState<string | null>(theme.secondary_color)
  const [mode, setMode] = useState<SecondaryMode>(() => initialMode(theme))
  const [font, setFont] = useState<string | null>(theme.font_family)

  // Carrega todas as fontes para que cada opção seja exibida na própria fonte.
  useEffect(() => {
    loadFonts(
      THEME_FONTS.map((option) => option.value),
      'tenant-theme-font-catalog',
    )
  }, [])

  const effectivePrimary = isHexColor(primary) ? primary.toUpperCase() : DEFAULT_THEME_PRIMARY
  const suggested = suggestSecondaryColor(effectivePrimary)
  const harmonies = useMemo(() => suggestSecondaryColors(effectivePrimary), [effectivePrimary])
  const effectiveSecondary = mode === 'auto' ? suggested : isHexColor(secondary) ? secondary.toUpperCase() : null

  const primaryInvalid = primary !== null && !isHexColor(primary)
  const secondaryInvalid = mode === 'custom' && secondary !== null && !isHexColor(secondary)

  const draft: TenantTheme = normalizeTheme({
    primary_color: primary,
    secondary_color: effectiveSecondary,
    font_family: font,
  })
  const previewCss = buildThemeCss(
    {
      ...draft,
      primary_color: draft.primary_color ?? DEFAULT_THEME_PRIMARY,
      font_family: draft.font_family ?? DEFAULT_THEME_FONT,
    },
    PREVIEW_SCOPE,
  )

  const lowContrast = contrastRatio(effectivePrimary, '#FFFFFF') < 3

  const applyPreset = (preset: (typeof COLOR_PRESETS)[number]) => {
    setPrimary(preset.primary)
    setSecondary(preset.secondary)
    setMode(suggestSecondaryColor(preset.primary) === preset.secondary ? 'auto' : 'custom')
  }

  const resetToDefault = () => {
    setPrimary(null)
    setSecondary(null)
    setMode('auto')
    setFont(null)
  }

  const handleSubmit = async () => {
    if (primaryInvalid || secondaryInvalid) return

    // Sem nenhuma personalização, salva o padrão (todos nulos).
    const payload =
      !draft.primary_color && !draft.font_family && mode === 'auto'
        ? EMPTY_THEME
        : draft

    await onSubmit(payload)
  }

  return (
    <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
      <Card>
        <CardContent className="space-y-8">
          <Section
            title="Pares sugeridos"
            description="Combinações prontas de cor principal e secundária. Você pode ajustar depois."
          >
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
              {COLOR_PRESETS.map((preset) => {
                const selected =
                  draft.primary_color === preset.primary && draft.secondary_color === preset.secondary

                return (
                  <button
                    key={preset.name}
                    type="button"
                    onClick={() => applyPreset(preset)}
                    aria-pressed={selected}
                    className={cn(
                      'flex cursor-pointer items-center gap-2.5 rounded-lg bg-surface-2 p-2.5 text-left text-[13px] transition-colors hover:bg-surface-3',
                      selected && 'bg-primary-soft text-primary',
                    )}
                  >
                    <span className="flex shrink-0 -space-x-1.5">
                      <span className="size-6 rounded-full shadow-card" style={{ backgroundColor: preset.primary }} />
                      <span className="size-6 rounded-full shadow-card" style={{ backgroundColor: preset.secondary }} />
                    </span>
                    <span className="min-w-0 flex-1 leading-tight">{preset.name}</span>
                    {selected && <Check className="size-4 shrink-0" aria-hidden="true" />}
                  </button>
                )
              })}
            </div>
          </Section>

          <Section title="Cores" description="Defina a cor principal; a secundária pode ser calculada automaticamente.">
            <div className="grid gap-5 sm:grid-cols-2">
              <div className="space-y-2">
                <ColorPicker
                  label="Cor principal"
                  value={primary}
                  onChange={setPrimary}
                  fallback={DEFAULT_THEME_PRIMARY}
                  hint="Botões, links, itens ativos e destaques."
                  error={primaryInvalid ? 'Use o formato #RRGGBB.' : undefined}
                />
                {lowContrast && (
                  <p className="flex items-start gap-1.5 text-[13px] text-warning">
                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                    Cor muito clara: textos e links nesta cor podem ficar difíceis de ler sobre o fundo branco.
                  </p>
                )}
              </div>

              <div className="space-y-3">
                <SegmentedControl<SecondaryMode>
                  value={mode}
                  onChange={(next) => {
                    if (next === 'custom' && !isHexColor(secondary)) setSecondary(suggested)
                    setMode(next)
                  }}
                  options={[
                    { value: 'auto', label: 'Automática' },
                    { value: 'custom', label: 'Personalizada' },
                  ]}
                />

                {mode === 'auto' ? (
                  <div className="flex items-center gap-3 rounded-lg bg-surface-2 p-3">
                    <span
                      className="size-10 shrink-0 rounded-lg shadow-card"
                      style={{ backgroundColor: suggested }}
                    />
                    <div className="min-w-0 text-[13px]">
                      <p className="flex items-center gap-1 font-medium text-foreground">
                        <Sparkles className="size-3.5" aria-hidden="true" />
                        Secundária sugerida · {suggested}
                      </p>
                      <p className="text-muted">Calculada a partir da principal (complementar dividida).</p>
                    </div>
                  </div>
                ) : (
                  <ColorPicker
                    label="Cor secundária"
                    value={secondary}
                    onChange={setSecondary}
                    fallback={suggested}
                    hint="Avatares, selos e detalhes de destaque."
                    error={secondaryInvalid ? 'Use o formato #RRGGBB.' : undefined}
                  />
                )}
              </div>
            </div>

            <div>
              <p className="mb-2 text-[13px] font-medium text-foreground">Harmonias com a cor principal</p>
              <div className="flex flex-wrap gap-2">
                {harmonies.map((harmony) => {
                  const selected = effectiveSecondary === harmony.color
                  return (
                    <button
                      key={harmony.key}
                      type="button"
                      onClick={() => {
                        setSecondary(harmony.color)
                        setMode(harmony.color === suggested ? 'auto' : 'custom')
                      }}
                      aria-pressed={selected}
                      className={cn(
                        'flex cursor-pointer items-center gap-2 rounded-full bg-surface-2 py-1 pr-3 pl-1 text-[13px] transition-colors hover:bg-surface-3',
                        selected && 'bg-primary-soft text-primary',
                      )}
                    >
                      <span className="size-6 rounded-full shadow-card" style={{ backgroundColor: harmony.color }} />
                      {harmony.label}
                    </button>
                  )
                })}
              </div>
            </div>
          </Section>

          <Section title="Fonte" description="Aplicada a todos os textos do painel.">
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
              {THEME_FONTS.map((option) => {
                const selected = (font ?? DEFAULT_THEME_FONT) === option.value
                return (
                  <button
                    key={option.value}
                    type="button"
                    onClick={() => setFont(option.value === DEFAULT_THEME_FONT ? null : option.value)}
                    aria-pressed={selected}
                    className={cn(
                      'cursor-pointer rounded-lg bg-surface-2 p-3 text-left transition-colors hover:bg-surface-3',
                      selected && 'bg-primary-soft text-primary',
                    )}
                    style={{ fontFamily: `'${option.value}', ui-sans-serif, system-ui, sans-serif` }}
                  >
                    <span className="block text-xl leading-none font-semibold">Aa</span>
                    <span className="mt-1.5 block truncate text-[13px]">
                      {option.label}
                      {option.value === DEFAULT_THEME_FONT && ' (padrão)'}
                    </span>
                  </button>
                )
              })}
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
            <Button type="button" variant="ghost" onClick={resetToDefault}>
              <RotateCcw className="size-4" />
              Restaurar padrão
            </Button>
            <Button
              type="button"
              loading={submitting}
              disabled={primaryInvalid || secondaryInvalid}
              onClick={() => void handleSubmit()}
            >
              Salvar tema
            </Button>
          </div>
        </CardContent>
      </Card>

      <div className="lg:sticky lg:top-4 lg:self-start">
        <style>{previewCss}</style>
        <Card data-theme-preview="" className="font-sans">
          <CardContent className="space-y-5">
            <div>
              <p className="text-sm font-semibold text-foreground">Pré-visualização</p>
              <p className="text-[13px] text-muted">Como o painel ficará depois de salvar.</p>
            </div>

            <div className="space-y-1 rounded-lg bg-surface-2 p-2">
              <div className="flex h-9 items-center gap-3 rounded-lg bg-primary-soft px-3 text-sm font-medium text-primary">
                <span className="size-2 rounded-full bg-secondary" aria-hidden="true" />
                Item ativo do menu
              </div>
              <div className="flex h-9 items-center gap-3 px-3 text-sm text-muted">
                <span className="size-2 rounded-full bg-surface-3" aria-hidden="true" />
                Outro item
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <Button type="button" size="sm">
                Botão principal
              </Button>
              <Button type="button" size="sm" variant="secondary">
                Secundário
              </Button>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="primary">Principal</Badge>
              <Badge variant="secondary">Secundária</Badge>
              <Avatar name="Maria Souza" size="sm" />
            </div>

            <div className="flex items-center justify-between gap-3">
              <Checkbox label="Opção marcada" checked onChange={() => undefined} />
              <Switch checked onCheckedChange={() => undefined} label="Exemplo de chave" />
            </div>

            <p className="text-sm text-foreground">
              Texto do painel com um <span className="font-medium text-primary">link de exemplo</span> e um
              destaque <span className="font-medium text-secondary">secundário</span>.
            </p>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
