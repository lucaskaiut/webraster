import type { ReactNode } from 'react'
import { Badge, Modal, Spinner } from '@/shared/design-system'
import { formatDateTimeWithSeconds } from '@/shared/utils/format'
import { useExternalApiRequestQuery } from '../hooks/useExternalApis'
import { StatusBadge } from './StatusBadge'

function prettyBody(body: string | null): string {
  if (!body) return '—'

  try {
    return JSON.stringify(JSON.parse(body), null, 2)
  } catch {
    return body
  }
}

function Meta({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="min-w-0">
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="mt-0.5 truncate text-sm text-foreground">{children}</dd>
    </div>
  )
}

function Payload({ title, headers, body }: { title: string; headers: Record<string, string>; body: string | null }) {
  const entries = Object.entries(headers)

  return (
    <section className="flex min-w-0 flex-col gap-2">
      <h3 className="text-sm font-semibold text-foreground">{title}</h3>
      {entries.length > 0 && (
        <details className="rounded-lg bg-surface-2 px-3 py-2 text-xs">
          <summary className="cursor-pointer text-muted">Cabeçalhos ({entries.length})</summary>
          <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 font-mono">
            {entries.map(([name, value]) => (
              <div key={name} className="contents">
                <dt className="text-muted">{name}</dt>
                <dd className="break-all text-foreground">{value}</dd>
              </div>
            ))}
          </dl>
        </details>
      )}
      <pre className="max-h-96 overflow-auto rounded-lg bg-surface-2 p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap break-all text-foreground">
        {prettyBody(body)}
      </pre>
    </section>
  )
}

export function ExternalApiRequestModal({ requestId, onClose }: { requestId: number | null; onClose: () => void }) {
  const query = useExternalApiRequestQuery(requestId)
  const request = query.data

  return (
    <Modal
      open={requestId !== null}
      onClose={onClose}
      size="xl"
      title={request ? request.service_label : 'Requisição'}
      description={request ? `${request.method} ${request.url}` : undefined}
    >
      {query.isPending || !request ? (
        <div className="flex justify-center py-10">
          <Spinner className="size-6" />
        </div>
      ) : (
        <div className="flex flex-col gap-5">
          <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <Meta label="Status">
              <StatusBadge request={request} />
            </Meta>
            <Meta label="Duração">{request.duration_ms !== null ? `${request.duration_ms} ms` : '—'}</Meta>
            <Meta label="Data/hora">{formatDateTimeWithSeconds(request.created_at)}</Meta>
            <Meta label="Provedor">
              <Badge variant="neutral">{request.provider}</Badge>
            </Meta>
            <Meta label="Origem">
              <span className="font-mono text-xs" title={request.origin ?? undefined}>{request.origin ?? '—'}</span>
            </Meta>
            <Meta label="Empresa">{request.tenant?.name ?? '—'}</Meta>
            <Meta label="Usuário">{request.user?.name ?? '—'}</Meta>
          </dl>

          {request.error && (
            <p className="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">{request.error}</p>
          )}

          <div className="grid gap-5 lg:grid-cols-2">
            <Payload title="Request" headers={request.request_headers} body={request.request_body} />
            <Payload title="Response" headers={request.response_headers} body={request.response_body} />
          </div>
        </div>
      )}
    </Modal>
  )
}
