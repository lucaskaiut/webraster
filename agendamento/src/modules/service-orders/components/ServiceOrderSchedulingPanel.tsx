import { useEffect, useRef, useState } from 'react'
import { useFormContext, useWatch } from 'react-hook-form'
import {
  CalendarClock,
  Car,
  ChevronDown,
  Clock,
  Info,
  MapPin,
  RefreshCw,
} from 'lucide-react'
import { Alert, Badge, Button, DatePicker, Field, Spinner } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { extractIsoDate, parseLocalDateTime, toLocalDateTimeValue } from '@/shared/utils/date'
import { toLocalIsoDate } from '@/shared/utils/format'
import type {
  ScheduleEstimate,
  ScheduleEstimatePayload,
} from '../services/service-orders.service'
import { ServiceOrderRouteMap } from './ServiceOrderRouteMap'
import { ServiceOrderAvailabilityBoard } from './ServiceOrderAvailabilityBoard'
import { formatMinutes } from '../lib/duration'
import {
  formatKilometers,
  formatTime,
  formatWeekdayDay,
  sameInstant,
  type ScheduleSlotView,
} from '../lib/schedule'
import { useScheduleAvailability } from '../hooks/useScheduleAvailability'
import type { ServiceOrderFormValues } from '../schemas/service-order.schema'

const DURATION_SOURCE_LABELS: Record<ScheduleEstimate['duration_source'], string> = {
  manual: 'informada manualmente',
  services: 'soma dos serviços',
  default: 'duração padrão',
}

function dateOfLocalInput(value: string | undefined | null): string | null {
  if (!value) return null
  return extractIsoDate(value)
}

function normalizedLocalStart(value: string | undefined): string | null {
  if (!value) return null

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
  serviceId: string
  /** Técnico fixo ainda sendo localizado. */
  technicianLoading?: boolean
}

interface ScheduleDetailEstimate {
  date: string
  start: string
  estimate: ScheduleEstimate
}

/**
 * Etapa de horário do formulário de OS.
 *
 * Com endereço, serviço e técnico definidos, a disponibilidade é consultada em
 * lotes de dias de calendário: a grade de 5 minutos, o deslocamento, a jornada
 * e os conflitos são calculados no servidor. O horário só é aplicado depois de
 * uma segunda confirmação da API.
 */
