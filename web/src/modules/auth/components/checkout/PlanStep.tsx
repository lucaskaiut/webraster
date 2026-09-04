import { Badge, Button, EmptyState, Loading } from '@/shared/design-system'
import { formatCurrency } from '@/shared/utils/format'
import { cn } from '@/shared/utils/cn'
import { CreditCard } from 'lucide-react'
import { usePublicPlansQuery } from '../../hooks/useCheckout'
import { useRegisterCheckoutStore } from '../../store/register-checkout.store'
import type { Plan } from '@/shared/types/models'

const unitLabel: Record<string, string> = {
  days: 'dias',
  weeks: 'semanas',
  months: 'mês',
  years: 'ano',
}

export function PlanStep() {
  const { data: plans = [], isLoading } = usePublicPlansQuery()
  const selectedPlan = useRegisterCheckoutStore((state) => state.selectedPlan)
  const setPlan = useRegisterCheckoutStore((state) => state.setPlan)
  const nextStep = useRegisterCheckoutStore((state) => state.nextStep)
  const previousStep = useRegisterCheckoutStore((state) => state.previousStep)

  const recommendedId = plans.length > 0 ? plans[Math.min(1, plans.length - 1)]?.id : null

  const continueNext = () => {
    if (!selectedPlan) return
    nextStep()
  }

  if (isLoading) {
    return <Loading />
  }

  if (plans.length === 0) {
    return (
      <div className="space-y-5">
        <EmptyState
          icon={CreditCard}
          title="Nenhum plano disponível"
          description="Ainda não há planos ativos para assinatura. Tente novamente mais tarde."
        />
        <Button type="button" variant="secondary" onClick={previousStep}>
          Voltar
        </Button>
      </div>
    )
  }

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-lg font-semibold text-foreground">Escolha do plano</h1>
        <p className="mt-1 text-sm text-muted">Selecione o plano ideal para sua operação.</p>
      </div>

      <div className="grid gap-3">
        {plans.map((plan) => (
          <PlanCard
            key={plan.id}
            plan={plan}
            selected={selectedPlan?.id === plan.id}
            recommended={plan.id === recommendedId}
            onSelect={() => setPlan(plan)}
          />
        ))}
      </div>

      <div className="flex justify-between pt-2">
        <Button type="button" variant="secondary" onClick={previousStep}>
          Voltar
        </Button>
        <Button type="button" disabled={!selectedPlan} onClick={continueNext}>
          Continuar
        </Button>
      </div>
    </div>
  )
}

function PlanCard({
  plan,
  selected,
  recommended,
  onSelect,
}: {
  plan: Plan
  selected: boolean
  recommended: boolean
  onSelect: () => void
}) {
  return (
    <button
      type="button"
      onClick={onSelect}
      className={cn(
        'rounded-xl border p-4 text-left transition-colors',
        selected
          ? 'border-primary bg-primary-soft/40'
          : 'border-border bg-surface hover:bg-surface-2',
      )}
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="font-semibold text-foreground">{plan.name}</h3>
            {recommended && <Badge variant="primary">Recomendado</Badge>}
          </div>
          {plan.description && (
            <p className="mt-1 text-sm text-muted">{plan.description}</p>
          )}
        </div>
        <p className="shrink-0 text-base font-semibold text-foreground">
          {formatCurrency(plan.price)}
          <span className="block text-xs font-normal text-muted">
            / {plan.recurrence_value} {unitLabel[plan.recurrence_unit] ?? plan.recurrence_unit}
          </span>
        </p>
      </div>
      {(plan.is_trial || plan.free_trial_days > 0) && (
        <p className="mt-3 text-xs font-medium text-success">
          {plan.trial_days ?? plan.free_trial_days} dias grátis
        </p>
      )}
    </button>
  )
}
