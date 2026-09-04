import { Controller, useFormContext } from 'react-hook-form'
import { Checkbox, Field, Skeleton } from '@/shared/design-system'
import { useRolesQuery } from '@/modules/roles/hooks/useRoles'

export function RolesField({ name = 'role_ids' }: { name?: string }) {
  const { control } = useFormContext()
  const rolesQuery = useRolesQuery({ per_page: 100 })

  return (
    <Controller
      control={control}
      name={name}
      render={({ field, fieldState }) => {
        const selected = (field.value ?? []) as number[]

        const toggle = (roleId: number) => {
          field.onChange(
            selected.includes(roleId) ? selected.filter((value) => value !== roleId) : [...selected, roleId],
          )
        }

        if (rolesQuery.isPending) {
          return (
            <Field label="Perfis de acesso" error={fieldState.error?.message}>
              <Skeleton className="h-24" />
            </Field>
          )
        }

        const roles = rolesQuery.data?.data ?? []

        return (
          <Field label="Perfis de acesso" error={fieldState.error?.message} required>
            {roles.length === 0 ? (
              <p className="text-sm text-muted">Nenhum perfil cadastrado. Crie um perfil antes de atribuir acesso.</p>
            ) : (
              <div className="space-y-2 rounded-xl bg-surface-2/60 p-4">
                {roles.map((role) => (
                  <Checkbox
                    key={role.id}
                    id={`user-role-${role.id}`}
                    label={role.name}
                    description={role.description ?? undefined}
                    checked={selected.includes(role.id)}
                    onChange={() => toggle(role.id)}
                  />
                ))}
              </div>
            )}
          </Field>
        )
      }}
    />
  )
}
