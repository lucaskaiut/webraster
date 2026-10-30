import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Plus, Trash2 } from 'lucide-react'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Checkbox,
  Field,
  Form,
  Input,
  SearchSelectField,
  Section,
  SelectField,
  SwitchField,
  TextField,
  type SearchSelectOption,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { onlyDigits } from '@/shared/utils/document'
import { maskCep, maskCpf, maskPhone } from '@/shared/utils/mask'
import { fetchAddressByCep } from '@/shared/services/viacep'
import { stocksService } from '@/modules/stocks/services/stocks.service'
import { useIsUmbrellaTenant } from '@/shared/hooks/useIsUmbrellaTenant'
import { useTenantModules } from '@/shared/hooks/useTenantModules'
import type { UserWorkHour } from '@/shared/types/models'
import { RolesField } from '../components/RolesField'
import { DEFAULT_WORK_HOURS, WEEKDAYS } from '../lib/work-hours'
import type { UserPayload } from '../services/users.service'
import { createUserSchema, updateUserSchema, type UserFormValues } from '../schemas/user.schema'

interface WorkHourRow extends UserWorkHour {
  key: string
}

function toRows(hours: UserWorkHour[]): WorkHourRow[] {
  return hours.map((hour) => ({ ...hour, key: crypto.randomUUID() }))
}

async function loadStockOptions(search: string): Promise<SearchSelectOption[]> {
  const response = await stocksService.list({
    search: search || undefined,
    per_page: 20,
    is_active: true,
  })

  return response.data.map((stock) => ({ value: stock.id, label: stock.name }))
}

async function resolveStockLabel(value: string): Promise<SearchSelectOption | null> {
  try {
    const stock = await stocksService.get(value)

    return { value: stock.id, label: stock.name }
  } catch {
    return null
  }
}

interface UserFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<UserFormValues>
  initialWorkHours?: UserWorkHour[]
  submitting: boolean
  onSubmit: (payload: UserPayload) => Promise<unknown>
}

