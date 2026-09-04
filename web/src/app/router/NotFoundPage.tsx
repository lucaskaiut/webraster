import { SearchX } from 'lucide-react'
import { ButtonLink, EmptyState } from '@/shared/design-system'

export function NotFoundPage() {
  return (
    <div className="flex min-h-dvh items-center justify-center px-4">
      <EmptyState
        icon={SearchX}
        title="Página não encontrada"
        description="A página que você procura não existe ou foi movida."
        action={<ButtonLink to="/dashboard">Ir para o dashboard</ButtonLink>}
      />
    </div>
  )
}
