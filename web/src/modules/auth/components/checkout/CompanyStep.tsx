import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Form, TextField } from '@/shared/design-system'
import { companyStepSchema, type CompanyStepValues } from '../../schemas/checkout.schema'
import { useRegisterCheckoutStore } from '../../store/register-checkout.store'

export function CompanyStep() {
  const company = useRegisterCheckoutStore((state) => state.company)
  const setCompany = useRegisterCheckoutStore((state) => state.setCompany)
  const nextStep = useRegisterCheckoutStore((state) => state.nextStep)

  const form = useForm<CompanyStepValues>({
    resolver: zodResolver(companyStepSchema),
    defaultValues: company,
    mode: 'onChange',
  })

  const onSubmit = (values: CompanyStepValues) => {
    setCompany(values)
    nextStep()
  }

  return (
    <Form form={form} onSubmit={onSubmit} className="space-y-5">
      <div>
        <h1 className="text-lg font-semibold text-foreground">Dados da empresa</h1>
        <p className="mt-1 text-sm text-muted">Informe os dados básicos da organização.</p>
      </div>

      <TextField name="name" label="Nome da empresa" required />
      <TextField
        name="document"
        label="CPF ou CNPJ"
        placeholder="Somente números"
        required
      />
      <TextField name="phone" label="Telefone" placeholder="(41) 99999-9999" required />

      <div className="flex justify-end pt-2">
        <Button type="submit" disabled={!form.formState.isValid}>
          Continuar
        </Button>
      </div>
    </Form>
  )
}
