import { useEffect, type MouseEvent } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  CardHeader,
  FileField,
  Form,
  SearchSelectField,
  SelectField,
  Skeleton,
  SwitchField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useActiveTenant } from '@/shared/brand/useActiveTenant'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import {
  defaultVehicleAlertConfigs,
  type VehicleAlertConfigValue,
} from '@/modules/alerts/lib/alert-types'
import { useVehicleAlertDefaultsQuery } from '@/modules/alerts/hooks/useAlerts'
import { clientsService } from '@/modules/clients/services/clients.service'
import type { VehiclePayload } from '../services/vehicles.service'
import { VehicleAlertConfigsCard } from '../components/VehicleAlertConfigsCard'
import { usePlateLookup } from '../hooks/usePlateLookup'
import { fillVehicleFromLookup } from '../utils/plate-lookup'
import {
  transmissionOptions,
  vehicleSchema,
  type VehicleFormValues,
} from '../schemas/vehicle.schema'
import { DEFAULT_VEHICLE_TYPE, vehicleTypeOptions } from '../lib/vehicle-types'
import { loadAvailableEquipments } from '../lib/load-available-equipments'

interface VehicleFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<VehicleFormValues>
  submitting: boolean
  crlvFileUrl?: string | null
  onSubmit: (payload: VehiclePayload) => Promise<unknown>
  /** Fixa o cliente no contexto (oculta o seletor) — ex.: cadastro dentro do cliente. */
  fixedClientId?: string
  /** Exibe a navegação lateral entre seções (padrão: true). */
  showNav?: boolean
  /** Exibe o link "Cancelar" (padrão: true). */
  showCancel?: boolean
  /** Rótulo do botão de envio. */
  submitLabel?: string
  /** Reseta o formulário após um envio bem-sucedido. */
  resetOnSuccess?: boolean
}

interface FormSection {
  id: string
  label: string
}

const SECTIONS: FormSection[] = [
  { id: 'veiculo-identificacao', label: 'Identificação' },
  { id: 'veiculo-dados', label: 'Dados do veículo' },
  { id: 'veiculo-rastreamento', label: 'Rastreamento' },
  { id: 'veiculo-consumo', label: 'Consumo e tanque' },
  { id: 'veiculo-fipe', label: 'Tabela FIPE' },
  { id: 'veiculo-documentos', label: 'Documentos' },
]

const ALERT_SECTION: FormSection = { id: 'veiculo-alertas', label: 'Alertas' }

async function loadClientOptions(search: string) {
  const response = await clientsService.list({ search: search || undefined, per_page: 20 })

  return response.data.map((client) => ({
    value: client.id,
    label: client.name,
  }))
}

async function resolveClientLabel(value: string) {
  try {
    const client = await clientsService.get(value)

    return { value: client.id, label: client.name }
  } catch {
    return null
  }
}

