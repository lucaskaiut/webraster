import { useNavigate, useParams } from 'react-router'
import { UserX } from 'lucide-react'
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
import { UserForm } from '../forms/UserForm'
import { useUpdateUser, useUserQuery } from '../hooks/useUsers'

function FormSkeleton() {
  return (
    <Card>
      <CardContent className="space-y-5">
        <Skeleton className="h-4 w-40" />
        <div className="grid gap-4 sm:grid-cols-2">
          <Skeleton className="h-10 sm:col-span-2" />
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
        </div>
        <div className="flex justify-end gap-2">
          <Skeleton className="h-10 w-24" />
          <Skeleton className="h-10 w-36" />
        </div>
      </CardContent>
    </Card>
  )
}

export default function UserEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useUserQuery(id)
  const updateUser = useUpdateUser(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar usuário"
        description={query.data ? `Atualize os dados de ${query.data.name}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Usuários', to: '/users' },
          { label: 'Editar' },
        ]}
      />

      <PageContent>
        {query.isPending && <FormSkeleton />}

        {query.isError && (
          <Card>
            <EmptyState
              icon={UserX}
              title="Usuário não encontrado"
              description="O usuário pode ter sido removido ou você não possui acesso a ele."
              action={
                <ButtonLink to="/users" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && (
          <UserForm
            mode="edit"
            defaultValues={{
              name: query.data.name,
              email: query.data.email,
              phone: query.data.phone ?? '',
              document: query.data.document ?? '',
              password: '',
              password_confirmation: '',
              role_ids: query.data.roles?.map((role) => role.id) ?? [],
            }}
            submitting={updateUser.isPending}
            onSubmit={async (payload) => {
              await updateUser.mutateAsync(payload)
              navigate('/users')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
