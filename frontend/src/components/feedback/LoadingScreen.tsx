import { Loader2 } from 'lucide-react'

export function LoadingScreen({ label = 'Loading…' }: { label?: string }) {
  return (
    <div className="flex h-svh w-full flex-col items-center justify-center gap-3 bg-background text-muted-foreground">
      <Loader2 className="h-6 w-6 animate-spin" aria-hidden="true" />
      <p className="text-sm">{label}</p>
    </div>
  )
}
