import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Alert,
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  SearchSelectField,
  Section,
  SwitchField,
  TextField,
  type SearchSelectOption,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useSessionStore } from '@/shared/stores/session.store'
import { stocksService } from '@/modules/stocks/services/stocks.service'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { EquipmentPayload } from '../services/equipments.service'
import { EQUIPMENT_MODELS } from '../lib/equipment-models'
import { searchEquipmentModels } from '../lib/model-search'
import { equipmentSchema, type EquipmentFormValues } from '../schemas/equipment.schema'

const MODEL_RESULT_LIMIT = 100

async function loadStockOptions(search: string): Promise<SearchSelectOption[]> {
  const response = await stocksService.list({
    search: search || undefined,
    per_page: 20,
    is_active: true,
  })

  return response.data.map((stock) => ({
    value: stock.id,
    label: stock.name,
    description: stock.is_active ? undefined : 'Inativo',
  }))
}

async function resolveStockLabel(value: string): Promise<SearchSelectOption | null> {
  try {
    const stock = await stocksService.get(value)

    return { value: stock.id, label: stock.name }
  } catch {
    return null
  }
}

function loadModelOptions(search: string): Promise<SearchSelectOption[]> {
  return Promise.resolve(searchEquipmentModels(search, MODEL_RESULT_LIMIT))
}

function resolveModelOption(value: string): Promise<SearchSelectOption | null> {
  const option = EQUIPMENT_MODELS.find((item) => item.value === value)

  return Promise.resolve(option ?? { value, label: value })
}

interface EquipmentFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<EquipmentFormValues>
  submitting: boolean
  /** Equipamento instalado: o estoque é controlado pelo fluxo de veículo. */
  stockLocked?: boolean
  onSubmit: (payload: EquipmentPayload) => Promise<unknown>
}

export function EquipmentForm({
  mode,
  defaultValues,
  submitting,
  stockLocked = false,
  onSubmit,
}: EquipmentFormProps) {
  const form = useForm<EquipmentFormValues>({
    resolver: zodResolver(equipmentSchema),
    defaultValues: {
      imei: '',
      model: '',
      line_number: '',
      carrier: '',
      is_active: true,
      stock_id: '',
      ...defaultValues,
    },
  })

  const serverIp = useSessionStore((state) => state.tenant?.traccar_server_ip)
  const serverDns = useSessionStore((state) => state.tenant?.traccar_server_dns)
  const hasServerInfo = Boolean(serverIp || serverDns)

  const { can } = usePermissions()
  const canViewDetails = can(Permission.EQUIPMENT_DETAILS_READ)
  const canReadStocks = can(Permission.STOCK_READ)

  const handleSubmit = async (values: EquipmentFormValues) => {
    const payload: EquipmentPayload = {
      ...(canViewDetails
        ? {
            imei: values.imei.trim(),
            model: values.model || null,
            line_number: values.line_number || null,
            carrier: values.carrier || null,
          }
        : {}),
      is_active: values.is_active,
      ...(canReadStocks && !stockLocked ? { stock_id: values.stock_id || null } : {}),
    }

    try {
      await onSubmit(payload)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Card>
      <CardContent className="space-y-5">
        {hasServerInfo && (
          <Alert variant="info" title="Servidor de rastreamento">
            <dl className="grid gap-x-6 gap-y-1 sm:grid-cols-2">
              {serverIp && (
                <div className="flex gap-1.5">
                  <dt className="font-medium">IP</dt>
                  <dd>{serverIp}</dd>
                </div>
              )}
              {serverDns && (
                <div className="flex gap-1.5">
                  <dt className="font-medium">DNS</dt>
                  <dd className="break-all">{serverDns}</dd>
                </div>
              )}
            </dl>
          </Alert>
        )}

        <Form form={form} onSubmit={handleSubmit} className="space-y-8">
          <Section title="Identificação do rastreador">
            <div className="grid gap-4 sm:grid-cols-2">
              {canViewDetails && (
                <>
                  <TextField
                    name="imei"
                    label="Número do equipamento (IMEI)"
                    required
                    className="sm:col-span-2"
                    inputMode="numeric"
                    autoComplete="off"
                    mask={(value) => value.replace(/\D/g, '').slice(0, 16)}
                  />
                  <SearchSelectField
                    name="model"
                    label="Modelo"
                    placeholder="Buscar modelo..."
                    emptyMessage="Nenhum modelo encontrado"
                    loadOptions={loadModelOptions}
                    resolveLabel={resolveModelOption}
                  />
                  <TextField name="carrier" label="Operadora" />
                  <TextField name="line_number" label="Número da Linha" className="sm:col-span-2" />
                </>
              )}
              {canReadStocks && (
                <SearchSelectField
                  name="stock_id"
                  label="Estoque"
                  className="sm:col-span-2"
                  disabled={stockLocked}
                  placeholder="Buscar estoque..."
                  emptyMessage="Nenhum estoque ativo encontrado"
                  loadOptions={loadStockOptions}
                  resolveLabel={resolveStockLabel}
                  hint={
                    stockLocked
                      ? 'Equipamento instalado. O estoque é atualizado automaticamente ao remover ou trocar no veículo.'
                      : 'Opcional. Sem estoque, o equipamento não aparece para técnicos com estoque vinculado.'
                  }
                />
              )}
              <SwitchField name="is_active" label="Equipamento ativo" />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/equipments" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar equipamento' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
