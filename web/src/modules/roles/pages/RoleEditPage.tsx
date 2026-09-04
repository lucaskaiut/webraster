import { useNavigate, useParams } from 'react-router'
import { ShieldX } from 'lucide-react'
import {
  ButtonLink,
  Card,
  CardContent,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { RoleForm } from '../forms/RoleForm'
import { useRoleQuery, useUpdateRole } from '../hooks/useRoles'

function FormSkeleton() {
  return (
    <Card>
      <CardContent className="space-y-5">
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-24 w-full" />
        <div className="grid gap-3 lg:grid-cols-2">
          <Skeleton className="h-40" />
          <Skeleton className="h-40" />
        </div>
      </CardContent>
    </Card>
  )
}

export default function RoleEditPage() {
  const { id } = useParams<{ id: string }>()
  const roleId = Number(id)
  const navigate = useNavigate()

  const query = useRoleQuery(Number.isFinite(roleId) ? roleId : undefined)
  const updateRole = useUpdateRole(roleId)

  return (
    <Page>
      <PageHeader
        title="Editar perfil"
        description={query.data ? `Atualize as permissões do perfil ${query.data.name}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Perfis de acesso', to: '/roles' },
          { label: 'Editar' },
        ]}
      />

      <PageContent>
        {query.isPending && <FormSkeleton />}

        {query.isError && (
          <Card>
            <EmptyState
              icon={ShieldX}
              title="Perfil não encontrado"
              description="O perfil pode ter sido removido ou você não possui acesso a ele."
              action={
                <ButtonLink to="/roles" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && (
          <RoleForm
            mode="edit"
            defaultValues={{
              name: query.data.name,
              description: query.data.description ?? '',
              permissions: query.data.permissions ?? [],
            }}
            submitting={updateRole.isPending}
            onSubmit={async (payload) => {
              await updateRole.mutateAsync(payload)
              navigate('/roles')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
