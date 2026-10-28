export const DEFAULT_FAVICON = '/favicon.png'

let requestId = 0

function setFaviconLinks(href: string): void {
  document
    .querySelectorAll<HTMLLinkElement>('link[rel="icon"], link[rel="apple-touch-icon"]')
    .forEach((link) => {
      link.href = href
    })
}

/**
 * Aplica o favicon do tenant na aba do navegador. Se a imagem não carregar
 * (URL inválida/removida), mantém o favicon padrão da aplicação.
 */
export function applyFavicon(url?: string | null): void {
  requestId += 1
  const currentRequest = requestId
  const href = url || DEFAULT_FAVICON

  if (href === DEFAULT_FAVICON) {
    setFaviconLinks(DEFAULT_FAVICON)
    return
  }

  const image = new Image()

  image.onload = () => {
    if (currentRequest === requestId) {
      setFaviconLinks(href)
    }
  }

  image.onerror = () => {
    if (currentRequest === requestId) {
      setFaviconLinks(DEFAULT_FAVICON)
    }
  }

  image.src = href
}
