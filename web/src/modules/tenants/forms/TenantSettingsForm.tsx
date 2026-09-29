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
import { AlertConfigsMatrix } from '@/modules/alerts/components/AlertConfigsMatrix'
import { vehicleAlertConfigsFromApi } from '@/modules/alerts/lib/alert-types'
import { SignatureField } from '../components/SignatureField'
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
      signature_path: tenant.signature_path,
      vehicle_alert_defaults: vehicleAlertConfigsFromApi(tenant.vehicle_alert_defaults),
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
      signature_path: values.signature_path,
      vehicle_alert_defaults: values.vehicle_alert_defaults.map(
        ({
          type,
          alarm_code,
          is_enabled,
          notify_in_app,
          notify_monitoring,
          notify_push,
          notify_email,
        }) => ({
          type,
          alarm_code,
          is_enabled,
          notify_in_app,
          notify_monitoring,
          notify_push,
          notify_email,
        }),
      ),
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

          <Section
            title="Assinatura do responsável"
            description="Assinatura aplicada automaticamente nos contratos que usam a variável {{ASSINATURA_EMPRESA}}."
          >
            <SignatureField initialUrl={tenant.signature_url} />
          </Section>

          <Section
            title="Alertas padrão"
            description="Define o estado inicial dos alertas — inclusive os canais — ao cadastrar um veículo. Depois do cadastro, cada veículo tem a própria configuração, que é a única usada no envio dos alertas."
          >
            <AlertConfigsMatrix
              name="vehicle_alert_defaults"
              deviceHint="Alarmes enviados pelo rastreador. Alarmes novos, que ainda não estiverem nesta lista, seguem o padrão do sistema."
            />
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
