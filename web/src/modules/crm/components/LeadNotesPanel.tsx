import { FileText } from 'lucide-react'
import { Spinner } from '@/shared/design-system'

export function LeadNotesPanel({
  notes,
  loading,
  compact,
}: {
  notes?: string | null
  loading?: boolean
  compact?: boolean
}) {
  const trimmed = notes?.trim() ?? ''

  return (
    <div className={compact ? 'flex min-h-0 flex-1 flex-col' : 'flex flex-col'}>
      <div className="mb-2 flex items-center gap-2 text-sm font-medium text-foreground">
        <FileText className="size-4 text-muted" aria-hidden />
        Observações do lead
      </div>
      {loading ? (
        <div className="flex justify-center py-8">
          <Spinner />
        </div>
      ) : trimmed === '' ? (
        <p className="text-sm text-muted">Nenhuma observação registrada ainda.</p>
      ) : (
        <div
          className="min-h-0 flex-1 overflow-y-auto overscroll-contain rounded-xl bg-surface-2/80 p-3 text-sm leading-relaxed text-foreground shadow-card"
        >
          <p className="whitespace-pre-wrap">{trimmed}</p>
        </div>
      )}
    </div>
  )
}
