import { useState } from 'react'
import { Pencil, Plus, Trash2, Webhook as WebhookIcon } from 'lucide-react'
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
import { formatDate } from '@/shared/utils/format'
import type { Webhook } from '@/shared/types/models'
import { useDeleteWebhook, useWebhooksQuery } from '../hooks/useWebhooks'

export default function WebhooksListPage() {
  const query = useWebhooksQuery()
  const deleteWebhook = useDeleteWebhook()
  const { can } = usePermissions()

  const [webhookToDelete, setWebhookToDelete] = useState<Webhook | null>(null)

  const confirmDelete = () => {
    if (!webhookToDelete) return
    deleteWebhook.mutate(webhookToDelete.id, { onSettled: () => setWebhookToDelete(null) })
  }

  const columns: Array<Column<Webhook>> = [
    {
      key: 'name',
      header: 'Nome',
      render: (webhook) => (
        <div className="flex items-center gap-3">
          <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-2 text-muted">
            <WebhookIcon className="size-4" aria-hidden="true" />
          </span>
          <div>
            <span className="font-medium text-foreground">{webhook.name}</span>
            {webhook.description && (
              <span className="mt-0.5 block max-w-60 truncate text-xs text-muted">
                {webhook.description}
              </span>
            )}
          </div>
        </div>
      ),
    },
    {
      key: 'event',
      header: 'Evento',
      render: (webhook) => <Badge variant="primary">{webhook.event}</Badge>,
    },
    {
      key: 'url',
      header: 'URL',
      render: (webhook) => (
        <span className="max-w-48 truncate text-muted" title={webhook.url}>
          {webhook.url}
        </span>
      ),
    },
    {
      key: 'method',
      header: 'Método',
      render: (webhook) => <Badge>{webhook.method}</Badge>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (webhook) =>
        webhook.is_active ? (
          <Badge variant="success">Ativo</Badge>
        ) : (
          <Badge>Inativo</Badge>
        ),
    },
    {
      key: 'created_at',
      header: 'Criado em',
      render: (webhook) => (
        <span className="text-muted">{webhook.created_at ? formatDate(webhook.created_at) : '-'}</span>
      ),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-20 text-right',
      render: (webhook: Webhook) => (
        <div className="flex items-center justify-end gap-1">
          {can(Permission.WEBHOOK_UPDATE) && (
            <ButtonLink to={`/webhooks/${webhook.id}/edit`} variant="ghost" size="sm">
              <Pencil className="size-4" />
            </ButtonLink>
          )}
          {can(Permission.WEBHOOK_DELETE) && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setWebhookToDelete(webhook)}
              aria-label={`Remover webhook ${webhook.name}`}
              className="text-danger hover:bg-danger-soft hover:text-danger"
            >
              <Trash2 className="size-4" />
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Webhooks"
        description="Configure notificações HTTP para eventos do sistema."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Webhooks' }]}
        actions={
          <Can permission={Permission.WEBHOOK_CREATE}>
            <ButtonLink to="/webhooks/create">
              <Plus className="size-4" />
              Novo webhook
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <DataTable
          caption="Lista de webhooks"
          columns={columns}
          rows={query.data ?? []}
          rowKey={(webhook) => webhook.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={WebhookIcon}
              title="Nenhum webhook configurado"
              description="Crie webhooks para receber notificações de eventos do sistema."
              action={
                <Can permission={Permission.WEBHOOK_CREATE}>
                  <ButtonLink to="/webhooks/create">
                    <Plus className="size-4" />
                    Novo webhook
                  </ButtonLink>
                </Can>
              }
            />
          }
        />
      </PageContent>

      <ConfirmDialog
        open={webhookToDelete !== null}
        onClose={() => setWebhookToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteWebhook.isPending}
        title="Remover webhook"
        description={
          <>
            Tem certeza que deseja remover o webhook{' '}
            <strong>{webhookToDelete?.name}</strong>? Ele deixará de ser disparado.
          </>
        }
        confirmLabel="Remover"
      />
    </Page>
  )
}
