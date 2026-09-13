import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { Eye, FileText } from 'lucide-react'
import {
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
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm, formResolver } from '@/shared/utils/forms'
import { useContractsQuery } from '@/modules/contracts/hooks/useContracts'
import type { ClientContract } from '@/shared/types/models'
import { useClientContractQuery, useUpsertClientContract } from '../hooks/useClients'

const clientContractSchema = z.object({
  contract_id: z.string().min(1, 'Selecione o contrato'),
  valid_until: z.string().min(1, 'Informe o prazo de validade'),
})

type ClientContractFormValues = z.infer<typeof clientContractSchema>

export function ClientContractSection({ clientId }: { clientId: string }) {
  const contractsQuery = useContractsQuery({ per_page: 100 })
  const contractQuery = useClientContractQuery(clientId)
  const upsert = useUpsertClientContract(clientId)

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
      onSubmit={(values) => upsert.mutateAsync(values)}
    />
  )
}

function ClientContractForm({
  contracts,
  current,
  submitting,
  onSubmit,
}: {
  contracts: Array<{ id: string; name: string }>
  current: ClientContract | null | undefined
  submitting: boolean
  onSubmit: (values: ClientContractFormValues) => Promise<unknown>
}) {
  const [previewOpen, setPreviewOpen] = useState(false)

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

              <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                {current ? (
                  <Button type="button" variant="secondary" onClick={() => setPreviewOpen(true)}>
                    <Eye className="size-4" />
                    Ver texto gerado
                  </Button>
                ) : (
                  <span />
                )}
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
