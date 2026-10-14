import { ChevronLeft, ChevronRight } from 'lucide-react'
import type { PaginationMeta } from '@/shared/types/api'
import { cn } from '@/shared/utils/cn'
import { Button } from './Button'
import { Select, type SelectOption } from './Select'

export interface PaginationProps {
  meta: PaginationMeta
  onPageChange: (page: number) => void
  className?: string
  /** Quantidade de registros por página (exibida junto à paginação). */
  perPage?: number | 'all'
  perPageOptions?: SelectOption[]
  onPerPageChange?: (value: number | 'all') => void
}

export function Pagination({
  meta,
  onPageChange,
  className,
  perPage,
  perPageOptions,
  onPerPageChange,
}: PaginationProps) {
  if (meta.total === 0) return null

  const showPerPage = perPage !== undefined && perPageOptions && onPerPageChange

  return (
    <nav
      aria-label="Paginação"
      className={cn('flex shrink-0 flex-wrap items-center justify-between gap-3', className)}
    >
      <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
        <p className="text-[13px] text-muted">
          Exibindo <span className="font-medium text-foreground">{meta.from ?? 0}</span>–
          <span className="font-medium text-foreground">{meta.to ?? 0}</span> de{' '}
          <span className="font-medium text-foreground">{meta.total}</span>
        </p>

        {showPerPage && (
          <div className="flex items-center gap-2 text-[13px] text-muted">
            <span className="whitespace-nowrap">Registros por página:</span>
            <Select
              aria-label="Registros por página"
              className="h-8 w-36 pl-2.5 text-xs"
              options={perPageOptions}
              value={perPage === 'all' ? 'all' : String(perPage)}
              onChange={(event) => {
                const value = event.target.value

                onPerPageChange(value === 'all' ? 'all' : Number(value) || 10)
              }}
            />
          </div>
        )}
      </div>

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
