import { useFieldArray, useForm, useWatch, type UseFormReturn } from 'react-hook-form'
import { ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Section,
  SelectField,
  SwitchField,
  TextareaField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm, formResolver } from '@/shared/utils/forms'
import {
  CHECKLIST_ANSWER_TYPE_OPTIONS,
  CHECKLIST_PHOTO_RULE_OPTIONS,
  CHECKLIST_TYPE_OPTIONS,
} from '../lib/labels'
import {
  checklistSchema,
  type ChecklistFormValues,
} from '../schemas/checklist.schema'
import type { ChecklistPayload } from '../services/checklists.service'

interface ChecklistFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<ChecklistFormValues>
  submitting: boolean
  onSubmit: (payload: ChecklistPayload) => Promise<unknown>
}

const EMPTY_QUESTION: ChecklistFormValues['questions'][number] = {
  prompt: '',
  answer_type: 'text',
  photo_rule: 'optional',
  options: [],
}

export function ChecklistForm({
  mode,
  defaultValues,
  submitting,
  onSubmit,
}: ChecklistFormProps) {
  const form = useForm<ChecklistFormValues>({
    resolver: formResolver<ChecklistFormValues>(checklistSchema),
    defaultValues: {
      name: '',
      description: '',
      type: 'initial',
      is_active: true,
      questions: [],
      ...defaultValues,
    },
  })

  const questions = useFieldArray({ control: form.control, name: 'questions' })

  const handleSubmit = async (values: ChecklistFormValues) => {
    const payload: ChecklistPayload = {
      name: values.name.trim(),
      description: values.description.trim() || null,
      type: values.type,
      is_active: values.is_active,
      questions: values.questions.map((question) => ({
        prompt: question.prompt.trim(),
        answer_type: question.answer_type,
        photo_rule: question.photo_rule,
        options:
          question.answer_type === 'text'
            ? []
            : question.options.map((option) => ({ label: option.label.trim() })),
      })),
    }

    try {
      await onSubmit(payload)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Card>
      <CardContent>
        <Form form={form} onSubmit={handleSubmit} className="space-y-8">
          <Section title="Dados do checklist">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="name" label="Nome" required className="sm:col-span-2" />
              <SelectField name="type" label="Tipo" required options={CHECKLIST_TYPE_OPTIONS} />
              <SwitchField name="is_active" label="Checklist ativo" />
              <TextareaField
                name="description"
                label="Descrição"
                rows={3}
                className="sm:col-span-2"
                placeholder="Explique a finalidade deste checklist"
              />
            </div>
          </Section>

          <Section
            title="Perguntas"
            description="A ordem das perguntas nesta tela é a ordem exibida ao técnico. Para perguntas de seleção, cadastre as opções disponíveis."
            actions={
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => questions.append({ ...EMPTY_QUESTION, options: [] })}
              >
                <Plus className="size-4" />
                Adicionar pergunta
              </Button>
            }
          >
            {questions.fields.length === 0 ? (
              <div className="rounded-xl bg-surface-2/60 p-5 text-sm text-muted">
                Nenhuma pergunta cadastrada. Adicione a primeira pergunta para montar o checklist.
              </div>
            ) : (
              <div className="space-y-4">
                {questions.fields.map((field, index) => (
                  <QuestionEditor
                    key={field.id}
                    form={form}
                    index={index}
                    canMoveUp={index > 0}
                    canMoveDown={index < questions.fields.length - 1}
                    onMoveUp={() => questions.move(index, index - 1)}
                    onMoveDown={() => questions.move(index, index + 1)}
                    onRemove={() => questions.remove(index)}
                  />
                ))}
              </div>
            )}
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/checklists" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar checklist' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}

function QuestionEditor({
  form,
  index,
  canMoveUp,
  canMoveDown,
  onMoveUp,
  onMoveDown,
  onRemove,
}: {
  form: UseFormReturn<ChecklistFormValues>
  index: number
  canMoveUp: boolean
  canMoveDown: boolean
  onMoveUp: () => void
  onMoveDown: () => void
  onRemove: () => void
}) {
  const answerType = useWatch({
    control: form.control,
    name: `questions.${index}.answer_type`,
  })
  const options = useFieldArray({
    control: form.control,
    name: `questions.${index}.options`,
  })
  const optionsError = form.formState.errors.questions?.[index]?.options?.message

  return (
    <div className="space-y-4 rounded-xl bg-surface-2/60 p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <p className="text-sm font-semibold text-foreground">Pergunta {index + 1}</p>
        <div className="flex items-center gap-1">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            disabled={!canMoveUp}
            onClick={onMoveUp}
            aria-label="Mover pergunta para cima"
          >
            <ChevronUp className="size-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            disabled={!canMoveDown}
            onClick={onMoveDown}
            aria-label="Mover pergunta para baixo"
          >
            <ChevronDown className="size-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onRemove}
            aria-label="Remover pergunta"
            className="text-danger hover:bg-danger-soft hover:text-danger"
          >
            <Trash2 className="size-4" />
          </Button>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_14rem_14rem]">
        <TextField
          name={`questions.${index}.prompt`}
          label="Enunciado"
          required
          placeholder="O que deve ser verificado?"
        />
        <SelectField
          name={`questions.${index}.answer_type`}
          label="Tipo de resposta"
          required
          options={CHECKLIST_ANSWER_TYPE_OPTIONS}
        />
        <SelectField
          name={`questions.${index}.photo_rule`}
          label="Regra de fotografia"
          required
          options={CHECKLIST_PHOTO_RULE_OPTIONS}
        />
      </div>

      {answerType !== 'text' && (
        <div className="space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="text-[13px] font-medium text-foreground">Opções de resposta</p>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => options.append({ label: '' })}
            >
              <Plus className="size-4" />
              Adicionar opção
            </Button>
          </div>

          {options.fields.length === 0 ? (
            <p className="text-[13px] text-muted">
              Adicione pelo menos uma opção para esta pergunta.
            </p>
          ) : (
            <div className="space-y-2">
              {options.fields.map((option, optionIndex) => (
                <div key={option.id} className="flex items-end gap-2">
                  <TextField
                    name={`questions.${index}.options.${optionIndex}.label`}
                    label={`Opção ${optionIndex + 1}`}
                    required
                    className="min-w-0 flex-1"
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={optionIndex === 0}
                    onClick={() => options.move(optionIndex, optionIndex - 1)}
                    aria-label="Mover opção para cima"
                  >
                    <ChevronUp className="size-4" />
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={optionIndex === options.fields.length - 1}
                    onClick={() => options.move(optionIndex, optionIndex + 1)}
                    aria-label="Mover opção para baixo"
                  >
                    <ChevronDown className="size-4" />
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => options.remove(optionIndex)}
                    aria-label="Remover opção"
                    className="text-danger hover:bg-danger-soft hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                </div>
              ))}
            </div>
          )}

          {optionsError && (
            <p className="text-[13px] text-danger" role="alert">
              {optionsError}
            </p>
          )}
        </div>
      )}
    </div>
  )
}
