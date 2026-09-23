import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Contact, Pencil, Plus, Trash2 } from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  ConfirmDialog,
  DataTable,
  EmptyState,
  FilterBar,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  SearchInput,
  Select,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { formatDate } from '@/shared/utils/format'
import { formatDocument } from '@/shared/utils/document'
import type { Client } from '@/shared/types/models'
import { useClientsQuery, useDeleteClient } from '../hooks/useClients'

const PER_PAGE = 10

export default function ClientsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const delinquentParam = searchParams.get('delinquent')
  const delinquencyFilter = delinquentParam === '1' ? 'delinquent' : ''

  const navigate = useNavigate()
  const { can } = usePermissions()

  const [clientToDelete, setClientToDelete] = useState<Client | null>(null)
  const deleteClient = useDeleteClient()

  const query = useClientsQuery({
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    delinquent: delinquentParam === '1' ? true : undefined,
  })

  const updateParams = (next: { page?: number; search?: string; delinquent?: string }) => {
    setSearchParams(
      (params) => {
        if (next.search !== undefined) {
          next.search ? params.set('search', next.search) : params.delete('search')
          params.delete('page')
        }
        if (next.delinquent !== undefined) {
          next.delinquent ? params.set('delinquent', next.delinquent) : params.delete('delinquent')
          params.delete('page')
        }
        if (next.page !== undefined) {
          next.page > 1 ? params.set('page', String(next.page)) : params.delete('page')
        }
        return params
      },
      { replace: true },
    )
  }

  const handleSearch = (value: string) => {
    setSearch(value)
    updateParams({ search: value })
  }

  const confirmDelete = () => {
    if (!clientToDelete) return

    deleteClient.mutate(clientToDelete.id, { onSettled: () => setClientToDelete(null) })
  }

  const canMutate = can(Permission.CLIENT_UPDATE) || can(Permission.CLIENT_DELETE)

  const columns: Array<Column<Client>> = [
    {
      key: 'name',
      header: 'Cliente',
      render: (client) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{client.name}</p>
          <p className="truncate text-[13px] text-muted">{client.email ?? '—'}</p>
        </div>
      ),
    },
    {
      key: 'document',
      header: 'CPF/CNPJ',
      render: (client) => <span className="text-muted">{formatDocument(client.document)}</span>,
    },
    {
      key: 'phone',
      header: 'Telefone',
      render: (client) => <span className="text-muted">{client.phone ?? '—'}</span>,
    },
    {
      key: 'is_active',
      header: 'Status',
      render: (client) => (
        <Badge variant={client.is_active ? 'success' : 'neutral'}>
          {client.is_active ? 'Ativo' : 'Inativo'}
        </Badge>
      ),
    },
    {
      key: 'created_at',
      header: 'Criado em',
      render: (client) => <span className="text-muted">{formatDate(client.created_at)}</span>,
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (client: Client) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.CLIENT_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/clients/${client.id}/edit`)}
                    aria-label={`Editar ${client.name}`}
                  >
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.CLIENT_DELETE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setClientToDelete(client)}
                    aria-label={`Excluir ${client.name}`}
                    className="text-danger hover:bg-danger-soft hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<Client>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Clientes"
        description="Gerencie os clientes da sua operação."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Clientes' }]}
        actions={
          <Can permission={Permission.CLIENT_CREATE}>
            <ButtonLink to="/clients/create">
              <Plus className="size-4" />
              Novo cliente
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por nome, documento ou e-mail..."
            aria-label="Buscar clientes"
            value={search}
            onChange={(event) => handleSearch(event.target.value)}
          />
          <Select
            aria-label="Filtrar por inadimplência"
            className="w-48"
            value={delinquencyFilter}
            onChange={(event) =>
              updateParams({ delinquent: event.target.value === 'delinquent' ? '1' : '' })
            }
            options={[
              { value: '', label: 'Todos os clientes' },
              { value: 'delinquent', label: 'Somente inadimplentes' },
            ]}
          />
        </FilterBar>

        <DataTable
          caption="Lista de clientes"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(client) => client.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Contact}
              title={
                debouncedSearch
                  ? 'Nenhum resultado encontrado'
                  : delinquencyFilter
                    ? 'Nenhum cliente inadimplente'
                    : 'Nenhum cliente cadastrado'
              }
              description={
                debouncedSearch
                  ? 'Tente ajustar os termos da busca.'
                  : delinquencyFilter
                    ? 'Nenhum cliente com cobrança em atraso no momento.'
                    : 'Comece cadastrando o primeiro cliente da sua operação.'
              }
              action={
                !debouncedSearch && !delinquencyFilter ? (
                  <Can permission={Permission.CLIENT_CREATE}>
                    <ButtonLink to="/clients/create">
                      <Plus className="size-4" />
                      Novo cliente
                    </ButtonLink>
                  </Can>
                ) : undefined
              }
            />
          }
        />

        {query.data && (
          <Pagination meta={query.data.meta} onPageChange={(next) => updateParams({ page: next })} />
        )}
      </PageContent>

      <ConfirmDialog
        open={clientToDelete !== null}
        onClose={() => setClientToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteClient.isPending}
        title="Excluir cliente"
        description={
          <>
            Tem certeza que deseja excluir <strong>{clientToDelete?.name}</strong>? Esta ação não
            pode ser desfeita.
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