function VehicleFormNav({ sections }: { sections: FormSection[] }) {
  const handleClick = (event: MouseEvent<HTMLAnchorElement>, id: string) => {
    event.preventDefault()
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  return (
    <nav aria-label="Seções do formulário" className="hidden lg:block">
      <ul className="sticky top-20 space-y-1">
        {sections.map((section) => (
          <li key={section.id}>
            <a
              href={`#${section.id}`}
              onClick={(event) => handleClick(event, section.id)}
              className="block rounded-lg px-3 py-2 text-sm text-muted transition-colors hover:bg-surface-2 hover:text-foreground"
            >
              {section.label}
            </a>
          </li>
        ))}
      </ul>
    </nav>
  )
}

function VehicleFormSkeleton({ showNav }: { showNav: boolean }) {
  const content = (
    <Card>
      <CardContent className="space-y-5">
        <Skeleton className="h-4 w-40" />
        <div className="grid gap-4 sm:grid-cols-2">
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
          <Skeleton className="h-10 sm:col-span-2" />
          <Skeleton className="h-10 sm:col-span-2" />
        </div>
      </CardContent>
    </Card>
  )

  if (!showNav) return content

  return (
    <div className="grid gap-6 lg:grid-cols-[200px_minmax(0,1fr)]">
      <div className="hidden lg:block" />
      {content}
    </div>
  )
}

/**
 * Resolve os padrões de alerta da empresa ativa antes de montar o formulário,
 * para que o cadastro de um veículo novo já comece com o estado configurado.
 */
export function VehicleForm(props: VehicleFormProps) {
  const { can } = usePermissions()
  const activeTenant = useActiveTenant()
  const needsTenantDefaults =
    props.mode === 'create' &&
    can(Permission.ALERT_CONFIG_UPDATE) &&
    props.defaultValues?.alert_configs === undefined

  const defaultsQuery = useVehicleAlertDefaultsQuery(activeTenant?.id, needsTenantDefaults)

  // `isFetching` evita travar no skeleton quando a query está desabilitada
  // (ex.: sem tenant ativo), caso em que o status também fica pendente.
  if (needsTenantDefaults && defaultsQuery.isPending && defaultsQuery.isFetching) {
    return <VehicleFormSkeleton showNav={props.showNav ?? true} />
  }

  return <VehicleFormFields {...props} tenantAlertConfigs={defaultsQuery.data} />
}

function VehicleFormFields({
  mode,
  defaultValues,
  submitting,
  crlvFileUrl,
  onSubmit,
  fixedClientId,
  showNav = true,
  showCancel = true,
  submitLabel,
  resetOnSuccess = false,
  tenantAlertConfigs,
}: VehicleFormProps & { tenantAlertConfigs?: VehicleAlertConfigValue[] }) {
  const { can } = usePermissions()
  const canConfigureAlerts = can(Permission.ALERT_CONFIG_UPDATE)
  const canAssignEquipment = mode === 'create' && can(Permission.VEHICLE_CREATE)
  const canViewEquipmentDetails = can(Permission.EQUIPMENT_DETAILS_READ)
  const sections = canConfigureAlerts ? [...SECTIONS, ALERT_SECTION] : SECTIONS

  const form = useForm<VehicleFormValues>({
    resolver: zodResolver(vehicleSchema),
    defaultValues: {
      client_id: fixedClientId ?? '',
      plate: '',
      chassis: '',
      renavam: '',
      brand: '',
      model: '',
      color: '',
      year: '',
      vehicle_type: String(DEFAULT_VEHICLE_TYPE),
      transmission: '',
      odometer: '',
      max_speed_kmh: '',
      speed_hysteresis_percent: '3',
      speed_min_duration_seconds: '30',
      average_consumption: '',
      tank_capacity: '',
      crlv_file: '',
      fipe_code: '',
      fipe_model_year: '',
      fipe_fuel: '',
      fipe_reference_month: '',
      fipe_value: '',
      fipe_model: '',
      fipe_brand: '',
      fipe_score: '',
      is_active: true,
      equipment_id: '',
      equipment_notes: '',
      ...defaultValues,
      alert_configs:
        defaultValues?.alert_configs ?? tenantAlertConfigs ?? defaultVehicleAlertConfigs(),
      ...(fixedClientId ? { client_id: fixedClientId } : {}),
    },
  })

  // Sincroniza os padrões de alerta da empresa quando a consulta é atualizada
  // (ex.: logo após salvar as configurações), sem descartar ajustes já feitos
  // pelo operador no veículo.
  useEffect(() => {
    if (!tenantAlertConfigs) return
    if (form.getFieldState('alert_configs').isDirty) return

    form.reset(
      { ...form.getValues(), alert_configs: tenantAlertConfigs },
      { keepDefaultValues: false },
    )
  }, [tenantAlertConfigs, form])

  const {
    hint: plateHint,
    loading: plateLoading,
    lookup: lookupPlate,
  } = usePlateLookup((data) => fillVehicleFromLookup(form, data))

  const handleSubmit = async (values: VehicleFormValues) => {
    const payload: VehiclePayload = {
      client_id: values.client_id,
      plate: values.plate,
      chassis: values.chassis || null,
      renavam: values.renavam || null,
      brand: values.brand || null,
      model: values.model || null,
      color: values.color || null,
      year: values.year ? Number(values.year) : null,
      vehicle_type: values.vehicle_type ? Number(values.vehicle_type) : null,
      transmission: values.transmission || null,
      odometer: values.odometer ? Number(values.odometer) : null,
      max_speed_kmh: values.max_speed_kmh ? Number(values.max_speed_kmh) : null,
      speed_hysteresis_percent: values.speed_hysteresis_percent
        ? Number(values.speed_hysteresis_percent)
        : null,
      speed_min_duration_seconds: values.speed_min_duration_seconds
        ? Number(values.speed_min_duration_seconds)
        : null,
      average_consumption: values.average_consumption
        ? Number(values.average_consumption)
        : null,
      tank_capacity: values.tank_capacity ? Number(values.tank_capacity) : null,
      crlv_file: values.crlv_file || null,
      fipe_code: values.fipe_code || null,
      fipe_model_year: values.fipe_model_year || null,
      fipe_fuel: values.fipe_fuel || null,
      fipe_reference_month: values.fipe_reference_month || null,
      fipe_value: values.fipe_value || null,
      fipe_model: values.fipe_model || null,
      fipe_brand: values.fipe_brand || null,
      fipe_score: values.fipe_score ? Number(values.fipe_score) : null,
      is_active: values.is_active,
      ...(canConfigureAlerts
        ? {
            alert_configs: values.alert_configs.map(
              ({
                type,
                alarm_code,
                is_enabled,
                notify_in_app,
                notify_monitoring,
                notify_push,
                notify_email,
              }) => ({
                type,
                alarm_code,
                is_enabled,
                notify_in_app,
                notify_monitoring,
                notify_push,
                notify_email,
              }),
            ),
          }
        : {}),
      ...(mode === 'create' && values.equipment_id
        ? {
            equipment_id: values.equipment_id,
            equipment_notes: values.equipment_notes || null,
          }
        : {}),
    }

    try {
      await onSubmit(payload)
      if (resetOnSuccess) {
        form.reset({
          ...form.getValues(),
          plate: '',
          chassis: '',
          renavam: '',
          brand: '',
          model: '',
          color: '',
          year: '',
          equipment_id: '',
          equipment_notes: '',
          ...(fixedClientId ? { client_id: fixedClientId } : { client_id: '' }),
        })
      }
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <div className={showNav ? 'grid gap-6 lg:grid-cols-[200px_minmax(0,1fr)]' : undefined}>
      {showNav && <VehicleFormNav sections={sections} />}

      <Form form={form} onSubmit={handleSubmit} className="space-y-5">
        <Card id="veiculo-identificacao" className="scroll-mt-24">
          <CardHeader
            title="Identificação"
            description={
              fixedClientId
                ? 'Informe os dados de identificação do veículo.'
                : 'Vincule o cliente e informe os dados de identificação do veículo.'
            }
          />
          <CardContent className="grid gap-5 sm:grid-cols-2">
            {!fixedClientId && (
              <SearchSelectField
                name="client_id"
                label="Cliente"
                required
                className="sm:col-span-2"
                placeholder="Buscar cliente..."
                emptyMessage="Nenhum cliente encontrado"
                loadOptions={loadClientOptions}
                resolveLabel={resolveClientLabel}
              />
            )}
            <TextField
              name="plate"
              label="Placa"
              required
              placeholder="ABC1D23"
              hint={plateHint}
              loading={plateLoading}
              onBlur={() => lookupPlate(form.getValues('plate'))}
            />
            <TextField name="renavam" label="RENAVAM" />
            <TextField name="chassis" label="Chassi" className="sm:col-span-2" loading={plateLoading} />
            <div className="rounded-xl bg-surface-2 px-4 py-3 sm:col-span-2">
              <SwitchField
                name="is_active"
                label="Veículo ativo"
                hint="Veículos inativos não aparecem no monitoramento."
              />
            </div>
          </CardContent>
        </Card>

        <Card id="veiculo-dados" className="scroll-mt-24">
          <CardHeader
            title="Dados do veículo"
            description="Características e classificação usadas em relatórios e no mapa."
          />
          <CardContent className="grid gap-5 sm:grid-cols-2">
            <TextField name="brand" label="Marca" loading={plateLoading} />
            <TextField name="model" label="Modelo" loading={plateLoading} />
            <TextField name="color" label="Cor" loading={plateLoading} />
            <TextField name="year" label="Ano" type="number" placeholder="2024" loading={plateLoading} />
            <SelectField
              name="vehicle_type"
              label="Tipo de veículo"
              required
              placeholder="Selecione"
              options={vehicleTypeOptions}
              loading={plateLoading}
            />
            <SelectField
              name="transmission"
              label="Transmissão"
              placeholder="Selecione"
              options={[...transmissionOptions]}
              loading={plateLoading}
            />
          </CardContent>
        </Card>

        <Card id="veiculo-rastreamento" className="scroll-mt-24">
          <CardHeader
            title="Rastreamento"
            description={
              canAssignEquipment
                ? 'Parâmetros de velocidade e, opcionalmente, o rastreador instalado no veículo.'
                : 'Parâmetros usados para detectar excessos de velocidade.'
            }
          />
          <CardContent className="grid gap-5 sm:grid-cols-2">
            <TextField
              name="max_speed_kmh"
              label="Velocidade máxima"
              type="number"
              placeholder="80"
              min={1}
              max={300}
              suffix="km/h"
              hint="Limite usado para detectar excessos de velocidade."
            />
            <TextField
              name="speed_min_duration_seconds"
              label="Duração mínima"
              type="number"
              placeholder="30"
              min={1}
              max={3600}
              suffix="s"
              hint="Excessos mais curtos são descartados."
            />
            <TextField
              name="speed_hysteresis_percent"
              label="Margem de histerese"
              type="number"
              placeholder="3"
              min={1}
              max={20}
              suffix="%"
              hint="Evita abrir/fechar o evento por oscilações do GPS."
            />
            <TextField
              name="odometer"
              label="Odômetro"
              type="number"
              placeholder="0"
              min={0}
              suffix="km"
            />
            {canAssignEquipment && (
              <>
                <SearchSelectField
                  name="equipment_id"
                  label="Equipamento"
                  className="sm:col-span-2"
                  placeholder={
                    canViewEquipmentDetails ? 'Buscar por IMEI...' : 'Buscar equipamento...'
                  }
                  emptyMessage="Nenhum equipamento disponível"
                  loadOptions={loadAvailableEquipments}
                  hint="Opcional. Liste apenas rastreadores sem veículo vinculado."
                />
                <TextField
                  name="equipment_notes"
                  label="Observações da instalação"
                  className="sm:col-span-2"
                />
              </>
            )}
          </CardContent>
        </Card>

        <Card id="veiculo-consumo" className="scroll-mt-24">
          <CardHeader
            title="Consumo e tanque"
            description="Médias usadas em relatórios de abastecimento e custos."
          />
          <CardContent className="grid gap-5 sm:grid-cols-2">
            <TextField
              name="average_consumption"
              label="Consumo médio"
              type="number"
              step="0.1"
              placeholder="0.0"
              min={0}
              suffix="km/L"
            />
            <TextField
              name="tank_capacity"
              label="Capacidade do tanque"
              type="number"
              step="0.1"
              placeholder="0.0"
              min={0}
              suffix="L"
            />
          </CardContent>
        </Card>

        <Card id="veiculo-fipe" className="scroll-mt-24">
          <CardHeader
            title="Tabela FIPE"
            description="Preenchida automaticamente pela consulta de placa."
          />
          <CardContent className="grid gap-5 sm:grid-cols-2">
            <TextField name="fipe_code" label="Código FIPE" loading={plateLoading} />
            <TextField name="fipe_value" label="Valor" loading={plateLoading} />
            <TextField name="fipe_brand" label="Marca" loading={plateLoading} />
            <TextField name="fipe_model" label="Modelo" loading={plateLoading} />
            <TextField name="fipe_model_year" label="Ano modelo" loading={plateLoading} />
            <TextField name="fipe_fuel" label="Combustível" loading={plateLoading} />
            <TextField name="fipe_reference_month" label="Mês de referência" loading={plateLoading} />
            <TextField
              name="fipe_score"
              label="Score"
              type="number"
              min={0}
              loading={plateLoading}
            />
          </CardContent>
        </Card>

        <Card id="veiculo-documentos" className="scroll-mt-24">
          <CardHeader title="Documentos" description="Arquivos digitalizados do veículo." />
          <CardContent>
            <FileField
              name="crlv_file"
              label="CRLV-e"
              hint="Envie o CRLV-e digitalizado (PDF ou imagem)."
              currentUrl={crlvFileUrl}
            />
          </CardContent>
        </Card>

        {canConfigureAlerts && <VehicleAlertConfigsCard />}

        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          {showCancel && (
            <ButtonLink to="/vehicles" variant="secondary">
              Cancelar
            </ButtonLink>
          )}
          <Button type="submit" loading={submitting}>
            {submitLabel ?? (mode === 'create' ? 'Criar veículo' : 'Salvar alterações')}
          </Button>
        </div>
      </Form>
    </div>
  )
}
