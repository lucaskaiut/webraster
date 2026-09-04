import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Link } from 'react-router'
import { Alert, Button, Card, CardContent, Form, TextField } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { loginSchema, type LoginFormValues } from '../schemas/auth.schema'
import { useLogin } from '../hooks/useAuth'

export default function LoginPage() {
  const login = useLogin()

  const form = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  })

  const onSubmit = async (values: LoginFormValues) => {
    try {
      await login.mutateAsync(values)
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
          <h1 className="text-lg font-semibold text-foreground">Entrar</h1>
          <p className="mt-1 text-sm text-muted">Acesse o painel da sua organização.</p>
        </div>

        {login.isError && isApiError(login.error) && login.error.status !== 422 && (
          <Alert variant="danger" title={login.error.message} className="mb-5" />
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
            label="Senha"
            type="password"
            placeholder="••••••••"
            autoComplete="current-password"
            required
          />
          <Button type="submit" className="w-full" loading={login.isPending}>
            Entrar
          </Button>
        </Form>

        <p className="mt-6 text-center text-sm text-muted">
          Ainda não tem uma conta?{' '}
          <Link to="/auth/register" className="font-medium text-primary hover:text-primary-hover">
            Criar conta
          </Link>
        </p>
      </CardContent>
    </Card>
  )
}
