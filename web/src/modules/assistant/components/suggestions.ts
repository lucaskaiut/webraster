import {
  BookOpenCheck,
  Compass,
  Lightbulb,
  Sparkles,
  type LucideIcon,
} from 'lucide-react'

export interface SuggestionCard {
  title: string
  description: string
  prompt: string
  icon: LucideIcon
  tint: 'primary' | 'success' | 'warning' | 'danger'
}

/**
 * Exemplos exibidos na tela inicial do assistente.
 * Cada projeto pode substituir estes prompts pelos do seu domínio.
 */
export const SUGGESTIONS: SuggestionCard[] = [
  {
    title: 'Como você pode me ajudar?',
    description: 'Conheça as capacidades do assistente',
    prompt: 'O que você consegue fazer por mim?',
    icon: Sparkles,
    tint: 'primary',
  },
  {
    title: 'Primeiros passos',
    description: 'Entenda como funciona esta plataforma',
    prompt: 'Como funciona esta plataforma?',
    icon: BookOpenCheck,
    tint: 'success',
  },
  {
    title: 'Me ajude a navegar',
    description: 'Descubra onde encontrar cada recurso',
    prompt: 'Onde encontro os recursos disponíveis?',
    icon: Compass,
    tint: 'warning',
  },
  {
    title: 'Sugestões e atalhos',
    description: 'Ideias para agilizar o seu dia a dia',
    prompt: 'Quais atalhos ou dicas você tem?',
    icon: Lightbulb,
    tint: 'danger',
  },
]

export const TINT_CLASSES: Record<SuggestionCard['tint'], { icon: string; iconBg: string }> = {
  primary: { icon: 'text-primary', iconBg: 'bg-primary-soft' },
  success: { icon: 'text-success', iconBg: 'bg-success-soft' },
  warning: { icon: 'text-warning', iconBg: 'bg-warning-soft' },
  danger: { icon: 'text-danger', iconBg: 'bg-danger-soft' },
}
