import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router'
import {
  Badge,
  ButtonLink,
  Card,
  CardContent,
  Page,
  PageContent,
  PageHeader,
  SearchSelect,
  Spinner,
} from '@/shared/design-system'
import { usersService } from '@/modules/users/services/users.service'
import { useServiceOrdersCalendarQuery } from '../hooks/useServiceOrders'
import { statusBadgeVariant, statusLabel, typeLabel } from '../lib/labels'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { Plus } from 'lucide-react'

export default function ServiceOrdersCalendarPage() {
  const navigate = useNavigate()
  const [technicianId, setTechnicianId] = useState('')
  const query = useServiceOrdersCalendarQuery({
    technician_id: technicianId || undefined,
  })

  const grouped = useMemo(() => {
    const map = new Map<string, typeof query.data>()
    for (const order of query.data ?? []) {
      if (!order.scheduled_start_at) continue
      const day = order.scheduled_start_at.slice(0, 10)
      const list = map.get(day) ?? []
      list.push(order)
      map.set(day, list)
    }
    return [...map.entries()].sort(([a], [b]) => a.localeCompare(b))
  }, [query.data])

  return (
    <Page>
      <PageHeader
        title="Agenda de OS"
        description="Visualize agendamentos por técnico e data."
        breadcrumb={[
          { label: 'Ordens de serviço', to: '/service-orders' },
          { label: 'Agenda' },
        ]}
        actions={
          <div className="flex flex-wrap gap-2">
            <ButtonLink to="/service-orders" variant="secondary">
              Lista
            </ButtonLink>
            <ButtonLink to="/service-orders/kanban" variant="secondary">
              Kanban
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
      <PageContent className="space-y-4">
        <div className="max-w-sm">
          <SearchSelect
            label="Técnico"
            placeholder="Todos os técnicos"
            value={technicianId}
            onChange={(value) => setTechnicianId(value)}
            loadOptions={async (search) => {
              const response = await usersService.list({ search: search || undefined, per_page: 20 })
              return [
                { value: '', label: 'Todos os técnicos' },
                ...response.data.map((user) => ({ value: user.id, label: user.name })),
              ]
            }}
            resolveLabel={async (value) => {
              if (!value) return { value: '', label: 'Todos os técnicos' }
              try {
                const user = await usersService.get(value)
                return { value: user.id, label: user.name }
              } catch {
                return null
              }
            }}
          />
        </div>

        {query.isPending ? (
          <div className="flex justify-center py-16">
            <Spinner />
          </div>
        ) : grouped.length === 0 ? (
          <p className="py-12 text-center text-sm text-muted">Nenhum agendamento no período.</p>
        ) : (
          <div className="space-y-4">
            {grouped.map(([day, orders]) => (
              <Card key={day}>
                <CardContent className="space-y-3">
                  <h2 className="text-sm font-semibold text-foreground">
                    {new Date(`${day}T12:00:00`).toLocaleDateString('pt-BR', {
                      weekday: 'long',
                      day: '2-digit',
                      month: 'long',
                    })}
                  </h2>
                  <ul className="space-y-2">
                    {orders!.map((order) => (
                      <li key={order.id}>
                        <button
                          type="button"
                          className="flex w-full flex-col gap-1 rounded-lg px-3 py-2 text-left transition-colors hover:bg-surface-2 sm:flex-row sm:items-center sm:justify-between"
                          onClick={() => navigate(`/service-orders/${order.id}`)}
                        >
                          <div className="min-w-0">
                            <p className="font-medium text-foreground">
                              {order.scheduled_start_at
                                ? new Date(order.scheduled_start_at).toLocaleTimeString('pt-BR', {
                                    hour: '2-digit',
                                    minute: '2-digit',
                                  })
                                : '—'}{' '}
                              · {order.code} · {order.type_label ?? typeLabel(order.type)}
                            </p>
                            <p className="truncate text-[13px] text-muted">
                              {order.client?.name ?? '—'}
                              {order.vehicle?.plate ? ` · ${order.vehicle.plate}` : ''}
                              {order.technician?.name ? ` · ${order.technician.name}` : ''}
                            </p>
                          </div>
                          <Badge variant={statusBadgeVariant(order.status)}>
                            {order.status_label ?? statusLabel(order.status)}
                          </Badge>
                        </button>
                      </li>
                    ))}
                  </ul>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </PageContent>
    </Page>
  )
}
