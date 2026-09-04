import { Controller, useFormContext } from 'react-hook-form'
import { Checkbox, Field } from '@/shared/design-system'
import { getPermissionGroups, type Permission } from '@/shared/constants/permissions'
import { useIsUmbrellaTenant } from '@/shared/hooks/useIsUmbrellaTenant'

/**
 * Seleção de permissões em grupos de checkboxes, com "selecionar tudo" por grupo.
 * Permissões de planos só aparecem para tenants umbrella (raiz / sem parent_id).
 */
export function PermissionsField({ name = 'permissions' }: { name?: string }) {
  const { control } = useFormContext()
  const isUmbrella = useIsUmbrellaTenant()
  const groups = getPermissionGroups({ includePlanPermissions: isUmbrella })

  return (
    <Controller
      control={control}
      name={name}
      render={({ field, fieldState }) => {
        const selected = (field.value ?? []) as Permission[]

        const toggle = (permission: Permission) => {
          field.onChange(
            selected.includes(permission)
              ? selected.filter((value) => value !== permission)
              : [...selected, permission],
          )
        }

        const toggleGroup = (permissions: Permission[], allSelected: boolean) => {
          const withoutGroup = selected.filter((value) => !permissions.includes(value))

          field.onChange(allSelected ? withoutGroup : [...withoutGroup, ...permissions])
        }

        return (
          <Field label="Permissões" error={fieldState.error?.message}>
            <div className="grid gap-3 lg:grid-cols-2">
              {groups.map((group) => {
                const values = group.permissions.map((permission) => permission.value)
                const allSelected = values.every((value) => selected.includes(value))

                return (
                  <div key={group.label} className="rounded-xl bg-surface-2/60 p-4">
                    <div className="mb-3 flex items-center justify-between gap-3">
                      <p className="text-[13px] font-semibold text-foreground">{group.label}</p>
                      <button
                        type="button"
                        onClick={() => toggleGroup(values, allSelected)}
                        className="text-xs font-medium text-primary transition-colors hover:text-primary-hover"
                      >
                        {allSelected ? 'Desmarcar tudo' : 'Selecionar tudo'}
                      </button>
                    </div>
                    <div className="space-y-2.5">
                      {group.permissions.map((permission) => (
                        <Checkbox
                          key={permission.value}
                          id={`permission-${permission.value}`}
                          label={permission.label}
                          checked={selected.includes(permission.value)}
                          onChange={() => toggle(permission.value)}
                        />
                      ))}
                    </div>
                  </div>
                )
              })}
            </div>
          </Field>
        )
      }}
    />
  )
}
