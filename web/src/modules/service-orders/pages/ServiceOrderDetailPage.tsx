import { useRef, useState } from 'react'
import { useParams } from 'react-router'
import { ImagePlus, Trash2 } from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  CardHeader,
  ConfirmDialog,
  DatePicker,
  Field,
  Loading,
  Modal,
  Page,
  PageContent,
  PageHeader,
  Spinner,
  Textarea,
} from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { apiErrorMessage, isApiError } from '@/shared/api/errors'
import { MAX_UPLOAD_BYTES, MAX_UPLOAD_LABEL, uploadFile } from '@/shared/api/uploads'
import { toast } from '@/shared/stores/toast.store'
import { formatCurrency, formatDate, formatDateTime, toLocalIsoDate } from '@/shared/utils/format'
import { billingStatusBadgeVariant, billingStatusLabel } from '@/modules/finance/lib/labels'
import type { ReactNode } from 'react'
import type { ServiceOrder, ServiceOrderStatus } from '@/shared/types/models'
import {
  useAddServiceOrderPhoto,
  useChangeServiceOrderStatus,
  useGenerateServiceOrderBilling,
  useRemoveServiceOrderPhoto,
  useServiceOrderQuery,
  useUpdateServiceOrder,
} from '../hooks/useServiceOrders'
import {
  historyActionLabel,
  historyFieldLabel,
  historyValueLabel,
  isReadableHistoryValue,
  priorityBadgeVariant,
  priorityLabel,
  statusBadgeVariant,
  statusLabel,
} from '../lib/labels'

