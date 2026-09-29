import { useController, useFieldArray, useFormContext, type FieldArrayPath, type FieldValues } from 'react-hook-form'
import { Switch } from '@/shared/design-system'
import { VEHICLE_ALERT_TYPES } from '../lib/alert-types'

interface AlertConfigsMatrixProps {
  /** Campo do formulário com a lista de configurações (ex.: `alert_configs`). */
  name: string
  /** Texto explicativo da seção de alarmes do dispositivo. */
  deviceHint?: string
}

function ChannelSwitch({
  name,
  label,
  disabled,
}: {
  name: string
  label: string
  disabled?: boolean
}) {
  const { control } = useFormContext<FieldValues>()
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

function AlertRow({
  base,
  label,
  description,
}: {
  base: string
  label: string
  description: string
}) {
  const { watch } = useFormContext<FieldValues>()
  const enabled = Boolean(watch(`${base}.is_enabled`))

  return (
    <div className="grid grid-cols-[minmax(0,1fr)_repeat(5,64px)] items-center gap-2 rounded-xl bg-surface-2 px-4 py-3">
      <div className="min-w-0">
        <p className="text-sm font-semibold text-foreground">{label}</p>
        <p className="mt-0.5 text-[13px] text-muted">{description}</p>
      </div>
      <div className="flex justify-center">
        <ChannelSwitch name={`${base}.is_enabled`} label={`${label}: alarme habilitado`} />
      </div>
      <div className="flex justify-center">
        <ChannelSwitch
          name={`${base}.notify_in_app`}
          label={`${label}: notificação in-app (cliente)`}
          disabled={!enabled}
        />
      </div>
      <div className="flex justify-center">
        <ChannelSwitch
          name={`${base}.notify_monitoring`}
          label={`${label}: monitoramento (operador)`}
          disabled={!enabled}
        />
      </div>
      <div className="flex justify-center">
        <ChannelSwitch
          name={`${base}.notify_push`}
          label={`${label}: notificação push`}
          disabled={!enabled}
        />
      </div>
      <div className="flex justify-center">
        <ChannelSwitch
          name={`${base}.notify_email`}
          label={`${label}: e-mail`}
          disabled={!enabled}
        />
      </div>
    </div>
  )
}

function fieldLabel(field: unknown): string {
  const label = (field as { label?: unknown } | null)?.label

  return typeof label === 'string' && label !== '' ? label : 'Alarme'
}

/**
 * Matriz de alertas (habilitado + canais) reutilizada no cadastro de veículos
 * e nos padrões de alerta da empresa.
 */
export function AlertConfigsMatrix({
  name,
  deviceHint = 'Alarmes enviados pelo rastreador, cada um com canais próprios. Alarmes novos aparecem aqui automaticamente após a primeira ocorrência.',
}: AlertConfigsMatrixProps) {
  const { control } = useFormContext<FieldValues>()
  const { fields } = useFieldArray({ control, name: name as FieldArrayPath<FieldValues> })

  const generalFields = fields.slice(0, VEHICLE_ALERT_TYPES.length)
  const deviceFields = fields.slice(VEHICLE_ALERT_TYPES.length)

  return (
    <div className="space-y-6">
      <div className="overflow-x-auto">
        <div className="min-w-[720px] space-y-2">
          <AlertMatrixHeader />
          {generalFields.map((field, index) => (
            <AlertRow
              key={field.id}
              base={`${name}.${index}`}
              label={fieldLabel(field)}
              description={
                VEHICLE_ALERT_TYPES[index]?.description ?? `Alarme "${fieldLabel(field)}".`
              }
            />
          ))}
        </div>
      </div>

      <div className="space-y-3">
        <div>
          <h4 className="text-sm font-semibold text-foreground">Alarmes do dispositivo</h4>
          <p className="mt-0.5 text-[13px] text-muted">{deviceHint}</p>
        </div>

        <div className="overflow-x-auto">
          <div className="min-w-[720px] space-y-2">
            <AlertMatrixHeader />
            {deviceFields.map((field, offset) => {
              const index = VEHICLE_ALERT_TYPES.length + offset

              return (
                <AlertRow
                  key={field.id}
                  base={`${name}.${index}`}
                  label={fieldLabel(field)}
                  description={`Alarme "${fieldLabel(field)}" reportado pelo rastreador.`}
                />
              )
            })}
          </div>
        </div>
      </div>
    </div>
  )
}
