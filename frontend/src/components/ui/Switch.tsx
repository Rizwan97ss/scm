import * as RadixSwitch from '@radix-ui/react-switch'
import { cn } from '@/utils/cn'

export interface SwitchProps {
  checked?: boolean
  onCheckedChange?: (checked: boolean) => void
  disabled?: boolean
  id?: string
  'aria-label'?: string
}

export function Switch({ checked, onCheckedChange, disabled, id, ...rest }: SwitchProps) {
  return (
    <RadixSwitch.Root
      id={id}
      checked={checked}
      onCheckedChange={onCheckedChange}
      disabled={disabled}
      aria-label={rest['aria-label']}
      className={cn(
        'relative h-6 w-11 shrink-0 rounded-full bg-muted transition-colors',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1',
        'data-[state=checked]:bg-primary',
        'disabled:cursor-not-allowed disabled:opacity-50'
      )}
    >
      <RadixSwitch.Thumb
        className={cn(
          'block h-5 w-5 translate-x-0.5 rounded-full bg-white shadow transition-transform',
          'data-[state=checked]:translate-x-[22px]'
        )}
      />
    </RadixSwitch.Root>
  )
}
