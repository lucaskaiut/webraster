import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { RoleForm } from '../forms/RoleForm'
import { useCreateRole } from '../hooks/useRoles'

export default function RoleCreatePage() {
  const navigate = useNavigate()
  const createRole = useCreateRole()

  return (
    <Page>
      <PageHeader
        title="Novo perfil"
        description="Crie um perfil de acesso e defina suas permissões."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Perfis de acesso', to: '/roles' },
          { label: 'Novo perfil' },
        ]}
      />

      <PageContent>
        <RoleForm
          mode="create"
          submitting={createRole.isPending}
          onSubmit={async (payload) => {
            await createRole.mutateAsync(payload)
            navigate('/roles')
          }}
        />
      </PageContent>
    </Page>
  )
}