export function ServiceOrderSchedulingPanel({
  serviceId,
  technicianLoading = false,
}: ServiceOrderSchedulingPanelProps) {
  const form = useFormContext<ServiceOrderFormValues>()
  const address = useWatch({ control: form.control, name: 'address' })
  const technicianId = useWatch({ control: form.control, name: 'technician_id' })
  const scheduledStartAt = useWatch({ control: form.control, name: 'scheduled_start_at' })

  const [date, setDate] = useState(
    () => dateOfLocalInput(form.getValues('scheduled_start_at')) ?? toLocalIsoDate(),
  )
  const [previewLoading, setPreviewLoading] = useState(false)
  const [confirmingStart, setConfirmingStart] = useState<string | null>(null)
  const [selectionWarning, setSelectionWarning] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [detail, setDetail] = useState<ScheduleDetailEstimate | null>(null)
  const [routeDetailsOpen, setRouteDetailsOpen] = useState(false)

  const skipDetailStartRef = useRef<string | null>(null)
  const selectionRequestRef = useRef(0)
  const detailRequestRef = useRef(0)
  const confirmingRef = useRef(false)

  const trimmedAddress = address?.trim() || null

  const requestRef = useRef<Omit<ScheduleEstimatePayload, 'date' | 'scheduled_start_at'>>({})
  requestRef.current = {
    address: trimmedAddress,
    technician_id: technicianId || null,
    items: serviceId ? [{ service_id: serviceId, quantity: 1 }] : [],
  }

  const hasTechnician = Boolean(technicianId)
  const ready = Boolean(trimmedAddress && serviceId && hasTechnician)
  const requestKey = JSON.stringify([trimmedAddress, technicianId || null, serviceId || null])

  const board = useScheduleAvailability({
    anchorDate: date,
    enabled: ready,
    request: requestRef.current,
    requestKey,
  })

  useEffect(() => {
    detailRequestRef.current += 1
    setDetail(null)
    setSelectionWarning(null)
    setActionError(null)
  }, [requestKey])

  useEffect(() => {
    setSelectionWarning(null)
    setActionError(null)
  }, [date])

  // Atualiza o trajeto e a prévia do horário selecionado quando os dados mudam.
  useEffect(() => {
    const nextDate = dateOfLocalInput(scheduledStartAt)
    const startAt = normalizedLocalStart(scheduledStartAt)

    if (!nextDate || !startAt) return

    // A seleção já confirmou o horário; evita uma requisição duplicada.
    if (skipDetailStartRef.current === startAt) {
      skipDetailStartRef.current = null
      return
    }

    const requestId = ++detailRequestRef.current
    const timer = window.setTimeout(() => {
      void board
        .fetchDay(nextDate, startAt)
        .then((estimate) => {
          if (requestId !== detailRequestRef.current || !estimate) return

          setDetail({ date: nextDate, start: startAt, estimate })
        })
        .catch((err) => {
          if (requestId !== detailRequestRef.current) return

          setActionError(
            isApiError(err)
              ? err.message
              : 'Não foi possível atualizar o trajeto do horário selecionado.',
          )
        })
    }, 500)

    return () => window.clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [scheduledStartAt, requestKey])

  const selectedDate = dateOfLocalInput(scheduledStartAt)
  const selectedStart = normalizedLocalStart(scheduledStartAt)
  const detailForSelection =
    selectedDate &&
    selectedStart &&
    detail &&
    detail.date === selectedDate &&
    sameInstant(detail.start, selectedStart)
      ? detail.estimate
      : null
  const selectedDayEstimate = selectedDate ? board.estimates[selectedDate] ?? null : null
  const selectedEstimate = detailForSelection ?? selectedDayEstimate
  const summaryEstimate = selectedDate ? selectedEstimate : board.estimates[date] ?? null
  const selectedSlot =
    selectedStart && selectedEstimate
      ? selectedEstimate.slots.find((slot) => sameInstant(slot.start, selectedStart))
      : undefined
  const selectedUnavailable = Boolean(
    hasTechnician &&
      selectedEstimate &&
      selectedStart &&
      !board.loading &&
      !board.loadingMore &&
      !selectedEstimate.slots.some(
        (slot) => sameInstant(slot.start, selectedStart) && slot.available,
      ),
  )

  const handleSelectSlot = async (
    slotDate: string,
    slot: ScheduleSlotView,
  ): Promise<boolean> => {
    if (confirmingRef.current) return false

    confirmingRef.current = true
    detailRequestRef.current += 1
    setPreviewLoading(true)
    setConfirmingStart(slot.start)
    setSelectionWarning(null)
    setActionError(null)

    const requestId = ++selectionRequestRef.current

    try {
      // Confirma novamente com os dados atuais antes de aplicar ao formulário.
      const next = await board.refreshDay(slotDate, slot.start)

      if (requestId !== selectionRequestRef.current || !next) return false

      const confirmed = next.slots.find((item) => sameInstant(item.start, slot.start))

      if (!confirmed || !confirmed.available) {
        setSelectionWarning(
          'Este horário não está mais disponível com os dados atuais. Escolha outro horário.',
        )
        return false
      }

      const nextStart = toLocalDateTimeValue(new Date(confirmed.start))
      const currentStart = normalizedLocalStart(form.getValues('scheduled_start_at'))

      if (nextStart !== currentStart) {
        skipDetailStartRef.current = nextStart
      }

      setDetail({ date: slotDate, start: nextStart, estimate: next })
      form.setValue('scheduled_start_at', nextStart, {
        shouldDirty: true,
        shouldValidate: true,
      })
      form.setValue('scheduled_end_at', toLocalDateTimeValue(new Date(confirmed.end)), {
        shouldDirty: true,
        shouldValidate: true,
      })

      return true
    } catch (err) {
      if (requestId === selectionRequestRef.current) {
        setSelectionWarning(
          isApiError(err)
            ? err.message
            : 'Não foi possível confirmar o horário selecionado.',
        )
      }

      return false
    } finally {
      if (requestId === selectionRequestRef.current) {
        setPreviewLoading(false)
        setConfirmingStart(null)
      }
      confirmingRef.current = false
    }
  }

  const handleRefresh = () => {
    const requestId = ++detailRequestRef.current

    setSelectionWarning(null)
    setActionError(null)
    board.reload()

    const nextDate = dateOfLocalInput(scheduledStartAt)
    const parsed = parseLocalDateTime(scheduledStartAt ?? '')

    if (nextDate && parsed) {
      const startAt = toLocalDateTimeValue(parsed)

      void board
        .fetchDay(nextDate, startAt)
        .then((estimate) => {
          if (requestId !== detailRequestRef.current || !estimate) return

          setDetail({ date: nextDate, start: startAt, estimate })
        })
        .catch((err) => {
          if (requestId !== detailRequestRef.current) return

          setActionError(
            isApiError(err)
              ? err.message
              : 'Não foi possível atualizar o horário selecionado.',
          )
        })
    }
  }

  const travel = summaryEstimate?.travel
  const returnTravel = summaryEstimate?.travel_return
  const roundTrip = summaryEstimate?.travel_round_trip
  const routePreviewStart = detailForSelection ? selectedStart : null
  const selectedTimezone = selectedEstimate?.timezone
  const scheduleError = form.formState.errors.scheduled_start_at?.message

  return (
    <div className="space-y-5">
      <div className="rounded-xl bg-surface-2/60 p-4 sm:p-5">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <Field label="Buscar a partir de" className="w-full sm:w-60">
            <DatePicker
              value={date}
              onChange={(event) => setDate(event.target.value)}
              aria-label="Buscar horários a partir desta data"
            />
          </Field>

          <div className="flex items-center gap-3">
            {previewLoading && (
              <span className="flex items-center gap-2 text-xs text-muted" role="status">
                <Spinner className="size-4" />
                Confirmando horário…
              </span>
            )}
            <Button
              type="button"
              variant="secondary"
              size="sm"
              loading={board.loading}
              disabled={!ready || !date || previewLoading}
              onClick={handleRefresh}>
              {!board.loading && <RefreshCw className="size-4" />}
              Atualizar horários
            </Button>
          </div>
        </div>

        {(!trimmedAddress || !serviceId) && (
          <div className="mt-4 flex items-start gap-2 rounded-lg bg-surface px-3 py-2.5 text-sm text-muted">
            <Info className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>Informe o endereço e o serviço para calcular os horários disponíveis.</p>
          </div>
        )}

        {trimmedAddress && serviceId && !hasTechnician && (
          <div className="mt-4 flex items-start gap-2 rounded-lg bg-surface px-3 py-2.5 text-sm text-muted">
            {technicianLoading ? (
              <Spinner className="mt-0.5 size-4 shrink-0" />
            ) : (
              <Info className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            )}
            <p>
              {technicianLoading
                ? 'Carregando a agenda do técnico…'
                : 'Não foi possível localizar o técnico responsável. Entre em contato com o suporte.'}
            </p>
          </div>
        )}

        {ready && date && (
          <div className="mt-4">
            <div className="mb-3 flex items-center gap-2">
              <CalendarClock className="size-4 text-muted" aria-hidden="true" />
              <p className="text-sm font-medium text-foreground">Horários disponíveis</p>
            </div>

            <p className="mb-3 text-xs text-muted">
              Os dias mostram a primeira opção da manhã e da tarde. Horários ocupados ou fora da
              jornada não aparecem; o cálculo considera o deslocamento de ida e volta.
            </p>

            <ServiceOrderAvailabilityBoard
              days={board.days}
              selectedDate={selectedDate}
              selectedStart={selectedStart}
              loading={board.loading}
              loadingMore={board.loadingMore}
              hasMore={board.hasMore}
              error={board.error}
              failedCount={board.failedCount}
              searchedUntil={board.searchedUntil}
              confirmingStart={confirmingStart}
              disabled={previewLoading}
              selectionWarning={selectionWarning}
              onReload={handleRefresh}
              onLoadMore={board.loadMore}
              onRetryFailed={() => void board.retryFailed()}
              onClearWarning={() => {
                setSelectionWarning(null)
                setActionError(null)
              }}
              onSelectSlot={handleSelectSlot}
            />
          </div>
        )}

        {selectedSlot?.available && !board.loading && (
          <Badge variant="success" className="mt-3 max-w-full whitespace-normal">
            Horário selecionado: {formatWeekdayDay(selectedSlot.start, selectedTimezone)} ·{' '}
            {formatTime(selectedSlot.start, selectedTimezone)} –{' '}
            {formatTime(selectedSlot.end, selectedTimezone)}
          </Badge>
        )}

        {(selectedUnavailable || selectionWarning) && (
          <Alert variant="warning" title="Revise o horário selecionado" className="mt-3">
            {selectionWarning ??
              'O horário selecionado não está mais disponível. Escolha outra opção acima.'}
          </Alert>
        )}

        {actionError && (
          <div className="mt-3 flex flex-col gap-2 rounded-lg bg-danger-soft px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-[13px] text-danger" role="alert">
              {actionError}
            </p>
            <Button type="button" variant="secondary" size="sm" onClick={handleRefresh}>
              Tentar novamente
            </Button>
          </div>
        )}

        {scheduleError && (
          <p className="mt-3 text-[13px] text-danger" role="alert">
            {scheduleError}
          </p>
        )}
      </div>

      {summaryEstimate && (
        <div className="space-y-4">
          <dl className="grid gap-4 px-1 sm:grid-cols-3">
            <div className="min-w-0">
              <dt className="flex items-center gap-1.5 text-xs text-muted">
                <Clock className="size-3.5" aria-hidden="true" /> Execução
              </dt>
              <dd className="mt-1 font-semibold text-foreground">
                {formatMinutes(summaryEstimate.duration_minutes)}
              </dd>
              <dd className="text-xs text-muted">
                {DURATION_SOURCE_LABELS[summaryEstimate.duration_source]}
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
                    {travelSourceLabel(summaryEstimate)}
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
                    {originLabel(summaryEstimate)}{' '}
                    <span className="text-muted">→ atendimento</span>
                  </dd>
                  <dd className="truncate text-xs text-muted">
                    Retorno {returnLabel(summaryEstimate)}
                  </dd>
                </>
              ) : (
                <dd className="mt-1 text-sm text-muted">
                  {hasTechnician ? 'Endereço sem trajeto calculado' : 'Defina o técnico para calcular'}
                </dd>
              )}
            </div>
          </dl>

          {summaryEstimate.warnings.length > 0 && (
            <Alert variant="warning" title="Atenção">
              <ul className="list-disc space-y-0.5 pl-4">
                {summaryEstimate.warnings.map((warning) => (
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
                  <dt className="text-xs text-muted">{originLabel(summaryEstimate)}</dt>
                  <dd className="break-words text-foreground">
                    {summaryEstimate.travel_origin ?? '—'}
                  </dd>
                </div>
                <div className="min-w-0">
                  <dt className="text-xs text-muted">Atendimento</dt>
                  <dd className="break-words text-foreground">
                    {summaryEstimate.address ?? '—'}
                  </dd>
                </div>
                <div className="min-w-0">
                  <dt className="text-xs text-muted">Retorno {returnLabel(summaryEstimate)}</dt>
                  <dd className="break-words text-foreground">
                    {summaryEstimate.travel_return_address ?? '—'}
                  </dd>
                </div>
              </dl>

              {routeDetailsOpen && summaryEstimate.route && (
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
                  <ServiceOrderRouteMap route={summaryEstimate.route} />
                </div>
              )}

              {routeDetailsOpen && !summaryEstimate.route && (
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
