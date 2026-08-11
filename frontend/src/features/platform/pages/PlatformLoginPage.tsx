import { ShieldCheck } from 'lucide-react'
import { PlatformLoginForm } from '../components/PlatformLoginForm'
import { env } from '@/config/env'

/**
 * Super Admin's own login — deliberately not ThemeContext-driven like the
 * tenant LoginPage (no single tenant's branding applies here; this is the
 * platform operator's own console, reached on the central domain).
 */
export function PlatformLoginPage() {
  return (
    <div className="flex min-h-svh items-center justify-center bg-muted/40 px-4 py-12">
      <div className="w-full max-w-sm">
        <div className="mb-8 flex flex-col items-center gap-3 text-center">
          <span className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary text-primary-foreground">
            <ShieldCheck className="h-6 w-6" />
          </span>
          <div>
            <h1 className="text-lg font-semibold">{env.appName} Platform</h1>
            <p className="text-sm text-muted-foreground">Sign in as a platform administrator</p>
          </div>
        </div>
        <div className="rounded-lg border border-border bg-card p-6 shadow-sm">
          <PlatformLoginForm />
        </div>
      </div>
    </div>
  )
}
