import { Building2 } from 'lucide-react'
import {
  Button,
  Card,
  CardContent,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { TenantSettingsForm } from '../forms/TenantSettingsForm'
import { useTenantQuery, useUpdateTenant } from '../hooks/useTenants'

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
  const query = useTenantQuery()
  const updateTenant = useUpdateTenant()

  return (
    <Page>
      <PageHeader
        title="Configurações"
        description="Atualize os dados e a identidade visual da empresa ativa."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Configurações' }]}
      />

      <PageContent>
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
          <TenantSettingsForm
            tenant={query.data}
            submitting={updateTenant.isPending}
            onSubmit={(payload) => updateTenant.mutateAsync(payload)}
          />
        )}
      </PageContent>
    </Page>
  )
}
