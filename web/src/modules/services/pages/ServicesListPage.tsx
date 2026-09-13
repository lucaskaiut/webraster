import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Pencil, Plus, Trash2, Wrench } from 'lucide-react'
import {
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
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { formatCurrency } from '@/shared/utils/format'
import type { CatalogService } from '@/shared/types/models'
import { useDeleteService, useServicesQuery } from '../hooks/useServices'

const PER_PAGE = 10

export default function ServicesListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()
  const { can } = usePermissions()

  const [serviceToDelete, setServiceToDelete] = useState<CatalogService | null>(null)
  const deleteService = useDeleteService()

  const query = useServicesQuery({
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
  })

  const updateParams = (next: { page?: number; search?: string }) => {
    setSearchParams(
      (params) => {
        if (next.search !== undefined) {
          next.search ? params.set('search', next.search) : params.delete('search')
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
    if (!serviceToDelete) return

    deleteService.mutate(serviceToDelete.id, { onSettled: () => setServiceToDelete(null) })
  }

  const canMutate = can(Permission.SERVICE_UPDATE) || can(Permission.SERVICE_DELETE)

  const columns: Array<Column<CatalogService>> = [
    {
      key: 'name',
      header: 'Serviço',
      render: (service) => <p className="truncate font-medium text-foreground">{service.name}</p>,
    },
    {
      key: 'amount',
      header: 'Valor',
      render: (service) => <span className="text-muted">{formatCurrency(service.amount)}</span>,
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (service: CatalogService) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.SERVICE_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/services/${service.id}/edit`)}
                    aria-label={`Editar ${service.name}`}
                  >
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.SERVICE_DELETE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setServiceToDelete(service)}
                    aria-label={`Excluir ${service.name}`}
                    className="text-danger hover:bg-danger-soft hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<CatalogService>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Serviços"
        description="Cadastre os serviços oferecidos e seus valores."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Serviços' }]}
        actions={
          <Can permission={Permission.SERVICE_CREATE}>
            <ButtonLink to="/services/create">
              <Plus className="size-4" />
              Novo serviço
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por nome..."
            aria-label="Buscar serviços"
            value={search}
            onChange={(event) => handleSearch(event.target.value)}
          />
        </FilterBar>

        <DataTable
          caption="Lista de serviços"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(service) => service.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Wrench}
              title={debouncedSearch ? 'Nenhum resultado encontrado' : 'Nenhum serviço cadastrado'}
              description={
                debouncedSearch
                  ? 'Tente ajustar os termos da busca.'
                  : 'Comece cadastrando o primeiro serviço.'
              }
              action={
                !debouncedSearch ? (
                  <Can permission={Permission.SERVICE_CREATE}>
                    <ButtonLink to="/services/create">
                      <Plus className="size-4" />
                      Novo serviço
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
        open={serviceToDelete !== null}
        onClose={() => setServiceToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteService.isPending}
        title="Excluir serviço"
        description={
          <>
            Tem certeza que deseja excluir <strong>{serviceToDelete?.name}</strong>? Esta ação não
            pode ser desfeita.
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
