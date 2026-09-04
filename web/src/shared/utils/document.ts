export function onlyDigits(value: string): string {
  return value.replace(/\D+/g, '')
}

function cpfCheckDigit(digits: string, position: number): number {
  let sum = 0

  for (let i = 0; i < position; i++) {
    sum += Number(digits[i]) * (position + 1 - i)
  }

  return ((10 * sum) % 11) % 10
}

function cnpjCheckDigit(digits: string, position: number): number {
  const weights =
    position === 12
      ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
      : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]

  const sum = weights.reduce((acc, weight, index) => acc + Number(digits[index]) * weight, 0)
  const remainder = sum % 11

  return remainder < 2 ? 0 : 11 - remainder
}

export function isValidCpf(value: string): boolean {
  const digits = onlyDigits(value)

  if (digits.length !== 11 || /^(\d)\1{10}$/.test(digits)) return false

  return (
    Number(digits[9]) === cpfCheckDigit(digits, 9) &&
    Number(digits[10]) === cpfCheckDigit(digits, 10)
  )
}

export function isValidCnpj(value: string): boolean {
  const digits = onlyDigits(value)

  if (digits.length !== 14 || /^(\d)\1{13}$/.test(digits)) return false

  return (
    Number(digits[12]) === cnpjCheckDigit(digits, 12) &&
    Number(digits[13]) === cnpjCheckDigit(digits, 13)
  )
}

export function isValidCpfOrCnpj(value: string): boolean {
  return isValidCpf(value) || isValidCnpj(value)
}

export function formatDocument(value: string | null | undefined): string {
  if (!value) return '—'

  const digits = onlyDigits(value)

  if (digits.length === 11) {
    return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4')
  }

  if (digits.length === 14) {
    return digits.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5')
  }

  return value
}
