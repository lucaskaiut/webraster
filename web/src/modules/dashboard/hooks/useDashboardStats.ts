import { useQuery } from '@tanstack/react-query'
import { Permission } from '@/shared/constants/permissions'
import { queryKeys } from '@/shared/constants/query-keys'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { usersService } from '@/modules/users/services/users.service'
import { rolesService } from '@/modules/roles/services/roles.service'
import { apiTokensService } from '@/modules/api-tokens/services/api-tokens.service'

export interface DashboardStat {
  total: number | null
  loading: boolean
  allowed: boolean
}

export function useDashboardStats(): {
  users: DashboardStat
  roles: DashboardStat
  apiTokens: DashboardStat
} {
  const { can } = usePermissions()

  const canReadUsers = can(Permission.USER_READ)
  const canReadRoles = can(Permission.ROLE_READ)
  const canReadTokens = can(Permission.API_TOKEN_READ)

  const users = useQuery({
    queryKey: queryKeys.users.list({ page: 1, per_page: 1 }),
    queryFn: () => usersService.list({ page: 1, per_page: 1 }),
    enabled: canReadUsers,
  })

  const roles = useQuery({
    queryKey: queryKeys.roles.list({ page: 1, per_page: 1 }),
    queryFn: () => rolesService.list({ page: 1, per_page: 1 }),
    enabled: canReadRoles,
  })

  const apiTokens = useQuery({
    queryKey: queryKeys.apiTokens.list(),
    queryFn: apiTokensService.list,
    enabled: canReadTokens,
  })

  return {
    users: {
      total: users.data?.meta.total ?? null,
      loading: canReadUsers && users.isPending,
      allowed: canReadUsers,
    },
    roles: {
      total: roles.data?.meta.total ?? null,
      loading: canReadRoles && roles.isPending,
      allowed: canReadRoles,
    },
    apiTokens: {
      total: apiTokens.data?.length ?? null,
      loading: canReadTokens && apiTokens.isPending,
      allowed: canReadTokens,
    },
  }
}
