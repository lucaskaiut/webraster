import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { ClipboardCheck, Pencil, Plus, Trash2 } from 'lucide-react'
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
import { useDebounce } from '@/shared/hooks/useDebounce'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useTableSort } from '@/shared/hooks/useTableSort'
import type { Checklist } from '@/shared/types/models'
import { useDeleteChecklist, useChecklistsQuery } from '../hooks/useChecklists'
import { checklistTypeBadgeVariant, checklistTypeLabel } from '../lib/labels'

const PER_PAGE = 10

export default function ChecklistsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const type = searchParams.get('type') ?? ''
  const active = searchParams.get('is_active') ?? ''

  const navigate = useNavigate()
  const { can } = usePermissions()
  const { sort, sortParam, toggleSort } = useTableSort()

  const [checklistToDelete, setChecklistToDelete] = useState<Checklist | null>(null)
  const deleteChecklist = useDeleteChecklist()

  const query = useChecklistsQuery({
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    type: type || undefined,
    is_active: active === '' ? undefined : active === '1',
    sort: sortParam,
  })

  const setFilter = (key: string, value: string) => {
    setSearchParams(
      (params) => {
        if (value) {
          params.set(key, value)
        } else {
          params.delete(key)
        }
        params.delete('page')
        return params
      },
      { replace: true },
    )
  }

  const columns: Array<Column<Checklist>> = [
    {
      key: 'name',
      header: 'Checklist',
      sortable: true,
      render: (checklist) => (
        <div className="min-w-0">
          <Link
            to={`/checklists/${checklist.id}`}
            className="block truncate font-medium text-primary hover:underline"
          >
            {checklist.name}
          </Link>
          <p className="truncate text-[13px] text-muted">
            {checklist.description ?? 'Sem descrição'}
          </p>
        </div>
      ),
    },
    {
      key: 'type',
      header: 'Tipo',
      sortable: true,
      render: (checklist) => (
        <Badge variant={checklistTypeBadgeVariant(checklist.type)}>
          {checklist.type_label ?? checklistTypeLabel(checklist.type)}
        </Badge>
      ),
    },
    {
      key: 'questions',
      header: 'Perguntas',
      render: (checklist) => (
        <span className="text-muted">
          {checklist.questions_count ?? 0}{' '}
          {checklist.questions_count === 1 ? 'pergunta' : 'perguntas'}
        </span>
      ),
    },
    {
      key: 'is_active',
      header: 'Status',
      sortable: true,
      render: (checklist) => (
        <Badge variant={checklist.is_active ? 'success' : 'neutral'}>
          {checklist.is_active ? 'Ativo' : 'Inativo'}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-24 text-right',
      render: (checklist) => (
        <div className="flex items-center justify-end gap-1">
          {can(Permission.CHECKLIST_UPDATE) && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => navigate(`/checklists/${checklist.id}/edit`)}
              aria-label={`Editar ${checklist.name}`}
            >
              <Pencil className="size-4" />
            </Button>
          )}
          {can(Permission.CHECKLIST_DELETE) && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setChecklistToDelete(checklist)}
              aria-label={`Excluir ${checklist.name}`}
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
        title="Checklists de OS"
        description="Modelos iniciais e finais utilizados durante a execução das ordens de serviço."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Checklists de OS' },
        ]}
        actions={
          <Can permission={Permission.CHECKLIST_CREATE}>
            <ButtonLink to="/checklists/create">
              <Plus className="size-4" />
              Novo checklist
            </ButtonLink>
          </Can>
        }
      />

      <PageContent variant="table">
        <FilterBar>
          <SearchInput
            placeholder="Buscar por nome ou descrição..."
            aria-label="Buscar checklists"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setFilter('search', event.target.value)
            }}
          />
          <div className="flex flex-col gap-2 sm:flex-row">
            <Select
              aria-label="Filtrar por tipo"
              className="w-40"
              value={type}
              onChange={(event) => setFilter('type', event.target.value)}
              options={[
                { value: '', label: 'Todos os tipos' },
                { value: 'initial', label: 'Inicial' },
                { value: 'final', label: 'Final' },
              ]}
            />
            <Select
              aria-label="Filtrar por status"
              className="w-40"
              value={active}
              onChange={(event) => setFilter('is_active', event.target.value)}
              options={[
                { value: '', label: 'Todos os status' },
                { value: '1', label: 'Ativos' },
                { value: '0', label: 'Inativos' },
              ]}
            />
          </div>
        </FilterBar>

        <DataTable
          fillHeight
          caption="Lista de checklists"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(checklist) => checklist.id}
          loading={query.isPending}
          sort={sort}
          onSort={toggleSort}
          emptyState={
            <EmptyState
              icon={ClipboardCheck}
              title={
                debouncedSearch ? 'Nenhum resultado encontrado' : 'Nenhum checklist cadastrado'
              }
              description={
                debouncedSearch
                  ? 'Tente ajustar os termos da busca.'
                  : 'Crie o primeiro modelo de checklist para as ordens de serviço.'
              }
              action={
                !debouncedSearch ? (
                  <Can permission={Permission.CHECKLIST_CREATE}>
                    <ButtonLink to="/checklists/create">
                      <Plus className="size-4" />
                      Novo checklist
                    </ButtonLink>
                  </Can>
                ) : undefined
              }
            />
          }
        />

        {query.data && (
          <Pagination
            meta={query.data.meta}
            onPageChange={(next) =>
              setSearchParams(
                (params) => {
                  if (next > 1) {
                    params.set('page', String(next))
                  } else {
                    params.delete('page')
                  }
                  return params
                },
                { replace: true },
              )
            }
          />
        )}
      </PageContent>

      <ConfirmDialog
        open={checklistToDelete !== null}
        onClose={() => setChecklistToDelete(null)}
        onConfirm={() => {
          if (!checklistToDelete) return
          deleteChecklist.mutate(checklistToDelete.id, {
            onSettled: () => setChecklistToDelete(null),
          })
        }}
        loading={deleteChecklist.isPending}
        title="Excluir checklist"
        description={
          <>
            Tem certeza que deseja excluir <strong>{checklistToDelete?.name}</strong>? Os
            atendimentos já preenchidos permanecem preservados.
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
