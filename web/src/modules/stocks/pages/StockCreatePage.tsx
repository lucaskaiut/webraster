import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { StockForm } from '../forms/StockForm'
import { useCreateStock } from '../hooks/useStocks'

export default function StockCreatePage() {
  const navigate = useNavigate()
  const createStock = useCreateStock()

  return (
    <Page>
      <PageHeader
        title="Novo estoque"
        description="Cadastre um local/estoque para organizar os equipamentos e os técnicos."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Estoques', to: '/stocks' },
          { label: 'Novo estoque' },
        ]}
      />

      <PageContent>
        <StockForm
          mode="create"
          submitting={createStock.isPending}
          onSubmit={async (payload) => {
            await createStock.mutateAsync(payload)
            navigate('/stocks')
          }}
        />
      </PageContent>
    </Page>
  )
}
