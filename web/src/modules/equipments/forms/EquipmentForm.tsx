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
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { EquipmentPayload } from '../services/equipments.service'
import { equipmentSchema, type EquipmentFormValues } from '../schemas/equipment.schema'

const EQUIPMENT_MODELS: SearchSelectOption[] = [{ value: 'E3+4G', label: 'E3+4G' }]

function loadModelOptions(search: string): Promise<SearchSelectOption[]> {
  const term = search.trim().toLowerCase()

  return Promise.resolve(
    EQUIPMENT_MODELS.filter((option) => option.label.toLowerCase().includes(term)),
  )
}

function resolveModelOption(value: string): Promise<SearchSelectOption | null> {
  return Promise.resolve(EQUIPMENT_MODELS.find((option) => option.value === value) ?? null)
}

interface EquipmentFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<EquipmentFormValues>
  submitting: boolean
  onSubmit: (payload: EquipmentPayload) => Promise<unknown>
}

export function EquipmentForm({ mode, defaultValues, submitting, onSubmit }: EquipmentFormProps) {
  const form = useForm<EquipmentFormValues>({
    resolver: zodResolver(equipmentSchema),
    defaultValues: {
      imei: '',
      model: '',
      iccid: '',
      carrier: '',
      is_active: true,
      ...defaultValues,
    },
  })

  const serverIp = useSessionStore((state) => state.tenant?.traccar_server_ip)
  const serverDns = useSessionStore((state) => state.tenant?.traccar_server_dns)
  const hasServerInfo = Boolean(serverIp || serverDns)

  const { can } = usePermissions()
  const canViewDetails = can(Permission.EQUIPMENT_DETAILS_READ)

  const handleSubmit = async (values: EquipmentFormValues) => {
    const payload: EquipmentPayload = {
      ...(canViewDetails
        ? {
            imei: values.imei,
            model: values.model || null,
            iccid: values.iccid || null,
            carrier: values.carrier || null,
          }
        : {}),
      is_active: values.is_active,
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
                  <TextField name="imei" label="IMEI" required className="sm:col-span-2" />
                  <SearchSelectField
                    name="model"
                    label="Modelo"
                    placeholder="Buscar modelo..."
                    emptyMessage="Nenhum modelo encontrado"
                    loadOptions={loadModelOptions}
                    resolveLabel={resolveModelOption}
                  />
                  <TextField name="carrier" label="Operadora" />
                  <TextField name="iccid" label="ICCID" className="sm:col-span-2" />
                </>
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
