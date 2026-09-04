import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Link, useSearchParams } from 'react-router'
import { Alert, Button, Card, CardContent, Form, TextField } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import {
  resetPasswordSchema,
  type ResetPasswordFormValues,
} from '../schemas/password.schema'
import { useResetPassword } from '../hooks/useAuth'

export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams()
  const resetPassword = useResetPassword()

  const form = useForm<ResetPasswordFormValues>({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: {
      email: searchParams.get('email') ?? '',
      token: searchParams.get('token') ?? '',
      password: '',
      password_confirmation: '',
    },
  })

  const onSubmit = async (values: ResetPasswordFormValues) => {
    try {
      await resetPassword.mutateAsync(values)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  const missingToken = !searchParams.get('token')

  return (
    <Card className="w-full max-w-sm">
      <CardContent className="p-6 sm:p-8">
        <div className="mb-6">
          <h1 className="text-lg font-semibold text-foreground">Redefinir senha</h1>
          <p className="mt-1 text-sm text-muted">Escolha uma nova senha para a sua conta.</p>
        </div>

        {missingToken ? (
          <Alert variant="danger" title="Link inválido" className="mb-5">
            Este link de redefinição está incompleto. Solicite um novo link.
          </Alert>
        ) : (
          <>
            {resetPassword.isError &&
              isApiError(resetPassword.error) &&
              resetPassword.error.status !== 422 && (
                <Alert variant="danger" title={resetPassword.error.message} className="mb-5" />
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
              <TextField
                name="password"
                label="Nova senha"
                type="password"
                placeholder="••••••••"
                autoComplete="new-password"
                required
              />
              <TextField
                name="password_confirmation"
                label="Confirmar senha"
                type="password"
                placeholder="••••••••"
                autoComplete="new-password"
                required
              />
              <Button type="submit" className="w-full" loading={resetPassword.isPending}>
                Salvar nova senha
              </Button>
            </Form>
          </>
        )}

        <p className="mt-6 text-center text-sm text-muted">
          <Link
            to="/auth/forgot-password"
            className="font-medium text-primary hover:text-primary-hover"
          >
            Solicitar novo link
          </Link>
        </p>
      </CardContent>
    </Card>
  )
}
