import { useCallback, useEffect, useRef, useState } from 'react'
import { isApiError } from '@/shared/api/errors'
import {
  addDaysToIsoDate,
  buildScheduleDay,
  CALENDAR_DAY_BATCH_SIZE,
  calendarDatesUpTo,
  isValidIsoDate,
  SEARCH_DAYS_LIMIT,
  type ScheduleDayView,
} from '../lib/schedule'
import {
  serviceOrdersService,
  type ScheduleEstimate,
  type ScheduleEstimatePayload,
} from '../services/service-orders.service'

/** Concorrência máxima por lote para não sobrecarregar o cálculo de rotas. */
const FETCH_CONCURRENCY = 4

/** Intervalo da verificação de mudanças na agenda do técnico. */
const WATCH_INTERVAL_MS = 20_000

export type ScheduleAvailabilityRequest = Omit<
  ScheduleEstimatePayload,
  'date' | 'scheduled_start_at'
>

export interface ScheduleAvailabilityOptions {
  anchorDate: string
  enabled: boolean
  request: ScheduleAvailabilityRequest
  /** Identifica mudanças em cliente, endereço, técnico, duração e serviços. */
  requestKey: string
  batchSize?: number
  maxDays?: number
  /** Intervalo da verificação automática de mudanças na agenda (0 desativa). */
  watchIntervalMs?: number
}

/** Dias cujos horários mudaram por alterações na agenda feitas por outra pessoa. */
export interface ScheduleChange {
  dates: string[]
  /** Muda a cada detecção, para efeitos dependentes reagirem. */
  revision: number
}

export interface ScheduleAvailability {
  /** Somente dias com ao menos um horário válido, em ordem cronológica. */
  days: ScheduleDayView[]
  /** Estimativas por data, inclusive de dias sem horário livre. */
  estimates: Record<string, ScheduleEstimate>
  loading: boolean
  loadingMore: boolean
  hasMore: boolean
  error: string | null
  failedCount: number
  searchedUntil: string | null
  searchLimitDate: string
  reload: () => void
  loadMore: () => void
  retryFailed: () => void
  /** Recalcula um dia com um horário específico, confirmando a disponibilidade. */
  refreshDay: (date: string, startAt: string) => Promise<ScheduleEstimate | null>
  /** Busca a estimativa de um horário sem alterar os cartões da lista. */
  fetchDay: (date: string, startAt: string) => Promise<ScheduleEstimate | null>
  /** Última atualização automática que alterou horários, até ser dispensada. */
  change: ScheduleChange | null
  dismissChange: () => void
}

interface SearchWindow {
  anchorDate: string
  batchSize: number
  maxDays: number
}

interface DayResult {
  date: string
  failed: boolean
  available: boolean
  message?: string
}

function failureMessage(failures: DayResult[], fallback: string): string {
  return failures.find((failure) => failure.message)?.message ?? fallback
}

async function mapWithConcurrency<T, R>(
  items: T[],
  limit: number,
  worker: (item: T) => Promise<R>,
): Promise<R[]> {
  const results = new Array<R>(items.length)
  let nextIndex = 0

  const runners = Array.from({ length: Math.min(Math.max(1, limit), items.length) }, async () => {
    while (true) {
      const index = nextIndex
      nextIndex += 1

      if (index >= items.length) return

      results[index] = await worker(items[index])
    }
  })

  await Promise.all(runners)

  return results
}

/** Horários livres de uma estimativa, para comparar antes/depois. */
function availabilityKey(estimate: ScheduleEstimate | undefined): string {
  if (!estimate) return ''

  return estimate.slots
    .filter((slot) => slot.available)
    .map((slot) => slot.start)
    .join('|')
}

function uniqueSorted(values: string[]): string[] {
  return Array.from(new Set(values)).sort((a, b) => a.localeCompare(b))
}

function resolveWindow(options: ScheduleAvailabilityOptions): SearchWindow {
  return {
    anchorDate: options.anchorDate,
    batchSize: Math.max(1, Math.floor(options.batchSize ?? CALENDAR_DAY_BATCH_SIZE)),
    maxDays: Math.max(1, Math.floor(options.maxDays ?? SEARCH_DAYS_LIMIT)),
  }
}

