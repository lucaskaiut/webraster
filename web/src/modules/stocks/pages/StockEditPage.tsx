import { useNavigate, useParams } from 'react-router'
import { Warehouse } from 'lucide-react'
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
import { StockForm } from '../forms/StockForm'
import { useStockQuery, useUpdateStock } from '../hooks/useStocks'

function FormSkeleton() {
  return (
    <Card>
      <CardContent className="space-y-5">
        <Skeleton className="h-4 w-40" />
        <div className="grid gap-4 sm:grid-cols-2">
          <Skeleton className="h-10 sm:col-span-2" />
          <Skeleton className="h-24 sm:col-span-2" />
          <Skeleton className="h-10 sm:col-span-2" />
        </div>
        <div className="flex justify-end gap-2">
          <Skeleton className="h-10 w-24" />
          <Skeleton className="h-10 w-36" />
        </div>
      </CardContent>
    </Card>
  )
}

export default function StockEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const query = useStockQuery(id)
  const updateStock = useUpdateStock(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar estoque"
        description={query.data ? `Atualize os dados de ${query.data.name}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Estoques', to: '/stocks' },
          { label: 'Editar' },
        ]}
      />

      <PageContent>
        {query.isPending && <FormSkeleton />}

        {query.isError && (
          <Card>
            <EmptyState
              icon={Warehouse}
              title="Estoque não encontrado"
              description="O estoque pode ter sido removido ou você não possui acesso a ele."
              action={
                <ButtonLink to="/stocks" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && (
          <StockForm
            mode="edit"
            defaultValues={{
              name: query.data.name,
              description: query.data.description ?? '',
              is_active: query.data.is_active,
            }}
            submitting={updateStock.isPending}
            onSubmit={async (payload) => {
              await updateStock.mutateAsync(payload)
              navigate('/stocks')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
