import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Card, CardContent, Form, Section } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { Tenant } from '@/shared/types/models'
import { AlertConfigsMatrix } from '@/modules/alerts/components/AlertConfigsMatrix'
import { vehicleAlertConfigsFromApi } from '@/modules/alerts/lib/alert-types'
import type { UpdateTenantPayload } from '../services/tenants.service'
import {
  tenantAlertSettingsSchema,
  type TenantAlertSettingsFormValues,
} from '../schemas/tenant.schema'

interface TenantAlertSettingsFormProps {
  tenant: Tenant
  submitting: boolean
  onSubmit: (payload: UpdateTenantPayload) => Promise<unknown>
}

export function TenantAlertSettingsForm({
  tenant,
  submitting,
  onSubmit,
}: TenantAlertSettingsFormProps) {
  const form = useForm<TenantAlertSettingsFormValues>({
    resolver: zodResolver(tenantAlertSettingsSchema),
    defaultValues: {
      vehicle_alert_defaults: vehicleAlertConfigsFromApi(tenant.vehicle_alert_defaults),
    },
  })

  const handleSubmit = async (values: TenantAlertSettingsFormValues) => {
    const payload: UpdateTenantPayload = {
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
