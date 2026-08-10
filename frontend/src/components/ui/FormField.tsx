import type { ReactNode } from 'react'
import { cn } from '@/utils/cn'

export interface FormFieldProps {
  label?: string
  htmlFor?: string
  error?: string
  required?: boolean
  hint?: string
  className?: string
  children: ReactNode
}

export function FormField({ label, htmlFor, error, required, hint, className, children }: FormFieldProps) {
  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      {label && (
        <label htmlFor={htmlFor} className="text-sm font-medium text-foreground">
          {label}
          {required && (
            <span className="ml-0.5 text-destructive" aria-hidden="true">
              *
            </span>
          )}
        </label>
      )}
      {children}
      {hint && !error && <p className="text-xs text-muted-foreground">{hint}</p>}
      {error && (
        <p className="text-xs text-destructive" role="alert">
          {error}
        </p>
      )}
    </div>
  )
}
