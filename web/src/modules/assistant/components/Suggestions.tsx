import { SUGGESTIONS, TINT_CLASSES } from './suggestions'

export function Suggestions({ onSelect }: { onSelect: (prompt: string) => void }) {
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      {SUGGESTIONS.map((suggestion) => {
        const tint = TINT_CLASSES[suggestion.tint]

        return (
          <button
            key={suggestion.title}
            type="button"
            onClick={() => onSelect(suggestion.prompt)}
            className="group flex items-start gap-3 rounded-xl border border-surface-3 bg-surface p-4 text-left shadow-card transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-raised"
          >
            <span
              className={`flex size-9 shrink-0 items-center justify-center rounded-lg ${tint.iconBg} ${tint.icon} transition-transform duration-200 group-hover:scale-105`}
            >
              <suggestion.icon className="size-4.5" aria-hidden="true" />
            </span>
            <span className="min-w-0">
              <span className="block text-sm font-medium text-foreground">{suggestion.title}</span>
              <span className="mt-0.5 block truncate text-[13px] text-muted">{suggestion.description}</span>
            </span>
          </button>
        )
      })}
    </div>
  )
}
