import { ShieldAlert } from 'lucide-react'
import { LinkButton } from '@/components/ui/LinkButton'
import { routePaths } from '@/routes/routePaths'

export function Forbidden() {
  return (
    <div className="flex h-full flex-col items-center justify-center gap-4 py-24 text-center">
      <ShieldAlert className="h-12 w-12 text-muted-foreground" />
      <div>
        <h1 className="text-xl font-semibold">Access denied</h1>
        <p className="mt-1 text-sm text-muted-foreground">You don't have permission to view this page.</p>
      </div>
      <LinkButton to={routePaths.dashboard}>Back to dashboard</LinkButton>
    </div>
  )
}
