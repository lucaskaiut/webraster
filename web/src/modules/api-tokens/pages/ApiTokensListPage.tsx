import { useState } from 'react'
import { KeyRound, Plus, Trash2 } from 'lucide-react'
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
import { formatDate, formatRelative } from '@/shared/utils/format'
import type { ApiToken } from '@/shared/types/models'
import { isTokenExpired, useApiTokensQuery, useRevokeApiToken } from '../hooks/useApiTokens'

export default function ApiTokensListPage() {
  const query = useApiTokensQuery()
  const revokeToken = useRevokeApiToken()
  const { can } = usePermissions()

  const [tokenToRevoke, setTokenToRevoke] = useState<ApiToken | null>(null)

  const confirmRevoke = () => {
    if (!tokenToRevoke) return

    revokeToken.mutate(tokenToRevoke.id, { onSettled: () => setTokenToRevoke(null) })
  }

  const columns: Array<Column<ApiToken>> = [
    {
      key: 'name',
      header: 'Nome',
      render: (token) => (
        <div className="flex items-center gap-3">
          <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-2 text-muted">
            <KeyRound className="size-4" aria-hidden="true" />
          </span>
          <span className="font-medium text-foreground">{token.name}</span>
        </div>
      ),
    },
    {
      key: 'last_used_at',
      header: 'Último uso',
      render: (token) => (
        <span className="text-muted">
          {token.last_used_at ? formatRelative(token.last_used_at) : 'Nunca utilizado'}
        </span>
      ),
    },
    {
      key: 'expires_at',
      header: 'Expiração',
      render: (token) => (
        <span className="text-muted">{token.expires_at ? formatDate(token.expires_at) : 'Nunca'}</span>
      ),
    },
    {
      key: 'scopes',
      header: 'Escopos',
      render: (token) =>
        token.permissions === null ? (
          <Badge variant="primary">Acesso total</Badge>
        ) : token.permissions.length === 0 ? (
          <Badge>Nenhum</Badge>
        ) : (
          <span className="text-muted">
            {token.permissions.length} {token.permissions.length === 1 ? 'escopo' : 'escopos'}
          </span>
        ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (token) =>
        isTokenExpired(token.expires_at) ? (
          <Badge variant="danger">Expirado</Badge>
        ) : (
          <Badge variant="success">Ativo</Badge>
        ),
    },
    ...(can(Permission.API_TOKEN_DELETE)
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-12 text-right',
            render: (token: ApiToken) => (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setTokenToRevoke(token)}
                aria-label={`Revogar token ${token.name}`}
                className="text-danger hover:bg-danger-soft hover:text-danger"
              >
                <Trash2 className="size-4" />
              </Button>
            ),
          } satisfies Column<ApiToken>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Tokens de API"
        description="Tokens de integração para acesso à API sem usuário."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Tokens de API' }]}
        actions={
          <Can permission={Permission.API_TOKEN_CREATE}>
            <ButtonLink to="/api-tokens/create">
              <Plus className="size-4" />
              Novo token
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <DataTable
          caption="Lista de tokens de API"
          columns={columns}
          rows={query.data ?? []}
          rowKey={(token) => token.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={KeyRound}
              title="Nenhum token criado"
              description="Crie tokens para integrar sistemas externos à sua API."
              action={
                <Can permission={Permission.API_TOKEN_CREATE}>
                  <ButtonLink to="/api-tokens/create">
                    <Plus className="size-4" />
                    Novo token
                  </ButtonLink>
                </Can>
              }
            />
          }
        />
      </PageContent>

      <ConfirmDialog
        open={tokenToRevoke !== null}
        onClose={() => setTokenToRevoke(null)}
        onConfirm={confirmRevoke}
        loading={revokeToken.isPending}
        title="Revogar token"
        description={
          <>
            Tem certeza que deseja revogar o token <strong>{tokenToRevoke?.name}</strong>? As
            integrações que o utilizam deixarão de funcionar imediatamente.
          </>
        }
        confirmLabel="Revogar"
      />
    </Page>
  )
}
