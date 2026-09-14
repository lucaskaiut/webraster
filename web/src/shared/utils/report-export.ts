export function escapeHtml(value: unknown): string {
  return String(value ?? '').replace(
    /[&<>"]/g,
    (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[char] ?? char,
  )
}

export function downloadBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  anchor.click()
  URL.revokeObjectURL(url)
}

const PRINT_STYLES = `
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 24px; font-size: 12px; }
  h1 { font-size: 16px; margin: 0 0 4px; }
  p.meta { color: #555; margin: 0 0 16px; font-size: 12px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
  th { background: #f2f2f2; font-weight: 600; }
  @media print { body { margin: 0; } }
`

/**
 * Abre uma janela de impressão com o conteúdo HTML e dispara o print
 * (o usuário pode salvar como PDF pelo navegador).
 */
export function printReportHtml(title: string, contentHtml: string): void {
  const html = `<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <title>${escapeHtml(title)}</title>
    <style>${PRINT_STYLES}</style>
  </head>
  <body>${contentHtml}</body>
</html>`

  const win = window.open('', '_blank', 'width=900,height=700')

  if (!win) return

  win.document.open()
  win.document.write(html)
  win.document.close()
  win.focus()
  win.addEventListener('afterprint', () => win.close())
  win.print()
}
