import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'
import type { ContractSignatureMethod, EvidenceVerificationStatus } from '@/shared/types/models'

export interface PublicSignatureValidation {
  code: string
  status: EvidenceVerificationStatus
  protected: boolean
  superseded: boolean
  superseded_at: string | null
  method: ContractSignatureMethod
  method_label: string
  signed_at: string | null
  company: string | null
  contract_name: string | null
  document_version: number
  signer_name: string | null
  signer_cpf_masked: string | null
  consent_explicit: boolean
  document_sha256: string | null
  manifest_sha256: string | null
  key_id: string | null
}

export const contractValidationService = {
  async validate(code: string): Promise<PublicSignatureValidation> {
    const response = await http.get<ApiResponse<PublicSignatureValidation>>(
      `/public/contract-signatures/${encodeURIComponent(code)}`,
    )

    return response.data.data
  },
}

/** SHA-256 calculado no navegador: o arquivo não é enviado ao servidor. */
export async function sha256Hex(file: File): Promise<string> {
  const digest = await crypto.subtle.digest('SHA-256', await file.arrayBuffer())

  return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('')
}
