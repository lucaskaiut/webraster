import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Section,
  SwitchField,
  TextareaField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { StockPayload } from '../services/stocks.service'
import { stockSchema, type StockFormValues } from '../schemas/stock.schema'

interface StockFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<StockFormValues>
  submitting: boolean
  onSubmit: (payload: StockPayload) => Promise<unknown>
}

export function StockForm({ mode, defaultValues, submitting, onSubmit }: StockFormProps) {
  const form = useForm<StockFormValues>({
    resolver: zodResolver(stockSchema),
    defaultValues: {
      name: '',
      description: '',
      is_active: true,
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: StockFormValues) => {
    const payload: StockPayload = {
      name: values.name.trim(),
      description: values.description.trim() || null,
      is_active: values.is_active,
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
          <Section
            title="Dados do estoque"
            description="Cada equipamento pertence a um único estoque por vez. O técnico usa o estoque vinculado para selecionar equipamentos nas OS.">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="name"
                label="Nome"
                required
                className="sm:col-span-2"
                placeholder="Ex.: Estoque central, Veículos de instalação..."
              />
              <TextareaField
                name="description"
                label="Descrição"
                className="sm:col-span-2"
                rows={4}
                placeholder="Localização, responsável ou observações sobre o estoque"
                hint="Opcional"
              />
              <SwitchField
                name="is_active"
                label="Estoque ativo"
                hint="Estoques inativos não aparecem para novas seleções, mas permanecem visíveis em registros existentes."
                className="sm:col-span-2"
              />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/stocks" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar estoque' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