export function UserForm({
  mode,
  defaultValues,
  initialWorkHours,
  submitting,
  onSubmit,
}: UserFormProps) {
  const form = useForm<UserFormValues>({
    resolver: zodResolver(mode === 'create' ? createUserSchema : updateUserSchema),
    defaultValues: {
      name: '',
      email: '',
      password: '',
      password_confirmation: '',
      type: 'staff',
      role_ids: [],
      technician_active: true,
      stock_id: '',
      street: '',
      number: '',
      complement: '',
      neighborhood: '',
      city: '',
      state: '',
      ...defaultValues,
      document: maskCpf(defaultValues?.document ?? ''),
      phone: maskPhone(defaultValues?.phone ?? ''),
      zip: maskCep(defaultValues?.zip ?? ''),
    },
  })

  const { can } = usePermissions()
  const selectedType = useWatch({ control: form.control, name: 'type' })
  const isTechnician = selectedType === 'technician'
  const canReadStocks = can(Permission.STOCK_READ)
  const isUmbrella = useIsUmbrellaTenant()
  const { hasModule } = useTenantModules()
  // Técnicos só existem em empresas filhas com ordens de serviço.
  const canHaveTechnicians = !isUmbrella && hasModule('service_orders')

  const [hours, setHours] = useState<WorkHourRow[]>(() =>
    toRows(initialWorkHours ?? (mode === 'create' ? DEFAULT_WORK_HOURS : [])),
  )
  const [hoursError, setHoursError] = useState<string | null>(null)
  const [cepStatus, setCepStatus] = useState<'idle' | 'loading' | 'error'>('idle')

  const lookupCep = async (value: string) => {
    if (onlyDigits(value).length !== 8) return

    setCepStatus('loading')
    try {
      const address = await fetchAddressByCep(value)
      if (!address) {
        setCepStatus('error')
        return
      }
      form.setValue('street', address.street, { shouldDirty: true })
      form.setValue('neighborhood', address.neighborhood, { shouldDirty: true })
      form.setValue('city', address.city, { shouldDirty: true })
      form.setValue('state', address.state, { shouldDirty: true })
      setCepStatus('idle')
    } catch {
      setCepStatus('error')
    }
  }

  const toggleDay = (weekday: number, enabled: boolean) => {
    setHours((current) =>
      enabled
        ? [...current, { key: crypto.randomUUID(), weekday, starts_at: '08:00', ends_at: '18:00' }]
        : current.filter((hour) => hour.weekday !== weekday),
    )
  }

  const updateHour = (key: string, patch: Partial<UserWorkHour>) => {
    setHours((current) => current.map((hour) => (hour.key === key ? { ...hour, ...patch } : hour)))
  }

  const handleSubmit = async (values: UserFormValues) => {
    if (values.type === 'technician') {
      if (hours.some((hour) => !hour.starts_at || !hour.ends_at || hour.ends_at <= hour.starts_at)) {
        setHoursError('Em cada intervalo, o fim deve ser posterior ao início.')
        return
      }
    }

    setHoursError(null)

    const payload: UserPayload = {
      name: values.name,
      email: values.email,
      phone: values.phone ? onlyDigits(values.phone) : null,
      document: values.document ? onlyDigits(values.document) : null,
      type: values.type,
      role_ids: values.role_ids,
    }

    if (values.type === 'technician') {
      payload.technician_active = values.technician_active
      payload.zip = values.zip ? onlyDigits(values.zip) : null
      payload.street = values.street || null
      payload.number = values.number || null
      payload.complement = values.complement || null
      payload.neighborhood = values.neighborhood || null
      payload.city = values.city || null
      payload.state = values.state ? values.state.toUpperCase() : null
      payload.work_hours = hours.map(({ weekday, starts_at, ends_at }) => ({
        weekday,
        starts_at,
        ends_at,
      }))

      if (canReadStocks) {
        payload.stock_id = values.stock_id || null
      }
    }

    if (values.password) {
      payload.password = values.password
    }

    try {
      await onSubmit(payload)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
        const workHoursError = error.fieldErrors?.work_hours?.[0]
        if (workHoursError) setHoursError(workHoursError)
      }
    }
  }

  return (
    <Card>
      <CardContent>
        <Form form={form} onSubmit={handleSubmit} className="space-y-8">
          <Section title="Informações básicas">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="name" label="Nome completo" required className="sm:col-span-2" />
              <TextField name="email" label="E-mail" type="email" required />
              <TextField
                name="phone"
                label="Telefone"
                placeholder="(41) 99999-9999"
                inputMode="tel"
                mask={maskPhone}
              />
              <TextField
                name="document"
                label="CPF"
                placeholder="000.000.000-00"
                inputMode="numeric"
                mask={maskCpf}
              />
              <SelectField
                name="type"
                label="Tipo de usuário"
                className="sm:col-span-2"
                hint="Técnicos usam o aplicativo com a área de ordens de serviço; a equipe usa a visão administrativa."
                options={[
                  { value: 'staff', label: 'Equipe' },
                  ...(canHaveTechnicians || isTechnician
                    ? [{ value: 'technician', label: 'Técnico' }]
                    : []),
                ]}
              />
            </div>
          </Section>

          {isTechnician && (
            <>
              <Section
                title="Dados do técnico"
                description="O endereço-base é o ponto de partida para calcular o deslocamento até o cliente."
              >
                <div className="space-y-4">
                  <SwitchField
                    name="technician_active"
                    label="Disponível para agendamento"
                    hint="Técnicos inativos não aparecem na seleção de novas ordens de serviço."
                  />

                  {canReadStocks && (
                    <SearchSelectField
                      name="stock_id"
                      label="Estoque de uso"
                      placeholder="Buscar estoque..."
                      emptyMessage="Nenhum estoque ativo encontrado"
                      loadOptions={loadStockOptions}
                      resolveLabel={resolveStockLabel}
                      hint="Opcional. Nas OS, o técnico só poderá selecionar equipamentos deste estoque. Sem estoque, ele pode usar qualquer equipamento disponível."
                    />
                  )}

                  <div className="grid gap-4 sm:grid-cols-6">
                    <TextField
                      name="zip"
                      label="CEP"
                      className="sm:col-span-2"
                      hint={
                        cepStatus === 'loading'
                          ? 'Buscando endereço...'
                          : cepStatus === 'error'
                            ? 'CEP não encontrado'
                            : undefined
                      }
                      onChange={(event) =>
                        form.setValue('zip', maskCep(event.target.value), { shouldDirty: true })
                      }
                      onBlur={(event) => void lookupCep(event.target.value)}
                    />
                    <TextField name="street" label="Logradouro" className="sm:col-span-4" />
                    <TextField name="number" label="Número" className="sm:col-span-2" />
                    <TextField name="complement" label="Complemento" className="sm:col-span-4" />
                    <TextField name="neighborhood" label="Bairro" className="sm:col-span-2" />
                    <TextField name="city" label="Cidade" className="sm:col-span-3" />
                    <TextField name="state" label="UF" className="sm:col-span-1" maxLength={2} />
                  </div>
                </div>
              </Section>

              <Section
                title="Jornada de trabalho"
                description="Os horários sugeridos no agendamento respeitam a jornada. Use dois intervalos para registrar o almoço."
              >
                <div className="space-y-3">
                  {WEEKDAYS.map((day) => {
                    const dayHours = hours.filter((hour) => hour.weekday === day.value)
                    const enabled = dayHours.length > 0

                    return (
                      <div
                        key={day.value}
                        className="flex flex-col gap-3 rounded-xl bg-surface-2/60 p-3 sm:flex-row sm:items-start"
                      >
                        <div className="w-32 shrink-0 pt-2">
                          <Checkbox
                            label={day.label}
                            checked={enabled}
                            onChange={(event) => toggleDay(day.value, event.target.checked)}
                          />
                        </div>
                        {enabled ? (
                          <div className="flex flex-1 flex-col gap-2">
                            {dayHours.map((hour) => (
                              <div key={hour.key} className="flex flex-wrap items-end gap-2">
                                <Field label="Início" className="w-32">
                                  <Input
                                    type="time"
                                    value={hour.starts_at}
                                    aria-label={`Início ${day.label}`}
                                    onChange={(event) =>
                                      updateHour(hour.key, { starts_at: event.target.value })
                                    }
                                  />
                                </Field>
                                <Field label="Fim" className="w-32">
                                  <Input
                                    type="time"
                                    value={hour.ends_at}
                                    aria-label={`Fim ${day.label}`}
                                    onChange={(event) =>
                                      updateHour(hour.key, { ends_at: event.target.value })
                                    }
                                  />
                                </Field>
                                <Button
                                  type="button"
                                  variant="ghost"
                                  size="sm"
                                  aria-label={`Remover intervalo de ${day.label}`}
                                  className="text-danger hover:bg-danger-soft hover:text-danger"
                                  onClick={() =>
                                    setHours((current) =>
                                      current.filter((item) => item.key !== hour.key),
                                    )
                                  }
                                >
                                  <Trash2 className="size-4" />
                                </Button>
                              </div>
                            ))}
                            <div>
                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                  setHours((current) => [
                                    ...current,
                                    {
                                      key: crypto.randomUUID(),
                                      weekday: day.value,
                                      starts_at: '13:00',
                                      ends_at: '18:00',
                                    },
                                  ])
                                }
                              >
                                <Plus className="size-4" />
                                Adicionar intervalo
                              </Button>
                            </div>
                          </div>
                        ) : (
                          <p className="pt-2 text-sm text-muted">Folga</p>
                        )}
                      </div>
                    )
                  })}
                </div>
                {hours.length === 0 && (
                  <p className="text-[13px] text-muted">
                    Sem jornada cadastrada, o agendamento usa 08:00–18:00 todos os dias.
                  </p>
                )}
                {hoursError && (
                  <p className="text-[13px] text-danger" role="alert">
                    {hoursError}
                  </p>
                )}
              </Section>
            </>
          )}

          <Section
            title="Perfis de acesso"
            description="Selecione um ou mais perfis para definir o que este usuário pode fazer no sistema."
          >
            <RolesField />
          </Section>

          <Section
            title={mode === 'create' ? 'Credenciais de acesso' : 'Alterar senha'}
            description={
              mode === 'edit' ? 'Preencha apenas se desejar definir uma nova senha.' : undefined
            }
          >
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="password"
                label="Senha"
                type="password"
                autoComplete="new-password"
                hint="Mínimo de 8 caracteres"
                required={mode === 'create'}
              />
              <TextField
                name="password_confirmation"
                label="Confirmar senha"
                type="password"
                autoComplete="new-password"
                required={mode === 'create'}
              />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/users" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar usuário' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
