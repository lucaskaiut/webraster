import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Form, Modal, TextField } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { User } from '@/shared/types/models'
import { RolesField } from '@/modules/users/components/RolesField'
import type { ClientUserUpdatePayload } from '../services/clients.service'
import {
  updateClientUserSchema,
  type ClientUserUpdateFormValues,
} from '../schemas/client.schema'

interface ClientUserEditModalProps {
  open: boolean
  user: User | null
  submitting: boolean
  onClose: () => void
  onSubmit: (payload: ClientUserUpdatePayload) => Promise<unknown>
}

export function ClientUserEditModal({
  open,
  user,
  submitting,
  onClose,
  onSubmit,
}: ClientUserEditModalProps) {
  const form = useForm<ClientUserUpdateFormValues>({
    resolver: zodResolver(updateClientUserSchema),
    defaultValues: {
      name: '',
      email: '',
      phone: '',
      password: '',
      role_ids: [],
    },
  })

  useEffect(() => {
    if (!open || !user) return

    form.reset({
      name: user.name,
      email: user.email,
      phone: user.phone ?? '',
      password: '',
      role_ids: user.roles?.map((role) => role.id) ?? [],
    })
  }, [open, user, form])

  const handleSubmit = async (values: ClientUserUpdateFormValues) => {
    try {
      await onSubmit({
        name: values.name,
        email: values.email,
        phone: values.phone || null,
        ...(values.password ? { password: values.password } : {}),
        role_ids: values.role_ids,
      })
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Editar usuário"
      description={
        user
          ? `Atualize os dados de acesso de ${user.name}. Deixe a senha em branco para mantê-la.`
          : undefined
      }
      size="md"
    >
      <Form form={form} onSubmit={handleSubmit} className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField name="name" label="Nome" required className="sm:col-span-2" />
          <TextField name="email" label="E-mail" type="email" required />
          <TextField name="phone" label="Telefone" />
          <TextField
            name="password"
            label="Nova senha"
            type="password"
            autoComplete="new-password"
            hint="Em branco, mantém a senha atual."
            className="sm:col-span-2"
          />
        </div>

        <RolesField />

        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose} disabled={submitting}>
            Cancelar
          </Button>
          <Button type="submit" loading={submitting}>
            Salvar alterações
          </Button>
        </div>
      </Form>
    </Modal>
  )
}
