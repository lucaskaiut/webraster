import { useEffect, useState } from 'react'
import { Button, Field, Input, Modal, Switch, Textarea } from '@/shared/design-system'
import type { CrmPipelineStage, CrmPipelineStageInput } from '../lib/types'

interface CrmStageFormModalProps {
  open: boolean
  onClose: () => void
  title: string
  initial?: CrmPipelineStage | null
  saving?: boolean
  error?: string | null
  onSubmit: (payload: CrmPipelineStageInput) => void
}

const defaultForm = (): CrmPipelineStageInput => ({
  name: '',
  description: '',
  color: '#6366f1',
  is_initial: false,
  is_final: false,
  is_won: false,
  is_lost: false,
  active: true,
})

export function CrmStageFormModal({
  open,
  onClose,
  title,
  initial,
  saving,
  error,
  onSubmit,
}: CrmStageFormModalProps) {
  const [form, setForm] = useState<CrmPipelineStageInput>(defaultForm)

  useEffect(() => {
    if (!open) {
      return
    }
    if (initial) {
      setForm({
        name: initial.name,
        description: initial.description ?? '',
        color: initial.color ?? '#6366f1',
        is_initial: initial.is_initial,
        is_final: initial.is_final,
        is_won: initial.is_won,
        is_lost: initial.is_lost,
        active: initial.active,
      })
    } else {
      setForm(defaultForm())
    }
  }, [open, initial])

  const handleSubmit = () => {
    const name = form.name.trim()
    if (!name) {
      return
    }
    onSubmit({
      ...form,
      name,
      description: form.description?.trim() || null,
      color: form.color?.trim() || null,
    })
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      size="md"
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={saving}>
            Cancelar
          </Button>
          <Button onClick={handleSubmit} disabled={saving || !form.name.trim()}>
            {saving ? 'Salvando…' : 'Salvar'}
          </Button>
        </>
      }
    >
      {error ? (
        <p className="mb-4 rounded-xl bg-danger/10 px-3 py-2 text-sm text-danger">{error}</p>
      ) : null}

      <div className="space-y-4">
        <Field label="Nome" required>
          <Input
            value={form.name}
            onChange={(e) => setForm((s) => ({ ...s, name: e.target.value }))}
            placeholder="Ex.: Qualificação"
          />
        </Field>

        <Field label="Descrição" hint="Opcional — visível só na gestão do funil.">
          <Textarea
            value={form.description ?? ''}
            onChange={(e) => setForm((s) => ({ ...s, description: e.target.value }))}
            rows={2}
          />
        </Field>

        <Field label="Cor no kanban">
          <div className="flex items-center gap-3">
            <input
              type="color"
              value={form.color?.startsWith('#') ? form.color : '#6366f1'}
              onChange={(e) => setForm((s) => ({ ...s, color: e.target.value }))}
              className="size-10 cursor-pointer rounded-lg bg-surface-2 shadow-card"
              aria-label="Cor da etapa"
            />
            <Input
              value={form.color ?? ''}
              onChange={(e) => setForm((s) => ({ ...s, color: e.target.value }))}
              placeholder="#6366f1"
              className="flex-1"
            />
          </div>
        </Field>

        <div className="space-y-3 rounded-xl bg-surface-2/80 p-4">
          <p className="text-sm font-medium text-foreground">Comportamento</p>
          <label className="flex items-center justify-between gap-4 text-sm">
            <span className="text-muted">Etapa inicial (novos leads)</span>
            <Switch
              checked={Boolean(form.is_initial)}
              onCheckedChange={(checked) => setForm((s) => ({ ...s, is_initial: checked }))}
            />
          </label>
          <label className="flex items-center justify-between gap-4 text-sm">
            <span className="text-muted">Etapa final</span>
            <Switch
              checked={Boolean(form.is_final)}
              onCheckedChange={(checked) => setForm((s) => ({ ...s, is_final: checked }))}
            />
          </label>
          <label className="flex items-center justify-between gap-4 text-sm">
            <span className="text-muted">Marca como ganho</span>
            <Switch
              checked={Boolean(form.is_won)}
              onCheckedChange={(checked) => setForm((s) => ({ ...s, is_won: checked }))}
            />
          </label>
          <label className="flex items-center justify-between gap-4 text-sm">
            <span className="text-muted">Marca como perdido</span>
            <Switch
              checked={Boolean(form.is_lost)}
              onCheckedChange={(checked) => setForm((s) => ({ ...s, is_lost: checked }))}
            />
          </label>
          {initial ? (
            <label className="flex items-center justify-between gap-4 text-sm">
              <span className="text-muted">Etapa ativa no kanban</span>
              <Switch
                checked={form.active !== false}
                onCheckedChange={(checked) => setForm((s) => ({ ...s, active: checked }))}
              />
            </label>
          ) : null}
        </div>
      </div>
    </Modal>
  )
}
