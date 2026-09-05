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
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { EquipmentPayload } from '../services/equipments.service'
import { equipmentSchema, type EquipmentFormValues } from '../schemas/equipment.schema'

interface EquipmentFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<EquipmentFormValues>
  submitting: boolean
  onSubmit: (payload: EquipmentPayload) => Promise<unknown>
}

export function EquipmentForm({ mode, defaultValues, submitting, onSubmit }: EquipmentFormProps) {
  const form = useForm<EquipmentFormValues>({
    resolver: zodResolver(equipmentSchema),
    defaultValues: {
      imei: '',
      model: '',
      iccid: '',
      carrier: '',
      is_active: true,
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: EquipmentFormValues) => {
    const payload: EquipmentPayload = {
      imei: values.imei,
      model: values.model || null,
      iccid: values.iccid || null,
      carrier: values.carrier || null,
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
          <Section title="Identificação do rastreador">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="imei" label="IMEI" required className="sm:col-span-2" />
              <TextField name="model" label="Modelo" />
              <TextField name="carrier" label="Operadora" />
              <TextField name="iccid" label="ICCID" className="sm:col-span-2" />
              <SwitchField name="is_active" label="Equipamento ativo" />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/equipments" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar equipamento' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
