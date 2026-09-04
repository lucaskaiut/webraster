import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Form, TextField } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import {
  PASSWORD_REQUIREMENTS,
  userStepSchema,
  type UserStepValues,
} from '../../schemas/checkout.schema'
import { useRegisterCheckoutStore } from '../../store/register-checkout.store'

export function UserStep() {
  const user = useRegisterCheckoutStore((state) => state.user)
  const setUser = useRegisterCheckoutStore((state) => state.setUser)
  const nextStep = useRegisterCheckoutStore((state) => state.nextStep)
  const previousStep = useRegisterCheckoutStore((state) => state.previousStep)

  const form = useForm<UserStepValues>({
    resolver: zodResolver(userStepSchema),
    defaultValues: user,
    mode: 'onChange',
  })

  const password = useWatch({ control: form.control, name: 'password' }) ?? ''

  const onSubmit = (values: UserStepValues) => {
    setUser(values)
    nextStep()
  }

  return (
    <Form form={form} onSubmit={onSubmit} className="space-y-5">
      <div>
        <h1 className="text-lg font-semibold text-foreground">Dados do usuário</h1>
        <p className="mt-1 text-sm text-muted">Crie o administrador da conta.</p>
      </div>

      <TextField name="name" label="Nome completo" required />
      <TextField name="email" label="E-mail" type="email" required />
      <TextField name="password" label="Senha" type="password" required />
      <TextField name="password_confirmation" label="Confirmar senha" type="password" required />

      <ul className="space-y-1.5 rounded-lg bg-surface-2 p-3">
        {PASSWORD_REQUIREMENTS.map((rule) => {
          const ok = rule.test(password)

          return (
            <li
              key={rule.id}
              className={cn('text-xs', ok ? 'text-success' : 'text-muted')}
            >
              {ok ? '✓' : '○'} {rule.label}
            </li>
          )
        })}
      </ul>

      <div className="flex justify-between pt-2">
        <Button type="button" variant="secondary" onClick={previousStep}>
          Voltar
        </Button>
        <Button type="submit" disabled={!form.formState.isValid}>
          Continuar
        </Button>
      </div>
    </Form>
  )
}
