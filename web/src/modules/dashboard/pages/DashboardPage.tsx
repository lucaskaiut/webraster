import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { useSessionStore } from '@/shared/stores/session.store'

export default function DashboardPage() {
  const user = useSessionStore((state) => state.user)

  return (
    <Page>
      <PageHeader
        title={`Olá, ${user?.name.split(' ')[0] ?? ''}`}
        description="Bem-vindo ao painel."
      />
      <PageContent />
    </Page>
  )
}