export default function ServiceOrderDetailPage() {
  const { id } = useParams()
  const { can } = usePermissions()
  const canViewEquipmentDetails = can(Permission.EQUIPMENT_DETAILS_READ)
  const query = useServiceOrderQuery(id)
  const changeStatus = useChangeServiceOrderStatus()
  const generateBilling = useGenerateServiceOrderBilling()
  const [cancelOpen, setCancelOpen] = useState(false)
  const [completeOpen, setCompleteOpen] = useState(false)
  const [billingOpen, setBillingOpen] = useState(false)
  const [cancelReason, setCancelReason] = useState('')
  const [executionNotes, setExecutionNotes] = useState('')
  const [billingDueDate, setBillingDueDate] = useState(toLocalIsoDate())

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
  const items = order.items ?? []
  const billing = order.billing ?? null
  const canGenerateBilling =
    can(Permission.FINANCE_BILLING_CREATE) &&
    order.status !== 'cancelled' &&
    items.length > 0 &&
    (!billing || billing.status === 'cancelled' || billing.status === 'refunded')

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

  const handleGenerateBilling = () => {
    generateBilling.mutate(
      { id: order.id, payload: { due_at: billingDueDate || null } },
      {
        onSuccess: () => {
          setBillingOpen(false)
          void query.refetch()
        },
      },
    )
  }

  return (
    <Page>
      <PageHeader
        title={order.code}
        description={order.client?.name ?? '—'}
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
            {canViewEquipmentDetails && (
              <>
                <Row label="Dispositivo" value={order.equipment?.imei ?? '—'} />
                <Row label="Modelo" value={order.equipment?.model ?? '—'} />
              </>
            )}
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
            {order.cancellation_reason && (
              <Row label="Motivo cancelamento" value={order.cancellation_reason} />
            )}
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader
            title="Serviços e cobrança"
            description="Serviços realizados na OS e cobrança gerada para o cliente."
            actions={
              canGenerateBilling ? (
                <Button onClick={() => setBillingOpen(true)}>Gerar cobrança</Button>
              ) : undefined
            }
          />
          <CardContent className="space-y-4">
            {items.length === 0 ? (
              <p className="text-sm text-muted">
                Nenhum serviço lançado. Edite a OS para adicionar os serviços realizados.
              </p>
            ) : (
              <ul className="space-y-2">
                {items.map((item) => (
                  <li
                    key={item.id}
                    className="flex items-center justify-between gap-3 rounded-lg bg-surface-2/60 px-3 py-2 text-sm"
                  >
                    <div className="min-w-0">
                      <p className="truncate font-medium text-foreground">{item.name}</p>
                      <p className="text-xs text-muted">
                        {item.quantity} × {formatCurrency(item.unit_amount_cents / 100)}
                      </p>
                    </div>
                    <span className="shrink-0 font-medium text-foreground">
                      {formatCurrency(item.total_cents / 100)}
                    </span>
                  </li>
                ))}
              </ul>
            )}

            {items.length > 0 && (
              <div className="flex items-center justify-between text-sm">
                <span className="text-muted">Total dos serviços</span>
                <span className="text-base font-semibold text-foreground">
                  {formatCurrency((order.items_total_cents ?? 0) / 100)}
                </span>
              </div>
            )}

            {billing && (
              <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-2/60 px-3 py-2.5">
                <div className="flex flex-wrap items-center gap-2 text-sm">
                  <Badge variant={billingStatusBadgeVariant(billing.status)}>
                    {billing.status_label ?? billingStatusLabel(billing.status)}
                  </Badge>
                  <span className="text-foreground">Cobrança {billing.code}</span>
                  <span className="text-muted">· vence {formatDate(billing.due_at)}</span>
                </div>
                <ButtonLink to={`/finance/billings/${billing.id}`} variant="secondary" size="sm">
                  Ver cobrança
                </ButtonLink>
              </div>
            )}
          </CardContent>
        </Card>

        <ServiceOrderPhotosCard order={order} />

        <ServiceOrderExecutionNotesCard order={order} canEdit={canEdit} />

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
                      {historyActionLabel(item.action)}
                      {item.field ? ` · ${historyFieldLabel(item.field)}` : ''}
                    </p>
                    <p className="text-[13px] text-muted">
                      {item.user?.name ?? 'Sistema'} ·{' '}
                      {item.created_at ? formatDateTime(item.created_at) : '—'}
                    </p>
                    {isReadableHistoryValue(item.old_value) &&
                      isReadableHistoryValue(item.new_value) &&
                      (item.old_value || item.new_value) && (
                        <p className="text-[13px] text-muted">
                          {historyValueLabel(item.old_value)} → {historyValueLabel(item.new_value)}
                        </p>
                      )}
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </PageContent>

      <Modal
        open={billingOpen}
        onClose={() => setBillingOpen(false)}
        title="Gerar cobrança"
        description={`Cobrança para ${order.client?.name ?? '—'} com os serviços desta OS.`}
        footer={
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setBillingOpen(false)}>
              Cancelar
            </Button>
            <Button onClick={handleGenerateBilling} loading={generateBilling.isPending}>
              Gerar cobrança
            </Button>
          </div>
        }
      >
        <div className="space-y-4">
          <div className="flex items-center justify-between rounded-xl bg-surface-2/60 px-3 py-2.5 text-sm">
            <span className="text-muted">Total dos serviços</span>
            <span className="font-semibold text-foreground">
              {formatCurrency((order.items_total_cents ?? 0) / 100)}
            </span>
          </div>
          <Field label="Vencimento">
            <DatePicker
              value={billingDueDate}
              onChange={(event) => setBillingDueDate(event.target.value)}
              aria-label="Vencimento da cobrança"
            />
          </Field>
          <p className="text-[13px] text-muted">
            A cobrança ficará disponível no financeiro para envio ao cliente (PIX, boleto ou cartão).
          </p>
        </div>
      </Modal>

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

function ServiceOrderPhotosCard({ order }: { order: ServiceOrder }) {
  const { can } = usePermissions()
  const inputRef = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)
  const addPhoto = useAddServiceOrderPhoto(order.id)
  const removePhoto = useRemoveServiceOrderPhoto(order.id)
  const photos = order.photos ?? []
  const canManage = can(Permission.SERVICE_ORDER_UPDATE) && order.status !== 'cancelled'

  const handleFiles = async (files: FileList | null) => {
    if (!files || files.length === 0) return

    const selected = Array.from(files)
    if (inputRef.current) {
      inputRef.current.value = ''
    }

    if (selected.some((file) => file.size > MAX_UPLOAD_BYTES)) {
      toast.error('Arquivo muito grande', `O limite para envio é de ${MAX_UPLOAD_LABEL}.`)
      return
    }

    setUploading(true)

    try {
      for (const file of selected) {
        const { path } = await uploadFile(file)
        await addPhoto.mutateAsync(path)
      }
    } catch (error) {
      toast.error(
        'Falha ao enviar foto',
        isApiError(error)
          ? apiErrorMessage(error)
          : 'Não foi possível enviar a foto. Tente novamente.',
      )
    } finally {
      setUploading(false)
    }
  }

  return (
    <Card>
      <CardHeader title="Fotos" description="Registros fotográficos da execução da OS." />
      <CardContent className="space-y-4">
        {photos.length === 0 && <p className="text-sm text-muted">Nenhuma foto enviada.</p>}

        <div className="flex flex-wrap gap-3">
          {photos.map((photo) => (
            <div
              key={photo.id}
              className="relative h-24 w-40 overflow-hidden rounded-xl bg-surface-2 shadow-card"
            >
              <img src={photo.url} alt="" className="h-full w-full object-cover" />
              {canManage && (
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => removePhoto.mutate(photo.id)}
                  loading={removePhoto.isPending && removePhoto.variables === photo.id}
                  aria-label="Remover foto"
                  className="absolute right-1.5 bottom-1.5 px-2 shadow-raised"
                >
                  <Trash2 className="size-3.5" aria-hidden="true" />
                </Button>
              )}
            </div>
          ))}

          {canManage && (
            <>
              <button
                type="button"
                disabled={uploading}
                onClick={() => inputRef.current?.click()}
                className="flex h-24 w-40 cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl bg-surface-2 text-muted transition-colors hover:bg-surface-3 disabled:cursor-default disabled:opacity-60"
              >
                {uploading ? (
                  <Spinner className="size-5" />
                ) : (
                  <ImagePlus className="size-5" aria-hidden="true" />
                )}
                <span className="text-xs font-medium">
                  {uploading ? 'Enviando...' : 'Adicionar foto'}
                </span>
              </button>
              <input
                ref={inputRef}
                type="file"
                accept="image/png,image/jpeg,image/webp"
                multiple
                className="hidden"
                onChange={(event) => void handleFiles(event.target.files)}
              />
            </>
          )}
        </div>
      </CardContent>
    </Card>
  )
}

function ServiceOrderExecutionNotesCard({
  order,
  canEdit,
}: {
  order: ServiceOrder
  canEdit: boolean
}) {
  const [value, setValue] = useState(order.execution_notes ?? '')
  const update = useUpdateServiceOrder(order.id)
  const dirty = value !== (order.execution_notes ?? '')

  const save = () => {
    update.mutate({ execution_notes: value.trim() ? value : null })
  }

  return (
    <Card>
      <CardHeader
        title="Observações da execução"
        description="Anotações do técnico sobre o serviço realizado."
      />
      <CardContent className="space-y-3">
        {canEdit ? (
          <>
            <Textarea
              value={value}
              onChange={(event) => setValue(event.target.value)}
              placeholder="Descreva a execução, materiais utilizados, pendências..."
              rows={5}
            />
            <div className="flex justify-end">
              <Button size="sm" onClick={save} loading={update.isPending} disabled={!dirty}>
                Salvar observações
              </Button>
            </div>
          </>
        ) : (
          <p className="text-sm whitespace-pre-line text-foreground">
            {order.execution_notes || '—'}
          </p>
        )}
      </CardContent>
    </Card>
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
