import { useMemo, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { Check, Copy, ExternalLink, Eye, FileArchive, FileDown, FileText, PenLine, ShieldCheck, Upload } from 'lucide-react'
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
  Switch,
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
import type {
  ClientContract,
  ContractEvidenceLevel,
  ContractSignatureEvidence,
  ContractSignatureStatus,
  EvidenceVerificationReport,
  EvidenceVerificationStatus,
} from '@/shared/types/models'
import { signatureStatusBadgeVariant, signatureStatusLabel } from '../lib/labels'
import {
  useClientContractQuery,
  useClientQuery,
  useDownloadClientContractPdf,
  useExportClientContractEvidence,
  useSetClientWithoutContract,
  useUpdateClientContractSignature,
  useUploadClientContractDocument,
  useUpsertClientContract,
  useVerifyClientContractEvidence,
} from '../hooks/useClients'

const clientContractSchema = z.object({
  contract_id: z.string().min(1, 'Selecione o contrato'),
})

type ClientContractFormValues = z.infer<typeof clientContractSchema>

const EVIDENCE_LEVEL: Record<
  ContractEvidenceLevel,
  { label: string; variant: 'neutral' | 'success' | 'warning' }
> = {
  none: { label: 'Sem evidências', variant: 'neutral' },
  legacy: { label: 'Registro anterior (legado)', variant: 'neutral' },
  pending: { label: 'Proteção pendente', variant: 'warning' },
  protected: { label: 'Manifesto assinado', variant: 'success' },
}

const VERIFICATION_STATUS: Record<
  EvidenceVerificationStatus,
  { label: string; variant: 'success' | 'danger' | 'warning' | 'neutral' }
> = {
  valid: { label: 'Válido', variant: 'success' },
  integrity_failure: { label: 'Falha de integridade', variant: 'danger' },
  missing: { label: 'Artefato ausente', variant: 'warning' },
  inconclusive: { label: 'Inconclusivo', variant: 'neutral' },
}

/** 401, 403 e 5xx já são notificados pelo interceptor HTTP. */
function notifiedGlobally(status: number): boolean {
  return status === 401 || status === 403 || status >= 500
}

function signedDocumentFilename(current: ClientContract): string {
  if (current.document_name) return current.document_name

  return current.signature_status === 'signed' ? 'contrato-assinado.pdf' : 'contrato.pdf'
}

function SignerField({ label, value }: { label: string; value: string | null }) {
  return (
    <div className="space-y-0.5">
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="text-[13px] font-medium text-foreground">{value ?? '—'}</dd>
    </div>
  )
}

function EvidenceField({ label, value }: { label: string; value: string | null }) {
  const [copied, setCopied] = useState(false)

  const handleCopy = async () => {
    if (!value) return

    try {
      await navigator.clipboard.writeText(value)
      setCopied(true)
      window.setTimeout(() => setCopied(false), 2000)
    } catch {
      toast.error('Não foi possível copiar')
    }
  }

  return (
    <div className="space-y-0.5">
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="flex items-center gap-1.5 text-[13px] font-medium text-foreground">
        <span className="truncate" title={value ?? undefined}>
          {value || '—'}
        </span>
        {value && (
          <button
            type="button"
            onClick={() => void handleCopy()}
            aria-label={`Copiar ${label}`}
            className="shrink-0 rounded p-0.5 text-subtle transition-colors hover:bg-surface-3 hover:text-foreground"
          >
            {copied ? <Check className="size-3.5 text-success" /> : <Copy className="size-3.5" />}
          </button>
        )}
      </dd>
    </div>
  )
}

export function ClientContractSection({ clientId }: { clientId: string }) {
  const contractsQuery = useContractsQuery({ per_page: 100 })
  const contractQuery = useClientContractQuery(clientId)
  const clientQuery = useClientQuery(clientId)
  const upsert = useUpsertClientContract(clientId)
  const updateSignature = useUpdateClientContractSignature(clientId)
  const downloadPdf = useDownloadClientContractPdf(clientId)
  const uploadDocument = useUploadClientContractDocument(clientId)
  const setWithoutContract = useSetClientWithoutContract(clientId)
  const verifyEvidence = useVerifyClientContractEvidence(clientId)
  const exportEvidence = useExportClientContractEvidence(clientId)

  const contracts = contractsQuery.data?.data ?? []
  const current = contractQuery.data
  const withoutContract = clientQuery.data?.without_contract ?? false

  if (contractsQuery.isPending || contractQuery.isPending || clientQuery.isPending) {
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
      withoutContract={withoutContract}
      togglingWithoutContract={setWithoutContract.isPending}
      submitting={upsert.isPending}
      signatureUpdating={updateSignature.isPending}
      downloadingPdf={downloadPdf.isPending}
      uploadingDocument={uploadDocument.isPending}
      onSubmit={(values) => upsert.mutateAsync(values)}
      onChangeSignature={(status) => updateSignature.mutateAsync({ signature_status: status })}
      onDownloadPdf={(filename) => downloadPdf.mutateAsync(filename)}
      onUploadDocument={(file) => uploadDocument.mutateAsync(file)}
      onChangeWithoutContract={(value) => setWithoutContract.mutateAsync(value)}
      verifyingEvidence={verifyEvidence.isPending}
      exportingEvidence={exportEvidence.isPending}
      onVerifyEvidence={(signatureId) => verifyEvidence.mutateAsync(signatureId)}
      onExportEvidence={(signatureId) => exportEvidence.mutateAsync(signatureId)}
    />
  )
}

function ClientContractForm({
  contracts,
  current,
  withoutContract,
  togglingWithoutContract,
  submitting,
  signatureUpdating,
  downloadingPdf,
  uploadingDocument,
  onSubmit,
  onChangeSignature,
  onDownloadPdf,
  onUploadDocument,
  onChangeWithoutContract,
  verifyingEvidence,
  exportingEvidence,
  onVerifyEvidence,
  onExportEvidence,
}: {
  contracts: Array<{ id: string; name: string }>
  current: ClientContract | null | undefined
  withoutContract: boolean
  togglingWithoutContract: boolean
  submitting: boolean
  signatureUpdating: boolean
  downloadingPdf: boolean
  uploadingDocument: boolean
  onSubmit: (values: ClientContractFormValues) => Promise<unknown>
  onChangeSignature: (status: ContractSignatureStatus) => Promise<unknown>
  onDownloadPdf: (filename?: string) => Promise<unknown>
  onUploadDocument: (file: File) => Promise<unknown>
  onChangeWithoutContract: (value: boolean) => Promise<unknown>
  verifyingEvidence: boolean
  exportingEvidence: boolean
  onVerifyEvidence: (signatureId: string) => Promise<EvidenceVerificationReport>
  onExportEvidence: (signatureId: string) => Promise<unknown>
}) {
  const [previewOpen, setPreviewOpen] = useState(false)
  const [signatureOpen, setSignatureOpen] = useState(false)
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
              <div className="flex flex-col gap-3 rounded-xl bg-surface-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                  <p className="text-sm font-medium text-foreground">Cliente sem contrato</p>
                  <p className="text-[13px] text-muted">
                    Não gera contrato para assinatura e libera o acesso do cliente no aplicativo.
                  </p>
                </div>
                <Switch
                  checked={withoutContract}
                  disabled={!canUpdate || togglingWithoutContract}
                  onCheckedChange={(checked) => {
                    void onChangeWithoutContract(checked).catch(() => undefined)
                  }}
                  label="Cliente sem contrato"
                />
              </div>

              {withoutContract ? (
                <Alert variant="success" title="Contrato dispensado">
                  Este cliente não possui contrato para assinatura e já está liberado para acessar
                  o aplicativo.
                </Alert>
              ) : (
                <>
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
                      <ButtonLink
                        to="/contracts/create"
                        variant="secondary"
                        size="sm"
                        className="mt-3"
                      >
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

                      {signed && current.evidence && (
                        <ContractEvidencePanel
                          evidence={current.evidence}
                          level={current.evidence_level ?? 'none'}
                          verifying={verifyingEvidence}
                          exporting={exportingEvidence}
                          onVerify={onVerifyEvidence}
                          onExport={onExportEvidence}
                        />
                      )}

                      {signed &&
                        !current.evidence &&
                        (current.document_hash ||
                          current.signature_hash ||
                          current.signed_ip ||
                          current.signed_user_agent) && (
                          <div className="space-y-2 rounded-xl bg-surface p-3.5">
                            <p className="text-xs font-semibold tracking-wide text-muted uppercase">
                              Evidências da assinatura
                            </p>
                            <dl className="grid gap-3 sm:grid-cols-2">
                              <EvidenceField
                                label="Hash do documento (SHA-256)"
                                value={current.document_hash}
                              />
                              <EvidenceField
                                label="Hash da assinatura (SHA-256)"
                                value={current.signature_hash}
                              />
                              <EvidenceField label="Endereço IP" value={current.signed_ip} />
                              <EvidenceField
                                label="Dispositivo"
                                value={current.signed_user_agent}
                              />
                            </dl>
                            <p className="text-[11px] text-subtle">
                              Assinatura registrada antes da cadeia de evidências: não há manifesto
                              assinado nem documento selado para verificação independente.
                            </p>
                          </div>
                        )}
                    </div>
                  )}

                  <ContractDocumentUpload
                    current={current}
                    uploading={uploadingDocument}
                    downloading={downloadingPdf}
                    canUpdate={canUpdate}
                    onUpload={onUploadDocument}
                    onDownload={onDownloadPdf}
                  />

                  <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap items-center gap-2">
                      {current && hasBody && (
                        <Button
                          type="button"
                          variant="secondary"
                          onClick={() => setPreviewOpen(true)}
                        >
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
                            void onDownloadPdf(signedDocumentFilename(current)).catch(
                              () => undefined,
                            )
                          }}
                        >
                          <FileDown className="size-4" />
                          {signed ? 'Baixar contrato' : 'Baixar PDF'}
                        </Button>
                      )}
                      {current?.signature_url && (
                        <Button
                          type="button"
                          variant="secondary"
                          onClick={() => setSignatureOpen(true)}
                        >
                          <PenLine className="size-4" />
                          Ver assinatura
                        </Button>
                      )}
                    </div>
                    {hasTemplates && (
                      <Button type="submit" loading={submitting}>
                        Salvar contrato
                      </Button>
                    )}
                  </div>
                </>
              )}
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

      <Modal
        open={signatureOpen}
        onClose={() => setSignatureOpen(false)}
        title="Assinatura do cliente"
        description="Registro gráfico capturado no aplicativo."
        footer={
          <Button type="button" variant="secondary" onClick={() => setSignatureOpen(false)}>
            Fechar
          </Button>
        }
      >
        {current?.signature_url && (
          <img
            src={current.signature_url}
            alt="Assinatura do cliente"
            className="mx-auto max-h-64 rounded-xl bg-white p-3"
          />
        )}
      </Modal>
    </>
  )
}

