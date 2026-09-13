import { useNavigate, useParams } from 'react-router'
import { FileText } from 'lucide-react'
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
import { ContractForm } from '../forms/ContractForm'
import { useContractQuery, useUpdateContract } from '../hooks/useContracts'

function FormSkeleton() {
  return (
    <Card>
      <CardContent className="space-y-5">
        <Skeleton className="h-4 w-40" />
        <Skeleton className="h-10" />
        <Skeleton className="h-48" />
        <div className="flex justify-end gap-2">
          <Skeleton className="h-10 w-24" />
          <Skeleton className="h-10 w-36" />
        </div>
      </CardContent>
    </Card>
  )
}

export default function ContractEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useContractQuery(id)
  const updateContract = useUpdateContract(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar contrato"
        description={query.data ? `Atualize o modelo ${query.data.name}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Contratos', to: '/contracts' },
          { label: 'Editar' },
        ]}
      />

      <PageContent>
        {query.isPending && <FormSkeleton />}

        {query.isError && (
          <Card>
            <EmptyState
              icon={FileText}
              title="Contrato não encontrado"
              description="O contrato pode ter sido removido ou você não possui acesso a ele."
              action={
                <ButtonLink to="/contracts" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && (
          <ContractForm
            mode="edit"
            defaultValues={{
              name: query.data.name,
              body: query.data.body,
            }}
            submitting={updateContract.isPending}
            onSubmit={async (payload) => {
              await updateContract.mutateAsync(payload)
              navigate('/contracts')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
