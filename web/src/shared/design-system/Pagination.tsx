import { ChevronLeft, ChevronRight } from 'lucide-react'
import type { PaginationMeta } from '@/shared/types/api'
import { cn } from '@/shared/utils/cn'
import { Button } from './Button'

export function Pagination({
  meta,
  onPageChange,
  className,
}: {
  meta: PaginationMeta
  onPageChange: (page: number) => void
  className?: string
}) {
  if (meta.total === 0) return null

  return (
    <nav
      aria-label="Paginação"
      className={cn('flex flex-wrap items-center justify-between gap-3', className)}
    >
      <p className="text-[13px] text-muted">
        Exibindo <span className="font-medium text-foreground">{meta.from ?? 0}</span>–
        <span className="font-medium text-foreground">{meta.to ?? 0}</span> de{' '}
        <span className="font-medium text-foreground">{meta.total}</span>
      </p>

      <div className="flex items-center gap-2">
        <Button
          variant="secondary"
          size="sm"
          disabled={meta.current_page <= 1}
          onClick={() => onPageChange(meta.current_page - 1)}
          aria-label="Página anterior"
        >
          <ChevronLeft className="size-4" />
          Anterior
        </Button>
        <span className="px-1 text-[13px] text-muted">
          {meta.current_page} / {meta.last_page}
        </span>
        <Button
          variant="secondary"
          size="sm"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPageChange(meta.current_page + 1)}
          aria-label="Próxima página"
        >
          Próxima
          <ChevronRight className="size-4" />
        </Button>
      </div>
    </nav>
  )
}
