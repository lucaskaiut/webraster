import { useState } from 'react'
import { useNavigate, useParams } from 'react-router'
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
  Textarea,
} from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatDateTime } from '@/shared/utils/format'
import type { ReactNode } from 'react'
import type { ServiceOrderStatus } from '@/shared/types/models'
import {
  useChangeServiceOrderStatus,
  useServiceOrderQuery,
} from '../hooks/useServiceOrders'
import {
  priorityBadgeVariant,
  priorityLabel,
  statusBadgeVariant,
  statusLabel,
  typeLabel,
} from '../lib/labels'

export default function ServiceOrderDetailPage() {
  const { id } = useParams()
  const { can } = usePermissions()
  const query = useServiceOrderQuery(id)
  const changeStatus = useChangeServiceOrderStatus()
  const [cancelOpen, setCancelOpen] = useState(false)
  const [completeOpen, setCompleteOpen] = useState(false)
  const [cancelReason, setCancelReason] = useState('')
  const [executionNotes, setExecutionNotes] = useState('')

  if (query.isLoading) {
    return (
      <Page>
        <PageContent>
          <Loading />
        </PageContent>
      </Page>
    )
  }

  const order = query.data
  if (!order) {
    return (
      <Page>
        <PageHeader
          title="OS não encontrada"
          breadcrumb={[{ label: 'Ordens de serviço', to: '/service-orders' }]}
        />
      </Page>
    )
  }

  const canEdit =
    can(Permission.SERVICE_ORDER_UPDATE) &&
    order.status !== 'completed' &&
    order.status !== 'cancelled'
  const canChangeStatus = can(Permission.SERVICE_ORDER_CHANGE_STATUS)

  const transition = (
    status: ServiceOrderStatus,
    extras?: { cancellation_reason?: string; execution_notes?: string | null },
  ) => {
    changeStatus.mutate(
      { id: order.id, payload: { status, ...extras } },
      {
        onSuccess: () => {
          setCancelOpen(false)
          setCompleteOpen(false)
          setCancelReason('')
          setExecutionNotes('')
          void query.refetch()
        },
      },
    )
  }

  return (
    <Page>
      <PageHeader
        title={order.code}
        description={`${order.type_label ?? typeLabel(order.type)} · ${order.client?.name ?? '—'}`}
        breadcrumb={[
          { label: 'Ordens de serviço', to: '/service-orders' },
          { label: order.code },
        ]}
        actions={
          <div className="flex flex-wrap gap-2">
            {canEdit && (
              <ButtonLink to={`/service-orders/${order.id}/edit`} variant="secondary">
                Editar
              </ButtonLink>
            )}
            {canChangeStatus && order.status === 'open' && (
              <Button onClick={() => transition('in_progress')}>Iniciar</Button>
            )}
            {canChangeStatus && order.status === 'in_progress' && (
              <Button onClick={() => setCompleteOpen(true)}>Concluir</Button>
            )}
            {canChangeStatus && (order.status === 'open' || order.status === 'in_progress') && (
              <Button variant="secondary" onClick={() => setCancelOpen(true)}>
                Cancelar
              </Button>
            )}
          </div>
        }
      />
      <PageContent className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader title="Informações gerais" />
          <CardContent className="space-y-3 text-sm">
            <Row label="Número" value={order.code} />
            <Row label="Tipo" value={order.type_label ?? typeLabel(order.type)} />
            <Row
              label="Status"
              value={
                <Badge variant={statusBadgeVariant(order.status)}>
                  {order.status_label ?? statusLabel(order.status)}
                </Badge>
              }
            />
            <Row
              label="Prioridade"
              value={
                <Badge variant={priorityBadgeVariant(order.priority)}>
                  {order.priority_label ?? priorityLabel(order.priority)}
                </Badge>
              }
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader title="Cliente e ativos" />
          <CardContent className="space-y-3 text-sm">
            <Row label="Cliente" value={order.client?.name ?? '—'} />
            <Row label="Veículo" value={order.vehicle?.plate ?? '—'} />
            <Row label="Dispositivo" value={order.equipment?.imei ?? '—'} />
            <Row label="Modelo" value={order.equipment?.model ?? '—'} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader title="Atendimento" />
          <CardContent className="space-y-3 text-sm">
            <Row label="Técnico" value={order.technician?.name ?? '—'} />
            <Row
              label="Início"
              value={order.scheduled_start_at ? formatDateTime(order.scheduled_start_at) : '—'}
            />
            <Row
              label="Fim"
              value={order.scheduled_end_at ? formatDateTime(order.scheduled_end_at) : '—'}
            />
            <Row label="Descrição" value={order.description || '—'} />
            <Row label="Observações" value={order.notes || '—'} />
            {order.execution_notes && <Row label="Execução" value={order.execution_notes} />}
            {order.cancellation_reason && (
              <Row label="Motivo cancelamento" value={order.cancellation_reason} />
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader title="Auditoria" />
          <CardContent className="space-y-3 text-sm">
            <Row label="Criado por" value={order.created_by?.name ?? '—'} />
            <Row label="Criado em" value={order.created_at ? formatDateTime(order.created_at) : '—'} />
            <Row label="Atualizado em" value={order.updated_at ? formatDateTime(order.updated_at) : '—'} />
            {order.completed_at && (
              <Row
                label="Concluída"
                value={`${formatDateTime(order.completed_at)}${order.completed_by ? ` · ${order.completed_by.name}` : ''}`}
              />
            )}
            {order.cancelled_at && (
              <Row
                label="Cancelada"
                value={`${formatDateTime(order.cancelled_at)}${order.cancelled_by ? ` · ${order.cancelled_by.name}` : ''}`}
              />
            )}
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader title="Histórico" />
          <CardContent>
            {(order.histories?.length ?? 0) === 0 ? (
              <p className="text-sm text-muted">Sem eventos registrados.</p>
            ) : (
              <ul className="space-y-3">
                {order.histories!.map((item) => (
                  <li key={item.id} className="text-sm">
                    <p className="font-medium text-foreground">
                      {item.action}
                      {item.field ? ` · ${item.field}` : ''}
                    </p>
                    <p className="text-[13px] text-muted">
                      {item.user?.name ?? 'Sistema'} ·{' '}
                      {item.created_at ? formatDateTime(item.created_at) : '—'}
                    </p>
                    {(item.old_value || item.new_value) && (
                      <p className="text-[13px] text-muted">
                        {item.old_value ?? '—'} → {item.new_value ?? '—'}
                      </p>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </PageContent>

      <ConfirmDialog
        open={cancelOpen}
        onClose={() => setCancelOpen(false)}
        onConfirm={() => {
          if (!cancelReason.trim()) return
          transition('cancelled', { cancellation_reason: cancelReason.trim() })
        }}
        loading={changeStatus.isPending}
        title="Cancelar OS"
        description={
          <div className="space-y-3">
            <p>Informe o motivo do cancelamento.</p>
            <Textarea
              value={cancelReason}
              onChange={(event) => setCancelReason(event.target.value)}
              placeholder="Motivo..."
              rows={3}
            />
          </div>
        }
        confirmLabel="Cancelar OS"
        variant="danger"
      />

      <ConfirmDialog
        open={completeOpen}
        onClose={() => setCompleteOpen(false)}
        onConfirm={() => transition('completed', { execution_notes: executionNotes || null })}
        loading={changeStatus.isPending}
        title="Concluir OS"
        description={
          <div className="space-y-3">
            <p>Deseja marcar esta OS como concluída?</p>
            <Textarea
              value={executionNotes}
              onChange={(event) => setExecutionNotes(event.target.value)}
              placeholder="Observações da execução (opcional)"
              rows={3}
            />
          </div>
        }
        confirmLabel="Concluir"
        variant="primary"
      />
    </Page>
  )
}

function Row({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5 sm:flex-row sm:gap-3">
      <dt className="w-40 shrink-0 text-muted">{label}</dt>
      <dd className="min-w-0 text-foreground">{value}</dd>
    </div>
  )
}
