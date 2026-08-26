import * as RadixCheckbox from '@radix-ui/react-checkbox'
import { Check } from 'lucide-react'
import { cn } from '@/utils/cn'

export interface CheckboxProps {
  checked?: boolean
  onCheckedChange?: (checked: boolean) => void
  disabled?: boolean
  id?: string
  className?: string
  'aria-label'?: string
}

export function Checkbox({ checked, onCheckedChange, disabled, id, className, ...rest }: CheckboxProps) {
  return (
    <RadixCheckbox.Root
      id={id}
      checked={checked}
      onCheckedChange={(state) => onCheckedChange?.(state === true)}
      disabled={disabled}
      aria-label={rest['aria-label']}
      className={cn(
        'flex h-5 w-5 items-center justify-center rounded border border-input bg-card',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1',
        'data-[state=checked]:bg-primary data-[state=checked]:border-primary',
        'disabled:cursor-not-allowed disabled:opacity-50',
        className
      )}
    >
      <RadixCheckbox.Indicator>
        <Check className="h-3.5 w-3.5 text-primary-foreground" />
      </RadixCheckbox.Indicator>
    </RadixCheckbox.Root>
  )
}
