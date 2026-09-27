export function conversationAiModeLabel(mode: string): string | null {
  switch (mode) {
    case 'ai_active':
      return 'IA ativa'
    case 'human_handoff':
      return 'Necessita atendimento humano'
    case 'ai_disabled':
      return 'IA desativada'
    default:
      return null
  }
}

export function conversationNeedsHumanAttention(mode: string): boolean {
  return mode === 'human_handoff'
}
