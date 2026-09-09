import { useMemo, useState } from 'react'
import { useSearchParams } from 'react-router'
import { BarChart3 } from 'lucide-react'
import {
  DataTable,
  EmptyState,
  FilterBar,
  Page,
  PageContent,
  PageHeader,
  Select,
  type Column,
} from '@/shared/design-system'
import { formatCurrency, formatDate, formatDateTime } from '@/shared/utils/format'
import { useFinanceReportsQuery } from '../hooks/useFinance'
import {
  REPORT_TYPE_OPTIONS,
  paymentMethodLabel,
  periodicityLabel,
  receivableStatusLabel,
  subscriptionStatusLabel,
} from '../lib/labels'
import type { FinanceReportType } from '@/shared/types/models'

function cellValue(row: Record<string, unknown>, key: string): string {
  const value = row[key]
  if (value == null || value === '') return '—'
  if (typeof value === 'number' && key.includes('cents')) {
    return formatCurrency(value / 100)
  }
  if (typeof value === 'string' && (key.endsWith('_at') || key === 'suspended_since')) {
    return key === 'due_at' || key === 'next_billing_at' ? formatDate(value) : formatDateTime(value)
  }
  if (key === 'status' && typeof value === 'string') {
    return receivableStatusLabel(value) !== value
      ? receivableStatusLabel(value)
      : subscriptionStatusLabel(value)
  }
  if (key === 'payment_method' && typeof value === 'string') {
    return paymentMethodLabel(value)
  }
  if (key === 'periodicity' && typeof value === 'string') {
    return periodicityLabel(value)
  }
  return String(value)
}

const COLUMNS_BY_TYPE: Record<FinanceReportType, Array<{ key: string; header: string }>> = {
  receivables: [
    { key: 'code', header: 'Código' },
    { key: 'client', header: 'Cliente' },
    { key: 'status', header: 'Status' },
    { key: 'amount_cents', header: 'Valor' },
    { key: 'due_at', header: 'Vencimento' },
    { key: 'paid_at', header: 'Pago em' },
  ],
  delinquency: [
    { key: 'code', header: 'Código' },
    { key: 'client', header: 'Cliente' },
    { key: 'amount_cents', header: 'Valor' },
    { key: 'due_at', header: 'Vencimento' },
    { key: 'days_overdue', header: 'Dias atraso' },
  ],
  receipts: [
    { key: 'code', header: 'Código' },
    { key: 'client', header: 'Cliente' },
    { key: 'paid_amount_cents', header: 'Valor pago' },
    { key: 'payment_method', header: 'Método' },
    { key: 'paid_at', header: 'Pago em' },
  ],
  subscriptions: [
    { key: 'client', header: 'Cliente' },
    { key: 'contract_code', header: 'Contrato' },
    { key: 'status', header: 'Status' },
    { key: 'periodicity', header: 'Periodicidade' },
    { key: 'next_billing_at', header: 'Próxima cobrança' },
  ],
  blocked_clients: [
    { key: 'client', header: 'Cliente' },
    { key: 'devices_suspended', header: 'Dispositivos' },
    { key: 'suspended_since', header: 'Suspenso desde' },
  ],
}

export default function FinanceReportsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const type = (searchParams.get('type') ?? 'receivables') as FinanceReportType
  const [from, setFrom] = useState(searchParams.get('from') ?? '')
  const [to, setTo] = useState(searchParams.get('to') ?? '')

  const query = useFinanceReportsQuery({
    type,
    from: from || undefined,
    to: to || undefined,
  })

  const columnDefs = COLUMNS_BY_TYPE[type] ?? COLUMNS_BY_TYPE.receivables

  const columns: Array<Column<Record<string, unknown>>> = useMemo(
    () =>
      columnDefs.map((col) => ({
        key: col.key,
        header: col.header,
        render: (row: Record<string, unknown>) => cellValue(row, col.key),
      })),
    [columnDefs],
  )

  const rows = (query.data?.rows ?? []) as Array<Record<string, unknown>>

  return (
    <Page>
      <PageHeader
        title="Relatórios financeiros"
        description="Consultas de cobranças, inadimplência, recebimentos e bloqueios."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Financeiro' },
          { label: 'Relatórios' },
        ]}
      />
      <PageContent>
        <FilterBar>
          <Select
            aria-label="Tipo de relatório"
            className="w-56"
            value={type}
            onChange={(event) => {
              setSearchParams(
                (params) => {
                  params.set('type', event.target.value)
                  return params
                },
                { replace: true },
              )
            }}
            options={REPORT_TYPE_OPTIONS}
          />
          <input
            type="date"
            aria-label="De"
            className="h-10 rounded-md border border-border bg-surface px-3 text-sm"
            value={from}
            onChange={(event) => {
              setFrom(event.target.value)
              setSearchParams(
                (params) => {
                  event.target.value
                    ? params.set('from', event.target.value)
                    : params.delete('from')
                  return params
                },
                { replace: true },
              )
            }}
          />
          <input
            type="date"
            aria-label="Até"
            className="h-10 rounded-md border border-border bg-surface px-3 text-sm"
            value={to}
            onChange={(event) => {
              setTo(event.target.value)
              setSearchParams(
                (params) => {
                  event.target.value ? params.set('to', event.target.value) : params.delete('to')
                  return params
                },
                { replace: true },
              )
            }}
          />
        </FilterBar>

        <DataTable
          caption="Relatório financeiro"
          columns={columns}
          rows={rows}
          rowKey={(row) => String(row.id ?? row.client_id ?? row.code ?? JSON.stringify(row))}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={BarChart3}
              title="Sem dados"
              description="Não há registros para os filtros selecionados."
            />
          }
        />
      </PageContent>
    </Page>
  )
}
