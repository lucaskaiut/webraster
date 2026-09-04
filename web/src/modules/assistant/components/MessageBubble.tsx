import { Sparkles } from 'lucide-react'
import type { AssistantMessage } from '@/shared/types/models'
import { cn } from '@/shared/utils/cn'
import { Markdown } from './Markdown'

export function AssistantAvatar({ size = 'md' }: { size?: 'md' | 'sm' }) {
  return (
    <span
      className={cn(
        'flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary to-primary-hover text-primary-foreground shadow-card',
        size === 'md' ? 'size-8' : 'size-7',
      )}
    >
      <Sparkles className={size === 'md' ? 'size-4' : 'size-3.5'} aria-hidden="true" />
    </span>
  )
}

export function MessageBubble({
  message,
  streaming,
}: {
  message: AssistantMessage
  streaming?: boolean
}) {
  if (message.role === 'user') {
    return (
      <div className="animate-message-in flex justify-end">
        <div className="max-w-[80%] rounded-2xl rounded-br-md bg-primary px-4 py-2.5 text-[15px] text-primary-foreground shadow-card">
          <p className="whitespace-pre-wrap break-words">{message.content}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="animate-message-in flex items-start gap-3">
      <AssistantAvatar />
      <div className="min-w-0 flex-1 pt-0.5">
        {message.content !== '' ? (
          <div className="text-[15px] leading-relaxed text-foreground">
            <Markdown content={message.content} />
            {streaming && <span className="animate-blink ml-0.5 inline-block h-4 w-0.5 translate-y-0.5 bg-primary" aria-hidden="true" />}
          </div>
        ) : (
          streaming && (
            <span className="inline-flex items-center gap-1.5" aria-label="Assistente digitando">
              <span className="size-1.5 animate-bounce rounded-full bg-muted [animation-delay:0ms]" />
              <span className="size-1.5 animate-bounce rounded-full bg-muted [animation-delay:150ms]" />
              <span className="size-1.5 animate-bounce rounded-full bg-muted [animation-delay:300ms]" />
            </span>
          )
        )}
      </div>
    </div>
  )
}
