import { useEffect, useRef, useState } from 'react'
import { useFormContext, useWatch } from 'react-hook-form'
import {
  CalendarClock,
  Car,
  ChevronDown,
  Clock,
  Info,
  Lock,
  MapPin,
  RefreshCw,
} from 'lucide-react'
import {
  Alert,
  Badge,
  Button,
  CheckboxField,
  DatePicker,
  Field,
  Spinner,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { extractIsoDate, parseLocalDateTime, toLocalDateTimeValue } from '@/shared/utils/date'
import { toLocalIsoDate } from '@/shared/utils/format'
import {
  serviceOrdersService,
  type ScheduleEstimate,
  type ScheduleEstimatePayload,
} from '../services/service-orders.service'
import { ServiceOrderRouteMap } from './ServiceOrderRouteMap'
import { AddressAutocompleteField } from './AddressAutocompleteField'
import { formatMinutes } from '../lib/duration'
import {
  formatKilometers,
  formatTime,
  formatWeekdayDay,
  groupBlockedSlots,
  sameInstant,
} from '../lib/schedule'
import type { ServiceOrderFormValues } from '../schemas/service-order.schema'
import type { ServiceOrderItemDraft } from '../lib/items'

const DURATION_SOURCE_LABELS: Record<ScheduleEstimate['duration_source'], string> = {
  manual: 'informada manualmente',
  services: 'soma dos serviços',
  default: 'duração padrão',
}

function dateOfLocalInput(value: string | undefined | null): string | null {
  if (!value) return null
  return extractIsoDate(value)
}

function selectedSlotStart(value: string | undefined, date: string): string | null {
  if (!value || dateOfLocalInput(value) !== date) return null

  const parsed = parseLocalDateTime(value)
  return parsed ? toLocalDateTimeValue(parsed) : null
}

function travelSourceLabel(estimate: ScheduleEstimate): string {
  const outbound = estimate.travel?.source
  const returnTrip = estimate.travel_return?.source
  const isCalculated = (source: string | undefined) => source === 'google' || source === 'osrm'

  return isCalculated(outbound) && isCalculated(returnTrip)
    ? 'rotas calculadas'
    : 'estimativa em linha reta'
}

function originLabel(estimate: ScheduleEstimate): string {
  if (estimate.travel_origin_kind === 'order') {
    return estimate.travel_origin_order_code
      ? `Da ${estimate.travel_origin_order_code}`
      : 'Da OS anterior'
  }

  return 'Da base do técnico'
}

function returnLabel(estimate: ScheduleEstimate): string {
  if (estimate.travel_return_kind === 'order') {
    return estimate.travel_return_order_code
      ? `para a ${estimate.travel_return_order_code}`
      : 'para a próxima OS'
  }

  return 'para a base'
}

interface ServiceOrderSchedulingPanelProps {
  items: ServiceOrderItemDraft[]
  serviceOrderId?: string | null
}

/**
 * Etapa de agendamento do formulário de OS.
 *
 * A disponibilidade é consultada novamente sempre que cliente, endereço, duração,
 * técnico, data ou serviços mudam. O horário só é aplicado depois de uma segunda
 * confirmação da API, mantendo a mesma proteção contra conflitos do fluxo anterior.
 */
export function ServiceOrderSchedulingPanel({
  items,
  serviceOrderId,
}: ServiceOrderSchedulingPanelProps) {
  const form = useFormContext<ServiceOrderFormValues>()
  const clientId = useWatch({ control: form.control, name: 'client_id' })
  const address = useWatch({ control: form.control, name: 'address' })
  const technicianId = useWatch({ control: form.control, name: 'technician_id' })
  const estimatedDuration = useWatch({
    control: form.control,
    name: 'estimated_duration_minutes',
  })
  const scheduledStartAt = useWatch({ control: form.control, name: 'scheduled_start_at' })

  const [date, setDate] = useState(
    () => dateOfLocalInput(form.getValues('scheduled_start_at')) ?? toLocalIsoDate(),
  )
  const [estimate, setEstimate] = useState<ScheduleEstimate | null>(null)
  const [loading, setLoading] = useState(false)
  const [previewLoading, setPreviewLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [selectionWarning, setSelectionWarning] = useState<string | null>(null)
  const [previewStart, setPreviewStart] = useState<string | null>(null)
  const [routeDetailsOpen, setRouteDetailsOpen] = useState(false)

  const requestIdRef = useRef(0)
  const skipAppliedSlotRef = useRef<string | null>(null)
  const previousNonStartKeyRef = useRef<string | null>(null)

  const itemsKey = items
    .filter((item) => item.service_id && !item.missing)
    .map((item) => `${item.service_id}:${Math.max(1, Math.floor(Number(item.quantity) || 1))}`)
    .join('|')

  const requestRef = useRef<Omit<ScheduleEstimatePayload, 'date'>>({})
  requestRef.current = {
    client_id: clientId || null,
    address: address?.trim() || null,
    technician_id: technicianId || null,
    service_order_id: serviceOrderId ?? null,
    duration_minutes: estimatedDuration ? Number(estimatedDuration) : null,
    items: items
      .filter((item) => item.service_id && !item.missing)
      .map((item) => ({
        service_id: item.service_id,
        quantity: Math.max(1, Math.floor(Number(item.quantity) || 1)),
      })),
  }

  // Mantém a data do painel alinhada quando Início/Fim são alterados manualmente.
  useEffect(() => {
    const nextDate = dateOfLocalInput(scheduledStartAt)
    if (nextDate && nextDate !== date) setDate(nextDate)
    // A data escolhida no próprio painel não altera Início/Fim, portanto não volta.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [scheduledStartAt])

  const fetchEstimate = (startAt?: string | null) =>
    serviceOrdersService.estimate({
      ...requestRef.current,
      date,
      scheduled_start_at: startAt ?? null,
    })

  const runEstimate = async (startAt?: string | null) => {
    if (!date || !clientId) {
      requestIdRef.current += 1
      setEstimate(null)
      setError(null)
      setLoading(false)
      setPreviewLoading(false)
      setRouteDetailsOpen(false)
      return
    }

    const requestId = ++requestIdRef.current
    setLoading(true)
    setPreviewLoading(false)
    setError(null)
    setSelectionWarning(null)

    try {
      const next = await fetchEstimate(startAt)
      if (requestId !== requestIdRef.current) return

      setEstimate(next)
      setPreviewStart(startAt ?? null)
    } catch (err) {
      if (requestId !== requestIdRef.current) return

      setEstimate(null)
      setPreviewStart(null)
      setRouteDetailsOpen(false)
      setError(isApiError(err) ? err.message : 'Não foi possível calcular a disponibilidade.')
    } finally {
      if (requestId === requestIdRef.current) setLoading(false)
    }
  }

  const nonStartKey = JSON.stringify([
    clientId,
    address,
    technicianId,
    estimatedDuration,
    itemsKey,
    date,
    serviceOrderId,
  ])

  // Atualiza a disponibilidade após uma pausa na edição dos campos que afetam a agenda.
  useEffect(() => {
    const appliedSlotWasConfirmed =
      skipAppliedSlotRef.current !== null &&
      Boolean(scheduledStartAt) &&
      sameInstant(skipAppliedSlotRef.current, scheduledStartAt ?? '') &&
      previousNonStartKeyRef.current === nonStartKey

    skipAppliedSlotRef.current = null
    previousNonStartKeyRef.current = nonStartKey

    // O clique em um horário já refez a confirmação na API; não dispara outra
    // consulta só porque Início/Fim acabaram de ser preenchidos.
    if (appliedSlotWasConfirmed) return

    if (!date || !clientId) {
      requestIdRef.current += 1
      setEstimate(null)
      setError(null)
      setLoading(false)
      setPreviewLoading(false)
      setRouteDetailsOpen(false)
      return
    }

    // Marca como carregando já durante o debounce para impedir a seleção de um
    // horário que será recalculado em seguida. Invalida também qualquer resposta
    // em voo para não exibir uma disponibilidade calculada com dados antigos.
    setLoading(true)
    setPreviewLoading(false)
    requestIdRef.current += 1
    const selectedStart = selectedSlotStart(scheduledStartAt, date)
    const timer = setTimeout(() => {
      void runEstimate(selectedStart)
    }, 500)

    return () => clearTimeout(timer)
    // Início/Fim também entram na verificação: ajustes manuais precisam atualizar
    // a rota e confirmar a disponibilidade, não apenas o calendário.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    clientId,
    address,
    technicianId,
    estimatedDuration,
    itemsKey,
    date,
    scheduledStartAt,
    serviceOrderId,
  ])

  const hasTechnician = Boolean(technicianId)
  const slots = estimate?.slots ?? []
  const displaySlots = groupBlockedSlots(slots)
  const availableSlots = displaySlots.filter((slot) => slot.available)
  const blockedSlots = displaySlots.filter((slot) => !slot.available)

  const selectedStart = selectedSlotStart(scheduledStartAt, date)
  const selectedSlot = selectedStart
    ? slots.find((slot) => sameInstant(slot.start, selectedStart))
    : undefined
  const selectedOnAnotherDate = Boolean(
    date && scheduledStartAt && dateOfLocalInput(scheduledStartAt) !== date,
  )
  const selectedUnavailable = Boolean(
    hasTechnician &&
      estimate &&
      selectedStart &&
      !loading &&
      !slots.some((slot) => sameInstant(slot.start, selectedStart) && slot.available),
  )

  const selectSlot = async (slot: ScheduleEstimate['slots'][number]) => {
    if (!slot.available || previewLoading) return

    const requestId = ++requestIdRef.current
    setLoading(false)
    setPreviewStart(slot.start)
    setPreviewLoading(true)
    setError(null)
    setSelectionWarning(null)

    try {
      // Confirma o horário com o endereço/duração atuais antes de aplicar.
      const next = await fetchEstimate(slot.start)
      if (requestId !== requestIdRef.current) return

      const confirmed = next.slots.find((item) => sameInstant(item.start, slot.start))
      setEstimate(next)

      if (!confirmed || !confirmed.available) {
        setPreviewStart(null)
        setSelectionWarning(
          'Este horário não está mais disponível com os dados atuais. Escolha outro horário.',
        )
        return
      }

      skipAppliedSlotRef.current = confirmed.start
      form.setValue('scheduled_start_at', toLocalDateTimeValue(new Date(confirmed.start)), {
        shouldDirty: true,
        shouldValidate: true,
      })
      form.setValue('scheduled_end_at', toLocalDateTimeValue(new Date(confirmed.end)), {
        shouldDirty: true,
        shouldValidate: true,
      })
    } catch (err) {
      if (requestId !== requestIdRef.current) return

      setPreviewStart(null)
      setError(isApiError(err) ? err.message : 'Não foi possível confirmar o horário.')
    } finally {
      if (requestId === requestIdRef.current) setPreviewLoading(false)
    }
  }

  const travel = estimate?.travel
  const returnTravel = estimate?.travel_return
  const roundTrip = estimate?.travel_round_trip
  const routePreviewStart = previewStart ?? selectedStart

  return (
    <div className="space-y-5">
      <div className="grid gap-4 lg:grid-cols-3">
        <AddressAutocompleteField
          name="address"
          label="Endereço do atendimento"
          className="lg:col-span-2"
          placeholder="Digite ao menos 2 palavras do endereço"
          hint="As sugestões aparecem a partir de 2 palavras. Selecione uma para gravar o endereço completo; em branco, usamos o endereço do cliente."
        />
        <TextField
          name="estimated_duration_minutes"
          label="Duração estimada (min)"
          type="number"
          min={1}
          max={1440}
          placeholder="Automática"
          hint="Em branco, soma o tempo estimado dos serviços."
        />
      </div>

      <div className="rounded-xl bg-surface-2/60 p-4 sm:p-5">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <Field label="Data do atendimento" className="w-full sm:w-60">
            <DatePicker
              value={date}
              onChange={(event) => setDate(event.target.value)}
              aria-label="Data do atendimento"
            />
          </Field>

          <div className="flex items-center gap-3">
            {loading && (
              <span className="flex items-center gap-2 text-xs text-muted" role="status">
                <Spinner className="size-4" />
                Atualizando horários…
              </span>
            )}
            <Button
              type="button"
              variant="secondary"
              size="sm"
              loading={loading}
              disabled={!clientId || !date || previewLoading}
              onClick={() => void runEstimate(selectedStart)}>
              {!loading && <RefreshCw className="size-4" />}
              {hasTechnician ? 'Atualizar horários' : 'Calcular estimativa'}
            </Button>
          </div>
        </div>

        {!date && (
          <p className="mt-4 text-sm text-muted">
            Escolha uma data para consultar os horários disponíveis.
          </p>
        )}

        {error && (
          <div className="mt-4 flex flex-col gap-3 rounded-lg bg-danger-soft p-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-[13px] text-danger" role="alert">
              {error}
            </p>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => void runEstimate(selectedStart)}>
              Tentar novamente
            </Button>
          </div>
        )}

        <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <CalendarClock className="size-4 text-muted" aria-hidden="true" />
            <p className="text-sm font-medium text-foreground">Horários disponíveis</p>
          </div>
          {estimate && (
            <p className="text-xs text-muted" aria-live="polite">
              {availableSlots.length} livre{availableSlots.length === 1 ? '' : 's'}
              {blockedSlots.length > 0
                ? ` · ${blockedSlots.length} bloqueado${blockedSlots.length === 1 ? '' : 's'}`
                : ''}
            </p>
          )}
        </div>

        <p className="mt-1 text-xs text-muted">
          O horário exibido é o período de execução no cliente. O deslocamento de ida e volta é
          calculado separadamente. Horários riscados estão bloqueados por outro atendimento.
        </p>

        {!clientId && (
          <div className="mt-4 flex items-start gap-2 rounded-lg bg-surface px-3 py-2.5 text-sm text-muted">
            <Info className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>Selecione o cliente na etapa 1 para calcular a disponibilidade.</p>
          </div>
        )}

        {clientId && !hasTechnician && (
          <div className="mt-4 flex items-start gap-2 rounded-lg bg-surface px-3 py-2.5 text-sm text-muted">
            <Info className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>
              Selecione o técnico responsável na etapa 1 para ver os horários livres e considerar o
              deslocamento a partir da base.
            </p>
          </div>
        )}

        {hasTechnician && loading && !estimate && (
          <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4" aria-hidden="true">
            {Array.from({ length: 8 }).map((_, index) => (
              <div key={index} className="h-16 animate-pulse rounded-lg bg-surface" />
            ))}
          </div>
        )}

        {hasTechnician && clientId && !loading && displaySlots.length === 0 && !error && (
          <div className="mt-4 rounded-lg bg-surface px-4 py-5 text-center">
            <p className="text-sm font-medium text-foreground">
              Nenhum horário livre nesta data.
            </p>
            <p className="mt-1 text-xs text-muted">
              Escolha outra data ou verifique a jornada e os atendimentos do técnico.
            </p>
          </div>
        )}

        {hasTechnician && displaySlots.length > 0 && (
          <div
            className={
              loading
                ? 'mt-4 grid grid-cols-2 gap-2 opacity-60 sm:grid-cols-3 lg:grid-cols-4'
                : 'mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4'
            }>
            {displaySlots.map((slot) =>
              slot.available ? (
                <Button
                  key={slot.start}
                  type="button"
                  size="sm"
                  className="h-16 min-w-0 flex-col gap-0 px-2"
                  variant={selectedStart && sameInstant(slot.start, selectedStart) ? 'primary' : 'secondary'}
                  disabled={previewLoading || loading}
                  aria-pressed={Boolean(
                    selectedStart && sameInstant(slot.start, selectedStart),
                  )}
                  aria-label={`Selecionar ${formatWeekdayDay(slot.start)}, ${formatTime(slot.start)} às ${formatTime(slot.end)}`}
                  onClick={() => void selectSlot(slot)}>
                  <span className="truncate text-[10px] font-medium tracking-wide opacity-75">
                    {formatWeekdayDay(slot.start)}
                  </span>
                  <span className="flex min-w-0 items-baseline gap-1">
                    <span className="truncate text-[13px] font-semibold">
                      {formatTime(slot.start)}
                    </span>
                    <span className="truncate text-[11px] opacity-75">
                      – {formatTime(slot.end)}
                    </span>
                  </span>
                </Button>
              ) : (
                <div
                  key={slot.start}
                  className="flex h-16 min-w-0 flex-col justify-center gap-0.5 overflow-hidden rounded-lg bg-surface px-2 py-1"
                  title={slot.reason ?? 'Horário bloqueado'}>
                  <span className="truncate text-[10px] font-medium text-subtle">
                    {formatWeekdayDay(slot.start)}
                  </span>
                  <span className="flex items-center gap-1 text-[11px] font-medium text-muted line-through">
                    <Lock className="size-3 shrink-0" aria-hidden="true" />
                    <span className="truncate">
                      {formatTime(slot.start)} – {formatTime(slot.end)}
                    </span>
                  </span>
                  <span className="truncate text-[10px] leading-tight text-subtle">
                    {slot.reason ?? 'Horário bloqueado por outro atendimento'}
                  </span>
                </div>
              ),
            )}
          </div>
        )}

        {previewLoading && (
          <p className="mt-3 flex items-center gap-2 text-xs text-muted" role="status">
            <Spinner className="size-4" />
            Confirmando disponibilidade do horário…
          </p>
        )}

        {selectedSlot?.available && !loading && (
          <Badge variant="success" className="mt-3">
            Horário selecionado: {formatTime(selectedSlot.start)} – {formatTime(selectedSlot.end)}
          </Badge>
        )}

        {selectedOnAnotherDate && (
          <p className="mt-3 text-xs text-warning">
            Início e Fim estão em outra data. Escolha um horário acima ou ajuste os campos abaixo.
          </p>
        )}

        {(selectedUnavailable || selectionWarning) && (
          <Alert variant="warning" title="Revise o horário selecionado" className="mt-3">
            {selectionWarning ??
              'O horário em Início/Fim não corresponde a um horário disponível na grade atual. Escolha uma opção acima ou ajuste os campos abaixo.'}
          </Alert>
        )}

        <div className="mt-5 space-y-3">
          <p className="text-xs font-medium text-muted">Horário definido</p>
          <div className="grid gap-3 sm:grid-cols-2">
            <TextField name="scheduled_start_at" label="Início" type="datetime-local" />
            <TextField name="scheduled_end_at" label="Fim" type="datetime-local" />
          </div>
          <p className="text-xs text-muted">
            Preenchido ao escolher um horário acima. Também pode ser ajustado manualmente quando
            necessário.
          </p>
        </div>

      </div>

      <CheckboxField
        name="ignore_schedule_conflict"
        label="Ignorar conflito de agenda (se houver)"
        description="Permite salvar mesmo quando a API identificar sobreposição. Use apenas quando o conflito já foi combinado com o técnico."
        className="rounded-xl bg-surface-2/60 px-4 py-3"
      />

      {estimate && (
        <div className="space-y-4">
          <dl className="grid gap-4 px-1 sm:grid-cols-3">
            <div className="min-w-0">
              <dt className="flex items-center gap-1.5 text-xs text-muted">
                <Clock className="size-3.5" aria-hidden="true" /> Execução
              </dt>
              <dd className="mt-1 font-semibold text-foreground">
                {formatMinutes(estimate.duration_minutes)}
              </dd>
              <dd className="text-xs text-muted">
                {DURATION_SOURCE_LABELS[estimate.duration_source]}
              </dd>
            </div>

            <div className="min-w-0">
              <dt className="flex items-center gap-1.5 text-xs text-muted">
                <Car className="size-3.5" aria-hidden="true" /> Deslocamento (ida e volta)
              </dt>
              {travel ? (
                <>
                  <dd className="mt-1 font-semibold text-foreground">
                    {formatMinutes(roundTrip?.minutes ?? travel.minutes * 2)} ·{' '}
                    {formatKilometers(
                      roundTrip?.distance_meters ?? travel.distance_meters * 2,
                    )}
                  </dd>
                  <dd className="text-xs text-muted">
                    Ida {formatMinutes(travel.minutes)} · Volta{' '}
                    {formatMinutes(returnTravel?.minutes ?? travel.minutes)} ·{' '}
                    {travelSourceLabel(estimate)}
                  </dd>
                </>
              ) : (
                <dd className="mt-1 text-sm text-muted">Não considerado</dd>
              )}
            </div>

            <div className="min-w-0">
              <dt className="flex items-center gap-1.5 text-xs text-muted">
                <MapPin className="size-3.5" aria-hidden="true" /> Trajeto
              </dt>
              {travel ? (
                <>
                  <dd className="mt-1 text-sm text-foreground">
                    {originLabel(estimate)}{' '}
                    <span className="text-muted">→ atendimento</span>
                  </dd>
                  <dd className="truncate text-xs text-muted">
                    Retorno {returnLabel(estimate)}
                  </dd>
                </>
              ) : (
                <dd className="mt-1 text-sm text-muted">
                  {hasTechnician ? 'Endereço sem trajeto calculado' : 'Defina o técnico para calcular'}
                </dd>
              )}
            </div>
          </dl>

          {estimate.warnings.length > 0 && (
            <Alert variant="warning" title="Atenção">
              <ul className="list-disc space-y-0.5 pl-4">
                {estimate.warnings.map((warning) => (
                  <li key={warning}>{warning}</li>
                ))}
              </ul>
            </Alert>
          )}

          <details
            className="group rounded-xl bg-surface-2/60"
            onToggle={(event) => setRouteDetailsOpen(event.currentTarget.open)}>
            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-foreground [&::-webkit-details-marker]:hidden">
              <span className="flex items-center gap-2">
                <MapPin className="size-4 text-muted" aria-hidden="true" />
                Detalhes do deslocamento e trajeto
              </span>
              <span className="flex items-center gap-2 text-xs font-normal text-muted">
                Ver detalhes
                <ChevronDown
                  className="size-4 transition-transform group-open:rotate-180"
                  aria-hidden="true"
                />
              </span>
            </summary>

            <div className="space-y-4 px-4 pb-4">
              <dl className="grid gap-2 text-sm sm:grid-cols-3">
                <div className="min-w-0">
                  <dt className="text-xs text-muted">{originLabel(estimate)}</dt>
                  <dd className="break-words text-foreground">
                    {estimate.travel_origin ?? '—'}
                  </dd>
                </div>
                <div className="min-w-0">
                  <dt className="text-xs text-muted">Atendimento</dt>
                  <dd className="break-words text-foreground">{estimate.address ?? '—'}</dd>
                </div>
                <div className="min-w-0">
                  <dt className="text-xs text-muted">Retorno {returnLabel(estimate)}</dt>
                  <dd className="break-words text-foreground">
                    {estimate.travel_return_address ?? '—'}
                  </dd>
                </div>
              </dl>

              {routeDetailsOpen && estimate.route && (
                <div className="space-y-2">
                  <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <p className="text-sm font-medium text-foreground">
                      {routePreviewStart
                        ? 'Trajeto do horário selecionado'
                        : 'Trajeto calculado (prévia)'}
                    </p>
                    <p className="text-[11px] text-muted">
                      {previewLoading
                        ? 'Atualizando trajeto…'
                        : routePreviewStart
                          ? 'Calculado com as OS existentes antes e depois deste horário.'
                          : 'Prévia do primeiro horário disponível; selecione um horário para atualizar.'}
                    </p>
                  </div>
                  <ServiceOrderRouteMap route={estimate.route} />
                </div>
              )}

              {routeDetailsOpen && !estimate.route && (
                <p className="text-sm text-muted">
                  O trajeto no mapa não está disponível para este atendimento.
                </p>
              )}
            </div>
          </details>
        </div>
      )}
    </div>
  )
}
