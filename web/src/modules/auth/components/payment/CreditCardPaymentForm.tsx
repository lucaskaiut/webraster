import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, CheckboxField, Form, SelectField, TextField } from '@/shared/design-system'
import { formatCurrency } from '@/shared/utils/format'
import { onlyDigits } from '@/shared/utils/document'
import {
  creditCardSchema,
  toCreditCardPaymentDataFromCard,
  type CreditCardValues,
} from '../../schemas/checkout.schema'

interface CreditCardPaymentFormProps {
  amount: string
  loading?: boolean
  onSubmit: (paymentData: Record<string, unknown>) => void | Promise<void>
}

function formatCardNumber(value: string): string {
  const digits = onlyDigits(value).slice(0, 19)
  return digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim()
}

function formatCep(value: string): string {
  const digits = onlyDigits(value).slice(0, 8)
  if (digits.length <= 5) return digits
  return `${digits.slice(0, 5)}-${digits.slice(5)}`
}

function formatMonth(value: string): string {
  return onlyDigits(value).slice(0, 2)
}

function formatYear(value: string): string {
  return onlyDigits(value).slice(0, 4)
}

export function CreditCardPaymentForm({
  amount,
  loading = false,
  onSubmit,
}: CreditCardPaymentFormProps) {
  const form = useForm<CreditCardValues>({
    resolver: zodResolver(creditCardSchema),
    defaultValues: {
      holder_name: '',
      number: '',
      expiration_month: '',
      expiration_year: '',
      cvv: '',
      postal_code: '',
      address_number: '',
      address_complement: '',
      installments: 1,
      recurring: false,
    },
    mode: 'onBlur',
  })

  const installments = form.watch('installments') || 1
  const installmentValue = Number(amount) / installments

  return (
    <Form
      form={form}
      onSubmit={(values) => void onSubmit(toCreditCardPaymentDataFromCard(values))}
      className="space-y-5"
    >
      <div className="space-y-4">
        <TextField
          name="holder_name"
          label="Nome impresso no cartão"
          placeholder="Como está no cartão"
          autoComplete="cc-name"
          required
        />

        <TextField
          name="number"
          label="Número do cartão"
          placeholder="0000 0000 0000 0000"
          inputMode="numeric"
          autoComplete="cc-number"
          required
          onChange={(event) => {
            form.setValue('number', formatCardNumber(event.target.value), {
              shouldValidate: form.formState.isSubmitted,
            })
          }}
        />

        <div className="grid gap-4 sm:grid-cols-3">
          <TextField
            name="expiration_month"
            label="Mês"
            placeholder="MM"
            inputMode="numeric"
            autoComplete="cc-exp-month"
            required
            onChange={(event) => {
              form.setValue('expiration_month', formatMonth(event.target.value), {
                shouldValidate: form.formState.isSubmitted,
              })
            }}
          />
          <TextField
            name="expiration_year"
            label="Ano"
            placeholder="AAAA"
            inputMode="numeric"
            autoComplete="cc-exp-year"
            required
            onChange={(event) => {
              form.setValue('expiration_year', formatYear(event.target.value), {
                shouldValidate: form.formState.isSubmitted,
              })
            }}
          />
          <TextField
            name="cvv"
            label="CVV"
            placeholder="000"
            inputMode="numeric"
            autoComplete="cc-csc"
            required
            onChange={(event) => {
              form.setValue('cvv', onlyDigits(event.target.value).slice(0, 4), {
                shouldValidate: form.formState.isSubmitted,
              })
            }}
          />
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <TextField
            name="postal_code"
            label="CEP do portador"
            placeholder="00000-000"
            inputMode="numeric"
            autoComplete="postal-code"
            required
            onChange={(event) => {
              form.setValue('postal_code', formatCep(event.target.value), {
                shouldValidate: form.formState.isSubmitted,
              })
            }}
          />
          <TextField
            name="address_number"
            label="Número"
            placeholder="123"
            autoComplete="address-line2"
            required
          />
        </div>

        <TextField
          name="address_complement"
          label="Complemento"
          placeholder="Apto, sala (opcional)"
          autoComplete="address-line3"
        />

        <SelectField
          name="installments"
          label="Parcelamento"
          hint={
            installments > 1
              ? `${installments}x de ${formatCurrency(installmentValue.toFixed(2))}`
              : 'Pagamento à vista'
          }
          options={Array.from({ length: 12 }, (_, i) => {
            const count = i + 1
            const value = Number(amount) / count
            return {
              value: String(count),
              label:
                count === 1
                  ? `1x de ${formatCurrency(amount)}`
                  : `${count}x de ${formatCurrency(value.toFixed(2))}`,
            }
          })}
          required
        />

        <CheckboxField
          name="recurring"
          label="Usar este cartão de forma recorrente"
          description="Permite cobrar automaticamente nas próximas faturas da assinatura."
        />
      </div>

      <Button type="submit" loading={loading} className="w-full sm:w-auto sm:min-w-64">
        Pagar {formatCurrency(amount)}
      </Button>
    </Form>
  )
}
