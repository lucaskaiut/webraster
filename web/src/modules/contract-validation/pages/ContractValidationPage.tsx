import { useRef, useState, type FormEvent, type ReactNode } from 'react'
import { useNavigate, useParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import {
  FileCheck2,
  FileSearch,
  ShieldAlert,
  ShieldCheck,
  ShieldQuestion,
  TriangleAlert,
} from 'lucide-react'
import { isApiError } from '@/shared/api/errors'
import { AppLogo } from '@/shared/brand/AppLogo'
import { Button, Card, CardContent, Input, ThemeToggle } from '@/shared/design-system'
import { formatDateTimeWithSeconds } from '@/shared/utils/format'
import { cn } from '@/shared/utils/cn'
import {
  contractValidationService,
  sha256Hex,
  type PublicSignatureValidation,
} from '../services/contract-validation.service'

/**
 * Página pública (sem login) aberta pelo QR Code do contrato assinado.
 * Exibe apenas o mínimo para conferir a autenticidade do documento.
 */
const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

type Tone = 'success' | 'danger' | 'warning' | 'neutral'

const TONE_CLASSES: Record<Tone, string> = {
  success: 'bg-success-soft text-success',
  danger: 'bg-danger-soft text-danger',
  warning: 'bg-warning-soft text-warning',
  neutral: 'bg-surface-2 text-muted',
}

function verdict(result: PublicSignatureValidation): {
  tone: Tone
  icon: ReactNode
  title: string
  description: string
} {
  if (result.status === 'integrity_failure') {
    return {
      tone: 'danger',
      icon: <ShieldAlert className="size-7" />,
      title: 'Documento com falha de integridade',
      description:
        'Os registros desta assinatura não conferem com os dados originais. Não confie neste documento.',
    }
  }

  if (result.status === 'missing') {
    return {
      tone: 'warning',
      icon: <TriangleAlert className="size-7" />,
      title: 'Registro incompleto',
      description: 'Parte das evidências desta assinatura não está disponível para conferência.',
    }
  }

  if (result.superseded) {
    return {
      tone: 'warning',
      icon: <TriangleAlert className="size-7" />,
      title: 'Assinatura substituída',
      description: `Esta assinatura foi substituída em ${formatDateTimeWithSeconds(result.superseded_at)} e não representa mais o contrato vigente.`,
    }
  }

  if (result.status === 'valid') {
    return {
      tone: 'success',
      icon: <ShieldCheck className="size-7" />,
      title: 'Assinatura válida',
      description: 'O documento e as evidências conferem com o registro feito no momento da assinatura.',
    }
  }

  return {
    tone: 'neutral',
    icon: <ShieldQuestion className="size-7" />,
    title: 'Validação em processamento',
    description:
      'A assinatura foi registrada, mas a proteção das evidências ainda não foi concluída. Tente novamente em alguns minutos.',
  }
}

function Field({ label, value, mono }: { label: string; value: string | null; mono?: boolean }) {
  return (
    <div className="space-y-0.5">
      <dt className="text-xs text-muted">{label}</dt>
      <dd
        className={cn(
          'text-[13px] font-medium text-foreground',
          mono && 'font-mono text-xs break-all',
        )}
      >
        {value || '—'}
      </dd>
    </div>
  )
}

export default function ContractValidationPage() {
  const { code } = useParams<{ code?: string }>()
  const navigate = useNavigate()
  const [input, setInput] = useState(code ?? '')
  const validCode = code && UUID_PATTERN.test(code) ? code.toLowerCase() : null

  const query = useQuery({
    queryKey: ['public', 'contract-signature', validCode],
    queryFn: () => contractValidationService.validate(validCode as string),
    enabled: Boolean(validCode),
    retry: false,
  })

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()
    const value = input.trim()
    if (value) navigate(`/validar-contrato/${encodeURIComponent(value)}`)
  }

  const notFound =
    (code && !validCode) || (query.isError && isApiError(query.error) && query.error.status === 404)

  return (
    <div className="flex h-dvh flex-col overflow-hidden bg-background">
      <header className="shrink-0 bg-surface px-5 py-5 shadow-card">
        <div className="mx-auto flex max-w-3xl items-center justify-between gap-4">
          <AppLogo size="sm" />
          <ThemeToggle />
        </div>
      </header>

      <main className="mx-auto min-h-0 w-full max-w-3xl flex-1 space-y-6 overflow-y-auto overscroll-contain px-5 py-8">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Validar contrato assinado</h1>
          <p className="mt-2 text-sm text-muted">
            Leia o QR Code impresso no contrato ou informe o código de verificação.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="flex flex-col gap-2 sm:flex-row">
          <Input
            value={input}
            onChange={(event) => setInput(event.target.value)}
            placeholder="Código de verificação"
            aria-label="Código de verificação"
            className="font-mono"
          />
          <Button type="submit" className="shrink-0">
            <FileSearch className="size-4" />
            Validar
          </Button>
        </form>

        {query.isFetching && <p className="text-sm text-muted">Validando assinatura...</p>}

        {notFound && (
          <Card>
            <CardContent className="flex items-start gap-3">
              <ShieldQuestion className="mt-0.5 size-6 shrink-0 text-muted" />
              <div>
                <p className="font-semibold text-foreground">Código não encontrado</p>
                <p className="text-sm text-muted">
                  Confira o código impresso no contrato. Nenhuma assinatura corresponde a ele.
                </p>
              </div>
            </CardContent>
          </Card>
        )}

        {query.isError && !notFound && (
          <Card>
            <CardContent>
              <p className="text-sm text-muted">
                Não foi possível validar agora. Tente novamente em instantes.
              </p>
            </CardContent>
          </Card>
        )}

        {query.data && <ValidationResult result={query.data} />}
      </main>
    </div>
  )
}

