import { cloneElement, isValidElement, type ReactNode } from 'react'
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
  // Only cloning a single, genuine element (never a fragment/array — a few
  // call sites pass more than one control) so screen readers get
  // aria-describedby/aria-invalid wired automatically for the common case
  // without risking a crash on the less common one; those just render
  // exactly as before.
  const describedById = htmlFor && (error ? `${htmlFor}-error` : hint ? `${htmlFor}-hint` : undefined)
  const control =
    htmlFor && isValidElement<{ 'aria-describedby'?: string; 'aria-invalid'?: boolean }>(children)
      ? cloneElement(children, {
          'aria-describedby': describedById,
          'aria-invalid': !!error,
        })
      : children

  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      {label && (
        <label htmlFor={htmlFor} className="text-sm font-medium text-foreground">
          {label}
          {required && (
            <span className="ms-0.5 text-destructive" aria-hidden="true">
              *
            </span>
          )}
        </label>
      )}
      {control}
      {hint && !error && (
        <p id={htmlFor ? `${htmlFor}-hint` : undefined} className="text-xs text-muted-foreground">
          {hint}
        </p>
      )}
      {error && (
        <p id={htmlFor ? `${htmlFor}-error` : undefined} className="text-xs text-destructive" role="alert">
          {error}
        </p>
      )}
    </div>
  )
}
