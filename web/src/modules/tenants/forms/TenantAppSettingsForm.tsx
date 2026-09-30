import { useState } from 'react'
import { Controller, useForm, useFormContext } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  Card,
  CardContent,
  ColorField,
  DEFAULT_PRIMARY_COLOR,
  DEFAULT_SECONDARY_COLOR,
  Form,
  ImageUploader,
  Section,
  TextField,
  type ImageValue,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { Tenant } from '@/shared/types/models'
import type { UpdateTenantPayload } from '../services/tenants.service'
import {
  tenantAppSettingsSchema,
  type TenantAppSettingsFormValues,
} from '../schemas/tenant.schema'

interface TenantAppSettingsFormProps {
  tenant: Tenant
  submitting: boolean
  onSubmit: (payload: UpdateTenantPayload) => Promise<unknown>
}

export function TenantAppSettingsForm({ tenant, submitting, onSubmit }: TenantAppSettingsFormProps) {
  const form = useForm<TenantAppSettingsFormValues>({
    resolver: zodResolver(tenantAppSettingsSchema),
    defaultValues: {
      app_name: tenant.app_name ?? '',
      app_icon_path: tenant.app_icon_path,
      app_logo_path: tenant.app_logo_path,
      app_primary_color: tenant.app_primary_color,
      app_secondary_color: tenant.app_secondary_color,
    },
  })

  const handleSubmit = async (values: TenantAppSettingsFormValues) => {
    const payload: UpdateTenantPayload = {
      app_name: values.app_name?.trim() || null,
      app_icon_path: values.app_icon_path,
      app_logo_path: values.app_logo_path,
      app_primary_color: values.app_primary_color,
      app_secondary_color: values.app_secondary_color,
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
          <Section
            title="Informações do aplicativo"
            description="Nome, ícone, logo e cores exibidos no aplicativo móvel da empresa."
          >
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="app_name"
                label="Nome do aplicativo"
                placeholder="Ex.: Rastreio Fácil"
                className="sm:col-span-2"
              />
              <ImageField
                name="app_icon_path"
                label="Ícone do aplicativo"
                hint="PNG quadrado (1024x1024). Usado como ícone do app."
                initialUrl={tenant.app_icon_url}
              />
              <ImageField
                name="app_logo_path"
                label="Logo do aplicativo"
                hint="PNG com fundo transparente. Exibida nas telas internas."
                initialUrl={tenant.app_logo_url}
              />
              <ColorField
                name="app_primary_color"
                label="Cor primária"
                hint="Botões, destaques e elementos principais."
                fallback={DEFAULT_PRIMARY_COLOR}
              />
              <ColorField
                name="app_secondary_color"
                label="Cor secundária"
                hint="Elementos de apoio e detalhes visuais."
                fallback={DEFAULT_SECONDARY_COLOR}
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
  name: 'app_icon_path' | 'app_logo_path'
  label: string
  hint?: string
  initialUrl?: string | null
  accept?: string
}) {
  const { control } = useFormContext<TenantAppSettingsFormValues>()
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
