import type { LucideIcon } from 'lucide-react'
import { Building2, Globe, KeyRound, ShieldCheck, Users } from 'lucide-react'
import { Link } from 'react-router'
import {
  Badge,
  Card,
  CardContent,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { useSessionStore } from '@/shared/stores/session.store'
import { cn } from '@/shared/utils/cn'
import { useDashboardStats, type DashboardStat } from '../hooks/useDashboardStats'

function StatCard({
  label,
  icon: Icon,
  stat,
  to,
  accent,
}: {
  label: string
  icon: LucideIcon
  stat: DashboardStat
  to: string
  accent: string
}) {
  const content = (
    <CardContent className="flex items-center gap-4">
      <span className={cn('flex size-11 shrink-0 items-center justify-center rounded-xl', accent)}>
        <Icon className="size-5.5" aria-hidden="true" />
      </span>
      <div className="min-w-0">
        <p className="text-[13px] text-muted">{label}</p>
        {stat.loading ? (
          <Skeleton className="mt-1 h-7 w-14" />
        ) : (
          <p className="text-2xl font-semibold tracking-tight text-foreground">
            {stat.allowed ? (stat.total ?? '—') : '—'}
          </p>
        )}
      </div>
    </CardContent>
  )

  if (!stat.allowed) {
    return <Card className="opacity-60">{content}</Card>
  }

  return (
    <Card className="transition-shadow hover:shadow-raised">
      <Link to={to} className="block rounded-xl" aria-label={`Ver ${label.toLowerCase()}`}>
        {content}
      </Link>
    </Card>
  )
}

export default function DashboardPage() {
  const user = useSessionStore((state) => state.user)
  const tenant = useSessionStore((state) => state.tenant)
  const stats = useDashboardStats()

  return (
    <Page>
      <PageHeader
        title={`Olá, ${user?.name.split(' ')[0] ?? ''}`}
        description="Aqui está um resumo da sua organização."
      />

      <PageContent>
        <Card>
          <CardContent className="flex flex-wrap items-center gap-4">
            <span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary">
              <Building2 className="size-6" aria-hidden="true" />
            </span>
            <div className="min-w-0 flex-1">
              <h2 className="truncate text-base font-semibold text-foreground">{tenant?.name}</h2>
              <p className="flex items-center gap-1.5 text-sm text-muted">
                <Globe className="size-3.5" aria-hidden="true" />
                {tenant?.domain}
              </p>
            </div>
            <Badge variant="success">Ativo</Badge>
          </CardContent>
        </Card>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatCard
            label="Usuários"
            icon={Users}
            stat={stats.users}
            to="/users"
            accent="bg-primary-soft text-primary"
          />
          <StatCard
            label="Perfis de acesso"
            icon={ShieldCheck}
            stat={stats.roles}
            to="/roles"
            accent="bg-success-soft text-success"
          />
          <StatCard
            label="Tokens de API"
            icon={KeyRound}
            stat={stats.apiTokens}
            to="/api-tokens"
            accent="bg-warning-soft text-warning"
          />
        </div>
      </PageContent>
    </Page>
  )
}
