import { z } from 'zod'

const optionSchema = z.object({
  label: z.string().trim().min(1, 'Informe o texto da opção'),
})

const questionSchema = z
  .object({
    prompt: z.string().trim().min(1, 'Informe o enunciado'),
    answer_type: z.enum(['text', 'single_choice', 'multiple_choice']),
    photo_rule: z.enum(['required', 'optional', 'none']),
    options: z.array(optionSchema),
  })
  .superRefine((question, ctx) => {
    if (question.answer_type !== 'text' && question.options.length === 0) {
      ctx.addIssue({
        code: 'custom',
        path: ['options'],
        message: 'Adicione ao menos uma opção',
      })
    }
  })

export const checklistSchema = z.object({
  name: z.string().trim().min(1, 'Informe o nome'),
  description: z.string(),
  type: z.enum(['initial', 'final']),
  is_active: z.boolean(),
  questions: z.array(questionSchema),
})

export type ChecklistFormValues = z.infer<typeof checklistSchema>
