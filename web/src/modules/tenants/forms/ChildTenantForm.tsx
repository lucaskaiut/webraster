import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Section,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { onlyDigits } from '@/shared/utils/document'
import { maskCpfCnpj, maskPhone } from '@/shared/utils/mask'
import type {
  CreateChildTenantPayload,
  UpdateChildTenantPayload,
} from '../services/tenants.service'
import {
  createChildTenantSchema,
  updateChildTenantSchema,
  type CreateChildTenantFormValues,
  type UpdateChildTenantFormValues,
} from '../schemas/tenant.schema'

type CreateProps = {
  mode: 'create'
  submitting: boolean
  onSubmit: (payload: CreateChildTenantPayload) => Promise<unknown>
  defaultValues?: Partial<CreateChildTenantFormValues>
}

type EditProps = {
  mode: 'edit'
  submitting: boolean
  onSubmit: (payload: UpdateChildTenantPayload) => Promise<unknown>
  defaultValues?: Partial<UpdateChildTenantFormValues>
}

type ChildTenantFormProps = CreateProps | EditProps

export function ChildTenantForm(props: ChildTenantFormProps) {
  const { mode, submitting } = props

  if (mode === 'create') {
    return (
      <CreateForm
        submitting={submitting}
        defaultValues={props.defaultValues}
        onSubmit={props.onSubmit}
      />
    )
  }

  return (
    <EditForm
      submitting={submitting}
      defaultValues={props.defaultValues}
      onSubmit={props.onSubmit}
    />
  )
}

function CreateForm({
  submitting,
  defaultValues,
  onSubmit,
}: {
  submitting: boolean
  defaultValues?: Partial<CreateChildTenantFormValues>
  onSubmit: (payload: CreateChildTenantPayload) => Promise<unknown>
}) {
  const form = useForm<CreateChildTenantFormValues>({
    resolver: zodResolver(createChildTenantSchema),
    defaultValues: {
      tenant: { name: '', document: '', email: '', phone: '' },
      user: { name: '', email: '', password: '' },
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: CreateChildTenantFormValues) => {
    const payload: CreateChildTenantPayload = {
      tenant: {
        name: values.tenant.name,
        document: onlyDigits(values.tenant.document),
        email: values.tenant.email,
        phone: onlyDigits(values.tenant.phone),
      },
      user: {
        name: values.user.name,
        email: values.user.email,
        password: values.user.password,
      },
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
          <TenantFields />
          <AdminFields />
          <FormActions submitting={submitting} submitLabel="Criar empresa" />
        </Form>
      </CardContent>
    </Card>
  )
}

function EditForm({
  submitting,
  defaultValues,
  onSubmit,
}: {
  submitting: boolean
  defaultValues?: Partial<UpdateChildTenantFormValues>
  onSubmit: (payload: UpdateChildTenantPayload) => Promise<unknown>
}) {
  const form = useForm<UpdateChildTenantFormValues>({
    resolver: zodResolver(updateChildTenantSchema),
    defaultValues: {
      tenant: {
        name: defaultValues?.tenant?.name ?? '',
        email: defaultValues?.tenant?.email ?? '',
        document: maskCpfCnpj(defaultValues?.tenant?.document ?? ''),
        phone: maskPhone(defaultValues?.tenant?.phone ?? ''),
      },
    },
  })

  const handleSubmit = async (values: UpdateChildTenantFormValues) => {
    const payload: UpdateChildTenantPayload = {
      tenant: {
        name: values.tenant.name,
        document: onlyDigits(values.tenant.document),
        email: values.tenant.email,
        phone: onlyDigits(values.tenant.phone),
      },
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
          <TenantFields />
          <FormActions submitting={submitting} submitLabel="Salvar alterações" />
        </Form>
      </CardContent>
    </Card>
  )
}

function TenantFields() {
  return (
    <Section title="Dados da empresa">
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField name="tenant.name" label="Nome" required className="sm:col-span-2" />
        <TextField
          name="tenant.document"
          label="CPF / CNPJ"
          required
          placeholder="000.000.000-00"
          inputMode="numeric"
          mask={maskCpfCnpj}
        />
        <TextField name="tenant.email" label="E-mail" type="email" required />
        <TextField
          name="tenant.phone"
          label="Telefone"
          required
          placeholder="(41) 99999-9999"
          inputMode="tel"
          mask={maskPhone}
        />
      </div>
    </Section>
  )
}

function AdminFields() {
  return (
    <Section title="Administrador" description="Credenciais do usuário que administrará esta empresa.">
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField name="user.name" label="Nome" required className="sm:col-span-2" />
        <TextField name="user.email" label="E-mail" type="email" required />
        <TextField
          name="user.password"
          label="Senha"
          type="password"
          autoComplete="new-password"
          required
          hint="Mínimo de 8 caracteres"
        />
      </div>
    </Section>
  )
}

function FormActions({ submitting, submitLabel }: { submitting: boolean; submitLabel: string }) {
  return (
    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
      <ButtonLink to="/tenants" variant="secondary">
        Cancelar
      </ButtonLink>
      <Button type="submit" loading={submitting}>
        {submitLabel}
      </Button>
    </div>
  )
}
