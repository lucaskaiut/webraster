import { useEffect, useRef, useState, type KeyboardEvent } from 'react'
import { ArrowUp, Mic, Paperclip } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { toast } from '@/shared/stores/toast.store'

export function ChatInput({
  disabled,
  onSend,
  onStop,
}: {
  disabled?: boolean
  onSend: (content: string) => void
  onStop?: () => void
}) {
  const [value, setValue] = useState('')
  const textareaRef = useRef<HTMLTextAreaElement>(null)

  useEffect(() => {
    const textarea = textareaRef.current
    if (!textarea) return

    textarea.style.height = 'auto'
    textarea.style.height = `${Math.min(textarea.scrollHeight, 200)}px`
  }, [value])

  const submit = () => {
    const content = value.trim()
    if (content === '' || disabled) return
    setValue('')
    onSend(content)
  }

  const handleKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault()
      submit()
    }
  }

  return (
    <div className="flex items-end gap-2 rounded-2xl border border-surface-3 bg-surface p-2.5 shadow-raised transition-all duration-200 focus-within:border-primary/40 focus-within:ring-2 focus-within:ring-ring/40">
      <button
        type="button"
        onClick={() => toast.info('Upload de arquivos', 'Em breve: envio de imagens, PDF e outros arquivos.')}
        aria-label="Anexar arquivo"
        className="flex size-9 shrink-0 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground"
      >
        <Paperclip className="size-4.5" />
      </button>

      <textarea
        ref={textareaRef}
        value={value}
        onChange={(event) => setValue(event.target.value)}
        onKeyDown={handleKeyDown}
        rows={1}
        placeholder="Envie uma mensagem..."
        aria-label="Mensagem"
        className="max-h-[200px] min-h-9 flex-1 resize-none bg-transparent py-2 text-[15px] text-foreground outline-none placeholder:text-subtle"
      />

      {disabled ? (
        <button
          type="button"
          onClick={onStop}
          aria-label="Parar geração"
          className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface-2 text-foreground transition-colors hover:bg-surface-3"
        >
          <span className="size-3 rounded-sm bg-current" />
        </button>
      ) : (
        <>
          <button
            type="button"
            onClick={() => toast.info('Entrada por voz', 'Em breve.')}
            aria-label="Entrada por voz"
            className="flex size-9 shrink-0 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground"
          >
            <Mic className="size-4.5" />
          </button>

          <button
            type="button"
            onClick={submit}
            disabled={value.trim() === ''}
            aria-label="Enviar mensagem"
            className={cn(
              'flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-card transition-all duration-200',
              'hover:bg-primary-hover active:scale-95',
              'disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-primary',
            )}
          >
            <ArrowUp className="size-5" />
          </button>
        </>
      )}
    </div>
  )
}
