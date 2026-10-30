import { useState } from 'react'
import { Controller, useForm, useFormContext, useWatch } from 'react-hook-form'
import {
  Button,
  Card,
  CardContent,
  Form,
  ImageUploader,
  RichTextEditor,
  Section,
  SwitchField,
  TextField,
  type ImageValue,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm, formResolver } from '@/shared/utils/forms'
import { onlyDigits } from '@/shared/utils/document'
import { maskCpfCnpj, maskPhone } from '@/shared/utils/mask'
import { useIsUmbrellaTenant } from '@/shared/hooks/useIsUmbrellaTenant'
import { useTenantModules } from '@/shared/hooks/useTenantModules'
import type { Tenant, TenantModuleKey } from '@/shared/types/models'
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
  const isUmbrella = useIsUmbrellaTenant()
  const { hasModule } = useTenantModules()
  /** O umbrella não opera: seções operacionais só para filhos com o módulo. */
  const showFor = (module: TenantModuleKey) => !isUmbrella && hasModule(module)

  const form = useForm<TenantSettingsFormValues>({
    resolver: formResolver<TenantSettingsFormValues>(tenantSettingsSchema),
    defaultValues: {
      name: tenant.name,
      identifier: tenant.identifier,
      document: maskCpfCnpj(tenant.document),
      email: tenant.email,
      phone: maskPhone(tenant.phone ?? ''),
      logo_path: tenant.logo_path,
      favicon_path: tenant.favicon_path,
      signature_path: tenant.signature_path,
      late_fine_percent: tenant.late_fine_percent != null ? Number(tenant.late_fine_percent) : 0,
      late_interest_percent:
        tenant.late_interest_percent != null ? Number(tenant.late_interest_percent) : 0,
      require_equipment_loan_acceptance: tenant.require_equipment_loan_acceptance ?? false,
      equipment_loan_term: tenant.equipment_loan_term ?? '',
    },
  })

  const requiresLoanTerm = useWatch({
    control: form.control,
    name: 'require_equipment_loan_acceptance',
  })

  const handleSubmit = async (values: TenantSettingsFormValues) => {
    const term = values.equipment_loan_term?.trim() ?? ''
    const termText = term
      .replace(/<[^>]*>/g, '')
      .replace(/&(?:nbsp|#160);/gi, ' ')
      .trim()

    const payload: UpdateTenantPayload = {
      name: values.name,
      identifier: values.identifier.trim().toLowerCase(),
      document: onlyDigits(values.document),
      email: values.email,
      phone: onlyDigits(values.phone),
      logo_path: values.logo_path,
      favicon_path: values.favicon_path,
      signature_path: values.signature_path,
      late_fine_percent: values.late_fine_percent,
      late_interest_percent: values.late_interest_percent,
      require_equipment_loan_acceptance: values.require_equipment_loan_acceptance,
      equipment_loan_term: termText ? term : null,
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
              {!isUmbrella && (
                <TextField
                  name="identifier"
                  label="Identificador do aplicativo"
                  required
                  className="sm:col-span-2"
                  hint="Código usado na tela inicial do app para localizar a empresa. Apenas letras, números, hífen e underline."
                />
              )}
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

          {showFor('contracts') && (
            <Section
              title="Assinatura do responsável"
              description="Assinatura aplicada automaticamente nos contratos que usam a variável {{ASSINATURA_EMPRESA}}."
            >
              <SignatureField initialUrl={tenant.signature_url} />
            </Section>
          )}

          {showFor('service_orders') && (
            <Section
              title="Assinatura do cliente"
              description="A assinatura do cliente é obrigatória na conclusão da OS. Ative o termo de comodato para incluir o aceite do termo no momento da assinatura."
            >
              <div className="space-y-4">
                <SwitchField
                  name="require_equipment_loan_acceptance"
                  label="Incluir aceite do termo de comodato na assinatura"
                  hint="Quando habilitado, o cliente verá e aceitará o termo de comodato ao assinar a OS no aplicativo."
                />
                {requiresLoanTerm && (
                  <Controller
                    control={form.control}
                    name="equipment_loan_term"
                    render={({ field, fieldState }) => (
                      <RichTextEditor
                        label="Termo de comodato"
                        value={field.value ?? ''}
                        onChange={field.onChange}
                        error={fieldState.error?.message}
                        placeholder="Digite o texto completo do termo de comodato que será exibido ao cliente..."
                        hint="Aceita formatação HTML (negrito, títulos, listas, links etc.). O texto será exibido no aplicativo e ficará registrado na OS no momento do aceite."
                      />
                    )}
                  />
                )}
              </div>
            </Section>

          )}

          {showFor('finance') && (
            <Section
              title="Multa e juros por atraso"
              description="Aplicados automaticamente em cobranças vencidas quando o cliente efetua o pagamento."
            >
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                  name="late_fine_percent"
                  label="Multa (% do valor)"
                  type="number"
                  min={0}
                  max={100}
                  step="0.01"
                  hint="Aplicada uma única vez sobre o valor da cobrança em atraso."
                />
                <TextField
                  name="late_interest_percent"
                  label="Juros (% ao mês)"
                  type="number"
                  min={0}
                  max={100}
                  step="0.01"
                  hint="Calculados por dia de atraso, proporcionais ao mês (base 30 dias)."
                />
              </div>
            </Section>
          )}

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
