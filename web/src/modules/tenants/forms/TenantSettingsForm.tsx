import { useState } from 'react'
import { Controller, useForm, useFormContext } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  Card,
  CardContent,
  Form,
  ImageUploader,
  Section,
  TextField,
  type ImageValue,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { onlyDigits } from '@/shared/utils/document'
import { maskCpfCnpj, maskPhone } from '@/shared/utils/mask'
import type { Tenant } from '@/shared/types/models'
import type { UpdateTenantPayload } from '../services/tenants.service'
import {
  tenantSettingsSchema,
  type TenantSettingsFormValues,
} from '../schemas/tenant.schema'

interface TenantSettingsFormProps {
  tenant: Tenant
  submitting: boolean
  onSubmit: (payload: UpdateTenantPayload) => Promise<unknown>
}

export function TenantSettingsForm({ tenant, submitting, onSubmit }: TenantSettingsFormProps) {
  const form = useForm<TenantSettingsFormValues>({
    resolver: zodResolver(tenantSettingsSchema),
    defaultValues: {
      name: tenant.name,
      document: maskCpfCnpj(tenant.document),
      email: tenant.email,
      phone: maskPhone(tenant.phone ?? ''),
      logo_path: tenant.logo_path,
      favicon_path: tenant.favicon_path,
    },
  })

  const handleSubmit = async (values: TenantSettingsFormValues) => {
    const payload: UpdateTenantPayload = {
      name: values.name,
      document: onlyDigits(values.document),
      email: values.email,
      phone: onlyDigits(values.phone),
      logo_path: values.logo_path,
      favicon_path: values.favicon_path,
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
          <Section title="Dados da empresa">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="name" label="Nome" required className="sm:col-span-2" />
              <TextField
                name="document"
                label="CPF / CNPJ"
                required
                placeholder="000.000.000-00"
                inputMode="numeric"
                mask={maskCpfCnpj}
              />
              <TextField name="email" label="E-mail" type="email" required />
              <TextField
                name="phone"
                label="Telefone"
                required
                placeholder="(41) 99999-9999"
                inputMode="tel"
                mask={maskPhone}
              />
            </div>
          </Section>

          <Section
            title="Identidade visual"
            description="Logo exibida no painel e favicon da aba do navegador."
          >
            <div className="grid gap-4 sm:grid-cols-2">
              <ImageField
                name="logo_path"
                label="Logo"
                hint="PNG, JPG ou WEBP. Recomendado fundo transparente."
                initialUrl={tenant.logo_url}
              />
              <ImageField
                name="favicon_path"
                label="Favicon"
                hint="PNG ou ICO em formato quadrado."
                initialUrl={tenant.favicon_url}
                accept=".ico,image/png,image/x-icon"
              />
            </div>
          </Section>

          <div className="flex justify-end">
            <Button type="submit" loading={submitting}>
              Salvar alterações
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}

function ImageField({
  name,
  label,
  hint,
  initialUrl,
  accept,
}: {
  name: 'logo_path' | 'favicon_path'
  label: string
  hint?: string
  initialUrl?: string | null
  accept?: string
}) {
  const { control } = useFormContext<TenantSettingsFormValues>()
  const [value, setValue] = useState<ImageValue | null>(
    initialUrl ? { url: initialUrl } : null,
  )

  return (
    <Controller
      control={control}
      name={name}
      render={({ field }) => (
        <ImageUploader
          label={label}
          hint={hint}
          accept={accept}
          value={value}
          onChange={(next) => {
            setValue(next)
            field.onChange(next?.path ?? null)
          }}
        />
      )}
    />
  )
}
