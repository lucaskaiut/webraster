import { useState } from 'react'
import { CreditCard, Pencil, Plus, Power } from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  ConfirmDialog,
  DataTable,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { Plan } from '@/shared/types/models'
import { useDeactivatePlan, usePlansQuery } from '../hooks/useBilling'

const unitLabel: Record<Plan['recurrence_unit'], string> = {
  days: 'dias',
  weeks: 'semanas',
  months: 'meses',
  years: 'anos',
}

export default function PlansListPage() {
  const query = usePlansQuery()
  const deactivate = useDeactivatePlan()
  const { can } = usePermissions()
  const [planToDeactivate, setPlanToDeactivate] = useState<Plan | null>(null)

  const columns: Array<Column<Plan>> = [
    {
      key: 'name',
      header: 'Plano',
      render: (plan) => (
        <div>
          <span className="font-medium text-foreground">{plan.name}</span>
          {plan.description && (
            <span className="mt-0.5 block max-w-72 truncate text-xs text-muted">
              {plan.description}
            </span>
          )}
        </div>
      ),
    },
    {
      key: 'price',
      header: 'Preço',
      render: (plan) => formatCurrency(plan.price),
    },
    {
      key: 'recurrence',
      header: 'Recorrência',
      render: (plan) => `${plan.recurrence_value} ${unitLabel[plan.recurrence_unit]}`,
    },
    {
      key: 'trial',
      header: 'Trial',
      render: (plan) =>
        plan.free_trial_days > 0 ? `${plan.free_trial_days} dias` : 'Sem trial',
    },
    {
      key: 'status',
      header: 'Status',
      render: (plan) =>
        plan.active ? <Badge variant="success">Ativo</Badge> : <Badge>Inativo</Badge>,
    },
    {
      key: 'created_at',
      header: 'Criado em',
      render: (plan) => (
        <span className="text-muted">{formatDate(plan.created_at)}</span>
      ),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-24 text-right',
      render: (plan) => (
        <div className="flex items-center justify-end gap-1">
          {can(Permission.PLAN_UPDATE) && (
            <ButtonLink to={`/billing/plans/${plan.id}/edit`} variant="ghost" size="sm">
              <Pencil className="size-4" />
            </ButtonLink>
          )}
          {can(Permission.PLAN_DELETE) && plan.active && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setPlanToDeactivate(plan)}
              aria-label={`Inativar plano ${plan.name}`}
              className="text-danger hover:bg-danger-soft hover:text-danger"
            >
              <Power className="size-4" />
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Planos"
        description="Gerencie os planos de assinatura oferecidos às empresas do grupo."
        actions={
          <Can permission={Permission.PLAN_CREATE}>
            <ButtonLink to="/billing/plans/create">
              <Plus className="size-4" />
              Novo plano
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <DataTable
          columns={columns}
          rows={query.data ?? []}
          rowKey={(plan) => plan.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={CreditCard}
              title="Nenhum plano cadastrado"
              description="Crie o primeiro plano para começar a cobrar assinaturas."
              action={
                can(Permission.PLAN_CREATE) ? (
                  <ButtonLink to="/billing/plans/create">Criar plano</ButtonLink>
                ) : undefined
              }
            />
          }
        />
      </PageContent>

      <ConfirmDialog
        open={Boolean(planToDeactivate)}
        onClose={() => setPlanToDeactivate(null)}
        title="Inativar plano"
        description={`O plano "${planToDeactivate?.name}" deixará de aparecer no catálogo.`}
        confirmLabel="Inativar"
        variant="danger"
        loading={deactivate.isPending}
        onConfirm={() => {
          if (!planToDeactivate) return
          deactivate.mutate(planToDeactivate.id, {
            onSettled: () => setPlanToDeactivate(null),
          })
        }}
      />
    </Page>
  )
}
