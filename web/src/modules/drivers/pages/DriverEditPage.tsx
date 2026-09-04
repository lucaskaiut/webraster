import { useNavigate, useParams } from 'react-router'
import { UserX } from 'lucide-react'
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
import { DriverForm } from '../forms/DriverForm'
import { useDriverQuery, useUpdateDriver } from '../hooks/useDrivers'

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

export default function DriverEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useDriverQuery(id)
  const updateDriver = useUpdateDriver(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar motorista"
        description={query.data ? `Atualize os dados de ${query.data.name}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Motoristas', to: '/drivers' },
          { label: 'Editar' },
        ]}
      />

      <PageContent>
        {query.isPending && <FormSkeleton />}

        {query.isError && (
          <Card>
            <EmptyState
              icon={UserX}
              title="Motorista não encontrado"
              description="O motorista pode ter sido removido ou você não possui acesso a ele."
              action={
                <ButtonLink to="/drivers" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && (
          <DriverForm
            mode="edit"
            defaultValues={{
              client_id: query.data.client_id,
              name: query.data.name,
              document: query.data.document ?? '',
              phone: query.data.phone ?? '',
              email: query.data.email ?? '',
              cnh_number: query.data.cnh_number ?? '',
              cnh_expires_at: query.data.cnh_expires_at ?? '',
              notes: query.data.notes ?? '',
              is_active: query.data.is_active,
            }}
            submitting={updateDriver.isPending}
            onSubmit={async (payload) => {
              await updateDriver.mutateAsync(payload)
              navigate('/drivers')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
