import type { ReactNode } from 'react'
import { School } from 'lucide-react'
import { LinkButton } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import { env } from '@/config/env'
import { cn } from '@/utils/cn'

/**
 * Shell for pre-auth, pre-tenant pages (signup wizard, future marketing
 * pages) — deliberately does NOT use ThemeContext/useTheme(). That context
 * resolves a specific tenant's branding (logo/colors), which makes no
 * sense before a school even exists yet; this uses static platform
 * branding (env.appName) instead.
 */
export function PublicLayout({
  children,
  step,
  totalSteps,
}: {
  children: ReactNode
  step?: number
  totalSteps?: number
}) {
  return (
    <div className="flex min-h-svh flex-col bg-muted/40">
      <header className="border-b border-border bg-card">
        <div className="mx-auto flex max-w-3xl items-center justify-between px-4 py-4 sm:px-6">
          <div className="flex items-center gap-2">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
              <School className="h-4 w-4" />
            </span>
            <span className="text-sm font-semibold">{env.appName}</span>
          </div>
          <LinkButton to={routePaths.login} variant="ghost" size="sm">
            Already have an account? Sign in
          </LinkButton>
        </div>
      </header>

      {step !== undefined && totalSteps !== undefined && (
        <div className="mx-auto w-full max-w-3xl px-4 pt-6 sm:px-6">
          <div className="flex items-center gap-2" role="progressbar" aria-valuenow={step + 1} aria-valuemin={1} aria-valuemax={totalSteps}>
            {Array.from({ length: totalSteps }, (_, i) => (
              <span key={i} className={cn('h-1.5 flex-1 rounded-full', i <= step ? 'bg-primary' : 'bg-border')} />
            ))}
          </div>
        </div>
      )}

      <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col px-4 py-8 sm:px-6">{children}</main>
    </div>
  )
}
