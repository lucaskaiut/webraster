import type { ComponentProps } from 'react'
import { Link } from 'react-router'
import { cn } from '@/shared/utils/cn'
import { Spinner } from './Spinner'

type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger'
type ButtonSize = 'sm' | 'md'

export interface ButtonProps extends ComponentProps<'button'> {
  variant?: ButtonVariant
  size?: ButtonSize
  loading?: boolean
}

const VARIANTS: Record<ButtonVariant, string> = {
  primary: 'bg-primary text-primary-foreground shadow-card hover:bg-primary-hover',
  secondary: 'bg-surface-2 text-foreground hover:bg-surface-3',
  ghost: 'text-muted hover:bg-surface-2 hover:text-foreground',
  danger: 'bg-danger text-white shadow-card hover:bg-danger-hover',
}

const SIZES: Record<ButtonSize, string> = {
  sm: 'h-8 px-3 text-[13px]',
  md: 'h-10 px-4 text-sm',
}

const BASE_CLASSES =
  'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg font-medium transition-colors select-none'

export function buttonClasses(
  variant: ButtonVariant = 'primary',
  size: ButtonSize = 'md',
  className?: string,
): string {
  return cn(BASE_CLASSES, VARIANTS[variant], SIZES[size], className)
}

export function Button({
  variant = 'primary',
  size = 'md',
  loading = false,
  disabled,
  className,
  children,
  type = 'button',
  ...props
}: ButtonProps) {
  return (
    <button
      type={type}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={cn(
        buttonClasses(variant, size, className),
        'disabled:pointer-events-none disabled:opacity-60',
      )}
      {...props}
    >
      {loading && <Spinner className="size-4" />}
      {children}
    </button>
  )
}

export interface ButtonLinkProps extends ComponentProps<typeof Link> {
  variant?: ButtonVariant
  size?: ButtonSize
}

export function ButtonLink({ variant = 'primary', size = 'md', className, ...props }: ButtonLinkProps) {
  return <Link className={buttonClasses(variant, size, className)} {...props} />
}
