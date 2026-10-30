import { Link } from 'react-router'
import { Ban, Building2, CircleCheck, Plus, Users } from 'lucide-react'
import {
  Badge,
  ButtonLink,
  Card,
  CardContent,
  CardHeader,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { MetricCard } from '@/modules/dashboard/components/MetricCard'
import { Permission } from '@/shared/constants/permissions'
import { formatDate } from '@/shared/utils/format'
import { useTenantOverviewQuery } from '../hooks/useTenants'

/**
 * Dashboard do tenant umbrella: ele não opera monitoramento, e sim oferece
 * o serviço às empresas filhas. Mostra a situação das empresas do grupo.
 */
export default function TenantsOverviewPage() {
  const query = useTenantOverviewQuery()

  return (
    <Page>
      <PageHeader
        title="Visão geral"
        description="Situação das empresas do grupo."
        breadcrumb={[{ label: 'Dashboard' }, { label: 'Visão geral' }]}
        actions={
          <Can permission={Permission.TENANT_CREATE}>
            <ButtonLink to="/tenants/create">
              <Plus className="size-4" />
              Nova empresa
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        {query.isPending && (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {Array.from({ length: 4 }).map((_, index) => (
              <Skeleton key={index} className="h-24 w-full rounded-xl" />
            ))}
          </div>
        )}

        {query.isError && (
          <Card>
            <EmptyState
              icon={Building2}
              title="Não foi possível carregar os indicadores"
              description="Atualize a página e tente novamente."
            />
          </Card>
        )}

        {query.data && (
          <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <MetricCard
                label="Empresas"
                value={query.data.companies.total}
                icon={Building2}
                to="/tenants"
              />
              <MetricCard
                label="Ativas"
                value={query.data.companies.active}
                icon={CircleCheck}
                tone="success"
                to="/tenants"
              />
              <MetricCard
                label="Inativas"
                value={query.data.companies.inactive}
                hint="Com o acesso bloqueado"
                icon={Ban}
                tone={query.data.companies.inactive > 0 ? 'danger' : 'neutral'}
                to="/tenants"
              />
              <MetricCard label="Usuários nas empresas" value={query.data.users} icon={Users} />
            </div>

            <Card>
              <CardHeader
                title="Empresas recentes"
                actions={
                  <Link
                    to="/tenants"
                    className="text-xs font-medium text-primary transition-colors hover:text-primary-hover"
                  >
                    Ver todas
                  </Link>
                }
              />
              <CardContent>
                {query.data.recent.length === 0 ? (
                  <EmptyState
                    icon={Building2}
                    title="Nenhuma empresa cadastrada"
                    description="Crie a primeira empresa do seu grupo."
                  />
                ) : (
                  <ul className="space-y-1">
                    {query.data.recent.map((tenant) => (
                      <li key={tenant.id}>
                        <Link
                          to={`/tenants/${tenant.id}/edit`}
                          className="flex items-center justify-between gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-surface-2"
                        >
                          <span className="min-w-0">
                            <span className="block truncate text-sm font-medium text-foreground">
                              {tenant.name}
                            </span>
                            <span className="block text-xs text-muted">
                              {tenant.users_count ?? 0} usuários · desde{' '}
                              {formatDate(tenant.created_at)}
                            </span>
                          </span>
                          {tenant.is_active ? (
                            <Badge variant="success">Ativa</Badge>
                          ) : (
                            <Badge variant="danger">Inativa</Badge>
                          )}
                        </Link>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>
          </div>
        )}
      </PageContent>
    </Page>
  )
}
