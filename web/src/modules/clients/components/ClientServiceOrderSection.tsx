import { useState } from 'react'
import { useNavigate } from 'react-router'
import { Card, CardContent, Checkbox, DatePicker, Field } from '@/shared/design-system'
import { toLocalIsoDate } from '@/shared/utils/format'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { toast } from '@/shared/stores/toast.store'
import { ServiceOrderForm } from '@/modules/service-orders/components/ServiceOrderForm'
import {
  useCreateServiceOrder,
  useGenerateServiceOrderBilling,
} from '@/modules/service-orders/hooks/useServiceOrders'
import { useVehiclesQuery } from '@/modules/vehicles/hooks/useVehicles'
import type { ServiceOrder } from '@/shared/types/models'

interface ClientServiceOrderSectionProps {
  clientId: string
  onBack?: () => void
  onCreated?: (order: ServiceOrder) => Promise<void> | void
}

export function ClientServiceOrderSection({ clientId, onBack, onCreated }: ClientServiceOrderSectionProps) {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const canGenerateBilling = can(Permission.FINANCE_BILLING_CREATE)
  const firstVehicleQuery = useVehiclesQuery({ client_id: clientId, sort: 'created_at:asc', per_page: 1 })
  const create = useCreateServiceOrder()
  const generateBilling = useGenerateServiceOrderBilling()
  const [withBilling, setWithBilling] = useState(false)
  const [billingDueDate, setBillingDueDate] = useState(toLocalIsoDate())

  if (firstVehicleQuery.isPending) {
    return (
      <Card>
        <CardContent>
          <p className="text-sm text-muted">Carregando...</p>
        </CardContent>
      </Card>
    )
  }

  const firstVehicleId = firstVehicleQuery.data?.data[0]?.id

  return (
    <ServiceOrderForm
      mode="create"
      lockClient
      defaults={{
        client_id: clientId,
        vehicle_id: firstVehicleId,
        description: 'Instalação',
      }}
      submitting={create.isPending || generateBilling.isPending}
      submitLabel="Criar OS e concluir"
      cancelLabel="Voltar"
      onCancel={onBack}
      footer={
        canGenerateBilling ? (
          <div className="space-y-3">
            <Checkbox
              label="Gerar cobrança"
              description="Ao concluir, gera a cobrança dos serviços desta OS para o cliente."
              checked={withBilling}
              onChange={(event) => setWithBilling(event.target.checked)}
            />
            {withBilling && (
              <Field label="Vencimento" required className="sm:max-w-xs">
                <DatePicker
                  value={billingDueDate}
                  onChange={(event) => setBillingDueDate(event.target.value)}
                  aria-label="Vencimento da cobrança"
                />
              </Field>
            )}
          </div>
        ) : undefined
      }
      onSubmit={async (payload) => {
        const billing = withBilling && canGenerateBilling

        if (billing && (payload.items?.length ?? 0) === 0) {
          toast.error('Adicione ao menos um serviço para gerar a cobrança.')
          return
        }

        if (billing && !billingDueDate) {
          toast.error('Informe o vencimento da cobrança.')
          return
        }

        const order = await create.mutateAsync(payload)

        if (billing) {
          try {
            await generateBilling.mutateAsync({ id: order.id, payload: { due_at: billingDueDate } })
          } catch {
            // A OS já existe: abre o detalhe para o usuário tentar gerar a cobrança novamente.
            navigate(`/service-orders/${order.id}`)
            return
          }
        }

        await onCreated?.(order)
      }}
    />
  )
}
