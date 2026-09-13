import { onlyDigits } from '@/shared/utils/document'
import { maskCep } from '@/shared/utils/mask'

export interface ViaCepAddress {
  zip: string
  street: string
  complement: string
  neighborhood: string
  city: string
  state: string
}

interface ViaCepResponse {
  cep?: string
  logradouro?: string
  complemento?: string
  bairro?: string
  localidade?: string
  uf?: string
  erro?: boolean
}

export async function fetchAddressByCep(cep: string): Promise<ViaCepAddress | null> {
  const digits = onlyDigits(cep)

  if (digits.length !== 8) return null

  const response = await fetch(`https://viacep.com.br/ws/${digits}/json/`)

  if (!response.ok) {
    throw new Error('Falha ao consultar o CEP')
  }

  const data = (await response.json()) as ViaCepResponse

  if (data.erro) return null

  return {
    zip: maskCep(digits),
    street: data.logradouro ?? '',
    complement: data.complemento ?? '',
    neighborhood: data.bairro ?? '',
    city: data.localidade ?? '',
    state: data.uf ?? '',
  }
}
