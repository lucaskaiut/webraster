import { useCallback, useEffect, useRef, useState } from 'react'
import { useSearchParams } from 'react-router'
import { Pause, Play, Terminal } from 'lucide-react'
import {
  Button,
  Page,
  PageContent,
  PageHeader,
  SearchSelect,
  type SearchSelectOption,
} from '@/shared/design-system'
import { equipmentsService } from '@/modules/equipments/services/equipments.service'
import { formatDateTimeWithSeconds } from '@/shared/utils/format'
import {
  deviceRawLogsService,
  type EquipmentRawLogLine,
} from '../services/device-raw-logs.service'

const POLL_MS = 2_000
const MAX_BUFFER_LINES = 2_000

function formatLogLine(entry: EquipmentRawLogLine): string {
  return `[${formatDateTimeWithSeconds(entry.received_at)}] ${entry.line}`
}

async function loadEquipmentOptions(search: string): Promise<SearchSelectOption[]> {
  const response = await equipmentsService.list({
    search: search || undefined,
    per_page: 25,
    is_active: true,
  })

  return response.data.map((equipment) => {
    const plate = equipment.vehicle?.plate
    const label = plate
      ? `${equipment.imei ?? '—'} · ${plate}`
      : (equipment.imei ?? equipment.model ?? equipment.id)

    return { value: equipment.id, label }
  })
}

export default function DeviceRawLogsPage() {
  const [searchParams] = useSearchParams()
  const initialEquipmentId = searchParams.get('equipment') ?? ''
  const initialEquipmentLabel = searchParams.get('equipment_label')
  const [equipmentId, setEquipmentId] = useState(initialEquipmentId)
  const [lines, setLines] = useState<string[]>([])
  const [paused, setPaused] = useState(false)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const afterIdRef = useRef(0)
  const logRef = useRef<HTMLDivElement>(null)
  const stickToBottomRef = useRef(true)

  const resetBuffer = useCallback(() => {
    afterIdRef.current = 0
    setLines([])
    setError(null)
    stickToBottomRef.current = true
  }, [])

  useEffect(() => {
    resetBuffer()
  }, [equipmentId, resetBuffer])

  useEffect(() => {
    if (!equipmentId || paused) {
      return
    }

    let cancelled = false

    const tick = async () => {
      try {
        setLoading((value) => value || afterIdRef.current === 0)
        const data = await deviceRawLogsService.tail(equipmentId, afterIdRef.current)

        if (cancelled || data.lines.length === 0) {
          setLoading(false)

          return
        }

        const formatted = data.lines.map(formatLogLine)

        setLines((previous) => {
          const next =
            afterIdRef.current === 0 ? formatted : [...previous, ...formatted]

          if (next.length <= MAX_BUFFER_LINES) {
            return next
          }

          return next.slice(next.length - MAX_BUFFER_LINES)
        })

        afterIdRef.current = data.last_id
        setError(null)
        setLoading(false)
      } catch {
        if (!cancelled) {
          setError('Não foi possível carregar os logs do equipamento.')
          setLoading(false)
        }
      }
    }

    void tick()
    const timer = window.setInterval(() => void tick(), POLL_MS)

    return () => {
      cancelled = true
      window.clearInterval(timer)
    }
  }, [equipmentId, paused])

  useEffect(() => {
    if (!stickToBottomRef.current || !logRef.current) {
      return
    }

    logRef.current.scrollTop = logRef.current.scrollHeight
  }, [lines])

  const handleScroll = () => {
    const element = logRef.current

    if (!element) {
      return
    }

    const distanceFromBottom = element.scrollHeight - element.scrollTop - element.clientHeight
    stickToBottomRef.current = distanceFromBottom < 48
  }

  return (
      <Page>
        <PageHeader
          title="Log bruto do dispositivo"
          description="Tramas brutas de todos os protocolos (texto ou hex), via Traccar — tail a cada 2 segundos."
          breadcrumb={[
            { label: 'Monitoramento', to: '/monitoring' },
            { label: 'Log do dispositivo' },
          ]}
        />

        <PageContent>
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end">
            <div className="min-w-0 flex-1">
              <SearchSelect
                label="Equipamento"
                value={equipmentId}
                onChange={(value) => setEquipmentId(value)}
                loadOptions={loadEquipmentOptions}
                placeholder="Buscar por IMEI ou veículo..."
                emptyMessage="Nenhum equipamento encontrado"
                defaultOption={
                  initialEquipmentId && initialEquipmentLabel
                    ? { value: initialEquipmentId, label: initialEquipmentLabel }
                    : null
                }
              />
            </div>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              disabled={!equipmentId}
              onClick={() => setPaused((value) => !value)}
              className="shrink-0"
            >
              {paused ? (
                <>
                  <Play className="size-4" aria-hidden="true" />
                  Retomar tail
                </>
              ) : (
                <>
                  <Pause className="size-4" aria-hidden="true" />
                  Pausar tail
                </>
              )}
            </Button>
          </div>

          <div className="mt-4 flex min-h-0 flex-1 flex-col">
            <div className="mb-2 flex items-center gap-2 text-xs text-muted">
              <Terminal className="size-3.5" aria-hidden="true" />
              {equipmentId
                ? paused
                  ? 'Tail pausado'
                  : `Atualizando a cada ${POLL_MS / 1000}s`
                : 'Selecione um equipamento para iniciar o tail'}
              {loading && equipmentId ? ' · carregando…' : null}
            </div>

            <div
              ref={logRef}
              onScroll={handleScroll}
              className="min-h-[min(70vh,640px)] flex-1 overflow-y-auto rounded-2xl bg-[#0d1117] p-4 font-mono text-[12px] leading-relaxed text-[#c9d1d9] shadow-card"
              role="log"
              aria-live="polite"
              aria-label="Log bruto do dispositivo"
            >
              {!equipmentId ? (
                <p className="text-[#8b949e]"># aguardando seleção do equipamento</p>
              ) : lines.length === 0 && !loading ? (
                <p className="text-[#8b949e]">
                  # nenhuma trama registrada ainda — aguardando posições do Traccar (atributo raw)
                </p>
              ) : (
                <pre className="whitespace-pre-wrap break-all">{lines.join('\n')}</pre>
              )}
              {error ? <p className="mt-3 text-[#f85149]">{error}</p> : null}
            </div>
          </div>
        </PageContent>
      </Page>
  )
}
