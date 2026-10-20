import { useState } from 'react'
import { Download, Image as ImageIcon } from 'lucide-react'
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
} from '@/shared/design-system'
import { toast } from '@/shared/stores/toast.store'
import { downloadBlob } from '@/shared/utils/report-export'
import type {
  ServiceOrder,
  ServiceOrderChecklist,
  ServiceOrderChecklistQuestion,
} from '@/shared/types/models'
import { serviceOrdersService } from '../services/service-orders.service'
import { checklistStatusLabel } from '@/modules/checklists/lib/labels'

export function ServiceOrderChecklistsCard({ order }: { order: ServiceOrder }) {
  const checklists = order.checklists ?? []

  if (checklists.length === 0) return null

  return (
    <Card>
      <CardHeader
        title="Checklists"
        description="Respostas e fotografias registradas durante o atendimento."
      />
      <CardContent className="space-y-5">
        {checklists.map((checklist) => (
          <ChecklistBlock key={checklist.id} order={order} checklist={checklist} />
        ))}
      </CardContent>
    </Card>
  )
}

function ChecklistBlock({
  order,
  checklist,
}: {
  order: ServiceOrder
  checklist: ServiceOrderChecklist
}) {
  return (
    <div className="space-y-3 rounded-xl bg-surface-2/60 p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="min-w-0">
          <p className="font-medium text-foreground">{checklist.name}</p>
          <p className="text-[13px] text-muted">
            {checklist.type_label ?? (checklist.type === 'initial' ? 'Inicial' : 'Final')}
          </p>
        </div>
        <Badge variant={checklist.status === 'completed' ? 'success' : 'warning'}>
          {checklist.status_label ?? checklistStatusLabel(checklist.status)}
        </Badge>
      </div>

      {checklist.questions.map((question, index) => (
        <QuestionBlock
          key={question.id}
          orderId={order.id}
          checklistId={checklist.id}
          question={question}
          index={index}
        />
      ))}
    </div>
  )
}

function QuestionBlock({
  orderId,
  checklistId,
  question,
  index,
}: {
  orderId: string
  checklistId: string
  question: ServiceOrderChecklistQuestion
  index: number
}) {
  const [busyPhoto, setBusyPhoto] = useState<string | null>(null)
  const selectedOptionIds = question.answer?.option_ids ?? []
  const selectedLabels = question.options
    .filter((option) => selectedOptionIds.includes(option.id))
    .map((option) => option.label)
  const answerText = question.answer?.text_answer?.trim()
  const photos = question.answer?.photos ?? []

  const downloadPhoto = async (photoId: string, fileName: string) => {
    setBusyPhoto(photoId)

    try {
      downloadBlob(
        await serviceOrdersService.checklistPhoto(orderId, checklistId, question.id, photoId),
        fileName,
      )
    } catch {
      toast.error('Não foi possível baixar a fotografia do checklist.')
    } finally {
      setBusyPhoto(null)
    }
  }

  return (
    <div className="space-y-2 rounded-lg bg-surface px-3 py-2.5">
      <p className="text-sm font-medium text-foreground">
        {index + 1}. {question.prompt}
      </p>

      {answerText ? <p className="text-sm text-foreground">{answerText}</p> : null}

      {selectedLabels.length > 0 ? (
        <ul className="list-inside list-disc text-sm text-foreground">
          {selectedLabels.map((label) => (
            <li key={label}>{label}</li>
          ))}
        </ul>
      ) : null}

      {!answerText && selectedLabels.length === 0 ? (
        <p className="text-[13px] text-muted">Sem resposta registrada.</p>
      ) : null}

      {photos.length > 0 ? (
        <div className="flex flex-wrap gap-2">
          {photos.map((photo) => (
            <Button
              key={photo.id}
              variant="secondary"
              size="sm"
              loading={busyPhoto === photo.id}
              onClick={() => void downloadPhoto(photo.id, photo.original_name)}
            >
              <ImageIcon className="size-4" />
              {photo.original_name}
              <Download className="size-3.5" />
            </Button>
          ))}
        </div>
      ) : null}
    </div>
  )
}
