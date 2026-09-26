import type { FieldPath } from 'react-hook-form'
import { useController, useFieldArray, useFormContext } from 'react-hook-form'
import { Card, CardContent, CardHeader, Switch } from '@/shared/design-system'
import { VEHICLE_ALERT_TYPES } from '@/modules/alerts/lib/alert-types'
import type { VehicleFormValues } from '../schemas/vehicle.schema'

type AlertFieldPath = FieldPath<VehicleFormValues>

function ChannelSwitch({
  name,
  label,
  disabled,
}: {
  name: AlertFieldPath
  label: string
  disabled?: boolean
}) {
  const { control } = useFormContext<VehicleFormValues>()
  const { field } = useController({ control, name })

  return (
    <Switch
      checked={Boolean(field.value)}
      disabled={disabled}
      onCheckedChange={(checked) => field.onChange(checked)}
      label={label}
    />
  )
}

function AlertMatrixHeader() {
  return (
    <div className="grid grid-cols-[minmax(0,1fr)_repeat(5,64px)] items-center gap-2 px-4 pb-1">
      <span className="text-xs font-medium tracking-wide text-muted uppercase">Alarme</span>
      <span className="text-center text-xs font-medium tracking-wide text-muted uppercase">
        Habilitado
      </span>
      <span className="text-center text-xs font-medium tracking-wide text-muted uppercase">
        In-app
      </span>
      <span className="text-center text-xs font-medium tracking-wide text-muted uppercase">
        Monitor.
      </span>
      <span className="text-center text-xs font-medium tracking-wide text-muted uppercase">
        Push
      </span>
      <span className="text-center text-xs font-medium tracking-wide text-muted uppercase">
        E-mail
      </span>
    </div>
  )
}

function VehicleAlertRow({
  index,
  label,
  description,
}: {
  index: number
  label: string
  description: string
}) {
  const { watch } = useFormContext<VehicleFormValues>()
  const base = `alert_configs.${index}`
  const enabled = Boolean(watch(`${base}.is_enabled` as AlertFieldPath))

  return (
    <div className="grid grid-cols-[minmax(0,1fr)_repeat(5,64px)] items-center gap-2 rounded-xl bg-surface-2 px-4 py-3">
      <div className="min-w-0">
        <p className="text-sm font-semibold text-foreground">{label}</p>
        <p className="mt-0.5 text-[13px] text-muted">{description}</p>
      </div>
      <div className="flex justify-center">
        <ChannelSwitch
          name={`${base}.is_enabled` as AlertFieldPath}
          label={`${label}: alarme habilitado`}
        />
      </div>
      <div className="flex justify-center">
        <ChannelSwitch
          name={`${base}.notify_in_app` as AlertFieldPath}
          label={`${label}: notificação in-app (cliente)`}
          disabled={!enabled}
        />
      </div>
      <div className="flex justify-center">
        <ChannelSwitch
          name={`${base}.notify_monitoring` as AlertFieldPath}
          label={`${label}: monitoramento (operador)`}
          disabled={!enabled}
        />
      </div>
      <div className="flex justify-center">
        <ChannelSwitch
          name={`${base}.notify_push` as AlertFieldPath}
          label={`${label}: notificação push`}
          disabled={!enabled}
        />
      </div>
      <div className="flex justify-center">
        <ChannelSwitch
          name={`${base}.notify_email` as AlertFieldPath}
          label={`${label}: e-mail`}
          disabled={!enabled}
        />
      </div>
    </div>
  )
}

export function VehicleAlertConfigsCard() {
  const { control } = useFormContext<VehicleFormValues>()
  const { fields } = useFieldArray({ control, name: 'alert_configs' })

  const generalFields = fields.slice(0, VEHICLE_ALERT_TYPES.length)
  const deviceFields = fields.slice(VEHICLE_ALERT_TYPES.length)

  return (
    <Card id="veiculo-alertas" className="scroll-mt-24">
      <CardHeader
        title="Alertas"
        description="Habilite os alarmes deste veículo e escolha os canais de cada um. In-app notifica o cliente; Monitoramento notifica o operador; Push e e-mail notificam ambos."
      />
      <CardContent className="space-y-6">
        <div className="overflow-x-auto">
          <div className="min-w-[720px] space-y-2">
            <AlertMatrixHeader />
            {generalFields.map((field, index) => (
              <VehicleAlertRow
                key={field.id}
                index={index}
                label={field.label}
                description={
                  VEHICLE_ALERT_TYPES[index]?.description ?? `Alarme "${field.label}".`
                }
              />
            ))}
          </div>
        </div>

        <div className="space-y-3">
          <div>
            <h4 className="text-sm font-semibold text-foreground">Alarmes do dispositivo</h4>
            <p className="mt-0.5 text-[13px] text-muted">
              Alarmes enviados pelo rastreador, cada um com canais próprios. Alarmes novos
              aparecem aqui automaticamente após a primeira ocorrência.
            </p>
          </div>

          <div className="overflow-x-auto">
            <div className="min-w-[720px] space-y-2">
              <AlertMatrixHeader />
              {deviceFields.map((field, offset) => {
                const index = VEHICLE_ALERT_TYPES.length + offset

                return (
                  <VehicleAlertRow
                    key={field.id}
                    index={index}
                    label={field.label}
                    description={`Alarme "${field.label}" reportado pelo rastreador.`}
                  />
                )
              })}
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
