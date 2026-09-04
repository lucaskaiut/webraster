import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Link } from 'react-router'
import { Alert, Button, Card, CardContent, Form, TextField } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import {
  forgotPasswordSchema,
  type ForgotPasswordFormValues,
} from '../schemas/password.schema'
import { useForgotPassword } from '../hooks/useAuth'

export default function ForgotPasswordPage() {
  const forgotPassword = useForgotPassword()
  const [sent, setSent] = useState(false)

  const form = useForm<ForgotPasswordFormValues>({
    resolver: zodResolver(forgotPasswordSchema),
    defaultValues: { email: '' },
  })

  const onSubmit = async (values: ForgotPasswordFormValues) => {
    try {
      await forgotPassword.mutateAsync(values)
      setSent(true)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Card className="w-full max-w-sm">
      <CardContent className="p-6 sm:p-8">
        <div className="mb-6">
          <h1 className="text-lg font-semibold text-foreground">Recuperar senha</h1>
          <p className="mt-1 text-sm text-muted">
            Informe seu e-mail para receber as instruções de redefinição.
          </p>
        </div>

        {sent ? (
          <Alert
            variant="success"
            title="Verifique seu e-mail"
            className="mb-5"
          >
            Se o e-mail informado estiver cadastrado, você receberá as instruções para
            redefinir a senha.
          </Alert>
        ) : (
          <>
            {forgotPassword.isError &&
              isApiError(forgotPassword.error) &&
              forgotPassword.error.status !== 422 && (
                <Alert variant="danger" title={forgotPassword.error.message} className="mb-5" />
              )}

            <Form form={form} onSubmit={onSubmit}>
              <TextField
                name="email"
                label="E-mail"
                type="email"
                placeholder="voce@empresa.com"
                autoComplete="email"
                required
              />
              <Button type="submit" className="w-full" loading={forgotPassword.isPending}>
                Enviar link
              </Button>
            </Form>
          </>
        )}

        <p className="mt-6 text-center text-sm text-muted">
          <Link to="/auth/login" className="font-medium text-primary hover:text-primary-hover">
            Voltar ao login
          </Link>
        </p>
      </CardContent>
    </Card>
  )
}
