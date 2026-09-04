import { useState } from 'react'
import { useNavigate } from 'react-router'
import { Copy, Receipt } from 'lucide-react'
import {
  Badge,
  Button,
  DataTable,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  type Column,
} from '@/shared/design-system'
import { formatCurrency, formatDateTime } from '@/shared/utils/format'
import { toast } from '@/shared/stores/toast.store'
import type { Invoice } from '@/shared/types/models'
import { useInvoicesQuery } from '../hooks/useBilling'

const statusVariant: Record<
  Invoice['status'],
  'success' | 'warning' | 'danger' | 'primary' | undefined
> = {
  PENDING: 'warning',
  PROCESSING: 'primary',
  PAID: 'success',
  EXPIRED: 'danger',
  FAILED: 'danger',
  CANCELLED: undefined,
}

function canPay(invoice: Invoice): boolean {
  return (
    (invoice.status === 'PENDING' || invoice.status === 'PROCESSING') &&
    (invoice.awaiting_payment_method === true || (!invoice.external_id && !invoice.pix_code))
  )
}

export default function InvoicesListPage() {
  const navigate = useNavigate()
  const query = useInvoicesQuery()
  const [selected, setSelected] = useState<Invoice | null>(null)

  const copyPix = async (code: string) => {
    await navigator.clipboard.writeText(code)
    toast.success('PIX copiado', 'Cole no app do seu banco para pagar.')
  }

  const columns: Array<Column<Invoice>> = [
    {
      key: 'amount',
      header: 'Valor',
      render: (invoice) => (
        <button
          type="button"
          className="font-medium text-foreground hover:underline"
          onClick={() => setSelected(invoice)}
        >
          {formatCurrency(invoice.amount)}
        </button>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (invoice) => (
        <Badge variant={statusVariant[invoice.status]}>
          {canPay(invoice) ? 'Aguardando método' : invoice.status}
        </Badge>
      ),
    },
    {
      key: 'method',
      header: 'Método',
      render: (invoice) => invoice.payment_method?.toUpperCase() ?? '—',
    },
    {
      key: 'due_date',
      header: 'Vencimento',
      render: (invoice) => formatDateTime(invoice.due_date),
    },
    {
      key: 'paid_at',
      header: 'Pago em',
      render: (invoice) => formatDateTime(invoice.paid_at),
    },
    {
      key: 'actions',
      header: '',
      render: (invoice) =>
        canPay(invoice) ? (
          <Button
            size="sm"
            onClick={() => navigate(`/pagamento?invoice=${invoice.id}`)}
          >
            Pagar
          </Button>
        ) : invoice.pix_code ? (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => copyPix(invoice.pix_code!)}
            aria-label="Copiar código PIX"
          >
            <Copy className="size-4" />
          </Button>
        ) : invoice.status === 'PENDING' && invoice.pix_code === null ? (
          <Button
            variant="secondary"
            size="sm"
            onClick={() => navigate(`/pagamento?invoice=${invoice.id}`)}
          >
            Ver
          </Button>
        ) : (
          '—'
        ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Cobranças"
        description="Faturas geradas, escolha do método de pagamento e status."
      />

      <PageContent className="space-y-6">
        <DataTable
          columns={columns}
          rows={query.data ?? []}
          rowKey={(invoice) => invoice.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Receipt}
              title="Nenhuma cobrança"
              description="As faturas aparecerão aqui quando a cobrança for gerada."
            />
          }
        />

        {selected && (
          <div className="rounded-xl bg-surface p-4 shadow-card">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="font-semibold text-foreground">
                  Cobrança {formatCurrency(selected.amount)}
                </h3>
                <p className="text-sm text-muted">
                  Status {selected.status} · Vencimento {formatDateTime(selected.due_date)}
                </p>
              </div>
              <div className="flex gap-2">
                {canPay(selected) && (
                  <Button
                    size="sm"
                    onClick={() => navigate(`/pagamento?invoice=${selected.id}`)}
                  >
                    Pagar
                  </Button>
                )}
                <Button variant="ghost" size="sm" onClick={() => setSelected(null)}>
                  Fechar
                </Button>
              </div>
            </div>

            {selected.pix_qrcode && (
              <img
                src={selected.pix_qrcode}
                alt="QR Code PIX"
                className="mt-4 size-40 rounded-lg bg-white p-2 shadow-card"
              />
            )}

            {selected.pix_code && (
              <div className="mt-4 space-y-2">
                <p className="text-xs font-medium tracking-wide text-muted uppercase">
                  PIX copia e cola
                </p>
                <div className="flex gap-2">
                  <code className="block flex-1 overflow-x-auto rounded-lg bg-surface-2 px-3 py-2 text-xs">
                    {selected.pix_code}
                  </code>
                  <Button variant="secondary" onClick={() => copyPix(selected.pix_code!)}>
                    <Copy className="size-4" />
                    Copiar
                  </Button>
                </div>
              </div>
            )}
          </div>
        )}
      </PageContent>
    </Page>
  )
}
