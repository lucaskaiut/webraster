import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { toast } from '@/shared/stores/toast.store'
import { useSessionStore } from '@/shared/stores/session.store'
import { useTenantContextStore } from '@/shared/stores/tenant.store'
import { authService, sessionQueryOptions } from '../services/auth.service'

function useAuthenticate() {
  const queryClient = useQueryClient()
  const setSession = useSessionStore((state) => state.setSession)
  const navigate = useNavigate()

  return async () => {
    const session = await queryClient.fetchQuery(sessionQueryOptions)
    setSession(session)
    navigate('/dashboard', { replace: true })
  }
}

export function useLogin() {
  const authenticate = useAuthenticate()

  return useMutation({
    mutationFn: authService.login,
    onSuccess: async () => {
      await authenticate()
      toast.success('Login realizado', 'Bem-vindo de volta!')
    },
  })
}

export function useRegister() {
  const authenticate = useAuthenticate()

  return useMutation({
    mutationFn: authService.register,
    onSuccess: async () => {
      await authenticate()
      toast.success('Conta criada com sucesso', 'Seu ambiente está pronto para uso.')
    },
  })
}

export function useLogout() {
  const queryClient = useQueryClient()
  const setGuest = useSessionStore((state) => state.setGuest)
  const clearSelectedTenantId = useTenantContextStore((state) => state.clearSelectedTenantId)
  const navigate = useNavigate()

  return useMutation({
    mutationFn: authService.logout,
    onSettled: () => {
      setGuest()
      clearSelectedTenantId()
      queryClient.clear()
      navigate('/auth/login', { replace: true })
    },
  })
}
