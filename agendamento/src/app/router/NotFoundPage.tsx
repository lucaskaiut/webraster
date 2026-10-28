import { SearchX } from 'lucide-react'
import { ButtonLink, EmptyState } from '@/shared/design-system'
import { HOME_PATH } from '@/shared/constants/routes'

export function NotFoundPage() {
  return (
    <div className="flex h-dvh items-center justify-center overflow-hidden px-4">
      <EmptyState
        icon={SearchX}
        title="Página não encontrada"
        description="A página que você procura não existe ou foi movida."
        action={<ButtonLink to={HOME_PATH}>Ir para o início</ButtonLink>}
      />
    </div>
  )
}
