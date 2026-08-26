import { FileQuestion } from 'lucide-react'
import { LinkButton } from '@/components/ui/LinkButton'
import { routePaths } from '@/routes/routePaths'

export function NotFound() {
  return (
    <div className="flex h-full flex-col items-center justify-center gap-4 py-24 text-center">
      <FileQuestion className="h-12 w-12 text-muted-foreground" />
      <div>
        <h1 className="text-xl font-semibold">Page not found</h1>
        <p className="mt-1 text-sm text-muted-foreground">The page you're looking for doesn't exist or was moved.</p>
      </div>
      <LinkButton to={routePaths.dashboard}>Back to dashboard</LinkButton>
    </div>
  )
}
