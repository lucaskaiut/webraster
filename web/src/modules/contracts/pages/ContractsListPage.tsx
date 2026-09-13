import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Eye, FileText, Pencil, Plus, Trash2 } from 'lucide-react'
import {
  Button,
  ButtonLink,
  ConfirmDialog,
  DataTable,
  EmptyState,
  FilterBar,
  Modal,
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
import type { Contract } from '@/shared/types/models'
import { useDeleteContract, useContractsQuery } from '../hooks/useContracts'
import {
  getFictionalContractValues,
  substituteContractVariables,
} from '../lib/variables'

const PER_PAGE = 10

export default function ContractsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()
  const { can } = usePermissions()

  const [contractToDelete, setContractToDelete] = useState<Contract | null>(null)
  const [previewContract, setPreviewContract] = useState<Contract | null>(null)
  const deleteContract = useDeleteContract()

  const query = useContractsQuery({
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
    if (!contractToDelete) return

    deleteContract.mutate(contractToDelete.id, { onSettled: () => setContractToDelete(null) })
  }

  const canMutate = can(Permission.CONTRACT_UPDATE) || can(Permission.CONTRACT_DELETE)

  const columns: Array<Column<Contract>> = [
    {
      key: 'name',
      header: 'Contrato',
      render: (contract) => <p className="truncate font-medium text-foreground">{contract.name}</p>,
    },
    {
      key: 'preview',
      header: <span className="sr-only">Pré-visualizar</span>,
      className: 'w-12 text-right',
      render: (contract) => (
        <Button
          variant="ghost"
          size="sm"
          onClick={() => setPreviewContract(contract)}
          aria-label={`Pré-visualizar ${contract.name}`}
        >
          <Eye className="size-4" />
        </Button>
      ),
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (contract: Contract) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.CONTRACT_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/contracts/${contract.id}/edit`)}
                    aria-label={`Editar ${contract.name}`}
                  >
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.CONTRACT_DELETE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setContractToDelete(contract)}
                    aria-label={`Excluir ${contract.name}`}
                    className="text-danger hover:bg-danger-soft hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<Contract>,
        ]
      : []),
  ]

  const previewHtml = previewContract
    ? substituteContractVariables(previewContract.body, getFictionalContractValues())
    : ''

  return (
    <Page>
      <PageHeader
        title="Contratos"
        description="Modele os contratos que os clientes poderão assinar."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Contratos' }]}
        actions={
          <Can permission={Permission.CONTRACT_CREATE}>
            <ButtonLink to="/contracts/create">
              <Plus className="size-4" />
              Novo contrato
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por nome..."
            aria-label="Buscar contratos"
            value={search}
            onChange={(event) => handleSearch(event.target.value)}
          />
        </FilterBar>

        <DataTable
          caption="Lista de contratos"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(contract) => contract.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={FileText}
              title={debouncedSearch ? 'Nenhum resultado encontrado' : 'Nenhum contrato cadastrado'}
              description={
                debouncedSearch
                  ? 'Tente ajustar os termos da busca.'
                  : 'Comece cadastrando o primeiro modelo de contrato.'
              }
              action={
                !debouncedSearch ? (
                  <Can permission={Permission.CONTRACT_CREATE}>
                    <ButtonLink to="/contracts/create">
                      <Plus className="size-4" />
                      Novo contrato
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
        open={contractToDelete !== null}
        onClose={() => setContractToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteContract.isPending}
        title="Excluir contrato"
        description={
          <>
            Tem certeza que deseja excluir <strong>{contractToDelete?.name}</strong>? Esta ação não
            pode ser desfeita.
          </>
        }
        confirmLabel="Excluir"
      />

      <Modal
        open={previewContract !== null}
        onClose={() => setPreviewContract(null)}
        title={previewContract?.name ?? 'Pré-visualização'}
        description="Variáveis substituídas por dados fictícios de exemplo."
        size="xl"
        footer={
          <Button type="button" variant="secondary" onClick={() => setPreviewContract(null)}>
            Fechar
          </Button>
        }
      >
        <div
          className="prose prose-sm max-w-none text-foreground"
          dangerouslySetInnerHTML={{ __html: previewHtml || '<p><em>Sem conteúdo.</em></p>' }}
        />
      </Modal>
    </Page>
  )
}
