import { Controller, useFormContext } from 'react-hook-form'
import { Checkbox, Field, Skeleton } from '@/shared/design-system'
import type { TenantModule, TenantModuleKey } from '@/shared/types/models'

/**
 * Módulos liberados para a empresa filha. Marcar um módulo marca também os
 * que ele exige; desmarcar um módulo desmarca os que dependem dele.
 */
export function ModulesField({
  name,
  modules,
  loading = false,
}: {
  name: string
  modules: TenantModule[] | undefined
  loading?: boolean
}) {
  const { control } = useFormContext()

  if (loading || !modules) {
    return (
      <div className="grid gap-3 lg:grid-cols-2">
        {Array.from({ length: 6 }, (_, index) => (
          <Skeleton key={index} className="h-20" />
        ))}
      </div>
    )
  }

  const labels = new Map(modules.map((module) => [module.key, module.label]))

  return (
    <Controller
      control={control}
      name={name}
      render={({ field, fieldState }) => {
        const selected = (field.value ?? []) as TenantModuleKey[]
        const allSelected = modules.every((module) => selected.includes(module.key))

        const toggle = (module: TenantModule) => {
          if (selected.includes(module.key)) {
            const dependents = modules
              .filter((item) => item.requires.includes(module.key))
              .map((item) => item.key)

            field.onChange(
              selected.filter((key) => key !== module.key && !dependents.includes(key)),
            )
            return
          }

          field.onChange(Array.from(new Set([...selected, module.key, ...module.requires])))
        }

        return (
          <Field error={fieldState.error?.message}>
            <div className="flex justify-end">
              <button
                type="button"
                onClick={() => field.onChange(allSelected ? [] : modules.map((module) => module.key))}
                className="text-xs font-medium text-primary transition-colors hover:text-primary-hover"
              >
                {allSelected ? 'Desmarcar todos' : 'Selecionar todos'}
              </button>
            </div>
            <div className="grid gap-3 lg:grid-cols-2">
              {modules.map((module) => (
                <div key={module.key} className="rounded-xl bg-surface-2/60 p-4">
                  <Checkbox
                    id={`module-${module.key}`}
                    label={module.label}
                    description={
                      module.requires.length > 0
                        ? `${module.description} Requer: ${module.requires
                            .map((key) => labels.get(key) ?? key)
                            .join(', ')}.`
                        : module.description
                    }
                    checked={selected.includes(module.key)}
                    onChange={() => toggle(module)}
                  />
                </div>
              ))}
            </div>
          </Field>
        )
      }}
    />
  )
}
