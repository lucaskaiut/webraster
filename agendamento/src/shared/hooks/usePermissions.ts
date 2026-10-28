import { useSessionStore } from '@/shared/stores/session.store'
import type { Permission } from '@/shared/constants/permissions'
import { isPlanPermission } from '@/shared/constants/permissions'
import { useIsUmbrellaTenant } from '@/shared/hooks/useIsUmbrellaTenant'

export interface PermissionChecker {
  permissions: Permission[]
  can: (permission: Permission) => boolean
  canAny: (permissions: Permission[]) => boolean
}

export function usePermissions(): PermissionChecker {
  const permissions = useSessionStore((state) => state.permissions)
  const isUmbrella = useIsUmbrellaTenant()

  return {
    permissions,
    can: (permission) => {
      if (isPlanPermission(permission) && !isUmbrella) {
        return false
      }

      return permissions.includes(permission)
    },
    canAny: (list) =>
      list.some((permission) => {
        if (isPlanPermission(permission) && !isUmbrella) {
          return false
        }

        return permissions.includes(permission)
      }),
  }
}
