import type { CrmPipeline } from './types'

/** Funil usado na UI: padrão do tenant ou o primeiro ativo (sem seleção manual). */
export function resolveDefaultPipeline(pipelines: CrmPipeline[] | undefined): CrmPipeline | undefined {
  if (!pipelines?.length) {
    return undefined
  }
  return pipelines.find((p) => p.is_default) ?? pipelines[0]
}
