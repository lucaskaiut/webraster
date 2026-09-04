import { useNavigate, useParams } from 'react-router'
import { Building2 } from 'lucide-react'
import {
  ButtonLink,
  Card,
  CardContent,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { ChildTenantForm } from '../forms/ChildTenantForm'
import { useTenantChildQuery, useUpdateChildTenant } from '../hooks/useTenants'

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
        <div className="flex justify-end gap-2">
          <Skeleton className="h-10 w-24" />
          <Skeleton className="h-10 w-36" />
        </div>
      </CardContent>
    </Card>
  )
}

export default function TenantEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useTenantChildQuery(id)
  const updateChild = useUpdateChildTenant(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar empresa"
        description={query.data ? `Atualize os dados de ${query.data.name}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Empresas', to: '/tenants' },
          { label: 'Editar' },
        ]}
      />

      <PageContent>
        {query.isPending && <FormSkeleton />}

        {query.isError && (
          <Card>
            <EmptyState
              icon={Building2}
              title="Empresa não encontrada"
              description="A empresa pode ter sido removida ou você não possui acesso a ela."
              action={
                <ButtonLink to="/tenants" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && (
          <ChildTenantForm
            mode="edit"
            defaultValues={{
              tenant: {
                name: query.data.name,
                document: query.data.document,
                email: query.data.email,
                phone: query.data.phone ?? '',
                domain: query.data.domain,
              },
            }}
            submitting={updateChild.isPending}
            onSubmit={async (payload) => {
              await updateChild.mutateAsync(payload)
              navigate('/tenants')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
