import type { ReactNode } from 'react'
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import type { SortCriterion } from '@/shared/utils/table-sort'
import { Card } from './Card'
import { Skeleton } from './Skeleton'

export interface Column<T> {
  key: string
  header: ReactNode
  render: (row: T) => ReactNode
  className?: string
  /** Habilita o botão de ordenação no cabeçalho da coluna. */
  sortable?: boolean
  /** Nome da coluna enviado à API (padrão: `key`). */
  sortKey?: string
}

export interface DataTableProps<T> {
  columns: Array<Column<T>>
  rows: T[]
  rowKey: (row: T) => string | number
  loading?: boolean
  skeletonRows?: number
  emptyState?: ReactNode
  caption?: string
  onRowClick?: (row: T) => void
  /** Preenche a altura disponível no layout de listagem e rola o corpo da tabela. */
  fillHeight?: boolean
  className?: string
  /** Critérios de ordenação ativos (a ordem define a prioridade). */
  sort?: SortCriterion[]
  /** Chamado ao clicar no cabeçalho de uma coluna ordenável. */
  onSort?: (column: string) => void
}

function SortableHeader<T>({
  column,
  sort,
  onSort,
}: {
  column: Column<T>
  sort: SortCriterion[]
  onSort: (column: string) => void
}) {
  const sortKey = column.sortKey ?? column.key
  const index = sort.findIndex((criterion) => criterion.column === sortKey)
  const direction = index >= 0 ? sort[index]!.direction : null
  const multiple = sort.length > 1

  const title =
    direction === 'asc'
      ? 'Ordenação crescente (clique para decrescente)'
      : direction === 'desc'
        ? 'Ordenação decrescente (clique para remover)'
        : 'Clique para ordenar'

  return (
    <button
      type="button"
      onClick={() => onSort(sortKey)}
      title={title}
      aria-label={typeof column.header === 'string' ? `Ordenar por ${column.header}` : undefined}
      className={cn(
        'group/sort -mx-1 inline-flex cursor-pointer items-center gap-1.5 rounded px-1 py-0.5 transition-colors hover:text-foreground',
        direction !== null && 'text-foreground',
      )}
    >
      <span>{column.header}</span>
      <span className="inline-flex shrink-0 items-center gap-0.5">
        {direction === 'asc' ? (
          <ArrowUp className="size-3.5 text-primary" aria-hidden="true" />
        ) : direction === 'desc' ? (
          <ArrowDown className="size-3.5 text-primary" aria-hidden="true" />
        ) : (
          <ArrowUpDown className="size-3.5 text-subtle/50 group-hover/sort:text-subtle" aria-hidden="true" />
        )}
        {multiple && direction !== null && (
          <span className="text-[9px] leading-none font-normal text-subtle">{index + 1}</span>
        )}
      </span>
    </button>
  )
}

export function DataTable<T>({
  columns,
  rows,
  rowKey,
  loading = false,
  skeletonRows = 5,
  emptyState,
  caption,
  onRowClick,
  fillHeight = false,
  className,
  sort = [],
  onSort,
}: DataTableProps<T>) {
  const showEmpty = !loading && rows.length === 0

  return (
    <Card
      data-slot="data-table-root"
      {...(fillHeight ? { 'data-fill-height': 'true' as const } : {})}
      className={cn('overflow-hidden', fillHeight && 'flex min-h-0 flex-col', className)}
    >
      <div
        className={cn(
          'overflow-x-auto',
          fillHeight && 'min-h-0 flex-1 overflow-y-auto overscroll-contain',
        )}
      >
        <table className="min-w-full text-sm">
          {caption && <caption className="sr-only">{caption}</caption>}
          <thead className={cn(fillHeight && 'sticky top-0 z-10')}>
            <tr className="bg-surface-2/95 text-left text-xs tracking-wide text-muted uppercase backdrop-blur-sm">
              {columns.map((column) => (
                <th key={column.key} scope="col" className={cn('px-5 py-3 font-medium', column.className)}>
                  {column.sortable && onSort ? (
                    <SortableHeader column={column} sort={sort} onSort={onSort} />
                  ) : (
                    column.header
                  )}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {loading
              ? Array.from({ length: skeletonRows }).map((_, index) => (
                  <tr key={index} className="shadow-[inset_0_1px_0_var(--app-surface-2)]">
                    {columns.map((column) => (
                      <td key={column.key} className="px-5 py-4">
                        <Skeleton className="h-4 w-full max-w-40" />
                      </td>
                    ))}
                  </tr>
                ))
              : rows.map((row) => (
                  <tr
                    key={rowKey(row)}
                    className={cn(
                      'shadow-[inset_0_1px_0_var(--app-surface-2)] transition-colors hover:bg-surface-2/40',
                      onRowClick && 'cursor-pointer',
                    )}
                    onClick={onRowClick ? () => onRowClick(row) : undefined}
                  >
                    {columns.map((column) => (
                      <td key={column.key} className={cn('px-5 py-3.5 align-middle', column.className)}>
                        {column.render(row)}
                      </td>
                    ))}
                  </tr>
                ))}
          </tbody>
        </table>
      </div>
      {showEmpty && emptyState}
    </Card>
  )
}
