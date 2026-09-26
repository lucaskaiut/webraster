import type { FieldPath } from 'react-hook-form'
import { useController, useFormContext } from 'react-hook-form'
import { Card, CardContent, CardHeader, Switch } from '@/shared/design-system'
import {
  VEHICLE_ALERT_TYPES,
  type VehicleAlertTypeOption,
} from '@/modules/alerts/lib/alert-types'
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

function VehicleAlertRow({ option, index }: { option: VehicleAlertTypeOption; index: number }) {
  const { watch } = useFormContext<VehicleFormValues>()
  const base = `alert_configs.${index}`
  const enabled = Boolean(watch(`${base}.is_enabled` as AlertFieldPath))

  return (
    <div className="grid grid-cols-[minmax(0,1fr)_repeat(3,64px)] items-center gap-2 rounded-xl bg-surface-2 px-4 py-3">
      <div className="min-w-0">
        <p className="text-sm font-semibold text-foreground">{option.label}</p>
        <p className="mt-0.5 text-[13px] text-muted">{option.description}</p>
      </div>
      <div className="flex justify-center">
        <ChannelSwitch
          name={`${base}.is_enabled` as AlertFieldPath}
          label={`${option.label}: alarme habilitado`}
        />
      </div>
      <div className="flex justify-center">
        <ChannelSwitch
          name={`${base}.notify_in_app` as AlertFieldPath}
          label={`${option.label}: notificação in-app`}
          disabled={!enabled}
        />
      </div>
      <div className="flex justify-center">
        <ChannelSwitch
          name={`${base}.notify_push` as AlertFieldPath}
          label={`${option.label}: notificação push`}
          disabled={!enabled}
        />
      </div>
      <div className="flex justify-center">
        <ChannelSwitch
          name={`${base}.notify_email` as AlertFieldPath}
          label={`${option.label}: e-mail`}
          disabled={!enabled}
        />
      </div>
    </div>
  )
}

export function VehicleAlertConfigsCard() {
  return (
    <Card id="veiculo-alertas" className="scroll-mt-24">
      <CardHeader
        title="Alertas"
        description="Habilite os alarmes deste veículo e escolha os canais de cada um. O cliente pode silenciar alertas no portal."
      />
      <CardContent>
        <div className="overflow-x-auto">
          <div className="min-w-[600px] space-y-2">
            <div className="grid grid-cols-[minmax(0,1fr)_repeat(3,64px)] items-center gap-2 px-4 pb-1">
              <span className="text-xs font-medium tracking-wide text-muted uppercase">
                Alarme
              </span>
              <span className="text-center text-xs font-medium tracking-wide text-muted uppercase">
                In-app
              </span>
              <span className="text-center text-xs font-medium tracking-wide text-muted uppercase">
                Push
              </span>
              <span className="text-center text-xs font-medium tracking-wide text-muted uppercase">
                E-mail
              </span>
            </div>

            {VEHICLE_ALERT_TYPES.map((option, index) => (
              <VehicleAlertRow key={option.type} option={option} index={index} />
            ))}
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
