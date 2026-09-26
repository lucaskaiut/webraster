import { BellRing } from 'lucide-react'
import {
  Card,
  CardContent,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
  Switch,
} from '@/shared/design-system'
import { toast } from '@/shared/stores/toast.store'
import { usePortalAlertConfigsQuery, useUpdatePortalAlertConfig } from '../hooks/useAlerts'

export default function AlertPreferencesPage() {
  const query = usePortalAlertConfigsQuery()
  const update = useUpdatePortalAlertConfig()

  const handleToggle = (type: string, isEnabled: boolean) => {
    update.mutate(
      { type, isEnabled },
      { onError: () => toast.error('Não foi possível atualizar o alerta') },
    )
  }

  return (
    <Page>
      <PageHeader
        title="Meus alertas"
        description="Silencie alertas que você não quer receber. Quais alertas cada veículo dispara é definido pelo operador no cadastro do veículo."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Meus alertas' }]}
      />

      <PageContent>
        {query.isPending && (
          <Card>
            <CardContent className="space-y-5">
              {Array.from({ length: 5 }).map((_, index) => (
                <div key={index} className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1 space-y-2">
                    <Skeleton className="h-4 w-40" />
                    <Skeleton className="h-3 w-64" />
                  </div>
                  <Skeleton className="h-6 w-10.5 rounded-full" />
                </div>
              ))}
            </CardContent>
          </Card>
        )}

        {query.isError && (
          <Card>
            <EmptyState
              icon={BellRing}
              title="Não foi possível carregar seus alertas"
              description="Atualize a página e tente novamente."
            />
          </Card>
        )}

        {query.data && (
          <Card>
            <CardContent className="space-y-5">
              {query.data.map((option) => (
                <div key={option.type} className="flex items-center justify-between gap-4">
                  <div className="min-w-0">
                    <p className="text-sm font-semibold text-foreground">{option.label}</p>
                    <p className="mt-0.5 text-[13px] text-muted">{option.description}</p>
                  </div>
                  <Switch
                    checked={option.is_enabled}
                    disabled={update.isPending && update.variables?.type === option.type}
                    onCheckedChange={(checked) => handleToggle(option.type, checked)}
                    label={option.label}
                  />
                </div>
              ))}
            </CardContent>
          </Card>
        )}
      </PageContent>
    </Page>
  )
}
