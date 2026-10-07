import type { ReactNode } from 'react'
import { cn } from '@/shared/utils/cn'
import { Card } from './Card'
import { Skeleton } from './Skeleton'

export interface Column<T> {
  key: string
  header: ReactNode
  render: (row: T) => ReactNode
  className?: string
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
                  {column.header}
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
