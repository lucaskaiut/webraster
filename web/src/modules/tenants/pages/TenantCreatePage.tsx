import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { ChildTenantForm } from '../forms/ChildTenantForm'
import { useCreateChildTenant } from '../hooks/useTenants'

export default function TenantCreatePage() {
  const navigate = useNavigate()
  const createChildTenant = useCreateChildTenant()

  return (
    <Page>
      <PageHeader
        title="Nova empresa"
        description="Cadastre uma empresa filha do seu grupo."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Empresas', to: '/tenants' },
          { label: 'Nova empresa' },
        ]}
      />

      <PageContent>
        <ChildTenantForm
          mode="create"
          submitting={createChildTenant.isPending}
          onSubmit={async (payload) => {
            await createChildTenant.mutateAsync(payload)
            navigate('/tenants')
          }}
        />
      </PageContent>
    </Page>
  )
}
