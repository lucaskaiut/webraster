import { useSearchParams } from 'react-router'
import { ScrollText } from 'lucide-react'
import {
  Badge,
  DataTable,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  Select,
  type Column,
} from '@/shared/design-system'
import { formatDateTime } from '@/shared/utils/format'
import type { AuditLog } from '@/shared/types/models'
import { useAuditQuery } from '../hooks/useAudit'

const PER_PAGE = 15

const ACTION_LABELS: Record<string, { label: string; variant: 'neutral' | 'primary' | 'success' | 'warning' | 'danger' }> = {
  master_login: { label: 'Login', variant: 'primary' },
  tenant_selected: { label: 'Seleção de organização', variant: 'neutral' },
  tenant_switched: { label: 'Troca de organização', variant: 'neutral' },
}

export default function AuditPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Number(searchParams.get('page') ?? 1)
  const action = searchParams.get('action') ?? ''

  const query = useAuditQuery({ page, per_page: PER_PAGE, action: action || undefined })

  const columns: Array<Column<AuditLog>> = [
    {
      key: 'action',
      header: 'Evento',
      render: (log) => {
        const info = ACTION_LABELS[log.action]
        return info ? <Badge variant={info.variant}>{info.label}</Badge> : <Badge variant="neutral">{log.action}</Badge>
      },
    },
    {
      key: 'entity',
      header: 'Entidade',
      render: (log) => (
        <div className="min-w-0">
          <p className="truncate text-foreground">{log.entity_type ?? '—'}</p>
          {log.details && <p className="truncate text-[13px] text-muted">{JSON.stringify(log.details)}</p>}
        </div>
      ),
    },
    {
      key: 'user',
      header: 'Usuário',
      render: (log) => <span className="text-muted">{log.user?.name ?? '—'}</span>,
    },
    {
      key: 'created_at',
      header: 'Data/hora',
      render: (log) => <span className="text-muted">{formatDateTime(log.created_at)}</span>,
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Auditoria"
        description="Trilha de auditoria do sistema."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Auditoria' }]}
      />

      <PageContent>
        <div className="flex gap-2">
          <Select
            aria-label="Filtrar por evento"
            className="w-60"
            value={action}
            onChange={(e) => setSearchParams((p) => { e.target.value ? p.set('action', e.target.value) : p.delete('action'); p.delete('page'); return p }, { replace: true })}
            options={[
              { value: '', label: 'Todos os eventos' },
              ...Object.entries(ACTION_LABELS).map(([value, info]) => ({ value, label: info.label })),
            ]}
          />
        </div>

        <DataTable
          caption="Registros de auditoria"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(log) => log.id}
          loading={query.isPending}
          emptyState={<EmptyState icon={ScrollText} title="Nenhum registro de auditoria" />}
        />

        {query.data && <Pagination meta={query.data.meta} onPageChange={(next) => setSearchParams((p) => { next > 1 ? p.set('page', String(next)) : p.delete('page'); return p })} />}
      </PageContent>
    </Page>
  )
}
