import { useState, type ReactNode } from 'react'
import { useParams } from 'react-router'
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  CardHeader,
  ConfirmDialog,
  Loading,
  Page,
  PageContent,
  PageHeader,
} from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { FinanceContractStatus } from '@/shared/types/models'
import {
  useChangeFinanceContractStatus,
  useFinanceContractQuery,
  useGenerateFinanceReceivable,
} from '../hooks/useFinance'
import {
  contractStatusBadgeVariant,
  contractStatusLabel,
  periodicityLabel,
} from '../lib/labels'

function Row({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex justify-between gap-4 border-b border-border/60 py-2 last:border-0">
      <span className="text-muted">{label}</span>
      <span className="text-right text-foreground">{value}</span>
    </div>
  )
}

export default function FinanceContractDetailPage() {
  const { id } = useParams()
  const { can } = usePermissions()
  const query = useFinanceContractQuery(id)
  const changeStatus = useChangeFinanceContractStatus()
  const generate = useGenerateFinanceReceivable()
  const [confirmStatus, setConfirmStatus] = useState<FinanceContractStatus | null>(null)

  if (query.isLoading) {
    return (
      <Page>
        <PageContent>
          <Loading />
        </PageContent>
      </Page>
    )
  }

  const contract = query.data
  if (!contract) {
    return (
      <Page>
        <PageHeader
          title="Contrato não encontrado"
          breadcrumb={[{ label: 'Contratos', to: '/finance/contracts' }]}
        />
      </Page>
    )
  }

  const canUpdate = can(Permission.FINANCE_CONTRACT_UPDATE)
  const canGenerate = can(Permission.FINANCE_RECEIVABLE_CREATE)

  const applyStatus = (status: FinanceContractStatus) => {
    changeStatus.mutate(
      { id: contract.id, status },
      {
        onSuccess: () => {
          setConfirmStatus(null)
          void query.refetch()
        },
      },
    )
  }

  return (
    <Page>
      <PageHeader
        title={contract.code}
        description={contract.client?.name ?? 'Contrato financeiro'}
        breadcrumb={[
          { label: 'Contratos', to: '/finance/contracts' },
          { label: contract.code },
        ]}
        actions={
          <div className="flex flex-wrap gap-2">
            {canGenerate && contract.status === 'active' && (
              <Button
                loading={generate.isPending}
                onClick={() =>
                  generate.mutate(
                    { contract_id: contract.id },
                    { onSuccess: () => void query.refetch() },
                  )
                }
              >
                Gerar cobrança
              </Button>
            )}
            {canUpdate && contract.status === 'active' && (
              <Button variant="secondary" onClick={() => setConfirmStatus('suspended')}>
                Suspender
              </Button>
            )}
            {canUpdate && contract.status === 'suspended' && (
              <Button onClick={() => setConfirmStatus('active')}>Reativar</Button>
            )}
            {canUpdate && contract.status !== 'cancelled' && (
              <Button variant="secondary" onClick={() => setConfirmStatus('cancelled')}>
                Cancelar
              </Button>
            )}
          </div>
        }
      />
      <PageContent className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader title="Geral" />
          <CardContent className="space-y-1 text-sm">
            <Row
              label="Status"
              value={
                <Badge variant={contractStatusBadgeVariant(contract.status)}>
                  {contract.status_label ?? contractStatusLabel(contract.status)}
                </Badge>
              }
            />
            <Row label="Cliente" value={contract.client?.name ?? '—'} />
            <Row label="Plano" value={contract.plan?.name ?? '—'} />
            <Row
              label="Periodicidade"
              value={contract.periodicity_label ?? periodicityLabel(contract.periodicity)}
            />
            <Row label="Início" value={formatDate(contract.starts_at)} />
            <Row label="Término" value={formatDate(contract.ends_at)} />
            <Row label="Dia vencimento" value={contract.due_day} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader title="Valores e bloqueio" />
          <CardContent className="space-y-1 text-sm">
            <Row label="Valor" value={formatCurrency(contract.amount)} />
            <Row label="Desconto" value={formatCurrency(contract.discount_cents / 100)} />
            <Row label="Líquido" value={formatCurrency(contract.net_amount_cents / 100)} />
            <Row label="Multa" value={`${contract.fine_percent}%`} />
            <Row label="Juros" value={`${contract.interest_percent}%`} />
            <Row label="Dispositivos" value={contract.device_quantity} />
            <Row label="Renovação auto" value={contract.auto_renew ? 'Sim' : 'Não'} />
            <Row label="Bloquear atraso" value={contract.block_on_overdue ? 'Sim' : 'Não'} />
            <Row label="Dias p/ bloquear" value={contract.block_after_days} />
          </CardContent>
        </Card>

        {contract.subscription && (
          <Card className="lg:col-span-2">
            <CardHeader
              title="Assinatura"
              actions={
                <ButtonLink to="/finance/subscriptions" variant="secondary">
                  Ver assinaturas
                </ButtonLink>
              }
            />
            <CardContent className="grid gap-2 text-sm sm:grid-cols-3">
              <Row
                label="Status"
                value={contract.subscription.status_label ?? contract.subscription.status}
              />
              <Row
                label="Próxima cobrança"
                value={formatDate(contract.subscription.next_billing_at)}
              />
              <Row
                label="Última cobrança"
                value={formatDate(contract.subscription.last_billing_at)}
              />
            </CardContent>
          </Card>
        )}

        {contract.notes && (
          <Card className="lg:col-span-2">
            <CardHeader title="Observações" />
            <CardContent>
              <p className="whitespace-pre-wrap text-sm text-foreground">{contract.notes}</p>
            </CardContent>
          </Card>
        )}
      </PageContent>

      <ConfirmDialog
        open={confirmStatus !== null}
        onClose={() => setConfirmStatus(null)}
        onConfirm={() => {
          if (!confirmStatus) return
          applyStatus(confirmStatus)
        }}
        loading={changeStatus.isPending}
        title="Alterar status"
        description={
          <>
            Alterar o contrato <strong>{contract.code}</strong> para{' '}
            <strong>{confirmStatus ? contractStatusLabel(confirmStatus) : ''}</strong>?
          </>
        }
        confirmLabel="Confirmar"
      />
    </Page>
  )
}
