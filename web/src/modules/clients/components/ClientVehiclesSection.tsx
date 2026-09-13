import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Car, Plus, Trash2 } from 'lucide-react'
import {
  Button,
  Card,
  CardContent,
  ConfirmDialog,
  DataTable,
  EmptyState,
  FileField,
  Form,
  Section,
  SelectField,
  TextField,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { Vehicle } from '@/shared/types/models'
import { useCreateVehicle, useDeleteVehicle, useVehiclesQuery } from '@/modules/vehicles/hooks/useVehicles'
import { usePlateLookup } from '@/modules/vehicles/hooks/usePlateLookup'
import { fillVehicleFromLookup } from '@/modules/vehicles/utils/plate-lookup'
import { transmissionOptions } from '@/modules/vehicles/schemas/vehicle.schema'

const vehicleQuickSchema = z.object({
  plate: z.string().min(1, 'Informe a placa').max(10, 'Placa inválida'),
  chassis: z.string(),
  renavam: z.string(),
  brand: z.string(),
  model: z.string(),
  color: z.string(),
  year: z.string(),
  transmission: z.enum(['manual', 'automatic', 'automated', '']),
  odometer: z.string(),
  average_consumption: z.string(),
  tank_capacity: z.string(),
  crlv_file: z.string(),
  fipe_code: z.string(),
  fipe_model_year: z.string(),
  fipe_fuel: z.string(),
  fipe_reference_month: z.string(),
  fipe_value: z.string(),
  fipe_model: z.string(),
  fipe_brand: z.string(),
  fipe_score: z.string(),
})

type VehicleQuickValues = z.infer<typeof vehicleQuickSchema>

export function ClientVehiclesSection({ clientId }: { clientId: string }) {
  const [vehicleToDelete, setVehicleToDelete] = useState<Vehicle | null>(null)
  const vehiclesQuery = useVehiclesQuery({ client_id: clientId, per_page: 50 })
  const createVehicle = useCreateVehicle()
  const deleteVehicle = useDeleteVehicle()

  const form = useForm<VehicleQuickValues>({
    resolver: zodResolver(vehicleQuickSchema),
    defaultValues: {
      plate: '',
      chassis: '',
      renavam: '',
      brand: '',
      model: '',
      color: '',
      year: '',
      transmission: '',
      odometer: '',
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
    },
  })

  const {
    hint: plateHint,
    loading: plateLoading,
    lookup: lookupPlate,
  } = usePlateLookup((data) => fillVehicleFromLookup(form, data))

  const handleCreate = async (values: VehicleQuickValues) => {
    try {
      await createVehicle.mutateAsync({
        client_id: clientId,
        plate: values.plate,
        chassis: values.chassis || null,
        renavam: values.renavam || null,
        brand: values.brand || null,
        model: values.model || null,
        color: values.color || null,
        year: values.year ? Number(values.year) : null,
        transmission: values.transmission || null,
        odometer: values.odometer ? Number(values.odometer) : null,
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
        is_active: true,
      })
      form.reset()
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  const columns: Array<Column<Vehicle>> = [
    {
      key: 'plate',
      header: 'Veículo',
      render: (vehicle) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{vehicle.plate}</p>
          <p className="truncate text-[13px] text-muted">
            {[vehicle.brand, vehicle.model, vehicle.year].filter(Boolean).join(' · ') || '—'}
          </p>
        </div>
      ),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-16 text-right',
      render: (vehicle) => (
        <div className="flex justify-end">
          <Can permission={Permission.VEHICLE_DELETE}>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setVehicleToDelete(vehicle)}
              aria-label={`Excluir ${vehicle.plate}`}
              className="text-danger hover:bg-danger-soft hover:text-danger"
            >
              <Trash2 className="size-4" />
            </Button>
          </Can>
        </div>
      ),
    },
  ]

  return (
    <>
      <Card>
        <CardContent className="space-y-8">
          <Section title="Veículos" description="Frota vinculada a este cliente.">
            <DataTable
              caption="Veículos do cliente"
              columns={columns}
              rows={vehiclesQuery.data?.data ?? []}
              rowKey={(vehicle) => vehicle.id}
              loading={vehiclesQuery.isPending}
              emptyState={
                <EmptyState
                  icon={Car}
                  title="Nenhum veículo cadastrado"
                  description="Cadastre os veículos que farão parte do pedido."
                />
              }
            />
          </Section>

          <Can permission={Permission.VEHICLE_CREATE}>
            <Section title="Novo veículo" description="Informe ao menos a placa.">
              <Form form={form} onSubmit={handleCreate} className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField
                    name="plate"
                    label="Placa"
                    required
                    placeholder="ABC1D23"
                    hint={plateHint}
                    loading={plateLoading}
                    onBlur={() => lookupPlate(form.getValues('plate'))}
                  />
                  <TextField name="year" label="Ano" type="number" placeholder="2024" loading={plateLoading} />
                  <TextField name="brand" label="Marca" loading={plateLoading} />
                  <TextField name="model" label="Modelo" loading={plateLoading} />
                  <TextField name="color" label="Cor" loading={plateLoading} />
                  <TextField name="renavam" label="RENAVAM" />
                  <TextField name="chassis" label="Chassi" className="sm:col-span-2" loading={plateLoading} />
                  <SelectField
                    name="transmission"
                    label="Transmissão"
                    placeholder="Selecione"
                    options={[...transmissionOptions]}
                    loading={plateLoading}
                  />
                  <TextField
                    name="odometer"
                    label="Odômetro (km)"
                    type="number"
                    placeholder="0"
                    min={0}
                  />
                  <TextField
                    name="average_consumption"
                    label="Consumo médio (km/L)"
                    type="number"
                    step="0.1"
                    placeholder="0.0"
                    min={0}
                  />
                  <TextField
                    name="tank_capacity"
                    label="Capacidade do tanque (L)"
                    type="number"
                    step="0.1"
                    placeholder="0.0"
                    min={0}
                  />
                  <TextField name="fipe_code" label="Código FIPE" loading={plateLoading} />
                  <TextField name="fipe_value" label="Valor FIPE" loading={plateLoading} />
                  <TextField name="fipe_brand" label="Marca FIPE" loading={plateLoading} />
                  <TextField name="fipe_model" label="Modelo FIPE" loading={plateLoading} />
                  <TextField name="fipe_model_year" label="Ano modelo FIPE" loading={plateLoading} />
                  <TextField name="fipe_fuel" label="Combustível FIPE" loading={plateLoading} />
                  <TextField
                    name="fipe_reference_month"
                    label="Mês de referência FIPE"
                    loading={plateLoading}
                  />
                  <TextField
                    name="fipe_score"
                    label="Score FIPE"
                    type="number"
                    min={0}
                    loading={plateLoading}
                  />
                  <FileField
                    name="crlv_file"
                    label="CRLV-e"
                    hint="Envie o CRLV-e digitalizado (PDF ou imagem)."
                    className="sm:col-span-2"
                  />
                </div>
                <div className="flex justify-end">
                  <Button type="submit" loading={createVehicle.isPending}>
                    <Plus className="size-4" />
                    Adicionar veículo
                  </Button>
                </div>
              </Form>
            </Section>
          </Can>
        </CardContent>
      </Card>

      <ConfirmDialog
        open={vehicleToDelete !== null}
        onClose={() => setVehicleToDelete(null)}
        onConfirm={() => {
          if (!vehicleToDelete) return
          deleteVehicle.mutate(vehicleToDelete.id, { onSettled: () => setVehicleToDelete(null) })
        }}
        loading={deleteVehicle.isPending}
        title="Excluir veículo"
        description={
          <>
            Tem certeza que deseja excluir <strong>{vehicleToDelete?.plate}</strong>? Esta ação não
            pode ser desfeita.
          </>
        }
        confirmLabel="Excluir"
      />
    </>
  )
}
