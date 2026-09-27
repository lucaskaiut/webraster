import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Input, Page, PageContent, PageHeader, Spinner, Textarea } from '@/shared/design-system'
import { queryKeys } from '@/shared/constants/query-keys'
import { EvolutionConnectModal } from '../components/EvolutionConnectModal'
import { crmService } from '../services/crm.service'

function connectionStatusLabel(status: string): string {
  switch (status) {
    case 'connected':
      return 'Conectado'
    case 'connecting':
      return 'Aguardando pareamento'
    default:
      return 'Desconectado'
  }
}

export default function CrmSettingsPage() {
  const queryClient = useQueryClient()
  const [evolutionModalOpen, setEvolutionModalOpen] = useState(false)

  const connectionsQuery = useQuery({
    queryKey: ['crm', 'connections'],
    queryFn: () => crmService.listConnections(),
  })

  const aiQuery = useQuery({
    queryKey: ['crm', 'ai-config'],
    queryFn: () => crmService.getAiConfiguration(),
  })

  const [aiForm, setAiForm] = useState({
    enabled: false,
    api_endpoint: 'https://api.openai.com/v1',
    api_key: '',
    system_prompt: '',
    model: 'gpt-4o-mini',
  })

  useEffect(() => {
    if (aiQuery.data) {
      setAiForm(() => ({
        enabled: aiQuery.data!.enabled,
        api_endpoint: aiQuery.data!.api_endpoint ?? 'https://api.openai.com/v1',
        api_key: '',
        system_prompt: aiQuery.data!.system_prompt ?? '',
        model: aiQuery.data!.model ?? 'gpt-4o-mini',
      }))
    }
  }, [aiQuery.data])

  const saveAi = useMutation({
    mutationFn: () => {
      const payload: Parameters<typeof crmService.updateAiConfiguration>[0] = {
        enabled: aiForm.enabled,
        api_endpoint: aiForm.api_endpoint.trim() || null,
        system_prompt: aiForm.system_prompt,
        model: aiForm.model,
      }
      if (aiForm.api_key.trim() !== '') {
        payload.api_key = aiForm.api_key.trim()
      }
      return crmService.updateAiConfiguration(payload)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.crm.all })
      void aiQuery.refetch()
    },
  })

  const connection = connectionsQuery.data?.[0]

  return (
    <Page>
      <PageHeader
        title="Configurações do CRM"
        description="Conexão WhatsApp (Evolution API) e agente de IA."
        breadcrumb={[{ label: 'CRM' }, { label: 'Configurações' }]}
      />
      <PageContent className="space-y-8">
        <section className="rounded-2xl bg-surface-2/60 p-6 shadow-card">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h2 className="text-lg font-semibold text-foreground">Gateway Evolution API</h2>
              <p className="mt-1 text-sm text-muted">
                URL e API Key da Evolution vêm do ambiente do servidor. A instância é criada automaticamente por tenant.
              </p>
            </div>
            <Button onClick={() => setEvolutionModalOpen(true)}>
              {connection ? 'Conectar / QR Code' : 'Configurar WhatsApp'}
            </Button>
          </div>
          {connectionsQuery.isPending ? (
            <Spinner className="mt-4" />
          ) : connection ? (
            <div className="mt-4 space-y-2 text-sm">
              <p>
                <span className="text-muted">Nome:</span> {connection.name}
              </p>
              <p>
                <span className="text-muted">Instância:</span> {connection.instance_name ?? '—'}
              </p>
              <p>
                <span className="text-muted">Status:</span>{' '}
                {connectionStatusLabel(connection.connection_status)}
              </p>
              <p>
                <span className="text-muted">Webhook:</span> {connection.webhook_url}
              </p>
            </div>
          ) : (
            <p className="mt-4 text-sm text-muted">Nenhuma conexão configurada ainda.</p>
          )}
        </section>

        <section className="rounded-2xl bg-surface-2/60 p-6 shadow-card">
          <h2 className="text-lg font-semibold text-foreground">IA comercial</h2>
          {aiQuery.isPending ? (
            <Spinner className="mt-4" />
          ) : (
            <div className="mt-4 grid max-w-2xl gap-3">
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={aiForm.enabled}
                  onChange={(e) => setAiForm((s) => ({ ...s, enabled: e.target.checked }))}
                />
                IA habilitada no tenant
              </label>
              <Input
                placeholder="URL da API (ex.: https://api.openai.com/v1)"
                value={aiForm.api_endpoint}
                onChange={(e) => setAiForm((s) => ({ ...s, api_endpoint: e.target.value }))}
              />
              <Input
                type="password"
                placeholder={
                  aiQuery.data?.has_api_key
                    ? 'Token da API (deixe em branco para manter o atual)'
                    : 'Token / API Key'
                }
                value={aiForm.api_key}
                onChange={(e) => setAiForm((s) => ({ ...s, api_key: e.target.value }))}
                autoComplete="off"
              />
              <Input
                placeholder="Modelo (ex.: gpt-4o-mini)"
                value={aiForm.model}
                onChange={(e) => setAiForm((s) => ({ ...s, model: e.target.value }))}
              />
              <Textarea
                rows={8}
                placeholder="System prompt global..."
                value={aiForm.system_prompt}
                onChange={(e) => setAiForm((s) => ({ ...s, system_prompt: e.target.value }))}
              />
              <Button onClick={() => saveAi.mutate()} disabled={saveAi.isPending}>
                Salvar configuração de IA
              </Button>
            </div>
          )}
        </section>
      </PageContent>

      <EvolutionConnectModal
        open={evolutionModalOpen}
        onClose={() => setEvolutionModalOpen(false)}
        connection={connection ?? null}
        onSuccess={() => void connectionsQuery.refetch()}
      />
    </Page>
  )
}
