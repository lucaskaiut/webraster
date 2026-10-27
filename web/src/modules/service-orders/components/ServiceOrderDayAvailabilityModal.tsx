import { Alert, Button, Modal, Spinner } from '@/shared/design-system'
import {
  formatFullDay,
  formatTime,
  sameInstant,
  type ScheduleDayView,
  type ScheduleSlotView,
} from '../lib/schedule'

const MODAL_SCROLLBAR_CLASSES =
  'overscroll-contain [scrollbar-color:var(--app-surface-3)_transparent] [scrollbar-gutter:stable] [scrollbar-width:thin] [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-button]:hidden [&::-webkit-scrollbar-corner]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-surface-3 [&::-webkit-scrollbar-thumb:hover]:bg-muted/60 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-surface-2'

interface ServiceOrderDayAvailabilityModalProps {
  open: boolean
  day: ScheduleDayView | null
  selectedStart: string | null
  confirmingStart: string | null
  disabled: boolean
  warning: string | null
  onClose: () => void
  onSelect: (slot: ScheduleSlotView) => void
}

function SlotGrid({
  title,
  slots,
  timezone,
  selectedStart,
  confirmingStart,
  disabled,
  onSelect,
}: {
  title: string
  slots: ScheduleSlotView[]
  timezone: string
  selectedStart: string | null
  confirmingStart: string | null
  disabled: boolean
  onSelect: (slot: ScheduleSlotView) => void
}) {
  if (slots.length === 0) return null

  return (
    <section aria-label={title}>
      <h3 className="text-xs font-medium tracking-wide text-muted uppercase">{title}</h3>
      <div className="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
        {slots.map((slot) => {
          const selected = Boolean(selectedStart && sameInstant(slot.start, selectedStart))
          const confirming = Boolean(confirmingStart && sameInstant(slot.start, confirmingStart))
          const start = formatTime(slot.start, timezone)
          const end = formatTime(slot.end, timezone)

          return (
            <Button
              key={slot.start}
              type="button"
              size="sm"
              variant={selected ? 'primary' : 'secondary'}
              className="h-9 justify-center tabular-nums"
              aria-pressed={selected}
              aria-label={`${selected ? 'Horário selecionado' : 'Selecionar'} ${start} às ${end}`}
              disabled={disabled || Boolean(confirmingStart)}
              onClick={() => onSelect(slot)}>
              {confirming && <Spinner className="size-3.5" />}
              {start}
            </Button>
          )
        })}
      </div>
    </section>
  )
}

export function ServiceOrderDayAvailabilityModal({
  open,
  day,
  selectedStart,
  confirmingStart,
  disabled,
  warning,
  onClose,
  onSelect,
}: ServiceOrderDayAvailabilityModalProps) {
  const title = day ? formatFullDay(day.date) : 'Horários disponíveis'

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      description={
        day
          ? `${day.availableSlots.length} horário${
              day.availableSlots.length === 1 ? '' : 's'
            } disponível${day.availableSlots.length === 1 ? '' : 'is'} · escolha um para atualizar a OS`
          : undefined
      }
      size="md"
      dismissable={!confirmingStart}
      bodyClassName={MODAL_SCROLLBAR_CLASSES}
      footer={
        <Button
          type="button"
          variant="secondary"
          onClick={onClose}
          disabled={Boolean(confirmingStart)}>
          Fechar
        </Button>
      }>
      {!day ? (
        <p className="text-sm text-muted">
          Este dia não possui mais horários disponíveis com os dados atuais.
        </p>
      ) : (
        <div className="space-y-5">
          {warning && (
            <Alert variant="warning" title="Revise o horário selecionado">
              {warning}
            </Alert>
          )}

          <SlotGrid
            title="Manhã"
            slots={day.morningSlots}
            timezone={day.timezone}
            selectedStart={selectedStart}
            confirmingStart={confirmingStart}
            disabled={disabled}
            onSelect={onSelect}
          />
          <SlotGrid
            title="Tarde"
            slots={day.afternoonSlots}
            timezone={day.timezone}
            selectedStart={selectedStart}
            confirmingStart={confirmingStart}
            disabled={disabled}
            onSelect={onSelect}
          />
        </div>
      )}
    </Modal>
  )
}
