import { useSearchParams } from 'react-router'
import { Building2 } from 'lucide-react'
import {
  Button,
  Card,
  CardContent,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  SegmentedControl,
  Skeleton,
} from '@/shared/design-system'
import { TenantAlertSettingsForm } from '../forms/TenantAlertSettingsForm'
import { TenantAppSettingsForm } from '../forms/TenantAppSettingsForm'
import { TenantSettingsForm } from '../forms/TenantSettingsForm'
import { useTenantQuery, useUpdateTenant } from '../hooks/useTenants'

type TenantSettingsTab = 'empresa' | 'aplicativo' | 'alertas'

const TAB_OPTIONS: Array<{ value: TenantSettingsTab; label: string }> = [
  { value: 'empresa', label: 'Empresa' },
  { value: 'aplicativo', label: 'Aplicativo' },
  { value: 'alertas', label: 'Alertas' },
]

function FormSkeleton() {
  return (
    <Card>
      <CardContent className="space-y-5">
        <Skeleton className="h-4 w-40" />
        <div className="grid gap-4 sm:grid-cols-2">
          <Skeleton className="h-10 sm:col-span-2" />
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
        </div>
        <Skeleton className="h-40" />
      </CardContent>
    </Card>
  )
}

export default function TenantSettingsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const query = useTenantQuery()
  const updateTenant = useUpdateTenant()

  const rawTab = searchParams.get('tab')
  const tab: TenantSettingsTab =
    rawTab === 'aplicativo' ? 'aplicativo' : rawTab === 'alertas' ? 'alertas' : 'empresa'

  const setTab = (value: TenantSettingsTab) => {
    setSearchParams(
      (params) => {
        if (value === 'empresa') {
          params.delete('tab')
        } else {
          params.set('tab', value)
        }
        return params
      },
      { replace: true },
    )
  }

  return (
    <Page>
      <PageHeader
        title="Configurações"
        description="Atualize os dados, a identidade visual, o aplicativo e os alertas padrão da empresa ativa."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Configurações' }]}
      />

      <PageContent className="space-y-5">
        <SegmentedControl value={tab} options={TAB_OPTIONS} onChange={setTab} />

        {query.isPending && <FormSkeleton />}

        {query.isError && (
          <Card>
            <EmptyState
              icon={Building2}
              title="Não foi possível carregar a empresa"
              description="Tente novamente ou verifique suas permissões de acesso."
              action={
                <Button variant="secondary" onClick={() => void query.refetch()}>
                  Tentar novamente
                </Button>
              }
            />
          </Card>
        )}

        {query.data && (
          <>
            {tab === 'empresa' && (
              <TenantSettingsForm
                tenant={query.data}
                submitting={updateTenant.isPending}
                onSubmit={(payload) => updateTenant.mutateAsync(payload)}
              />
            )}

            {tab === 'aplicativo' && (
              <TenantAppSettingsForm
                tenant={query.data}
                submitting={updateTenant.isPending}
                onSubmit={(payload) => updateTenant.mutateAsync(payload)}
              />
            )}

            {tab === 'alertas' && (
              <TenantAlertSettingsForm
                tenant={query.data}
                submitting={updateTenant.isPending}
                onSubmit={(payload) => updateTenant.mutateAsync(payload)}
              />
            )}
          </>
        )}
      </PageContent>
    </Page>
  )
}