/**
 * Orquestra o carregamento progressivo da disponibilidade.
 *
 * A API de estimativa trabalha por dia; cada lote consulta 5 dias de calendário
 * com concorrência limitada, descarta dias sem horário e continua automaticamente
 * para o próximo lote quando o lote inteiro está indisponível. Os cálculos de
 * grade, deslocamento e conflitos permanecem exclusivamente na API.
 */
export function useScheduleAvailability(
  options: ScheduleAvailabilityOptions,
): ScheduleAvailability {
  const {
    anchorDate,
    enabled,
    request,
    requestKey,
    batchSize = CALENDAR_DAY_BATCH_SIZE,
    maxDays = SEARCH_DAYS_LIMIT,
    watchIntervalMs = WATCH_INTERVAL_MS,
  } = options

  const [days, setDays] = useState<ScheduleDayView[]>([])
  const [estimates, setEstimates] = useState<Record<string, ScheduleEstimate>>({})
  const [loading, setLoading] = useState(false)
  const [loadingMore, setLoadingMore] = useState(false)
  const [hasMore, setHasMore] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [failedCount, setFailedCount] = useState(0)
  const [searchedUntil, setSearchedUntil] = useState<string | null>(null)
  const [change, setChange] = useState<ScheduleChange | null>(null)

  const daysRef = useRef(new Map<string, ScheduleDayView>())
  const estimatesRef = useRef(new Map<string, ScheduleEstimate>())
  const manualVersionsRef = useRef(new Map<string, number>())
  const refreshSequencesRef = useRef(new Map<string, number>())
  const failedDatesRef = useRef<string[]>([])
  const pendingRef = useRef(new Map<string, Promise<ScheduleEstimate>>())
  const cursorRef = useRef(anchorDate)
  const searchedUntilRef = useRef<string | null>(null)
  const generationRef = useRef(0)
  const loadingRef = useRef(false)
  const hasMoreRef = useRef(false)
  const requestRef = useRef(request)
  const requestSnapshotRef = useRef(request)
  const optionsRef = useRef(options)
  const windowRef = useRef<SearchWindow>({ anchorDate, batchSize, maxDays })
  const enabledRef = useRef(enabled)
  /** Assinatura da agenda conhecida quando cada dia foi calculado. */
  const signaturesRef = useRef(new Map<string, string>())
  /** Horário usado no último cálculo manual de cada dia (prévia do trajeto). */
  const dayStartsRef = useRef(new Map<string, string>())
  const watchingRef = useRef(false)
  const changeRevisionRef = useRef(0)

  requestRef.current = request
  optionsRef.current = options
  enabledRef.current = enabled

  const commit = useCallback(() => {
    setDays(
      Array.from(daysRef.current.values()).sort((a, b) => a.date.localeCompare(b.date)),
    )
    setEstimates(Object.fromEntries(estimatesRef.current))
    setFailedCount(failedDatesRef.current.length)
    setSearchedUntil(searchedUntilRef.current)
  }, [])

  const markHasMore = useCallback((value: boolean) => {
    hasMoreRef.current = value
    setHasMore(value)
  }, [])

  const applyEstimate = useCallback(
    (date: string, estimate: ScheduleEstimate, override = false) => {
      if (override) {
        manualVersionsRef.current.set(
          date,
          (manualVersionsRef.current.get(date) ?? 0) + 1,
        )
      }

      estimatesRef.current.set(date, estimate)

      const day = buildScheduleDay(date, estimate)

      if (day) {
        daysRef.current.set(date, day)
      } else {
        daysRef.current.delete(date)
      }

      commit()
    },
    [commit],
  )

  const requestDay = useCallback(
    (
      snapshot: ScheduleAvailabilityRequest,
      date: string,
      startAt: string | null,
      generation: number,
    ) => {
      const key = `${generation}:${date}:${startAt ?? ''}`
      const pending = pendingRef.current.get(key)

      if (pending) return pending

      const promise = serviceOrdersService.estimate({
        ...snapshot,
        date,
        scheduled_start_at: startAt,
      })

      pendingRef.current.set(key, promise)

      const clear = () => pendingRef.current.delete(key)
      void promise.then(clear, clear)

      return promise
    },
    [],
  )

  const resetWindow = useCallback(
    (nextWindow: SearchWindow) => {
      generationRef.current += 1
      windowRef.current = nextWindow
      daysRef.current.clear()
      estimatesRef.current.clear()
      manualVersionsRef.current.clear()
      refreshSequencesRef.current.clear()
      signaturesRef.current.clear()
      dayStartsRef.current.clear()
      failedDatesRef.current = []
      pendingRef.current.clear()
      cursorRef.current = nextWindow.anchorDate
      searchedUntilRef.current = null
      loadingRef.current = false
      hasMoreRef.current = false

      setDays([])
      setEstimates({})
      setLoading(false)
      setLoadingMore(false)
      setHasMore(false)
      setError(null)
      setFailedCount(0)
      setSearchedUntil(null)
      setChange(null)
    },
    [],
  )

  /**
   * Registra a assinatura da agenda dos dias antes de calculá-los, para que
   * qualquer mudança posterior seja detectada pela verificação automática.
   */
  const captureSignatures = useCallback(
    async (snapshot: ScheduleAvailabilityRequest, dates: string[], generation: number) => {
      if (!snapshot.technician_id || dates.length === 0) return

      try {
        const signatures = await serviceOrdersService.agendaSignature({
          technician_id: snapshot.technician_id,
          service_order_id: snapshot.service_order_id ?? null,
          from: dates[0],
          to: dates[dates.length - 1],
        })

        if (generation !== generationRef.current) return

        for (const date of dates) {
          if (signatures[date] && !signaturesRef.current.has(date)) {
            signaturesRef.current.set(date, signatures[date])
          }
        }
      } catch {
        // Sem assinatura, o dia é registrado na próxima verificação.
      }
    },
    [],
  )

  const runSearch = useCallback(
    async (generation: number, initial: boolean) => {
      if (loadingRef.current) return

      loadingRef.current = true
      setError(null)

      if (initial) {
        setLoading(true)
      } else {
        setLoadingMore(true)
      }

      try {
        while (generation === generationRef.current) {
          const searchWindow = windowRef.current
          const limitDate = addDaysToIsoDate(
            searchWindow.anchorDate,
            searchWindow.maxDays - 1,
          )
          const dates = calendarDatesUpTo(cursorRef.current, limitDate).slice(
            0,
            searchWindow.batchSize,
          )

          if (dates.length === 0) {
            markHasMore(false)
            return
          }

          const snapshot = requestSnapshotRef.current
          await captureSignatures(snapshot, dates, generation)

          if (generation !== generationRef.current) return

          const results = await mapWithConcurrency(
            dates,
            FETCH_CONCURRENCY,
            async (date): Promise<DayResult> => {
              const version = manualVersionsRef.current.get(date) ?? 0

              try {
                const estimate = await requestDay(snapshot, date, null, generation)

                if (generation !== generationRef.current) {
                  return { date, failed: false, available: false }
                }

                // Uma confirmação manual mais recente vence a resposta do lote.
                if ((manualVersionsRef.current.get(date) ?? 0) === version) {
                  applyEstimate(date, estimate)
                }

                return {
                  date,
                  failed: false,
                  available: daysRef.current.has(date),
                }
              } catch (err) {
                return {
                  date,
                  failed: true,
                  available: false,
                  message: isApiError(err) ? err.message : undefined,
                }
              }
            },
          )

          if (generation !== generationRef.current) return

          cursorRef.current = addDaysToIsoDate(dates[dates.length - 1], 1)
          searchedUntilRef.current = dates[dates.length - 1]

          const failures = results.filter((result) => result.failed)

          if (failures.length > 0) {
            failedDatesRef.current = uniqueSorted([
              ...failedDatesRef.current,
              ...failures.map((failure) => failure.date),
            ])
            setError(
              failures.length === dates.length
                ? failureMessage(
                    failures,
                    'Não foi possível consultar a disponibilidade. Tente novamente.',
                  )
                : failureMessage(
                    failures,
                    'Alguns dias não puderam ser consultados. Tente novamente para completar a lista.',
                  ),
            )
            markHasMore(cursorRef.current <= limitDate)
            commit()
            return
          }

          commit()

          if (results.some((result) => result.available)) {
            markHasMore(cursorRef.current <= limitDate)
            return
          }

          // Nenhum dia do lote tem horário: segue para o próximo lote até
          // encontrar disponibilidade ou atingir o limite da busca.
          if (cursorRef.current > limitDate) {
            markHasMore(false)
            return
          }
        }
      } finally {
        if (generation === generationRef.current) {
          loadingRef.current = false
          setLoading(false)
          setLoadingMore(false)
        }
      }
    },
    [applyEstimate, captureSignatures, commit, markHasMore, requestDay],
  )

  useEffect(() => {
    const nextWindow = resolveWindow(options)
    requestSnapshotRef.current = requestRef.current
    resetWindow(nextWindow)

    if (!enabled || !isValidIsoDate(nextWindow.anchorDate)) return

    setLoading(true)

    const generation = generationRef.current
    const timer = window.setTimeout(() => {
      void runSearch(generation, true)
    }, 500)

    return () => window.clearTimeout(timer)
    // A janela é reaberta apenas quando a data de referência ou os critérios mudam.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [anchorDate, requestKey, enabled, batchSize, maxDays, resetWindow, runSearch])

  const reload = useCallback(() => {
    const nextWindow = resolveWindow(optionsRef.current)

    requestSnapshotRef.current = requestRef.current
    resetWindow(nextWindow)

    if (!enabledRef.current || !isValidIsoDate(nextWindow.anchorDate)) return

    setLoading(true)
    void runSearch(generationRef.current, true)
  }, [resetWindow, runSearch])

  const loadMore = useCallback(() => {
    if (loadingRef.current || !hasMoreRef.current) return

    void runSearch(generationRef.current, false)
  }, [runSearch])

  const retryFailed = useCallback(async () => {
    if (loadingRef.current || failedDatesRef.current.length === 0) return

    const generation = generationRef.current
    const dates = uniqueSorted(failedDatesRef.current)
    let shouldContinueSearch = false

    loadingRef.current = true
    setLoadingMore(true)
    setError(null)

    try {
      const snapshot = requestSnapshotRef.current
      const results = await mapWithConcurrency(
        dates,
        FETCH_CONCURRENCY,
        async (date): Promise<DayResult> => {
          const version = manualVersionsRef.current.get(date) ?? 0

          try {
            const estimate = await requestDay(snapshot, date, null, generation)

            if (generation !== generationRef.current) {
              return { date, failed: false, available: false }
            }

            if ((manualVersionsRef.current.get(date) ?? 0) === version) {
              applyEstimate(date, estimate)
            }

            return {
              date,
              failed: false,
              available: daysRef.current.has(date),
            }
          } catch (err) {
            return {
              date,
              failed: true,
              available: false,
              message: isApiError(err) ? err.message : undefined,
            }
          }
        },
      )

      if (generation !== generationRef.current) return

      const failures = results.filter((result) => result.failed)

      failedDatesRef.current = failures.map((failure) => failure.date)

      if (failures.length > 0) {
        setError(
          failureMessage(
            failures,
            'Ainda não foi possível consultar alguns dias. Tente novamente.',
          ),
        )
      } else {
        const searchWindow = windowRef.current
        const limitDate = addDaysToIsoDate(
          searchWindow.anchorDate,
          searchWindow.maxDays - 1,
        )
        markHasMore(cursorRef.current <= limitDate)
        shouldContinueSearch = daysRef.current.size === 0 && hasMoreRef.current
      }

      commit()
    } finally {
      if (generation === generationRef.current) {
        loadingRef.current = false
        setLoadingMore(false)
      }
    }

    // Se a nova tentativa recuperou os dias que falharam mas nenhum deles tem
    // horário, retoma a busca a partir do cursor para não encerrar antes do limite.
    if (shouldContinueSearch && generation === generationRef.current) {
      void runSearch(generation, false)
    }
  }, [applyEstimate, commit, markHasMore, requestDay, runSearch])

  const refreshDay = useCallback(
    async (date: string, startAt: string) => {
      if (!enabledRef.current) return null

      const generation = generationRef.current
      const snapshot = requestSnapshotRef.current
      const sequence = (refreshSequencesRef.current.get(date) ?? 0) + 1

      refreshSequencesRef.current.set(date, sequence)
      dayStartsRef.current.set(date, startAt)

      const estimate = await requestDay(snapshot, date, startAt, generation)

      if (generation !== generationRef.current) return null

      // Uma confirmação mais recente para o mesmo dia sempre vence.
      if (refreshSequencesRef.current.get(date) !== sequence) return null

      applyEstimate(date, estimate, true)

      return estimate
    },
    [applyEstimate, requestDay],
  )

  const fetchDay = useCallback(
    async (date: string, startAt: string) => {
      if (!enabledRef.current) return null

      const generation = generationRef.current
      const snapshot = requestSnapshotRef.current
      const estimate = await requestDay(snapshot, date, startAt, generation)

      if (generation !== generationRef.current) return null

      return estimate
    },
    [requestDay],
  )

  /**
   * Verifica se a agenda do técnico mudou (ex.: outra pessoa marcou uma OS)
   * e recalcula apenas os dias afetados. A consulta de assinatura não envolve
   * endereços; o recálculo reaproveita a geocodificação e as rotas já em
   * cache na API.
   */
  const checkForChanges = useCallback(async () => {
    if (watchingRef.current || loadingRef.current || !enabledRef.current) return

    const snapshot = requestSnapshotRef.current
    const loadedDates = Array.from(estimatesRef.current.keys()).sort((a, b) =>
      a.localeCompare(b),
    )

    if (!snapshot.technician_id || loadedDates.length === 0) return

    const generation = generationRef.current
    watchingRef.current = true

    try {
      const signatures = await serviceOrdersService.agendaSignature({
        technician_id: snapshot.technician_id,
        service_order_id: snapshot.service_order_id ?? null,
        from: loadedDates[0],
        to: loadedDates[loadedDates.length - 1],
      })

      if (generation !== generationRef.current) return

      const changedDates: string[] = []

      for (const date of loadedDates) {
        const signature = signatures[date]

        if (!signature) continue

        const known = signaturesRef.current.get(date)

        if (known === undefined) {
          signaturesRef.current.set(date, signature)
        } else if (known !== signature) {
          changedDates.push(date)
        }
      }

      if (changedDates.length === 0) return

      const updatedDates: string[] = []

      await mapWithConcurrency(changedDates, FETCH_CONCURRENCY, async (date) => {
        const version = manualVersionsRef.current.get(date) ?? 0
        const startAt = dayStartsRef.current.get(date) ?? null

        try {
          const estimate = await requestDay(snapshot, date, startAt, generation)

          if (generation !== generationRef.current) return

          signaturesRef.current.set(date, signatures[date])

          // Uma confirmação manual mais recente já trouxe os dados atuais.
          if ((manualVersionsRef.current.get(date) ?? 0) !== version) return

          const before = availabilityKey(estimatesRef.current.get(date))

          applyEstimate(date, estimate)

          if (availabilityKey(estimate) !== before) {
            updatedDates.push(date)
          }
        } catch {
          // Mantém a assinatura antiga para tentar de novo na próxima verificação.
        }
      })

      if (generation !== generationRef.current || updatedDates.length === 0) return

      changeRevisionRef.current += 1
      setChange({
        dates: uniqueSorted(updatedDates),
        revision: changeRevisionRef.current,
      })
    } catch {
      // Falhas na verificação são silenciosas; a próxima rodada tenta de novo.
    } finally {
      watchingRef.current = false
    }
  }, [applyEstimate, requestDay])

  useEffect(() => {
    if (!enabled || watchIntervalMs <= 0) return

    const timer = window.setInterval(() => {
      if (typeof document !== 'undefined' && document.visibilityState === 'hidden') return

      void checkForChanges()
    }, watchIntervalMs)

    // Ao voltar para a aba, verifica imediatamente.
    const handleVisibility = () => {
      if (document.visibilityState === 'visible') void checkForChanges()
    }

    document.addEventListener('visibilitychange', handleVisibility)

    return () => {
      window.clearInterval(timer)
      document.removeEventListener('visibilitychange', handleVisibility)
    }
  }, [checkForChanges, enabled, requestKey, watchIntervalMs])

  const dismissChange = useCallback(() => setChange(null), [])

  const searchLimitDate = addDaysToIsoDate(anchorDate, Math.max(1, maxDays) - 1)

  return {
    days,
    estimates,
    loading,
    loadingMore,
    hasMore,
    error,
    failedCount,
    searchedUntil,
    searchLimitDate,
    reload,
    loadMore,
    retryFailed,
    refreshDay,
    fetchDay,
    change,
    dismissChange,
  }
}
