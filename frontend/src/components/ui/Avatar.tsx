import * as RadixAvatar from '@radix-ui/react-avatar'
import { cn } from '@/utils/cn'

function initialsFrom(name: string): string {
  const parts = name.trim().split(/\s+/)
  return parts
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}

// Deterministic per-name accent so avatar-heavy lists (staff, students, guardians) read
// as a lively set of individuals rather than a wall of identical primary-tinted circles.
const AVATAR_PALETTE = [
  'bg-primary/10 text-primary',
  'bg-violet-500/10 text-violet-600 dark:text-violet-400',
  'bg-rose-500/10 text-rose-600 dark:text-rose-400',
  'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
  'bg-success/10 text-success',
  'bg-warning/10 text-warning',
  'bg-amber-500/10 text-amber-600 dark:text-amber-400',
  'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
]

function paletteFor(name: string): string {
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = (hash * 31 + name.charCodeAt(i)) >>> 0
  return AVATAR_PALETTE[hash % AVATAR_PALETTE.length]
}

export function Avatar({ name, src, className, size = 40 }: { name: string; src?: string | null; className?: string; size?: number }) {
  return (
    <RadixAvatar.Root
      className={cn('inline-flex shrink-0 select-none items-center justify-center overflow-hidden rounded-full', paletteFor(name || '?'), className)}
      style={{ width: size, height: size }}
    >
      {src && <RadixAvatar.Image src={src} alt={name} className="h-full w-full object-cover" />}
      <RadixAvatar.Fallback className="flex h-full w-full items-center justify-center text-sm font-medium" delayMs={src ? 400 : 0}>
        {initialsFrom(name) || '?'}
      </RadixAvatar.Fallback>
    </RadixAvatar.Root>
  )
}
