import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Building2, Pencil, Plus } from 'lucide-react'
import {
  Button,
  ButtonLink,
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
import { useDebounce } from '@/shared/hooks/useDebounce'
import { useIsUmbrellaTenant } from '@/shared/hooks/useIsUmbrellaTenant'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useSessionStore } from '@/shared/stores/session.store'
import { formatDate } from '@/shared/utils/format'
import { formatDocument } from '@/shared/utils/document'
import type { Tenant } from '@/shared/types/models'
import { useTenantChildrenQuery } from '../hooks/useTenants'

const PER_PAGE = 10

export default function TenantsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const navigate = useNavigate()
  const { can } = usePermissions()

  const query = useTenantChildrenQuery({ page, per_page: PER_PAGE, search: debouncedSearch || undefined })

  const isMaster = useSessionStore((state) => state.isMaster)
  const isUmbrella = useIsUmbrellaTenant()
  const showCreateButton = isMaster && isUmbrella
  const canUpdate = can(Permission.TENANT_UPDATE) && isMaster && isUmbrella

  const createButton = (
    <ButtonLink to="/tenants/create">
      <Plus className="size-4" />
      Nova empresa
    </ButtonLink>
  )

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

  const columns: Array<Column<Tenant>> = [
    {
      key: 'name',
      header: 'Empresa',
      render: (tenant) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{tenant.name}</p>
          <p className="truncate text-[13px] text-muted">{tenant.domain}</p>
        </div>
      ),
    },
    {
      key: 'document',
      header: 'Documento',
      render: (tenant) => <span className="text-muted">{formatDocument(tenant.document)}</span>,
    },
    {
      key: 'email',
      header: 'E-mail',
      render: (tenant) => <span className="text-muted">{tenant.email}</span>,
    },
    {
      key: 'users_count',
      header: 'Usuários',
      render: (tenant) => <span className="text-muted">{tenant.users_count ?? 0}</span>,
    },
    {
      key: 'created_at',
      header: 'Criada em',
      render: (tenant) => <span className="text-muted">{formatDate(tenant.created_at)}</span>,
    },
    ...(canUpdate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-16 text-right',
            render: (tenant: Tenant) => (
              <div className="flex items-center justify-end">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => navigate(`/tenants/${tenant.id}/edit`)}
                  aria-label={`Editar ${tenant.name}`}
                >
                  <Pencil className="size-4" />
                </Button>
              </div>
            ),
          } satisfies Column<Tenant>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Empresas"
        description="Gerencie as empresas do seu grupo."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Empresas' }]}
        actions={showCreateButton ? createButton : undefined}
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por nome, domínio ou e-mail..."
            aria-label="Buscar empresas"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              updateParams({ search: event.target.value })
            }}
          />
        </FilterBar>

        <DataTable
          caption="Lista de empresas"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(tenant) => tenant.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Building2}
              title={debouncedSearch ? 'Nenhum resultado encontrado' : 'Nenhuma empresa cadastrada'}
              description={
                debouncedSearch
                  ? 'Tente ajustar os termos da busca.'
                  : 'Crie a primeira empresa do seu grupo.'
              }
              action={
                !debouncedSearch && showCreateButton ? (
                  <Can permission={Permission.TENANT_CREATE}>{createButton}</Can>
                ) : undefined
              }
            />
          }
        />

        {query.data && (
          <Pagination meta={query.data.meta} onPageChange={(next) => updateParams({ page: next })} />
        )}
      </PageContent>
    </Page>
  )
}
