const URL_PATTERN = /^(https?:\/\/|www\.)\S+$/i

/** Retorna a URL quando o ponto de referência é um link (ex.: Google Maps). */
export function addressReferenceUrl(value: string | null | undefined): string | null {
  const trimmed = value?.trim() ?? ''

  if (!URL_PATTERN.test(trimmed)) return null

  return trimmed.toLowerCase().startsWith('www.') ? `https://${trimmed}` : trimmed
}
