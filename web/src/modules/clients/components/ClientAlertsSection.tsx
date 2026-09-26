import { BellRing } from 'lucide-react'
import { Badge, Card, CardContent, CardHeader, EmptyState, Skeleton } from '@/shared/design-system'
import { useClientAlertConfigsQuery } from '@/modules/alerts/hooks/useAlerts'

/**
 * Visão do tenant: quais alertas o cliente silenciou no portal (somente leitura).
 */
export function ClientAlertsSection({ clientId }: { clientId?: string }) {
  const query = useClientAlertConfigsQuery(clientId)

  return (
    <Card>
      <CardHeader
        title="Alertas"
        description="Preferências do cliente no portal. Alertas silenciados não notificam o cliente; a configuração por veículo é feita no cadastro do veículo."
      />
      <CardContent className="space-y-4">
        {query.isPending && (
          <div className="space-y-4">
            {Array.from({ length: 4 }).map((_, index) => (
              <div key={index} className="flex items-center justify-between gap-4">
                <Skeleton className="h-4 w-40" />
                <Skeleton className="h-5 w-16 rounded-full" />
              </div>
            ))}
          </div>
        )}

        {query.isError && (
          <EmptyState
            icon={BellRing}
            title="Não foi possível carregar os alertas"
            description="Atualize a página e tente novamente."
          />
        )}

        {query.data?.map((option) => (
          <div key={option.type} className="flex items-center justify-between gap-4">
            <div className="min-w-0">
              <p className="text-sm font-semibold text-foreground">{option.label}</p>
              <p className="mt-0.5 text-[13px] text-muted">{option.description}</p>
            </div>
            <Badge variant={option.is_enabled ? 'success' : 'neutral'}>
              {option.is_enabled ? 'Recebendo' : 'Silenciado'}
            </Badge>
          </div>
        ))}
      </CardContent>
    </Card>
  )
}
