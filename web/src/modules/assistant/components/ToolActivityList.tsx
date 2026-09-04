import { Check, X } from 'lucide-react'
import { Spinner } from '@/shared/design-system'
import type { ToolActivity } from '../hooks/useChatStream'
import { toolDoneLabel, toolRunningLabel } from './tool-labels'
import { cn } from '@/shared/utils/cn'

export function ToolActivityList({ activities }: { activities: ToolActivity[] }) {
  if (activities.length === 0) return null

  return (
    <div className="flex flex-col gap-1.5 pl-11">
      {activities.map((activity) => (
        <div
          key={activity.key}
          className="flex items-center gap-2 rounded-lg border border-surface-3 bg-surface px-3 py-2 text-[13px] shadow-card"
        >
          {activity.status === 'running' ? (
            <Spinner className="size-3.5 text-primary" />
          ) : activity.status === 'done' ? (
            <span className="flex size-4 items-center justify-center rounded-full bg-success-soft text-success">
              <Check className="size-3" aria-hidden="true" />
            </span>
          ) : (
            <span className="flex size-4 items-center justify-center rounded-full bg-danger-soft text-danger">
              <X className="size-3" aria-hidden="true" />
            </span>
          )}

          <span className={cn('truncate', activity.status === 'error' ? 'text-danger' : 'text-muted')}>
            {activity.status === 'done' ? toolDoneLabel(activity.name) : toolRunningLabel(activity.name)}
          </span>
        </div>
      ))}
    </div>
  )
}
