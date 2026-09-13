import { useRef, useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Modal,
  RichTextEditor,
  Section,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm, formResolver } from '@/shared/utils/forms'
import type { ContractPayload } from '../services/contracts.service'
import { contractSchema, type ContractFormValues } from '../schemas/contract.schema'
import {
  CONTRACT_VARIABLES,
  getFictionalContractValues,
  substituteContractVariables,
} from '../lib/variables'

interface ContractFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<ContractFormValues>
  submitting: boolean
  onSubmit: (payload: ContractPayload) => Promise<unknown>
}

export function ContractForm({ mode, defaultValues, submitting, onSubmit }: ContractFormProps) {
  const [previewOpen, setPreviewOpen] = useState(false)
  const insertRef = useRef<(content: string) => void>(() => {})

  const form = useForm<ContractFormValues>({
    resolver: formResolver<ContractFormValues>(contractSchema),
    defaultValues: {
      name: '',
      body: '',
      ...defaultValues,
    },
  })

  const bodyValue = form.watch('body')

  const handleSubmit = async (values: ContractFormValues) => {
    const payload: ContractPayload = {
      name: values.name,
      body: values.body,
    }

    try {
      await onSubmit(payload)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  const insertVariable = (token: string) => {
    insertRef.current(token)
  }

  const previewHtml = substituteContractVariables(bodyValue || '', getFictionalContractValues())

  return (
    <>
      <Card>
        <CardContent>
          <Form form={form} onSubmit={handleSubmit} className="space-y-8">
            <Section title="Dados do contrato">
              <TextField name="name" label="Nome" required />
            </Section>

            <Section
              title="Conteúdo"
              description="Use o editor para redigir o contrato e insira variáveis que serão substituídas pelos dados do cliente."
            >
              <div className="mb-4 flex flex-wrap gap-2">
                {CONTRACT_VARIABLES.map((variable) => (
                  <button
                    key={variable.key}
                    type="button"
                    title={variable.description}
                    className="rounded-md bg-surface-2 px-2.5 py-1 text-xs font-medium text-muted transition-colors hover:bg-surface-3 hover:text-foreground"
                    onClick={() => insertVariable(variable.token)}
                  >
                    {variable.token}
                  </button>
                ))}
              </div>

              <Controller
                control={form.control}
                name="body"
                render={({ field, fieldState }) => (
                  <RichTextEditor
                    label="Texto do contrato"
                    value={field.value}
                    onChange={field.onChange}
                    error={fieldState.error?.message}
                    placeholder="Redija o contrato e insira variáveis como {{NOME_CLIENTE}}..."
                    toolbarEnd={({ insertContent }) => {
                      insertRef.current = insertContent

                      return (
                        <label className="ml-auto flex items-center gap-2 text-xs text-muted">
                          <span className="sr-only">Inserir variável</span>
                          <select
                            className="h-8 max-w-[14rem] rounded-md bg-surface-1 px-2 text-xs text-foreground"
                            defaultValue=""
                            onChange={(event) => {
                              const token = event.target.value
                              if (!token) return
                              insertContent(token)
                              event.target.value = ''
                            }}
                          >
                            <option value="">Inserir variável...</option>
                            {CONTRACT_VARIABLES.map((variable) => (
                              <option key={variable.key} value={variable.token}>
                                {variable.label} ({variable.key})
                              </option>
                            ))}
                          </select>
                        </label>
                      )
                    }}
                  />
                )}
              />
            </Section>

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
              <Button type="button" variant="secondary" onClick={() => setPreviewOpen(true)}>
                Pré-visualizar
              </Button>
              <div className="flex flex-col-reverse gap-2 sm:flex-row">
                <ButtonLink to="/contracts" variant="secondary">
                  Cancelar
                </ButtonLink>
                <Button type="submit" loading={submitting}>
                  {mode === 'create' ? 'Criar contrato' : 'Salvar alterações'}
                </Button>
              </div>
            </div>
          </Form>
        </CardContent>
      </Card>

      <Modal
        open={previewOpen}
        onClose={() => setPreviewOpen(false)}
        title="Pré-visualização do contrato"
        description="Variáveis substituídas por dados fictícios de exemplo."
        size="xl"
        footer={
          <Button type="button" variant="secondary" onClick={() => setPreviewOpen(false)}>
            Fechar
          </Button>
        }
      >
        <div
          className="prose prose-sm max-w-none text-foreground"
          dangerouslySetInnerHTML={{ __html: previewHtml || '<p><em>Sem conteúdo.</em></p>' }}
        />
      </Modal>
    </>
  )
}
