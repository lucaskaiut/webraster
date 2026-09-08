import { useNavigate } from 'react-router'
import { ButtonLink, Page, PageContent, PageHeader, Spinner } from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatDateTime } from '@/shared/utils/format'
import type { ServiceOrder, ServiceOrderStatus } from '@/shared/types/models'
import { useChangeServiceOrderStatus, useServiceOrdersKanbanQuery } from '../hooks/useServiceOrders'
import {
  KANBAN_COLUMNS,
  priorityBadgeVariant,
  priorityLabel,
  statusLabel,
  typeLabel,
} from '../lib/labels'
import { Badge } from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Plus } from 'lucide-react'
import { cn } from '@/shared/utils/cn'

const NEXT_STATUS: Partial<Record<ServiceOrderStatus, ServiceOrderStatus>> = {
  open: 'in_progress',
  in_progress: 'completed',
}

export default function ServiceOrdersKanbanPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const query = useServiceOrdersKanbanQuery()
  const changeStatus = useChangeServiceOrderStatus()

  const move = (order: ServiceOrder, status: ServiceOrderStatus) => {
    if (!can(Permission.SERVICE_ORDER_CHANGE_STATUS)) return
    changeStatus.mutate({ id: order.id, payload: { status } })
  }

  return (
    <Page>
      <PageHeader
        title="Kanban de OS"
        description="Acompanhe o fluxo operacional por status."
        breadcrumb={[
          { label: 'Ordens de serviço', to: '/service-orders' },
          { label: 'Kanban' },
        ]}
        actions={
          <div className="flex flex-wrap gap-2">
            <ButtonLink to="/service-orders" variant="secondary">
              Lista
            </ButtonLink>
            <Can permission={Permission.SERVICE_ORDER_CREATE}>
              <ButtonLink to="/service-orders/create">
                <Plus className="size-4" />
                Nova OS
              </ButtonLink>
            </Can>
          </div>
        }
      />
      <PageContent>
        {query.isPending ? (
          <div className="flex justify-center py-16">
            <Spinner />
          </div>
        ) : (
          <div className="grid w-full grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {KANBAN_COLUMNS.map((status) => {
              const items = query.data?.[status] ?? []
              return (
                <section
                  key={status}
                  className="flex min-w-0 flex-col rounded-xl bg-surface-2/60 p-3"
                >
                  <header className="mb-3 flex items-center justify-between px-1">
                    <h2 className="text-sm font-semibold text-foreground">{statusLabel(status)}</h2>
                    <span className="text-xs text-muted">{items.length}</span>
                  </header>
                  <div className="flex flex-1 flex-col gap-2">
                    {items.length === 0 ? (
                      <p className="px-1 py-6 text-center text-xs text-muted">Nenhuma OS</p>
                    ) : (
                      items.map((order) => (
                        <article
                          key={order.id}
                          className={cn(
                            'cursor-pointer rounded-xl bg-surface p-3 shadow-card transition-colors hover:bg-surface-2',
                          )}
                          onClick={() => navigate(`/service-orders/${order.id}`)}
                        >
                          <div className="flex items-start justify-between gap-2">
                            <p className="min-w-0 truncate font-medium text-foreground">{order.code}</p>
                            <Badge variant={priorityBadgeVariant(order.priority)}>
                              {order.priority_label ?? priorityLabel(order.priority)}
                            </Badge>
                          </div>
                          <p className="mt-1 text-[13px] text-muted">
                            {order.type_label ?? typeLabel(order.type)}
                          </p>
                          <p className="mt-2 truncate text-sm text-foreground">{order.client?.name ?? '—'}</p>
                          <p className="truncate text-[13px] text-muted">{order.vehicle?.plate ?? 'Sem veículo'}</p>
                          <p className="mt-2 text-[12px] text-muted">
                            {order.technician?.name ?? 'Sem técnico'}
                            {order.scheduled_start_at
                              ? ` · ${formatDateTime(order.scheduled_start_at)}`
                              : ''}
                          </p>
                          {NEXT_STATUS[order.status] && can(Permission.SERVICE_ORDER_CHANGE_STATUS) && (
                            <button
                              type="button"
                              className="mt-3 text-xs font-medium text-primary"
                              onClick={(event) => {
                                event.stopPropagation()
                                move(order, NEXT_STATUS[order.status]!)
                              }}
                            >
                              Avançar →
                            </button>
                          )}
                        </article>
                      ))
                    )}
                  </div>
                </section>
              )
            })}
          </div>
        )}
      </PageContent>
    </Page>
  )
}
