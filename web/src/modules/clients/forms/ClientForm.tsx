import { useEffect, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Section,
  SwitchField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { onlyDigits } from '@/shared/utils/document'
import { maskCep, maskCpfCnpj, maskPhone } from '@/shared/utils/mask'
import { fetchAddressByCep } from '@/shared/services/viacep'
import type { ClientPayload } from '../services/clients.service'
import { clientSchema, type ClientFormValues } from '../schemas/client.schema'

interface ClientFormProps {
  mode: 'create' | 'edit'
  variant?: 'full' | 'basic' | 'address'
  defaultValues?: Partial<ClientFormValues>
  submitting: boolean
  submitLabel?: string
  onSubmit: (payload: ClientPayload) => Promise<unknown>
  onBack?: () => void
}

function cepHint(status: 'idle' | 'loading' | 'ok' | 'error'): string | undefined {
  if (status === 'loading') return 'Buscando endereço...'
  if (status === 'ok') return 'Endereço preenchido pelo CEP.'
  if (status === 'error') return 'CEP não encontrado. Preencha o endereço manualmente.'
  return undefined
}

export function ClientForm({
  mode,
  variant = 'full',
  defaultValues,
  submitting,
  submitLabel,
  onSubmit,
  onBack,
}: ClientFormProps) {
  const form = useForm<ClientFormValues>({
    resolver: zodResolver(clientSchema),
    defaultValues: {
      name: '',
      legal_name: '',
      trade_name: '',
      state_registration: '',
      email: '',
      financial_email: '',
      street: '',
      number: '',
      complement: '',
      neighborhood: '',
      city: '',
      state: '',
      is_active: true,
      ...defaultValues,
      document: maskCpfCnpj(defaultValues?.document ?? ''),
      phone: maskPhone(defaultValues?.phone ?? ''),
      zip: maskCep(defaultValues?.zip ?? ''),
    },
  })

  const showAddress = variant === 'full' || variant === 'address'
  const zip = useWatch({ control: form.control, name: 'zip' })
  const [cepStatus, setCepStatus] = useState<'idle' | 'loading' | 'ok' | 'error'>('idle')
  const lastFetchedCep = useRef<string | null>(null)

  useEffect(() => {
    if (!showAddress) return

    const digits = onlyDigits(zip ?? '')

    if (digits.length !== 8) {
      if (digits.length < 8) {
        lastFetchedCep.current = null
        setCepStatus('idle')
      }
      return
    }

    if (lastFetchedCep.current === digits) return

    // Só consulta ViaCEP quando o usuário alterou o CEP (evita sobrescrever dados no edit).
    if (!form.formState.dirtyFields.zip) {
      lastFetchedCep.current = digits
      return
    }

    let cancelled = false
    setCepStatus('loading')

    fetchAddressByCep(digits)
      .then((address) => {
        if (cancelled) return

        if (!address) {
          setCepStatus('error')
          return
        }

        lastFetchedCep.current = digits
        form.setValue('street', address.street, { shouldDirty: true })
        form.setValue('neighborhood', address.neighborhood, { shouldDirty: true })
        form.setValue('city', address.city, { shouldDirty: true })
        form.setValue('state', address.state, { shouldDirty: true })
        if (address.complement) {
          form.setValue('complement', address.complement, { shouldDirty: true })
        }
        setCepStatus('ok')
      })
      .catch(() => {
        if (!cancelled) setCepStatus('error')
      })

    return () => {
      cancelled = true
    }
  }, [zip, showAddress, form.formState.dirtyFields.zip, form.setValue])

  const handleSubmit = async (values: ClientFormValues) => {
    const payload: ClientPayload = {
      name: values.name,
      legal_name: values.legal_name || null,
      trade_name: values.trade_name || null,
      document: onlyDigits(values.document),
      state_registration: values.state_registration || null,
      email: values.email || null,
      financial_email: values.financial_email || null,
      phone: values.phone ? onlyDigits(values.phone) : null,
      street: values.street || null,
      number: values.number || null,
      complement: values.complement || null,
      neighborhood: values.neighborhood || null,
      city: values.city || null,
      state: values.state ? values.state.toUpperCase() : null,
      zip: values.zip ? onlyDigits(values.zip) : null,
      is_active: values.is_active,
    }

    try {
      await onSubmit(payload)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Card>
      <CardContent>
        <Form form={form} onSubmit={handleSubmit} className="space-y-8">
          {(variant === 'full' || variant === 'basic') && (
            <Section title="Informações básicas">
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField name="name" label="Nome" required className="sm:col-span-2" />
                <TextField name="legal_name" label="Razão social" className="sm:col-span-2" />
                <TextField name="trade_name" label="Nome fantasia" className="sm:col-span-2" />
                <TextField
                  name="document"
                  label="CPF/CNPJ"
                  required
                  placeholder="000.000.000-00"
                  inputMode="numeric"
                  autoComplete="off"
                  mask={maskCpfCnpj}
                />
                <TextField name="state_registration" label="Inscrição estadual" />
                <TextField name="email" label="E-mail" type="email" />
                <TextField name="financial_email" label="E-mail financeiro" type="email" />
                <TextField
                  name="phone"
                  label="Telefone"
                  placeholder="(41) 99999-9999"
                  inputMode="tel"
                  autoComplete="tel"
                  mask={maskPhone}
                />
                <SwitchField name="is_active" label="Cliente ativo" />
              </div>
            </Section>
          )}

          {showAddress && (
            <Section title="Endereço" description="Informe o CEP para preencher o endereço automaticamente.">
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                  name="zip"
                  label="CEP"
                  placeholder="00000-000"
                  inputMode="numeric"
                  autoComplete="postal-code"
                  mask={maskCep}
                  hint={cepHint(cepStatus)}
                />
                <TextField name="number" label="Número" />
                <TextField name="street" label="Logradouro" className="sm:col-span-2" />
                <TextField name="complement" label="Complemento" />
                <TextField name="neighborhood" label="Bairro" />
                <TextField name="city" label="Cidade" />
                <TextField name="state" label="UF" placeholder="PR" maxLength={2} />
              </div>
            </Section>
          )}

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            {onBack ? (
              <Button type="button" variant="secondary" onClick={onBack}>
                Voltar
              </Button>
            ) : (
              <ButtonLink to="/clients" variant="secondary">
                Cancelar
              </ButtonLink>
            )}
            <Button type="submit" loading={submitting}>
              {submitLabel ?? (mode === 'create' ? 'Criar cliente' : 'Salvar alterações')}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
