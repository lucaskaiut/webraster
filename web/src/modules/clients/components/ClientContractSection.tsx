import { useMemo, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { Eye, FileDown, FileText, PenLine, Upload } from 'lucide-react'
import {
  Alert,
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Modal,
  Section,
  SelectField,
  buttonClasses,
} from '@/shared/design-system'
import { apiErrorMessage, isApiError } from '@/shared/api/errors'
import { MAX_UPLOAD_BYTES, MAX_UPLOAD_LABEL } from '@/shared/api/uploads'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { toast } from '@/shared/stores/toast.store'
import { formatDocument } from '@/shared/utils/document'
import { formatDate, formatDateTime } from '@/shared/utils/format'
import { applyApiErrorsToForm, formResolver } from '@/shared/utils/forms'
import { useContractsQuery } from '@/modules/contracts/hooks/useContracts'
import type { ClientContract, ContractSignatureStatus } from '@/shared/types/models'
import { signatureStatusBadgeVariant, signatureStatusLabel } from '../lib/labels'
import {
  useClientContractQuery,
  useDownloadClientContractPdf,
  useUpdateClientContractSignature,
  useUploadClientContractDocument,
  useUpsertClientContract,
} from '../hooks/useClients'

const clientContractSchema = z.object({
  contract_id: z.string().min(1, 'Selecione o contrato'),
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
  const downloadPdf = useDownloadClientContractPdf(clientId)
  const uploadDocument = useUploadClientContractDocument(clientId)

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

  return (
    <ClientContractForm
      contracts={contracts}
      current={current}
      submitting={upsert.isPending}
      signatureUpdating={updateSignature.isPending}
      downloadingPdf={downloadPdf.isPending}
      uploadingDocument={uploadDocument.isPending}
      onSubmit={(values) => upsert.mutateAsync(values)}
      onChangeSignature={(status) => updateSignature.mutateAsync({ signature_status: status })}
      onDownloadPdf={() => downloadPdf.mutateAsync()}
      onUploadDocument={(file) => uploadDocument.mutateAsync(file)}
    />
  )
}

function ClientContractForm({
  contracts,
  current,
  submitting,
  signatureUpdating,
  downloadingPdf,
  uploadingDocument,
  onSubmit,
  onChangeSignature,
  onDownloadPdf,
  onUploadDocument,
}: {
  contracts: Array<{ id: string; name: string }>
  current: ClientContract | null | undefined
  submitting: boolean
  signatureUpdating: boolean
  downloadingPdf: boolean
  uploadingDocument: boolean
  onSubmit: (values: ClientContractFormValues) => Promise<unknown>
  onChangeSignature: (status: ContractSignatureStatus) => Promise<unknown>
  onDownloadPdf: () => Promise<unknown>
  onUploadDocument: (file: File) => Promise<unknown>
}) {
  const [previewOpen, setPreviewOpen] = useState(false)
  const { can } = usePermissions()
  const canUpdate = can(Permission.CLIENT_UPDATE)

  const form = useForm<ClientContractFormValues>({
    resolver: formResolver<ClientContractFormValues>(clientContractSchema),
    defaultValues: {
      contract_id: current?.contract_id ?? '',
    },
  })

  const options = useMemo(
    () => contracts.map((contract) => ({ value: contract.id, label: contract.name })),
    [contracts],
  )

  const hasTemplates = contracts.length > 0
  const signed = current?.signature_status === 'signed'
  const hasSignerData = Boolean(
    current?.signer_name || current?.signer_cpf || current?.signer_birth_date,
  )
  const hasBody = Boolean(current?.body?.trim())

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
              description="Vincule um modelo para assinatura digital ou importe um contrato já assinado."
            >
              {hasTemplates ? (
                <SelectField
                  name="contract_id"
                  label="Contrato"
                  placeholder="Selecione..."
                  options={options}
                  required
                />
              ) : (
                <Alert variant="info" title="Nenhum modelo de contrato cadastrado">
                  <p>
                    Para usar a assinatura digital no app, cadastre um modelo de contrato. Você
                    ainda pode importar um contrato já assinado abaixo.
                  </p>
                  <ButtonLink to="/contracts/create" variant="secondary" size="sm" className="mt-3">
                    Cadastrar contrato
                  </ButtonLink>
                </Alert>
              )}

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

              <ContractDocumentUpload
                current={current}
                uploading={uploadingDocument}
                canUpdate={canUpdate}
                onUpload={onUploadDocument}
              />

              <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-2">
                  {current && hasBody && (
                    <Button type="button" variant="secondary" onClick={() => setPreviewOpen(true)}>
                      <Eye className="size-4" />
                      Ver texto gerado
                    </Button>
                  )}
                  {current && (
                    <Button
                      type="button"
                      variant="secondary"
                      loading={downloadingPdf}
                      onClick={() => {
                        void onDownloadPdf().catch(() => undefined)
                      }}
                    >
                      <FileDown className="size-4" />
                      {current.document_url ? 'Baixar contrato' : 'Baixar PDF'}
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
                {hasTemplates && (
                  <Button type="submit" loading={submitting}>
                    Salvar contrato
                  </Button>
                )}
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

function ContractDocumentUpload({
  current,
  uploading,
  canUpdate,
  onUpload,
}: {
  current: ClientContract | null | undefined
  uploading: boolean
  canUpdate: boolean
  onUpload: (file: File) => Promise<unknown>
}) {
  const inputRef = useRef<HTMLInputElement>(null)

  const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return

    if (file.size > MAX_UPLOAD_BYTES) {
      toast.error('Arquivo muito grande', `O limite para envio é de ${MAX_UPLOAD_LABEL}.`)
      return
    }

    void onUpload(file).catch((error) => {
      if (isApiError(error) && error.status === 422) {
        toast.error('Arquivo inválido', apiErrorMessage(error))
      }
    })
  }

  return (
    <div className="space-y-3 rounded-xl bg-surface-2 p-4">
      <div className="space-y-1">
        <p className="text-sm font-medium text-foreground">Contrato já assinado</p>
        <p className="text-[13px] text-muted">
          Se o cliente já possui um contrato ativo assinado (por exemplo, vindo de outra
          plataforma), envie o arquivo para marcar como assinado sem exigir uma nova assinatura.
        </p>
      </div>

      <input
        ref={inputRef}
        type="file"
        accept=".pdf,image/png,image/jpeg,image/webp"
        className="hidden"
        onChange={handleFileChange}
      />

      {current?.document_url ? (
        <div className="flex flex-wrap items-center gap-2">
          <a
            href={current.document_url}
            target="_blank"
            rel="noreferrer"
            className={buttonClasses('secondary')}
          >
            <FileText className="size-4" />
            {current.document_name ?? 'Ver contrato enviado'}
          </a>
          {canUpdate && (
            <Button
              type="button"
              variant="secondary"
              loading={uploading}
              onClick={() => inputRef.current?.click()}
            >
              <Upload className="size-4" />
              Substituir arquivo
            </Button>
          )}
        </div>
      ) : canUpdate ? (
        <Button
          type="button"
          variant="secondary"
          loading={uploading}
          onClick={() => inputRef.current?.click()}
        >
          <Upload className="size-4" />
          Enviar contrato assinado
        </Button>
      ) : null}
    </div>
  )
}
