import { CHECKOUT_STEPS, type CheckoutStep } from '../../store/register-checkout.store'
import { cn } from '@/shared/utils/cn'

export function CheckoutStepper({ current }: { current: CheckoutStep }) {
  return (
    <nav aria-label="Etapas do cadastro" className="mb-8">
      <ol className="flex flex-wrap items-center gap-2">
        {CHECKOUT_STEPS.map((step, index) => {
          const done = current > step.id
          const active = current === step.id

          return (
            <li key={step.id} className="flex items-center gap-2">
              {index > 0 && (
                <span className="hidden text-subtle sm:inline" aria-hidden="true">
                  /
                </span>
              )}
              <span
                className={cn(
                  'inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors',
                  active && 'bg-primary-soft text-primary',
                  done && !active && 'text-foreground',
                  !done && !active && 'text-muted',
                )}
              >
                <span
                  className={cn(
                    'flex size-5 items-center justify-center rounded-full text-[11px] font-semibold',
                    active && 'bg-primary text-primary-foreground',
                    done && !active && 'bg-success text-white',
                    !done && !active && 'bg-surface-3 text-muted',
                  )}
                >
                  {done && !active ? '✓' : index + 1}
                </span>
                {step.label}
              </span>
            </li>
          )
        })}
      </ol>
    </nav>
  )
}
