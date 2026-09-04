import type { ReactNode } from 'react'
import { formatCurrency } from '@/shared/utils/format'
import { Badge, Card, CardContent } from '@/shared/design-system'
import { useRegisterCheckoutStore } from '../../store/register-checkout.store'

const unitLabel: Record<string, string> = {
  days: 'dias',
  weeks: 'semanas',
  months: 'mês',
  years: 'ano',
}

export function CheckoutSummary() {
  const plan = useRegisterCheckoutStore((state) => state.selectedPlan)

  const dueToday =
    plan && (plan.requires_immediate_payment ?? !(plan.free_trial_days > 0))
      ? Number(plan.price)
      : 0

  return (
    <aside className="lg:sticky lg:top-8">
      <Card>
        <CardContent className="space-y-4 p-5">
          <div>
            <p className="text-xs font-medium tracking-wide text-muted uppercase">Resumo</p>
            <h2 className="mt-1 text-base font-semibold text-foreground">
              {plan?.name ?? 'Escolha um plano'}
            </h2>
            {plan?.description && (
              <p className="mt-1 text-sm text-muted">{plan.description}</p>
            )}
          </div>

          {plan ? (
            <div className="space-y-3 border-t border-border pt-4 text-sm">
              <Row
                label="Valor"
                value={`${formatCurrency(plan.price)} / ${plan.recurrence_value} ${unitLabel[plan.recurrence_unit] ?? plan.recurrence_unit}`}
              />
              <Row
                label="Período grátis"
                value={
                  plan.is_trial || plan.free_trial_days > 0 ? (
                    <Badge variant="success">
                      {plan.trial_days ?? plan.free_trial_days} dias
                    </Badge>
                  ) : (
                    'Sem trial'
                  )
                }
              />
              <div className="flex items-center justify-between border-t border-border pt-3">
                <span className="font-medium text-foreground">Total devido hoje</span>
                <span className="text-lg font-semibold text-foreground">
                  {formatCurrency(dueToday)}
                </span>
              </div>
              {plan.is_trial || plan.free_trial_days > 0 ? (
                <p className="text-xs text-muted">
                  Cobrança inicia após o período de testes. Você escolhe o método de pagamento
                  na plataforma.
                </p>
              ) : (
                <p className="text-xs text-muted">
                  Após o cadastro você escolhe o método e conclui o pagamento na plataforma.
                </p>
              )}
            </div>
          ) : (
            <p className="text-sm text-muted">
              O resumo do plano aparece aqui assim que você selecionar uma opção.
            </p>
          )}
        </CardContent>
      </Card>
    </aside>
  )
}

function Row({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex items-start justify-between gap-3">
      <span className="text-muted">{label}</span>
      <span className="text-right font-medium text-foreground">{value}</span>
    </div>
  )
}
