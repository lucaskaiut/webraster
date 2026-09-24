import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { Eye, FileText, PenLine } from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  EmptyState,
  Form,
  Modal,
  Section,
  SelectField,
  TextField,
  buttonClasses,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatDocument } from '@/shared/utils/document'
import { formatDate, formatDateTime } from '@/shared/utils/format'
import { applyApiErrorsToForm, formResolver } from '@/shared/utils/forms'
import { useContractsQuery } from '@/modules/contracts/hooks/useContracts'
import type { ClientContract, ContractSignatureStatus } from '@/shared/types/models'
import { signatureStatusBadgeVariant, signatureStatusLabel } from '../lib/labels'
import {
  useClientContractQuery,
  useUpdateClientContractSignature,
  useUpsertClientContract,
} from '../hooks/useClients'

const clientContractSchema = z.object({
  contract_id: z.string().min(1, 'Selecione o contrato'),
  valid_until: z.string().min(1, 'Informe o prazo de validade'),
})

type ClientContractFormValues = z.infer<typeof clientContractSchema>

function SignerField({ label, value }: { label: string; value: string | null }) {
  return (
    <div className="space-y-0.5">
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="text-[13px] font-medium text-foreground">{value ?? '—'}</dd>
    </div>
  )
}

export function ClientContractSection({ clientId }: { clientId: string }) {
  const contractsQuery = useContractsQuery({ per_page: 100 })
  const contractQuery = useClientContractQuery(clientId)
  const upsert = useUpsertClientContract(clientId)
  const updateSignature = useUpdateClientContractSignature(clientId)

  const contracts = contractsQuery.data?.data ?? []
  const current = contractQuery.data

  if (contractsQuery.isPending || contractQuery.isPending) {
    return (
      <Card>
        <CardContent>
          <p className="text-sm text-muted">Carregando contrato...</p>
        </CardContent>
      </Card>
    )
  }

  if (contracts.length === 0) {
    return (
      <Card>
        <CardContent>
          <EmptyState
            icon={FileText}
            title="Nenhum contrato cadastrado"
            description="Cadastre um modelo de contrato antes de vincular ao cliente."
            action={
              <ButtonLink to="/contracts/create" variant="secondary">
                Cadastrar contrato
              </ButtonLink>
            }
          />
        </CardContent>
      </Card>
    )
  }

  return (
    <ClientContractForm
      contracts={contracts}
      current={current}
      submitting={upsert.isPending}
      signatureUpdating={updateSignature.isPending}
      onSubmit={(values) => upsert.mutateAsync(values)}
      onChangeSignature={(status) => updateSignature.mutateAsync({ signature_status: status })}
    />
  )
}

function ClientContractForm({
  contracts,
  current,
  submitting,
  signatureUpdating,
  onSubmit,
  onChangeSignature,
}: {
  contracts: Array<{ id: string; name: string }>
  current: ClientContract | null | undefined
  submitting: boolean
  signatureUpdating: boolean
  onSubmit: (values: ClientContractFormValues) => Promise<unknown>
  onChangeSignature: (status: ContractSignatureStatus) => Promise<unknown>
}) {
  const [previewOpen, setPreviewOpen] = useState(false)
  const { can } = usePermissions()
  const canUpdate = can(Permission.CLIENT_UPDATE)

  const form = useForm<ClientContractFormValues>({
    resolver: formResolver<ClientContractFormValues>(clientContractSchema),
    defaultValues: {
      contract_id: current?.contract_id ?? '',
      valid_until: current?.valid_until ?? '',
    },
  })

  const options = useMemo(
    () => contracts.map((contract) => ({ value: contract.id, label: contract.name })),
    [contracts],
  )

  const signed = current?.signature_status === 'signed'
  const hasSignerData = Boolean(
    current?.signer_name || current?.signer_cpf || current?.signer_birth_date,
  )

  const handleSubmit = async (values: ClientContractFormValues) => {
    try {
      await onSubmit(values)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <>
      <Card>
        <CardContent>
          <Form form={form} onSubmit={handleSubmit} className="space-y-8">
            <Section
              title="Contrato"
              description="Selecione o contrato que o cliente irá assinar e informe o prazo de validade."
            >
              <div className="grid gap-4 sm:grid-cols-2">
                <SelectField
                  name="contract_id"
                  label="Contrato"
                  placeholder="Selecione..."
                  options={options}
                  required
                />
                <TextField
                  name="valid_until"
                  label="Prazo de validade"
                  type="date"
                  required
                />
              </div>

              {current && (
                <div className="space-y-3 rounded-xl bg-surface-2 p-4">
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm font-medium text-foreground">
                          Status da assinatura
                        </span>
                        <Badge variant={signatureStatusBadgeVariant(current.signature_status)}>
                          {current.signature_status_label ??
                            signatureStatusLabel(current.signature_status)}
                        </Badge>
                      </div>
                      <p className="text-[13px] text-muted">
                        {signed && current.signed_at
                          ? `Assinado em ${formatDateTime(current.signed_at)}`
                          : 'Marque quando o cliente assinar o contrato.'}
                      </p>
                    </div>
                    {canUpdate && (
                      <Button
                        type="button"
                        variant={signed ? 'secondary' : 'primary'}
                        loading={signatureUpdating}
                        onClick={() => {
                          void onChangeSignature(signed ? 'pending' : 'signed').catch(
                            () => undefined,
                          )
                        }}
                      >
                        {signed ? 'Marcar como pendente' : 'Marcar como assinado'}
                      </Button>
                    )}
                  </div>

                  {hasSignerData && (
                    <dl className="grid gap-3 rounded-xl bg-surface p-3.5 sm:grid-cols-3">
                      <SignerField label="Nome completo" value={current.signer_name} />
                      <SignerField label="CPF" value={formatDocument(current.signer_cpf)} />
                      <SignerField
                        label="Data de nascimento"
                        value={formatDate(current.signer_birth_date)}
                      />
                    </dl>
                  )}
                </div>
              )}

              <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-2">
                  {current && (
                    <Button type="button" variant="secondary" onClick={() => setPreviewOpen(true)}>
                      <Eye className="size-4" />
                      Ver texto gerado
                    </Button>
                  )}
                  {current?.signature_url && (
                    <a
                      href={current.signature_url}
                      target="_blank"
                      rel="noreferrer"
                      className={buttonClasses('secondary')}
                    >
                      <PenLine className="size-4" />
                      Ver assinatura
                    </a>
                  )}
                </div>
                <Button type="submit" loading={submitting}>
                  Salvar contrato
                </Button>
              </div>
            </Section>
          </Form>
        </CardContent>
      </Card>

      <Modal
        open={previewOpen}
        onClose={() => setPreviewOpen(false)}
        title={current?.contract_name ?? 'Contrato'}
        description="Texto gerado com os dados reais do cliente e do pedido."
        size="xl"
        footer={
          <Button type="button" variant="secondary" onClick={() => setPreviewOpen(false)}>
            Fechar
          </Button>
        }
      >
        <div
          className="prose prose-sm max-w-none text-foreground"
          dangerouslySetInnerHTML={{ __html: current?.body || '<p><em>Sem conteúdo.</em></p>' }}
        />
      </Modal>
    </>
  )
}