function ValidationResult({ result }: { result: PublicSignatureValidation }) {
  const info = verdict(result)

  return (
    <>
      <Card>
        <CardContent className="space-y-6">
          <div className="flex items-start gap-4">
            <span className={cn('rounded-xl p-3', TONE_CLASSES[info.tone])}>{info.icon}</span>
            <div className="space-y-1">
              <p className="text-lg font-semibold text-foreground">{info.title}</p>
              <p className="text-sm text-muted">{info.description}</p>
            </div>
          </div>

          <dl className="grid gap-4 sm:grid-cols-2">
            <Field label="Empresa" value={result.company} />
            <Field label="Contrato" value={result.contract_name} />
            <Field label="Assinado por" value={result.signer_name} />
            <Field label="CPF" value={result.signer_cpf_masked} />
            <Field label="Data e hora" value={formatDateTimeWithSeconds(result.signed_at)} />
            <Field label="Forma de assinatura" value={result.method_label} />
            <Field label="Versão do documento" value={String(result.document_version)} />
            <Field
              label="Consentimento"
              value={result.consent_explicit ? 'Aceite explícito registrado' : 'Não registrado'}
            />
          </dl>
        </CardContent>
      </Card>

      <FileCheck expectedHash={result.document_sha256} />

      <Card>
        <CardContent className="space-y-4">
          <p className="text-sm font-semibold text-foreground">Dados técnicos</p>
          <dl className="grid gap-4">
            <Field label="Código de verificação" value={result.code} mono />
            <Field label="SHA-256 do documento" value={result.document_sha256} mono />
            <Field label="SHA-256 do manifesto de evidências" value={result.manifest_sha256} mono />
            <Field label="Chave do servidor (Ed25519)" value={result.key_id} mono />
          </dl>
          <p className="text-xs text-subtle">
            Assinatura eletrônica com registro de evidências. Não se trata de assinatura digital
            com certificado ICP-Brasil. A validação confirma a integridade do documento e o
            registro da assinatura; não comprova, isoladamente, a identidade de quem assinou.
          </p>
        </CardContent>
      </Card>
    </>
  )
}

function FileCheck({ expectedHash }: { expectedHash: string | null }) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [state, setState] = useState<
    { status: 'idle' } | { status: 'checking' } | { status: 'done'; name: string; matches: boolean }
  >({ status: 'idle' })

  if (!expectedHash) return null

  const handleFile = async (file: File | undefined) => {
    if (!file) return

    setState({ status: 'checking' })
    const hash = await sha256Hex(file)
    setState({ status: 'done', name: file.name, matches: hash === expectedHash })
  }

  return (
    <Card>
      <CardContent className="space-y-3">
        <div className="space-y-1">
          <p className="text-sm font-semibold text-foreground">Conferir arquivo</p>
          <p className="text-[13px] text-muted">
            Selecione o PDF que você recebeu para confirmar que é exatamente o documento assinado.
            A conferência é feita no seu navegador; o arquivo não é enviado.
          </p>
        </div>

        <input
          ref={inputRef}
          type="file"
          accept="application/pdf,.pdf"
          className="hidden"
          onChange={(event) => {
            const file = event.target.files?.[0]
            event.target.value = ''
            void handleFile(file)
          }}
        />

        <Button
          type="button"
          variant="secondary"
          loading={state.status === 'checking'}
          onClick={() => inputRef.current?.click()}
        >
          <FileCheck2 className="size-4" />
          Selecionar PDF
        </Button>

        {state.status === 'done' && (
          <p
            className={cn(
              'rounded-lg px-3 py-2 text-[13px] font-medium',
              state.matches ? TONE_CLASSES.success : TONE_CLASSES.danger,
            )}
          >
            {state.matches
              ? `“${state.name}” é idêntico ao documento assinado.`
              : `“${state.name}” não corresponde ao documento assinado (o arquivo foi alterado ou é outro documento).`}
          </p>
        )}
      </CardContent>
    </Card>
  )
}
