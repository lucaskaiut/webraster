import { useSearchParams } from 'react-router'
import { Building2, Palette } from 'lucide-react'
import { normalizeTheme } from '@/shared/theme/theme'
import {
  Button,
  Card,
  CardContent,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  SegmentedControl,
  Skeleton,
} from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { useIsUmbrellaTenant } from '@/shared/hooks/useIsUmbrellaTenant'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useTenantModules } from '@/shared/hooks/useTenantModules'
import { TenantAlertSettingsForm } from '../forms/TenantAlertSettingsForm'
import { TenantAppSettingsForm } from '../forms/TenantAppSettingsForm'
import { TenantSettingsForm } from '../forms/TenantSettingsForm'
import { TenantThemeForm } from '../forms/TenantThemeForm'
import {
  useTenantQuery,
  useTenantThemeQuery,
  useUpdateTenant,
  useUpdateTenantTheme,
} from '../hooks/useTenants'

type TenantSettingsTab = 'empresa' | 'aplicativo' | 'alertas' | 'tema'

function FormSkeleton() {
  return (
    <Card>
      <CardContent className="space-y-5">
        <Skeleton className="h-4 w-40" />
        <div className="grid gap-4 sm:grid-cols-2">
          <Skeleton className="h-10 sm:col-span-2" />
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
        </div>
        <Skeleton className="h-40" />
      </CardContent>
    </Card>
  )
}

export default function TenantSettingsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const { can } = usePermissions()
  const canEditTenant = can(Permission.TENANT_UPDATE)
  const canManageTheme = can(Permission.THEME_MANAGE)
  const query = useTenantQuery(canEditTenant)
  const updateTenant = useUpdateTenant()
  const themeQuery = useTenantThemeQuery(canManageTheme)
  const updateTheme = useUpdateTenantTheme()
  const isUmbrella = useIsUmbrellaTenant()
  const { hasModule } = useTenantModules()

  // O umbrella não tem app nem alertas; nos filhos, as abas seguem os módulos.
  const showAppTab = canEditTenant && !isUmbrella && (hasModule('tracking') || hasModule('service_orders'))
  const showAlertsTab = canEditTenant && !isUmbrella && hasModule('alerts')
  const tabOptions: Array<{ value: TenantSettingsTab; label: string }> = [
    ...(canEditTenant ? [{ value: 'empresa' as const, label: 'Empresa' }] : []),
    ...(showAppTab ? [{ value: 'aplicativo' as const, label: 'Aplicativo' }] : []),
    ...(showAlertsTab ? [{ value: 'alertas' as const, label: 'Alertas' }] : []),
    ...(canManageTheme ? [{ value: 'tema' as const, label: 'Tema' }] : []),
  ]
  const defaultTab: TenantSettingsTab = canEditTenant ? 'empresa' : 'tema'

  const rawTab = searchParams.get('tab')
  const tab: TenantSettingsTab =
    tabOptions.find((option) => option.value === rawTab)?.value ?? defaultTab

  const setTab = (value: TenantSettingsTab) => {
    setSearchParams(
      (params) => {
        if (value === defaultTab) {
          params.delete('tab')
        } else {
          params.set('tab', value)
        }
        return params
      },
      { replace: true },
    )
  }

  return (
    <Page>
      <PageHeader
        title="Configurações"
        description="Atualize os dados e as preferências da empresa ativa."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Configurações' }]}
      />

      <PageContent className="space-y-5">
        {tabOptions.length > 1 && (
          <SegmentedControl value={tab} options={tabOptions} onChange={setTab} />
        )}

        {tab === 'tema' && (
          <>
            {themeQuery.isPending && <FormSkeleton />}
            {themeQuery.isError && (
              <Card>
                <EmptyState
                  icon={Palette}
                  title="Não foi possível carregar o tema"
                  description="Tente novamente ou verifique suas permissões de acesso."
                  action={
                    <Button variant="secondary" onClick={() => void themeQuery.refetch()}>
                      Tentar novamente
                    </Button>
                  }
                />
              </Card>
            )}
            {themeQuery.data && (
              <TenantThemeForm
                theme={normalizeTheme(themeQuery.data)}
                submitting={updateTheme.isPending}
                onSubmit={(payload) => updateTheme.mutateAsync(payload)}
              />
            )}
          </>
        )}

        {tab !== 'tema' && query.isPending && <FormSkeleton />}

        {tab !== 'tema' && query.isError && (
          <Card>
            <EmptyState
              icon={Building2}
              title="Não foi possível carregar a empresa"
              description="Tente novamente ou verifique suas permissões de acesso."
              action={
                <Button variant="secondary" onClick={() => void query.refetch()}>
                  Tentar novamente
                </Button>
              }
            />
          </Card>
        )}

        {query.data && (
          <>
            {tab === 'empresa' && (
              <TenantSettingsForm
                tenant={query.data}
                submitting={updateTenant.isPending}
                onSubmit={(payload) => updateTenant.mutateAsync(payload)}
              />
            )}

            {tab === 'aplicativo' && (
              <TenantAppSettingsForm
                tenant={query.data}
                submitting={updateTenant.isPending}
                onSubmit={(payload) => updateTenant.mutateAsync(payload)}
              />
            )}

            {tab === 'alertas' && (
              <TenantAlertSettingsForm
                tenant={query.data}
                submitting={updateTenant.isPending}
                onSubmit={(payload) => updateTenant.mutateAsync(payload)}
              />
            )}
          </>
        )}
      </PageContent>
    </Page>
  )
}
