import type { ReactNode } from 'react'

/**
 * Renderizador Markdown leve (sem dependências externas).
 * Suporta: títulos, parágrafos, negrito, itálico, código inline,
 * blocos de código, listas ordenadas/não ordenadas, tabelas,
 * citações, links e linhas horizontais.
 */
export function Markdown({ content }: { content: string }) {
  const blocks = parseBlocks(content)

  return (
    <div className="space-y-3 text-sm leading-relaxed text-foreground">
      {blocks.map((block, index) => renderBlock(block, index))}
    </div>
  )
}

interface InlineToken {
  type: 'text' | 'strong' | 'em' | 'code' | 'link'
  content: string
  href?: string
}

function parseInline(text: string): InlineToken[] {
  const regex = /(\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`|\[[^\]]*\]\([^)]*\))/g
  const tokens: InlineToken[] = []
  let lastIndex = 0
  let match: RegExpExecArray | null

  const pushText = (value: string) => {
    if (value !== '') tokens.push({ type: 'text', content: value })
  }

  while ((match = regex.exec(text)) !== null) {
    pushText(text.slice(lastIndex, match.index))

    const raw = match[0]

    if (raw.startsWith('**')) {
      tokens.push({ type: 'strong', content: raw.slice(2, -2) })
    } else if (raw.startsWith('*')) {
      tokens.push({ type: 'em', content: raw.slice(1, -1) })
    } else if (raw.startsWith('`')) {
      tokens.push({ type: 'code', content: raw.slice(1, -1) })
    } else {
      const linkMatch = raw.match(/^\[([^\]]*)\]\(([^)]*)\)$/)
      if (linkMatch) {
        tokens.push({ type: 'link', content: linkMatch[1], href: linkMatch[2] })
      } else {
        pushText(raw)
      }
    }

    lastIndex = regex.lastIndex
  }

  pushText(text.slice(lastIndex))

  return tokens
}

function renderInline(text: string): ReactNode[] {
  return parseInline(text).map((token, index) => {
    switch (token.type) {
      case 'strong':
        return (
          <strong key={index} className="font-semibold text-foreground">
            {token.content}
          </strong>
        )
      case 'em':
        return (
          <em key={index} className="italic">
            {token.content}
          </em>
        )
      case 'code':
        return (
          <code key={index} className="rounded bg-surface-2 px-1.5 py-0.5 font-mono text-[13px]">
            {token.content}
          </code>
        )
      case 'link':
        return (
          <a key={index} href={token.href} target="_blank" rel="noreferrer" className="text-primary underline">
            {token.content}
          </a>
        )
      default:
        return <span key={index}>{token.content}</span>
    }
  })
}

type Block =
  | { type: 'heading'; level: number; text: string }
  | { type: 'paragraph'; text: string }
  | { type: 'code'; code: string }
  | { type: 'list'; ordered: boolean; items: string[] }
  | { type: 'table'; header: string[]; rows: string[][] }
  | { type: 'quote'; text: string }
  | { type: 'rule' }

