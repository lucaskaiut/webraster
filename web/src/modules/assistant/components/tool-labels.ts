/**
 * Rótulos amigáveis exibidos enquanto as ferramentas do agente são executadas.
 * Registre aqui o texto de cada ferramenta do seu projeto; o fallback genérico
 * é usado quando o nome não é mapeado.
 */
export const TOOL_RUNNING_LABELS: Record<string, string> = {}

export const TOOL_DONE_LABELS: Record<string, string> = {}

export function toolRunningLabel(name: string): string {
  return TOOL_RUNNING_LABELS[name] ?? 'Executando ferramenta...'
}

export function toolDoneLabel(name: string): string {
  return TOOL_DONE_LABELS[name] ?? 'Operação concluída'
}
