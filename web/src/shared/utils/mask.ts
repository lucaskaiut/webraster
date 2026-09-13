import { onlyDigits } from '@/shared/utils/document'

/** Máscara progressiva de CPF: 000.000.000-00 */
export function maskCpf(value: string): string {
  const digits = onlyDigits(value).slice(0, 11)

  return digits
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d{1,2})$/, '$1-$2')
}

/** Máscara progressiva de CNPJ: 00.000.000/0000-00 */
export function maskCnpj(value: string): string {
  const digits = onlyDigits(value).slice(0, 14)

  return digits
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d)/, '.$1/$2')
    .replace(/(\d{4})(\d)/, '$1-$2')
}

/**
 * Detecta CPF ou CNPJ conforme a quantidade de dígitos digitados.
 * Até 11 dígitos usa CPF; a partir do 12º troca para CNPJ.
 */
export function maskCpfCnpj(value: string): string {
  const digits = onlyDigits(value).slice(0, 14)

  if (digits.length <= 11) {
    return maskCpf(digits)
  }

  return maskCnpj(digits)
}

/** Telefone BR: (00) 0000-0000 ou (00) 00000-0000 */
export function maskPhone(value: string): string {
  const digits = onlyDigits(value).slice(0, 11)

  if (digits.length === 0) return ''
  if (digits.length <= 2) return `(${digits}`
  if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`
  if (digits.length <= 10) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`
  }

  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`
}

/** CEP: 00000-000 */
export function maskCep(value: string): string {
  const digits = onlyDigits(value).slice(0, 8)

  if (digits.length <= 5) return digits

  return `${digits.slice(0, 5)}-${digits.slice(5)}`
}
