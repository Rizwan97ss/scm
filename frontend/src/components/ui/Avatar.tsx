import * as RadixAvatar from '@radix-ui/react-avatar'
import { cn } from '@/utils/cn'

function initialsFrom(name: string): string {
  const parts = name.trim().split(/\s+/)
  return parts
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}

export function Avatar({ name, src, className, size = 40 }: { name: string; src?: string | null; className?: string; size?: number }) {
  return (
    <RadixAvatar.Root
      className={cn('inline-flex shrink-0 select-none items-center justify-center overflow-hidden rounded-full bg-primary/10', className)}
      style={{ width: size, height: size }}
    >
      {src && <RadixAvatar.Image src={src} alt={name} className="h-full w-full object-cover" />}
      <RadixAvatar.Fallback
        className="flex h-full w-full items-center justify-center text-sm font-medium text-primary"
        delayMs={src ? 400 : 0}
      >
        {initialsFrom(name) || '?'}
      </RadixAvatar.Fallback>
    </RadixAvatar.Root>
  )
}
