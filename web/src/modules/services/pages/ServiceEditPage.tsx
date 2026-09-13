import { useNavigate, useParams } from 'react-router'
import { Wrench } from 'lucide-react'
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
import { centsToReais } from '@/modules/finance/lib/labels'
import { ServiceForm } from '../forms/ServiceForm'
import { useServiceQuery, useUpdateService } from '../hooks/useServices'

function FormSkeleton() {
  return (
    <Card>
      <CardContent className="space-y-5">
        <Skeleton className="h-4 w-40" />
        <div className="grid gap-4 sm:grid-cols-2">
          <Skeleton className="h-10 sm:col-span-2" />
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

export default function ServiceEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useServiceQuery(id)
  const updateService = useUpdateService(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar serviço"
        description={query.data ? `Atualize os dados de ${query.data.name}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Serviços', to: '/services' },
          { label: 'Editar' },
        ]}
      />

      <PageContent>
        {query.isPending && <FormSkeleton />}

        {query.isError && (
          <Card>
            <EmptyState
              icon={Wrench}
              title="Serviço não encontrado"
              description="O serviço pode ter sido removido ou você não possui acesso a ele."
              action={
                <ButtonLink to="/services" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && (
          <ServiceForm
            mode="edit"
            defaultValues={{
              name: query.data.name,
              amount: centsToReais(query.data.amount_cents),
            }}
            submitting={updateService.isPending}
            onSubmit={async (payload) => {
              await updateService.mutateAsync(payload)
              navigate('/services')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
