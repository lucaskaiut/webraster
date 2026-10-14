import { BellRing, Play, Volume2 } from 'lucide-react'
import {
  Button,
  Card,
  CardContent,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Select,
  Skeleton,
  Switch,
} from '@/shared/design-system'
import { toast } from '@/shared/stores/toast.store'
import { playAlertSound, type AlertSoundKey } from '@/shared/utils/alert-sound'
import {
  useAlertSoundPreferencesQuery,
  usePortalAlertConfigsQuery,
  useUpdateAlertSoundPreference,
  useUpdatePortalAlertConfig,
} from '../hooks/useAlerts'

const SOUND_LABELS: Record<string, string> = {
  default: 'Padrão',
  chime: 'Campainha',
  alert: 'Alerta',
  siren: 'Sirene',
}

export default function AlertPreferencesPage() {
  const query = usePortalAlertConfigsQuery()
  const update = useUpdatePortalAlertConfig()
  const soundQuery = useAlertSoundPreferencesQuery()
  const updateSound = useUpdateAlertSoundPreference()

  const soundOptions = (soundQuery.data?.sounds ?? Object.keys(SOUND_LABELS)).map((value) => ({
    value,
    label: SOUND_LABELS[value] ?? value,
  }))

  const handleToggle = (type: string, isEnabled: boolean) => {
    update.mutate(
      { type, isEnabled },
      { onError: () => toast.error('Não foi possível atualizar o alerta') },
    )
  }

  const handleSoundChange = (type: string, sound: string) => {
    updateSound.mutate(
      { type, sound },
      { onError: () => toast.error('Não foi possível salvar o som') },
    )
  }

  return (
    <Page>
      <PageHeader
        title="Meus alertas"
        description="Silencie alertas que você não quer receber. Quais alertas cada veículo dispara é definido pelo operador no cadastro do veículo."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Meus alertas' }]}
      />

      <PageContent>
        {soundQuery.data && (
          <Card>
            <CardContent className="space-y-4">
              <div className="flex items-center gap-2">
                <Volume2 className="size-4 text-primary" aria-hidden="true" />
                <h2 className="text-sm font-semibold text-foreground">Sons de notificação</h2>
              </div>
              <p className="text-[13px] text-muted">
                Escolha o som reproduzido no painel quando um novo alerta chegar. O mesmo som é
                usado no aplicativo.
              </p>

              <div className="space-y-3">
                {soundQuery.data.alerts.map((group) => (
                  <div
                    key={group.type}
                    className="flex flex-wrap items-center justify-between gap-3"
                  >
                    <div className="min-w-0">
                      <p className="text-sm font-semibold text-foreground">{group.label}</p>
                      <p className="mt-0.5 text-[13px] text-muted">{group.description}</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Select
                        aria-label={`Som de ${group.label}`}
                        className="h-8 w-36 text-xs"
                        options={soundOptions}
                        value={group.sound}
                        disabled={updateSound.isPending && updateSound.variables?.type === group.type}
                        onChange={(event) => handleSoundChange(group.type, event.target.value)}
                      />
                      <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        onClick={() => playAlertSound(group.sound as AlertSoundKey)}
                        aria-label={`Ouvir som de ${group.label}`}
                      >
                        <Play className="size-3.5" aria-hidden="true" />
                        Ouvir
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        )}

        {query.isPending && (
          <Card>
            <CardContent className="space-y-5">
              {Array.from({ length: 5 }).map((_, index) => (
                <div key={index} className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1 space-y-2">
                    <Skeleton className="h-4 w-40" />
                    <Skeleton className="h-3 w-64" />
                  </div>
                  <Skeleton className="h-6 w-10.5 rounded-full" />
                </div>
              ))}
            </CardContent>
          </Card>
        )}

        {query.isError && (
          <Card>
            <EmptyState
              icon={BellRing}
              title="Não foi possível carregar seus alertas"
              description="Atualize a página e tente novamente."
            />
          </Card>
        )}

        {query.data && (
          <Card>
            <CardContent className="space-y-5">
              {query.data.map((option) => (
                <div key={option.type} className="flex items-center justify-between gap-4">
                  <div className="min-w-0">
                    <p className="text-sm font-semibold text-foreground">{option.label}</p>
                    <p className="mt-0.5 text-[13px] text-muted">{option.description}</p>
                  </div>
                  <Switch
                    checked={option.is_enabled}
                    disabled={update.isPending && update.variables?.type === option.type}
                    onCheckedChange={(checked) => handleToggle(option.type, checked)}
                    label={option.label}
                  />
                </div>
              ))}
            </CardContent>
          </Card>
        )}
      </PageContent>
    </Page>
  )
}
