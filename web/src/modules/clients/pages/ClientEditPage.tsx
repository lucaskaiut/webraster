import { useMemo } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router'
import { UserX } from 'lucide-react'
import {
  ButtonLink,
  Card,
  CardContent,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  SegmentedControl,
  Skeleton,
} from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { ClientForm } from '../forms/ClientForm'
import { ClientAlertsSection } from '../components/ClientAlertsSection'
import { ClientContractSection } from '../components/ClientContractSection'
import { ClientFinanceSection } from '../components/ClientFinanceSection'
import { ClientOrderSection } from '../components/ClientOrderSection'
import { ClientUsersSection } from '../components/ClientUsersSection'
import { ClientVehiclesSection } from '../components/ClientVehiclesSection'
import { useClientQuery, useUpdateClient } from '../hooks/useClients'

type ClientEditTab = 'dados' | 'usuarios' | 'veiculos' | 'alertas' | 'pedido' | 'contrato'

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

export default function ClientEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const { can } = usePermissions()

  const query = useClientQuery(id)
  const updateClient = useUpdateClient(id ?? '')
  const showOrderTab = can(Permission.CLIENT_READ)
  const showFinance = can(Permission.FINANCE_SUBSCRIPTION_READ)
  const showAlertsTab = can(Permission.ALERT_CONFIG_READ)

  const tabOptions = useMemo(
    () => [
      { value: 'dados' as const, label: 'Dados' },
      { value: 'usuarios' as const, label: 'Usuários' },
      { value: 'veiculos' as const, label: 'Veículos' },
      ...(showAlertsTab ? [{ value: 'alertas' as const, label: 'Alertas' }] : []),
      ...(showOrderTab ? [{ value: 'pedido' as const, label: 'Pedido' }] : []),
      ...(showOrderTab ? [{ value: 'contrato' as const, label: 'Contrato' }] : []),
    ],
    [showAlertsTab, showOrderTab],
  )

  const rawTab = searchParams.get('tab')
  const tab: ClientEditTab =
    rawTab === 'pedido' && showOrderTab
      ? 'pedido'
      : rawTab === 'contrato' && showOrderTab
        ? 'contrato'
        : rawTab === 'usuarios'
          ? 'usuarios'
          : rawTab === 'veiculos'
            ? 'veiculos'
            : rawTab === 'alertas' && showAlertsTab
              ? 'alertas'
              : rawTab === 'assinatura' && showOrderTab
                ? 'pedido'
                : 'dados'

  const setTab = (value: ClientEditTab) => {
    setSearchParams(
      (params) => {
        if (value === 'dados') {
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
        title="Editar cliente"
        description={query.data ? `Atualize os dados de ${query.data.name}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Clientes', to: '/clients' },
          { label: 'Editar' },
        ]}
      />

      <PageContent className="space-y-6">
        {query.isPending && <FormSkeleton />}

        {query.isError && (
          <Card>
            <EmptyState
              icon={UserX}
              title="Cliente não encontrado"
              description="O cliente pode ter sido removido ou você não possui acesso a ele."
              action={
                <ButtonLink to="/clients" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && id && (
          <>
            <SegmentedControl value={tab} options={tabOptions} onChange={setTab} />

            {tab === 'dados' && (
              <ClientForm
                mode="edit"
                defaultValues={{
                  name: query.data.name,
                  legal_name: query.data.legal_name ?? '',
                  trade_name: query.data.trade_name ?? '',
                  document: query.data.document,
                  state_registration: query.data.state_registration ?? '',
                  email: query.data.email ?? '',
                  financial_email: query.data.financial_email ?? '',
                  phone: query.data.phone ?? '',
                  street: query.data.street ?? '',
                  number: query.data.number ?? '',
                  complement: query.data.complement ?? '',
                  neighborhood: query.data.neighborhood ?? '',
                  city: query.data.city ?? '',
                  state: query.data.state ?? '',
                  zip: query.data.zip ?? '',
                  is_active: query.data.is_active,
                }}
                submitting={updateClient.isPending}
                onSubmit={async (payload) => {
                  await updateClient.mutateAsync(payload)
                  navigate('/clients')
                }}
              />
            )}

            {tab === 'usuarios' && <ClientUsersSection clientId={id} />}

            {tab === 'veiculos' && <ClientVehiclesSection clientId={id} />}

            {tab === 'alertas' && <ClientAlertsSection clientId={id} />}

            {tab === 'contrato' && <ClientContractSection clientId={id} />}

            {tab === 'pedido' && (
              <div className="space-y-6">
                <ClientOrderSection clientId={id} />
                {showFinance && <ClientFinanceSection clientId={id} />}
              </div>
            )}
          </>
        )}
      </PageContent>
    </Page>
  )
}