function ContractDocumentUpload({
  current,
  uploading,
  downloading,
  canUpdate,
  onUpload,
  onDownload,
}: {
  current: ClientContract | null | undefined
  uploading: boolean
  downloading: boolean
  canUpdate: boolean
  onUpload: (file: File) => Promise<unknown>
  onDownload: (filename?: string) => Promise<unknown>
}) {
  const uploaded = current?.evidence?.method === 'uploaded_document' || Boolean(current?.document_url)

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

      {current && uploaded ? (
        <div className="flex flex-wrap items-center gap-2">
          {current.document_url ? (
            <a
              href={current.document_url}
              target="_blank"
              rel="noreferrer"
              className={buttonClasses('secondary')}
            >
              <FileText className="size-4" />
              {current.document_name ?? 'Ver contrato enviado'}
            </a>
          ) : (
            <Button
              type="button"
              variant="secondary"
              loading={downloading}
              onClick={() => {
                void onDownload(signedDocumentFilename(current)).catch(() => undefined)
              }}
            >
              <FileText className="size-4" />
              {current.document_name ?? 'Baixar contrato enviado'}
            </Button>
          )}
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

function ContractEvidencePanel({
  evidence,
  level,
  verifying,
  exporting,
  onVerify,
  onExport,
}: {
  evidence: ContractSignatureEvidence
  level: ContractEvidenceLevel
  verifying: boolean
  exporting: boolean
  onVerify: (signatureId: string) => Promise<EvidenceVerificationReport>
  onExport: (signatureId: string) => Promise<unknown>
}) {
  const [report, setReport] = useState<EvidenceVerificationReport | null>(null)
  const levelInfo = EVIDENCE_LEVEL[level]
  const reportInfo = report ? VERIFICATION_STATUS[report.status] : null
  const failedChecks = report?.checks.filter((item) => item.status !== 'valid') ?? []
  const cpf =
    evidence.signer_cpf_masked &&
    (evidence.cpf_matches_client === false
      ? `${evidence.signer_cpf_masked} (diverge do cadastro)`
      : evidence.signer_cpf_masked)

  const handleVerify = async () => {
    try {
      setReport(await onVerify(evidence.id))
    } catch (error) {
      if (isApiError(error) && !notifiedGlobally(error.status)) {
        toast.error('Não foi possível verificar', apiErrorMessage(error))
      }
    }
  }

  const handleExport = async () => {
    try {
      await onExport(evidence.id)
    } catch (error) {
      if (isApiError(error) && !notifiedGlobally(error.status)) {
        toast.error('Não foi possível exportar', apiErrorMessage(error))
      }
    }
  }

  return (
    <div className="space-y-3 rounded-xl bg-surface p-3.5">
      <div className="flex flex-wrap items-center gap-2">
        <p className="text-xs font-semibold tracking-wide text-muted uppercase">
          Evidências da assinatura
        </p>
        <Badge variant="primary">{evidence.method_label}</Badge>
        <Badge variant={levelInfo.variant}>{levelInfo.label}</Badge>
      </div>

      <dl className="grid gap-3 sm:grid-cols-2">
        <EvidenceField label="Código de verificação" value={evidence.id} />
        <EvidenceField label="Documento final (SHA-256)" value={evidence.document_sha256} />
        <EvidenceField
          label="Documento apresentado (SHA-256)"
          value={evidence.presented_document_sha256}
        />
        {evidence.signature_image_sha256 && (
          <EvidenceField
            label="Imagem da assinatura (SHA-256)"
            value={evidence.signature_image_sha256}
          />
        )}
        <EvidenceField label="Manifesto (SHA-256)" value={evidence.manifest_sha256} />
        <EvidenceField label="Chave do manifesto" value={evidence.key_id} />
        <SignerField
          label="Versão do documento"
          value={String(evidence.document_version)}
        />
        <SignerField
          label="Assinado em (servidor)"
          value={evidence.signed_at ? formatDateTime(evidence.signed_at) : null}
        />
        <SignerField
          label="Consentimento"
          value={
            evidence.consent_explicit
              ? `Aceite explícito · termo ${evidence.consent_version ?? '—'}`
              : 'Sem aceite explícito registrado'
          }
        />
        {evidence.signer_name && (
          <SignerField label="Nome declarado" value={evidence.signer_name} />
        )}
        {cpf && <SignerField label="CPF declarado" value={cpf} />}
        <EvidenceField label="Endereço IP" value={evidence.ip} />
        <EvidenceField label="Dispositivo (declarado)" value={evidence.user_agent} />
        {evidence.app_version && (
          <SignerField label="Versão do app (declarada)" value={evidence.app_version} />
        )}
      </dl>

      {level === 'pending' && (
        <p className="text-[11px] text-warning">
          O manifesto ainda não foi assinado pelo servidor. A proteção é concluída
          automaticamente quando a chave de assinatura estiver configurada.
        </p>
      )}

      {report && reportInfo && (
        <div className="space-y-2 rounded-lg bg-surface-2 p-3">
          <div className="flex items-center gap-2">
            <span className="text-[13px] font-medium text-foreground">Verificação</span>
            <Badge variant={reportInfo.variant}>{reportInfo.label}</Badge>
          </div>
          {failedChecks.length > 0 && (
            <ul className="space-y-1 text-[12px] text-muted">
              {failedChecks.map((item) => (
                <li key={item.check}>
                  <span className="font-medium text-foreground">{item.check}</span>:{' '}
                  {VERIFICATION_STATUS[item.status].label} — {item.detail}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      <div className="flex flex-wrap items-center gap-2">
        <Button
          type="button"
          variant="secondary"
          size="sm"
          loading={verifying}
          onClick={() => void handleVerify()}
        >
          <ShieldCheck className="size-4" />
          Verificar integridade
        </Button>
        <Button
          type="button"
          variant="secondary"
          size="sm"
          loading={exporting}
          onClick={() => void handleExport()}
        >
          <FileArchive className="size-4" />
          Exportar evidências
        </Button>
        <a
          href={`/validar-contrato/${evidence.id}`}
          target="_blank"
          rel="noreferrer"
          className={buttonClasses('secondary', 'sm')}
        >
          <ExternalLink className="size-4" />
          Página de validação
        </a>
      </div>

      <p className="text-[11px] text-subtle">
        As evidências comprovam a integridade do documento e o contexto do registro; não
        comprovam, isoladamente, a identidade física de quem assinou.
      </p>
    </div>
  )
}