function parseBlocks(content: string): Block[] {
  const lines = content.split('\n')
  const blocks: Block[] = []
  let index = 0

  const collectParagraph = (start: string): string => {
    const buffer = [start]
    while (
      index + 1 < lines.length &&
      lines[index + 1].trim() !== '' &&
      !isBlockStart(lines[index + 1])
    ) {
      index += 1
      buffer.push(lines[index].trim())
    }
    return buffer.join(' ')
  }

  while (index < lines.length) {
    const line = lines[index]

    if (line.trim() === '') {
      index += 1
      continue
    }

    if (line.startsWith('```')) {
      const codeLines: string[] = []
      index += 1
      while (index < lines.length && !lines[index].startsWith('```')) {
        codeLines.push(lines[index])
        index += 1
      }
      index += 1
      blocks.push({ type: 'code', code: codeLines.join('\n') })
      continue
    }

    const heading = line.match(/^(#{1,6})\s+(.*)$/)
    if (heading) {
      blocks.push({ type: 'heading', level: heading[1].length, text: heading[2] })
      index += 1
      continue
    }

    if (/^(\s*[-*_])(\s*)$/.test(line.trim())) {
      blocks.push({ type: 'rule' })
      index += 1
      continue
    }

    if (/^\s*[-*+]\s+/.test(line)) {
      const items: string[] = []
      while (index < lines.length && /^\s*[-*+]\s+/.test(lines[index])) {
        items.push(lines[index].replace(/^\s*[-*+]\s+/, ''))
        index += 1
      }
      blocks.push({ type: 'list', ordered: false, items })
      continue
    }

    if (/^\s*\d+[.)]\s+/.test(line)) {
      const items: string[] = []
      while (index < lines.length && /^\s*\d+[.)]\s+/.test(lines[index])) {
        items.push(lines[index].replace(/^\s*\d+[.)]\s+/, ''))
        index += 1
      }
      blocks.push({ type: 'list', ordered: true, items })
      continue
    }

    if (line.startsWith('>')) {
      const quoteLines = [line.replace(/^>\s?/, '')]
      while (index + 1 < lines.length && lines[index + 1].startsWith('>')) {
        index += 1
        quoteLines.push(lines[index].replace(/^>\s?/, ''))
      }
      blocks.push({ type: 'quote', text: quoteLines.join(' ') })
      index += 1
      continue
    }

    if (line.includes('|') && index + 1 < lines.length && /^\s*\|?[\s:-]+\|[\s:|-]*$/.test(lines[index + 1])) {
      const header = splitTableRow(line)
      index += 2
      const rows: string[][] = []
      while (index < lines.length && lines[index].includes('|')) {
        rows.push(splitTableRow(lines[index]))
        index += 1
      }
      blocks.push({ type: 'table', header, rows })
      continue
    }

    blocks.push({ type: 'paragraph', text: collectParagraph(line.trim()) })
    index += 1
  }

  return blocks
}

function isBlockStart(line: string): boolean {
  const trimmed = line.trim()
  return (
    trimmed.startsWith('#') ||
    trimmed.startsWith('```') ||
    trimmed.startsWith('>') ||
    trimmed.startsWith('- ') ||
    trimmed.startsWith('* ') ||
    trimmed.startsWith('+ ') ||
    /^\d+[.)]\s+/.test(trimmed) ||
    trimmed.includes('|')
  )
}

function splitTableRow(line: string): string[] {
  return line
    .trim()
    .replace(/^\|/, '')
    .replace(/\|$/, '')
    .split('|')
    .map((cell) => cell.trim())
}

function renderBlock(block: Block, key: number): ReactNode {
  const headingClasses: Record<number, string> = {
    1: 'text-lg font-semibold',
    2: 'text-base font-semibold',
    3: 'text-sm font-semibold',
    4: 'text-sm font-semibold',
    5: 'text-sm font-medium',
    6: 'text-sm font-medium',
  }

  switch (block.type) {
    case 'heading':
      return (
        <div key={key} className={`pt-1 text-foreground ${headingClasses[block.level] ?? 'text-sm'}`}>
          {renderInline(block.text)}
        </div>
      )
    case 'paragraph':
      return <p key={key}>{renderInline(block.text)}</p>
    case 'code':
      return (
        <pre key={key} className="overflow-x-auto rounded-lg border border-surface-3 bg-surface-2 p-3 font-mono text-[13px] text-foreground">
          <code>{block.code}</code>
        </pre>
      )
    case 'list':
      if (block.ordered) {
        return (
          <ol key={key} className="list-decimal space-y-1 pl-6">
            {block.items.map((item, i) => (
              <li key={i}>{renderInline(item)}</li>
            ))}
          </ol>
        )
      }
      return (
        <ul key={key} className="list-disc space-y-1 pl-6">
          {block.items.map((item, i) => (
            <li key={i}>{renderInline(item)}</li>
          ))}
        </ul>
      )
    case 'table':
      return (
        <div key={key} className="overflow-x-auto rounded-lg border border-surface-3">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-surface-2">
                {block.header.map((cell, i) => (
                  <th key={i} className="px-3 py-2 text-left font-semibold text-foreground">
                    {renderInline(cell)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {block.rows.map((row, r) => (
                <tr key={r} className="border-t border-surface-3">
                  {row.map((cell, c) => (
                    <td key={c} className="px-3 py-2 align-top text-muted">
                      {renderInline(cell)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )
    case 'quote':
      return (
        <blockquote key={key} className="border-l-2 border-primary/40 pl-3 text-muted">
          {renderInline(block.text)}
        </blockquote>
      )
    case 'rule':
      return <hr key={key} className="border-surface-3" />
  }
}
