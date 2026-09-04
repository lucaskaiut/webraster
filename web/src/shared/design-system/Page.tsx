import type { ComponentProps, ReactNode } from 'react'
import { cn } from '@/shared/utils/cn'
import { Breadcrumb, type BreadcrumbItem } from './Breadcrumb'

export function Page({ className, ...props }: ComponentProps<'div'>) {
  return <div className={cn('flex flex-col gap-6 pb-12', className)} {...props} />
}

export function PageHeader({
  title,
  description,
  breadcrumb,
  actions,
}: {
  title: string
  description?: string
  breadcrumb?: BreadcrumbItem[]
  actions?: ReactNode
}) {
  return (
    <header className="flex flex-col gap-3">
      {breadcrumb && <Breadcrumb items={breadcrumb} />}
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <h1 className="text-xl font-semibold tracking-tight text-foreground">{title}</h1>
          {description && <p className="mt-1 text-sm text-muted">{description}</p>}
        </div>
        {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
      </div>
    </header>
  )
}

export function PageContent({ className, ...props }: ComponentProps<'div'>) {
  return <div className={cn('flex flex-col gap-5', className)} {...props} />
}

export function Container({ className, ...props }: ComponentProps<'div'>) {
  return <div className={cn('mx-auto w-full px-6 lg:px-10', className)} {...props} />
}

export function Section({
  title,
  description,
  children,
  className,
}: {
  title?: string
  description?: string
  children: ReactNode
  className?: string
}) {
  return (
    <section className={cn('space-y-4', className)}>
      {(title || description) && (
        <div>
          {title && <h2 className="text-sm font-semibold text-foreground">{title}</h2>}
          {description && <p className="mt-0.5 text-[13px] text-muted">{description}</p>}
        </div>
      )}
      {children}
    </section>
  )
}
