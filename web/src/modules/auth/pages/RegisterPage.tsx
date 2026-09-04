import { Card, CardContent } from '@/shared/design-system'
import { Link } from 'react-router'
import { CheckoutStepper } from '../components/checkout/CheckoutStepper'
import { CheckoutSummary } from '../components/checkout/CheckoutSummary'
import { CompanyStep } from '../components/checkout/CompanyStep'
import { UserStep } from '../components/checkout/UserStep'
import { PlanStep } from '../components/checkout/PlanStep'
import { ConfirmStep } from '../components/checkout/ConfirmStep'
import { useRegisterCheckoutStore } from '../store/register-checkout.store'

export default function RegisterPage() {
  const step = useRegisterCheckoutStore((state) => state.step)
  const reset = useRegisterCheckoutStore((state) => state.reset)

  return (
    <div className="w-full max-w-5xl">
      <Card>
        <CardContent className="p-6 sm:p-8">
          <CheckoutStepper current={step} />

          <div className="grid gap-8 lg:grid-cols-[minmax(0,1.65fr)_minmax(16rem,1fr)]">
            <div>
              {step === 0 && <CompanyStep />}
              {step === 1 && <UserStep />}
              {step === 2 && <PlanStep />}
              {step === 3 && <ConfirmStep />}
            </div>

            <CheckoutSummary />
          </div>

          <p className="mt-8 text-center text-sm text-muted">
            Já tem uma conta?{' '}
            <Link
              to="/auth/login"
              className="font-medium text-primary hover:text-primary-hover"
              onClick={() => reset()}
            >
              Entrar
            </Link>
          </p>
        </CardContent>
      </Card>
    </div>
  )
}
