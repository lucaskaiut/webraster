import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { UserForm } from '../forms/UserForm'
import { useCreateUser } from '../hooks/useUsers'

export default function UserCreatePage() {
  const navigate = useNavigate()
  const createUser = useCreateUser()

  return (
    <Page>
      <PageHeader
        title="Novo usuário"
        description="Cadastre um novo usuário na sua organização."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Usuários', to: '/users' },
          { label: 'Novo usuário' },
        ]}
      />

      <PageContent>
        <UserForm
          mode="create"
          submitting={createUser.isPending}
          onSubmit={async (payload) => {
            await createUser.mutateAsync(payload)
            navigate('/users')
          }}
        />
      </PageContent>
    </Page>
  )
}
