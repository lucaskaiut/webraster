import { useState } from 'react'
import { BellRing, Pencil, Plus, Trash2 } from 'lucide-react'
import {
  Badge,
  Button,
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
import type { AlertConfig } from '@/shared/types/models'
import { AlertConfigForm } from '../forms/AlertConfigForm'
import {
  useAlertConfigsQuery,
  useCreateAlertConfig,
  useDeleteAlertConfig,
  useUpdateAlertConfig,
} from '../hooks/useAlerts'

function scopeLabel(config: AlertConfig) {
  if (config.scope === 'vehicle') return config.vehicle?.plate ?? 'Veículo'
  if (config.scope === 'client') return config.client?.name ?? 'Cliente'
  return 'Todos os veículos'
}

function settingsSummary(config: AlertConfig) {
  if (config.type === 'speed') {
    return `${config.settings.speed_limit_kmh ?? 80} km/h · ${config.settings.min_duration_seconds ?? 60}s`
  }
  if (config.type === 'offline') {
    return `${config.settings.offline_minutes ?? 15} min`
  }
  if (config.type === 'battery') {
    return `≤ ${config.settings.battery_threshold ?? 20}%`
  }
  return '—'
}

export default function AlertConfigPage() {
  const { can } = usePermissions()
  const query = useAlertConfigsQuery()
  const create = useCreateAlertConfig()
  const update = useUpdateAlertConfig()
  const remove = useDeleteAlertConfig()
  const [showCreate, setShowCreate] = useState(false)
  const [editing, setEditing] = useState<AlertConfig | null>(null)
  const [toDelete, setToDelete] = useState<AlertConfig | null>(null)

  const canManage = can(Permission.ALERT_CONFIG_UPDATE)

  const columns: Array<Column<AlertConfig>> = [
    {
      key: 'name',
      header: 'Alerta',
      render: (item) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{item.name || item.type_label || item.type}</p>
          <p className="truncate text-[13px] text-muted">{item.type_label ?? item.type}</p>
        </div>
      ),
    },
    {
      key: 'scope',
      header: 'Escopo',
      render: (item) => (
        <Badge variant={item.scope === 'all' ? 'neutral' : item.scope === 'client' ? 'primary' : 'success'}>
          {scopeLabel(item)}
        </Badge>
      ),
    },
    {
      key: 'settings',
      header: 'Valores',
      render: (item) => <span className="text-muted">{settingsSummary(item)}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (item) => (
        <Badge variant={item.is_enabled ? 'success' : 'neutral'}>
          {item.is_enabled ? 'Ativo' : 'Inativo'}
        </Badge>
      ),
    },
    {
      key: 'notify',
      header: 'Canais',
      render: (item) => (
        <span className="text-[13px] text-muted">
          {[item.notify_in_app ? 'App' : null, item.notify_email ? 'E-mail' : null]
            .filter(Boolean)
            .join(' · ') || '—'}
        </span>
      ),
    },
    ...(canManage
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (item: AlertConfig) => (
              <div className="flex items-center justify-end gap-1">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => {
                    setShowCreate(false)
                    setEditing(item)
                  }}
                  aria-label={`Editar ${item.name || item.type}`}
                >
                  <Pencil className="size-4" />
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => setToDelete(item)}
                  aria-label={`Excluir ${item.name || item.type}`}
                  className="text-danger hover:bg-danger-soft hover:text-danger"
                >
                  <Trash2 className="size-4" />
                </Button>
              </div>
            ),
          } satisfies Column<AlertConfig>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Configuração de alertas"
        description="Crie regras por veículo, cliente ou para toda a frota."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Alertas', to: '/alerts' },
          { label: 'Configuração' },
        ]}
        actions={
          canManage ? (
            <Button
              onClick={() => {
                setEditing(null)
                setShowCreate(true)
              }}
            >
              <Plus className="size-4" />
              Novo alerta
            </Button>
          ) : undefined
        }
      />
      <PageContent className="space-y-4">
        {(showCreate || editing) && canManage && (
          <AlertConfigForm
            key={editing?.id ?? 'create'}
            mode={editing ? 'edit' : 'create'}
            initial={editing}
            submitting={create.isPending || update.isPending}
            onCancel={() => {
              setShowCreate(false)
              setEditing(null)
            }}
            onSubmit={async (payload) => {
              if (editing) {
                await update.mutateAsync({ id: editing.id, payload })
                setEditing(null)
                return
              }
              await create.mutateAsync(payload)
              setShowCreate(false)
            }}
          />
        )}

        <DataTable
          caption="Regras de alerta"
          columns={columns}
          rows={query.data ?? []}
          rowKey={(item) => item.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={BellRing}
              title="Nenhuma regra"
              description="Crie um alerta e escolha o veículo, o cliente ou deixe em branco para todos."
              action={
                <Can permission={Permission.ALERT_CONFIG_UPDATE}>
                  <Button onClick={() => setShowCreate(true)}>
                    <Plus className="size-4" />
                    Novo alerta
                  </Button>
                </Can>
              }
            />
          }
        />
      </PageContent>

      <ConfirmDialog
        open={toDelete !== null}
        onClose={() => setToDelete(null)}
        onConfirm={() => {
          if (!toDelete) return
          remove.mutate(toDelete.id, { onSettled: () => setToDelete(null) })
        }}
        loading={remove.isPending}
        title="Excluir regra"
        description={
          <>
            Remover <strong>{toDelete?.name || toDelete?.type_label}</strong>?
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
