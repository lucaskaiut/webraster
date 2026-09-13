import { useNavigate, useSearchParams } from 'react-router'
import { Button, ButtonLink, Page, PageContent, PageHeader } from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import type { Client } from '@/shared/types/models'
import type { ClientPayload } from '../services/clients.service'
import { ClientForm } from '../forms/ClientForm'
import { ClientUsersSection } from '../components/ClientUsersSection'
import { ClientVehiclesSection } from '../components/ClientVehiclesSection'
import { ClientOrderSection } from '../components/ClientOrderSection'
import { ClientContractSection } from '../components/ClientContractSection'
import { ClientWizardStepper, type ClientWizardStep } from '../components/ClientWizardStepper'
import { useClientQuery, useCreateClient, useUpdateClient } from '../hooks/useClients'

function toFormValues(client: Client) {
  return {
    name: client.name,
    legal_name: client.legal_name ?? '',
    trade_name: client.trade_name ?? '',
    document: client.document,
    state_registration: client.state_registration ?? '',
    email: client.email ?? '',
    financial_email: client.financial_email ?? '',
    phone: client.phone ?? '',
    street: client.street ?? '',
    number: client.number ?? '',
    complement: client.complement ?? '',
    neighborhood: client.neighborhood ?? '',
    city: client.city ?? '',
    state: client.state ?? '',
    zip: client.zip ?? '',
    is_active: client.is_active,
  }
}

export default function ClientCreatePage() {
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const { can } = usePermissions()

  const rawStep = Number(searchParams.get('step') ?? 0)
  const step = (Number.isFinite(rawStep) ? Math.min(Math.max(rawStep, 0), 5) : 0) as ClientWizardStep
  const clientId = searchParams.get('id')

  const clientQuery = useClientQuery(clientId ?? undefined)
  const createClient = useCreateClient()
  const updateClient = useUpdateClient(clientId ?? '')

  const goTo = (nextStep: ClientWizardStep, id = clientId) => {
    setSearchParams(
      (params) => {
        params.set('step', String(nextStep))
        if (id) {
          params.set('id', id)
        }
        return params
      },
      { replace: true },
    )
  }

  const finish = () => {
    navigate('/clients')
  }

  const saveAndNext = async (payload: ClientPayload, next: ClientWizardStep) => {
    if (!clientId) {
      const client = await createClient.mutateAsync(payload)
      goTo(next, client.id)
      return
    }

    await updateClient.mutateAsync(payload)
    goTo(next)
  }

  return (
    <Page>
      <PageHeader
        title="Novo cliente"
        description="Cadastre o cliente em etapas: dados, endereço, usuários, veículos e pedido."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Clientes', to: '/clients' },
          { label: 'Novo cliente' },
        ]}
      />

      <PageContent className="space-y-6">
        <ClientWizardStepper current={step} />

        {step === 0 && (
          <ClientForm
            key={clientId ?? 'new'}
            mode="create"
            variant="basic"
            defaultValues={clientQuery.data ? toFormValues(clientQuery.data) : undefined}
            submitting={createClient.isPending || updateClient.isPending}
            submitLabel="Continuar"
            onSubmit={(payload) => saveAndNext(payload, 1)}
          />
        )}

        {step === 1 && clientId && !clientQuery.data && (
          <p className="text-sm text-muted">Carregando endereço...</p>
        )}

        {step === 1 && clientId && clientQuery.data && (
          <ClientForm
            key={`${clientId}-address`}
            mode="edit"
            variant="address"
            defaultValues={toFormValues(clientQuery.data)}
            submitting={updateClient.isPending}
            submitLabel="Continuar"
            onBack={() => goTo(0)}
            onSubmit={(payload) => saveAndNext(payload, 2)}
          />
        )}

        {step === 2 && clientId && (
          <div className="space-y-4">
            <ClientUsersSection clientId={clientId} />
            <WizardNav onBack={() => goTo(1)} onNext={() => goTo(3)} nextLabel="Continuar" />
          </div>
        )}

        {step === 3 && clientId && (
          <div className="space-y-4">
            <ClientVehiclesSection clientId={clientId} />
            <WizardNav onBack={() => goTo(2)} onNext={() => goTo(4)} nextLabel="Continuar" />
          </div>
        )}

        {step === 4 && clientId && (
          <div className="space-y-4">
            {can(Permission.FINANCE_SUBSCRIPTION_CREATE) ? (
              <ClientOrderSection clientId={clientId} onSaved={() => goTo(5)} />
            ) : (
              <p className="text-sm text-muted">
                Você não possui permissão para criar o pedido. Conclua o cadastro e peça a um
                administrador para montar os serviços.
              </p>
            )}
            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
              <Button type="button" variant="secondary" onClick={() => goTo(3)}>
                Voltar
              </Button>
              <Button type="button" variant="secondary" onClick={() => goTo(5)}>
                Concluir sem pedido
              </Button>
            </div>
          </div>
        )}

        {step === 5 && clientId && (
          <div className="space-y-4">
            <ClientContractSection clientId={clientId} />
            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
              <Button type="button" variant="secondary" onClick={() => goTo(4)}>
                Voltar
              </Button>
              <Button type="button" onClick={finish}>
                Concluir cadastro
              </Button>
            </div>
          </div>
        )}

        {step > 0 && !clientId && (
          <div className="space-y-3 text-sm text-muted">
            <p>Comece pelas informações básicas para continuar o cadastro.</p>
            <ButtonLink to="/clients/create" variant="secondary">
              Voltar ao início
            </ButtonLink>
          </div>
        )}
      </PageContent>
    </Page>
  )
}

function WizardNav({
  onBack,
  onNext,
  nextLabel,
}: {
  onBack: () => void
  onNext: () => void
  nextLabel: string
}) {
  return (
    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
      <Button type="button" variant="secondary" onClick={onBack}>
        Voltar
      </Button>
      <Button type="button" onClick={onNext}>
        {nextLabel}
      </Button>
    </div>
  )
}
