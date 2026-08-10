import { School } from 'lucide-react'
import { LoginForm } from '../components/LoginForm'
import { useTheme } from '@/context/ThemeContext'

export function LoginPage() {
  const { appName, logoUrl } = useTheme()

  return (
    <div className="flex min-h-svh items-center justify-center bg-muted/40 px-4 py-12">
      <div className="w-full max-w-sm">
        <div className="mb-8 flex flex-col items-center gap-3 text-center">
          {logoUrl ? (
            <img src={logoUrl} alt={appName} className="h-12 w-12 rounded object-contain" />
          ) : (
            <span className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary text-primary-foreground">
              <School className="h-6 w-6" />
            </span>
          )}
          <div>
            <h1 className="text-lg font-semibold">{appName}</h1>
            <p className="text-sm text-muted-foreground">Sign in to your account</p>
          </div>
        </div>
        <div className="rounded-lg border border-border bg-card p-6 shadow-sm">
          <LoginForm />
        </div>
      </div>
    </div>
  )
}
