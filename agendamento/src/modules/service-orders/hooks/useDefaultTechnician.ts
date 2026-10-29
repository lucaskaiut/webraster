import { useQuery } from '@tanstack/react-query'
import { DEFAULT_TECHNICIAN_EMAIL } from '@/shared/constants/technician'
import { usersService } from '@/modules/users/services/users.service'

/** Técnico fixo do agendamento, localizado pelo e-mail configurado. */
export function useDefaultTechnician() {
  return useQuery({
    queryKey: ['default-technician', DEFAULT_TECHNICIAN_EMAIL],
    queryFn: async () => {
      const response = await usersService.list({
        search: DEFAULT_TECHNICIAN_EMAIL,
        type: 'technician',
        per_page: 10,
      })
      const email = DEFAULT_TECHNICIAN_EMAIL.toLowerCase()

      return response.data.find((user) => user.email.toLowerCase() === email) ?? null
    },
    staleTime: Infinity,
  })
}
