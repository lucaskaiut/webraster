import { useEffect, type ReactNode } from 'react'
import { useForm, useFormContext, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { RotateCcw } from 'lucide-react'
import {
  Button,
  Card,
  CardContent,
  Form,
  Loading,
  Page,
  PageContent,
  PageHeader,
  Section,
  SwitchField,
  TextField,
  TextareaField,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { CollectionReminderStage, FinanceCollectionSettings } from '@/shared/types/models'
import {
  useFinanceCollectionSettingsQuery,
  useUpdateFinanceCollectionSettings,
} from '../hooks/useFinance'
import { COLLECTION_VARIABLES, renderCollectionPreview } from '../lib/collection'
import {
  collectionSettingsSchema,
  type CollectionSettingsFormValues,
} from '../schemas/collection-settings.schema'

function toFormValues(data: FinanceCollectionSettings): CollectionSettingsFormValues {
  return {
    is_active: data.is_active,
    before_due_enabled: data.before_due_enabled,
    before_due_days: String(data.before_due_days),
    before_due_message: data.before_due_message,
    due_date_enabled: data.due_date_enabled,
    due_date_message: data.due_date_message,
    overdue_enabled: data.overdue_enabled,
    overdue_interval_days: String(data.overdue_interval_days),
    overdue_max_reminders: data.overdue_max_reminders ? String(data.overdue_max_reminders) : '',
    overdue_message: data.overdue_message,
    block_access_enabled: data.block_access_enabled,
    block_access_after_days: String(data.block_access_after_days),
  }
}

export default function FinanceCollectionSettingsPage() {
  const query = useFinanceCollectionSettingsQuery()
  const update = useUpdateFinanceCollectionSettings()

  const form = useForm<CollectionSettingsFormValues>({
    resolver: zodResolver(collectionSettingsSchema),
    defaultValues: {
      is_active: false,
      before_due_enabled: true,
      before_due_days: '3',
      before_due_message: '',
      due_date_enabled: true,
      due_date_message: '',
      overdue_enabled: true,
      overdue_interval_days: '3',
      overdue_max_reminders: '',
      overdue_message: '',
      block_access_enabled: false,
      block_access_after_days: '5',
    },
  })

  useEffect(() => {
    if (query.data) form.reset(toFormValues(query.data))
  }, [query.data, form])

  const onSubmit = async (values: CollectionSettingsFormValues) => {
    try {
      await update.mutateAsync({
        ...values,
        before_due_days: Number(values.before_due_days),
        overdue_interval_days: Number(values.overdue_interval_days),
        overdue_max_reminders: values.overdue_max_reminders
          ? Number(values.overdue_max_reminders)
          : null,
        block_access_after_days: Number(values.block_access_after_days),
      })
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  const defaults = query.data?.default_messages

  return (
    <Page>
      <PageHeader
        title="Régua de cobrança"
        description="Avisos enviados automaticamente aos clientes antes, no dia e depois do vencimento das cobranças."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Financeiro' },
          { label: 'Régua de cobrança' },
        ]}
      />
      <PageContent>
        {query.isPending && <Loading />}
        {!query.isPending && query.data && defaults && (
          <Card>
            <CardContent>
              <Form form={form} onSubmit={onSubmit} className="space-y-8">
                <Section
                  title="Envio automático"
                  description="Os avisos saem todo dia às 9h por e-mail (e-mail financeiro do cliente ou, se vazio, o principal) e como notificação no app e no portal do cliente.">
                  <SwitchField
                    name="is_active"
                    label="Régua de cobrança ativa"
                    hint="Ativa, substitui os avisos padrão de vencimento e de atraso."
                  />
                  <div className="space-y-2 rounded-xl bg-surface-2/60 p-4 text-[13px] text-muted">
                    <p>
                      O <span className="font-medium text-foreground">{'{link}'}</span> leva à fatura do
                      Asaas quando a cobrança já foi enviada ao gateway. Caso contrário, abre a cobrança
                      no portal do cliente, onde ele gera o pagamento.
                    </p>
                    <div className="flex flex-wrap gap-1.5 pt-1">
                      {COLLECTION_VARIABLES.map((variable) => (
                        <span
                          key={variable.key}
                          title={variable.label}
                          className="rounded-md bg-surface px-2 py-1 font-mono text-xs text-foreground shadow-card">
                          {variable.key}
                        </span>
                      ))}
                    </div>
                  </div>
                </Section>

                <StageSection
                  stage="before_due"
                  title="Antes do vencimento"
                  description="Lembrete enviado alguns dias antes da data de vencimento."
                  enabledName="before_due_enabled"
                  messageName="before_due_message"
                  defaultMessage={defaults.before_due}>
                  <TextField
                    name="before_due_days"
                    label="Dias antes do vencimento"
                    type="number"
                    min={1}
                    max={30}
                    required
                    hint="Entre 1 e 30 dias."
                  />
                </StageSection>

                <StageSection
                  stage="due_date"
                  title="No dia do vencimento"
                  description="Aviso enviado na própria data de vencimento."
                  enabledName="due_date_enabled"
                  messageName="due_date_message"
                  defaultMessage={defaults.due_date}
                />

                <StageSection
                  stage="overdue"
                  title="Em atraso"
                  description="O primeiro aviso sai no dia seguinte ao vencimento e se repete enquanto a cobrança não for paga."
                  enabledName="overdue_enabled"
                  messageName="overdue_message"
                  defaultMessage={defaults.overdue}>
                  <TextField
                    name="overdue_interval_days"
                    label="Repetir a cada (dias)"
                    type="number"
                    min={1}
                    max={30}
                    required
                    hint="Entre 1 e 30 dias."
                  />
                  <TextField
                    name="overdue_max_reminders"
                    label="Limite de avisos em atraso"
                    type="number"
                    min={0}
                    max={100}
                    placeholder="Sem limite"
                    hint="Em branco, envia até a cobrança ser paga ou cancelada."
                  />
                </StageSection>

                <BlockAccessSection />

                <Can permission={Permission.FINANCE_COLLECTION_CONFIG_UPDATE}>
                  <div className="flex justify-end">
                    <Button type="submit" loading={update.isPending}>
                      Salvar régua
                    </Button>
                  </div>
                </Can>
              </Form>
            </CardContent>
          </Card>
        )}
      </PageContent>
    </Page>
  )
}

function BlockAccessSection() {
  const form = useFormContext<CollectionSettingsFormValues>()
  const enabled = useWatch({ control: form.control, name: 'block_access_enabled' })

  return (
    <Section
      title="Bloqueio por inadimplência"
      description="Restringe o acesso do cliente com cobrança em atraso: no painel e no app ele passa a ver só o financeiro, com o aviso para pagar. O acesso volta assim que a cobrança é paga.">
      <SwitchField
        name="block_access_enabled"
        label="Bloquear acesso de clientes inadimplentes"
        hint="Vale para os usuários do portal do cliente. A equipe da empresa não é afetada."
      />
      {enabled && (
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField
            name="block_access_after_days"
            label="Bloquear após (dias de atraso)"
            type="number"
            min={1}
            max={90}
            required
            hint="Contados a partir do vencimento da cobrança mais antiga em aberto."
          />
        </div>
      )}
    </Section>
  )
}

function StageSection({
  stage,
  title,
  description,
  enabledName,
  messageName,
  defaultMessage,
  children,
}: {
  stage: CollectionReminderStage
  title: string
  description: string
  enabledName: keyof CollectionSettingsFormValues
  messageName: keyof CollectionSettingsFormValues
  defaultMessage: string
  children?: ReactNode
}) {
  const form = useFormContext<CollectionSettingsFormValues>()
  const enabled = useWatch({ control: form.control, name: enabledName }) as boolean
  const message = (useWatch({ control: form.control, name: messageName }) as string) ?? ''

  return (
    <Section
      title={title}
      description={description}
      actions={
        enabled && message !== defaultMessage ? (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => form.setValue(messageName, defaultMessage, { shouldDirty: true })}>
            <RotateCcw className="size-4" />
            Restaurar texto padrão
          </Button>
        ) : undefined
      }>
      <SwitchField name={enabledName} label="Enviar este aviso" />
      {enabled && (
        <div className="space-y-4">
          {children && <div className="grid gap-4 sm:grid-cols-2">{children}</div>}
          <TextareaField
            name={messageName}
            label="Texto do aviso"
            rows={4}
            maxLength={2000}
            hint="Em branco, usa o texto padrão."
            aria-describedby={`collection-preview-${stage}`}
          />
          <div
            id={`collection-preview-${stage}`}
            className="space-y-1 rounded-xl bg-surface-2/60 p-4 text-sm">
            <p className="text-xs font-medium uppercase tracking-wide text-muted">Prévia</p>
            <p className="whitespace-pre-line break-words text-foreground">
              {renderCollectionPreview(message || defaultMessage)}
            </p>
          </div>
        </div>
      )}
    </Section>
  )
}
